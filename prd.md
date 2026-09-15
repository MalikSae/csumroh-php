# Product Requirements Document (PRD)
# CS Umroh Copilot & LMS (Multi-Brand Edition)

## 1. Executive Summary & Problem Statement

### 1.1 Background
Perusahaan mengelola **5 brand travel umroh**. Dalam operasional harian, Customer Service internal (seperti CS Fitri di Azhan Tour & Travel) bertugas menangani *inbound leads* (prospek jamaah) dari WhatsApp, iklan, dan formulir website. 

Saat ini, perusahaan telah memiliki SOP sales umroh dan ratusan bank skrip percakapan dalam bentuk file JSON terstruktur (`conversion-chat/` dan `scripts-chat/`). Namun, skrip tersebut masih berorientasi pada sudut pandang agen/mitra lapangan dan sulit diakses secara cepat saat CS melayani chat WhatsApp yang padat.

### 1.2 Objective
Membangun aplikasi web internal yang **minimalis, fungsional, dan berdampak tinggi (high-impact)** untuk:
1. Membantu CS membalas chat secara instan, relevan, dan terpersonalisasi tanpa salah brand atau salah rekening.
2. Membimbing CS menangani keberatan jamaah secara sistematis menggunakan framework **TGJP** (*Terima, Gali, Jawab, Pastikan*).
3. Melacak status prospek harian agar tidak ada calon jamaah yang menggantung tanpa tindak lanjut.
4. Menyediakan modul **LMS Belajar Mandiri** interaktif agar CS dapat menguasai SOP konversi umroh secara terstruktur.
5. Memberikan kontrol penuh bagi Super Admin untuk mengelola 5 brand travel, paket umroh, dan akun CS.

---

## 2. Design System & Aesthetic Guidelines

### 2.1 Style: Neutral Black & White (Monochrome)
Sesuai arahan, antarmuka menggunakan palet **netral hitam-putih (monochrome)** yang bersih, kontras tinggi, fokus pada teks, dan bebas dari distraksi warna-warni yang mencolok.

* **Primary Background**: `#FFFFFF` (Light Clean) dengan card/panel `#F9FAFB` (Zinc-50) & `#F3F4F6` (Zinc-100).
* **Borders & Dividers**: `#E5E7EB` (Zinc-200) atau `#D1D5DB` (Zinc-300).
* **Text Hierarchy**:
  * Heading & Primary Text: `#111827` (Zinc-900) / Pure Black `#000000`.
  * Secondary Text / Descriptions: `#4B5563` (Zinc-600) / `#6B7280` (Zinc-500).
  * Muted / Placeholder: `#9CA3AF` (Zinc-400).
* **Interactive Elements (Buttons & Active States)**:
  * Primary Action: Solid Black (`#000000`) dengan teks putih (`#FFFFFF`), hover `#1F2937` (Zinc-800).
  * Secondary Action: Border `#000000` / `#D1D5DB` dengan latar putih dan teks hitam.
  * Status Badges: Skala abu-abu dengan variasi kontras (*dark badge for active/won, bordered badge for progress, light badge for neutral/lost*).
* **Layout Optimization**:
  * Responsif dan fleksibel untuk mode **Split-Screen** (50% layar di samping WhatsApp Web).

---

## 3. User Personas & Roles

| Role | Tanggung Jawab Utama | Lingkup Akses |
| :--- | :--- | :--- |
| **Super Admin** | Manajemen entitas bisnis pusat | Kelola 5 Brand Travel, Kelola Paket Umroh per Brand, Kelola Akun CS, Monitoring Prospek Global. |
| **CS Umroh** (e.g. Fitri) | Konsultasi & konversi calon jamaah | Login sesuai akun pribadi, terikat pada 1 Brand Travel, Copilot Chat & Quick Copy, Mini Lead Tracker, Modul LMS. |

---

## 4. Functional Requirements

### 4.1 Autentikasi & Multi-Brand Scoping
* **REQ-AUTH-01**: Login berbasis email dan password.
* **REQ-AUTH-02**: Sesi aman dengan pemisahan akses `superadmin` dan `cs`.
* **REQ-AUTH-03**: Setiap user CS memiliki relasi ke `brand_id` tertentu.
* **REQ-AUTH-04**: Saat CS login, seluruh variabel brand (Nama Travel, Izin PPIU, Rekening Transfer Bank) dan pilihan paket otomatis terisolasi sesuai brand CS tersebut.

### 4.2 Super Admin Management
* **REQ-ADM-01 (Kelola Brand Travel)**:
  * Super Admin dapat menambah, melihat, mengedit detail 5 Brand Travel.
  * Field: Nama Brand, Nomor Izin PPIU Kemenag, Bank & No. Rekening Resmi, Atas Nama Rekening, Alamat Kantor, No. Kontak Pusat.
* **REQ-ADM-02 (Kelola Paket Umroh)**:
  * Super Admin dapat menambah, mengedit, mengaktifkan/menonaktifkan paket umroh per brand.
  * Field: Brand Pemilik, Nama Paket, Harga Paket, Nilai Minimal DP, Maskapai, Hotel Makkah, Hotel Madinah, Jadwal/Bulan Keberangkatan, Fasilitas Utama.
* **REQ-ADM-03 (Kelola Akun CS)**:
  * Super Admin dapat membuat akun CS baru dan menugaskannya ke salah satu Brand Travel.
  * Field: Nama Lengkap CS, Email, Password, Brand Penugasan, Status Akun.

