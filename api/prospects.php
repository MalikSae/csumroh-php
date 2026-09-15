<?php
/**
 * Prospects API Endpoint (Full CRM Umroh Standard)
 * Handles CRUD, Stage Transition, Pipeline Analytics, and Follow-up Tracking
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/meta_capi.php';

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

$action = $_POST['action'] ?? $_GET['action'] ?? $_REQUEST['action'] ?? '';

// Helper to clean price number
function clean_num($str, $fallback = 0) {
    if (!$str) return (float)$fallback;
    $num = preg_replace('/[^\d]/', '', (string)$str);
    return $num ? (float)$num : (float)$fallback;
}

// Helper to calculate deal value from package and room pax
function calc_prospect_deal_value(PDO $db, ?int $pkgId, int $quad, int $triple, int $double, int $infant, ?float $overrideVal = null): float {
    if ($overrideVal !== null && $overrideVal > 0) {
        return $overrideVal;
    }
    if (!$pkgId) {
        return 0;
    }
    $stmt = $db->prepare("SELECT price, price_quad, price_triple, price_double, price_infant FROM packages WHERE id = ?");
    $stmt->execute([$pkgId]);
    $pkg = $stmt->fetch();
    if (!$pkg) return 0;

    $def = clean_num($pkg['price'], 0);
    $pQuad = clean_num($pkg['price_quad'], $def);
    $pTriple = clean_num($pkg['price_triple'], $def);
    $pDouble = clean_num($pkg['price_double'], $def);
    $pInfant = clean_num($pkg['price_infant'], 0);

    return ($quad * $pQuad) + ($triple * $pTriple) + ($double * $pDouble) + ($infant * $pInfant);
}

// -------------------------------------------------------------
// 1. GET LIST OF PROSPECTS (WITH FULL CRM & PACKAGE FIELDS)
// -------------------------------------------------------------
if ($action === 'list') {
    $brandId = ($user['role'] === 'superadmin' && !empty($_GET['brand_id'])) 
                ? (int)$_GET['brand_id'] 
                : ($user['brand_id'] ?: 1);

    $sql = "
        SELECT p.*, b.name AS brand_name,
               pkg.name AS package_name, pkg.price AS package_price, pkg.dp AS package_dp,
               pkg.price_quad, pkg.price_triple, pkg.price_double, pkg.price_infant,
               pkg.quota_remaining, pkg.departure_date, pkg.airline AS package_airline,
               pkg.hotel_makkah AS package_hotel_makkah, pkg.hotel_madinah AS package_hotel_madinah,
               pkg.flyer_image,
               u.name AS cs_name
        FROM prospects p
        JOIN brands b ON p.brand_id = b.id
        LEFT JOIN packages pkg ON p.package_id = pkg.id
        LEFT JOIN users u ON p.user_id = u.id
    ";

    $params = [];
    $sql .= " WHERE p.brand_id = ?";
    $params[] = $brandId;

    if (!empty($_GET['user_id'])) {
        $sql .= " AND p.user_id = ?";
        $params[] = (int)$_GET['user_id'];
    }

    $sql .= " ORDER BY p.updated_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $prospects = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $prospects]);
    exit;
}

// -------------------------------------------------------------
// 2. SAVE OR UPDATE PROSPECT (FULL CRM STANDARD)
// -------------------------------------------------------------
if (($action === 'save' || $action === 'create' || $action === 'update') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $package_id = !empty($_POST['package_id']) ? (int)$_POST['package_id'] : null;
    $current_stage = $_POST['current_stage'] ?? 'greeting';
    $status = $_POST['status'] ?? 'new';
    $notes = trim($_POST['notes'] ?? '');
    $next_followup_date = !empty($_POST['next_followup_date']) ? $_POST['next_followup_date'] : null;

    // Room Pax
    $pax_quad = isset($_POST['pax_quad']) ? max(0, (int)$_POST['pax_quad']) : 0;
    $pax_triple = isset($_POST['pax_triple']) ? max(0, (int)$_POST['pax_triple']) : 0;
    $pax_double = isset($_POST['pax_double']) ? max(0, (int)$_POST['pax_double']) : 0;
    $pax_infant = isset($_POST['pax_infant']) ? max(0, (int)$_POST['pax_infant']) : 0;

    // CRM Extended Fields
    $city = trim($_POST['city'] ?? '');
    $lead_source = trim($_POST['lead_source'] ?? 'whatsapp');
    $target_month = trim($_POST['target_month'] ?? '');
    $budget_range = trim($_POST['budget_range'] ?? '');
    $room_preference = trim($_POST['room_preference'] ?? '');
    $special_needs = trim($_POST['special_needs'] ?? '');
    $decision_maker = trim($_POST['decision_maker'] ?? '');
    $passport_status = trim($_POST['passport_status'] ?? 'belum_ada');
    $vaccine_status = trim($_POST['vaccine_status'] ?? 'belum');

    // Financial
    $overrideDeal = isset($_POST['deal_value']) && is_numeric($_POST['deal_value']) ? (float)$_POST['deal_value'] : null;
    $deal_value = calc_prospect_deal_value($db, $package_id, $pax_quad, $pax_triple, $pax_double, $pax_infant, $overrideDeal);
    $dp_amount = isset($_POST['dp_amount']) ? (float)clean_num($_POST['dp_amount']) : 0;
    $payment_status = trim($_POST['payment_status'] ?? ($dp_amount > 0 ? 'partial_dp' : 'unpaid'));
    $dp_paid_at = !empty($_POST['dp_paid_at']) ? $_POST['dp_paid_at'] : ($dp_amount > 0 ? date('Y-m-d H:i:s') : null);

    // Lost evaluation
    $lost_reason = trim($_POST['lost_reason'] ?? '');
    $lost_reason_detail = trim($_POST['lost_reason_detail'] ?? '');

    $brand_id = ($user['role'] === 'superadmin' && !empty($_POST['brand_id']))
                ? (int)$_POST['brand_id']
                : ($user['brand_id'] ?: 1);

    if (empty($name)) {
        if ($id > 0) {
            $currNameStmt = $db->prepare("SELECT name FROM prospects WHERE id = ?");
            $currNameStmt->execute([$id]);
            $name = $currNameStmt->fetchColumn() ?: 'Calon Jamaah';
        } else {
            $name = !empty($phone) ? ('Jamaah ' . $phone) : 'Calon Jamaah';
        }
    }

    // Format phone
    if (!empty($phone)) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }
    }

    $remote_jid = trim($_POST['remote_jid'] ?? '');

    if ($id > 0) {
        $oldStmt = $db->prepare("SELECT * FROM prospects WHERE id = ?");
        $oldStmt->execute([$id]);
        $old = $oldStmt->fetch();

        $targetUserId = (!empty($_POST['reassign_to_me']) || !empty($_POST['claim'])) ? $user['id'] : ($old['user_id'] ?? $user['id']);
        if (!empty($_POST['user_id'])) {
            $targetUserId = (int)$_POST['user_id'];
        }

        $stmt = $db->prepare("
            UPDATE prospects 
            SET user_id = ?, package_id = ?, name = ?, phone = ?, current_stage = ?, status = ?, notes = ?, next_followup_date = ?,
                pax_quad = ?, pax_triple = ?, pax_double = ?, pax_infant = ?,
                city = ?, lead_source = ?, target_month = ?, budget_range = ?, room_preference = ?,
                special_needs = ?, decision_maker = ?, passport_status = ?, vaccine_status = ?,
                deal_value = ?, dp_amount = ?, dp_paid_at = ?, payment_status = ?,
                lost_reason = ?, lost_reason_detail = ?,
                remote_jid = IF(remote_jid IS NULL OR remote_jid = '', ?, remote_jid)
            WHERE id = ?
        ");
        $stmt->execute([
            $targetUserId,
            $package_id, $name, $phone, $current_stage, $status, $notes, $next_followup_date,
            $pax_quad, $pax_triple, $pax_double, $pax_infant,
            $city, $lead_source, $target_month, $budget_range, $room_preference,
            $special_needs, $decision_maker, $passport_status, $vaccine_status,
            $deal_value, $dp_amount, $dp_paid_at, $payment_status,
            $lost_reason, $lost_reason_detail,
            $remote_jid ?: null, $id
        ]);
        $msg = 'Data profil prospek berhasil diperbarui.';

        if (!empty($remote_jid)) {
            $db->prepare("UPDATE chat_messages SET prospect_id = ? WHERE brand_id = ? AND remote_jid = ?")->execute([$id, $brand_id, $remote_jid]);
        }

        if ($old && $old['user_id'] != $targetUserId) {
            log_prospect_activity($id, $user['id'], 'reassigned', 'PIC Diperbarui', "Penanggung jawab dialihkan ke ID {$targetUserId}.");
        }

        // Activity audit logs
        if ($old && $old['status'] !== $status) {
            log_prospect_activity($id, $user['id'], 'status_changed', 'Status Tahapan Diperbarui', "Status diubah dari '{$old['status']}' ke '{$status}'.");
            if ($status === 'closed_won') {
                $capiRes = send_meta_purchase_event($id, $brand_id);
                if ($capiRes['success']) {
                    log_prospect_activity($id, $user['id'], 'meta_capi', 'Meta CAPI Purchase Terkirim', 'Event Purchase DP terkirim ke Meta Conversions API. ID: ' . $capiRes['event_id']);
                }
            }
        } else {
            log_prospect_activity($id, $user['id'], 'updated', 'Profil CRM Diperbarui', 'Data kualifikasi dan rincian prospek diperbarui.');
        }
    } else {
        $stmt = $db->prepare("
            INSERT INTO prospects (
                brand_id, user_id, package_id, name, phone, remote_jid, current_stage, status, notes, next_followup_date,
                pax_quad, pax_triple, pax_double, pax_infant,
                city, lead_source, target_month, budget_range, room_preference,
                special_needs, decision_maker, passport_status, vaccine_status,
                deal_value, dp_amount, dp_paid_at, payment_status,
                lost_reason, lost_reason_detail
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $brand_id, $user['id'], $package_id, $name, $phone, $remote_jid ?: null, $current_stage, $status, $notes, $next_followup_date,
            $pax_quad, $pax_triple, $pax_double, $pax_infant,
            $city, $lead_source, $target_month, $budget_range, $room_preference,
            $special_needs, $decision_maker, $passport_status, $vaccine_status,
            $deal_value, $dp_amount, $dp_paid_at, $payment_status,
            $lost_reason, $lost_reason_detail
        ]);
        $id = $db->lastInsertId();
        $msg = 'Prospek baru berhasil dicatat ke pipeline CRM.';

        if (!empty($remote_jid)) {
            $db->prepare("UPDATE chat_messages SET prospect_id = ? WHERE brand_id = ? AND remote_jid = ?")->execute([$id, $brand_id, $remote_jid]);
        }

        log_prospect_activity($id, $user['id'], 'created', 'Prospek Masuk ke CRM', 'Calon jamaah didaftarkan ke sistem pipeline konversi.');
    }

    $fetchStmt = $db->prepare("
        SELECT p.*, u.name as cs_name, pkg.name as package_name, pkg.price as package_price, pkg.dp as package_dp,
               pkg.price_quad, pkg.price_triple, pkg.price_double, pkg.price_infant,
               pkg.quota_remaining, pkg.departure_date, pkg.airline as package_airline,
               pkg.hotel_makkah as package_hotel_makkah, pkg.hotel_madinah as package_hotel_madinah,
               pkg.departure_info as package_departure_info, pkg.duration as package_duration,
               pkg.highlights as package_highlights, pkg.flyer_image
        FROM prospects p
        LEFT JOIN packages pkg ON p.package_id = pkg.id
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ");
    $fetchStmt->execute([$id]);
    $savedProspect = $fetchStmt->fetch();

    echo json_encode(['success' => true, 'message' => $msg, 'id' => $id, 'prospect' => $savedProspect]);
    exit;
}

// -------------------------------------------------------------
// 3. CLAIM PROSPECT PIC (AMBIL ALIH / PINDAH CS)
// -------------------------------------------------------------
if ($action === 'claim' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = !empty($input['id']) ? (int)$input['id'] : 0;
    $targetUserId = !empty($input['user_id']) ? (int)$input['user_id'] : $user['id'];

    if ($id > 0) {
        $pStmt = $db->prepare("SELECT id, brand_id, user_id FROM prospects WHERE id = ?");
        $pStmt->execute([$id]);
        $prospect = $pStmt->fetch();

        if (!$prospect) {
            echo json_encode(['success' => false, 'error' => 'Prospek tidak ditemukan.']);
            exit;
        }

        if ($user['role'] !== 'superadmin' && $prospect['brand_id'] != $user['brand_id']) {
            echo json_encode(['success' => false, 'error' => 'Akses ditolak ke brand ini.']);
            exit;
        }

        $stmt = $db->prepare("UPDATE prospects SET user_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$targetUserId, $id]);

        $uStmt = $db->prepare("SELECT id, name FROM users WHERE id = ?");
        $uStmt->execute([$targetUserId]);
        $targetUser = $uStmt->fetch();
        $targetUserName = $targetUser['name'] ?? $user['name'];

        log_prospect_activity($id, $user['id'], 'reassigned', 'Klaim Penanggung Jawab', "Prospek diambil alih oleh {$targetUserName}.");

        $fetchStmt = $db->prepare("
            SELECT p.*, u.name as cs_name, pkg.name as package_name, pkg.price as package_price, pkg.dp as package_dp,
                   pkg.price_quad, pkg.price_triple, pkg.price_double, pkg.price_infant,
                   pkg.quota_remaining, pkg.departure_date, pkg.airline as package_airline,
                   pkg.hotel_makkah as package_hotel_makkah, pkg.hotel_madinah as package_hotel_madinah,
                   pkg.flyer_image
            FROM prospects p
            LEFT JOIN packages pkg ON p.package_id = pkg.id
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.id = ?
        ");
        $fetchStmt->execute([$id]);
        $fullProspect = $fetchStmt->fetch();

        echo json_encode([
            'success' => true,
            'user_id' => $targetUserId,
            'cs_name' => $targetUserName,
            'prospect' => $fullProspect,
            'message' => "Prospek berhasil ditugaskan ke {$targetUserName}."
        ]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'ID prospek tidak valid.']);
    exit;
}

// -------------------------------------------------------------
// 4. QUICK UPDATE STAGE / PIPELINE STATUS (WITH LOST REASON SUPPORT)
// -------------------------------------------------------------
if (($action === 'update_status' || $action === 'update_stage') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $validStatuses = ['new', 'identifying', 'offered', 'closing', 'objection', 'followup', 'nurture', 'closed_won', 'closed_lost'];

    if (!in_array($status, $validStatuses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Status tahapan pipeline tidak valid.']);
        exit;
    }

    $oldStmt = $db->prepare("SELECT status, brand_id FROM prospects WHERE id = ?");
    $oldStmt->execute([$id]);
    $oldRow = $oldStmt->fetch();
    if (!$oldRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Prospek tidak ditemukan.']);
        exit;
    }

    $oldStatus = $oldRow['status'] ?? '';
    $prospectBrandId = (int)($oldRow['brand_id'] ?? ($user['brand_id'] ?: 1));

    $lostReason = isset($_POST['lost_reason']) ? trim($_POST['lost_reason']) : null;
    $lostDetail = isset($_POST['lost_reason_detail']) ? trim($_POST['lost_reason_detail']) : null;

    if ($status === 'closed_lost') {
        $stmt = $db->prepare("UPDATE prospects SET status = ?, lost_reason = COALESCE(?, lost_reason), lost_reason_detail = COALESCE(?, lost_reason_detail) WHERE id = ?");
        $stmt->execute([$status, $lostReason, $lostDetail, $id]);
    } else {
        $stmt = $db->prepare("UPDATE prospects SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
    }

    $logMsg = "Tahapan konversi diubah dari '{$oldStatus}' menjadi '{$status}'.";
    if ($status === 'closed_lost' && $lostReason) {
        $logMsg .= " Alasan pembatalan: {$lostReason}.";
    }
    log_prospect_activity($id, $user['id'], 'status_changed', 'Status Tahapan Diubah', $logMsg);

    $capiResult = null;
    if ($status === 'closed_won' && $oldStatus !== 'closed_won') {
        // Also set payment status to paid / partial
        $db->prepare("UPDATE prospects SET payment_status = IF(payment_status = 'unpaid', 'partial_dp', payment_status), dp_paid_at = COALESCE(dp_paid_at, NOW()) WHERE id = ?")->execute([$id]);

        $capiResult = send_meta_purchase_event($id, $prospectBrandId);
        if ($capiResult['success']) {
            log_prospect_activity($id, $user['id'], 'meta_capi', 'Meta CAPI Purchase Terkirim', 'Event Purchase DP terkirim ke Meta Conversions API. ID: ' . $capiResult['event_id']);
        }
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Status tahapan prospek berhasil diperbarui.',
        'status' => $status,
        'meta_capi' => $capiResult
    ]);
    exit;
}

// -------------------------------------------------------------
// 4. QUICK UPDATE PACKAGE & RECALCULATE DEAL VALUE
// -------------------------------------------------------------
if ($action === 'update_package' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $package_id = !empty($_POST['package_id']) ? (int)$_POST['package_id'] : null;

    $pStmt = $db->prepare("SELECT pax_quad, pax_triple, pax_double, pax_infant FROM prospects WHERE id = ?");
    $pStmt->execute([$id]);
    $pRow = $pStmt->fetch();
    if (!$pRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Prospek tidak ditemukan.']);
        exit;
    }

    $newDealValue = calc_prospect_deal_value($db, $package_id, (int)$pRow['pax_quad'], (int)$pRow['pax_triple'], (int)$pRow['pax_double'], (int)$pRow['pax_infant']);

    $stmt = $db->prepare("UPDATE prospects SET package_id = ?, deal_value = ? WHERE id = ?");
    $stmt->execute([$package_id, $newDealValue, $id]);

    $pkgName = 'Belum Memilih Paket';
    if ($package_id) {
        $pnStmt = $db->prepare("SELECT name FROM packages WHERE id = ?");
        $pnStmt->execute([$package_id]);
        $pkgName = $pnStmt->fetchColumn() ?: 'Paket Pilihan';
    }

    log_prospect_activity($id, $user['id'], 'package_assigned', 'Paket Umroh Diperbarui', "Paket diminati diubah menjadi: {$pkgName}.");

    echo json_encode([
        'success' => true,
        'message' => 'Paket umroh berhasil diperbarui.',
        'package_id' => $package_id,
        'deal_value' => $newDealValue
    ]);
    exit;
}

// -------------------------------------------------------------
// 4B. QUICK UPDATE ROOM PAX & RECALCULATE DEAL VALUE
// -------------------------------------------------------------
if ($action === 'update_pax' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $pax_quad = max(0, (int)($_POST['pax_quad'] ?? 0));
    $pax_triple = max(0, (int)($_POST['pax_triple'] ?? 0));
    $pax_double = max(0, (int)($_POST['pax_double'] ?? 0));
    $pax_infant = max(0, (int)($_POST['pax_infant'] ?? 0));

    $pStmt = $db->prepare("SELECT package_id FROM prospects WHERE id = ?");
    $pStmt->execute([$id]);
    $pRow = $pStmt->fetch();
    if (!$pRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Prospek tidak ditemukan.']);
        exit;
    }

    $package_id = $pRow['package_id'] ? (int)$pRow['package_id'] : null;
    $deal_value = calc_prospect_deal_value($db, $package_id, $pax_quad, $pax_triple, $pax_double, $pax_infant);

    $stmt = $db->prepare("UPDATE prospects SET pax_quad = ?, pax_triple = ?, pax_double = ?, pax_infant = ?, deal_value = ? WHERE id = ?");
    $stmt->execute([$pax_quad, $pax_triple, $pax_double, $pax_infant, $deal_value, $id]);

    log_prospect_activity($id, $user['id'], 'updated', 'Rincian Kamar & Pax Diperbarui', "Kamar diubah: Quad ({$pax_quad}), Triple ({$pax_triple}), Double ({$pax_double}), Infant ({$pax_infant}). Deal Value: Rp " . number_format($deal_value, 0, ',', '.'));

    echo json_encode([
        'success' => true,
        'message' => 'Rincian kamar dan nilai deal berhasil diperbarui.',
        'deal_value' => $deal_value,
        'pax_quad' => $pax_quad,
        'pax_triple' => $pax_triple,
        'pax_double' => $pax_double,
        'pax_infant' => $pax_infant
    ]);
    exit;
}

// -------------------------------------------------------------
// 4C. QUICK UPDATE PAYMENT DP & STATUS
// -------------------------------------------------------------
if ($action === 'update_payment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $dp_amount = (float)clean_num($_POST['dp_amount'] ?? 0);
    $payment_status = trim($_POST['payment_status'] ?? ($dp_amount > 0 ? 'partial_dp' : 'unpaid'));
    $dp_paid_at = !empty($_POST['dp_paid_at']) ? $_POST['dp_paid_at'] : ($dp_amount > 0 ? date('Y-m-d H:i:s') : null);

    $stmt = $db->prepare("UPDATE prospects SET dp_amount = ?, payment_status = ?, dp_paid_at = ? WHERE id = ?");
    $stmt->execute([$dp_amount, $payment_status, $dp_paid_at, $id]);

    log_prospect_activity($id, $user['id'], 'updated', 'Status Pembayaran Diperbarui', "Nominal DP: Rp " . number_format($dp_amount, 0, ',', '.') . " (Status: {$payment_status})");

    echo json_encode([
        'success' => true,
        'message' => 'Data pembayaran DP berhasil diperbarui.',
        'dp_amount' => $dp_amount,
        'payment_status' => $payment_status,
        'dp_paid_at' => $dp_paid_at
    ]);
    exit;
}

// -------------------------------------------------------------
// 5. LOG NEW FOLLOW-UP ACTIVITY & NOTES
// -------------------------------------------------------------
if ($action === 'log_followup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $note = trim($_POST['note'] ?? '');
    $next_date = !empty($_POST['next_followup_date']) ? $_POST['next_followup_date'] : null;
    $status = !empty($_POST['status']) ? $_POST['status'] : null;

    if (empty($note)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Catatan follow-up tidak boleh kosong.']);
        exit;
    }

    $sql = "UPDATE prospects SET last_followup_at = NOW()";
    $params = [];
    if ($next_date) {
        $sql .= ", next_followup_date = ?";
        $params[] = $next_date;
    }
    if ($status) {
        $sql .= ", status = ?";
        $params[] = $status;
    }
    $sql .= " WHERE id = ?";
    $params[] = $id;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    log_prospect_activity($id, $user['id'], 'followup_logged', 'Catatan Interaksi & Follow-up', $note);

    if ($status === 'closed_won') {
        $pStmt = $db->prepare("SELECT brand_id FROM prospects WHERE id = ?");
        $pStmt->execute([$id]);
        $bId = (int)$pStmt->fetchColumn() ?: ($user['brand_id'] ?: 1);
        $capiRes = send_meta_purchase_event($id, $bId);
        if ($capiRes['success']) {
            log_prospect_activity($id, $user['id'], 'meta_capi', 'Meta CAPI Purchase Terkirim', 'Event Purchase DP terkirim ke Meta Conversions API. ID: ' . $capiRes['event_id']);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Catatan aktivitas berhasil disimpan ke riwayat!']);
    exit;
}

// -------------------------------------------------------------
// 6. DELETE PROSPECT
// -------------------------------------------------------------
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $db->prepare("DELETE FROM prospects WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Prospek berhasil dihapus dari pipeline.']);
    exit;
}

// -------------------------------------------------------------
// 7. GET LOGS FOR A PROSPECT
// -------------------------------------------------------------
if ($action === 'get_logs') {
    $id = (int)($_REQUEST['id'] ?? 0);
    $stmt = $db->prepare("
        SELECT pl.*, u.name as user_name
        FROM prospect_logs pl
        LEFT JOIN users u ON pl.user_id = u.id
        WHERE pl.prospect_id = ?
        ORDER BY pl.created_at DESC, pl.id DESC
    ");
    $stmt->execute([$id]);
    $logs = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $logs]);
    exit;
}

http_response_code(404);
echo json_encode(['success' => false, 'error' => 'Action not found']);
