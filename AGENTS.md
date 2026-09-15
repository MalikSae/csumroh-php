# AGENTS.md
# Guidelines & System Context for AI Coding Agents

Proyek: **CS Umroh Copilot, CRM Pipeline & WhatsApp Shared Inbox (Multi-Brand)**  
Path: `C:\laragon\www\csumroh`

---

## 1. Project Purpose & Scope

Aplikasi ini adalah sistem operasi internal untuk Customer Service (CS) Umroh perusahaan (seperti CS Fitri dan CS Malik di Hana Tours) yang mengelola **Multi-Brand Travel Umroh** secara aman, efisien, dan terstandarisasi.

Tujuan utamanya:
1. **WhatsApp Web Live Chat & Shared Inbox (`chat.php`)**: Obrolan langsung multi-sesi terisolasi per brand berbasis Baileys, multi-identifier auto-select (`@lid`, `@s.whatsapp.net`, `phone`, `prospect_id`), panel CRM inline, dan drawer skrip Copilot.
2. **Multi-CS Shared Inbox & Collaboration**: Keterbukaan data prospek satu brand (*brand-scoped*), kepemilikan CS PIC (*claim/ambil alih*), auto-takeover saat CS membalas chat, dan distribusi seimbang lead baru (*balanced distribution*).
3. **Enterprise CRM Pipeline 360° (`prospects.php` & `prospect_detail.php`)**: Papan Kanban 8 tahap konversi, tabel data komprehensif dengan ekspor CSV, kualifikasi NPGD, kalkulator kamar pax (*Quad, Triple, Double, Infant*), pelacakan paspor/vaksin, dan riwayat aktivitas prospek.
4. **Copilot Chat & 19 Variabel Dinamis (`index.php`)**: Skrip percakapan terkurasi standar CRO (*Single-Question Rule*), interpolasi variabel otomatis, dan 1-klik salin/kirim ke WhatsApp.
5. **Interactive TGJP Objection Wizard**: Navigasi respons keberatan bertahap (*Terima $\rightarrow$ Gali $\rightarrow$ Jawab $\rightarrow$ Pastikan*).
6. **Meta Conversions API (CAPI) Integration**: Pelacakan event konversi server-side (*Contact, AddToCart, InitiateCheckout, Purchase*) dari iklan Meta Click-to-WhatsApp.
7. **LMS Belajar Mandiri (`lms/index.php`)**: Modul e-learning mandiri bagi CS berdasarkan `conversion-cycle.json`.
8. **Super Admin Multi-Brand (`admin/`)**: Manajemen master 5 Brand Travel, Paket Umroh multi-kamar, dan Akun CS.

---

## 2. Environment & Runtime Specifications

* **OS**: Windows (Laragon environment).
* **PHP Binary**: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`
* **Web Server Root**: `C:\laragon\www\csumroh` (diakses via `http://localhost/csumroh` atau virtual host Laragon `http://csumroh.test`).
* **Database**: MySQL Server (Laragon default port 3306, user `root`, password `""`, database `csumroh`).
* **WhatsApp Gateway**: Node.js microservice (`whatsapp-gateway/server.js`, port 3001) berbasis `@whiskeysockets/baileys`.
* **Dependency & Build Policy**:
  * **TIDAK MENGGUNAKAN BUILD TOOLS (No Webpack, No Vite, No NPM untuk aplikasi web utama)**.
  * Frontend menggunakan **Tailwind CSS CDN** dan **Alpine.js CDN** untuk interaktivitas reaktif.
  * Backend menggunakan **PHP 8.3 Native Modern** dengan PDO MySQL (ringan, cepat, mudah dirawat).

---

## 3. Strict Design Rules: Neutral Black & White (Monochrome)

Antarmuka **WAJIB** menerapkan gaya **netral hitam-putih (monochrome)** murni tanpa warna-warni mencolok (tidak menggunakan gradien warna-warni, tidak menggunakan warna primer biru/hijau/merah mencolok).

### Color Tokens:
* **Backgrounds**:
  * Body/Main Canvas: `bg-white` (`#ffffff`)
  * Panels/Cards: `bg-zinc-50` (`#fafafa`) atau `bg-zinc-100` (`#f4f4f5`)
  * Borders/Dividers: `border-zinc-200` (`#e4e4e7`) atau `border-zinc-300` (`#d4d4d8`)
* **Text**:
  * Heading & Primary Text: `text-black` (`#000000`) atau `text-zinc-900` (`#18181b`)
  * Body: `text-zinc-700` (`#3f3f46`)
  * Subtext / Muted: `text-zinc-500` (`#71717a`)
