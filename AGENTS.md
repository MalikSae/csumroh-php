# AGENTS.md
# Guidelines & System Context for AI Coding Agents

Proyek: **CS Umroh Copilot & LMS (Multi-Brand)**  
Path: `C:\laragon\www\csumroh`

---

## 1. Project Purpose & Scope

Aplikasi ini adalah sistem internal minimalis namun berdampak tinggi (*high-impact*) untuk Customer Service (CS) Umroh perusahaan (seperti CS Fitri di Azhan Tour & Travel) yang mengelola **5 brand travel umroh**.

Tujuan utamanya:
1. **Copilot Chat & Quick Copy**: Menyediakan skrip percakapan terkurasi, auto-replace variabel nama calon jamaah dan detail brand travel, navigasi penanganan keberatan (**TGJP**), dan 1-klik salin ke WhatsApp.
2. **Mini Lead Tracker**: Manajemen prospek harian dengan pelacakan 9 status konversi.
3. **LMS Belajar Mandiri**: Modul e-learning mandiri bagi CS berdasarkan `conversion-cycle.json`.
4. **Super Admin Multi-Brand**: Manajemen master 5 Brand Travel, Paket Umroh per Brand, dan Akun CS.

---

## 2. Environment & Runtime Specifications

* **OS**: Windows (Laragon environment).
* **PHP Binary**: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`
* **Web Server Root**: `C:\laragon\www\csumroh` (diakses via `http://localhost/csumroh` atau virtual host Laragon `http://csumroh.test`).
* **Database**: MySQL Server (Laragon default port 3306, user `root`, password `""`, database `csumroh`).
* **Dependency Policy**:
  * **TIDAK MENGGUNAKAN BUILD TOOLS (No Webpack, No Vite, No NPM)**.
  * Frontend menggunakan **Tailwind CSS CDN** dan **Alpine.js CDN** untuk interaktivitas reaktif.
  * Backend menggunakan **PHP 8.3 Native Modern** dengan PDO MySQL (ringan, cepat, mudah dirawat).

---

## 3. Strict Design Rules: Neutral Black & White (Monochrome)

Antarmuka **WAJIB** menerapkan gaya **netral hitam-putih (monochrome)** murni tanpa warna-warni mencolok (tidak menggunakan gradien warna-warni, tidak menggunakan warna primer biru/hijau/merah mencolok).

### Color Tokens:
* **Backgrounds**:
  * Body/Main Canvas: `bg-white` (`#ffffff`)
  * Panels/Cards: `bg-zinc-50` (`#fafafa`) atau `bg-gray-50` (`#f9fafb`)
  * Borders/Dividers: `border-zinc-200` (`#e4e4e7`) atau `border-gray-200` (`#e5e7eb`)
* **Text**:
  * Heading: `text-black` (`#000000`) atau `text-zinc-900` (`#18181b`)
  * Body: `text-zinc-700` (`#3f3f46`)
  * Subtext / Muted: `text-zinc-500` (`#71717a`)
* **Buttons & Badges**:
  * Primary Button: `bg-black text-white hover:bg-zinc-800`
  * Secondary / Outline Button: `border border-zinc-300 text-zinc-800 hover:bg-zinc-100 bg-white`
  * Status Badges:
    * Active / Won: `bg-black text-white`
    * Progress / Follow-up: `border border-zinc-800 text-zinc-900 bg-white`
    * Pending / New: `bg-zinc-100 text-zinc-700`
    * Lost / Batal: `bg-zinc-200 text-zinc-600 line-through`
* **Form Elements**:
  * Input & Select: `border border-zinc-300 focus:border-black focus:ring-1 focus:ring-black bg-white rounded-md`
* **Layout Rule**:
  * Layout harus nyaman digunakan secara **Split-Screen** (lebar 50% layar) di samping WhatsApp Web.

---

## 4. Multi-Brand Architecture & Scoping

Perusahaan memiliki **5 Brand Travel**. Setiap entitas di dalam sistem memiliki relasi yang jelas:

```
[Master 5 Brands]
       │
       ├─── [Users / CS] (Setiap CS di-assign ke 1 Brand)
       ├─── [Packages]   (Setiap Paket Umroh milik 1 Brand)
       └─── [Prospects]  (Setiap Prospek tercatat di bawah 1 Brand & 1 CS)
```

### Aturan Scoping Brand:
1. Saat CS login, sistem mendeteksi `brand_id` dari session CS tersebut.
2. Semua variabel brand (`{{travel}}`, `{{ppiu}}`, `{{rekening}}`, `{{nama_rekening}}`, dll) otomatis mengambil data dari brand CS yang sedang login.
3. Dropdown pilihan paket umroh di halaman CS **hanya menampilkan paket milik brand tersebut**.
4. Super Admin memiliki hak akses lintas brand (*cross-brand*).

---

## 5. Standarisasi Script: Transformasi POV Agent $\rightarrow$ CS Umroh

File JSON asli di folder `scripts-chat/` dan `conversion-chat/` ditulis dengan sudut pandang Agen/Mitra Lapangan. Agen AI **wajib melakukan transformasi narasi** saat menyajikan skrip kepada pengguna:

1. **Variabel Nama**:
   * Ubah `{{agent_name}}` menjadi **`{{cs_name}}`**.
2. **Representasi Diri**:
   * Hapus frasa *"mitra agen konsultan"*, *"agen resmi"*, *"referral link agen"*.
   * Ganti dengan: *"Customer Service resmi {{travel}}"*, *"tim layanan jamaah {{travel}}"*, atau *"konsultan umroh resmi {{travel}}"*.
3. **Inbound Context**:
   * Sesuaikan konteks pesan pembuka untuk jamaah yang masuk dari website, iklan, atau media sosial resmi travel.

---

## 6. Database Schema Definition (MySQL)

```sql
CREATE DATABASE IF NOT EXISTS csumroh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE csumroh;

-- 1. Brands Table (5 Brand Travel)
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

-- 2. Users Table
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

-- 3. Packages Table
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
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Prospects Table
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL
) ENGINE=InnoDB;
```

---

## 7. Folder & Code Organization

```
c:\laragon\www\csumroh\
├── AGENTS.md                  # System & Agent Guidelines (Dokumen ini)
├── prd.md                     # Product Requirements Document
├── config/
│   └── db.php                 # Koneksi PDO MySQL & Migration Helper
├── auth/
│   ├── login.php              # Halaman & Proses Login
│   └── logout.php             # Logout & Destroy Session
├── admin/
│   ├── index.php              # Dashboard Super Admin
│   ├── brands.php             # Kelola 5 Brand Travel
│   ├── packages.php           # Kelola Paket Umroh
│   └── users.php              # Kelola Akun CS
├── includes/
│   ├── auth_check.php         # Guard autentikasi session & role
│   ├── header.php             # Header monochrome & navigasi
│   └── footer.php             # Footer & toast notification
├── data/
│   └── scripts_loader.php     # Service pembaca & normalisasi JSON (Agent -> CS POV)
├── lms/
│   └── index.php              # Modul Belajar Mandiri (Tampilan LMS)
├── index.php                  # CS Workspace (Copilot Chat & Mini Lead Tracker)
├── conversion-chat/           # Master Data Framework SOP
└── scripts-chat/              # Master Data Bank Skrip
```

---

## 8. Development Rules for AI Agents

1. **Explicit User Instruction Rule**: Jangan melakukan koding sampai pengguna memerintahkan secara eksplisit.
2. **Minimalism First**: Buat kode yang bersih, mudah dipahami, tanpa abstraksi berlebihan yang tidak perlu.
3. **Prepared Statements Always**: Seluruh interaksi database wajib menggunakan parameter binding PDO untuk mencegah SQL Injection.
4. **Preserve Native JSONs**: File asli di `conversion-chat/` dan `scripts-chat/` tidak boleh dihapus atau dirusak; baca datanya secara aman dan transformasikan secara dinamis di runtime/cache.
5. **Strict Monochrome**: Selalu pertahankan palet hitam-putih-abu-abu netral pada seluruh komponen UI.

