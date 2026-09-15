# Product Requirements Document (PRD)
# CS Umroh Copilot, CRM Enterprise & WhatsApp Shared Inbox (Multi-Brand)

## 1. Executive Summary & Problem Statement

### 1.1 Background
Perusahaan mengelola **5 Brand Travel Umroh**. Dalam operasional harian, tim Customer Service internal (seperti CS Fitri dan CS Malik di Hana Tours) bertugas menangani *inbound leads* (calon jamaah) dari WhatsApp, iklan Meta Ads (*Click-to-WhatsApp*), dan formulir website resmi.

Sebelum sistem ini dibangun:
1. Skrip percakapan masih berorientasi pada sudut pandang agen/mitra lapangan dan sulit diakses cepat saat CS melayani chat yang padat.
2. Risiko kesalahan pengiriman nomor rekening bank atau nama izin PPIU antar brand sangat tinggi jika CS menangani lebih dari satu brand.
3. Obrolan WhatsApp terisolasi di perangkat fisik masing-masing CS tanpa visibilitas tim (*no shared inbox*), menyulitkan pembagian beban kerja (*lead distribution*) dan pengalihan (*takeover*) saat CS berhalangan.
4. Tidak adanya CRM umroh terstandarisasi untuk kualifikasi kebutuhan jamaah (NPGD), pembagian tipe kamar (Quad/Triple/Double/Infant), pemantauan sisa kuota seat, dan pencatatan riwayat follow-up.
5. Konversi closing di WhatsApp tidak terhubung balik ke analitik iklan Meta (CAPI).

### 1.2 Objective
Membangun sistem operasi CS Umroh terpadu yang **minimalis, modern, fungsional, dan berdampak tinggi (*high-impact*)** untuk:
1. **Copilot Chat & Smart Script Copier**: Membantu CS membalas chat secara instan dengan 19 variabel dinamis terinterpolasi otomatis dan 1-klik salin/kirim ke WhatsApp.
2. **Integrated WhatsApp Web & Multi-CS Shared Inbox**: Mengintegrasikan obrolan langsung WhatsApp via gateway Baileys dengan pembagian peran, klaim penanggung jawab (*CS PIC*), auto-takeover saat membalas, dan penyeimbangan beban prospek baru (*balanced distribution*).
3. **Enterprise CRM Pipeline 360°**: Visualisasi pipa penjualan 8 tahap (Kanban & Rich Table) lengkap dengan kalkulasi kamar pax, estimasi deal value, status dokumen paspor/vaksin, dan riwayat aktivitas prospek.
4. **Interactive TGJP Objection Wizard**: Membimbing CS mengatasi keberatan biaya, jadwal, keluarga, atau legalitas izin secara sistematis.
5. **Meta Conversions API (CAPI) Server-Side**: Mengirimkan event konversi umroh (*Contact, AddToCart, InitiateCheckout, Purchase*) secara otomatis ke Meta Events Manager.
6. **LMS Belajar Mandiri**: Modul e-learning 9 bab kurikulum konversi umroh terstruktur.
7. **Super Admin Multi-Brand**: Manajemen master 5 Brand Travel, paket umroh multi-kamar, dan manajemen akun CS.

---

## 2. Design System & Aesthetic Guidelines

### 2.1 Style: Strict Neutral Monochrome (Hitam-Putih Netral)
Antarmuka menerapkan gaya **netral hitam-putih murni** yang bersih, kontras tinggi, bebas distraksi warna-warni mencolok, dan ramah digunakan selama jam kerja panjang (*eye-strain reduction*).

* **Palet Warna**:
  * Body/Canvas: `#FFFFFF` (bg-white)
  * Panel / Card / Sidebar: `#FAFAFA` (zinc-50) & `#F4F4F5` (zinc-100)
  * Border & Divider: `#E4E4E7` (border-zinc-200) & `#D4D4D8` (border-zinc-300)
  * Teks Utama / Heading: `#000000` (text-black) / `#18181B` (text-zinc-900)
  * Teks Sekunder / Body: `#3F3F46` (text-zinc-700) / `#71717A` (text-zinc-500)
