<?php
/**
 * WhatsApp Gateway Webhook Handler
 * Receives connection updates, inbound/outbound messages, and past history syncs
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

// Verify secret header
$gatewaySecret = 'csumroh-secret-key-2026';
$receivedSecret = $_SERVER['HTTP_X_GATEWAY_SECRET'] ?? '';

if ($receivedSecret !== $gatewaySecret) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized gateway access']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!$payload || !isset($payload['event'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid webhook payload']);
    exit;
}

$db = get_db();
$event = $payload['event'];
$brandId = (int)($payload['brand_id'] ?? 0);

if ($brandId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid brand_id']);
    exit;
}

/**
 * Helper to auto-extract customer name from WhatsApp message text or pushName
 */
function detect_customer_name(?string $text, ?string $pushName, string $phone): string {
    // 1. WhatsApp pushName (display name from sender's profile)
    if (!empty($pushName)) {
        $cleanPush = trim($pushName);
        if ($cleanPush !== '') {
            return $cleanPush;
        }
    }

    // 2. Check patterns in text
    if (!empty($text)) {
        // Pattern 2a: "Nama: [Name]" (e.g. "Nama: Bpk. H. Rahmat" or "Nama: Malik")
        if (preg_match('/(?:^|\n|\r)\s*(?:nama|nama\s*lengkap)\s*[:=]\s*([a-zA-Z\s\.\,\'\-]+?)(?:\r|\n|kota|kec|produk|usia|alamat|$)/i', $text, $m)) {
            $cand = trim($m[1]);
            if (strlen($cand) >= 2 && !preg_match('/^(mau|tanya|saya|ingin)/i', $cand)) {
                return ucwords(strtolower($cand));
            }
        }

        // Pattern 2b: "Saya [Name] dari [Company]" (e.g. "Saya Dio dari Plasgos CRM")
        if (preg_match('/(?:saya|dari)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)\s+dari\s+([A-Za-z0-9\s\.\-]+)/i', $text, $m)) {
            $pName = trim($m[1]);
            $comp = trim(explode("\n", $m[2])[0]);
            return ucwords(strtolower($pName)) . ' (' . ucwords(strtolower($comp)) . ')';
        }

        // Pattern 2c: "Halo [Name]! Saya [Name]" (e.g. "Halo Tentaklik! Saya Malik")
        if (preg_match('/(?:halo|hai)\s+([A-Za-z0-9\.\-\_]+)[\!\,\.]\s+(?:saya|nama saya)/i', $text, $m)) {
            $destName = trim($m[1]);
            if (strlen($destName) >= 3) {
                return ucwords(strtolower($destName));
            }
        }

        // Pattern 2d: standard "nama saya [Nama]" or "saya [Nama]"
        if (preg_match('/(?:nama(?: saya)?|saya)\s*[:=]?\s*([a-zA-Z\s\.\,\'\-]+)/i', $text, $m)) {
            $rawWords = preg_split('/\s+/', trim($m[1]));
            $stopWords = ['mau', 'tanya', 'ingin', 'info', 'paket', 'umroh', 'daftar', 'berangkat', 'bisa', 'tolong', 'assalamualaikum', 'halo', 'hai', 'dari', 'apakah', 'ada', 'yang', 'ini', 'itu', 'ke', 'di', 'untuk'];
            $nameParts = [];
            foreach ($rawWords as $w) {
                $lw = strtolower(trim($w));
                if (in_array($lw, $stopWords)) {
                    break;
                }
                if (strlen($w) >= 2) {
                    $nameParts[] = $w;
                }
                if (count($nameParts) >= 3) break;
            }
            if (!empty($nameParts)) {
                return ucwords(strtolower(implode(' ', $nameParts)));
            }
        }
    }

    // 3. Fallback: return real phone number, NEVER 'Calon Jamaah'
    if (!empty($phone) && (str_starts_with($phone, '08') || str_starts_with($phone, '628'))) {
        return $phone;
    }

    return '';
}

/**
 * Helper to auto-extract clean Indonesian phone number from text
 */
