<?php
/**
 * WhatsApp Controller API for CS Workspace & Live Chat
 * Manages gateway connection, chat threads, messages, and sending
 */

require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

$user = get_logged_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$db = get_db();

// Handle JSON input if sent as raw body
$rawInput = file_get_contents('php://input');
if (!empty($rawInput)) {
    $jsonData = json_decode($rawInput, true);
    if (is_array($jsonData)) {
        $_REQUEST = array_merge($_REQUEST, $jsonData);
        $_POST = array_merge($_POST, $jsonData);
    }
}

// Brand scope determination
$brandId = ($user['role'] === 'superadmin' && !empty($_REQUEST['brand_id'])) 
            ? (int)$_REQUEST['brand_id'] 
            : ($user['brand_id'] ?: 1);

$gatewayUrl = 'http://127.0.0.1:3001';
$action = $_REQUEST['action'] ?? '';

// Helper to call Node gateway API
if (!function_exists('call_gateway')) {
    function call_gateway(string $endpoint, string $method = 'GET', array $data = []): array {
        global $gatewayUrl;
        $url = rtrim($gatewayUrl, '/') . '/' . ltrim($endpoint, '/');
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$res) {
            return ['ok' => false, 'http_code' => $httpCode, 'error' => 'Gateway Node.js offline'];
        }

        $json = json_decode($res, true);
        return is_array($json) ? array_merge(['ok' => ($httpCode >= 200 && $httpCode < 300)], $json) : ['ok' => false, 'raw' => $res];
    }
}


// -------------------------------------------------------------
// 1. GET WHATSAPP CONNECTION STATUS
// -------------------------------------------------------------
if ($action === 'status') {
    // 1. Check database session
    $stmt = $db->prepare("SELECT * FROM whatsapp_sessions WHERE brand_id = ?");
    $stmt->execute([$brandId]);
    $session = $stmt->fetch();

    // 2. Query Gateway for live status
    $gwStatus = call_gateway("/status/{$brandId}");

    $status = 'disconnected';
    $qrCode = null;
    $phone = null;

    if ($gwStatus['ok'] ?? false) {
        $status = $gwStatus['status'] ?? ($session['status'] ?? 'disconnected');
        $qrCode = $gwStatus['qrCode'] ?? ($session['qr_code'] ?? null);
        $phone = $gwStatus['phoneNumber'] ?? ($session['phone_number'] ?? null);
    } else {
        // Fallback to database record
        $status = $session['status'] ?? 'disconnected';
        $qrCode = $session['qr_code'] ?? null;
        $phone = $session['phone_number'] ?? null;
    }

    // If QR code is present and status is not connected, the effective status is qr_ready
    if (!empty($qrCode) && $status !== 'connected') {
        $status = 'qr_ready';
    }

    echo json_encode([
        'success' => true,
        'brand_id' => $brandId,
        'status' => $status,
        'phone_number' => $phone,
        'qr_code' => $qrCode,
        'gateway_online' => ($gwStatus['ok'] ?? false),
        'last_connected_at' => $session['last_connected_at'] ?? null
    ]);
    exit;
}

// -------------------------------------------------------------
// 2. START SESSION / REQUEST QR CODE
// -------------------------------------------------------------
if ($action === 'start_session' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $gwRes = call_gateway('/session/start', 'POST', ['brandId' => $brandId]);

    if (!($gwRes['ok'] ?? false)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Gagal terhubung ke WhatsApp Gateway Node.js. Pastikan service gateway berjalan pada port 3001.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'brand_id' => $brandId,
        'status' => $gwRes['status'] ?? 'connecting',
        'qr_code' => $gwRes['qrCode'] ?? null
    ]);
    exit;
}

