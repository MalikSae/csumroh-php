# Laporan Audit Integrasi Meta Conversions API (CAPI) & Click-to-WhatsApp (CTWA)

**Proyek**: CS Umroh Copilot & LMS (Multi-Brand)  
**Tanggal Audit**: 14 September 2026  
**Status**: Analisis Selesai & Divalidasi Terhadap Database & Source Code Riil  
**Target Sistem**: WhatsApp Gateway (Baileys v7), Webhook Inbound, Pipeline Prospek CRM, dan Meta Graph API v19.0 Client  

---

## 1. Ringkasan Eksekutif (Executive Summary)

Audit ini dilakukan untuk mengevaluasi kesiapan teknis, keandalan atribusi, dan kepatuhan sistem pelacakan konversi iklan Meta (**Click-to-WhatsApp Ads / CTWA**) yang bermuara pada pengiriman event konversi (**Purchase / DP Umroh**) melalui **Meta Conversions API (CAPI)**.

### Ringkasan Status:
* **Infrastruktur Dasar**: Telah tersedia tabel penyimpanan kredensial per brand (`brands`), tabel riwayat pengiriman (`meta_capi_logs`), penangkap konteks referral iklan di gateway ([whatsapp-gateway/server.js](file:///c:/laragon/www/csumroh/whatsapp-gateway/server.js)), dan helper pengirim CAPI ([includes/meta_capi.php](file:///c:/laragon/www/csumroh/includes/meta_capi.php)).
* **Temuan Utama**: Ditemukan **2 bug berstatus Kritis (P1)** pada ekstraksi data referral iklan dan pemetaan parameter ID klik (`ctwa_clid`), serta **4 temuan berstatus Penting (P2)** terkait standarisasi payload Meta Business Messaging dan skor pencocokan kualitas event (*Event Match Quality / EMQ*).
* **Kondisi Database Saat Ini**: Kedua brand aktif (*Hana Tours & Travel* dan *Nava Tours & Travel*) belum memiliki nilai Pixel ID dan Access Token di tabel `brands`. Sudah terdapat pesan masuk dengan konteks iklan CTWA di tabel `chat_messages`, namun atribut `ctwa_clid` dan `source_id` belum terserap dengan benar karena masalah parsing protobuf.

---

## 2. Arsitektur & Alur Kerja Pelacakan (End-to-End Workflow)

```
[Calon Jamaah Melihat Iklan CTWA di FB / IG]
                    │
                    │ Klik tombol "Kirim Pesan WhatsApp"
                    ▼
       [WhatsApp Inbound Message]
                    │
                    ▼
   [whatsapp-gateway/server.js (Node.js/Baileys)]
        ├── Mendeteksi pesan masuk & contextInfo.referral / externalAdReply
        └── Mengirim POST payload ke Webhook PHP
                    │
                    ▼
     [api/whatsapp_webhook.php]
        ├── Menyimpan riwayat pesan ke tabel `chat_messages` (JSON meta_referral_data)
        └── Membuat / memperbarui data prospek di tabel `prospects`
                    │
                    ▼
     [CS Melakukan Edukasi & Closing di Chat CRM]
                    │
                    │ Jamaah membayar DP Umroh -> CS mengubah status ke "closed_won"
                    ▼
        [api/prospects.php]
                    │
                    ▼
     [includes/meta_capi.php (send_meta_purchase_event)]
        ├── Mengambil Pixel ID, Access Token, Page ID milik Brand
        ├── Normalisasi & Hash E.164 Nomor HP (SHA-256)
        ├── Mengirimkan Event Purchase ke Meta Graph API v19.0
        └── Mencatat status HTTP & respons payload ke tabel `meta_capi_logs`
```

---

## 3. Komponen Sistem Terkait

| Komponen | File / Tabel | Peran & Tanggung Jawab |
|---|---|---|
| **Gateway Socket** | [whatsapp-gateway/server.js](file:///c:/laragon/www/csumroh/whatsapp-gateway/server.js) | Menangkap event WhatsApp Baileys, membaca objek `contextInfo.referral` / `externalAdReply`. |
| **Inbound Webhook** | [api/whatsapp_webhook.php](file:///c:/laragon/www/csumroh/api/whatsapp_webhook.php) | Memvalidasi secret gateway, auto-create prospek baru, menyimpan marker iklan CTWA. |
| **CAPI Engine** | [includes/meta_capi.php](file:///c:/laragon/www/csumroh/includes/meta_capi.php) | Format payload Meta Graph API, hashing privasi SHA-256, pengiriman cURL, dan audit logging. |
| **Pipeline Trigger** | [api/prospects.php](file:///c:/laragon/www/csumroh/api/prospects.php) | Memicu `send_meta_purchase_event()` saat prospek berubah status menjadi `closed_won`. |
| **UI Master Brand** | [admin/brands.php](file:///c:/laragon/www/csumroh/admin/brands.php) | Form konfigurasi Meta Pixel ID, Access Token (System User), dan Facebook Page ID. |
| **Audit Log DB** | `meta_capi_logs` | Tabel pencatat setiap upaya pengiriman (event_id, payload, response_status, response_body). |

---

## 4. Rincian Temuan Audit Teknis

### 🔴 Temuan 1: Properti Baileys Protobuf Menggunakan CamelCase (Kritis - P1)
* **File**: [whatsapp-gateway/server.js:140-165](file:///c:/laragon/www/csumroh/whatsapp-gateway/server.js#L140-L165)
* **Akar Masalah**:  
  Pada baris fungsi `extractAdReferral(msg)`:
  ```javascript
  ctwa_clid: referral?.ctwa_clid || null,
  source_id: referral?.source_id || null,
  source_url: referral?.source_url || null
  ```
  Di dalam spesifikasi library `@whiskeysockets/baileys` (`WAProto.proto` entitas `ExternalAdReplyInfo` dan `CtwaContextData`), properti hasil decode protobuf menggunakan penamaan **camelCase**:
  - `referral?.ctwaClid` atau `externalAdReply?.ctwaClid`
  - `referral?.sourceId` atau `externalAdReply?.sourceId`
  - `referral?.sourceUrl` atau `externalAdReply?.sourceUrl`
* **Bukti di Database Riil (`chat_messages`)**:
  ```json
  {
    "body": "Alasan 80% Advertiser Ganti ke Akun Whitelist...",
    "headline": "Konsultasi Gratis Sekarang Juga",
    "ctwa_clid": null,
    "source_id": null,
    "source_url": "https://fb.me/8dWuLNhzk",
    "source_type": "ad"
  }
  ```
* **Dampak**: Parameter `ctwa_clid` dan `source_id` selalu terekstrak sebagai `null`, sehingga peluang atribusi berbasis ID klik langsung menjadi hilang.

---

### 🔴 Temuan 2: Fallback `ctwa_clid` Mengirim Teks Judul Iklan ke Meta (Kritis - P1)
* **File**: [api/whatsapp_webhook.php:400](file:///c:/laragon/www/csumroh/api/whatsapp_webhook.php#L400) & [includes/meta_capi.php:92-94](file:///c:/laragon/www/csumroh/includes/meta_capi.php#L92-L94)
* **Akar Masalah**:  
  Di webhook:
  ```php
  $marker = $metaReferral['ctwa_clid'] ?? $metaReferral['source_id'] ?? $metaReferral['headline'] ?? null;
  $db->prepare("UPDATE prospects SET meta_referral_marker = ? WHERE id = ?")->execute([$marker, $prospectId]);
  ```
  Kemudian di `meta_capi.php`:
  ```php
  if (!empty($prospect['meta_referral_marker'])) {
      $userData['ctwa_clid'] = $prospect['meta_referral_marker'];
  }
  ```
* **Dampak**:  
  Jika `ctwa_clid` null, sistem mengambil string headline (misalnya `"Konsultasi Gratis Sekarang Juga"`), lalu mengirim string teks tersebut ke Meta CAPI pada key `ctwa_clid`.  
  Meta API akan menolak atribusi atau memberikan peringatan error karena format `ctwa_clid` bukanlah teks bebas melainkan token ID klik iklan resmi Meta.

---

### 🟡 Temuan 3: Data Iklan Tidak Tersimpan ke Prospek Saat `history_sync` (Sedang - P2)
* **File**: [api/whatsapp_webhook.php:565-570](file:///c:/laragon/www/csumroh/api/whatsapp_webhook.php#L565-L570)
* **Akar Masalah**:  
  Pada blok sinkronisasi riwayat obrolan (`history_sync`), sistem membuat data prospek baru jika belum ada di database, namun query insert **tidak menyertakan kolom `meta_referral_marker`**, meskipun pesan riwayat tersebut memiliki `meta_referral_data`.
* **Bukti di Database**:  
  Prospek ID 6 dan ID 94 tercatat berasal dari iklan CTWA di tabel `chat_messages` (ID 19 & 3709), namun pada tabel `prospects`, kolom `meta_referral_marker`, `ad_id`, dan `campaign_id` bernilai kosong (`NULL`).

---

### 🟡 Temuan 4: `action_source` Belum Sesuai Standar Meta Business Messaging (Penting - P2)
* **File**: [includes/meta_capi.php:114](file:///c:/laragon/www/csumroh/includes/meta_capi.php#L114)
* **Akar Masalah**:  
  Sistem saat ini mengirimkan:
  ```php
  'action_source' => 'other',
  ```
  Berdasarkan dokumentasi resmi *Meta Conversions API for Business Messaging* (CTWA), nilai standar yang direkomendasikan adalah:
  - `'action_source' => 'business_messaging'`
  - `'messaging_channel' => 'whatsapp'`
* **Dampak**:  
  Penggunaan `'other'` dapat menurunkan efektivitas machine learning Meta dalam menghubungkan percakapan WhatsApp dengan kampanye Click-to-WhatsApp di Ads Manager.

---

### 🟡 Temuan 5: Skor Event Match Quality (EMQ) Masih Sub-Optimal (Penting - P2)
* **File**: [includes/meta_capi.php:80-95](file:///c:/laragon/www/csumroh/includes/meta_capi.php#L80-L95)
* **Akar Masalah**:  
  Objek `user_data` saat ini hanya mengirimkan satu parameter pengguna:
  ```php
  $userData['ph'] = [hash_for_meta($e164)];
  ```
* **Peluang Optimalisasi**:  
  Skor EMQ Meta Events Manager dapat ditingkatkan secara drastis dengan melengkapi parameter pencocokan:
  1. `fn`: SHA-256 dari nama depan prospek (diekstrak dari kolom `prospects.name`).
  2. `ln`: SHA-256 dari nama belakang prospek (jika nama > 1 kata).
  3. `external_id`: SHA-256 dari identifier unik prospek internal (misal: `csumroh_prospect_{id}`).
  4. `page_id`: ID Halaman Facebook resmi brand (unhashed).

---

### 🟡 Temuan 6: Kelengkapan Struktur E-Commerce `custom_data` (Penting - P2)
* **File**: [includes/meta_capi.php:100-106](file:///c:/laragon/www/csumroh/includes/meta_capi.php#L100-L106)
* **Akar Masalah**:  
  Untuk event standar `Purchase`, Meta sangat merekomendasikan menyertakan array `contents` di samping parameter `value` dan `currency`. Saat ini array `contents` belum disertakan.

---

### 🟢 Temuan 7: Belum Tersedia Pengujian Real-Time (`test_event_code`) (Fitur - P3)
* **Akar Masalah**:  
  Meta Events Manager menyediakan fitur **Test Events Tool** yang memerlukan parameter `test_event_code` (misalnya `TEST12345`).  
  Saat ini belum ada mekanisme input kode uji coba maupun tombol pengujian koneksi (*Ping Test*) di halaman Admin Master Brand untuk memverifikasi apakah Access Token dan Pixel ID valid sebelum sistem digunakan live.

---

### ℹ️ Temuan 8: Status Konfigurasi Database Lokal Saat Ini
Pemeriksaan langsung pada tabel `brands`:
* **Hana Tours & Travel (ID: 1)**:  
  `meta_pixel_id`: `NULL` | `meta_access_token`: `NULL` | `facebook_page_id`: `NULL`
* **Nava Tours & Travel (ID: 6)**:  
  `meta_pixel_id`: `NULL` | `meta_access_token`: `NULL` | `facebook_page_id`: `NULL`
* **Tabel `meta_capi_logs`**: Belum ada rekaman (0 rows) karena event Purchase belum pernah terpicu dengan kredensial aktif.

---

## 5. Matriks Rekomendasi Solusi & Tindakan Perbaikan

| Prioritas | Komponen | Rekomendasi Tindakan |
|---|---|---|
| 🔴 **P1 (Kritis)** | [whatsapp-gateway/server.js](file:///c:/laragon/www/csumroh/whatsapp-gateway/server.js) | Perbaiki pembacaan protobuf Baileys dengan fallback camelCase: `referral?.ctwaClid \|\| externalAdReply?.ctwaClid` dan `sourceId`. |
| 🔴 **P1 (Kritis)** | [api/whatsapp_webhook.php](file:///c:/laragon/www/csumroh/api/whatsapp_webhook.php) | Pisahkan penyimpanan: simpan `ctwa_clid` hanya jika string valid ID klik. Simpan judul/headline iklan ke kolom `ad_id` / catatan, bukan ke `ctwa_clid`. |
| 🟡 **P2 (Penting)** | [includes/meta_capi.php](file:///c:/laragon/www/csumroh/includes/meta_capi.php) | Perbarui payload Meta CAPI: ubah `action_source` ke `business_messaging` dan tambahkan `messaging_channel => 'whatsapp'`. |
| 🟡 **P2 (Penting)** | [includes/meta_capi.php](file:///c:/laragon/www/csumroh/includes/meta_capi.php) | Tambahkan hashing parameter EMQ: `fn` (First Name), `ln` (Last Name), `external_id`, serta array e-commerce `contents`. |
| 🟡 **P2 (Penting)** | [api/whatsapp_webhook.php](file:///c:/laragon/www/csumroh/api/whatsapp_webhook.php) | Pastikan proses `history_sync` menyerap marker iklan CTWA dan memperbarui data prospek terkait. |
| 🟢 **P3 (Fitur)** | [admin/brands.php](file:///c:/laragon/www/csumroh/admin/brands.php) | Tambahkan tombol **"Uji Koneksi Meta CAPI"** (Test Ping) dan field input `test_event_code` untuk pengujian live di Meta Events Manager. |
| 🟢 **P3 (Fitur)** | [admin/](file:///c:/laragon/www/csumroh/admin/) | Buat halaman / modal audit log CAPI agar Super Admin dapat melihat riwayat pengiriman event, respons API Meta, dan diagnosa error. |

---

## 6. Panduan Implementasi Perbaikan (Code Reference)

### A. Perbaikan Ekstraksi di `whatsapp-gateway/server.js`
```javascript
function extractAdReferral(msg) {
  try {
    const ext = msg.message?.extendedTextMessage;
    const contextInfo = ext?.contextInfo || msg.messageContextInfo;
    if (!contextInfo) return null;

    const referral = contextInfo.referral || null;
    const externalAdReply = contextInfo.externalAdReply || null;

    if (referral || externalAdReply) {
      const ctwaClid = referral?.ctwaClid || referral?.ctwa_clid || externalAdReply?.ctwaClid || null;
      const sourceId = referral?.sourceId || referral?.source_id || externalAdReply?.sourceId || null;
      const sourceUrl = referral?.sourceUrl || referral?.source_url || externalAdReply?.sourceUrl || null;

      return {
        headline: referral?.headline || externalAdReply?.title || null,
        body: referral?.body || externalAdReply?.body || null,
        source_type: referral?.sourceType || referral?.source_type || 'ad',
        source_id: sourceId,
        source_url: sourceUrl,
        ctwa_clid: ctwaClid,
        media_url: externalAdReply?.mediaUrl || null,
        thumbnail_url: externalAdReply?.thumbnailUrl || null
      };
    }
  } catch (e) {}
  return null;
}
```

### B. Perbaikan Payload di `includes/meta_capi.php`
```php
// 1. Ekstraksi nama untuk EMQ tinggi
$rawName = trim($prospect['name'] ?? '');
$nameParts = explode(' ', $rawName);
$firstName = $nameParts[0] ?? '';
$lastName = count($nameParts) > 1 ? end($nameParts) : '';

$userData = [
    'ph' => [hash_for_meta(normalize_phone_for_meta($prospect['phone']))],
    'external_id' => [hash_for_meta('prospect_' . $prospectId)]
];

if (!empty($firstName)) {
    $userData['fn'] = [hash_for_meta($firstName)];
}
if (!empty($lastName)) {
    $userData['ln'] = [hash_for_meta($lastName)];
}
if (!empty($brand['facebook_page_id'])) {
    $userData['page_id'] = trim($brand['facebook_page_id']);
}
// Kirim ctwa_clid hanya jika benar-benar ID klik yang valid
if (!empty($prospect['ctwa_clid'])) {
    $userData['ctwa_clid'] = trim($prospect['ctwa_clid']);
}

// 2. Event Data Standar Business Messaging
$eventData = [
    'event_name' => 'Purchase',
    'event_time' => $now,
    'event_id' => $eventId,
    'action_source' => 'business_messaging',
    'messaging_channel' => 'whatsapp',
    'user_data' => $userData,
    'custom_data' => [
        'currency' => 'IDR',
        'value' => $dpValue,
        'content_name' => 'DP Umroh - ' . $packageName,
        'content_type' => 'product',
        'contents' => [
            [
                'id' => (string)($prospect['package_id'] ?? 'dp_umroh'),
                'quantity' => 1,
                'item_price' => $dpValue
            ]
        ]
    ]
];
```

---

*Laporan audit ini disusun sebagai acuan teknis perbaikan sistem pelacakan konversi iklan Meta Umroh pada proyek CS Umroh.*
