<?php
/**
 * Prospects API Endpoint (CRUD & Quick Status Update)
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/meta_capi.php';

header('Content-Type: application/json');

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

// 1. GET LIST OF PROSPECTS
if ($action === 'list') {
    $brandId = ($user['role'] === 'superadmin' && !empty($_GET['brand_id'])) 
                ? (int)$_GET['brand_id'] 
                : $user['brand_id'];

    $sql = "
        SELECT p.*, b.name AS brand_name, pkg.name AS package_name, pkg.price AS package_price, u.name AS cs_name
        FROM prospects p
        JOIN brands b ON p.brand_id = b.id
        LEFT JOIN packages pkg ON p.package_id = pkg.id
        LEFT JOIN users u ON p.user_id = u.id
    ";

    $params = [];
    if ($user['role'] !== 'superadmin') {
        $sql .= " WHERE p.brand_id = ? AND p.user_id = ?";
        $params = [$brandId, $user['id']];
    } elseif ($brandId) {
        $sql .= " WHERE p.brand_id = ?";
        $params = [$brandId];
    }

    $sql .= " ORDER BY p.updated_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $prospects = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $prospects]);
    exit;
}

// 2. SAVE OR UPDATE PROSPECT
if (($action === 'save' || $action === 'create' || $action === 'update') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $package_id = !empty($_POST['package_id']) ? (int)$_POST['package_id'] : null;
    $current_stage = $_POST['current_stage'] ?? 'greeting';
    $status = $_POST['status'] ?? 'new';
    $notes = trim($_POST['notes'] ?? '');
    $next_followup_date = !empty($_POST['next_followup_date']) ? $_POST['next_followup_date'] : null;

    $pax_quad = isset($_POST['pax_quad']) ? max(0, (int)$_POST['pax_quad']) : 0;
    $pax_triple = isset($_POST['pax_triple']) ? max(0, (int)$_POST['pax_triple']) : 0;
    $pax_double = isset($_POST['pax_double']) ? max(0, (int)$_POST['pax_double']) : 0;
    $pax_infant = isset($_POST['pax_infant']) ? max(0, (int)$_POST['pax_infant']) : 0;

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
        // Fetch old status for audit log
        $oldStmt = $db->prepare("SELECT status, current_stage, package_id FROM prospects WHERE id = ?");
        $oldStmt->execute([$id]);
        $old = $oldStmt->fetch();

        // Update
        $stmt = $db->prepare("
            UPDATE prospects 
            SET package_id = ?, name = ?, phone = ?, current_stage = ?, status = ?, notes = ?, next_followup_date = ?,
                pax_quad = ?, pax_triple = ?, pax_double = ?, pax_infant = ?,
                remote_jid = IF(remote_jid IS NULL OR remote_jid = '', ?, remote_jid)
            WHERE id = ?
        ");
        $stmt->execute([$package_id, $name, $phone, $current_stage, $status, $notes, $next_followup_date, $pax_quad, $pax_triple, $pax_double, $pax_infant, $remote_jid ?: null, $id]);
        $msg = 'Data prospek berhasil diperbarui.';

        if (!empty($remote_jid)) {
            $db->prepare("UPDATE chat_messages SET prospect_id = ? WHERE brand_id = ? AND remote_jid = ?")->execute([$id, $brand_id, $remote_jid]);
        }

        // Log changes
        if ($old && $old['status'] !== $status) {
            log_prospect_activity($id, $user['id'], 'status_changed', 'Status Diperbarui', "Status diubah dari {$old['status']} ke {$status}.");
            if ($status === 'closed_won') {
                $capiRes = send_meta_purchase_event($id, $brand_id);
                if ($capiRes['success']) {
                    log_prospect_activity($id, $user['id'], 'meta_capi', 'Meta CAPI Purchase Terkirim', 'Event Purchase DP terkirim ke Meta Conversions API. ID: ' . $capiRes['event_id']);
                }
            }
        } else {
            log_prospect_activity($id, $user['id'], 'updated', 'Data Prospek Diperbarui', "Profil dan catatan prospek diperbarui.");
        }
    } else {
        // Insert
        $stmt = $db->prepare("
            INSERT INTO prospects (brand_id, user_id, package_id, name, phone, remote_jid, current_stage, status, notes, next_followup_date, pax_quad, pax_triple, pax_double, pax_infant)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$brand_id, $user['id'], $package_id, $name, $phone, $remote_jid ?: null, $current_stage, $status, $notes, $next_followup_date, $pax_quad, $pax_triple, $pax_double, $pax_infant]);
        $id = $db->lastInsertId();
        $msg = 'Prospek baru berhasil disimpan ke pipeline.';

        if (!empty($remote_jid)) {
            $db->prepare("UPDATE chat_messages SET prospect_id = ? WHERE brand_id = ? AND remote_jid = ?")->execute([$id, $brand_id, $remote_jid]);
        }

        log_prospect_activity($id, $user['id'], 'created', 'Prospek Dibuat', 'Calon jamaah didaftarkan ke pipeline konversi.');
    }

    $fetchStmt = $db->prepare("
        SELECT p.*, pkg.name as package_name, pkg.price as package_price, pkg.dp as package_dp,
               pkg.airline as package_airline, pkg.hotel_makkah as package_hotel_makkah,
               pkg.hotel_madinah as package_hotel_madinah, pkg.departure_info as package_departure_info,
               pkg.duration as package_duration, pkg.highlights as package_highlights
        FROM prospects p
        LEFT JOIN packages pkg ON p.package_id = pkg.id
        WHERE p.id = ?
    ");
    $fetchStmt->execute([$id]);
    $savedProspect = $fetchStmt->fetch();

    echo json_encode(['success' => true, 'message' => $msg, 'id' => $id, 'prospect' => $savedProspect]);
    exit;
}

// 3. QUICK UPDATE STATUS
if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $validStatuses = ['new', 'identifying', 'offered', 'closing', 'objection', 'followup', 'nurture', 'closed_won', 'closed_lost'];

    if (!in_array($status, $validStatuses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Status tidak valid.']);
        exit;
    }

    // Fetch old status and brand
    $oldStmt = $db->prepare("SELECT status, brand_id FROM prospects WHERE id = ?");
    $oldStmt->execute([$id]);
    $oldRow = $oldStmt->fetch();
    $oldStatus = $oldRow['status'] ?? '';
    $prospectBrandId = (int)($oldRow['brand_id'] ?? ($user['brand_id'] ?: 1));

    $stmt = $db->prepare("UPDATE prospects SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    log_prospect_activity($id, $user['id'], 'status_changed', 'Status Diubah', "Status diubah dari '{$oldStatus}' ke '{$status}'.");

    $capiResult = null;
    if ($status === 'closed_won' && $oldStatus !== 'closed_won') {
        $capiResult = send_meta_purchase_event($id, $prospectBrandId);
        if ($capiResult['success']) {
            log_prospect_activity($id, $user['id'], 'meta_capi', 'Meta CAPI Purchase Terkirim', 'Event Purchase DP terkirim ke Meta Conversions API. ID: ' . $capiResult['event_id']);
        }
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Status prospek berhasil diubah.',
        'meta_capi' => $capiResult
    ]);
    exit;
}

// 4. LOG NEW FOLLOW-UP ACTIVITY
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

    log_prospect_activity($id, $user['id'], 'followup_logged', 'Catatan Follow-up', $note);

    if ($status === 'closed_won') {
        $pStmt = $db->prepare("SELECT brand_id FROM prospects WHERE id = ?");
        $pStmt->execute([$id]);
        $bId = (int)$pStmt->fetchColumn() ?: ($user['brand_id'] ?: 1);
        $capiRes = send_meta_purchase_event($id, $bId);
        if ($capiRes['success']) {
            log_prospect_activity($id, $user['id'], 'meta_capi', 'Meta CAPI Purchase Terkirim', 'Event Purchase DP terkirim ke Meta Conversions API. ID: ' . $capiRes['event_id']);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Follow-up berhasil dicatat ke riwayat!']);
    exit;
}

// 5. DELETE PROSPECT
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $db->prepare("DELETE FROM prospects WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Prospek berhasil dihapus.']);
    exit;
}

// 6. GET LOGS FOR A PROSPECT
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


