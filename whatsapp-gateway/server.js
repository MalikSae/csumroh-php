import express from 'express';
import cors from 'cors';
import qrcode from 'qrcode';
import pino from 'pino';
import { createRequire } from 'module';
import { fileURLToPath } from 'url';
import path from 'path';
import fs from 'fs';
import http from 'http';
import sharp from 'sharp';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

import {
  default as makeWASocket,
  useMultiFileAuthState,
  DisconnectReason,
  fetchLatestBaileysVersion,
  makeCacheableSignalKeyStore,
  getContentType,
  downloadMediaMessage,
  Browsers,
  ALL_WA_PATCH_NAMES,
  USyncQuery,
  USyncUser
} from '@whiskeysockets/baileys';

const app = express();
const PORT = process.env.PORT || 3001;
const WEBHOOK_URL = process.env.WEBHOOK_URL || 'http://localhost/csumroh/api/whatsapp_webhook.php';
const GATEWAY_SECRET = process.env.GATEWAY_SECRET || 'csumroh-secret-key-2026';

app.use(cors());
app.use(express.json());

// In-memory store for brand sessions
// brandId -> { sock, qrCode, status, phoneNumber, reconnectAttempts }
const activeSessions = new Map();

// In-memory LID <-> PN mapping cache (persisted via signalRepository in v7)
// lidJid -> phoneJid (e.g. "123@lid" -> "628xxx@s.whatsapp.net")
const lidPnCache = new Map();

// Helper: send data to PHP webhook
async function notifyWebhook(payload) {
  try {
    const url = new URL(WEBHOOK_URL);
    const body = JSON.stringify(payload);
    
    const req = http.request({
      hostname: url.hostname,
      port: url.port || 80,
      path: url.pathname + url.search,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(body),
        'X-Gateway-Secret': GATEWAY_SECRET
      },
      timeout: 5000
    }, (res) => {
      res.on('data', () => {}); // consume
    });

    req.on('error', (err) => {
      console.warn(`[Webhook Warning] Failed to notify PHP: ${err.message}`);
    });

    req.write(body);
    req.end();
  } catch (err) {
    console.warn(`[Webhook Error] ${err.message}`);
  }
}

// Helper: normalize JID to standard phone format (08xxxx)
function normalizePhone(jid) {
  if (!jid) return '';
  // Never treat LID (Meta encrypted identifier) as a phone number
  if (jid.endsWith('@lid')) return '';
  const num = jid.split('@')[0].split(':')[0].replace(/[^0-9]/g, '');
  if (!num) return '';
  if (num.startsWith('62')) return '0' + num.substring(2);
  if (num.startsWith('08')) return num;
  if (num === '0') return '';
  return num;
}

// Helper: resolve LID to phone using multiple strategies (v7 lidMapping + cache + senderPn)
async function resolveLidToPhone(jid, sock) {
  if (!jid || !jid.endsWith('@lid')) return '';

  // Strategy 1: In-memory LID->PN cache (fastest)
  if (lidPnCache.has(jid)) {
    return normalizePhone(lidPnCache.get(jid));
  }

  // Strategy 2: Baileys v7 signalRepository.lidMapping (persisted across restarts)
  if (sock?.signalRepository?.lidMapping?.getPNForLID) {
    try {
      const pnJid = await sock.signalRepository.lidMapping.getPNForLID(jid);
      if (pnJid) {
        lidPnCache.set(jid, pnJid); // cache it
        console.log(`[LIDMapping] Resolved via signalRepository: ${jid} → ${pnJid}`);
        return normalizePhone(pnJid);
      }
    } catch (e) {
      // ignore, try next
    }
  }

  return '';
}

// Helper: save LID<->PN mapping to cache and Baileys store
function cacheLidMapping(lidJid, pnJid) {
  if (!lidJid || !pnJid) return;
  lidPnCache.set(lidJid, pnJid);
}

// Helper: extract message text from various Baileys message types
function extractMessageText(msg) {
  if (!msg.message) return '';
  const type = getContentType(msg.message);
  if (!type) return '';

  if (type === 'conversation') return msg.message.conversation || '';
  if (type === 'extendedTextMessage') return msg.message.extendedTextMessage?.text || '';
  if (type === 'imageMessage') return msg.message.imageMessage?.caption || '[Gambar]';
  if (type === 'videoMessage') return msg.message.videoMessage?.caption || '[Video]';
  if (type === 'audioMessage') return msg.message.audioMessage?.ptt ? '[Voice Note]' : '[Audio]';
  if (type === 'documentMessage') return msg.message.documentMessage?.title || '[Dokumen]';
  if (type === 'buttonsResponseMessage') return msg.message.buttonsResponseMessage?.selectedDisplayText || '';
  if (type === 'templateButtonReplyMessage') return msg.message.templateButtonReplyMessage?.selectedDisplayText || '';
  return `[${type}]`;
}

// Helper: extract Meta CTWA Ad Referral Marker
function extractAdReferral(msg) {
  try {
    const ext = msg.message?.extendedTextMessage;
    const contextInfo = ext?.contextInfo || msg.messageContextInfo;
    if (!contextInfo) return null;

    const referral = contextInfo.referral || null;
    const externalAdReply = contextInfo.externalAdReply || null;

    if (referral || externalAdReply) {
      return {
        headline: referral?.headline || externalAdReply?.title || null,
        body: referral?.body || externalAdReply?.body || null,
        source_type: referral?.source_type || 'ad',
        source_id: referral?.source_id || null,
        source_url: referral?.source_url || externalAdReply?.sourceUrl || null,
        ctwa_clid: referral?.ctwa_clid || null,
        media_url: externalAdReply?.mediaUrl || null,
        thumbnail_url: externalAdReply?.thumbnailUrl || null
      };
    }
  } catch (e) {
    // ignore
  }
  return null;
}