// -------------------------------------------------------------
// 3. LOGOUT / DISCONNECT SESSION
// -------------------------------------------------------------
if ($action === 'logout_session' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $gwRes = call_gateway('/session/logout', 'POST', ['brandId' => $brandId]);

    // Update database
    $stmt = $db->prepare("
        UPDATE whatsapp_sessions 
        SET status = 'disconnected', qr_code = NULL, phone_number = NULL 
        WHERE brand_id = ?
    ");
    $stmt->execute([$brandId]);

    echo json_encode([
        'success' => true,
        'brand_id' => $brandId,
        'message' => 'Sesi WhatsApp berhasil diputuskan.'
    ]);
    exit;
}

// -------------------------------------------------------------
// 4. LIST OF CHAT THREADS / CONVERSATIONS
// -------------------------------------------------------------
if ($action === 'chats_list') {
    // Verify live WhatsApp connection status: Live chat only displays when connected!
    $gwStatus = call_gateway("/status/{$brandId}");
    $isLiveConnected = false;
    if ($gwStatus['ok'] ?? false) {
        $isLiveConnected = (($gwStatus['status'] ?? '') === 'connected');
    } else {
        $stmtSess = $db->prepare("SELECT status FROM whatsapp_sessions WHERE brand_id = ?");
        $stmtSess->execute([$brandId]);
        $isLiveConnected = ($stmtSess->fetchColumn() === 'connected');
    }

    if (!$isLiveConnected) {
        echo json_encode([
            'success' => true,
            'connected' => false,
            'status' => $gwStatus['status'] ?? 'disconnected',
            'data' => [],
            'message' => 'WhatsApp belum terhubung'
        ]);
        exit;
    }

    // Get currently connected phone number for this brand
    $stmtSess = $db->prepare("SELECT phone_number FROM whatsapp_sessions WHERE brand_id = ?");
    $stmtSess->execute([$brandId]);
    $connectedPhone = $gwStatus['phoneNumber'] ?? ($stmtSess->fetchColumn() ?: '');

    if (empty($connectedPhone)) {
        echo json_encode(['success' => true, 'connected' => false, 'data' => []]);
        exit;
    }

    $search = trim($_GET['q'] ?? '');

    // Get latest message per remote_jid strictly for currently connected WhatsApp number
    $sql = "
        SELECT 
            m.remote_jid,
            CASE 
                WHEN p.phone IS NOT NULL AND (p.phone LIKE '08%' OR p.phone LIKE '628%') THEN p.phone
                WHEN m.phone IS NOT NULL AND (m.phone LIKE '08%' OR m.phone LIKE '628%') THEN m.phone
                ELSE ''
            END AS phone,
            CASE 
                WHEN p.name IS NOT NULL AND p.name != '' AND p.name NOT LIKE 'Calon Jamaah%' AND p.name NOT LIKE 'Jamaah %' THEN p.name
                WHEN m.sender_name IS NOT NULL AND m.sender_name != '' THEN m.sender_name
                WHEN p.phone IS NOT NULL AND (p.phone LIKE '08%' OR p.phone LIKE '628%') THEN p.phone
                WHEN m.phone IS NOT NULL AND (m.phone LIKE '08%' OR m.phone LIKE '628%') THEN m.phone
                ELSE m.remote_jid
            END AS prospect_name,
            COALESCE(
                NULLIF(CASE WHEN p.name NOT LIKE 'Calon Jamaah%' AND p.name NOT LIKE 'Jamaah %' THEN p.name ELSE NULL END, ''),
                NULLIF(m.sender_name, ''),
                CASE 
                    WHEN p.phone IS NOT NULL AND (p.phone LIKE '08%' OR p.phone LIKE '628%') THEN p.phone
                    WHEN m.phone IS NOT NULL AND (m.phone LIKE '08%' OR m.phone LIKE '628%') THEN m.phone
                    ELSE m.remote_jid
                END
            ) AS sender_name,
            m.message_text AS last_message,
            m.is_deleted AS last_is_deleted,
            m.timestamp AS last_timestamp,
            m.is_from_me AS last_is_from_me,
            m.status AS last_status,
            CASE 
                WHEN m.is_from_me = 0 AND (m.status != 'read' OR m.status IS NULL) THEN 1 
                ELSE 0 
            END AS is_unread,
            CASE 
                WHEN m.is_from_me = 1 THEN 0
                ELSE (
                    SELECT COUNT(*) FROM chat_messages unread 
                    WHERE unread.brand_id = m.brand_id 
                      AND unread.session_phone = m.session_phone
                      AND unread.is_from_me = 0 
                      AND (unread.status != 'read' OR unread.status IS NULL)
                      AND (
                          unread.remote_jid = m.remote_jid 
                          OR (m.phone IS NOT NULL AND m.phone != '' AND unread.phone = m.phone)
                      )
                )
            END AS unread_count,
            p.id AS prospect_id,
            p.status AS prospect_status,
            p.current_stage AS prospect_stage,
            p.package_id,
            p.notes,
            p.meta_referral_marker,
            p.photo_url,
            p.pax_quad,
            p.pax_triple,
            p.pax_double,
            p.pax_infant,
            p.next_followup_date,
            pkg.name AS package_name,
            pkg.price AS package_price,
            pkg.dp AS package_dp,
            pkg.airline AS package_airline,
            pkg.hotel_makkah,
            pkg.hotel_madinah,
            pkg.departure_info,
            pkg.duration,
            pkg.highlights
        FROM (
            SELECT MAX(id) as max_id
            FROM chat_messages
            WHERE brand_id = ?
              AND session_phone = ?
              AND remote_jid != '0@s.whatsapp.net'
              AND message_type NOT IN ('protocolMessage', 'senderKeyDistributionMessage')
            GROUP BY 
                COALESCE(
                    NULLIF(CASE 
                        WHEN phone IS NOT NULL AND (phone LIKE '08%' OR phone LIKE '628%') THEN 
                            CASE WHEN phone LIKE '62%' THEN CONCAT('0', SUBSTRING(phone, 3)) ELSE phone END
                        ELSE NULL 
                    END, ''),
                    NULLIF(CASE 
                        WHEN remote_jid LIKE '%@s.whatsapp.net' THEN 
                            CASE WHEN remote_jid LIKE '62%' THEN CONCAT('0', SUBSTRING(REPLACE(remote_jid, '@s.whatsapp.net', ''), 3))
                                 ELSE REPLACE(remote_jid, '@s.whatsapp.net', '') END
                        ELSE NULL 
                    END, ''),
                    NULLIF(CASE WHEN prospect_id IS NOT NULL AND prospect_id > 0 THEN CONCAT('prospect_', prospect_id) ELSE NULL END, ''),
                    remote_jid
                )
        ) latest
        JOIN chat_messages m ON latest.max_id = m.id
        LEFT JOIN prospects p ON (m.prospect_id = p.id OR (p.brand_id = ? AND (p.remote_jid = m.remote_jid OR (p.phone IS NOT NULL AND p.phone = m.phone))))
        LEFT JOIN packages pkg ON p.package_id = pkg.id
        WHERE m.brand_id = ?
          AND m.session_phone = ?
          AND m.remote_jid != '0@s.whatsapp.net'
          AND m.message_type NOT IN ('protocolMessage', 'senderKeyDistributionMessage')
    ";

    $params = [$brandId, $connectedPhone, $brandId, $brandId, $connectedPhone];

    if (!empty($search)) {
        $sql .= " AND (p.name LIKE ? OR m.sender_name LIKE ? OR m.phone LIKE ? OR m.message_text LIKE ?)";
        $wildcard = "%{$search}%";
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
    }

    $sql .= " ORDER BY m.timestamp DESC LIMIT 100";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $chats = $stmt->fetchAll();

    // Deduplicate by unified conversation key (phone / prospect_id / remote_jid)
    $uniqueChats = [];
    $seenKeys = [];
    foreach ($chats as $c) {
        $pPhone = preg_replace('/[^0-9]/', '', $c['phone'] ?? '');
        if (str_starts_with($pPhone, '62')) $pPhone = '0' . substr($pPhone, 2);
        
        $key = !empty($pPhone) ? $pPhone : (!empty($c['prospect_id']) ? 'p_' . $c['prospect_id'] : $c['remote_jid']);
        if (!isset($seenKeys[$key])) {
            $seenKeys[$key] = true;
            $uniqueChats[] = $c;
        }
    }

    echo json_encode(['success' => true, 'data' => $uniqueChats]);
    exit;
}

// -------------------------------------------------------------
// 5. GET MESSAGES IN A THREAD
// -------------------------------------------------------------
if ($action === 'messages') {
    // Verify live WhatsApp connection status
    $gwStatus = call_gateway("/status/{$brandId}");
    $isLiveConnected = false;
    if ($gwStatus['ok'] ?? false) {
        $isLiveConnected = (($gwStatus['status'] ?? '') === 'connected');
    } else {
        $stmtSess = $db->prepare("SELECT status FROM whatsapp_sessions WHERE brand_id = ?");
        $stmtSess->execute([$brandId]);
        $isLiveConnected = ($stmtSess->fetchColumn() === 'connected');
    }

    if (!$isLiveConnected) {
        echo json_encode([
            'success' => true,
            'connected' => false,
            'messages' => [],
            'message' => 'WhatsApp belum terhubung'
        ]);
        exit;
    }

    $remoteJid = trim($_GET['remote_jid'] ?? '');
    $prospectId = (int)($_GET['prospect_id'] ?? 0);

    if (empty($remoteJid) && $prospectId > 0) {
        $stmtP = $db->prepare("SELECT remote_jid, phone FROM prospects WHERE id = ?");
        $stmtP->execute([$prospectId]);
        $rowP = $stmtP->fetch();
        if ($rowP) {
            $remoteJid = $rowP['remote_jid'];
            if (empty($remoteJid) && !empty($rowP['phone'])) {
                $cleanNum = preg_replace('/[^0-9]/', '', $rowP['phone']);
                if (str_starts_with($cleanNum, '0')) $cleanNum = '62' . substr($cleanNum, 1);
                $remoteJid = $cleanNum . '@s.whatsapp.net';
            }
        }
    }

    if (empty($remoteJid)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'remote_jid wajib diisi']);
        exit;
    }

    // Get currently connected phone number for this brand
    $stmtSess = $db->prepare("SELECT phone_number FROM whatsapp_sessions WHERE brand_id = ?");
    $stmtSess->execute([$brandId]);
    $connectedPhone = $gwStatus['phoneNumber'] ?? ($stmtSess->fetchColumn() ?: '');

    // Resolve prospect and clean phone
    $cleanPhone = preg_replace('/[^0-9]/', '', explode('@', $remoteJid)[0]);
    if (str_starts_with($cleanPhone, '62')) $cleanPhone = '0' . substr($cleanPhone, 2);

    $lookupPhone = trim($_GET['phone'] ?? '');
    if (!empty($lookupPhone)) {
        $lookupPhone = preg_replace('/[^0-9]/', '', $lookupPhone);
        if (str_starts_with($lookupPhone, '62')) $lookupPhone = '0' . substr($lookupPhone, 2);
    }
    if (empty($lookupPhone) && (str_starts_with($cleanPhone, '08') || str_starts_with($cleanPhone, '628'))) {
        $lookupPhone = $cleanPhone;
    }

    // Fetch associated prospect
    $prospectStmt = $db->prepare("
        SELECT p.*, u.name as cs_name, pkg.name as package_name, pkg.price as package_price, pkg.dp as package_dp,
               pkg.airline as package_airline, pkg.hotel_makkah as package_hotel_makkah,
               pkg.hotel_madinah as package_hotel_madinah, pkg.departure_info as package_departure_info,
               pkg.duration as package_duration, pkg.highlights as package_highlights
        FROM prospects p
        LEFT JOIN packages pkg ON p.package_id = pkg.id
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.brand_id = ? AND (p.remote_jid = ? OR (? != '' AND (p.phone = ? OR p.phone = ?)) OR (? > 0 AND p.id = ?))
        LIMIT 1
    ");
    $altLookupPhone = !empty($lookupPhone) ? ('62' . substr($lookupPhone, 1)) : '';
    $prospectStmt->execute([$brandId, $remoteJid, $lookupPhone, $lookupPhone, $altLookupPhone, $prospectId, $prospectId]);
    $prospect = $prospectStmt->fetch();

    if ($prospect) {
        $prospectId = (int)$prospect['id'];
        if (empty($lookupPhone) && !empty($prospect['phone'])) {
            $lookupPhone = preg_replace('/[^0-9]/', '', $prospect['phone']);
            if (str_starts_with($lookupPhone, '62')) $lookupPhone = '0' . substr($lookupPhone, 2);
        }
        if (empty($prospect['name']) || str_starts_with($prospect['name'], 'Calon Jamaah') || str_starts_with($prospect['name'], 'Jamaah ')) {
            $stmtSender = $db->prepare("SELECT sender_name, phone FROM chat_messages WHERE brand_id = ? AND remote_jid = ? AND sender_name IS NOT NULL AND sender_name != '' ORDER BY id DESC LIMIT 1");
            $stmtSender->execute([$brandId, $remoteJid]);
            $senderRow = $stmtSender->fetch();
            if ($senderRow && !empty($senderRow['sender_name'])) {
                $prospect['name'] = $senderRow['sender_name'];
            } elseif (!empty($prospect['phone'])) {
                $prospect['name'] = $prospect['phone'];
            } else {
                $prospect['name'] = $remoteJid;
            }
        }
    }

    if (empty($lookupPhone)) {
        $stmtM = $db->prepare("SELECT phone, prospect_id FROM chat_messages WHERE brand_id = ? AND remote_jid = ? AND phone IS NOT NULL AND phone != '' AND (phone LIKE '08%' OR phone LIKE '628%') ORDER BY id DESC LIMIT 1");
        $stmtM->execute([$brandId, $remoteJid]);
        $rowM = $stmtM->fetch();
        if ($rowM) {
            $lookupPhone = preg_replace('/[^0-9]/', '', $rowM['phone']);
            if (str_starts_with($lookupPhone, '62')) $lookupPhone = '0' . substr($lookupPhone, 2);
            if (!$prospectId && !empty($rowM['prospect_id'])) $prospectId = (int)$rowM['prospect_id'];
        }
    }

    // Mark unread inbound messages in this conversation as 'read'
    $findUnreadSql = "
        SELECT id, message_id, remote_jid 
        FROM chat_messages
        WHERE brand_id = ? AND session_phone = ?
          AND is_from_me = 0
          AND (status != 'read' OR status IS NULL)
    ";
    $findUnreadParams = [$brandId, $connectedPhone];
    if (!empty($lookupPhone)) {
        $phoneJid = '62' . substr($lookupPhone, 1) . '@s.whatsapp.net';
        $altPhone = '62' . substr($lookupPhone, 1);
        $findUnreadSql .= " AND (remote_jid = ? OR remote_jid = ? OR phone = ? OR phone = ? " . ($prospectId > 0 ? "OR prospect_id = ?" : "") . ")";
        $findUnreadParams[] = $remoteJid;
        $findUnreadParams[] = $phoneJid;
        $findUnreadParams[] = $lookupPhone;
        $findUnreadParams[] = $altPhone;
        if ($prospectId > 0) $findUnreadParams[] = $prospectId;
    } else {
        $findUnreadSql .= " AND remote_jid = ?";
        $findUnreadParams[] = $remoteJid;
    }
    $findUnreadStmt = $db->prepare($findUnreadSql);
    $findUnreadStmt->execute($findUnreadParams);
    $unreadRows = $findUnreadStmt->fetchAll();

    if (!empty($unreadRows)) {
        $idsToMark = array_column($unreadRows, 'id');
        $inClause = implode(',', array_fill(0, count($idsToMark), '?'));
        $db->prepare("UPDATE chat_messages SET status = 'read' WHERE id IN ($inClause)")->execute($idsToMark);

        // Forward to gateway so Baileys sends blue tick read receipts
        $keysToRead = [];
        foreach ($unreadRows as $ur) {
            if (!empty($ur['message_id'])) {
                $keysToRead[] = [
                    'remoteJid' => $ur['remote_jid'],
                    'id' => $ur['message_id']
                ];
            }
        }
        if (!empty($keysToRead)) {
            call_gateway('/message/mark-read', 'POST', [
                'brandId' => $brandId,
                'keys' => $keysToRead
            ]);
        }
    }

    // Fetch messages strictly for the currently connected WhatsApp account
    // Unified across remote_jid, phone, and prospect_id
    $msgSql = "
        SELECT * FROM chat_messages
        WHERE brand_id = ? AND session_phone = ?
          AND message_type NOT IN ('protocolMessage', 'senderKeyDistributionMessage')
    ";
    $msgParams = [$brandId, $connectedPhone];

    if (!empty($lookupPhone)) {
        $phoneJid = '62' . substr($lookupPhone, 1) . '@s.whatsapp.net';
        $altPhone = '62' . substr($lookupPhone, 1);
        $msgSql .= " AND (remote_jid = ? OR remote_jid = ? OR phone = ? OR phone = ? " . ($prospectId > 0 ? "OR prospect_id = ?" : "") . ")";
        $msgParams[] = $remoteJid;
        $msgParams[] = $phoneJid;
        $msgParams[] = $lookupPhone;
        $msgParams[] = $altPhone;
        if ($prospectId > 0) $msgParams[] = $prospectId;
    } else {
        $msgSql .= " AND remote_jid = ?";
        $msgParams[] = $remoteJid;
    }

    $msgSql .= " ORDER BY timestamp ASC, id ASC LIMIT 200";
    $stmt = $db->prepare($msgSql);
    $stmt->execute($msgParams);
    $messages = $stmt->fetchAll();

    // Fetch brand packages for quick select in chat
    $pkgStmt = $db->prepare("SELECT * FROM packages WHERE brand_id = ? AND is_active = 1 ORDER BY id ASC");
    $pkgStmt->execute([$brandId]);
    $packages = $pkgStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'remote_jid' => $remoteJid,
        'prospect' => $prospect ?: null,
        'packages' => $packages,
        'messages' => $messages
    ]);
    exit;
}

