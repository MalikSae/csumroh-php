<?php
/**
 * Meta Conversions API (CAPI) Integration for Umroh Closing / DP
 * Compliant with "Dari Chat ke Purchase" CTWA Tracking Guide
 */

require_once __DIR__ . '/../config/db.php';

/**
 * Format phone number to E.164 (without plus sign) for Meta hashing
 * e.g. 08123456789 -> 628123456789
 */
function normalize_phone_for_meta(string $phone): string {
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($clean, '0')) {
        $clean = '62' . substr($clean, 1);
    } elseif (str_starts_with($clean, '8')) {
        $clean = '62' . $clean;
    }
    return $clean;
}

/**
 * Hash data with SHA-256 for Meta user_data privacy compliance
 */
function hash_for_meta(string $val): string {
    return hash('sha256', trim(strtolower($val)));
}

/**
 * Extract numeric value from DP/price string (e.g. "Rp 5.000.000" -> 5000000)
 */
function extract_numeric_value(?string $str): float {
    if (!$str) return 5000000.00;
    $digits = preg_replace('/[^0-9]/', '', $str);
    return !empty($digits) ? (float)$digits : 5000000.00;
}

/**
 * Send Meta CAPI Purchase Event for a closed_won prospect
 *
 * @param int $prospectId
 * @param int $brandId
 * @return array ['success' => bool, 'message' => string, 'event_id' => string]
 */
function send_meta_purchase_event(int $prospectId, int $brandId): array {
    $db = get_db();

    // 1. Fetch Brand Meta Settings
    $stmt = $db->prepare("SELECT id, name, meta_pixel_id, meta_access_token, facebook_page_id FROM brands WHERE id = ?");
    $stmt->execute([$brandId]);
    $brand = $stmt->fetch();

    if (!$brand || empty($brand['meta_pixel_id']) || empty($brand['meta_access_token'])) {
        return [
            'success' => false,
            'message' => 'Meta Pixel ID atau Access Token belum dikonfigurasi untuk brand ini. Silakan atur di Master Brand.'
        ];
    }

    // 2. Fetch Prospect & Package Data
    $stmt = $db->prepare("
        SELECT p.*, pkg.name as package_name, pkg.dp as package_dp, pkg.price as package_price
        FROM prospects p
        LEFT JOIN packages pkg ON p.package_id = pkg.id
        WHERE p.id = ?
    ");
    $stmt->execute([$prospectId]);
    $prospect = $stmt->fetch();

    if (!$prospect) {
        return ['success' => false, 'message' => 'Prospek tidak ditemukan.'];
    }

    $now = time();
    $eventId = 'csumroh_' . $prospectId . '_' . $now;
    $pixelId = trim($brand['meta_pixel_id']);
    $accessToken = trim($brand['meta_access_token']);

    // 3. Prepare User Data
    $userData = [];
    if (!empty($prospect['phone'])) {
        $e164 = normalize_phone_for_meta($prospect['phone']);
        $userData['ph'] = [hash_for_meta($e164)];
    }

    if (!empty($brand['facebook_page_id'])) {
        $userData['page_id'] = trim($brand['facebook_page_id']);
    }

    // Pass referral marker unhashed if exists from CTWA ad
    if (!empty($prospect['meta_referral_marker'])) {
        $userData['ctwa_clid'] = $prospect['meta_referral_marker'];
    }

    // 4. Prepare Custom Data (Purchase Value & Package info)
    $dpValue = extract_numeric_value($prospect['package_dp'] ?? '5000000');
    $packageName = $prospect['package_name'] ?? 'Paket Umroh';

    $customData = [
        'currency' => 'IDR',
        'value' => $dpValue,
        'content_name' => 'DP Umroh - ' . $packageName,
        'content_type' => 'product',
        'status' => 'closed_won'
    ];

    // 5. Build Event Payload
    $eventData = [
        'event_name' => 'Purchase',
        'event_time' => $now,
        'event_id' => $eventId,
        'event_source_url' => 'http://localhost/csumroh',
        'action_source' => 'other',
        'user_data' => $userData,
        'custom_data' => $customData
    ];

    $payload = [
        'data' => [$eventData]
    ];

    $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

    // 6. Send HTTP POST to Meta Graph API
    $url = "https://graph.facebook.com/v19.0/{$pixelId}/events?access_token=" . urlencode($accessToken);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonPayload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false // Local dev friendly
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    $isSuccess = ($httpCode >= 200 && $httpCode < 300);
    $status = $isSuccess ? 'success' : 'failed';

    // 7. Log to meta_capi_logs
    try {
        $logStmt = $db->prepare("
            INSERT INTO meta_capi_logs (brand_id, prospect_id, event_name, event_id, payload, response_status, response_body, status)
            VALUES (?, ?, 'Purchase', ?, ?, ?, ?, ?)
        ");
        $logStmt->execute([
            $brandId,
            $prospectId,
            $eventId,
            $jsonPayload,
            $httpCode,
            $response ?: $curlErr,
            $status
        ]);
    } catch (Exception $e) {
        // Continue even if logging fails
    }

    return [
        'success' => $isSuccess,
        'http_code' => $httpCode,
        'event_id' => $eventId,
        'response' => $response ?: $curlErr,
        'message' => $isSuccess 
            ? 'Event Purchase berhasil terkirim ke Meta Conversions API (CAPI)!' 
            : 'Gagal mengirim ke Meta CAPI (' . $httpCode . '): ' . ($response ?: $curlErr)
    ];
}