* **Buttons & Badges**:
  * Primary Button: `bg-black text-white hover:bg-zinc-800`
  * Secondary / Outline Button: `border border-zinc-300 text-zinc-800 hover:bg-zinc-100 bg-white`
  * Status Badges (Semantic Monochrome):
    * Closed Won: `bg-emerald-600 text-white`
    * Progress / Follow-up / Closing: `border border-zinc-800 text-zinc-900 bg-white` atau warna fungsional terkontrol
    * Pending / New: `bg-zinc-100 text-zinc-700`
    * Lost / Batal: `bg-zinc-200 text-zinc-600 line-through`
* **Dropdown Rule (CRITICAL)**:
  * **DILARANG MENGGUNAKAN ELEMEN NATIVE `<select>`**.
  * Seluruh dropdown formulir dan filter wajib menggunakan **Custom Dropdown Alpine.js** monokrom dengan `@click.outside="open = false"` dan transisi opacity.
* **Layout Rule**:
  * Layout harus nyaman digunakan secara **Split-Screen** (lebar 50% layar) di samping WhatsApp Web.

---

## 4. Multi-Brand & Multi-CS Shared Inbox Architecture

Perusahaan memiliki **5 Brand Travel**. Setiap entitas di dalam sistem memiliki relasi yang jelas:

```
[Master 5 Brands]
       │
       ├─── [Users / CS] (Beberapa CS dapat di-assign ke Brand yang sama)
       ├─── [Packages]   (Setiap Paket Umroh milik 1 Brand)
       ├─── [Prospects]  (Setiap Prospek milik 1 Brand, memiliki CS PIC penanggung jawab)
       └─── [WA Session] (Sesi Baileys terisolasi per brand)
```

### Aturan Scoping & Kolaborasi Brand:
1. Saat CS login, sistem mendeteksi `brand_id` dari session CS tersebut.
2. Seluruh prospek dan pesan WhatsApp satu brand dapat dilihat oleh seluruh CS brand tersebut (*Shared Inbox / Brand Transparency*).
3. **Klaim PIC (*Ownership*)**: Prospek memiliki atribut `user_id`. CS lain dapat mengambil alih prospek via tombol `[Klaim PIC]` atau `[Ambil Alih]`.
4. **Auto-Takeover Saat Membalas Pesan**: Saat CS membalas chat WhatsApp jamaah, sistem otomatis memperbarui `prospects.user_id = $user['id']`.
5. **Balanced Distribution Webhook**: Kontak baru dari webhook WhatsApp otomatis dialokasikan ke CS aktif dari brand terkait yang memiliki beban prospek aktif paling sedikit.
6. **Keamanan Finansial**: Variabel brand (`{{travel}}`, `{{ppiu}}`, `{{rekening}}`, `{{nama_rekening}}`) selalu terisi dari brand CS yang bertugas. **Meniadakan risiko salah kirim nomor rekening.**
7. Super Admin memiliki hak akses lintas brand (*cross-brand*).

---

## 5. Standarisasi Script: Transformasi POV Agent $\rightarrow$ CS Umroh

File JSON asli di folder `scripts-chat/` dan `conversion-chat/` ditulis dengan sudut pandang Agen/Mitra Lapangan. Agen AI **wajib melakukan transformasi narasi** saat menyajikan skrip:

1. **Variabel Nama**: Ubah `{{agent_name}}` menjadi **`{{cs_name}}`**.
2. **Representasi Diri**: Hapus frasa *"mitra agen konsultan"*, *"agen resmi"*, *"referral link agen"*. Ganti dengan: *"Customer Service resmi {{travel}}"*, *"tim layanan jamaah {{travel}}"*, atau *"konsultan umroh resmi {{travel}}"*.
3. **Inbound Context**: Sesuaikan konteks pesan pembuka untuk jamaah yang masuk dari website, iklan, atau media sosial resmi travel.

---

## 6. Database Schema Definition (MySQL)