// -------------------------------------------------------------
// 6. SEND MESSAGE TO WHATSAPP
// -------------------------------------------------------------
if ($action === 'send_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $remoteJid = trim($_POST['remote_jid'] ?? '');
    $text = trim($_POST['text'] ?? '');
    $prospectId = !empty($_POST['prospect_id']) ? (int)$_POST['prospect_id'] : null;
    $phone = trim($_POST['phone'] ?? '');

    if (empty($remoteJid) || empty($text)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'remote_jid dan text wajib diisi']);
        exit;
    }

    // Look up prospect phone and prospectId if not explicitly provided
    if (!$prospectId) {
        $checkPhone = !empty($phone) ? $phone : preg_replace('/[^0-9]/', '', explode('@', $remoteJid)[0]);
        if (str_starts_with($checkPhone, '62')) $checkPhone = '0' . substr($checkPhone, 2);
        if (!empty($checkPhone)) {
            $altCheckPhone = '62' . substr($checkPhone, 1);
            $stmtP = $db->prepare("SELECT id, remote_jid, phone FROM prospects WHERE brand_id = ? AND (phone = ? OR phone = ? OR remote_jid = ?) LIMIT 1");
            $stmtP->execute([$brandId, $checkPhone, $altCheckPhone, $remoteJid]);
            $existingP = $stmtP->fetch();
            if ($existingP) {
                $prospectId = (int)$existingP['id'];
                if (empty($phone) && !empty($existingP['phone'])) $phone = $existingP['phone'];
                if (!empty($existingP['remote_jid'])) {
                    $remoteJid = $existingP['remote_jid'];
                }
            }
        }
    } else {
        $stmtP = $db->prepare("SELECT id, remote_jid, phone FROM prospects WHERE id = ?");
        $stmtP->execute([$prospectId]);
        $rowP = $stmtP->fetch();
        if ($rowP) {
            if (empty($phone) && !empty($rowP['phone'])) $phone = $rowP['phone'];
            if (!empty($rowP['remote_jid']) && str_ends_with($rowP['remote_jid'], '@lid')) {
                $remoteJid = $rowP['remote_jid'];
            }
        }
    }

    if (empty($phone)) {
        $stmtM = $db->prepare("SELECT phone FROM chat_messages WHERE brand_id = ? AND remote_jid = ? AND phone IS NOT NULL AND phone != '' AND (phone LIKE '08%' OR phone LIKE '628%') ORDER BY id DESC LIMIT 1");
        $stmtM->execute([$brandId, $remoteJid]);
        $phone = $stmtM->fetchColumn() ?: '';
    }

    // Get current connected phone for session_phone
    $stmtSess = $db->prepare("SELECT phone_number FROM whatsapp_sessions WHERE brand_id = ?");
    $stmtSess->execute([$brandId]);
    $sessionPhone = $stmtSess->fetchColumn() ?: null;

    $quotedMessageId = !empty($_POST['quoted_message_id']) ? trim($_POST['quoted_message_id']) : null;
    $quotedText = !empty($_POST['quoted_text']) ? trim($_POST['quoted_text']) : null;
    $quotedSender = !empty($_POST['quoted_sender']) ? trim($_POST['quoted_sender']) : null;
    $quotedFromMe = !empty($_POST['quoted_from_me']) ? (int)$_POST['quoted_from_me'] : 0;

    // Forward to Node gateway
    $sendPayload = [
        'brandId' => $brandId,
        'to' => $remoteJid,
        'phone' => $phone,
        'text' => $text
    ];
    if (!empty($quotedMessageId)) {
        $sendPayload['quoted'] = [
            'id' => $quotedMessageId,
            'remoteJid' => $remoteJid,
            'fromMe' => $quotedFromMe,
            'text' => $quotedText
        ];
    }
    $gwRes = call_gateway('/message/send', 'POST', $sendPayload);

    if (!($gwRes['ok'] ?? false)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $gwRes['error'] ?? 'Gagal mengirim pesan WhatsApp. Pastikan sesi WhatsApp terhubung.'
        ]);
        exit;
    }

    $messageId = $gwRes['messageId'] ?? ('out_' . uniqid());
    $timestamp = $gwRes['timestamp'] ?? time();
    $effectivePhone = !empty($phone) ? $phone : preg_replace('/[^0-9]/', '', explode('@', $remoteJid)[0]);
    if (str_starts_with($effectivePhone, '62')) $effectivePhone = '0' . substr($effectivePhone, 2);

    // Persist to chat_messages
    $ins = $db->prepare("
        INSERT INTO chat_messages (
            brand_id, session_phone, prospect_id, message_id, remote_jid, phone,
            sender_name, is_from_me, message_type, message_text,
            quoted_message_id, quoted_text, quoted_sender,
            status, timestamp
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'conversation', ?, ?, ?, ?, 'sent', ?)
        ON DUPLICATE KEY UPDATE message_text = VALUES(message_text)
    ");
    $ins->execute([
        $brandId,
        $sessionPhone,
        $prospectId,
        $messageId,
        $remoteJid,
        $effectivePhone,
        $user['name'],
        $text,
        $quotedMessageId,
        $quotedText,
        $quotedSender,
        $timestamp
    ]);

    // Update prospect updated_at timestamp & auto-assign to replying CS
    if ($prospectId) {
        if ($user['role'] === 'cs') {
            $db->prepare("UPDATE prospects SET updated_at = NOW(), user_id = ? WHERE id = ?")->execute([$user['id'], $prospectId]);
        } else {
            $db->prepare("UPDATE prospects SET updated_at = NOW() WHERE id = ?")->execute([$prospectId]);
        }
    }

    echo json_encode([
        'success' => true,
        'message_id' => $messageId,
        'timestamp' => $timestamp,
        'status' => 'sent',
        'sender_name' => $user['name'],
        'text' => $text,
        'quoted_message_id' => $quotedMessageId,
        'quoted_text' => $quotedText,
        'quoted_sender' => $quotedSender
    ]);
    exit;
}