// Initialize or resume Baileys session for a brand
async function startBrandSession(brandId, reconnectAttempts = 0) {
  brandId = parseInt(brandId, 10);
  if (isNaN(brandId)) throw new Error('Invalid Brand ID');

  const existing = activeSessions.get(brandId);
  if (existing && existing.status === 'connected') return existing;
  if (existing && existing.status === 'qr_ready' && existing.qrCode) return existing;

  // Clean up old socket listeners if existing
  if (existing && existing.sock) {
    try { existing.sock.ev.removeAllListeners(); } catch (e) {}
  }

  const sessionDir = path.join(__dirname, 'sessions', `brand_${brandId}`);
  if (!fs.existsSync(sessionDir)) {
    fs.mkdirSync(sessionDir, { recursive: true });
  }

  const { state, saveCreds } = await useMultiFileAuthState(sessionDir);
  console.log(`[Brand ${brandId}] Initializing Baileys WhatsApp socket (v7)...`);

  const sock = makeWASocket({
    auth: {
      creds: state.creds,
      keys: makeCacheableSignalKeyStore(state.keys, pino({ level: 'silent' }))
    },
    logger: pino({ level: 'silent' }),
    browser: Browsers.ubuntu('Chrome'),
    syncFullHistory: true,
    generateHighQualityLinkPreview: true,
    // Baileys v7: mark all messages as read to reduce unnecessary acks
    markOnlineOnConnect: false
  });

  const sessionData = {
    brandId,
    sock,
    qrCode: existing?.qrCode || null,
    status: existing?.qrCode ? 'qr_ready' : 'connecting',
    phoneNumber: null,
    reconnectAttempts
  };
  activeSessions.set(brandId, sessionData);

  // Save credentials on update
  sock.ev.on('creds.update', saveCreds);

  // Connection Update (QR, Open, Close)
  sock.ev.on('connection.update', async (update) => {
    const { connection, lastDisconnect, qr } = update;

    if (qr) {
      try {
        const qrDataUrl = await qrcode.toDataURL(qr, { margin: 1, scale: 6 });
        sessionData.qrCode = qrDataUrl;
        sessionData.status = 'qr_ready';
        console.log(`[Brand ${brandId}] QR Code generated successfully`);
        notifyWebhook({
          event: 'connection_status',
          brand_id: brandId,
          status: 'qr_ready',
          qr_code: qrDataUrl,
          phone_number: null
        });
      } catch (err) {
        console.error(`[Brand ${brandId}] QR Generation error:`, err);
      }
    }

    if (connection === 'open') {
      sessionData.status = 'connected';
      sessionData.qrCode = null;
      sessionData.reconnectAttempts = 0;
      
      const userJid = sock.user?.id || '';
      sessionData.phoneNumber = normalizePhone(userJid);
      console.log(`[Brand ${brandId}] WhatsApp Connected! Phone: ${sessionData.phoneNumber}`);

      notifyWebhook({
        event: 'connection_status',
        brand_id: brandId,
        status: 'connected',
        qr_code: null,
        phone_number: sessionData.phoneNumber
      });

      // Auto-resync contacts from WhatsApp address book on connect
      // This populates contacts.upsert with c.jid for address-book contacts
      setTimeout(async () => {
        try {
          console.log(`[Brand ${brandId}] Auto-triggering resyncAppState for contacts...`);
          await sock.resyncAppState(ALL_WA_PATCH_NAMES, true);
        } catch (e) {
          console.log(`[Brand ${brandId}] resyncAppState skipped:`, e.message);
        }

        // After resync, scan all known LID contacts via lidMapping (v7 feature)
        // This resolves historical contacts WITHOUT needing address book
        setTimeout(() => bulkResolveLIDsFromMapping(brandId, sock), 3000);
      }, 3000);
    }

    if (connection === 'close') {
      const statusCode = lastDisconnect?.error?.output?.statusCode;
      const shouldReconnect = statusCode !== DisconnectReason.loggedOut;
      
      console.log(`[Brand ${brandId}] Connection closed. StatusCode: ${statusCode}, ShouldReconnect: ${shouldReconnect}`);

      if (statusCode === DisconnectReason.loggedOut) {
        sessionData.status = 'disconnected';
        sessionData.qrCode = null;
        sessionData.phoneNumber = null;
        activeSessions.delete(brandId);
        try { fs.rmSync(sessionDir, { recursive: true, force: true }); } catch (e) {}
        notifyWebhook({
          event: 'connection_status',
          brand_id: brandId,
          status: 'disconnected',
          qr_code: null,
          phone_number: null
        });
      } else if (shouldReconnect) {
        const attempts = (sessionData.reconnectAttempts || 0) + 1;
        sessionData.status = sessionData.qrCode ? 'qr_ready' : 'connecting';
        activeSessions.delete(brandId);
        const delay = Math.min(attempts * 1500, 5000);
        console.log(`[Brand ${brandId}] Reconnecting in ${delay}ms (attempt ${attempts})...`);
        setTimeout(() => startBrandSession(brandId, attempts), delay);
      } else {
        sessionData.status = 'disconnected';
        activeSessions.delete(brandId);
        notifyWebhook({
          event: 'connection_status',
          brand_id: brandId,
          status: 'disconnected',
          qr_code: null,
          phone_number: null
        });
      }
    }
  });

  // Handle Full Past History Sync from WhatsApp Multi-Device
  sock.ev.on('messaging-history.set', async ({ chats, contacts, messages, isLatest }) => {
    console.log(`[Brand ${brandId}] messaging-history.set! Messages: ${messages?.length || 0}, Contacts: ${contacts?.length || 0}`);

    const contactBatch = [];

    // 1. Process contacts array from history sync
    if (contacts && Array.isArray(contacts)) {
      for (const c of contacts) {
        if (!c.id || c.id.endsWith('@g.us')) continue;
        const name = c.name || c.notify || c.verifiedName || '';
        // c.jid = phone JID only if in address book / mapping known
        let phone = normalizePhone(c.jid || c.id);
        // If this is a LID contact, try v7 lidMapping
        if (!phone && c.id.endsWith('@lid')) {
          phone = await resolveLidToPhone(c.id, sock);
          // Cache the LID <-> PN mapping if c.jid was provided
          if (c.jid) cacheLidMapping(c.id, c.jid);
        }
        if (name || phone) {
          contactBatch.push({ jid: c.id, lid: c.lid || null, name, phone });
        }
      }
    }

    // 2. Extract senderPn from messages to resolve LID → real phone
    // senderPn is embedded in message key for EVERY incoming LID message
    const lidPhoneMap = {};
    const formattedMessages = [];

    if (messages && messages.length > 0) {
      for (const msg of messages) {
        const jid = msg.key?.remoteJid;
        if (!jid || jid.endsWith('@g.us') || jid === 'status@broadcast') continue;

        let phone = normalizePhone(jid);
        const senderPn = msg.key?.senderPn || msg.key?.participantPn;
        if (!phone && senderPn) {
          phone = normalizePhone(senderPn);
          if (phone && jid.endsWith('@lid') && !lidPhoneMap[jid]) {
            lidPhoneMap[jid] = { phone, name: msg.pushName || '' };
            // Also save to in-memory cache
            cacheLidMapping(jid, senderPn);
          }
        }
        // If still no phone, try lidMapping from v7 store
        if (!phone && jid.endsWith('@lid')) {
          phone = await resolveLidToPhone(jid, sock);
        }

        const text = extractMessageText(msg);
        if (!text) continue;

        formattedMessages.push({
          message_id: msg.key?.id,
          remote_jid: jid,
          phone: phone,
          sender_name: msg.pushName || null,
          is_from_me: msg.key?.fromMe ? 1 : 0,
          message_type: getContentType(msg.message) || 'conversation',
          message_text: text,
          timestamp: typeof msg.messageTimestamp === 'number'
            ? msg.messageTimestamp
            : (msg.messageTimestamp?.low || Math.floor(Date.now() / 1000)),
          meta_referral: extractAdReferral(msg)
        });
      }
    }

    // 3. Merge senderPn-resolved phones into contact batch
    for (const [jid, { phone, name }] of Object.entries(lidPhoneMap)) {
      contactBatch.push({ jid, lid: null, name, phone });
    }
    if (contactBatch.length > 0) {
      console.log(`[Brand ${brandId}] history contacts resolved: ${contactBatch.length}`);
      notifyWebhook({ event: 'contacts_bulk', brand_id: brandId, contacts: contactBatch });
    }

    // 4. Send history messages
    if (formattedMessages.length > 0) {
      const currentSessionPhone = sessionData.phoneNumber || normalizePhone(sock.user?.id);
      console.log(`[Brand ${brandId}] Forwarding ${formattedMessages.length} past messages (session: ${currentSessionPhone})...`);
      for (let i = 0; i < formattedMessages.length; i += 50) {
        notifyWebhook({
          event: 'history_sync',
          brand_id: brandId,
          session_phone: currentSessionPhone,
          messages: formattedMessages.slice(i, i + 50)
        });
      }
    }
  });


  // Handle Real-time Incoming & Outgoing Messages
  sock.ev.on('messages.upsert', async ({ messages, type }) => {
    if (!messages || messages.length === 0) return;

    for (const msg of messages) {
      const jid = msg.key?.remoteJid;
      if (!jid || jid.endsWith('@g.us') || jid === 'status@broadcast') continue;

      // Check if this is a REVOKE protocol message (sender deleted the message)
      const proto = msg.message?.protocolMessage;
      if (proto && (proto.type === 0 || proto.type === 'REVOKE' || String(proto.type) === '0')) {
        const revokedId = proto.key?.id;
        if (revokedId) {
          console.log(`[Brand ${brandId}] Incoming message REVOKED: ${revokedId} in chat ${jid}`);
          notifyWebhook({
            event: 'message_deleted',
            brand_id: brandId,
            message_id: revokedId,
            remote_jid: jid,
            timestamp: typeof msg.messageTimestamp === 'number'
              ? msg.messageTimestamp
              : (msg.messageTimestamp?.low || Math.floor(Date.now() / 1000))
          });
          continue; // Do not process revoke protocol as a normal message
        }
      }

      let phone = normalizePhone(jid);

      // For LID contacts: Strategy 1 = senderPn in message key (most reliable, works without address book)
      const senderPn = msg.key?.senderPn || msg.key?.participantPn;
      if (!phone && senderPn) {
        phone = normalizePhone(senderPn);
        if (phone && jid.endsWith('@lid')) {
          console.log(`[Brand ${brandId}] LID resolved via senderPn: ${jid} → ${phone}`);
          cacheLidMapping(jid, senderPn);
          // Immediately update DB record for this prospect
          notifyWebhook({
            event: 'contact_update',
            brand_id: brandId,
            jid: jid,
            lid: null,
            name: msg.pushName || '',
            phone: phone
          });
        }
      }

      // Strategy 2: lidMapping from v7 signalRepository (for historical contacts)
      if (!phone && jid.endsWith('@lid')) {
        phone = await resolveLidToPhone(jid, sock);
        if (phone) {
          console.log(`[Brand ${brandId}] LID resolved via signalRepository: ${jid} → ${phone}`);
          notifyWebhook({
            event: 'contact_update',
            brand_id: brandId,
            jid: jid,
            lid: null,
            name: msg.pushName || '',
            phone: phone
          });
        }
      }

      // Debug log for LID contacts
      if (jid.endsWith('@lid')) {
        console.log(`[Brand ${brandId}] LID msg key:`, JSON.stringify({
          id: msg.key?.id,
          remoteJid: jid,
          fromMe: msg.key?.fromMe,
          senderPn: msg.key?.senderPn,
          resolvedPhone: phone
        }));
      }

      // Check if message is a reaction (reactionMessage)
      if (msg.message?.reactionMessage) {
        const rMsg = msg.message.reactionMessage;
        const targetId = rMsg.key?.id;
        const emoji = rMsg.text || '';
        if (targetId) {
          console.log(`[Brand ${brandId}] Inbound ReactionMessage on ${targetId}: "${emoji}"`);
          notifyWebhook({
            event: 'message_reaction',
            brand_id: brandId,
            message_id: targetId,
            remote_jid: rMsg.key?.remoteJid || jid,
            reaction: emoji
          });
        }
        continue;
      }

      const text = extractMessageText(msg);
      const referral = extractAdReferral(msg);
      const currentSessionPhone = sessionData.phoneNumber || normalizePhone(sock.user?.id);
      const contentType = getContentType(msg.message) || 'conversation';
      let mediaUrl = null;

      // Extract Quoted / Replied Message Context if present
      const contextInfo = msg.message?.[contentType]?.contextInfo || msg.message?.extendedTextMessage?.contextInfo;
      const quotedMsgId = contextInfo?.stanzaId || null;
      let quotedText = null;
      let quotedSender = null;
      if (quotedMsgId) {
        const qm = contextInfo.quotedMessage;
        quotedText = qm?.conversation 
          || qm?.extendedTextMessage?.text 
          || qm?.imageMessage?.caption 
          || (qm?.imageMessage ? '[Gambar]' : null)
          || qm?.videoMessage?.caption
          || (qm?.videoMessage ? '[Video]' : null)
          || (qm?.audioMessage ? (qm.audioMessage.ptt ? '[Voice Note]' : '[Audio]') : null)
          || (qm?.documentMessage ? (qm.documentMessage.fileName || '[Dokumen]') : null)
          || null;
        quotedSender = contextInfo.participant ? normalizePhone(contextInfo.participant) : null;
      }

      // Download media sent by prospect (inbound) - image, document, audio/voice note, video
      const mediaTypes = ['imageMessage', 'documentMessage', 'audioMessage', 'videoMessage'];
      if (!msg.key?.fromMe && mediaTypes.includes(contentType)) {
        try {
          const buffer = await downloadMediaMessage(
            msg,
            'buffer',
            {},
            { logger: pino({ level: 'silent' }), reuploadRequest: sock.updateMediaMessage }
          );
          if (buffer) {
            let ext = 'bin';
            if (contentType === 'imageMessage') {
              ext = msg.message?.imageMessage?.mimetype?.includes('png') ? 'png' : 'jpg';
            } else if (contentType === 'videoMessage') {
              ext = 'mp4';
            } else if (contentType === 'audioMessage') {
              const mime = msg.message?.audioMessage?.mimetype || '';
              ext = mime.includes('ogg') ? 'ogg' : (mime.includes('mp4') ? 'm4a' : (mime.includes('mpeg') ? 'mp3' : 'ogg'));
            } else if (contentType === 'documentMessage') {
              const originalName = msg.message?.documentMessage?.fileName || '';
              ext = path.extname(originalName).replace(/^\./, '') || 'pdf';
            }
            const localFileName = `${brandId}_in_${msg.key?.id}_${Date.now()}.${ext}`;
            const uploadDir = path.join(__dirname, '..', 'uploads', 'chat');
            if (!fs.existsSync(uploadDir)) fs.mkdirSync(uploadDir, { recursive: true });
            fs.writeFileSync(path.join(uploadDir, localFileName), buffer);
            mediaUrl = `uploads/chat/${localFileName}`;
            console.log(`[Brand ${brandId}] Inbound media (${contentType}) saved: ${mediaUrl}`);
          }
        } catch (downloadErr) {
          console.warn(`[Brand ${brandId}] Inbound media download skipped:`, downloadErr.message);
        }
      }

      console.log(`[Brand ${brandId}] Message ${msg.key?.id} from ${phone || jid}: ${text.substring(0, 50)}`);

      const mappedMessageType = (contentType === 'imageMessage') ? 'image' 
        : ((contentType === 'documentMessage') ? 'document' 
        : ((contentType === 'audioMessage') ? 'audio' 
        : ((contentType === 'videoMessage') ? 'video' : contentType)));

      notifyWebhook({
        event: 'new_message',
        brand_id: brandId,
        session_phone: currentSessionPhone,
        message: {
          message_id: msg.key?.id,
          remote_jid: jid,
          phone: phone,
          sender_name: msg.pushName || null,
          is_from_me: msg.key?.fromMe ? 1 : 0,
          message_type: mappedMessageType,
          message_text: text,
          media_url: mediaUrl,
          quoted_message_id: quotedMsgId,
          quoted_text: quotedText,
          quoted_sender: quotedSender,
          timestamp: typeof msg.messageTimestamp === 'number'
            ? msg.messageTimestamp
            : (msg.messageTimestamp?.low || Math.floor(Date.now() / 1000)),
          meta_referral: referral
        }
      });

      // Background async fetch profile picture if available
      fetchContactProfilePic(sock, jid, phone).then(photoUrl => {
        if (photoUrl) {
          notifyWebhook({
            event: 'contact_update',
            brand_id: brandId,
            jid: jid,
            lid: null,
            name: msg.pushName || '',
            phone: phone,
            photo_url: photoUrl
          });
        }
      }).catch(() => {});
    }
  });

  // Handle Reactions from WhatsApp
  sock.ev.on('messages.reaction', async (reactions) => {
    if (!reactions || reactions.length === 0) return;
    for (const r of reactions) {
      const targetId = r.key?.id;
      const remoteJid = r.key?.remoteJid;
      const emoji = r.reaction?.text || '';
      if (!targetId) continue;
      console.log(`[Brand ${brandId}] Event messages.reaction on ${targetId}: "${emoji}"`);
      notifyWebhook({
        event: 'message_reaction',
        brand_id: brandId,
        message_id: targetId,
        remote_jid: remoteJid,
        reaction: emoji
      });
    }
  });

  // Handle Message Status Updates (Delivery ACK, Read Receipts / Blue Checkmarks)
  sock.ev.on('messages.update', async (updates) => {
    if (!updates || updates.length === 0) return;
    for (const u of updates) {
      const msgId = u.key?.id;
      const remoteJid = u.key?.remoteJid;
      const rawStatus = u.update?.status;
      if (!msgId) continue;

      let statusStr = null;
      if (rawStatus === 4 || rawStatus === 5 || rawStatus === 'READ' || rawStatus === 'PLAYED') {
        statusStr = 'read';
      } else if (rawStatus === 3 || rawStatus === 'DELIVERY_ACK') {
        statusStr = 'delivered';
      } else if (rawStatus === 2 || rawStatus === 'SERVER_ACK') {
        statusStr = 'sent';
      }

      if (statusStr) {
        console.log(`[Brand ${brandId}] Message ${msgId} status updated -> ${statusStr}`);
        notifyWebhook({
          event: 'message_status_update',
          brand_id: brandId,
          message_id: msgId,
          remote_jid: remoteJid,
          status: statusStr
        });
      }
    }
  });

  sock.ev.on('message-receipt.update', async (receipts) => {
    if (!receipts || receipts.length === 0) return;
    for (const r of receipts) {
      const msgId = r.key?.id;
      const remoteJid = r.key?.remoteJid;
      if (!msgId) continue;

      if (r.receipt?.readTimestamp) {
        console.log(`[Brand ${brandId}] Message ${msgId} READ receipt confirmed via message-receipt.update`);
        notifyWebhook({
          event: 'message_status_update',
          brand_id: brandId,
          message_id: msgId,
          remote_jid: remoteJid,
          status: 'read'
        });
      }
    }
  });

  // Handle Contact Upsert from WhatsApp (batch to avoid flooding PHP)
  sock.ev.on('contacts.upsert', async (contacts) => {
    console.log(`[Brand ${brandId}] contacts.upsert: ${contacts.length} contacts`);
    const batch = [];

    for (const c of contacts) {
      if (!c.id || c.id.endsWith('@g.us')) continue;
      const name = c.name || c.notify || c.verifiedName || '';
      
      // c.jid = phone JID if contact is in address book or mapping cached
      let phone = normalizePhone(c.jid || c.id);
      
      // For LID contacts without phone: try v7 lidMapping (KEY FEATURE!)
      if (!phone && c.id.endsWith('@lid')) {
        // Cache jid if present
        if (c.jid) cacheLidMapping(c.id, c.jid);
        phone = await resolveLidToPhone(c.id, sock);
      }

      if (name || phone) {
        batch.push({ jid: c.id, lid: c.lid || null, name, phone });
      }
    }
    if (batch.length > 0) {
      console.log(`[Brand ${brandId}] Sending contacts_bulk: ${batch.length} contacts`);
      notifyWebhook({ event: 'contacts_bulk', brand_id: brandId, contacts: batch });
    }
  });

  // Handle Contact Update from WhatsApp
  sock.ev.on('contacts.update', async (updates) => {
    console.log(`[Brand ${brandId}] contacts.update: ${updates.length} updates`);
    const batch = [];
    for (const c of updates) {
      if (!c.id || c.id.endsWith('@g.us')) continue;
      const name = c.name || c.notify || c.verifiedName || '';
      let phone = normalizePhone(c.jid || c.id);
      if (!phone && c.id.endsWith('@lid')) {
        if (c.jid) cacheLidMapping(c.id, c.jid);
        phone = await resolveLidToPhone(c.id, sock);
      }
      if (name || phone) {
        batch.push({ jid: c.id, lid: c.lid || null, name, phone });
      }
    }
    if (batch.length > 0) {
      console.log(`[Brand ${brandId}] Sending contacts_bulk update: ${batch.length}`);
      notifyWebhook({ event: 'contacts_bulk', brand_id: brandId, contacts: batch });
    }
  });

  // Handle Phone Number Share on LID (user explicitly shares their phone)
  sock.ev.on('chats.phoneNumberShare', ({ lid, jid }) => {
    if (lid && jid) {
      console.log(`[Brand ${brandId}] chats.phoneNumberShare: lid=${lid}, jid=${jid}`);
      cacheLidMapping(lid, jid);
      notifyWebhook({
        event: 'contact_update',
        brand_id: brandId,
        jid: jid,
        lid: lid,
        name: '',
        phone: normalizePhone(jid)
      });
    }
  });

  return sessionData;
}