function extract_phone_from_text(?string $text): ?string {
    if (empty($text)) return null;
    if (preg_match('/(?:(?:\+?62)|0)8[0-9\-\s]{7,13}/', $text, $m)) {
        $clean = preg_replace('/[^0-9]/', '', $m[0]);
        if (str_starts_with($clean, '62')) {
            $clean = '0' . substr($clean, 2);
        }
        if (strlen($clean) >= 10 && strlen($clean) <= 13) {
            return $clean;
        }
    }
    return null;
}

/**
 * Helper to find or assign default CS user for a brand
 */
function get_cs_user_for_brand(PDO $db, int $brandId): int {
    // Distribute among active CS of this brand by least active/open prospects
    $stmt = $db->prepare("
        SELECT u.id 
        FROM users u
        LEFT JOIN prospects p ON (u.id = p.user_id AND p.status NOT IN ('closed_won', 'closed_lost'))
        WHERE u.brand_id = ? AND u.role = 'cs' AND u.is_active = 1
        GROUP BY u.id
        ORDER BY COUNT(p.id) ASC, u.id ASC
        LIMIT 1
    ");
    $stmt->execute([$brandId]);
    $userId = $stmt->fetchColumn();

    if (!$userId) {
        $stmt2 = $db->query("SELECT id FROM users ORDER BY id ASC LIMIT 1");
        $userId = $stmt2->fetchColumn() ?: 1;
    }
    return (int)$userId;
}

// -------------------------------------------------------------
// EVENT 1: Connection Status Update (QR, Connected, Disconnected)
// -------------------------------------------------------------
if ($event === 'connection_status') {
    $status = $payload['status'] ?? 'disconnected';
    $qrCode = $payload['qr_code'] ?? null;
    $phoneNumber = $payload['phone_number'] ?? null;

    $stmt = $db->prepare("
        INSERT INTO whatsapp_sessions (brand_id, session_name, status, qr_code, phone_number, last_connected_at)
        VALUES (?, ?, ?, ?, ?, IF(? = 'connected', NOW(), NULL))
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            qr_code = VALUES(qr_code),
            phone_number = IF(VALUES(phone_number) IS NOT NULL, VALUES(phone_number), phone_number),
            last_connected_at = IF(VALUES(status) = 'connected', NOW(), last_connected_at)
    ");
    $stmt->execute([$brandId, 'brand_' . $brandId, $status, $qrCode, $phoneNumber, $status]);

    echo json_encode(['success' => true, 'event' => 'connection_status', 'brand_id' => $brandId]);
    exit;
}

// -------------------------------------------------------------
// EVENT 1b: Contact Update (Name & Phone from WhatsApp Sync)
// -------------------------------------------------------------
if ($event === 'contact_update') {
    $jid = $payload['jid'] ?? '';
    $lid = $payload['lid'] ?? '';
    $name = trim($payload['name'] ?? '');
    $phone = trim($payload['phone'] ?? '');

    // Validate phone: must start with 08 or 628
    $validPhone = null;
    if (!empty($phone) && (str_starts_with($phone, '08') || str_starts_with($phone, '628'))) {
        $validPhone = $phone;
    }

    $photoUrl = trim($payload['photo_url'] ?? '');

    $conditions = [];
    $params = [];
    if (!empty($jid)) { $conditions[] = "remote_jid = ?"; $params[] = $jid; }
    if (!empty($lid)) { $conditions[] = "remote_jid = ?"; $params[] = $lid; }
    if (!empty($validPhone)) {
        $cleanP = preg_replace('/[^0-9]/', '', $validPhone);
        if (str_starts_with($cleanP, '62')) $cleanP = '0' . substr($cleanP, 2);
        $altP = '62' . substr($cleanP, 1);
        $conditions[] = "phone = ?"; $params[] = $cleanP;
        $conditions[] = "phone = ?"; $params[] = $altP;
    }

    if (!empty($conditions)) {
        $whereClause = "(" . implode(" OR ", $conditions) . ")";

        if (!empty($photoUrl)) {
            $stmtP = $db->prepare("UPDATE prospects SET photo_url = ? WHERE brand_id = ? AND {$whereClause}");
            $stmtP->execute(array_merge([$photoUrl, $brandId], $params));
        }

        if (!empty($name) && !empty($validPhone)) {
            $stmt = $db->prepare("UPDATE prospects SET name = ?, phone = ? WHERE brand_id = ? AND {$whereClause}");
            $stmt->execute(array_merge([$name, $validPhone, $brandId], $params));
            $stmt2 = $db->prepare("UPDATE chat_messages SET phone = ? WHERE brand_id = ? AND {$whereClause}");
            $stmt2->execute(array_merge([$validPhone, $brandId], $params));
        } elseif (!empty($validPhone)) {
            $stmt = $db->prepare("UPDATE prospects SET phone = ? WHERE brand_id = ? AND {$whereClause}");
            $stmt->execute(array_merge([$validPhone, $brandId], $params));
            $stmt2 = $db->prepare("UPDATE chat_messages SET phone = ? WHERE brand_id = ? AND {$whereClause}");
            $stmt2->execute(array_merge([$validPhone, $brandId], $params));
        } elseif (!empty($name)) {
            $stmt = $db->prepare("UPDATE prospects SET name = ? WHERE brand_id = ? AND {$whereClause}");
            $stmt->execute(array_merge([$name, $brandId], $params));
        }
    }

    echo json_encode(['success' => true, 'event' => 'contact_update']);
    exit;
}

// -------------------------------------------------------------
// EVENT 1c: Contacts Bulk (Batch upsert from contacts.upsert / contacts.update)
// Sent as one request to avoid PHP ECONNRESET from mass individual webhooks
// -------------------------------------------------------------
if ($event === 'contacts_bulk') {
    $contacts = $payload['contacts'] ?? [];
    if (empty($contacts) || !is_array($contacts)) {
        echo json_encode(['success' => false, 'message' => 'No contacts']);
        exit;
    }

    $updated = 0;
    foreach ($contacts as $c) {
        $jid   = $c['jid'] ?? '';
        $lid   = $c['lid'] ?? '';
        $name  = trim($c['name'] ?? '');
        $phone = trim($c['phone'] ?? '');

        // Validate phone: must start with 08 or 628
        $validPhone = null;
        if (!empty($phone) && (str_starts_with($phone, '08') || str_starts_with($phone, '628'))) {
            $validPhone = $phone;
        }

        // Build WHERE clause matching by jid and/or lid
        $conditions = [];
        $params = [];
        if (!empty($jid)) { $conditions[] = "remote_jid = ?"; $params[] = $jid; }
        if (!empty($lid)) { $conditions[] = "remote_jid = ?"; $params[] = $lid; }
        if (empty($conditions)) continue;

        $whereClause = "(" . implode(" OR ", $conditions) . ")";

        if (!empty($validPhone) && !empty($name)) {
            $stmt = $db->prepare("UPDATE prospects SET name = ?, phone = ? WHERE brand_id = ? AND {$whereClause}");
            $stmt->execute(array_merge([$name, $validPhone, $brandId], $params));
            $stmt2 = $db->prepare("UPDATE chat_messages SET phone = ? WHERE brand_id = ? AND {$whereClause}");
            $stmt2->execute(array_merge([$validPhone, $brandId], $params));
            $updated += $stmt->rowCount();
        } elseif (!empty($validPhone)) {
            $stmt = $db->prepare("UPDATE prospects SET phone = ? WHERE brand_id = ? AND {$whereClause}");
            $stmt->execute(array_merge([$validPhone, $brandId], $params));
            $stmt2 = $db->prepare("UPDATE chat_messages SET phone = ? WHERE brand_id = ? AND {$whereClause}");
            $stmt2->execute(array_merge([$validPhone, $brandId], $params));
            $updated += $stmt->rowCount();
        } elseif (!empty($name)) {
            $stmt = $db->prepare("UPDATE prospects SET name = ? WHERE brand_id = ? AND {$whereClause}");
            $stmt->execute(array_merge([$name, $brandId], $params));
            $updated += $stmt->rowCount();
        }
    }

    echo json_encode(['success' => true, 'event' => 'contacts_bulk', 'updated' => $updated, 'total' => count($contacts)]);
    exit;
}


// -------------------------------------------------------------
// EVENT 1d: Request Null Phones (Baileys v7 lidMapping bulk resolve)
// Gateway fires this on connect → PHP queries all LID prospects
// with NULL phone → sends list to /resolve-lids → gateway responds
// with contacts_bulk containing resolved phones from signalRepository
// -------------------------------------------------------------
if ($event === 'request_null_phones') {
    // Get all LID prospects with missing phone for this brand
    $stmt = $db->prepare("
        SELECT remote_jid FROM prospects
        WHERE brand_id = ?
        AND remote_jid LIKE '%@lid'
        AND (phone IS NULL OR phone = '')
        ORDER BY id
    ");
    $stmt->execute([$brandId]);
    $lids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($lids)) {
        echo json_encode(['success' => true, 'message' => 'No null-phone LID prospects found']);
        exit;
    }

    // Call gateway /resolve-lids endpoint to resolve via signalRepository.lidMapping
    $gatewayUrl = 'http://127.0.0.1:3001/resolve-lids';
    $postData = json_encode(['brandId' => $brandId, 'lids' => $lids]);

    $ch = curl_init($gatewayUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Content-Length: ' . strlen($postData)],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15
    ]);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($result, true);
    echo json_encode([
        'success' => true,
        'event' => 'request_null_phones',
        'lids_requested' => count($lids),
        'gateway_response' => $decoded,
        'http_code' => $httpCode
    ]);
    exit;
}

// -------------------------------------------------------------
if ($event === 'new_message') {
    $msg = $payload['message'] ?? [];
    if (empty($msg) || empty($msg['message_id']) || empty($msg['remote_jid'])) {
        echo json_encode(['success' => false, 'message' => 'Empty message payload']);
        exit;
    }

    $messageId = $msg['message_id'];
    $remoteJid = $msg['remote_jid'];
    $phone = $msg['phone'] ?? '';
    $senderName = $msg['sender_name'] ?? null;
    $isFromMe = (int)($msg['is_from_me'] ?? 0);
    $messageType = $msg['message_type'] ?? 'conversation';
    $messageText = $msg['message_text'] ?? '';
    $timestamp = (int)($msg['timestamp'] ?? time());
    $metaReferral = $msg['meta_referral'] ?? null;
    $referralJson = $metaReferral ? json_encode($metaReferral, JSON_UNESCAPED_SLASHES) : null;
    $mediaUrl = $msg['media_url'] ?? null;
    $quotedMessageId = $msg['quoted_message_id'] ?? null;
    $quotedText = $msg['quoted_text'] ?? null;
    $quotedSender = $msg['quoted_sender'] ?? null;

    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($cleanPhone, '62')) $cleanPhone = '0' . substr($cleanPhone, 2);
    $altPhone = !empty($cleanPhone) ? ('62' . substr($cleanPhone, 1)) : '';

    // 1. Find existing prospect by phone or remote_jid
    $prospectStmt = $db->prepare("
        SELECT id, name, phone, remote_jid, meta_referral_marker
        FROM prospects
        WHERE brand_id = ? AND (remote_jid = ? OR (? != '' AND (phone = ? OR phone = ?)))
        LIMIT 1
    ");
    $prospectStmt->execute([$brandId, $remoteJid, $cleanPhone, $cleanPhone, $altPhone]);
    $prospect = $prospectStmt->fetch();

    $prospectId = null;

    if ($prospect) {
        $prospectId = (int)$prospect['id'];

        // Update remote_jid if missing or upgrading to LID
        if (empty($prospect['remote_jid']) || (str_ends_with($remoteJid, '@lid') && !str_ends_with($prospect['remote_jid'], '@lid'))) {
            $db->prepare("UPDATE prospects SET remote_jid = ? WHERE id = ?")->execute([$remoteJid, $prospectId]);
        }
        if (empty($prospect['phone']) && !empty($cleanPhone)) {
            $db->prepare("UPDATE prospects SET phone = ? WHERE id = ?")->execute([$cleanPhone, $prospectId]);
        }

        // Update name if current name is placeholder and better name found
        // Update name if current name is placeholder, phone, or if better name found
        $isGeneric = empty($prospect['name']) 
            || str_starts_with($prospect['name'], 'Calon Jamaah') 
            || str_starts_with($prospect['name'], 'Jamaah ')
            || $prospect['name'] === $prospect['phone']
            || str_contains($prospect['name'], '@');
        if ($isGeneric && (!empty($senderName) || !empty($messageText))) {
            $newName = detect_customer_name($messageText, $senderName, $phone);
            if (!empty($newName)) {
                $db->prepare("UPDATE prospects SET name = ? WHERE id = ?")->execute([$newName, $prospectId]);
            }
        }

        // Auto-extract phone number from message text if currently missing or LID
        $extractedPhone = extract_phone_from_text($messageText);
        $hasCleanPhone = (!empty($prospect['phone']) && (str_starts_with($prospect['phone'], '08') || str_starts_with($prospect['phone'], '628')));
        if (!$hasCleanPhone && $extractedPhone) {
            $db->prepare("UPDATE prospects SET phone = ? WHERE id = ?")->execute([$extractedPhone, $prospectId]);
            $db->prepare("UPDATE chat_messages SET phone = ? WHERE remote_jid = ?")->execute([$extractedPhone, $remoteJid]);
            $phone = $extractedPhone;
        }

        // Save referral marker if ad info found
        if ($metaReferral && empty($prospect['meta_referral_marker'])) {
            $marker = $metaReferral['ctwa_clid'] ?? $metaReferral['source_id'] ?? $metaReferral['headline'] ?? null;
            if ($marker) {
                $db->prepare("UPDATE prospects SET meta_referral_marker = ? WHERE id = ?")->execute([$marker, $prospectId]);
            }
        }

        // Update last activity timestamp
        $db->prepare("UPDATE prospects SET updated_at = NOW() WHERE id = ?")->execute([$prospectId]);

    } elseif ($isFromMe === 0) {
        // Auto-create new prospect for inbound message
        $detectedName = detect_customer_name($messageText, $senderName, $phone);
        $assignedUserId = get_cs_user_for_brand($db, $brandId);
        $referralMarker = $metaReferral ? ($metaReferral['ctwa_clid'] ?? $metaReferral['source_id'] ?? $metaReferral['headline'] ?? null) : null;

        // If phone is LID or empty, check if message text contains a real phone number
        $extractedPhone = extract_phone_from_text($messageText);
        $effectivePhone = $extractedPhone ?: (str_starts_with($phone, '08') || str_starts_with($phone, '628') ? $phone : null);

        if (empty($detectedName)) {
            $detectedName = $effectivePhone ?: (!empty($senderName) ? $senderName : $remoteJid);
        }

        $insProspect = $db->prepare("
            INSERT INTO prospects (brand_id, user_id, name, phone, remote_jid, meta_referral_marker, current_stage, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, 'greeting', 'new', 'Masuk via WhatsApp Auto-Inbound')
        ");
        $insProspect->execute([
            $brandId,
            $assignedUserId,
            $detectedName,
            $effectivePhone,
            $remoteJid,
            $referralMarker
        ]);
        $prospectId = (int)$db->lastInsertId();

    }

    // Get session phone (the connected WhatsApp number that received/sent this message)
    $sessionPhone = $payload['session_phone'] ?? null;
    if (empty($sessionPhone)) {
        $stmtSess = $db->prepare("SELECT phone_number FROM whatsapp_sessions WHERE brand_id = ?");
        $stmtSess->execute([$brandId]);
        $sessionPhone = $stmtSess->fetchColumn() ?: null;
    }

    // 2. Insert message into chat_messages
    $insMsg = $db->prepare("
        INSERT INTO chat_messages (
            brand_id, session_phone, prospect_id, message_id, remote_jid, phone,
            sender_name, is_from_me, message_type, message_text, media_url,
            quoted_message_id, quoted_text, quoted_sender,
            timestamp, meta_referral_data
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            session_phone = IF(session_phone IS NULL, VALUES(session_phone), session_phone),
            message_text = IF(is_from_me = 1 AND message_text != '' AND message_text != '[Gambar]' AND message_text != '[Dokumen]' AND message_text != '[Video]' AND message_text != '[Voice Note]' AND message_text != '[Audio]', message_text, VALUES(message_text)),
            media_url = IF(media_url IS NOT NULL AND media_url != '', media_url, VALUES(media_url)),
            quoted_message_id = IF(quoted_message_id IS NULL, VALUES(quoted_message_id), quoted_message_id),
            quoted_text = IF(quoted_text IS NULL, VALUES(quoted_text), quoted_text),
            quoted_sender = IF(quoted_sender IS NULL, VALUES(quoted_sender), quoted_sender),
            prospect_id = IF(prospect_id IS NULL, VALUES(prospect_id), prospect_id)
    ");
    $insMsg->execute([
        $brandId,
        $sessionPhone,
        $prospectId,
        $messageId,
        $remoteJid,
        $phone,
        $senderName,
        $isFromMe,
        $messageType,
        $messageText,
        $mediaUrl,
        $quotedMessageId,
        $quotedText,
        $quotedSender,
        $timestamp,
        $referralJson
    ]);

    // When prospect replies, any prior outbound messages in this conversation are naturally read
    if ($isFromMe === 0 && !empty($remoteJid)) {
        $db->prepare("
            UPDATE chat_messages 
            SET status = 'read' 
            WHERE brand_id = ? AND remote_jid = ? AND is_from_me = 1 AND status != 'read'
        ")->execute([$brandId, $remoteJid]);
    }

    echo json_encode([
        'success' => true,
        'event' => 'new_message',
        'message_id' => $messageId,
        'prospect_id' => $prospectId
    ]);
    exit;
}

// -------------------------------------------------------------
// EVENT 3: Past History Sync (Batch of past messages)
// -------------------------------------------------------------
if ($event === 'history_sync') {
    $messages = $payload['messages'] ?? [];
    if (empty($messages) || !is_array($messages)) {
        echo json_encode(['success' => true, 'count' => 0]);
        exit;
    }

    $inserted = 0;
    $sessionPhone = $payload['session_phone'] ?? null;
    if (empty($sessionPhone)) {
        $stmtSess = $db->prepare("SELECT phone_number FROM whatsapp_sessions WHERE brand_id = ?");
        $stmtSess->execute([$brandId]);
        $sessionPhone = $stmtSess->fetchColumn() ?: null;
    }

    $insMsg = $db->prepare("
        INSERT IGNORE INTO chat_messages (
            brand_id, session_phone, prospect_id, message_id, remote_jid, phone,
            sender_name, is_from_me, message_type, message_text,
            timestamp, meta_referral_data
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($messages as $msg) {
        $messageId = $msg['message_id'] ?? null;
        $remoteJid = $msg['remote_jid'] ?? null;
        if (!$messageId || !$remoteJid) continue;

        $phone = $msg['phone'] ?? '';
        $senderName = $msg['sender_name'] ?? null;
        $isFromMe = (int)($msg['is_from_me'] ?? 0);
        $messageType = $msg['message_type'] ?? 'conversation';
        $messageText = $msg['message_text'] ?? '';
        $timestamp = (int)($msg['timestamp'] ?? time());
        $metaReferral = $msg['meta_referral'] ?? null;
        $referralJson = $metaReferral ? json_encode($metaReferral, JSON_UNESCAPED_SLASHES) : null;

        // Check if prospect exists
        $prospectStmt = $db->prepare("SELECT id FROM prospects WHERE brand_id = ? AND (remote_jid = ? OR phone = ?) LIMIT 1");
        $prospectStmt->execute([$brandId, $remoteJid, $phone]);
        $prospectId = $prospectStmt->fetchColumn();

        // Auto-extract phone if present in text
        $extractedPhone = extract_phone_from_text($messageText);
        $cleanPhone = $extractedPhone ?: (str_starts_with($phone, '08') || str_starts_with($phone, '628') ? $phone : null);

        if (!$prospectId && $isFromMe === 0) {
            // Auto-create prospect for past chat
            $detectedName = detect_customer_name($messageText, $senderName, $cleanPhone ?: '');
            if (empty($detectedName)) {
                $detectedName = $cleanPhone ?: (!empty($senderName) ? $senderName : $remoteJid);
            }
            $assignedUserId = get_cs_user_for_brand($db, $brandId);
            $insProspect = $db->prepare("
                INSERT INTO prospects (brand_id, user_id, name, phone, remote_jid, current_stage, status, notes)
                VALUES (?, ?, ?, ?, ?, 'greeting', 'new', 'Disinkronkan dari riwayat WhatsApp')
            ");
            $insProspect->execute([$brandId, $assignedUserId, $detectedName, $cleanPhone, $remoteJid]);
            $prospectId = $db->lastInsertId();
        } elseif ($prospectId && $extractedPhone) {
            $db->prepare("UPDATE prospects SET phone = ? WHERE id = ? AND (phone IS NULL OR phone = '')")->execute([$extractedPhone, $prospectId]);
        }

        $insMsg->execute([
            $brandId,
            $sessionPhone,
            $prospectId ?: null,
            $messageId,
            $remoteJid,
            $phone,
            $senderName,
            $isFromMe,
            $messageType,
            $messageText,
            $timestamp,
            $referralJson
        ]);
        $inserted++;
    }


    echo json_encode([
        'success' => true,
        'event' => 'history_sync',
        'inserted_count' => $inserted
    ]);
    exit;
}

// -------------------------------------------------------------
// EVENT 4: Message Deleted / Revoked from WhatsApp
// -------------------------------------------------------------
if ($event === 'message_deleted') {
    $messageId = trim($payload['message_id'] ?? '');
    if (!empty($messageId)) {
        $stmt = $db->prepare("
            UPDATE chat_messages 
            SET is_deleted = 1, deleted_at = NOW() 
            WHERE brand_id = ? AND message_id = ?
        ");
        $stmt->execute([$brandId, $messageId]);
        $affected = $stmt->rowCount();


        echo json_encode([
            'success' => true,
            'event' => 'message_deleted',
            'message_id' => $messageId,
            'updated' => $affected
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Missing message_id']);
    exit;
}

// -------------------------------------------------------------
// EVENT 5: Message Status Update (Delivery ACK, Read Receipts / Centang Biru)
// -------------------------------------------------------------
if ($event === 'message_status_update') {
    $messageId = trim($payload['message_id'] ?? '');
    $status = trim($payload['status'] ?? 'delivered');
    if (!empty($messageId)) {
        $stmt = $db->prepare("UPDATE chat_messages SET status = ? WHERE brand_id = ? AND message_id = ?");
        $stmt->execute([$status, $brandId, $messageId]);
        echo json_encode([
            'success' => true,
            'event' => 'message_status_update',
            'message_id' => $messageId,
            'status' => $status,
            'updated' => $stmt->rowCount()
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Missing message_id']);
    exit;
}

// -------------------------------------------------------------
// EVENT 6: Message Reaction (WhatsApp React Emoji)
// -------------------------------------------------------------
if ($event === 'message_reaction') {
    $messageId = trim($payload['message_id'] ?? '');
    $reaction = trim($payload['reaction'] ?? '');
    if (!empty($messageId)) {
        $stmt = $db->prepare("UPDATE chat_messages SET reaction = ? WHERE brand_id = ? AND message_id = ?");
        $stmt->execute([$reaction ?: null, $brandId, $messageId]);
        echo json_encode([
            'success' => true,
            'event' => 'message_reaction',
            'message_id' => $messageId,
            'reaction' => $reaction ?: null,
            'updated' => $stmt->rowCount()
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Missing message_id']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unhandled event type']);