// -------------------------------------------------------------
// 6b. REACT TO A MESSAGE
// -------------------------------------------------------------
if ($action === 'react' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $remoteJid = trim($_POST['remote_jid'] ?? '');
    $messageId = trim($_POST['message_id'] ?? '');
    $emoji = trim($_POST['emoji'] ?? '');
    $isFromMe = (int)($_POST['is_from_me'] ?? 0);
    $phone = trim($_POST['phone'] ?? '');

    if (empty($remoteJid) || empty($messageId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'remote_jid dan message_id wajib diisi']);
        exit;
    }

    $gwRes = call_gateway('/message/react', 'POST', [
        'brandId' => $brandId,
        'remoteJid' => $remoteJid,
        'phone' => $phone,
        'messageId' => $messageId,
        'isFromMe' => $isFromMe,
        'emoji' => $emoji
    ]);

    // Update in database
    $stmt = $db->prepare("UPDATE chat_messages SET reaction = ? WHERE brand_id = ? AND message_id = ?");
    $stmt->execute([$emoji ?: null, $brandId, $messageId]);

    echo json_encode([
        'success' => true,
        'message_id' => $messageId,
        'reaction' => $emoji ?: null
    ]);
    exit;
}

// -------------------------------------------------------------
// 6b. SEND MEDIA (IMAGE / DOCUMENT) TO WHATSAPP
// -------------------------------------------------------------
if ($action === 'send_media' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $remoteJid = trim($_POST['remote_jid'] ?? '');
    $mediaType = trim($_POST['media_type'] ?? 'image'); // 'image' or 'document'
    $caption = trim($_POST['caption'] ?? '');
    $prospectId = !empty($_POST['prospect_id']) ? (int)$_POST['prospect_id'] : null;
    $phone = trim($_POST['phone'] ?? '');

    if (empty($remoteJid)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'remote_jid wajib diisi']);
        exit;
    }

    $existingFilePath = trim($_POST['existing_file_path'] ?? '');
    $hasUpload = isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK;

    if (!$hasUpload && empty($existingFilePath)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'File lampiran atau flyer tidak ditemukan']);
        exit;
    }

    $uploadDir = __DIR__ . '/../uploads/chat/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $imageExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $docExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', 'csv'];

    if ($hasUpload) {
        $uploadedFile = $_FILES['file'];
        $fileSize = $uploadedFile['size'];
        $maxSize = 25 * 1024 * 1024; // 25 MB

        if ($fileSize > $maxSize) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ukuran file melebihi batas maksimal 25MB']);
            exit;
        }

        $origName = basename($uploadedFile['name']);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if ($mediaType === 'image') {
            if (!in_array($ext, $imageExts)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Format file gambar tidak didukung (harus JPG, PNG, WEBP, atau GIF)']);
                exit;
            }
        } else {
            $mediaType = 'document';
            if (!in_array($ext, array_merge($imageExts, $docExts))) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Format dokumen tidak didukung']);
                exit;
            }
        }

        $uniqueName = $brandId . '_out_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $targetPath = $uploadDir . $uniqueName;

        if (!move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Gagal menyimpan file di server']);
            exit;
        }
    } else {
        // Send existing server file (e.g. package flyer)
        $cleanRel = str_replace(['..', '\\'], ['', '/'], ltrim($existingFilePath, '/'));
        $fullPath = realpath(__DIR__ . '/../' . $cleanRel);
        $allowedBase = realpath(__DIR__ . '/../uploads');
        if (!$fullPath || !str_starts_with($fullPath, $allowedBase) || !file_exists($fullPath)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Berkas flyer tidak ditemukan di server']);
            exit;
        }

        $origName = basename($fullPath);
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if ($mediaType === 'image') {
            if (!in_array($ext, $imageExts)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Format flyer gambar tidak didukung']);
                exit;
            }
        }

        $uniqueName = $brandId . '_out_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $targetPath = $uploadDir . $uniqueName;

        if (!copy($fullPath, $targetPath)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Gagal menyiapkan file flyer di server']);
            exit;
        }
    }

    // Convert WebP or non-PNG images to standard JPG for WhatsApp client compatibility
    if ($mediaType === 'image' && $ext !== 'png') {
        if ($ext === 'webp' && function_exists('imagecreatefromwebp') && function_exists('imagejpeg')) {
            $imgRes = @imagecreatefromwebp($targetPath);
            if ($imgRes) {
                $jpgName = $brandId . '_out_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
                $jpgPath = $uploadDir . $jpgName;
                imagejpeg($imgRes, $jpgPath, 85);
                imagedestroy($imgRes);
                @unlink($targetPath);
                $targetPath = $jpgPath;
                $uniqueName = $jpgName;
                $ext = 'jpg';
            }
        }
    }

    $mediaUrl = 'uploads/chat/' . $uniqueName;

    if ($mediaType === 'image') {
        $mimeType = ($ext === 'png') ? 'image/png' : 'image/jpeg';
    } else {
        $mimeMap = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed'
        ];
        $mimeType = $mimeMap[$ext] ?? mime_content_type($targetPath) ?? 'application/octet-stream';
    }

    // Look up prospect phone and prospectId if not explicitly provided
    if (!$prospectId) {
        $checkPhone = !empty($phone) ? $phone : preg_replace('/[^0-9]/', '', explode('@', $remoteJid)[0]);
        if (str_starts_with($checkPhone, '62')) $checkPhone = '0' . substr($checkPhone, 2);
        if (!empty($checkPhone)) {
            $altCheckPhone = '62' . substr($checkPhone, 1);
            $stmtP = $db->prepare("SELECT id, remote_jid, phone FROM prospects WHERE brand_id = ? AND (phone = ? OR phone = ? OR remote_jid = ?) LIMIT 1");
            $stmtP->execute([$brandId, $checkPhone, $altCheckPhone, $remoteJid]);
            $existingP = $stmtP->fetch();
            if ($existingP) {
                $prospectId = (int)$existingP['id'];
                if (empty($phone) && !empty($existingP['phone'])) $phone = $existingP['phone'];
                if (!empty($existingP['remote_jid'])) {
                    $remoteJid = $existingP['remote_jid'];
                }
            }
        }
    } else {
        $stmtP = $db->prepare("SELECT id, remote_jid, phone FROM prospects WHERE id = ?");
        $stmtP->execute([$prospectId]);
        $rowP = $stmtP->fetch();
        if ($rowP) {
            if (empty($phone) && !empty($rowP['phone'])) $phone = $rowP['phone'];
            if (!empty($rowP['remote_jid']) && str_ends_with($rowP['remote_jid'], '@lid')) {
                $remoteJid = $rowP['remote_jid'];
            }
        }
    }

    if (empty($phone)) {
        $stmtM = $db->prepare("SELECT phone FROM chat_messages WHERE brand_id = ? AND remote_jid = ? AND phone IS NOT NULL AND phone != '' AND (phone LIKE '08%' OR phone LIKE '628%') ORDER BY id DESC LIMIT 1");
        $stmtM->execute([$brandId, $remoteJid]);
        $phone = $stmtM->fetchColumn() ?: '';
    }

    // Get current connected phone for session_phone
    $stmtSess = $db->prepare("SELECT phone_number FROM whatsapp_sessions WHERE brand_id = ?");
    $stmtSess->execute([$brandId]);
    $sessionPhone = $stmtSess->fetchColumn() ?: null;

    // Send to Node.js Gateway
    $gwRes = call_gateway('/message/send-media', 'POST', [
        'brandId' => $brandId,
        'to' => $remoteJid,
        'phone' => $phone,
        'mediaType' => $mediaType,
        'filePath' => realpath($targetPath) ?: $targetPath,
        'fileName' => $origName,
        'mimetype' => $mimeType,
        'caption' => $caption
    ]);

    if (!($gwRes['ok'] ?? false)) {
        @unlink($targetPath); // Remove unneeded file if send failed
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $gwRes['error'] ?? 'Gagal mengirim media ke WhatsApp. Pastikan sesi WhatsApp terhubung.'
        ]);
        exit;
    }

    $messageId = $gwRes['messageId'] ?? ('out_' . uniqid());
    $timestamp = $gwRes['timestamp'] ?? time();
    $effectivePhone = !empty($phone) ? $phone : preg_replace('/[^0-9]/', '', explode('@', $remoteJid)[0]);
    if (str_starts_with($effectivePhone, '62')) $effectivePhone = '0' . substr($effectivePhone, 2);

    $displayText = !empty($caption) ? $caption : $origName;

    // Persist to chat_messages
    $ins = $db->prepare("
        INSERT INTO chat_messages (
            brand_id, session_phone, prospect_id, message_id, remote_jid, phone,
            sender_name, is_from_me, message_type, message_text, media_url, status, timestamp
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, 'sent', ?)
        ON DUPLICATE KEY UPDATE 
            message_text = VALUES(message_text),
            media_url = VALUES(media_url)
    ");
    $ins->execute([
        $brandId,
        $sessionPhone,
        $prospectId,
        $messageId,
        $remoteJid,
        $effectivePhone,
        $user['name'],
        $mediaType,
        $displayText,
        $mediaUrl,
        $timestamp
    ]);

    // Update prospect updated_at timestamp & auto-assign to replying CS
    if ($prospectId) {
        if ($user['role'] === 'cs') {
            $db->prepare("UPDATE prospects SET updated_at = NOW(), user_id = ? WHERE id = ?")->execute([$user['id'], $prospectId]);
        } else {
            $db->prepare("UPDATE prospects SET updated_at = NOW() WHERE id = ?")->execute([$prospectId]);
        }
    }

    echo json_encode([
        'success' => true,
        'message_id' => $messageId,
        'timestamp' => $timestamp,
        'status' => 'sent',
        'sender_name' => $user['name'],
        'media_type' => $mediaType,
        'media_url' => $mediaUrl,
        'file_name' => $origName,
        'text' => $displayText
    ]);
    exit;
}