// Bulk resolve all known LID contacts from v7 signalRepository.lidMapping
// This is the KEY feature of v7 — it can resolve historical LID contacts
// that the session has ever seen, WITHOUT needing them to send a new message
async function bulkResolveLIDsFromMapping(brandId, sock) {
  if (!sock?.signalRepository?.lidMapping?.getPNForLID) {
    console.log(`[Brand ${brandId}] bulkResolveLIDs: signalRepository.lidMapping not available`);
    return;
  }

  // Query DB for all LID prospects with missing phone for this brand
  // We do this via a special webhook that asks PHP to return LID JIDs
  notifyWebhook({
    event: 'request_null_phones',
    brand_id: brandId
  });

  console.log(`[Brand ${brandId}] bulkResolveLIDs: Requested null-phone LID list from PHP`);
}

// Endpoint called by PHP to provide LID list → gateway resolves them and sends back
app.post('/resolve-lids', async (req, res) => {
  const { brandId, lids } = req.body;
  if (!brandId || !lids || !Array.isArray(lids)) {
    return res.status(400).json({ error: 'brandId and lids[] are required' });
  }

  const session = activeSessions.get(parseInt(brandId, 10));
  if (!session || !session.sock) {
    return res.status(400).json({ error: 'Socket not connected' });
  }

  const sock = session.sock;
  const resolved = [];

  console.log(`[Brand ${brandId}] resolve-lids: Trying to resolve ${lids.length} LIDs...`);

  for (const lid of lids) {
    if (!lid || !lid.endsWith('@lid')) continue;

    // Try v7 signalRepository.lidMapping first
    let phone = await resolveLidToPhone(lid, sock);

    if (phone) {
      resolved.push({ jid: lid, phone });
      console.log(`[Brand ${brandId}] ✓ ${lid} → ${phone}`);
    } else {
      console.log(`[Brand ${brandId}] ✗ ${lid} → not resolved`);
    }
  }

  if (resolved.length > 0) {
    // Send resolved phones back to PHP
    notifyWebhook({
      event: 'contacts_bulk',
      brand_id: parseInt(brandId, 10),
      contacts: resolved.map(r => ({ jid: r.jid, lid: null, name: '', phone: r.phone }))
    });
  }

  res.json({ success: true, resolved: resolved.length, total: lids.length, phones: resolved });
});

