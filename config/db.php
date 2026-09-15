<?php
/**
 * Database Configuration & Auto-Migration / Seeder
 * Laragon MySQL Environment
 */

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'csumroh');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // If database doesn't exist, run migration
            init_db();
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        }
    }
    return $pdo;
}

function init_db(): void {
    $rootDsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
    $rootPdo = new PDO($rootDsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // 1. Create Database if not exists
    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $rootPdo->exec("USE `" . DB_NAME . "`");

    // 2. Create Tables
    $rootPdo->exec("
        CREATE TABLE IF NOT EXISTS brands (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            code VARCHAR(30) NOT NULL UNIQUE,
            ppiu_number VARCHAR(100) NULL,
            bank_name VARCHAR(50) NULL,
            bank_account_number VARCHAR(50) NULL,
            bank_account_holder VARCHAR(100) NULL,
            address TEXT NULL,
            phone VARCHAR(30) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            brand_id INT NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('superadmin', 'cs') NOT NULL DEFAULT 'cs',
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL
        ) ENGINE=InnoDB;

        CREATE TABLE IF NOT EXISTS packages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            brand_id INT NOT NULL,
            name VARCHAR(150) NOT NULL,
            price VARCHAR(50) NOT NULL,
            dp VARCHAR(50) NOT NULL,
            airline VARCHAR(100) NULL,
            hotel_makkah VARCHAR(100) NULL,
            hotel_madinah VARCHAR(100) NULL,
            departure_info VARCHAR(100) NULL,
            duration VARCHAR(50) NULL,
            highlights TEXT NULL,
            flyer_image VARCHAR(255) NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;

        CREATE TABLE IF NOT EXISTS prospects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            brand_id INT NOT NULL,
            user_id INT NOT NULL,
            package_id INT NULL,
            name VARCHAR(100) NOT NULL,
            phone VARCHAR(30) NULL,
            current_stage VARCHAR(50) DEFAULT 'greeting',
            status ENUM('new', 'identifying', 'offered', 'closing', 'objection', 'followup', 'nurture', 'closed_won', 'closed_lost') DEFAULT 'new',
            notes TEXT NULL,
            next_followup_date DATE NULL,
            last_followup_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL
        ) ENGINE=InnoDB;

        CREATE TABLE IF NOT EXISTS prospect_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            prospect_id INT NOT NULL,
            user_id INT NULL,
            action_type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (prospect_id) REFERENCES prospects(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB;

        CREATE TABLE IF NOT EXISTS whatsapp_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            brand_id INT NOT NULL UNIQUE,
            phone_number VARCHAR(30) NULL,
            session_name VARCHAR(100) NOT NULL,
            status ENUM('disconnected', 'connecting', 'connected', 'qr_ready') DEFAULT 'disconnected',
            qr_code TEXT NULL,
            last_connected_at DATETIME NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;

        CREATE TABLE IF NOT EXISTS chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            brand_id INT NOT NULL,
            prospect_id INT NULL,
            message_id VARCHAR(100) NOT NULL,
            remote_jid VARCHAR(100) NOT NULL,
            phone VARCHAR(30) NOT NULL,
            sender_name VARCHAR(100) NULL,
            is_from_me TINYINT(1) DEFAULT 0,
            message_type VARCHAR(30) DEFAULT 'conversation',
            message_text TEXT NULL,
            media_url TEXT NULL,
            status VARCHAR(30) DEFAULT 'delivered',
            timestamp INT UNSIGNED NOT NULL,
            meta_referral_data JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_brand_msg (brand_id, message_id),
            KEY idx_brand_jid (brand_id, remote_jid),
            KEY idx_prospect (prospect_id),
            FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
            FOREIGN KEY (prospect_id) REFERENCES prospects(id) ON DELETE SET NULL
        ) ENGINE=InnoDB;

        CREATE TABLE IF NOT EXISTS meta_capi_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            brand_id INT NOT NULL,
            prospect_id INT NOT NULL,
            event_name VARCHAR(50) NOT NULL,
            event_id VARCHAR(100) NOT NULL,
            payload TEXT NULL,
            response_status INT NULL,
            response_body TEXT NULL,
            status ENUM('success', 'failed') DEFAULT 'success',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
            FOREIGN KEY (prospect_id) REFERENCES prospects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");

    // Helper to safely add column if not exists
    $addColumn = function($table, $column, $def) use ($rootPdo) {
        $stmt = $rootPdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        if ((int)$stmt->fetchColumn() === 0) {
            $rootPdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$def}");
        }
    };

    $addColumn('brands', 'meta_pixel_id', 'VARCHAR(50) NULL');
    $addColumn('brands', 'meta_access_token', 'TEXT NULL');
    $addColumn('brands', 'facebook_page_id', 'VARCHAR(50) NULL');
    $addColumn('prospects', 'remote_jid', 'VARCHAR(100) NULL');
    $addColumn('prospects', 'meta_referral_marker', 'TEXT NULL');
    $addColumn('prospects', 'ad_id', 'VARCHAR(100) NULL');
    $addColumn('prospects', 'campaign_id', 'VARCHAR(100) NULL');
    $addColumn('prospects', 'photo_url', 'TEXT NULL');
    $addColumn('prospects', 'pax_quad', 'INT NOT NULL DEFAULT 0');
    $addColumn('prospects', 'pax_triple', 'INT NOT NULL DEFAULT 0');
    $addColumn('prospects', 'pax_double', 'INT NOT NULL DEFAULT 0');
    $addColumn('prospects', 'pax_infant', 'INT NOT NULL DEFAULT 0');
    $addColumn('chat_messages', 'is_deleted', 'TINYINT(1) DEFAULT 0');
    $addColumn('chat_messages', 'deleted_at', 'DATETIME NULL');
    $addColumn('chat_messages', 'reaction', 'VARCHAR(10) NULL');
    $addColumn('chat_messages', 'quoted_message_id', 'VARCHAR(100) NULL');
    $addColumn('chat_messages', 'quoted_text', 'TEXT NULL');
    $addColumn('chat_messages', 'quoted_sender', 'VARCHAR(100) NULL');

    // Packages Table Migrations (CRM Umroh Pro)
    $addColumn('packages', 'departure_date', 'DATE NULL');
    $addColumn('packages', 'flight_type', "ENUM('direct', 'transit') DEFAULT 'direct'");
    $addColumn('packages', 'hotels', 'JSON NULL');
    $addColumn('packages', 'quota_remaining', 'INT NULL DEFAULT 0');
    $addColumn('packages', 'price_quad', 'VARCHAR(50) NULL');
    $addColumn('packages', 'price_triple', 'VARCHAR(50) NULL');
    $addColumn('packages', 'price_double', 'VARCHAR(50) NULL');
    $addColumn('packages', 'price_infant', 'VARCHAR(50) NULL');
    $addColumn('packages', 'facilities_included', 'TEXT NULL');
    $addColumn('packages', 'facilities_excluded', 'TEXT NULL');
    $addColumn('packages', 'itinerary', 'LONGTEXT NULL');
    $addColumn('packages', 'is_promo', 'TINYINT(1) DEFAULT 0');
    $addColumn('packages', 'promo_discount', 'VARCHAR(50) NULL');
    $addColumn('packages', 'promo_deadline', 'DATE NULL');
    $addColumn('packages', 'flyer_image', 'VARCHAR(255) NULL');

    // Ensure whatsapp_sessions exist for all brands
    $brandsList = $rootPdo->query("SELECT id FROM brands")->fetchAll(PDO::FETCH_COLUMN);
    $wsStmt = $rootPdo->prepare("INSERT IGNORE INTO whatsapp_sessions (brand_id, session_name, status) VALUES (?, ?, 'disconnected')");
    foreach ($brandsList as $bId) {
        $wsStmt->execute([$bId, 'brand_' . $bId]);
    }

    // 3. Seed Initial 5 Brands if empty
    $stmt = $rootPdo->query("SELECT COUNT(*) FROM brands");
    if ((int)$stmt->fetchColumn() === 0) {
        $brands = [
            [
                'name' => 'Azhan Tour & Travel',
                'code' => 'azhan-tour',
                'ppiu_number' => '123/PPIU/KEMENAG/2023',
                'bank_name' => 'BSI (Bank Syariah Indonesia)',
                'bank_account_number' => '7188291021',
                'bank_account_holder' => 'PT Azhan Wisata Mandiri',
                'address' => 'Gedung Menara Madinah Lt. 3, Jakarta Selatan',
                'phone' => '081234567890'
            ],
            [
                'name' => 'Haramain Utama Wisata',
                'code' => 'haramain-utama',
                'ppiu_number' => '456/PPIU/KEMENAG/2022',
                'bank_name' => 'Bank Mandiri',
                'bank_account_number' => '1320098765432',
                'bank_account_holder' => 'PT Haramain Utama Wisata',
                'address' => 'Jl. Haji Nawi No. 45, Jakarta Selatan',
                'phone' => '081234567891'
            ],
            [
                'name' => 'Safwa Barakah Travel',
                'code' => 'safwa-barakah',
                'ppiu_number' => '789/PPIU/KEMENAG/2024',
                'bank_name' => 'BCA Syariah',
                'bank_account_number' => '0981234567',
                'bank_account_holder' => 'PT Safwa Barakah Travel',
                'address' => 'Ruko Grand Wisata Blok A-12, Bekasi',
                'phone' => '081234567892'
            ],
            [
                'name' => 'Al-Fajr Insani Tour',
                'code' => 'alfajr-insani',
                'ppiu_number' => '321/PPIU/KEMENAG/2021',
                'bank_name' => 'Bank Muamalat',
                'bank_account_number' => '3019876543',
                'bank_account_holder' => 'PT Al-Fajr Insani Tour',
                'address' => 'Jl. Pajajaran No. 88, Bandung',
                'phone' => '081234567893'
            ],
            [
                'name' => 'Madinah Makmur Mandiri',
                'code' => 'madinah-makmur',
                'ppiu_number' => '654/PPIU/KEMENAG/2023',
                'bank_name' => 'BSI (Bank Syariah Indonesia)',
                'bank_account_number' => '7299102938',
                'bank_account_holder' => 'PT Madinah Makmur Mandiri',
                'address' => 'Jl. Pemuda No. 10, Surabaya',
                'phone' => '081234567894'
            ],
        ];

        $ins = $rootPdo->prepare("
            INSERT INTO brands (name, code, ppiu_number, bank_name, bank_account_number, bank_account_holder, address, phone)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($brands as $b) {
            $ins->execute([
                $b['name'], $b['code'], $b['ppiu_number'],
                $b['bank_name'], $b['bank_account_number'], $b['bank_account_holder'],
                $b['address'], $b['phone']
            ]);
        }
    }

    // 4. Seed Default Users if empty
    $stmt = $rootPdo->query("SELECT COUNT(*) FROM users");
    if ((int)$stmt->fetchColumn() === 0) {
        $ins = $rootPdo->prepare("
            INSERT INTO users (brand_id, name, email, password, role, is_active)
            VALUES (?, ?, ?, ?, ?, 1)
        ");

        // Super Admin (Cross-brand access)
        $ins->execute([
            null,
            'Super Admin',
            'admin@csumroh.com',
            password_hash('admin123', PASSWORD_DEFAULT),
            'superadmin'
        ]);

        // CS Fitri (Assigned to Brand 1: Azhan Tour & Travel)
        $ins->execute([
            1,
            'Fitri',
            'fitri@csumroh.com',
            password_hash('fitri123', PASSWORD_DEFAULT),
            'cs'
        ]);

        // CS Rina (Assigned to Brand 2: Haramain Utama Wisata)
        $ins->execute([
            2,
            'CS Rina',
            'rina@csumroh.com',
            password_hash('rina123', PASSWORD_DEFAULT),
            'cs'
        ]);
    }

    // 5. Seed Initial Packages if empty
    $stmt = $rootPdo->query("SELECT COUNT(*) FROM packages");
    if ((int)$stmt->fetchColumn() === 0) {
        $packages = [
            // Brand 1 Packages
            [
                'brand_id' => 1,
                'name' => 'Umroh Reguler Hemat 9 Hari',
                'price' => 'Rp 27.500.000',
                'dp' => 'Rp 5.000.000',
                'airline' => 'Batik Air / Lion Direct',
                'hotel_makkah' => 'Retaj Al Rayyan (Bintang 3 - 350m)',
                'hotel_madinah' => 'Concorde Dar Al Khair (Bintang 3 - 200m)',
                'departure_info' => 'Oktober & November 2026',
                'duration' => '9 Hari',
                'highlights' => 'Free Perlengkapan Lengkap, Kereta Cepat Haramain, Fullboard Buffet 3x'
            ],
            [
                'brand_id' => 1,
                'name' => 'Umroh VIP Bintang 5 Plus 12 Hari',
                'price' => 'Rp 36.900.000',
                'dp' => 'Rp 5.000.000',
                'airline' => 'Saudia Airlines Direct JKT-JED',
                'hotel_makkah' => 'Pullman Zamzam Tower (Depan Masjidil Haram)',
                'hotel_madinah' => 'Rove Al Madinah (Pelataran Masjid Nabawi)',
                'departure_info' => 'Desember 2026 (Liburan Akhir Tahun)',
                'duration' => '12 Hari',
                'highlights' => 'Hotel Depan Pelataran, Saudia Direct, Handling Bandara VIP, Ziarah Thaif Free Nasi Mandi'
            ],
            // Brand 2 Packages
            [
                'brand_id' => 2,
                'name' => 'Paket Berkah Syawal 9 Hari',
                'price' => 'Rp 29.800.000',
                'dp' => 'Rp 5.000.000',
                'airline' => 'Garuda Indonesia Direct',
                'hotel_makkah' => 'Le Meridien Towers (Shuttle 24 Jam)',
                'hotel_madinah' => 'Dinar Al Madinah (Bintang 4)',
                'departure_info' => 'Januari 2027',
                'duration' => '9 Hari',
                'highlights' => 'Maskapai Bintang 5 Garuda Indonesia, Muthawwif Berpengalaman Lulusan Al-Azhar / Madinah'
            ],
            // Brand 3 Packages
            [
                'brand_id' => 3,
                'name' => 'Paket Safwa Barakah Platinum 10 Hari',
                'price' => 'Rp 33.500.000',
                'dp' => 'Rp 5.000.000',
                'airline' => 'Oman Air / Qatar Airways',
                'hotel_makkah' => 'Swissotel Makkah',
                'hotel_madinah' => 'Al Rawda Royal Inn',
                'departure_info' => 'Februari 2027',
                'duration' => '10 Hari',
                'highlights' => 'City Tour Kota Jeddah & Thaif, Audio Receiver Guide, Free Asuransi Syariah Perjalanan'
            ],
        ];

        $ins = $rootPdo->prepare("
            INSERT INTO packages (brand_id, name, price, dp, airline, hotel_makkah, hotel_madinah, departure_info, duration, highlights)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($packages as $p) {
            $ins->execute([
                $p['brand_id'], $p['name'], $p['price'], $p['dp'],
                $p['airline'], $p['hotel_makkah'], $p['hotel_madinah'],
                $p['departure_info'], $p['duration'], $p['highlights']
            ]);
        }
    }
}

/**
 * Log an activity or change on a prospect record.
 */
function log_prospect_activity(int $prospectId, ?int $userId, string $actionType, string $title, ?string $description = null): void {
    try {
        $db = get_db();
        $stmt = $db->prepare("
            INSERT INTO prospect_logs (prospect_id, user_id, action_type, title, description)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$prospectId, $userId, $actionType, $title, $description]);
    } catch (Exception $e) {
        // fail silently to avoid breaking the calling action
    }
}