* **Buttons & Badges**:
  * Primary Button: `bg-black text-white hover:bg-zinc-800`
  * Secondary / Outline: `border border-zinc-300 text-zinc-800 hover:bg-zinc-100 bg-white`
  * Status Badges (Semantic Monochrome):
    * Closed Won: `bg-emerald-600 text-white`
    * Progress / Follow-up / Closing: Border kontras `border-zinc-800 bg-white text-zinc-900` atau aksen fungsional terukur
    * New / Pending: `bg-zinc-100 text-zinc-700`
    * Lost / Batal: `bg-zinc-200 text-zinc-600 line-through`
* **Zero Native `<select>` Policy**:
  * Seluruh dropdown formulir dan filter **WAJIB menggunakan Custom Dropdown Alpine.js** monokrom dengan animasi transisi halus dan penutup luar (`@click.outside`).
* **Layout Rule**:
  * Seluruh halaman dirancang responsif dan nyaman digunakan dalam mode **Split-Screen (50% layar)** berdampingan dengan aplikasi lain.

---

## 3. User Personas & Roles

| Role | Tanggung Jawab Utama | Lingkup Akses |
| :--- | :--- | :--- |
| **Super Admin** | Manajemen entitas bisnis pusat & analitik | Akses lintas 5 brand (*cross-brand*), kelola master brand & kredensial Meta, kelola master paket umroh, manajemen akun CS, monitoring seluruh prospek & sesi WhatsApp. |
| **Customer Service (CS)** | Konsultasi jamaah, follow-up, & closing | Terisolasi pada 1 brand penugasan, kolaborasi multi-CS (*shared inbox*), klaim PIC prospek, kirim obrolan WhatsApp, akses Copilot skrip & CRM pipeline, akses LMS. |

---

## 4. Functional Requirements

### 4.1 Autentikasi & Multi-Brand Scoping
* **REQ-AUTH-01**: Login berbasis email dan password dengan perlindungan sesi.
* **REQ-AUTH-02**: Pemisahan hak akses strictly antara `superadmin` dan `cs`.
* **REQ-AUTH-03**: Saat CS login, seluruh variabel brand (Nama Travel, Izin PPIU, Rekening Bank) dan pilihan paket otomatis terisolasi sesuai brand CS tersebut. Meniadakan risiko salah rekening atau salah brand.
* **REQ-AUTH-04 (Multi-CS Collaboration)**: Beberapa CS dapat di-assign ke brand yang sama (misal: Fitri dan Malik di Hana Tours). Semua CS pada brand tersebut dapat melihat prospek dan percakapan brand secara transparan.

### 4.2 WhatsApp Web Live Chat & Shared Inbox (`chat.php`)
* **REQ-CHAT-01 (Messaging Engine)**: Mengirim dan menerima pesan teks, foto, dokumen brosur PDF, rekaman audio (*voice note*), video, stiker, reaksi emoji, dan balasan kutipan pesan.
* **REQ-CHAT-02 (Multi-Session Brand Gateway)**: Microservice Node.js Baileys mengelola sesi terisolasi per brand pada port 3001 (`whatsapp-gateway/sessions/brand_{id}`).
* **REQ-CHAT-03 (Multi-Identifier Auto-Select)**: Saat CS membuka livechat dari tautan prospek, sistem otomatis mencocokkan dan memilih percakapan berdasarkan `remote_jid` (`@lid` / `@s.whatsapp.net`), `prospect_id`, atau 9 digit nomor telepon.
* **REQ-CHAT-04 (Auto-Stub New Chat)**: Jika kontak prospek baru belum memiliki riwayat obrolan di WhatsApp, sistem otomatis membuat *stub chat* agar CS bisa langsung mengirim pesan pembuka.
* **REQ-CHAT-05 (Split Dual Panel)**: Layar obrolan dilengkapi panel kanan yang memuat detail CRM prospek dan drawer skrip Copilot tanpa berpindah halaman.
* **REQ-CHAT-06 (CS PIC Management & Auto-Takeover)**:
  * Menampilkan nama CS PIC penanggung jawab kontak di header panel kanan.
  * Tombol `[Ambil Alih]` untuk klaim prospek.
  * Saat CS membalas chat WhatsApp, sistem otomatis mengubah `prospects.user_id` menjadi ID CS tersebut.