// Manual trigger: Scan all LID prospects and resolve via lidMapping
app.get('/scan-phones/:brandId', async (req, res) => {
  const brandId = parseInt(req.params.brandId, 10);
  const session = activeSessions.get(brandId);
  if (!session || !session.sock) {
    return res.status(400).json({ error: 'Socket not connected' });
  }

  const sock = session.sock;

  if (!sock?.signalRepository?.lidMapping?.getPNForLID) {
    return res.status(400).json({ error: 'signalRepository.lidMapping not available (wrong Baileys version?)' });
  }

  // Request PHP to send list of null-phone LIDs
  notifyWebhook({ event: 'request_null_phones', brand_id: brandId });
  res.json({ success: true, message: 'Requested null-phone LIDs from PHP. Watch webhook for contact_bulk events.' });
});

// Trigger manual App State Resync
app.get('/resync/:brandId', async (req, res) => {
  const brandId = parseInt(req.params.brandId, 10);
  const session = activeSessions.get(brandId);
  if (!session || !session.sock) {
    return res.status(400).json({ error: 'Socket not connected' });
  }
  try {
    console.log(`[Brand ${brandId}] Triggering manual resyncAppState...`);
    await session.sock.resyncAppState(ALL_WA_PATCH_NAMES, true);
    res.json({ success: true, message: 'resyncAppState triggered' });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

// Profile picture in-memory cache
const profilePicCache = new Map(); // key -> { url, expires }

async function fetchContactProfilePic(sock, jid, phone) {
  const cacheKey = (phone || jid);
  if (!cacheKey) return null;
  const cached = profilePicCache.get(cacheKey);
  if (cached && cached.expires > Date.now()) {
    return cached.url;
  }

  const candidates = [];
  if (phone) {
    let clean = phone.replace(/[^0-9]/g, '');
    if (clean.startsWith('0')) clean = '62' + clean.substring(1);
    const phoneJid = clean + '@s.whatsapp.net';
    candidates.push(phoneJid);
  }
  if (jid && !candidates.includes(jid)) {
    candidates.push(jid);
  }

  let picUrl = null;
  for (const cJid of candidates) {
    try {
      picUrl = await sock.profilePictureUrl(cJid, 'preview');
      if (picUrl) break;
    } catch (e) {
      // 404 or privacy setting
    }
  }

  // Cache for 6 hours if found, or 15 mins if not found
  const ttl = picUrl ? (6 * 3600 * 1000) : (15 * 60 * 1000);
  profilePicCache.set(cacheKey, { url: picUrl, expires: Date.now() + ttl });
  return picUrl;
}

// Get single contact profile picture
app.get('/profile-picture/:brandId', async (req, res) => {
  const brandId = parseInt(req.params.brandId, 10);
  const jid = req.query.jid;
  const phone = req.query.phone;
  const session = activeSessions.get(brandId);
  if (!session || !session.sock || session.status !== 'connected') {
    return res.json({ url: null });
  }
  try {
    const url = await fetchContactProfilePic(session.sock, jid, phone);
    res.json({ url: url || null });
  } catch (e) {
    res.json({ url: null });
  }
});

// Batch fetch profile pictures
app.post('/profile-pictures-batch/:brandId', async (req, res) => {
  const brandId = parseInt(req.params.brandId, 10);
  const contacts = req.body.contacts || [];
  const session = activeSessions.get(brandId);
  if (!session || !session.sock || session.status !== 'connected' || !Array.isArray(contacts)) {
    return res.json({ results: {} });
  }

  const results = {};
  await Promise.allSettled(contacts.map(async (c) => {
    const key = c.phone || c.jid;
    if (!key) return;
    try {
      const url = await fetchContactProfilePic(session.sock, c.jid, c.phone);
      if (url) {
        results[key] = url;
      }
    } catch (e) {}
  }));

  res.json({ results });
});

// Get session status for a brand
app.get('/status/:brandId', (req, res) => {
  const brandId = parseInt(req.params.brandId, 10);
  const session = activeSessions.get(brandId);
  if (!session) {
    return res.json({ brandId, status: 'disconnected', phoneNumber: null, qrCode: null });
  }
  let effectiveStatus = session.status;
  if (session.qrCode && effectiveStatus !== 'connected') effectiveStatus = 'qr_ready';
  res.json({ brandId, status: effectiveStatus, phoneNumber: session.phoneNumber, qrCode: session.qrCode });
});

// Start / Request QR for a brand
app.post('/session/start', async (req, res) => {
  const brandId = parseInt(req.body.brandId, 10);
  if (!brandId) return res.status(400).json({ error: 'brandId is required' });
  try {
    const session = await startBrandSession(brandId);
    res.json({ brandId, status: session.status, phoneNumber: session.phoneNumber, qrCode: session.qrCode });
  } catch (err) {
    console.error(`[Error starting session ${brandId}]`, err);
    res.status(500).json({ error: err.message });
  }
});

// Logout / Disconnect a brand
app.post('/session/logout', async (req, res) => {
  const brandId = parseInt(req.body.brandId, 10);
  if (!brandId) return res.status(400).json({ error: 'brandId is required' });
  const session = activeSessions.get(brandId);
  if (session && session.sock) {
    try { await session.sock.logout(); } catch (e) {
      try { session.sock.end(); } catch (err) {}
    }
  }
  activeSessions.delete(brandId);
  const sessionDir = path.join(__dirname, 'sessions', `brand_${brandId}`);
  try { fs.rmSync(sessionDir, { recursive: true, force: true }); } catch (e) {}
  notifyWebhook({ event: 'connection_status', brand_id: brandId, status: 'disconnected', qr_code: null, phone_number: null });
  res.json({ success: true, brandId, status: 'disconnected' });
});

// Send WhatsApp message
app.post('/message/send', async (req, res) => {
  const { brandId, to, text, phone, quoted } = req.body;
  if (!brandId || !to || !text) {
    return res.status(400).json({ error: 'brandId, to, and text are required' });
  }
  const session = activeSessions.get(parseInt(brandId, 10));
  if (!session || session.status !== 'connected' || !session.sock) {
    return res.status(400).json({ error: 'WhatsApp session is not connected for this brand' });
  }

  const sock = session.sock;
  let targetJid = null;

  // Check if explicit candidate phone number is available
  let cleanPhone = null;
  if (phone) {
    cleanPhone = normalizePhone(phone);
  }
  if (!cleanPhone && !to.includes('@')) {
    cleanPhone = normalizePhone(to);
  }

  if (to.endsWith('@s.whatsapp.net') || to.endsWith('@g.us')) {
    targetJid = to;
  } else if (to.endsWith('@lid')) {
    // If it's an LID, check if we have the resolved real phone number
    if (!cleanPhone) {
      const cached = lidPnCache.get(to);
      if (cached) cleanPhone = normalizePhone(cached);
    }
    if (!cleanPhone && sock.signalRepository?.lidMapping?.getPNForLID) {
      try {
        const pn = await sock.signalRepository.lidMapping.getPNForLID(to);
        if (pn) cleanPhone = normalizePhone(pn);
      } catch (e) {}
    }

    if (cleanPhone) {
      // Send directly to the real phone JID so it is guaranteed to reach the recipient's phone!
      const num = cleanPhone.startsWith('0') ? '62' + cleanPhone.substring(1) : cleanPhone;
      targetJid = `${num}@s.whatsapp.net`;
      console.log(`[Brand ${brandId}] Send: mapped LID ${to} -> phone JID ${targetJid}`);
    } else {
      // If no phone could be resolved, keep as @lid (NEVER turn into fake @s.whatsapp.net!)
      targetJid = to;
      console.log(`[Brand ${brandId}] Send: sending directly to LID ${targetJid}`);
    }
  } else if (cleanPhone) {
    const num = cleanPhone.startsWith('0') ? '62' + cleanPhone.substring(1) : cleanPhone;
    targetJid = `${num}@s.whatsapp.net`;
  } else {
    let raw = to.replace(/[^0-9]/g, '');
    if (raw.startsWith('0')) raw = '62' + raw.substring(1);
    else if (raw.startsWith('8')) raw = '62' + raw;
    targetJid = `${raw}@s.whatsapp.net`;
  }

  console.log(`[Brand ${brandId}] Sending message to ${targetJid} (original: ${to}, phone: ${phone || '-'}): "${text.substring(0, 50)}..."`);
  try {
    let sendOptions = {};
    if (quoted && quoted.id) {
      sendOptions.quoted = {
        key: {
          remoteJid: quoted.remoteJid || targetJid,
          fromMe: !!quoted.fromMe,
          id: quoted.id
        },
        message: {
          conversation: quoted.text || ''
        }
      };
    }
    const sentMsg = await sock.sendMessage(targetJid, { text }, sendOptions);
    console.log(`[Brand ${brandId}] Message SENT successfully! ID: ${sentMsg?.key?.id} to ${targetJid}`);
    res.json({
      success: true,
      messageId: sentMsg?.key?.id,
      remoteJid: targetJid,
      originalJid: to,
      timestamp: Math.floor(Date.now() / 1000)
    });
  } catch (err) {
    console.error(`[Brand ${brandId}] Send message failed to ${targetJid}:`, err);
    res.status(500).json({ error: err.message });
  }
});

// Helper: resolve target JID for sending
async function resolveTargetJid(sock, to, phone, brandId) {
  let targetJid = null;
  let cleanPhone = null;
  if (phone) cleanPhone = normalizePhone(phone);
  if (!cleanPhone && !to.includes('@')) cleanPhone = normalizePhone(to);

  if (to.endsWith('@s.whatsapp.net') || to.endsWith('@g.us')) {
    targetJid = to;
  } else if (to.endsWith('@lid')) {
    if (!cleanPhone) {
      const cached = lidPnCache.get(to);
      if (cached) cleanPhone = normalizePhone(cached);
    }
    if (!cleanPhone && sock.signalRepository?.lidMapping?.getPNForLID) {
      try {
        const pn = await sock.signalRepository.lidMapping.getPNForLID(to);
        if (pn) cleanPhone = normalizePhone(pn);
      } catch (e) {}
    }

    if (cleanPhone) {
      const num = cleanPhone.startsWith('0') ? '62' + cleanPhone.substring(1) : cleanPhone;
      targetJid = `${num}@s.whatsapp.net`;
      console.log(`[Brand ${brandId}] Send: mapped LID ${to} -> phone JID ${targetJid}`);
    } else {
      targetJid = to;
      console.log(`[Brand ${brandId}] Send: sending directly to LID ${targetJid}`);
    }
  } else if (cleanPhone) {
    const num = cleanPhone.startsWith('0') ? '62' + cleanPhone.substring(1) : cleanPhone;
    targetJid = `${num}@s.whatsapp.net`;
  } else {
    let raw = to.replace(/[^0-9]/g, '');
    if (raw.startsWith('0')) raw = '62' + raw.substring(1);
    else if (raw.startsWith('8')) raw = '62' + raw;
    targetJid = `${raw}@s.whatsapp.net`;
  }
  return targetJid;
}

// Send WhatsApp media (Image or Document)
app.post('/message/send-media', async (req, res) => {
  const { brandId, to, phone, mediaType, filePath, fileName, mimetype, caption } = req.body;
  if (!brandId || !to || !filePath) {
    return res.status(400).json({ error: 'brandId, to, and filePath are required' });
  }
  const session = activeSessions.get(parseInt(brandId, 10));
  if (!session || session.status !== 'connected' || !session.sock) {
    return res.status(400).json({ error: 'WhatsApp session is not connected for this brand' });
  }

  if (!fs.existsSync(filePath)) {
    return res.status(404).json({ error: 'File attachment not found on server' });
  }

  const sock = session.sock;
  const targetJid = await resolveTargetJid(sock, to, phone, brandId);

  console.log(`[Brand ${brandId}] Sending ${mediaType || 'file'} to ${targetJid} (path: ${filePath}, caption: ${caption || '-'})`);

  try {
    const fileBuffer = fs.readFileSync(filePath);
    let sentMsg;

    if (mediaType === 'image') {
      let imageBuffer = fileBuffer;
      let finalMime = 'image/jpeg';

      try {
        const meta = await sharp(fileBuffer).metadata();
        if (meta.format === 'png') {
          finalMime = 'image/png';
        } else {
          // Convert WebP, GIF, TIFF, BMP, or raw images to standard JPEG for 100% WhatsApp mobile app compatibility
          imageBuffer = await sharp(fileBuffer)
            .rotate() // auto-orient based on EXIF
            .jpeg({ quality: 85, mozjpeg: false })
            .toBuffer();
          finalMime = 'image/jpeg';
        }
      } catch (sharpErr) {
        console.warn(`[Brand ${brandId}] Sharp conversion warning:`, sharpErr.message);
      }

      // Generate embedded jpeg thumbnail for instant mobile preview
      let jpegThumbnail = undefined;
      try {
        jpegThumbnail = await sharp(imageBuffer)
          .resize(64, 64, { fit: 'inside' })
          .jpeg({ quality: 50 })
          .toBuffer();
      } catch (e) {}

      sentMsg = await sock.sendMessage(targetJid, {
        image: imageBuffer,
        caption: caption || undefined,
        mimetype: finalMime,
        jpegThumbnail
      });
    } else {
      sentMsg = await sock.sendMessage(targetJid, {
        document: fileBuffer,
        mimetype: mimetype || 'application/octet-stream',
        fileName: fileName || path.basename(filePath),
        caption: caption || undefined
      });
    }

    console.log(`[Brand ${brandId}] Media SENT successfully! ID: ${sentMsg?.key?.id} to ${targetJid}`);
    res.json({
      success: true,
      messageId: sentMsg?.key?.id,
      remoteJid: targetJid,
      originalJid: to,
      mediaType: mediaType || 'document',
      timestamp: Math.floor(Date.now() / 1000)
    });
  } catch (err) {
    console.error(`[Brand ${brandId}] Send media failed to ${targetJid}:`, err);
    res.status(500).json({ error: err.message });
  }
});

// Delete / Revoke WhatsApp message for everyone
app.post('/message/delete', async (req, res) => {
  const { brandId, remoteJid, phone, messageId } = req.body;
  if (!brandId || !messageId) {
    return res.status(400).json({ error: 'brandId and messageId are required' });
  }
  const session = activeSessions.get(parseInt(brandId, 10));
  if (!session || session.status !== 'connected' || !session.sock) {
    return res.status(400).json({ error: 'WhatsApp session is not connected for this brand' });
  }

  const sock = session.sock;
  let targetJid = null;

  let cleanPhone = null;
  if (phone) cleanPhone = normalizePhone(phone);
  if (!cleanPhone && remoteJid && !remoteJid.includes('@')) cleanPhone = normalizePhone(remoteJid);

  if (remoteJid?.endsWith('@s.whatsapp.net') || remoteJid?.endsWith('@g.us')) {
    targetJid = remoteJid;
  } else if (remoteJid?.endsWith('@lid')) {
    if (!cleanPhone) {
      const cached = lidPnCache.get(remoteJid);
      if (cached) cleanPhone = normalizePhone(cached);
    }
    if (!cleanPhone && sock.signalRepository?.lidMapping?.getPNForLID) {
      try {
        const pn = await sock.signalRepository.lidMapping.getPNForLID(remoteJid);
        if (pn) cleanPhone = normalizePhone(pn);
      } catch (e) {}
    }
    if (cleanPhone) {
      const num = cleanPhone.startsWith('0') ? '62' + cleanPhone.substring(1) : cleanPhone;
      targetJid = `${num}@s.whatsapp.net`;
    } else {
      targetJid = remoteJid;
    }
  } else if (cleanPhone) {
    const num = cleanPhone.startsWith('0') ? '62' + cleanPhone.substring(1) : cleanPhone;
    targetJid = `${num}@s.whatsapp.net`;
  } else if (remoteJid) {
    targetJid = remoteJid;
  }

  if (!targetJid) {
    return res.status(400).json({ error: 'Could not resolve target JID for message deletion' });
  }

  console.log(`[Brand ${brandId}] Revoking message ${messageId} in ${targetJid} (original JID: ${remoteJid})`);
  try {
    const key = {
      remoteJid: targetJid,
      fromMe: true,
      id: messageId
    };

    await sock.sendMessage(targetJid, { delete: key });

    // If targetJid was mapped from LID, also try sending delete stanza to LID if distinct
    if (remoteJid && remoteJid !== targetJid && remoteJid.endsWith('@lid')) {
      try {
        await sock.sendMessage(remoteJid, { delete: { remoteJid, fromMe: true, id: messageId } });
      } catch (e2) {
        // secondary attempt ignore
      }
    }

    console.log(`[Brand ${brandId}] Message ${messageId} REVOKED successfully!`);
    res.json({
      success: true,
      messageId,
      targetJid
    });
  } catch (err) {
    console.error(`[Brand ${brandId}] Revoke message failed for ${messageId}:`, err);
    res.status(500).json({ error: err.message });
  }
});

// React to a message (send or clear reaction)
app.post('/message/react', async (req, res) => {
  const { brandId, remoteJid, phone, messageId, isFromMe, emoji } = req.body;
  if (!brandId || !messageId) {
    return res.status(400).json({ error: 'brandId and messageId are required' });
  }

  const session = activeSessions.get(parseInt(brandId, 10));
  if (!session || session.status !== 'connected' || !session.sock) {
    return res.status(400).json({ error: 'WhatsApp session is not connected for this brand' });
  }

  const sock = session.sock;
  const targetJid = await resolveTargetJid(sock, remoteJid, phone, brandId);
  if (!targetJid) {
    return res.status(400).json({ error: 'Could not resolve target JID for reaction' });
  }

  console.log(`[Brand ${brandId}] Reacting to ${messageId} in ${targetJid} with "${emoji || 'REMOVE'}"`);
  try {
    const reactionKey = {
      remoteJid: targetJid,
      fromMe: !!isFromMe,
      id: messageId
    };

    await sock.sendMessage(targetJid, {
      react: {
        text: emoji || '',
        key: reactionKey
      }
    });

    console.log(`[Brand ${brandId}] Reaction "${emoji}" sent successfully for ${messageId}`);
    res.json({
      success: true,
      messageId,
      reaction: emoji || null
    });
  } catch (err) {
    console.error(`[Brand ${brandId}] React failed for ${messageId}:`, err);
    res.status(500).json({ error: err.message });
  }
});

// Mark messages as read on WhatsApp (sends blue ticks)
app.post('/message/mark-read', async (req, res) => {
  const { brandId, keys } = req.body;
  if (!brandId || !Array.isArray(keys) || keys.length === 0) {
    return res.status(400).json({ error: 'brandId and keys array are required' });
  }

  const session = activeSessions.get(parseInt(brandId, 10));
  if (!session || !session.sock) {
    return res.status(400).json({ error: 'Socket not connected' });
  }

  try {
    const formattedKeys = keys.map(k => ({
      remoteJid: k.remoteJid,
      id: k.id,
      fromMe: false,
      participant: k.participant || undefined
    }));
    await session.sock.readMessages(formattedKeys);
    console.log(`[Brand ${brandId}] Marked ${formattedKeys.length} messages as read`);
    res.json({ success: true, count: formattedKeys.length });
  } catch (err) {
    console.warn(`[Brand ${brandId}] readMessages warning:`, err.message);
    res.status(500).json({ error: err.message });
  }
});

// Debug: test LID resolution for all known LID contacts
app.get('/debug/resolve/:brandId', async (req, res) => {
  const brandId = parseInt(req.params.brandId, 10);
  const session = activeSessions.get(brandId);
  if (!session || !session.sock) return res.status(400).json({ error: 'Socket not connected' });

  const sock = session.sock;
  const testLids = [
    '69647894372576@lid',
    '146600705941553@lid',
    '167611115511860@lid',
    '233444609126619@lid',
    '249194036076572@lid',
    '6936489631887@lid'
  ];

  const results = {};
  for (const lid of testLids) {
    results[lid] = {
      cachedPhone: lidPnCache.get(lid) || null
    };
    // Try v7 lidMapping
    try {
      const pn = await sock.signalRepository?.lidMapping?.getPNForLID(lid);
      results[lid].lidMappingPn = pn || null;
      results[lid].resolvedPhone = pn ? normalizePhone(pn) : null;
    } catch (e) {
      results[lid].lidMappingError = e.message;
    }
  }
  res.json({ lidMappingAvailable: !!sock.signalRepository?.lidMapping?.getPNForLID, results });
});

// Auto-restore any existing saved sessions on server boot
async function restoreSavedSessions() {
  const sessionsBase = path.join(__dirname, 'sessions');
  if (!fs.existsSync(sessionsBase)) return;
  const entries = fs.readdirSync(sessionsBase, { withFileTypes: true });
  for (const entry of entries) {
    if (entry.isDirectory() && entry.name.startsWith('brand_')) {
      const brandId = parseInt(entry.name.replace('brand_', ''), 10);
      if (brandId) {
        console.log(`[Auto-Restore] Resuming WhatsApp session for Brand ${brandId}...`);
        try { await startBrandSession(brandId); } catch (e) {
          console.warn(`[Auto-Restore Error for Brand ${brandId}]:`, e.message);
        }
      }
    }
  }
}

app.listen(PORT, '0.0.0.0', () => {
  console.log(`\n======================================================`);
  console.log(`CS Umroh WhatsApp Gateway running on http://127.0.0.1:${PORT}`);
  console.log(`PHP Webhook URL: ${WEBHOOK_URL}`);
  console.log(`Baileys: v7 (ESM) with signalRepository.lidMapping`);
  console.log(`======================================================\n`);
  restoreSavedSessions();
});