```sql
CREATE DATABASE IF NOT EXISTS csumroh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE csumroh;

-- 1. Brands Table (Master 5 Brand Travel)
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
    meta_pixel_id VARCHAR(50) NULL,
    meta_access_token TEXT NULL,
    facebook_page_id VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Users Table (Super Admin & CS)
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

-- 3. Packages Table (Paket Umroh Multi-Kamar)
CREATE TABLE IF NOT EXISTS packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    price VARCHAR(50) NOT NULL,
    dp VARCHAR(50) NOT NULL,
    price_quad VARCHAR(50) NULL,
    price_triple VARCHAR(50) NULL,
    price_double VARCHAR(50) NULL,
    price_infant VARCHAR(50) NULL,
    quota_remaining INT NULL,
    departure_date DATE NULL,
    airline VARCHAR(100) NULL,
    hotel_makkah VARCHAR(100) NULL,
    hotel_madinah VARCHAR(100) NULL,
    departure_info VARCHAR(100) NULL,
    duration VARCHAR(50) NULL,
    flight_type VARCHAR(50) NULL,
    facilities_included TEXT NULL,
    facilities_excluded TEXT NULL,
    itinerary TEXT NULL,
    highlights TEXT NULL,
    flyer_image VARCHAR(255) NULL,
    is_promo TINYINT(1) DEFAULT 0,
    promo_discount VARCHAR(50) NULL,
    promo_deadline DATE NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Prospects Table (Pipeline Prospek 360°)
CREATE TABLE IF NOT EXISTS prospects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    user_id INT NOT NULL,
    package_id INT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(30) NULL,
    city VARCHAR(100) NULL,
    lead_source VARCHAR(50) DEFAULT 'whatsapp',
    current_stage VARCHAR(50) DEFAULT 'greeting',
    status ENUM('new', 'identifying', 'offered', 'closing', 'objection', 'followup', 'nurture', 'closed_won', 'closed_lost') DEFAULT 'new',
    target_month VARCHAR(50) NULL,
    budget_range VARCHAR(50) NULL,
    room_preference VARCHAR(50) NULL,
    pax_quad INT NOT NULL DEFAULT 0,
    pax_triple INT NOT NULL DEFAULT 0,
    pax_double INT NOT NULL DEFAULT 0,
    pax_infant INT NOT NULL DEFAULT 0,
    special_needs TEXT NULL,
    decision_maker VARCHAR(50) NULL,
    passport_status VARCHAR(50) NULL,
    vaccine_status VARCHAR(50) NULL,
    deal_value DECIMAL(15, 2) DEFAULT 0.00,
    dp_amount DECIMAL(15, 2) DEFAULT 0.00,
    dp_paid_at DATETIME NULL,
    payment_status ENUM('unpaid', 'partial_dp', 'paid_full') DEFAULT 'unpaid',
    lost_reason VARCHAR(100) NULL,
    lost_reason_detail TEXT NULL,
    remote_jid VARCHAR(100) NULL,
    meta_referral_marker TEXT NULL,
    ad_id VARCHAR(100) NULL,
    campaign_id VARCHAR(100) NULL,
    photo_url TEXT NULL,
    notes TEXT NULL,
    next_followup_date DATE NULL,
    last_followup_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5. Prospect Logs Table (Audit Trail Aktivitas)
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

-- 6. WhatsApp Sessions Table (Status Sesi Gateway Baileys)
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

-- 7. Chat Messages Table (Pesan WhatsApp & Media)
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
    is_deleted TINYINT(1) DEFAULT 0,
    deleted_at DATETIME NULL,
    reaction VARCHAR(10) NULL,
    quoted_message_id VARCHAR(100) NULL,
    quoted_text TEXT NULL,
    quoted_sender VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_brand_msg (brand_id, message_id),
    KEY idx_brand_jid (brand_id, remote_jid),
    KEY idx_prospect (prospect_id),
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
    FOREIGN KEY (prospect_id) REFERENCES prospects(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 8. Meta CAPI Logs Table (Riwayat Event Server-Side)
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
```

---

## 7. Folder & Code Organization

