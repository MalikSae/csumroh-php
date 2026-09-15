# CS Umroh Copilot & LMS (Multi-Brand Travel)

Aplikasi asisten cerdas (*copilot chat*), antarmuka obrolan WhatsApp terintegrasi (*real-time WhatsApp Web*), pelacak prospek (*mini lead tracker & CRM*), dan modul pelatihan mandiri (*e-learning LMS*) untuk Customer Service (CS) Umroh yang mengelola **5 Brand Travel Umroh** secara terisolasi dan aman.

Sistem dirancang dengan gaya **Strict Monochrome (Netral Hitam-Putih)** yang bersih, kontras tinggi, bebas distraksi, serta dilengkapi dengan warna semantik terukur pada badge status pipa konversi.

---

## 📑 Daftar Isi

1. [Fitur Utama Sistem](#-fitur-utama-sistem)
2. [Arsitektur Multi-Brand & Hak Akses](#-arsitektur-multi-brand--hak-akses)
3. [WhatsApp Web Gateway & Real-Time Engine](#-whatsapp-web-gateway--real-time-engine)
4. [Mesin Skrip & Variabel Dinamis (CRO Standar)](#-mesin-skrip--variabel-dinamis-cro-standar)
5. [Framework Penanganan Keberatan (TGJP)](#-framework-penanganan-keberatan-tgjp)
6. [Meta Conversions API (CAPI) & Atribusi Iklan](#-meta-conversions-api-capi--atribusi-iklan)
7. [Modul LMS Belajar Mandiri CS](#-modul-lms-belajar-mandiri-cs)
8. [Panduan Instalasi & Menjalankan Sistem](#-panduan-instalasi--menjalankan-sistem)
9. [Kredensial Akun Bawaan (Default Demo)](#-kredensial-akun-bawaan-default-demo)
10. [Struktur Direktori Proyek](#-struktur-direktori-proyek)

---

## 🚀 Fitur Utama Sistem

### 1. 💬 All-in-One Integrated WhatsApp Web (`chat.php`)
* **Live Chat Messenger**: Mengirim dan menerima pesan teks, gambar, rekaman suara (*voice note* audio), video, dokumen brosur, stiker, dan balasan pesan (*quote/reply*).
* **Real-Time Synchronous via Server-Sent Events (SSE)**: Pesan masuk dan keluar terupdate secara instan tanpa perlu memuat ulang halaman (*zero reload*).
* **Copilot Drawer Sisi Kanan**: Panel pembantu di sisi percakapan yang memungkinkan CS memilih skrip, menginterpolasi variabel jamaah secara otomatis, dan menyalin atau mengirimkan pesan langsung ke chat dengan satu klik.
* **Deteksi Sumber Iklan (Meta CTWA)**: Menampilkan badge referral iklan Facebook/Instagram Ads (`Ad ID`, `Campaign ID`, pesan awal pembuka iklan) langsung pada daftar chat.

### 2. ⚡ Copilot Chat & Smart Script Copier (`index.php`)
* **Bar Variabel Otomatis**: Input nama calon jamaah (misal: *Ibu Hj. Aminah*) langsung mengubah seluruh skrip di layar secara real-time.
* **Seleksi Paket & Scoping Aman**: Dropdown paket hanya memuat paket milik brand CS yang sedang bertugas.
* **1-Klik Salin & Quick WA Launcher**:
  * Tombol **"Salin Pesan"**: Menginterpolasi 19 variabel dinamis dan menyalin ke clipboard dengan animasi toast.
  * Tombol **"💬 Kirim ke WA"**: Membuka WhatsApp Web dengan teks pesan siap kirim.

### 3. 🛡️ Interactive TGJP Objection Wizard
Menghilangkan keraguan CS dalam merespons sanggahan jamaah (*"Mahal"*, *"DP-nya berat"*, *"Rembuk keluarga dulu"*, *"Ragu legalitas izin travel"*):
* **T (Terima)**: Validasi empati tanpa mendebat calon jamaah.
* **G (Gali)**: Menggali pertimbangan inti dengan pertanyaan tertutup/pilihan terarah.
* **J (Jawab)**: Memberikan perbandingan nilai rasional (bukan klaim berlebihan).
* **P (Pastikan)**: Mendorong komitmen mikro atau penguncian kuota seat.

### 4. 📊 Mini Lead Tracker & Pipeline Konversi (`prospects.php` & `prospect_detail.php`)
* **9 Status Pipa Penjualan**:
  1. `new` (Prospek Baru)
  2. `identifying` (Penggalian Kebutuhan NPGD)
  3. `offered` (Paket Diajukan)
  4. `closing` (Tahap Pembayaran / Ambil Seat)
  5. `objection` (Penanganan Keberatan TGJP)
  6. `followup` (Follow-up Terjadwal)
  7. `nurture` (Edukasi & Nurturing)
  8. `closed_won` (Closing Berhasil / DP Masuk)
  9. `closed_lost` (Batal / Hilang)
* **Kalkulasi Nilai Transaksi & Kamar (Quad/Triple/Double/Infant)**: Menghitung total estimasi transaksi dan rincian pax jamaah.
* **Timeline Log Aktivitas**: Mencatat setiap perubahan status, pengiriman skrip, dan catatan khusus CS.

### 5. 🎯 Meta Conversions API (CAPI) Terintegrasi
* Melaporkan konversi server-side ke Meta Events Manager secara aman (*hashed phone & email* SHA256).
* Trigger otomatis pada perubahan status prospek:
  * Status `identifying` $\rightarrow$ Event `Contact`
  * Status `offered` $\rightarrow$ Event `AddToCart`
  * Status `closing` $\rightarrow$ Event `InitiateCheckout`
  * Status `closed_won` $\rightarrow$ Event `Purchase` (dengan estimasi nilai Rupiah)

### 6. 🎓 LMS Belajar Mandiri CS (`lms/index.php`)
* Modul e-learning 9 materi berdasarkan siklus konversi umroh modern (`conversion-cycle.json`).
* Komparasi praktis: **Contoh Chat Salah (Don't)** vs **Contoh Chat Benar (Do)**.
* Checklist pemahaman mandiri interaktif dengan status progres lokal browser.

### 7. 👑 Super Admin Multi-Brand Panel (`admin/`)
* **Kelola Brand** (`admin/brands.php`): Konfigurasi 5 Brand Travel, legalitas PPIU Kemenag, rekening bank resmi, alamat, kontak, dan kredensial Meta Pixel/Token.
* **Kelola Paket Umroh** (`admin/packages.php`): Manajemen detail paket, harga kamar (Quad, Triple, Double), diskon promo, tanggal keberangkatan, dan upload brosur flyer.
* **Kelola CS / Pengguna** (`admin/users.php`): Pembuatan akun CS dan penugasan ke brand tertentu.

---

## 🏢 Arsitektur Multi-Brand & Hak Akses

Perusahaan menaungi **5 Brand Travel Umroh** dengan pembagian hak akses:

```
                  ┌─────────────────────────────────┐
                  │    SUPER ADMIN (Cross-Brand)    │
                  │ Akses Seluruh Data 5 Brand      │
                  └────────────────┬────────────────┘
                                   │
       ┌──────────────┬────────────┼────────────┬──────────────┐
       ▼              ▼            ▼            ▼              ▼
┌──────────────┐┌──────────────┐┌──────────────┐┌──────────────┐┌──────────────┐
│   Brand 1    ││   Brand 2    ││   Brand 3    ││   Brand 4    ││   Brand 5    │
│  Azhan Tour  ││Haramain Utama││Safwa Barakah ││Al-Fajr Insani││Madinah Makmur│
└──────┬───────┘└──────┬───────┘└──────┬───────┘└──────┬───────┘└──────┬───────┘
       │               │               │               │               │
  [CS Fitri]      [CS Rina]        [CS ...]        [CS ...]        [CS ...]
  [Paket B1]      [Paket B2]       [Paket B3]      [Paket B4]      [Paket B5]
  [Prospek B1]    [Prospek B2]     [Prospek B3]    [Prospek B4]    [Prospek B5]
  [WA Sesi B1]    [WA Sesi B2]     [WA Sesi B3]    [WA Sesi B4]    [WA Sesi B5]
```

* **Data Scoping Otomatis**: CS yang login hanya dapat melihat prospek, paket, sesi WhatsApp, dan riwayat pesan milik brand yang ditugaskan kepadanya.
* **Keamanan Finansial**: Rekening pembayaran dan nomor izin PPIU selalu diisi otomatis dari database brand CS terkait. **Meniadakan risiko salah kirim rekening antar brand.**

---

## 📱 WhatsApp Web Gateway & Real-Time Engine

Sistem menggunakan gateway Node.js mandiri berbasis library `@whiskeysockets/baileys` yang berjalan lokal di latar belakang:

* **Lokasi Gateway**: `whatsapp-gateway/server.js` (Port default: `3001`).
* **Multi-Session Isolation**: Setiap brand memiliki direktori sesi terpisah di `whatsapp-gateway/sessions/brand_{id}/`.
* **Koneksi QR Code**: Dikelola melalui antarmuka visual `whatsapp_connect.php`.
* **Webhook Sinkron**: Pesan masuk diterima oleh `api/whatsapp_webhook.php`, disimpan ke tabel MySQL `chat_messages`, dan didorong ke browser via Server-Sent Events (SSE).

---

## 📝 Mesin Skrip & Variabel Dinamis (CRO Standar)

Seluruh bank percakapan di folder `scripts-chat/` telah diaudit dan dioptimalkan dengan prinsip **Conversion Rate Optimization (CRO)**:
1. **Sudut Pandang CS Resmi**: Menghilangkan bias mitra lapangan/agen konsultan independen.
2. **Aturan Satu Pertanyaan (Single-Question Rule)**: Setiap akhir pesan hanya memuat 1 pertanyaan terarah untuk mencegah beban kognitif pada calon jamaah.
3. **Pilihan Biner Terarah**: Menggantikan pertanyaan buntu (*"Ada yang bisa dibantu?"*) dengan alternatif mikro-komitmen (*"Apakah mengutamakan jadwal keberangkatan atau kenyamanan hotel?"*).
4. **Fleksibilitas Hotel Universal**: Menghilangkan klaim kaku "pelataran masjid" $\rightarrow$ diganti dengan perbandingan rasional paket hemat vs paket premium.

### Daftar 19 Token Variabel Dinamis:

| Variabel | Deskripsi | Sumber Data |
| :--- | :--- | :--- |
| `{{nama}}` | Nama calon jamaah | Input CS / Data Prospek |
| `{{cs_name}}` | Nama CS yang sedang bertugas | Sesi Login CS |
| `{{travel}}` | Nama resmi brand travel | Master Brand |
| `{{ppiu}}` | Nomor izin PPIU Kemenag | Master Brand |
| `{{bank}}` | Nama bank transfer resmi | Master Brand |
| `{{rekening}}` | Nomor rekening resmi perusahaan | Master Brand |
| `{{nama_rekening}}` | Pemilik rekening (Atas Nama) | Master Brand |
| `{{alamat}}` | Alamat kantor pusat travel | Master Brand |
| `{{telepon}}` | Nomor kontak resmi kantor | Master Brand |
| `{{paket}}` | Nama paket umroh yang dipilih | Master Paket |
| `{{harga}}` | Harga paket umroh | Master Paket |
| `{{dp}}` | Besaran uang muka (DP) | Master Paket |
| `{{airline}}` | Maskapai penerbangan | Master Paket |
| `{{hotel}}` | Ringkasan nama hotel | Master Paket |
| `{{hotel_makkah}}` | Hotel bintang & jarak Makkah | Master Paket |
| `{{hotel_madinah}}` | Hotel bintang & jarak Madinah | Master Paket |
| `{{durasi}}` | Durasi perjalanan (hari) | Master Paket |
| `{{keberangkatan}}` | Musim / tanggal keberangkatan | Master Paket |
| `{{highlights}}` | Fasilitas unggulan paket | Master Paket |

---

## 🛡️ Framework Penanganan Keberatan (TGJP)

Bank skrip keberatan di `scripts-chat/objection.json` dikelompokkan ke dalam 4 langkah terstruktur:

```
[Keberatan Jamaah] 
       │
       ▼
1. T (Terima)    ──► "Alhamdulillah, kami sangat memahami pertimbangan Bapak/Ibu..."
       │
       ▼
2. G (Gali)      ──► "Bolehkah kami tahu, apakah kendalanya di budget atau jadwal...?"
       │
       ▼
3. J (Jawab)     ──► "Untuk alternatif yang lebih terjangkau, kami memiliki paket..."
       │
       ▼
4. P (Pastikan)  ──► "Apakah kami bantu amankan 2 seat terlebih dahulu untuk jadwal ini?"
```

---

## 🎯 Meta Conversions API (CAPI) & Atribusi Iklan

Aplikasi mengintegrasikan pelacakan konversi iklan Meta (Click-to-WhatsApp Ads):

1. **Deteksi Pesan Awal**: Saat jamaah mengklik iklan Facebook/Instagram, WhatsApp menyertakan kode rujukan iklan (`meta_referral_data`).
2. **Ekstraksi Otomatis**: Webhook mengekstrak `ctwa_clid`, `ad_id`, `headline`, dan `source_url`, lalu menautkannya ke profil prospek.
3. **Pengiriman Event CAPI**: Saat status prospek diperbarui di CRM, sistem otomatis mengirimkan HTTP POST ke endpoint Meta Graph API (`v20.0`) dengan parameter:
   * `event_name` (`Contact`, `AddToCart`, `InitiateCheckout`, `Purchase`)
   * `user_data` (Nomor telepon & email ter-hash SHA256)
   * `custom_data` (Value Rupiah, Nama Paket, Currency `IDR`)
   * `event_id` (Deduplikasi event)

---

## 🎓 Modul LMS Belajar Mandiri CS

Terletak di `lms/index.php`, modul pelatihan mandiri menyajikan 9 bab pembelajaran:
1. **Prinsip Dasar CS Umroh**: Mindset pelayan tamu Allah (*Khadimul Dhuyufurrahman*).
2. **SOP Greeting & Respons Cepat**: Waktu respons emas < 3 menit.
3. **Metode Penggalian NPGD**: *Need, Priority, Group, Decision Maker*.
4. **Presentasi Paket MRBVA**: *Meet Needs, Return Value, Benefit First*.
5. **Teknik Closing CRA**: *Choice, Scarcity, Action*.
6. **Penanganan Keberatan TGJP**: *Terima, Gali, Jawab, Pastikan*.
7. **Strategi Follow-up Berbobot**: Nilai tambah tanpa spamming.
8. **Nurturing Calon Jamaah Pasif**: Edukasi manasik & doa harian.
9. **Kepatuhan Regulasi & Etika Umroh**: PPIU Kemenag & transparansi biaya.

---

## 🛠️ Panduan Instalasi & Menjalankan Sistem

### Kebutuhan Lingkungan:
* **Laragon** (atau Apache/Nginx lokal lainnya)
* **PHP**: Versi `8.2` atau `8.3` (ekstensi `pdo_mysql`, `curl`, `mbstring`, `openssl` aktif)
* **MySQL**: Port default `3306`
* **Node.js**: Versi `18+` atau `20+` (untuk WhatsApp Gateway Baileys)

### Langkah Instalasi:

1. **Clone Repository ke Direktori Web**:
   ```bash
   cd C:\laragon\www
   git clone https://github.com/MalikSae/csumroh-php.git csumroh
   cd csumroh
   ```

2. **Database & Auto-Migration**:
   * Sistem memiliki fitur **Auto-Migration & Seeder Otomatis** pada `config/db.php`.
   * Cukup pastikan MySQL Laragon aktif (user: `root`, password: kosong `""`).
   * Saat pertama kali membuka aplikasi di browser, database `csumroh` beserta tabel dan data awal 5 brand akan otomatis dibuat.

3. **Instalasi Dependensi WhatsApp Gateway**:
   ```bash
   cd whatsapp-gateway
   npm install
   ```

4. **Menjalankan WhatsApp Gateway**:
   ```bash
   node server.js
   ```
   *Gateway akan berjalan di port `3001` (Output: `WhatsApp Gateway running on port 3001`).*

5. **Membuka Aplikasi di Browser**:
   * **URL Utama**: `http://localhost/csumroh` atau `http://csumroh.test`
   * **Halaman Login**: `http://localhost/csumroh/auth/login.php`
   * **Hubungkan WhatsApp**: Buka menu **WhatsApp Connect** (`whatsapp_connect.php`), pilih brand, dan pindai kode QR dengan aplikasi WhatsApp Anda.

---

## 🔑 Kredensial Akun Bawaan (Default Demo)

| Akun / Peran | Email | Kata Sandi | Brand Penugasan | Cakupan Akses |
| :--- | :--- | :--- | :--- | :--- |
| **Super Admin** | `admin@csumroh.com` | `admin123` | *Lintas 5 Brand* | Master Brand, Paket, Pengguna CS, Log CAPI |
| **CS Fitri** | `fitri@csumroh.com` | `fitri123` | **Azhan Tour & Travel** | Copilot Chat Brand 1, Prospek, WhatsApp Web |
| **CS Rina** | `rina@csumroh.com` | `rina123` | **Haramain Utama Wisata** | Copilot Chat Brand 2, Prospek, WhatsApp Web |

*(Pada halaman login tersedia tombol klik cepat untuk langsung mengisi kredensial demo)*

---

## 📁 Struktur Direktori Proyek

```
csumroh/
├── admin/                     # Modul Super Admin
│   ├── brands.php             # Kelola 5 Brand Travel & Kredensial Meta
│   ├── index.php              # Dashboard Analitik & Statistik Admin
│   ├── packages.php           # Daftar Master Paket Umroh
│   ├── package_detail.php     # Detail & Spesifikasi Paket Umroh
│   ├── package_form.php       # Tambah / Edit Paket & Upload Flyer
│   └── users.php              # Manajemen Akun CS & Penugasan Brand
├── api/                       # Endpoint REST & Realtime Services
│   ├── prospects.php          # AJAX CRUD & Status Updater Prospek
│   ├── scripts.php            # JSON API Bank Skrip Terinterpolasi
│   ├── whatsapp.php           # Controller Kirim Pesan & SSE Realtime Stream
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
│   ├── footer.php             # Footer & Script Helper Notifikasi
│   ├── header.php             # Navigasi Atas, Brand Scoping & Menu
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
│   └── flyers/                # Berkas Brosur Paket Umroh
├── whatsapp-gateway/          # Microservice WhatsApp Web (Baileys)
│   ├── package.json           # Dependensi Node.js (@whiskeysockets/baileys)
│   └── server.js              # HTTP Gateway, WebSocket & Multi-Session Handler
├── AGENTS.md                  # Panduan Konvensi Arsitektur & Rules AI Agent
├── AUDIT_META_CAPI.md         # Dokumentasi & Audit Teknis Meta CAPI
├── chat.php                   # Halaman Utama Terintegrasi WhatsApp Web CS
├── index.php                  # Halaman CS Workspace (Copilot Chat & Quick Copy)
├── prd.md                     # Product Requirements Document Asli
├── prospects.php              # Halaman Manajemen Pipa Prospek (CRM)
├── prospect_detail.php        # Halaman Profil Prospek & Riwayat Lengkap
├── whatsapp_connect.php       # Halaman QR Scanner & Status Sesi WhatsApp
└── README.md                  # Dokumentasi Proyek Ini
```

---

## 🎨 Konvensi Desain: Strict Monochrome & Semantic Status

* **Monokromatik Dominan**: Memastikan sistem nyaman digunakan selama jam kerja panjang tanpa melelahkan mata (*eye-strain reduction*).
* **Tipografi Bersih**: Memanfaatkan font sans-serif modern dengan kontras tinggi (`text-zinc-900` di atas `bg-white` dan `bg-zinc-50`).
* **Semantic Status Badge**: Warna aksen fungsional hanya digunakan pada badge pipa status prospek (Hijau untuk Deal Menang, Biru/Kuning untuk Follow-up aktif, Abu-abu untuk Prospek Baru, Merah/Coret untuk Batal).

---

## 📄 Lisensi & Hak Cipta

Sistem ini dikembangkan secara eksklusif untuk operasional Customer Service Travel Umroh multi-brand. Seluruh hak cipta dilindungi undang-undang.