// -------------------------------------------------------------
// 7. FETCH PROFILE PICTURES (SINGLE OR BATCH)
// -------------------------------------------------------------
if ($action === 'fetch_profile_pictures') {
    $contacts = $_POST['contacts'] ?? [];
    if (empty($contacts) || !is_array($contacts)) {
        // Support single query via GET
        $singleJid = trim($_GET['remote_jid'] ?? '');
        $singlePhone = trim($_GET['phone'] ?? '');
        if (!empty($singleJid) || !empty($singlePhone)) {
            $contacts = [['jid' => $singleJid, 'phone' => $singlePhone]];
        }
    }

    if (empty($contacts)) {
        echo json_encode(['success' => true, 'results' => []]);
        exit;
    }

    // Call Gateway batch endpoint
    $gwRes = call_gateway("/profile-pictures-batch/{$brandId}", 'POST', ['contacts' => $contacts]);
    $results = $gwRes['results'] ?? [];

    // Save found profile pictures to prospects table
    foreach ($results as $key => $photoUrl) {
        if (!empty($photoUrl)) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $key);
            if (str_starts_with($cleanPhone, '62')) $cleanPhone = '0' . substr($cleanPhone, 2);
            $altPhone = !empty($cleanPhone) ? ('62' . substr($cleanPhone, 1)) : '';

            $uStmt = $db->prepare("
                UPDATE prospects 
                SET photo_url = ? 
                WHERE brand_id = ? 
                  AND (remote_jid = ? OR phone = ? OR phone = ?)
            ");
            $uStmt->execute([$photoUrl, $brandId, $key, $cleanPhone, $altPhone]);
        }
    }

    echo json_encode(['success' => true, 'results' => $results]);
    exit;
}