```
csumroh/
├── admin/                     # Modul Super Admin
│   ├── brands.php             # Kelola 5 Brand Travel & Kredensial Meta
│   ├── index.php              # Dashboard Analitik & Metrik Admin
│   ├── packages.php           # Daftar Master Paket Umroh
│   ├── package_detail.php     # Detail Spesifikasi Paket & Kamar
│   ├── package_form.php       # Tambah / Edit Paket & Upload Flyer
│   └── users.php              # Manajemen Akun CS & Penugasan Brand
├── api/                       # REST Endpoints & Realtime Services
│   ├── prospects.php          # AJAX CRUD, Claim PIC, Stage Updater Prospek
│   ├── scripts.php            # JSON API Bank Skrip Terinterpolasi
│   ├── whatsapp.php           # Controller Chat WhatsApp & Messages Loader
│   └── whatsapp_webhook.php   # Webhook Penerima Pesan Masuk dari Baileys
├── auth/                      # Otentikasi Pengguna
│   ├── login.php              # Halaman Login (Monochrome UI)
│   └── logout.php             # Pembersih Sesi & Logout
├── config/
│   └── db.php                 # Koneksi PDO MySQL & Auto-Migration/Seeder
├── conversion-chat/           # Master Standar SOP & Kurikulum LMS (JSON)
│   ├── conversion-cycle.json  # 9 Tahap Siklus Konversi Lengkap
│   └── tgjp-framework.json    # Detail Algoritma Penanganan Keberatan
├── data/
│   └── scripts_loader.php     # Engine Loader & Parser Bank Skrip
├── includes/                  # Komponen Reusable UI & Backend Guard
│   ├── auth_check.php         # Guard Sesi Login & Hak Akses Peran
│   ├── footer.php             # Footer & Script Helper Notifikasi Toast
│   ├── header.php             # Header Navigasi, Sidebar & Custom Dropdowns
│   └── meta_capi.php          # Service Meta Conversions API (Graph API v20.0)
├── lms/
│   └── index.php              # Antarmuka E-Learning Belajar Mandiri CS
├── scripts-chat/              # Master Bank Skrip Percakapan (JSON Terkurasi)
│   ├── greeting.json          # Skrip Pembuka & Sapaan Awal
│   ├── identification.json    # Skrip Penggalian Kebutuhan (NPGD)
│   ├── presentation.json      # Skrip Rekomendasi Paket (MRBVA)
│   ├── closing.json           # Skrip Pengamanan Kuota & DP (CRA)
│   ├── objection.json         # Skrip Penanganan Keberatan (TGJP)
│   ├── followups.json         # Skrip Follow-up Terjadwal
│   └── nurturing.json         # Skrip Edukasi & Silaturahmi Panjang
├── uploads/                   # Direktori Berkas Media & Brosur
│   ├── chat/                  # Media Obrolan WhatsApp (.gitkeep)
│   └── flyers/                # Berkas Brosur Flyer Paket Umroh
├── whatsapp-gateway/          # Microservice WhatsApp Web (Baileys)
│   ├── sessions/              # Direktori Sesi Terisolasi per Brand
│   ├── package.json           # Dependensi Node.js (@whiskeysockets/baileys)
│   └── server.js              # HTTP Gateway, WebSocket & Multi-Session Handler
├── AGENTS.md                  # Panduan Konvensi Arsitektur & Rules AI Agent (Dokumen ini)
├── AUDIT_META_CAPI.md         # Dokumentasi & Audit Teknis Meta CAPI
├── chat.php                   # Halaman Utama Terintegrasi WhatsApp Web CS
├── index.php                  # Halaman CS Workspace (Copilot Chat & Quick Copy)
├── prd.md                     # Product Requirements Document
├── prospects.php              # Halaman CRM Prospek Umroh (Kanban & Table)
├── prospect_detail.php        # Halaman Profil Prospek 360° & Riwayat Lengkap
├── whatsapp_connect.php       # Halaman QR Scanner & Status Sesi WhatsApp
└── README.md                  # Dokumentasi Resmi Proyek
```

---

## 8. Development Rules for AI Agents

1. **NO COMMIT OR PUSH WITHOUT EXPLICIT USER INSTRUCTION (ATURAN MUTLAK)**:
   * Agen AI **DILARANG KERAS** menjalankan `git commit` atau `git push` kecuali jika pengguna secara eksplisit dan sengaja memberikan perintah (misalnya: *"commit dan push"*).
2. **ZERO NATIVE `<select>` DROPDOWNS (ATURAN KONSISTENSI UI)**:
   * Jangan pernah membuat atau menggunakan elemen `<select>` native browser.
   * Seluruh pilihan interaktif wajib menggunakan **Custom Dropdown Alpine.js** monokrom (`x-data="{ open: false }"`, `@click.outside="open = false"`).
3. **Explicit User Instruction Rule**:
   * Jangan melakukan penambahan fitur atau koding di luar apa yang diperintahkan pengguna secara spesifik.
4. **Minimalism First & No Build Tools**:
   * Buat kode yang bersih, mudah dipahami, tanpa dependensi build step (*No Webpack, No Vite, No NPM* untuk aplikasi web utama).
5. **Prepared Statements Always**:
   * Seluruh interaksi database wajib menggunakan parameter binding PDO untuk mencegah celah SQL Injection.
6. **Preserve Native JSONs**:
   * File asli di `conversion-chat/` dan `scripts-chat/` tidak boleh dihapus atau dirusak; baca datanya secara aman dan transformasikan secara dinamis di runtime/cache.
7. **Strict Monochrome**:
   * Selalu pertahankan palet hitam-putih-abu-abu netral (`black`, `zinc-50` s.d. `zinc-900`) pada seluruh komponen antarmuka.
