# CS Umroh Copilot, CRM Pipeline & LMS (Multi-Brand Travel)

Sistem operasional terpadu untuk Customer Service (CS) Umroh perusahaan yang mengelola **Multi-Brand Travel Umroh** secara aman, terisolasi, dan kolaboratif. Menggabungkan asisten percakapan cerdas (*Copilot Chat*), antarmuka WhatsApp Web multi-sesi langsung (*Shared Inbox*), pelacakan prospek enterprise (*CRM Kanban & 360° Profile*), serta modul pelatihan mandiri (*E-Learning LMS*).

Sistem dirancang dengan filosofi **Strict Monochrome (Netral Hitam-Putih)** yang elegan, kontras tajam, bebas distraksi visual, dan dilengkapi indikator status semantik terukur pada badge tahapan konversi. Seluruh elemen antarmuka menggunakan **Custom Dropdown Alpine.js** tanpa elemen `<select>` bawaan browser.

---

## 📑 Daftar Isi

1. [Fitur Utama Sistem](#-fitur-utama-sistem)
2. [Arsitektur Multi-Brand & Multi-CS Shared Inbox](#-arsitektur-multi-brand--multi-cs-shared-inbox)
3. [Enterprise CRM & Pipeline 360°](#-enterprise-crm--pipeline-360)
4. [WhatsApp Web Gateway & Real-Time Engine](#-whatsapp-web-gateway--real-time-engine)
5. [Mesin Skrip Copilot & 19 Variabel Dinamis (CRO)](#-mesin-skrip-copilot--19-variabel-dinamis-cro)
6. [Framework Penanganan Keberatan (TGJP)](#-framework-penanganan-keberatan-tgjp)
7. [Meta Conversions API (CAPI) & Pelacakan Iklan CTWA](#-meta-conversions-api-capi--pelacakan-iklan-ctwa)
8. [Modul E-Learning LMS Mandiri CS](#-modul-e-learning-lms-mandiri-cs)
9. [Super Admin Multi-Brand Panel](#-super-admin-multi-brand-panel)
10. [Spesifikasi Teknologi](#-spesifikasi-teknologi)
11. [Panduan Instalasi & Menjalankan Sistem](#-panduan-instalasi--menjalankan-sistem)
12. [Kredensial Akun Bawaan (Demo)](#-kredensial-akun-bawaan-demo)
13. [Struktur Direktori Proyek](#-struktur-direktori-proyek)

---

## 🚀 Fitur Utama Sistem

### 1. 💬 All-in-One WhatsApp Web & Shared Inbox (`chat.php`)
* **Live Chat Messenger**: Mengirim dan menerima pesan teks, foto, dokumen PDF brosur, rekaman suara (*voice note* audio), video, stiker, reaksi emoji (*reactions*), serta kutipan balasan (*quoted replies*).
* **Multi-Identifier Auto-Select**: Membuka percakapan secara instan dari tautan CRM menggunakan pencocokan otomatis Linked Device JID (`@lid`), WhatsApp JID (`@s.whatsapp.net`), `prospect_id`, maupun nomor telepon.
* **Auto-Stub New Lead**: Jika kontak belum memiliki riwayat obrolan di WhatsApp, sistem otomatis membuat ruang obrolan sementara (*stub chat*) agar CS dapat langsung memulai pesan pertama.
* **Sidebar Profil CRM 360° & Mini Copilot**: Mengubah status pipa prospek, mencatat kebutuhan kamar, menetapkan paket, dan menyalin skrip percakapan langsung di samping jendela obrolan tanpa berpindah tab.
* **Deteksi Iklan Meta CTWA**: Menampilkan penanda khusus (*badge referral iklan*) yang memuat `Ad ID`, `Campaign ID`, dan teks pemicu awal dari calon jamaah yang masuk via iklan berbayar Facebook/Instagram.

### 2. 📊 Enterprise CRM Pipeline & Prospek Umroh (`prospects.php`)
* **Dual View Mode**:
  * **Kanban Pipeline (8 Tahapan)**: Papan visual interaktif dengan ringkasan jumlah prospek per kolom, estimasi nilai transaksi per tahap, kartu prospek informatif, dan menu geser status cepat.
  * **Rich Table List**: Tabel data lengkap dengan identitas jamaah, paket pilihan, kualifikasi kamar, estimasi deal & DP, indikator jadwal follow-up, CS PIC penanggung jawab, tombol WhatsApp, dan ekspor data ke format CSV.
* **Toolbar Filter Cerdas**:
  * Pencarian instan (nama, nomor WA, domisili kota, catatan).
  * Tombol Pill Cepat: *Semua*, *🔥 High Intent* (Closing & Offered), *⏰ Follow-up Hari Ini*, *🏆 Closed Won*, dan *Baru*.
  * Filter Sekunder Kustom: Filter per Paket Umroh, Sumber Prospek (*WhatsApp, Meta Ads, Website, Referral, Walk-in*), dan Filter Tim CS PIC.

### 3. 👤 Profil Prospek 360° Lengkap (`prospect_detail.php`)
* **Kualifikasi Kebutuhan NPGD**: *Need, Priority, Group Size, Decision Maker*.
* **Rincian Kamar & Estimasi Deal Otomatis**: Perhitungan otomatis nilai transaksi berdasarkan konfigurasi kamar (*Quad, Triple, Double, Infant*) dikalikan harga paket.
* **Kesiapan Dokumen Jamaah**: Pelacakan status paspor (*Sudah Ada, Proses Buat, Perpanjang*) dan status vaksinasi meningitis.
* **Catatan Finansial**: Rekapitulasi nominal DP masuk, tanggal pembayaran DP, status pembayaran (*Unpaid, Partial DP, Paid Full*), dan sisa pelunasan.
* **Activity Audit Trail**: Pencatatan riwayat kronologis setiap interaksi (perubahan status, log follow-up, pengalihan CS PIC, pengiriman event Meta CAPI).
* **Tab Skrip Khusus Prospek**: Skrip rekomendasi penanganan keberatan TGJP yang disesuaikan dengan kondisi prospek saat itu.

### 4. ⚡ Copilot Workspace & Smart Script Copier (`index.php`)
* **Bar Variabel Otomatis**: Mengisi nama jamaah dan memilih paket akan langsung menginterpolasi 19 variabel dinamis ke dalam seluruh skrip secara real-time.
* **1-Klik Salin & Quick WhatsApp**: Tombol salin pesan otomatis ke clipboard dengan notifikasi toast, serta tombol langsung buka WhatsApp Web.
* **Mini Lead Tracker**: Widget ringkas pemantauan prospek harian langsung pada layar kerja CS.

### 5. 🛡️ Interactive TGJP Objection Wizard
Panduan respons keberatan jamaah (*"Biaya kemahalan"*, *"DP berat"*, *"Runding keluarga dulu"*, *"Ragu legalitas izin travel"*):
* **T (Terima)**: Validasi empati tanpa mendebat calon jamaah.
* **G (Gali)**: Menggali pertimbangan inti dengan pertanyaan pilihan terarah.
* **J (Jawab)**: Memberikan komparasi nilai rasional (bukan janji berlebihan).
* **P (Pastikan)**: Mendorong komitmen mikro atau penguncian kuota seat.

### 6. 🎯 Meta Conversions API (CAPI) Terintegrasi
* Pengiriman event server-side ke Meta Events Manager secara aman (*hashed phone & email* SHA256).
* Trigger otomatis pada perubahan status prospek:
  * `identifying` $\rightarrow$ Event `Contact`
  * `offered` $\rightarrow$ Event `AddToCart`
  * `closing` $\rightarrow$ Event `InitiateCheckout`
  * `closed_won` $\rightarrow$ Event `Purchase` (dengan estimasi nominal transaksi IDR)

### 7. 🎓 Modul LMS Belajar Mandiri CS (`lms/index.php`)
* Modul e-learning 9 bab kurikulum konversi umroh modern (`conversion-cycle.json`).
* Komparasi praktis: **Contoh Chat Salah (Don't)** vs **Contoh Chat Benar (Do)**.
* Checklist pemahaman mandiri interaktif tersimpan lokal di browser.

### 8. 👑 Super Admin Multi-Brand Panel (`admin/`)
* **Kelola Brand Travel** (`admin/brands.php`): Konfigurasi brand, legalitas PPIU Kemenag, rekening bank resmi perusahaan, kontak, serta kredensial Meta Pixel & Access Token.
* **Kelola Paket Umroh** (`admin/packages.php`): Konfigurasi paket per brand, tanggal keberangkatan, harga bertingkat per kamar (Quad, Triple, Double, Infant), sisa kuota seat, fasilitas, itinerary, dan upload flyer brosur.
* **Kelola Pengguna CS** (`admin/users.php`): Pembuatan akun CS dan penetapan hak akses ke brand tertentu.

---

## 🏢 Arsitektur Multi-Brand & Multi-CS Shared Inbox

Perusahaan memiliki arsitektur multi-brand dengan model **Shared Inbox Kolaboratif**:

```
                       ┌─────────────────────────────────┐
                       │    SUPER ADMIN (Cross-Brand)    │
                       │  Akses Penuh Seluruh 5 Brand    │
                       └────────────────┬────────────────┘
                                        │
        ┌──────────────┬────────────────┼────────────────┬──────────────┐
        ▼              ▼                ▼                ▼              ▼
 ┌──────────────┐┌──────────────┐┌──────────────┐┌──────────────┐┌──────────────┐
 │   Brand 1    ││   Brand 2    ││   Brand 3    ││   Brand 4    ││   Brand 5    │
 │  Hana Tours  ││  Nava Tours  ││Safwa Barakah ││Al-Fajr Insani││Madinah Makmur│
 └──────┬───────┘└──────┬───────┘└──────┬───────┘└──────┬───────┘└──────┬───────┘
        │               │               │               │               │
  ┌─────┴─────┐   ┌─────┴─────┐   ┌─────┴─────┐   ┌─────┴─────┐   ┌─────┴─────┐
  │CS Fitri   │   │CS Rina    │   │CS ...     │   │CS ...     │   │CS ...     │
  │CS Malik   │   │CS ...     │   │           │   │           │   │           │
  └───────────┘   └───────────┘   └───────────┘   └───────────┘   └───────────┘
   [Paket B1]      [Paket B2]      [Paket B3]      [Paket B4]      [Paket B5]
   [Shared CRM]    [Shared CRM]    [Shared CRM]    [Shared CRM]    [Shared CRM]
   [WA Sesi B1]    [WA Sesi B2]    [WA Sesi B3]    [WA Sesi B4]    [WA Sesi B5]
```

### Mekanisme Kolaborasi Tim CS:
1. **Brand-Scoped Transparency**: Semua CS yang bertugas pada brand yang sama dapat melihat seluruh percakapan dan prospek brand tersebut.
2. **Klaim PIC (*Ownership*)**: Setiap prospek memiliki atribut `user_id` (CS PIC penanggung jawab). CS lain dapat mengambil alih penanganan prospek dengan menekan tombol **`[Klaim PIC]`** di Kanban card, tabel, halaman detail 360°, maupun livechat WhatsApp.
3. **Auto-Takeover Saat Membalas Pesan**: Saat seorang CS mengirim pesan balasan WhatsApp ke calon jamaah, sistem secara otomatis memperbarui PIC prospek menjadi CS pengirim pesan tersebut.
4. **Balanced Distribution Webhook**: Pesan masuk WhatsApp dari kontak baru otomatis dialokasikan ke CS aktif dari brand terkait yang memiliki jumlah prospek aktif paling sedikit.
5. **Keamanan Finansial Terisolasi**: Rekening bank dan nomor izin PPIU selalu terkunci sesuai brand CS yang bertugas, meniadakan risiko salah kirim nomor rekening antar brand.

---

## 📊 Enterprise CRM & Pipeline 360°

Alur pipa konversi prospek jamaah menggunakan 8 tahapan standar industri umroh:

```
[1. Prospek Baru]
       │
       ▼
[2. Identifikasi Kebutuhan (NPGD)]
       │
       ▼
[3. Ditawarkan Paket (MRBVA)]
       │
       ├─────────────────────────┐
       ▼                         ▼
[4. Keberatan (TGJP)]     [5. Follow-up Terjadwal]
       │                         │
       └───────────┬─────────────┘
                   ▼
       [6. Closing / Ambil Seat (CRA)]
                   │
         ┌─────────┴─────────┐
         ▼                   ▼
[7. Deal Menang (Won)]  [8. Batal (Lost)]
 (DP Masuk / Pelunasan)  (Tercatat Alasan Batal)
```

---

## 📱 WhatsApp Web Gateway & Real-Time Engine

Sistem mengintegrasikan WhatsApp Web engine mandiri berbasis `@whiskeysockets/baileys` yang berjalan di latar belakang:

* **Lokasi Service**: `whatsapp-gateway/server.js` (Port default: `3001`).
* **Multi-Session Isolation**: Kredensial autentikasi WhatsApp tiap brand tersimpan terpisah pada direktori `whatsapp-gateway/sessions/brand_{id}/`.
* **Koneksi QR Visual**: Halaman `whatsapp_connect.php` menyediakan pemindaian kode QR per brand dan pemantauan status koneksi langsung.
* **Webhook Sinkron**: Pesan masuk diterima oleh `api/whatsapp_webhook.php`, disimpan ke database MySQL `chat_messages`, dan langsung disinkronkan ke layar obrolan CS.

---

## 📝 Mesin Skrip Copilot & 19 Variabel Dinamis (CRO)

Bank skrip pada direktori `scripts-chat/` telah distandarisasi menggunakan kaidah **Conversion Rate Optimization (CRO)**:
* **Single-Question Rule**: Setiap pesan ditutup dengan maksimal satu pertanyaan terarah untuk mencegah kebingungan jamaah.
* **Pilihan Alternatif Biner**: Menghindari pertanyaan buntu (*"Ada yang bisa dibantu?"*) dan menggantinya dengan pilihan konkret (*"Apakah mengutamakan keberangkatan bulan Oktober atau November?"*).
* **Sudut Pandang Resmi CS Travel**: Menggunakan narasi perwakilan resmi perusahaan (*Customer Service resmi {{travel}}*).

### Tabel 19 Variabel Token:

| Variabel | Deskripsi | Sumber Data |
| :--- | :--- | :--- |
| `{{nama}}` | Nama sapaan calon jamaah | Input CS / Data Prospek |
| `{{cs_name}}` | Nama CS yang sedang login | Sesi Akun CS |
| `{{travel}}` | Nama resmi brand travel | Master Brand |
| `{{ppiu}}` | Nomor izin PPIU Kemenag | Master Brand |
| `{{bank}}` | Nama bank transfer resmi | Master Brand |
| `{{rekening}}` | Nomor rekening resmi perusahaan | Master Brand |
| `{{nama_rekening}}` | Pemilik rekening (Atas Nama) | Master Brand |
| `{{alamat}}` | Alamat kantor pusat travel | Master Brand |
| `{{telepon}}` | Kontak kantor resmi | Master Brand |
| `{{paket}}` | Nama paket umroh yang dipilih | Master Paket |
| `{{harga}}` | Harga paket umroh | Master Paket |
| `{{dp}}` | Besaran uang muka (DP) | Master Paket |
| `{{airline}}` | Maskapai penerbangan | Master Paket |
| `{{hotel}}` | Ringkasan hotel | Master Paket |
| `{{hotel_makkah}}` | Hotel bintang & jarak Makkah | Master Paket |
| `{{hotel_madinah}}` | Hotel bintang & jarak Madinah | Master Paket |
| `{{durasi}}` | Durasi perjalanan (hari) | Master Paket |
| `{{keberangkatan}}` | Tanggal / musim keberangkatan | Master Paket |
| `{{highlights}}` | Fasilitas unggulan paket | Master Paket |

---

## 🛡️ Framework Penanganan Keberatan (TGJP)

Skrip keberatan pada `scripts-chat/objection.json` diklasifikasikan ke dalam 4 langkah respons psikologis:

```
[Keberatan Jamaah] 
       │
       ▼
1. T (Terima)    ──► Validasi empati: "Alhamdulillah, kami sangat memahami pertimbangan Bapak/Ibu..."
       │
       ▼
2. G (Gali)      ──► Pertanyaan terarah: "Bolehkah kami tahu, apakah kendalanya di budget atau jadwal...?"
       │
       ▼
3. J (Jawab)     ──► Solusi rasional: "Untuk alternatif yang lebih terjangkau, kami memiliki paket..."
       │
       ▼
4. P (Pastikan)  ──► Komitmen mikro: "Apakah kami bantu amankan 2 seat terlebih dahulu untuk jadwal ini?"
```

---

## 🎯 Meta Conversions API (CAPI) & Pelacakan Iklan CTWA

* **Atribusi Iklan Otomatis**: Webhook WhatsApp mengekstrak data rujukan iklan (`ctwa_clid`, `ad_id`, `campaign_id`) dan menautkannya ke profil prospek.
* **Server-Side Event Dispatching**: Setiap pembaruan status prospek mengirimkan HTTP POST ke endpoint Meta Graph API (`v20.0`) dengan hashing SHA256 pada nomor telepon dan email.
* **Audit Trail**: Seluruh respons dan payload Meta CAPI tersimpan pada tabel `meta_capi_logs`.

---

## 🎓 Modul E-Learning LMS Mandiri CS

Terletak di `lms/index.php`, menyajikan 9 bab materi pelatihan:
1. **Prinsip Dasar CS Umroh**: Mindset pelayan tamu Allah (*Khadimul Dhuyufurrahman*).
2. **SOP Greeting & Respons Cepat**: Waktu respons emas di bawah 3 menit.
3. **Metode Penggalian NPGD**: *Need, Priority, Group, Decision Maker*.
4. **Presentasi Paket MRBVA**: *Meet Needs, Return Value, Benefit First*.
5. **Teknik Closing CRA**: *Choice, Scarcity, Action*.
6. **Penanganan Keberatan TGJP**: *Terima, Gali, Jawab, Pastikan*.
7. **Strategi Follow-up Berbobot**: Memberi nilai tambah tanpa spamming.
8. **Nurturing Calon Jamaah Pasif**: Edukasi manasik dan doa harian.
9. **Kepatuhan Regulasi & Etika Umroh**: Standar PPIU Kemenag dan transparansi biaya.

---

## 👑 Super Admin Multi-Brand Panel

Akses khusus Super Admin pada direktori `admin/`:
* `admin/index.php`: Dashboard analitik metrik performa konversi, total prospek, dan status WhatsApp seluruh brand.
* `admin/brands.php`: Manajemen master brand travel dan konfigurasi token Meta CAPI.
* `admin/packages.php` & `package_form.php`: Manajemen paket, harga per tipe kamar, diskon promo, sisa kuota, dan upload flyer.
* `admin/users.php`: Pembuatan akun CS dan penugasan brand.

---

## 💻 Spesifikasi Teknologi

* **Backend**: PHP 8.3 Native Modern (PDO MySQL dengan prepared statements dan strict parameter binding).
* **Frontend**: Tailwind CSS CDN & Alpine.js CDN (Reaktif, ringan, bebas dependensi build tools).
* **Komponen UI**: 100% Custom Dropdown Alpine.js monokrom (tanpa elemen `<select>` bawaan OS).
* **Database**: MySQL Server (Laragon default port 3306).
* **WhatsApp Gateway**: Node.js microservice (`@whiskeysockets/baileys`, port 3001).
* **Arsitektur Tanpa Build Tool**: **No Webpack, No Vite, No NPM untuk aplikasi web utama**. Perubahan kode langsung aktif tanpa proses kompilasi.

---

## 🛠️ Panduan Instalasi & Menjalankan Sistem

### Kebutuhan Lingkungan:
* **Laragon** (Windows) atau Apache/Nginx lokal lainnya.
* **PHP**: Versi `8.2` atau `8.3` (ekstensi `pdo_mysql`, `curl`, `mbstring`, `openssl` aktif).
* **MySQL**: Port default `3306`.
* **Node.js**: Versi `18+` atau `20+` (untuk WhatsApp Gateway).

### Langkah Menjalankan:

1. **Letakkan Proyek di Direktori Web Laragon**:
   ```bash
   C:\laragon\www\csumroh
   ```

2. **Database & Auto-Migration**:
   * Sistem dilengkapi fitur **Auto-Migration & Seeder** otomatis di `config/db.php`.
   * Cukup pastikan MySQL Laragon menyala (user: `root`, password: `""`).
   * Saat pertama kali halaman aplikasi diakses di browser, database `csumroh` beserta tabel dan data awal akan dibuat secara otomatis.

3. **Menjalankan WhatsApp Gateway (Node.js)**:
   Buka terminal di folder gateway:
   ```bash
   cd C:\laragon\www\csumroh\whatsapp-gateway
   npm install
   node server.js
   ```
   *Gateway akan aktif di port `3001`.*

4. **Mengakses Aplikasi di Browser**:
   * **URL Utama**: `http://localhost/csumroh` atau `http://csumroh.test`
   * **Halaman Login**: `http://localhost/csumroh/auth/login.php`
   * **Menghubungkan WhatsApp**: Buka menu **WhatsApp Connect** (`whatsapp_connect.php`), pilih brand, lalu pindai kode QR dengan aplikasi WhatsApp.

---

## 🔑 Kredensial Akun Bawaan (Demo)

| Akun / Pengguna | Email | Kata Sandi | Peran | Brand Penugasan | Hak Akses |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Super Admin** | `admin@csumroh.com` | `admin123` | `superadmin` | *Lintas Brand* | Master Brand, Paket, Akun CS, Log CAPI |
| **CS Fitri** | `fitri@csumroh.com` | `fitri123` | `cs` | **Hana Tours & Travel** | Shared Inbox, CRM Prospek, Copilot Chat |
| **CS Malik** | `malik@csumroh.com` | `malik123` | `cs` | **Hana Tours & Travel** | Shared Inbox, CRM Prospek, Copilot Chat |

*(Pada halaman login tersedia tombol klik cepat untuk langsung mengisi kredensial demo)*

---

## 📁 Struktur Direktori Proyek

```
csumroh/
├── admin/                     # Modul Super Admin
│   ├── brands.php             # Manajemen Master Brand & Token Meta
│   ├── index.php              # Dashboard Analitik Admin
│   ├── packages.php           # Daftar Master Paket Umroh
│   ├── package_detail.php     # Detail Spesifikasi Paket & Kamar
│   ├── package_form.php       # Form Tambah/Edit Paket & Upload Flyer
│   └── users.php              # Manajemen Akun CS & Penugasan Brand
├── api/                       # API Endpoints & Realtime Services
│   ├── prospects.php          # REST Endpoint CRM, Claim PIC, Stage Updater
│   ├── scripts.php            # Endpoint Bank Skrip Terinterpolasi
│   ├── whatsapp.php           # Controller Chat WhatsApp & Messages Loader
│   └── whatsapp_webhook.php   # Webhook Penerima Pesan Masuk Baileys
├── auth/                      # Otentikasi
│   ├── login.php              # Halaman Login Monokrom & Quick Login
│   └── logout.php             # Pembersih Sesi & Logout
├── config/
│   └── db.php                 # Koneksi PDO & Auto-Migration Database
├── conversion-chat/           # Master SOP & Kurikulum LMS (JSON)
│   ├── conversion-cycle.json  # 9 Siklus Konversi Lengkap
│   └── tgjp-framework.json    # Algoritma Penanganan Keberatan
├── data/
│   └── scripts_loader.php     # Engine Pembaca & Transformasi Bank Skrip
├── includes/                  # Komponen Antarmuka & Guards
│   ├── auth_check.php         # Guard Sesi & Hak Akses
│   ├── footer.php             # Footer Monokrom & Toast Notification
│   ├── header.php             # Header Navigasi, Sidebar & Custom Dropdowns
│   └── meta_capi.php          # Service Meta Conversions API (Graph API v20.0)
├── lms/
│   └── index.php              # Antarmuka Modul E-Learning CS
├── scripts-chat/              # Master Bank Skrip Percakapan CRO (JSON)
│   ├── greeting.json          # Sapaan & Pesan Pembuka
│   ├── identification.json    # Penggalian Kebutuhan (NPGD)
│   ├── presentation.json      # Rekomendasi Paket (MRBVA)
│   ├── closing.json           # Penguncian Seat & DP (CRA)
│   ├── objection.json         # Penanganan Keberatan (TGJP)
│   ├── followups.json         # Follow-up Terjadwal
│   └── nurturing.json         # Edukasi & Nurturing Jamaah Pasif
├── uploads/                   # Berkas Unggahan
│   ├── chat/                  # Media Obrolan WhatsApp (.gitkeep)
│   └── flyers/                # Berkas Brosur Flyer Paket
├── whatsapp-gateway/          # Microservice WhatsApp Baileys
│   ├── sessions/              # Direktori Sesi Terisolasi per Brand
│   ├── package.json           # Dependensi Node.js Baileys
│   └── server.js              # HTTP Server Gateway (Port 3001)
├── AGENTS.md                  # Panduan Konvensi Arsitektur & Rules AI
├── AUDIT_META_CAPI.md         # Dokumentasi & Audit Meta CAPI
├── chat.php                   # Halaman Live Chat WhatsApp & Shared Inbox
├── index.php                  # Halaman Workspace CS (Copilot Chat & Mini Tracker)
├── prd.md                     # Product Requirements Document
├── prospects.php              # Halaman CRM Prospek Umroh (Kanban & Table)
├── prospect_detail.php        # Halaman Profil Prospek 360° Lengkap
├── whatsapp_connect.php       # Halaman QR Scanner & Status Sesi WhatsApp
└── README.md                  # Dokumentasi Resmi Proyek
```

---

## 🎨 Konvensi Desain: Strict Monochrome & Semantic Status

* **Monokromatik Dominan**: Palet hitam (`#000000`), putih (`#ffffff`), dan abu-abu netral (`zinc-50` hingga `zinc-900`) untuk kenyamanan penggunaan jangka panjang tanpa kelelahan mata (*eye-strain reduction*).
* **Zero Native `<select>`**: Seluruh pilihan interaktif menggunakan custom dropdown Alpine.js dengan transisi halus dan desain konsisten.
* **Semantic Status Badge**: Warna fungsional terkontrol hanya diaplikasikan pada status prospek (Hijau untuk Deal Menang, Biru/Kuning/Ungu untuk Tahapan Progres, Abu-abu untuk Prospek Baru, Coret untuk Batal).

---

## 📄 Lisensi & Hak Cipta

Sistem ini dikembangkan secara eksklusif untuk operasional Customer Service Travel Umroh multi-brand. Seluruh hak cipta dilindungi undang-undang.
