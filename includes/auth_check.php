<?php
/**
 * Authentication Guard & Session Helper
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!ob_get_level()) {
    ob_start();
}

require_once __DIR__ . '/../config/db.php';

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function get_logged_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }

    static $currentUser = null;
    if ($currentUser === null) {
        $db = get_db();
        $stmt = $db->prepare("
            SELECT u.*, b.name AS brand_name, b.code AS brand_code, b.ppiu_number,
                   b.bank_name, b.bank_account_number, b.bank_account_holder, b.phone AS brand_phone
            FROM users u
            LEFT JOIN brands b ON u.brand_id = b.id
            WHERE u.id = ? AND u.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $currentUser = $stmt->fetch() ?: null;
    }

    return $currentUser;
}

function get_base_url(): string {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return str_contains($uri, '/csumroh') ? '/csumroh' : '';
}

function require_login(?string $base_url = null): array {
    $base_url = $base_url ?? get_base_url();
    if (!is_logged_in()) {
        header("Location: {$base_url}/auth/login.php");
        exit;
    }

    $user = get_logged_user();
    if (!$user) {
        // Invalid or deactivated session
        session_destroy();
        header("Location: {$base_url}/auth/login.php");
        exit;
    }

    return $user;
}

function require_role(string $role, ?string $base_url = null): array {
    $base_url = $base_url ?? get_base_url();
    $user = require_login($base_url);
    if ($user['role'] !== $role && $user['role'] !== 'superadmin') {
        http_response_code(403);
        echo "<div style='font-family: sans-serif; padding: 40px; text-align: center;'>";
        echo "<h2>403 - Akses Ditolak</h2>";
        echo "<p>Halaman ini hanya dapat diakses oleh peran: <strong>" . htmlspecialchars($role) . "</strong>.</p>";
        echo "<p><a href='{$base_url}/index.php'>Kembali ke Halaman Utama</a></p>";
        echo "</div>";
        exit;
    }
    return $user;
}