* **REQ-CHAT-07 (Deteksi Iklan Meta CTWA)**: Menampilkan badge referral iklan Facebook/Instagram Ads (`Ad ID`, `Campaign ID`, pesan awal) pada obrolan.

### 4.3 Enterprise CRM Pipeline & Prospek Umroh (`prospects.php`)
* **REQ-CRM-01 (8 Tahapan Konversi)**:
  1. `new` (Prospek Baru)
  2. `identifying` (Identifikasi Kebutuhan NPGD)
  3. `offered` (Penawaran Paket)
  4. `objection` (Keberatan / TGJP)
  5. `followup` (Follow-up Terjadwal)
  6. `closing` (Closing / Pengamanan Kuota Seat)
  7. `closed_won` (Deal Menang / DP Masuk)
  8. `closed_lost` (Batal / Lost)
* **REQ-CRM-02 (Dual View Switcher)**:
  * **Kanban Board**: Kartu prospek horizontal dengan kalkulator nilai deal per kolom, badge sumber lead, domisili kota, status follow-up, CS PIC, dan tombol geser tahap instan.
  * **Rich Table List**: Tampilan tabel komprehensif dengan avatar, paket, kamar pax, nominal deal, dropdown ubah status, CS PIC dengan tombol `[Klaim]`, tombol langsung ke Live Chat WhatsApp, dan tombol ekspor CSV.
* **REQ-CRM-03 (Filter Toolbar Monokrom)**:
  * Search box instan (nama, nomor WA, domisili, catatan).
  * Quick Filter Pills: *Semua*, *🔥 High Intent*, *⏰ Follow-up Hari Ini*, *🏆 Closed Won*, *Baru*.
  * Secondary Custom Dropdowns: Filter Paket, Filter Sumber Lead, dan Filter Tim CS PIC (*Semua Tim CS, Milik Saya, atau per nama CS*).
* **REQ-CRM-04 (Klaim PIC Kolaboratif)**: Tombol `[Klaim PIC]` tersedia pada kartu Kanban dan baris tabel untuk prospek yang belum dipegang oleh CS yang sedang login.

### 4.4 Profil Prospek 360° Lengkap (`prospect_detail.php`)
* **REQ-DET-01 (Kualifikasi NPGD)**: Formulir pencatatan *Need, Priority, Group, Decision Maker*, dan kebutuhan khusus (kursi roda, lansia, dll).
* **REQ-DET-02 (Rincian Kamar Pax & Deal Calculator)**: Pengaturan pax kamar (*Quad, Triple, Double, Infant*) yang secara dinamis menghitung total estimasi transaksi berdasarkan paket yang dipilih.
* **REQ-DET-03 (Kesiapan Dokumen Jamaah)**: Status paspor (*Sudah Ada, Proses Buat, Perlu Perpanjang, Belum Ada*) dan status vaksinasi meningitis.
* **REQ-DET-04 (Rekapitulasi Finansial)**: Input nominal DP masuk, tanggal bayar DP, status pembayaran (*unpaid, partial_dp, paid_full*), dan sisa tagihan.
* **REQ-DET-05 (Timeline Log Aktivitas)**: Riwayat kronologis otomatis atas setiap aksi follow-up, perubahan status, pengalihan PIC CS, dan pengiriman event Meta CAPI.
* **REQ-DET-06 (Inline TGJP Objection Scripts)**: Tab kurasi skrip rekomendasi penanganan keberatan langsung pada halaman profil.