// -------------------------------------------------------------
// 8. DELETE / REVOKE MESSAGE FOR EVERYONE
// -------------------------------------------------------------
if ($action === 'delete_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $messageId = trim($_POST['message_id'] ?? '');
    $remoteJid = trim($_POST['remote_jid'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($messageId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'message_id wajib diisi']);
        exit;
    }

    // Lookup message from DB
    $stmt = $db->prepare("SELECT * FROM chat_messages WHERE brand_id = ? AND message_id = ? LIMIT 1");
    $stmt->execute([$brandId, $messageId]);
    $msg = $stmt->fetch();

    if ($msg) {
        if (empty($remoteJid)) $remoteJid = $msg['remote_jid'];
        if (empty($phone)) $phone = $msg['phone'];
    }

    // Call Gateway /message/delete
    $gwRes = call_gateway('/message/delete', 'POST', [
        'brandId' => $brandId,
        'remoteJid' => $remoteJid,
        'phone' => $phone,
        'messageId' => $messageId
    ]);

    if (!($gwRes['ok'] ?? false)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $gwRes['error'] ?? 'Gagal menarik pesan dari WhatsApp'
        ]);
        exit;
    }

    // Update database is_deleted flag (preserve message_text for CS/admin inspection)
    $stmtUp = $db->prepare("UPDATE chat_messages SET is_deleted = 1, deleted_at = NOW() WHERE brand_id = ? AND message_id = ?");
    $stmtUp->execute([$brandId, $messageId]);


    echo json_encode([
        'success' => true,
        'message_id' => $messageId,
        'is_deleted' => 1
    ]);
    exit;
}

http_response_code(404);
echo json_encode(['success' => false, 'error' => 'Action not found']);