### 4.3 CS Workspace - Fitur 1: Copilot Chat & Quick Copy (POV CS)
* **REQ-COP-01 (Variable Injector)**:
  * Bar atas memuat: Nama Calon Jamaah (`{{nama}}`), Dropdown Paket Umroh (hanya paket dari brand milik CS), dan Nama CS (`{{cs_name}}`).
  * Variabel otomatis diinjeksi ke skrip: `{{nama}}`, `{{travel}}`, `{{cs_name}}`, `{{paket}}`, `{{harga}}`, `{{dp}}`, `{{hotel}}`, `{{maskapai}}`, `{{rekening}}`, `{{nama_rekening}}`, `{{ppiu}}`.
* **REQ-COP-02 (Navigasi Tahapan Konversi)**:
  * Tab navigasi 6 tahap:
    1. Greeting
    2. Identifikasi (NPGD: *Need, Pain, Gain, Dream*)
    3. Penawaran (Formula MRBVA)
    4. Closing (Formula CRA & Pengamanan DP)
    5. Keberatan (Framework TGJP)
    6. Follow-up (Formula CCN & Nurture)
* **REQ-COP-03 (Pencarian & 1-Klik Salin)**:
  * Input pencarian instan (contoh: cari skrip *"kemahalan"*, *"tanya suami"*, *"minta diskon"*).
  * Tombol 1-klik "Salin Pesan" dengan notifikasi visual instan ("Tersalin ke Clipboard!").
* **REQ-COP-04 (Interactive TGJP Objection Wizard)**:
  * Antarmuka bertahap untuk menangani keberatan jamaah:
    * Step 1: Terima (Empathy script)
    * Step 2: Gali (Root cause question)
    * Step 3: Jawab (Solusi paket/value)
    * Step 4: Pastikan (Check closing readiness)
* **REQ-COP-05 (Standarisasi POV Customer Service)**:
  * Seluruh narasi skrip menggunakan sudut pandang Customer Service resmi travel, ramah, profesional, syar'i, dan amanah (bukan agen personal / makelar).

### 4.4 CS Workspace - Fitur 2: Mini Lead Tracker (Pencatatan Prospek)
* **REQ-LEAD-01 (Quick Lead Form)**:
  * Form input cepat calon jamaah: Nama, No. WhatsApp, Paket Diminati, Tahap Saat Ini, Catatan Singkat NPGD, Tanggal Next Follow-up.
* **REQ-LEAD-02 (Pipeline List & Direct Action)**:
  * Menampilkan daftar prospek yang sedang di-handle oleh CS tersebut.
  * Tombol direct link buka WhatsApp Web (`wa.me/628xxx`).
  * Tombol 1-klik: Klik nama prospek $\rightarrow$ variabel di Copilot Chat langsung terisi otomatis.
* **REQ-LEAD-03 (Status Lifecycle)**:
  * Mendukung 9 status resmi: `new`, `identifying`, `offered`, `closing`, `objection`, `followup`, `nurture`, `closed_won`, `closed_lost`.

### 4.5 CS Workspace - Fitur 3: LMS Belajar Mandiri
* **REQ-LMS-01 (Struktur Kurikulum Sidebar)**:
  * Modul 1: Pengantar Umroh Conversion Cycle & Filosofi Sales Syar'i
  * Modul 2: Greeting & Sell Yourself (Membangun Kenyamanan & Trust)
  * Modul 3: Prospect Identification & Framework NPGD
  * Modul 4: Crafting Offers & Formula MRBVA
  * Modul 5: Closing Strategy & Formula CRA (Mengunci DP)
  * Modul 6: Handling Objection & Framework TGJP
  * Modul 7: Follow-up & Formula CCN
  * Modul 8: Kamus 9 Status Prospek & Manajemen Pipeline
  * Modul 9: Kode Etik CS Umroh, Urgency Nyata & Anti-Overpromise
* **REQ-LMS-02 (Konten Interaktif)**:
  * Estimasi waktu baca modul.
  * Komparasi contoh nyata: *Chat Salah / Kurang Tepat* vs *Chat Standar SOP*.
  * Checklist pemahaman mandiri & tombol *"Tandai Sudah Dipelajari"*.

---

## 5. Technical Architecture

* **Environment**: Lokal Laragon (Windows).
* **Web Server**: Apache / Nginx bawaan Laragon atau PHP Built-in Server.
* **Backend**: PHP 8.3 Native (Modular, Prepared Statement PDO MySQL, Clean Session Auth).
* **Database**: MySQL (`localhost:3306`, database `csumroh`, user `root`, password `""`).
* **Frontend**: Tailwind CSS CDN + Alpine.js CDN (Tanpa Node.js build step, zero compilation).
* **Data Seed**: 11 file JSON pada `conversion-chat/` dan `scripts-chat/` dimanfaatkan sebagai basis data kurikulum dan skrip.

---

## 6. Success Metrics & Impact

1. **Efisiensi Waktu CS**: Kecepatan merespons chat prospek meningkat 3x lipat dengan fitur Smart Copier & 1-Klik Salin.
2. **Akurasi Bisnis Multi-Brand**: 0% kesalahan penulisan nomor rekening DP atau salah nama travel pada 5 brand perusahaan.
3. **Peningkatan Konversi Closing**: CS terbiasa menggunakan alur TGJP dan CRA terstandar, meminimalisir prospek yang menggantung tanpa status.
4. **Onboarding Cepat CS Baru**: CS baru bisa mandiri mempelajari SOP umroh melalui modul LMS dalam waktu < 2 jam.