### 4.5 Copilot Chat Workspace (`index.php`)
* **REQ-COP-01 (19 Variabel Terinterpolasi)**: Menggantikan seluruh placeholder skrip secara real-time (`{{nama}}`, `{{cs_name}}`, `{{travel}}`, `{{ppiu}}`, `{{bank}}`, `{{rekening}}`, `{{paket}}`, `{{harga}}`, `{{dp}}`, `{{airline}}`, `{{hotel_makkah}}`, dll).
* **REQ-COP-02 (1-Klik Salin & Quick WA)**: Tombol salin ke clipboard dengan notifikasi toast, serta tombol langsung buka WhatsApp Web.
* **REQ-COP-03 (Standarisasi CRO)**: Narasi skrip menerapkan *Single-Question Rule*, pilihan biner terarah, dan sudut pandang CS resmi perusahaan.
* **REQ-COP-04 (TGJP Objection Wizard)**: Alur terpandu 4 langkah: Terima $\rightarrow$ Gali $\rightarrow$ Jawab $\rightarrow$ Pastikan.

### 4.6 Meta Conversions API (CAPI) Terintegrasi
* **REQ-CAPI-01**: Trigger otomatis pengiriman HTTP POST ke Meta Graph API (`v20.0`) saat status prospek berubah:
  * `identifying` $\rightarrow$ `Contact`
  * `offered` $\rightarrow$ `AddToCart`
  * `closing` $\rightarrow$ `InitiateCheckout`
  * `closed_won` $\rightarrow$ `Purchase`
* **REQ-CAPI-02**: Nomor telepon dan email calon jamaah di-hash dengan standar SHA256 sebelum dikirimkan.
* **REQ-CAPI-03**: Seluruh payload dan respons Meta dicatat pada tabel `meta_capi_logs`.

### 4.7 Modul E-Learning LMS Mandiri CS (`lms/index.php`)
* **REQ-LMS-01**: Modul 9 bab materi pelatihan konversi umroh interaktif.
* **REQ-LMS-02**: Komparasi chat salah (*Don't*) vs chat benar (*Do*).
* **REQ-LMS-03**: Checklist pemahaman mandiri interaktif dengan penyimpanan progres lokal.

### 4.8 Super Admin Management (`admin/`)
* **REQ-ADM-01 (Kelola Brand Travel)**: Form master 5 brand travel, izin PPIU, bank resmi, dan kredensial Meta Pixel/Token.
* **REQ-ADM-02 (Kelola Paket Umroh)**: Form master paket umroh, tanggal keberangkatan, harga bertingkat kamar (Quad, Triple, Double, Infant), sisa kuota seat, dan upload flyer brosur.
* **REQ-ADM-03 (Kelola Akun CS)**: Pembuatan akun CS dan penugasan ke brand tertentu.

---

## 5. Technical Architecture & Constraints

* **Runtime**: PHP 8.3 Native Modern di lingkungan Laragon (Windows).
* **Database**: MySQL 8.0 (PDO dengan Prepared Statements dan strict parameter binding).
* **Frontend**: Tailwind CSS CDN + Alpine.js CDN.
* **Build Tool Policy**: **TIDAK MENGGUNAKAN BUILD TOOLS (No Webpack, No Vite, No NPM untuk aplikasi web utama)**.
* **WhatsApp Gateway**: Microservice Node.js 18+/20+ berbasis `@whiskeysockets/baileys` (port 3001).
* **UI Constraint**: **Strict Monochrome** murni dan **100% Custom Dropdown Alpine.js** (tanpa `<select>` bawaan OS).

---

## 6. Success Metrics & Key Results (OKRs)

1. **Efisiensi Waktu CS**: Kecepatan merespons chat calon jamaah meningkat 3x lipat dengan fitur Smart Copier, multi-identifier WhatsApp launcher, dan template CRO.
2. **Akurasi Bisnis Multi-Brand**: 0% kesalahan penulisan nomor rekening DP atau salah nama travel pada seluruh brand perusahaan.
3. **Kolaborasi Tim Tanpa Gesekan**: 100% prospek memiliki kejelasan CS PIC dengan kemampuan ambil alih dan auto-takeover saat merespons.
4. **Optimalisasi Iklan Meta**: Event *Purchase* dan *Contact* terkirim otomatis ke Meta CAPI secara real-time untuk akurasi optimasi algoritma iklan berbayar.
5. **Onboarding Cepat CS Baru**: CS baru mampu memahami SOP konversi umroh melalui modul LMS dalam waktu < 2 jam.
