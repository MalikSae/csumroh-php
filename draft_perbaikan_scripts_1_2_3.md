# Draft Usulan Perbaikan Skrip Chat (Temuan 1, 2, dan 3)
## CS Umroh Copilot Multi-Brand

**Status Dokumen:** SELESAI DITERAPKAN KE MASTER JSON (`scripts-chat/*.json`)  
**Tanggal:** 15 September 2026  
**Auditor & Penyusun:** AI Agent & Sales Conversion Specialist  
**Dasar Acuan:** `AGENTS.md` (Aturan Desain & Standarisasi CS) & Hasil Audit Bahasa & CRO

---

### Ringkasan Eksekutif Draft Perbaikan

Dokumen ini memuat usulan naskah perbaikan kata demi kata (*word-by-word*) untuk **59 skrip chat** yang teridentifikasi mengalami kelemahan pada Audit Bahasa & CRO:

1. **Perbaikan 1 (12 Skrip):** Eliminasi Bias Keagenan / Makelar $\rightarrow$ Menjadi Representasi Customer Service Resmi Travel.
2. **Perbaikan 2 (33 Skrip):** Eliminasi Dead-End Chat (CTA Pasif / Buntu) $\rightarrow$ Menjadi Active Low-Friction Binary CTA.
3. **Perbaikan 3 (14 Skrip):** Penguraian Pertanyaan Bertumpuk (Cognitive Overload) $\rightarrow$ Menjadi Single-Question Rule & Micro-Commitment.

---

# BAGIAN 1: PERBAIKAN BIAS KEAGENAN (12 SKRIP)
> **Tujuan:** Menghilangkan persepsi bahwa jamaah dihubungi oleh "agen/makelar lepas" atau "perantara". Mengubah seluruh narasi menjadi suara **Customer Service Resmi {{travel}}** yang memiliki otoritas, empati, dan kredibilitas tinggi.

---

### 1. `greeting_agent_outbound_lead_01`
* **Kategori / Judul:** Sapa Jamaah Baru dari Web / Link Iklan
* **Konteks Penggunaan:** Calon jamaah baru mengisi form minat di landing page / website resmi travel.
* **Teks Lama (Before):**
  > *"Assalamu'alaikum Kak {{nama}} 😊 Salam kenal, saya {{agent_name}}, konsultan umroh dari {{travel}}. Saya melihat Kakak tertarik dengan informasi umroh kami. Salam silaturahmi ya Kak, insyaAllah saya siap bantu berikan rincian paket yang paling sesuai dengan rencana Kakak sekeluarga."*
* **Draft Usulan Baru (After):**
  > *"Assalamu'alaikum Kak {{nama}} 😊 Terima kasih telah menghubungi layanan resmi **{{travel}}**. Saya **{{cs_name}}** dari tim layanan jamaah. Menindaklanjuti data formulir yang Kakak isi di website kami, apakah Kakak sedang mencari paket keberangkatan dalam waktu dekat ini atau untuk musim liburan nanti, Kak?"*
* **Rationale CRO:**
  - Menghapus `{{agent_name}}` $\rightarrow$ ganti `{{cs_name}}`.
  - Menegaskan *"layanan resmi {{travel}}"*.
  - Mengubah penutup pasif (*"siap bantu berikan rincian..."*) menjadi pertanyaan pilihan biner yang sangat mudah dijawab (*"waktu dekat atau musim liburan"*).

---

### 2. `greeting_agent_personal_network_01`
* **Kategori / Judul:** Menyapa Kenalan / Kerabat (Personal Network)
* **Konteks Penggunaan:** CS menyapa kontak warm/relasi yang sudah ada di database.
* **Teks Lama (Before):**
  > *"Assalamu'alaikum Kak {{nama}} 😊 Semoga sehat dan berkah selalu sekeluarga. Kebetulan saat ini saya menjadi konsultan perwakilan resmi di {{travel}}. Jika Kakak atau keluarga ada niat ibadah umroh dalam waktu dekat, insyaAllah saya siap bantu carikan jadwal, paket, dan bimbingan terbaik ya Kak."*
* **Draft Usulan Baru (After):**
  > *"Assalamu'alaikum Kak {{nama}} 😊 Semoga berkah dan sehat selalu sekeluarga. Senang bisa menyapa Kakak kembali. Sekadar mengabarkan, saat ini saya bertugas di layanan konsultasi resmi **{{travel}}**. Semisal Kakak atau keluarga ada rencana ibadah umroh di tahun ini, boleh saya bantu kirimkan jadwal keberangkatan terbarunya, Kak?"*
* **Rationale CRO:**
  - Mengganti *"konsultan perwakilan resmi"* menjadi *"bertugas di layanan konsultasi resmi {{travel}}"*.
  - Menutup dengan tawaran konkret berizin (*permission-based CTA*): *"boleh saya bantu kirimkan jadwal...?"*.

---

### 3. `greeting_general_01`
* **Kategori / Judul:** Greeting Umum (Prospek hanya mengucap salam)
* **Konteks Penggunaan:** Prospek baru mengirim "Assalamu'alaikum".
* **Teks Lama (Before):**
  > *"Wa'alaikumussalam Kak {{nama}} 😊 Saya {{agent_name}}, konsultan umroh dari {{travel}}. InsyaAllah saya siap dampingi ya. Kakak sedang cari informasi umroh untuk keberangkatan kapan?"*
* **Draft Usulan Baru (After):**
  > *"Wa'alaikumussalam Kak {{nama}} 😊 Selamat datang di layanan resmi **{{travel}}**. Saya **{{cs_name}}** yang siap membantu rencana ibadah Kakak. Boleh tahu, Kakak sedang mencari informasi paket untuk keberangkatan di bulan apa?"*
* **Rationale CRO:**
  - Memposisikan kantor travel resmi sejak detik pertama.
  - Bahasa lebih hangat, fokus langsung pada target bulan.

---

### 4. `greeting_general_02`
* **Kategori / Judul:** Prospek Hanya Mengirim Sapaan Singkat / Halo
* **Konteks Penggunaan:** Prospek hanya mengirim "Halo kak" atau "P".
* **Teks Lama (Before):**
  > *"Halo Kak {{nama}} 😊 Saya {{agent_name}}, mitra konsultan umroh dari {{travel}}. Boleh, saya siap dampingi. Lagi cari info paket umroh ya, Kak?"*
* **Draft Usulan Baru (After):**
  > *"Halo Kak {{nama}} 😊 Terima kasih sudah menghubungi **{{travel}}**. Saya **{{cs_name}}**, siap mendampingi rencana ibadah Kakak. Boleh tahu, Kakak tertarik dengan paket umroh promo hemat atau paket yang fasilitasnya lebih nyaman/premium, Kak?"*
* **Rationale CRO:**
  - Menghapus kata *"mitra konsultan"*.
  - Memberikan binary framing universal yang realistis (*paket hemat vs fasilitas lebih nyaman/premium*) tanpa mengasumsikan lokasi hotel tertentu.

---

### 5. `greeting_info_01`
* **Kategori / Judul:** Prospek Minta Info Umroh
* **Konteks Penggunaan:** Prospek mengatakan "Mau tanya info umroh kak".
* **Teks Lama (Before):**
  > *"Siap Kak {{nama}} 😊 Saya {{agent_name}}, konsultan umroh dari {{travel}}. InsyaAllah siap saya bantu carikan pilihan terbaik. Rencananya ingin berangkat kapan, Kak?"*
* **Draft Usulan Baru (After):**
  > *"Siap dengan senang hati Kak {{nama}} 😊 Saya **{{cs_name}}** dari Customer Service resmi **{{travel}}**. Agar saya bisa pilihkan paket yang paling pas, rencananya Kakak ingin berangkat di kisaran bulan apa?"*
* **Rationale CRO:**
  - Memberikan *reason-why* (*"Agar saya bisa pilihkan paket yang paling pas..."*) sebelum mengajukan pertanyaan bulan.

---

### 6. `greeting_first_timer_01`
* **Kategori / Judul:** Pertama Kali Umroh
* **Konteks Penggunaan:** Prospek mengaku belum pernah umroh dan masih awam.
* **Teks Lama (Before):**
  > *"Boleh banget Kak 😊 Saya {{agent_name}} dari {{travel}}. Nggak apa-apa kalau masih mulai cari info, saya bantu dampingi pelan-pelan ya. Rencananya ada target berangkat kapan?"*
* **Draft Usulan Baru (After):**
  > *"Alhamdulillah, berkah niat baiknya Kak {{nama}} 😊 Saya **{{cs_name}}** dari tim bimbingan jamaah **{{travel}}**. Tenang saja Kak, kami siap dampingi dan jelaskan seluruh prosesnya dari nol sampai selesai di Tanah Suci. Untuk rencana awal, Kakak berniat berangkat sendiri atau bersama keluarga tercinta?"*
* **Rationale CRO:**
  - Menghilangkan kesan keagenan, mengedepankan tim bimbingan travel.
  - Untuk jamaah pertama kali, pertanyaan *"sendiri atau bersama keluarga"* jauh lebih mudah dijawab daripada menentukan tanggal pasti.

---

### 7. `greeting_referral_01`
* **Kategori / Judul:** Prospek dari Rekomendasi Jamaah (Referral)
* **Konteks Penggunaan:** Prospek menyebut mendapat kontak dari jamaah alumni (misal Bu Rina).
* **Teks Lama (Before):**
  > *"MasyaAllah, iya Kak 😊 Terima kasih sudah menghubungi saya. Saya {{agent_name}} konsultan umroh resmi {{travel}}. Bu Rina alhamdulillah pernah berangkat bersama kami. Kakak sendiri sedang cari keberangkatan untuk kapan?"*
* **Draft Usulan Baru (After):**
  > *"MasyaAllah, salam hangat untuk keluarga ya Kak 😊 Terima kasih sudah menghubungi **{{travel}}**. Saya **{{cs_name}}**, betul sekali alhamdulillah Bu Rina adalah jamaah kami yang telah mempercayakan perjalanan ibadahnya bersama {{travel}}. Untuk rencana ibadah Kakak sendiri, apakah ada rencana berangkat di periode yang sama dengan beliau?"*
* **Rationale CRO:**
  - Memperkuat *social proof* dan kebanggaan bahwa alumni travel puas.
  - Membangun keterikatan emosional melalui kesamaan rencana.

---

### 8. `greeting_referral_02`
* **Kategori / Judul:** Referral Tanpa Menyebut Nama
* **Konteks Penggunaan:** Prospek bilang "Dapat nomor ini dari teman".
* **Teks Lama (Before):**
  > *"Siap Kak 😊 Terima kasih sudah menghubungi saya. Saya {{agent_name}} konsultan umroh {{travel}}. InsyaAllah siap bantu dampingi rencana ibadahnya. Kakak sedang cari info paket atau jadwal keberangkatan?"*
* **Draft Usulan Baru (After):**
  > *"Alhamdulillah, terima kasih banyak ya Kak sudah menghubungi layanan resmi **{{travel}}** 😊 Saya **{{cs_name}}** yang siap melayani Kakak. Biar saya bisa siapkan informasi yang paling sesuai, Kakak ingin rekomendasi jadwal keberangkatan terdekat atau paket promo khusus, Kak?"*
* **Rationale CRO:**
  - Menghapus bias keagenan perorangan.
  - Pilihan biner: *"jadwal terdekat atau promo khusus"*.

---

### 9. `greeting_social_media_01`
* **Kategori / Judul:** Prospek dari Media Sosial (IG / TikTok)
* **Konteks Penggunaan:** Prospek chat setelah melihat postingan medsos resmi travel.
* **Teks Lama (Before):**
  > *"MasyaAllah, terima kasih sudah berkunjung dan menyapa ya Kak 😊 Saya {{agent_name}} konsultan umroh {{travel}}. Ada info paket atau rencana keberangkatan yang sedang Kakak cari?"*
* **Draft Usulan Baru (After):**
  > *"MasyaAllah, terima kasih sudah menyapa akun resmi **{{travel}}** ya Kak {{nama}} 😊 Saya **{{cs_name}}** dari tim layanan jamaah. Apakah Kakak tertarik dengan paket yang tadi Kakak lihat di postingan media sosial kami, atau sedang mencari jadwal keberangkatan lain?"*
* **Rationale CRO:**
  - Langsung mengaitkan dengan konten yang dilihat prospek sehingga alur relevan (*seamless context*).

---

### 10. `greeting_ad_01`
* **Kategori / Judul:** Prospek dari Iklan Berbayar (Meta / Google Ads)
* **Konteks Penggunaan:** Prospek klik tombol "Kirim Pesan" dari iklan.
* **Teks Lama (Before):**
  > *"Siap Kak {{nama}} 😊 Saya {{agent_name}} mitra konsultan resmi {{travel}}. Terima kasih sudah menghubungi saya. Biar saya bantu carikan yang paling pas, Kakak sudah ada rencana berangkat kapan?"*
* **Draft Usulan Baru (After):**
  > *"Assalamu'alaikum Kak {{nama}} 😊 Terima kasih telah menghubungi WhatsApp resmi **{{travel}}**. Saya **{{cs_name}}**, siap memberikan rincian promo yang Kakak lihat di iklan kami. Untuk kuota promonya, rencananya Kakak ingin cek untuk keberangkatan berapa orang, Kak?"*
* **Rationale CRO:**
  - Menghapus istilah fatal *"mitra konsultan"*.
  - Memvalidasi promo iklan dan langsung menanyakan jumlah jamaah (*seat calculation hook*).

---

### 11. `greeting_trust_01`
* **Kategori / Judul:** Prospek Langsung Tanya Keamanan Travel
* **Konteks Penggunaan:** Pertanyaan awal jamaah mencerminkan kecemasan (*"Travelnya aman nggak?"*).
* **Teks Lama (Before):**
  > *"Wajar banget ditanyakan, Kak. Saya {{agent_name}} konsultan resmi di {{travel}}. Nanti saya kirimkan detail izin PPIU resmi dan rekam jejak travel kami yang bisa Kakak cek langsung ke Kemenag. Sebelumnya Kakak pernah punya pengalaman kurang nyaman dengan travel atau sedang hati-hati memilih?"*
* **Draft Usulan Baru (After):**
  > *"Sangat wajar dan tepat sekali Kakak menanyakan hal ini di awal. Ini perjalanan ibadah mulia. Kami di **{{travel}}** adalah penyelenggara resmi berizin Kemenag RI dengan nomor **PPIU: {{ppiu}}**. Seluruh transaksi dilakukan melalui rekening giro resmi perusahaan atas nama PT, bukan perorangan. Apakah Kakak ingin saya kirimkan tautan pengecekan izin resmi kami di website Kemenag RI sekarang, Kak?"*
* **Rationale CRO:**
  - Tidak lagi berposisi sebagai *"konsultan perorangan yang nanti akan kirim izin"*.
  - Langsung menyodorkan bukti hukum (No PPIU, Rekening PT) dan memberikan CTA aksi satu langkah: *"Mau saya kirim link cek Kemenag sekarang?"*.

---

### 12. `greeting_script_bank` (Metadata & Guidelines)
* **Kategori:** Modul Metadata `greeting.json`
* **Teks Lama (Before):**
  > *"deskripsi": "Kumpulan script pembuka percakapan untuk agen/konsultan umroh saat menyapa atau merespons calon jamaah baru."*  
  > *"tujuan": "Menyambut calon jamaah, memperkenalkan diri sebagai konsultan/mitra agen resmi travel..."*
* **Draft Usulan Baru (After):**
  > *"deskripsi": "Kumpulan script pembuka percakapan resmi Customer Service (CS) Umroh saat menyambut calon jamaah baru dari berbagai kanal inbound resmi travel."*  
  > *"tujuan": "Menyambut calon jamaah dengan hangat dan profesional, memperkenalkan otoritas travel resmi, membangun rasa aman instan, lalu memandu percakapan menuju tahap Identifikasi Kebutuhan (NPGD)."*

---

# BAGIAN 2: PERBAIKAN DEAD-END CHAT & CTA BUNTU (33 SKRIP)
> **Tujuan:** Menghilangkan kalimat penutup datar (*"kabari saya ya"*, *"nanti kita lanjut lagi"*, *"silakan dipelajari"*). Setiap pesan **wajib berujung pada pemicu respon aktif (Active Hook)** yang meminta komitmen mikro tanpa friksi.

---

### KELOMPOK A: PENANGANAN KEBERATAN TGJP (`objection.json`) - 15 SKRIP

#### 1. `obj_harga_mahal_01` (Jawab: `budget_tidak_cukup`)
* **Before:** *"Kalau begitu jangan dipaksakan ke paket ini, Kak. Saya coba lihat dulu pilihan yang lebih dekat dengan budget Kakak."*
* **After:** *"Sangat kami pahami Kak, jangan sampai rencana ibadah ini membebani keuangan keluarga. Kami ada opsi paket yang lebih efisien namun tetap nyaman. Mau saya pilihkan opsi paket hemat yang selisihnya Rp 3-5 juta lebih terjangkau, Kak?"*
* **CRO Rationale:** Mengganti pernyataan pasif dengan penawaran solusi spesifik berangka dan berizin (*permission question*).

#### 2. `obj_harga_mahal_01` (Jawab: `belum_melihat_value`)
* **Before:** *"Kalau kebutuhan Kakak tadi memang {{fasilitas_utama}}, perbedaannya paling terasa di bagian itu. Jadi bukan hanya selisih harganya, tapi manfaat yang Kakak dapat selama perjalanan."*
* **After:** *"Betul Kak, selisih biayanya berbanding lurus dengan kualitas fasilitas dan ketenangan ibadah Kakak sekeluarga. Di paket ini, hotel **{{hotel}}** dan fasilitas **{{fasilitas_utama}}** kami pilihkan khusus agar jamaah tidak kelelahan fisik dan bisa fokus ibadah dengan nyaman. Jika melihat kemudahan dan fasilitas yang didapatkan, apakah fasilitas seperti ini memang yang Kakak prioritaskan?"*
* **CRO Rationale:** Menjelaskan value kenyamanan secara elegan dan relevan dengan variabel dinamis tanpa membuat klaim jarak kaku yang belum tentu ada di semua paket.

#### 3. `obj_harga_mahal_01` (Jawab: `bandingkan_harga`)
* **Before:** *"Boleh dibandingkan, Kak. Supaya adil, coba kita lihat paket yang setara dari hotel, maskapai, durasi, dan fasilitasnya."*
* **After:** *"Tentu sangat boleh Kak, umroh memang harus dipilih dengan teliti. Agar perbandingannya berimbang, pastikan travel pembanding sudah include asuransi, visa resmi, dan tiket pesawat direct tanpa transit lama ya Kak. Boleh tahu, paket pembanding yang Kakak lihat di harga kisaran berapa agar kita bisa bedah bersama?"*
* **CRO Rationale:** Menjadi konsultan objektif yang memandu standar perbandingan, lalu meminta angka pembanding.

#### 4. `obj_harga_mahal_01` (Jawab: `dp_terlalu_besar`)
* **Before:** *"Kalau yang berat di pembayaran awal, saya bantu cek dulu skema pembayaran yang memang tersedia untuk paket ini."*
* **After:** *"Untuk pembayaran awal, di travel kami ada skema komitmen booking yang sangat fleksibel agar seat tidak hangus. Boleh tahu, nominal yang paling nyaman untuk Kakak siapkan hari ini di angka berapa?"*
* **CRO Rationale:** Menggiring langsung ke negosiasi nominal yang realistis bagi prospek.

#### 5. `obj_harga_mahal_01` (Jawab: `paket_tidak_sesuai`)
* **Before:** *"Berarti mungkin paket ini memang terlalu tinggi dari kebutuhan Kakak. Saya coba carikan pilihan yang lebih pas."*
* **After:** *"Berarti paket ini fiturnya melebihi dari yang Kakak butuhkan saat ini. Bagaimana kalau saya buatkan rekomendasi paket standar kami yang fasilitas pokoknya tetap lengkap tapi harganya jauh lebih ekonomis, Kak?"*
* **CRO Rationale:** Menghilangkan kata negatif *"terlalu tinggi"*, menawarkan paket standar dengan pertanyaan persetujuan.

#### 6. `obj_dp_berat_01` (Jawab: `cashflow`)
* **Before:** *"Saya bantu cek dulu apakah untuk paket ini ada skema pembayaran yang lebih nyaman."*
* **After:** *"Untuk membantu kelancaran cashflow Kakak, pelunasan sisa biaya sebenarnya bisa dicicil bertahap hingga 1 bulan sebelum jadwal terbang. Apakah skema cicilan bertahap seperti ini terasa lebih meringankan buat Kakak?"*
* **CRO Rationale:** Mengangkat fitur cicilan bertahap dan menutup dengan pertanyaan kepuasan solusi.

#### 7. `obj_dp_berat_01` (Jawab: `belum_yakin`)
* **Before:** *"Kalau begitu sebelum bicara DP, kita selesaikan dulu bagian yang masih bikin Kakak ragu."*
* **After:** *"Betul Kak, jangan transfer DP sebelum hati Kakak benar-benar mantap 100%. Dari penjelasan paket **{{paket}}** tadi, bagian mana yang sebenarnya masih membuat Kakak menimbang-nimbang?"*
* **CRO Rationale:** Membangun integritas travel (*"jangan transfer sebelum mantap"*) dan menggali keraguan tersembunyi.

#### 8. `obj_dp_berat_01` (Jawab: `menunggu_dana`)
* **Before:** *"Baik Kak. Kalau dananya diperkirakan siap di {{tanggal}}, saya follow-up lagi di waktu itu ya."*
* **After:** *"Baik Kak, semoga Allah lancarkan rezeki Kakak sekeluarga. Agar jadwal keberangkatan ini tidak terlewat dan kuotanya tidak habis diambil jamaah lain, boleh saya catat tanggal {{tanggal}} sebagai jadwal pengingat untuk Kakak?"*
* **CRO Rationale:** Mengunci izin follow-up dengan sentuhan doa dan alasan ketersediaan kuota.

#### 9. `obj_belum_ada_dana_01` (Jawab: `belum_prioritas`)
* **Before:** *"Baik Kak, berarti belum perlu dipaksakan sekarang. Nanti kalau waktunya sudah lebih pas, kita bisa lanjut lagi."*
* **After:** *"Sangat kami pahami Kak, ibadah umroh adalah panggilan hati dan kesiapan. Jika boleh saya simpan nomor Kakak, apakah Kakak berkenan saya kirimkan update info promo berkala sewaktu-waktu ada program tabungan umroh dari travel kami?"*
* **CRO Rationale:** Mengonversi lost prospect menjadi nurture subscriber yang sah.

#### 10. `obj_belum_ada_dana_01` (Jawab: `dana_belum_tersedia`)
* **Before:** *"Kita bisa simpan dulu rencananya. Nanti mendekati waktu yang Kakak targetkan saya bantu cek pilihan terbaru."*
* **After:** *"InsyaAllah niat baiknya sudah dicatat pahala oleh Allah ya Kak. Untuk target rencana ibadah Kakak, kira-kira proyeksi yang lebih memungkinkan di akhir tahun ini atau awal musim tahun depan, Kak?"*
* **CRO Rationale:** Mengunci rentang waktu proyeksi untuk pipeline nurture CRM.

#### 11. `obj_belum_ada_dana_01` (Jawab: `harga_di_luar_kemampuan`)
* **Before:** *"Kalau masih ingin berangkat, saya coba lihat dulu apakah ada pilihan yang lebih dekat dengan kemampuan Kakak."*
* **After:** *"Di {{travel}} kami memiliki program umroh hemat serba lengkap. Kalau ada paket di kisaran Rp 25 - 28 jutaan yang resmi dan amanah, apakah Kakak ingin saya buatkan simulasinya?"*
* **CRO Rationale:** Memberikan jangkar harga konkret (*Rp 25-28 jt*) dan meminta persetujuan pembuatan simulasi.

#### 12. `obj_diskusi_pasangan_01` (Jawab: `keputusan_bersama`)
* **Before:** *"Saya bisa bantu rangkum paket, harga, tanggal, dan DP-nya supaya lebih mudah Kakak diskusikan."*
* **After:** *"Tepat sekali Kak, keputusan ibadah bersama pasangan insyaAllah membawa berkah. Saya buatkan ringkasan 1 lembar format PDF/teks yang memuat hotel, maskapai, dan rincian biaya yang mudah dibaca beliau ya. Mau saya kirimkan sekarang Kak?"*
* **CRO Rationale:** Menghilangkan friksi diskusi keluarga dengan membuat rangkuman instan dan CTA kirim sekarang.

#### 13. `obj_diskusi_pasangan_01` (Jawab: `pasangan_belum_tahu`)
* **Before:** *"Kalau diperlukan, saya juga bisa bantu jelaskan langsung saat Kakak dan pasangan sama-sama senggang."*
* **After:** *"Semisal pasangan Kakak ingin mendengar penjelasan langsung mengenai fasilitas hotel dan garansi kepastian visa dari kami, kami siap bantu lewat voice note atau telepon singkat. Kira-kira waktu santai beliau biasanya di malam hari atau akhir pekan, Kak?"*
* **CRO Rationale:** Menawarkan bantuan aktif dan bertanya waktu senggang yang nyaman.

#### 14. `obj_diskusi_keluarga_01` (Jawab: `keputusan_keluarga`)
* **Before:** *"Saya bantu siapkan ringkasan paketnya supaya informasinya lebih mudah dibahas bersama."*
* **After:** *"Tentu Kak, ibadah sekeluarga memang perlu dimusyawarahkan. Saya bantu rincikan biaya per orang dan fasilitas kamar (Double/Triple/Quad) agar mudah dibagikan ke grup keluarga ya. Berapa orang kira-kira yang kemungkinan besar ikut, Kak?"*
* **CRO Rationale:** Membantu spesifikasi kamar keluarga dan menanyakan jumlah jamaah potensial.

#### 15. `obj_diskusi_keluarga_01` (Jawab: `jadwal_keluarga`)
* **Before:** *"Kalau masalahnya jadwal, nanti setelah keluarga sepakat periodenya saya bantu cari pilihan yang paling pas."*
* **After:** *"Menyesuaikan jadwal cuti kerja dan libur sekolah anak-anak memang butuh pencocokan Kak. Di travel kami ada 2 opsi favorit: keberangkatan awal liburan atau akhir liburan. Keluarga Kakak kira-kira lebih leluasa di opsi yang mana?"*
* **CRO Rationale:** Menyajikan 2 opsi solusi waktu liburan daripada membiarkan diskusi keluarga mengambang.

---

### KELOMPOK B: FOLLOW-UP BUNTU & PASIF (`followups.json`) - 15 SKRIP

#### 16. `fu_after_greeting_01` (Tidak membalas setelah greeting)
* **Before:** *"Assalamu'alaikum Kak {{nama}}, salam silaturahmi ya 😊 Kemarin kita sempat berdiskusi soal rencana ibadah umroh bersama {{travel}}. Kalau masih ingin tanya-tanya jadwal atau info paket, saya siap bantu dampingi ya Kak."*
* **After:** *"Assalamu'alaikum Kak {{nama}} 😊 Semoga aktivitas hari ini lancar. Melanjutkan obrolan kita kemarin di layanan resmi **{{travel}}**, apakah Kakak masih ingin melihat jadwal keberangkatan bulan **{{bulan}}**, atau ada bulan lain yang ingin dicek?"*
* **CRO Rationale:** Menyebut nama bulan spesifik dan memberikan pilihan biner bulan lain.

#### 17. `fu_after_initial_question_02` (Permudah prospek menjawab)
* **Before:** *"Kalau tanggalnya belum pasti nggak apa-apa, Kak. Kisaran bulan atau periodenya saja sudah cukup supaya saya bisa bantu arahkan."*
* **After:** *"Tidak apa-apa Kak semisal tanggal pastinya belum fix. Untuk rencana awal, Kakak lebih condong berangkat sebelum bulan Ramadan atau setelah Idul Fitri nanti, Kak?"*
* **CRO Rationale:** Memberikan patokan momen spiritual (sebelum Ramadan vs setelah Idul Fitri) yang sangat mudah dipilih.

#### 18. `fu_after_ask_price_02` (Cari kisaran budget)
* **Before:** *"Kalau yang kemarin belum masuk, boleh kasih kisaran budget yang nyaman buat Kakak. Saya coba lihat apakah ada pilihan yang lebih pas."*
* **After:** *"Semisal paket kemarin belum sesuai dengan budget yang Kakak anggarkan, kami ada beberapa alternatif paket promo. Apakah kisaran budget di bawah Rp 30 juta per orang yang saat ini Kakak cari?"*
* **CRO Rationale:** Menyebut batas angka psikologis (*di bawah 30 juta*) sehingga prospek cukup menjawab "Ya" atau "Bukan".

#### 19. `fu_after_identification_02` (Gunakan konteks terakhir)
* **Before:** *"Kemarin Kakak cari yang {{paket}} dan lebih mengutamakan kenyamanan ya. Kalau rencananya masih sama, saya lanjut carikan yang paling mendekati."*
* **After:** *"Kemarin Kakak mencari paket yang mengutamakan kenyamanan untuk **{{paket}}** ya. Kebetulan ketersediaan seat dan alokasi kamar untuk jadwal tersebut baru saja diperbarui oleh tim operasional kami. Boleh saya kirimkan foto fasilitas kamar dan rincian lengkapnya sekarang, Kak?"*
* **CRO Rationale:** Menghadirkan urgensi ketersediaan seat terbaru dan meminta izin kirim materi visual (*visual hook*) tanpa asumsi kaku posisi hotel.

#### 20. `fu_after_comparison_02` (Bantu prospek memilih paket)
* **Before:** *"Kalau masih bingung, sebutkan satu hal yang paling penting buat Kakak. Nanti kita pilih paket dari situ."*
* **After:** *"Memilih paket terbaik memang butuh ketelitian Kak. Dari dua pilihan kemarin, mana yang lebih membuat Kakak tenang: **efisiensi biayanya** atau **kenyamanan fasilitas hotel & maskapainya**, Kak?"*
* **CRO Rationale:** Menyederhanakan kebingungan menjadi pilihan biner harga vs fasilitas/kenyamanan.

#### 21. `fu_promised_dp_02` (Ada kendala saat janji transfer DP)
* **Before:** *"Kak, kalau ada kendala di proses DP-nya boleh kabari saya ya. Biar saya tahu apakah perlu dibantu atau memang rencananya berubah."*
* **After:** *"Kak {{nama}}, izin konfirmasi apakah ada kendala di nomor rekening atau limit transfer bank hari ini? Jika butuh dibantu split pembayaran atau invoice resmi perusahaan, saya siap bantu sekarang Kak."*
* **CRO Rationale:** Langsung mendiagnosis kendala teknis perbankan dan menawarkan solusi split payment.

#### 22. `fu_waiting_promo_01` (Prospek menunggu promo khusus)
* **Before:** *"Kak {{nama}}, rencana umrohnya masih ada ya? Saya catat kemarin Kakak menunggu pilihan yang lebih sesuai budget."*
* **After:** *"Kabar gembira Kak {{nama}}! Travel kami baru saja merilis program promo potongan DP khusus pendaftaran minggu ini. Apakah Kakak masih memprioritaskan keberangkatan di bulan **{{bulan}}**?"*
* **CRO Rationale:** Hook berita gembira + konfirmasi bulan target.

#### 23. `fu_seat_update_01` (Update ketersediaan seat)
* **Before:** *"Kak {{nama}}, izin update untuk keberangkatan {{tanggal}} yang kemarin kita bahas. Saat ini tersisa {{seat}} seat. Kalau rencananya masih lanjut, kabari saya ya."*
* **After:** *"Kak {{nama}}, sekadar mengabarkan amanah kuota: jadwal keberangkatan **{{tanggal}}** saat ini tersisa **{{seat}} seat** lagi karena baru ada rombongan yang booking. Agar seat Kakak tidak tergeser, apakah nama Kakak mau saya kunci sementara dalam antrean hari ini?"*
* **CRO Rationale:** Menghapus frasa terlarang *"kabari saya ya"*, menggantinya dengan penawaran kunci antrean sementara tanpa biaya (*zero-risk booking hold*).

#### 24. `fu_relevant_promo_01` (Ada promo yang relevan dengan budget)
* **Before:** *"Kak {{nama}}, saya ingat kemarin Kakak cari di kisaran budget tertentu. Sekarang ada {{promo}} yang cukup mendekati. Kalau masih ada rencana umroh, saya kirim detailnya ya."*
* **After:** *"Kak {{nama}}, saya ingat kebutuhan budget Kakak kemarin. Hari ini baru saja masuk alokasi paket promo **{{promo}}** yang selisihnya sangat pas dengan budget Kakak. Mau saya kirimkan brosur resminya sekarang sebelum kuotanya penuh?"*
* **CRO Rationale:** Menciptakan urgensi halus dan aksi instan (*"Mau saya kirim sekarang?"*).

#### 25. `fu_discussion_spouse_02` (Cari tahu respon pasangan)
* **Before:** *"Kalau boleh tahu, pasangan masih mempertimbangkan bagian apa? Kalau ada yang perlu saya jelaskan, saya bantu."*
* **After:** *"Bagaimana respon pasangan Kakak setelah melihat rincian paketnya? Apakah beliau lebih menyoroti soal tanggal keberangkatannya atau rincian biayanya, Kak?"*
* **CRO Rationale:** Mempersempit keberatan pasangan ke dua faktor utama (tanggal vs biaya).

#### 26. `fu_nurture_short_01` (Nurture prospek jangka pendek)
* **Before:** *"Assalamu'alaikum Kak {{nama}}, izin menyapa lagi. Rencana umroh yang kemarin bagaimana, sudah mulai memungkinkan untuk kita lanjutkan?"*
* **After:** *"Assalamu'alaikum Kak {{nama}} 😊 Semoga selalu dilimpahkan kelapangan rezeki. Menyambung rencana ibadah Kakak tempo hari, apakah di bulan ini kondisinya sudah lebih memungkinkan untuk kita pilihkan jadwal keberangkatan?"*
* **CRO Rationale:** Bahasa lebih santun, mendoakan, dan fokus pada kelayakan bulan ini.

#### 27. `fu_nurture_long_01` (Nurture prospek jangka panjang)
* **Before:** *"Assalamu'alaikum Kak {{nama}}, dulu sempat cerita punya target umroh sekitar {{bulan}}. Rencananya masih sama?"*
* **After:** *"Assalamu'alaikum Kak {{nama}} 😊 Semoga sehat berkah sekeluarga. Dulu Kakak sempat merencanakan umroh untuk periode bulan **{{bulan}}**. Mengingat pendaftaran musim tersebut sudah mulai dibuka di kantor kami, apakah rencana Kakak masih tetap di periode tersebut?"*
* **CRO Rationale:** Menghubungkan pembukaan pendaftaran resmi kantor dengan komitmen lama prospek.

#### 28. `fu_thinking_01` (Prospek pamit mau pikir-pikir dulu)
* **Before:** *"Assalamu'alaikum Kak {{nama}}, kemarin minta waktu untuk pertimbangkan dulu. Sekarang bagaimana, masih lanjut dipertimbangkan?"*
* **After:** *"Assalamu'alaikum Kak {{nama}} 😊 Semoga hari ini menyenangkan. Sekadar memastikan agar rencana ibadah Kakak tidak menggantung, setelah dipertimbangkan kemarin, apakah paket ini sudah terasa pas di hati atau ingin dicarikan alternatif tanggal lain, Kak?"*
* **CRO Rationale:** Pertanyaan biner: *"sudah pas di hati atau ingin alternatif tanggal lain"*.

#### 29. `fu_waiting_document_01` (Menunggu kepengurusan paspor)
* **Before:** *"Assalamu'alaikum Kak {{nama}}, paspornya sudah ada perkembangan? Kalau sudah siap kita bisa lanjut lihat jadwal keberangkatannya lagi."*
* **After:** *"Assalamu'alaikum Kak {{nama}}, bagaimana kabar pengurusan paspornya di kantor imigrasi, apakah sudah selesai foto atau masih menunggu jadwal antrean M-Paspor Kak?"*
* **CRO Rationale:** Memahami proses imigrasi teknis sehingga CS terdengar berpengalaman dan membantu.

#### 30. `fu_final_status_03` (Penutupan sopan setelah tanpa respon bertahap)
* **Before:** *"Baik Kak {{nama}}, saya tidak akan sering follow-up lagi ya. Kalau suatu waktu rencana umrohnya sudah siap, silakan chat saya kapan saja. InsyaAllah saya bantu."*
* **After:** *"Assalamu'alaikum Kak {{nama}} 🙏 Khawatir Kakak sedang ada agenda keluarga yang padat sehingga belum sempat merespons, saya izin jeda follow-up agar tidak mengganggu Kakak ya. Nanti jika kerinduan ke Tanah Suci sudah siap diwujudkan, pintu kantor **{{travel}}** selalu terbuka untuk Kakak sekeluarga. Semoga sehat dan berkah selalu 😊"*
* **CRO Rationale:** Teknik *Permission to Close* yang sangat menyentuh hati dan terbukti memicu *spontaneous reply rate* tertinggi.

---

### KELOMPOK C: CLOSING DATAR & TANPA ACTION HOOK (`closing.json`) - 3 SKRIP

#### 31. `closing_dp_01` (Closing Pengamanan Seat)
* **Before:** *"Kalau semuanya sudah cocok, Kak, seat-nya bisa kita amankan dulu dengan DP {{dp}}."*
* **After:** *"Alhamdulillah jika seluruh rincian paketnya sudah cocok di hati Kakak. Untuk mengunci seat dan harga promo ini agar tidak berubah, boleh saya bantu buatkan form reservasi resminya sekarang Kak? Cukup dengan DP awal **{{dp}}**."*
* **CRO Rationale:** Mengubah pernyataan datar menjadi pertanyaan tindakan: *"boleh saya bantu buatkan form reservasi sekarang?"*.

#### 32. `closing_dp_02` (Langkah Berikutnya Menuju DP)
* **Before:** *"Langkah berikutnya tinggal amankan seat dulu, Kak. Untuk paket ini DP awalnya {{dp}}."*
* **After:** *"Langkah mudah berikutnya, kita tinggal amankan kuota manifes maskapai Kakak dengan DP awal **{{dp}}**. Apakah Kakak ingin saya kirimkan nomor rekening resmi travel kami sekarang?"*
* **CRO Rationale:** Memberikan langkah spesifik berikutnya (*kirim nomor rekening resmi*).

#### 33. `closing_schedule_01` (Jadwal Sangat Sesuai)
* **Before:** *"Karena tanggal {{tanggal}} ini memang yang paling cocok dengan jadwal Kakak, kalau sudah mantap kita bisa lanjut amankan seat-nya."*
* **After:** *"Mengingat tanggal **{{tanggal}}** ini adalah jadwal yang paling pas dengan waktu luang Kakak sekeluarga, mau saya bantu tahan kuotanya untuk **{{jumlah_jamaah}}** orang hari ini Kak?"*
* **CRO Rationale:** Meminta persetujuan penahanan kuota sejumlah jamaah yang direncanakan.

---

# BAGIAN 3: PENGURAIAN COGNITIVE OVERLOAD & MULTI-QUESTION (14 SKRIP)
> **Tujuan:** Menjalankan **Single-Question Rule** (Aturan 1 Pertanyaan per Balon Chat). Jangan pernah menanyakan waktu, budget, dan jumlah orang sekaligus. Gunakan konsep bertahap (*Micro-Commitment*).

---

### 1. `greeting_brochure_01` (`greeting.json`)
* **Masalah:** Bertanya 2 hal sekaligus (kapan dan berapa orang).
* **Teks Lama (Before):**
  > *"Siap Kak. Saya kirim yang paling relevan ya. Rencananya mau berangkat kapan dan berapa orang?"*
* **Draft Usulan Baru (After):**
  > *"Siap Kak, dengan senang hati. Karena pilihan paket kami cukup beragam, agar saya kirimkan brosur yang paling pas, rencananya Kakak ingin keberangkatan di bulan apa?"*
* **Teknik CRO:** Tanyakan BULAN terlebih dahulu. Setelah prospek menjawab bulan, baru tanyakan jumlah orang di bubble berikutnya.

---

### 2. `greeting_ready_01` (`greeting.json`)
* **Masalah:** Mengajukan 3 pertanyaan sekaligus dalam satu kalimat panjang (paket apa, tanggal berapa, dan berapa orang).
* **Teks Lama (Before):**
  > *"MasyaAllah, siap Kak 😊 Saya bantu prosesnya. Sebelum lanjut, boleh tahu paket atau tanggal keberangkatan yang Kakak pilih dan rencananya berapa orang?"*
* **Draft Usulan Baru (After):**
  > *"MasyaAllah, alhamdulillah siap kami bantu proses pendaftarannya Kak 😊 Boleh diinfokan nama paket atau tanggal keberangkatan yang sudah Kakak incar?"*
* **Teknik CRO:** Kunci pilihan paket/tanggal dulu. Jamaah merasa beban mengetik berkurang 70%.

---

### 3. `qual_city_01` (`identification.json`)
* **Masalah:** Bertanya domisili di mana + ingin terbang dari kota mana.
* **Teks Lama (Before):**
  > *"Kakak domisili di mana? Lebih nyaman berangkat dari kota mana?"*
* **Draft Usulan Baru (After):**
  > *"Untuk keberangkatan nanti, Kakak lebih nyaman terbang dari bandara mana, Kak?"*
* **Teknik CRO:** CS hanya butuh bandara keberangkatan. Domisili tempat tinggal tidak relevan untuk tiket maskapai.

---

### 4. `combo_family_01` (`identification.json`)
* **Masalah:** Bertanya berapa orang + sudah ada target bulan apa.
* **Teks Lama (Before):**
  > *"MasyaAllah. Rencananya berapa orang dan sudah ada target bulan keberangkatannya?"*
* **Draft Usulan Baru (After):**
  > *"MasyaAllah, berkah sekali bisa ibadah bersama keluarga tercinta. Rencananya rombongan keluarga yang akan berangkat sekitar berapa orang, Kak?"*
* **Teknik CRO:** Fokus pada jumlah jamaah (*headcount*) untuk menentukan tipe kamar (Quad/Triple/Double).

---

### 5. `combo_parent_01` (`identification.json`)
* **Masalah:** Memberikan 3 opsi sekaligus yang tumpang tindih (hotel dekat, pendampingan, atau ritme santai).
* **Teks Lama (Before):**
  > *"Kalau untuk Ibu nanti, yang paling ingin Kakak pastikan itu hotel dekat, pendampingan, atau ritme perjalanan yang lebih nyaman?"*
* **Draft Usulan Baru (After):**
  > *"MasyaAllah, mulia sekali niat Kakak membahagiakan orang tua. Untuk kenyamanan beliau nanti, apakah kondisi fisik Ibu saat ini masih kuat berjalan kaki normal atau membutuhkan kursi roda Kak?"*
* **Teknik CRO:** Pertanyaan medis/fisik praktis. Ini secara otomatis menentukan kebutuhan hotel dekat dan muthawif khusus tanpa membingungkan jamaah.

---

### 6. `combo_first_timer_01` (`identification.json`)
* **Masalah:** Kalimat berbelit-belit (*"banyak pendampingan atau proses dijelaskan dari awal"*).
* **Teks Lama (Before):**
  > *"Karena ini pertama kali, Kakak lebih butuh paket yang banyak pendampingannya atau sudah cukup nyaman kalau prosesnya dijelaskan dari awal?"*
* **Draft Usulan Baru (After):**
  > *"Karena ini keberangkatan pertama kali, tenang saja Kak, kami siapkan manasik intensif sebelum terbang. Apakah Kakak lebih nyaman jika dibimbing langsung oleh muthawif pembimbing sejak dari bandara di Indonesia?"*
* **Teknik CRO:** Pertanyaan tegas dengan nilai tambah pendampingan sejak bandara asal.

---

### 7. `pain_doubt_01` (`identification.json`)
* **Masalah:** Memberikan 4 pilihan keraguan sekaligus (travel, paket, harga, waktu).
* **Teks Lama (Before):**
  > *"Yang masih bikin ragu lebih ke travelnya, paketnya, harganya, atau waktunya, Kak?"*
* **Draft Usulan Baru (After):**
  > *"Sangat wajar Kak jika masih menimbang. Kalau boleh tahu, yang saat ini paling menjadi pertimbangan Kakak lebih ke **pencocokan jadwal** atau **kesiapan biayanya**, Kak?"*
* **Teknik CRO:** Mereduksi 4 opsi menjadi 2 kutub utama (Waktu vs Uang).

---

### 8. `need_package_01` (`identification.json`)
* **Masalah:** Memisahkan kenyamanan dan jarak hotel padahal keduanya sama.
* **Teks Lama (Before):**
  > *"Kalau untuk paketnya, Kakak lebih mengutamakan harga, kenyamanan, atau jarak hotel?"*
* **Draft Usulan Baru (After):**
  > *"Kalau untuk paketnya nanti, prioritas utama Kakak lebih mengutamakan **efisiensi biaya (paket hemat)** atau **kenyamanan fasilitas dan layanan terbaik (paket VIP/Premium)**?"*
* **Teknik CRO:** Kontras tajam yang jelas (Hemat Biaya vs Fasilitas Terbaik/VIP) tanpa overclaim posisi hotel.

---

### 9. `obj_diskusi_keluarga_01` - Gali (`objection.json`)
* **Masalah:** Bertanya rincian jamaah/jadwal/biaya lalu langsung menyambung dengan pertanyaan siapa penentu keputusan.
* **Teks Lama (Before):**
  > *"Yang perlu disepakati biasanya jumlah jamaah, jadwal, atau biayanya? Siapa saja yang perlu ikut menentukan?"*
* **Draft Usulan Baru (After):**
  > *"Tentu Kak, agar saat rembuk keluarga nanti Kakak punya bahan yang lengkap, hal apa yang biasanya paling banyak ditanyakan oleh keluarga: **pilihan tanggal liburnya** atau **total biayanya**, Kak?"*
* **Teknik CRO:** Membantu posisi prospek sebagai juru bicara keluarga dengan opsi biner.

---

### 10. `fu_after_offer_01` (`followups.json`)
* **Masalah:** Bertanya sempat dilihat + ada yang mau ditanyakan.
* **Teks Lama (Before):**
  > *"Assalamu'alaikum Kak {{nama}}, paket yang kemarin saya kirim sempat dilihat? Ada bagian yang masih ingin ditanyakan?"*
* **Draft Usulan Baru (After):**
  > *"Assalamu'alaikum Kak {{nama}} 😊 Dari rincian hotel dan jadwal keberangkatan **{{tanggal}}** yang kemarin saya kirimkan, apakah tanggal tersebut sudah cocok dengan jadwal luang Kakak?"*
* **Teknik CRO:** Langsung validasi tanggal, tidak menanyakan apakah brosur sudah dibuka (yang sering dijawab "belum sempat").

---

### 11. `qual_special_condition_01` (`identification.json`)
* **Masalah:** Pertanyaan terlalu abstrak dan membebani pikiran jamaah.
* **Teks Lama (Before):**
  > *"Ada kondisi khusus dari Kakak atau keluarga yang perlu saya perhatikan supaya nanti perjalanannya lebih nyaman?"*
* **Draft Usulan Baru (After):**
  > *"Supaya perjalanan ibadah nanti tenang dan lancar, apakah ada anggota keluarga yang lansia, anak-anak, atau memiliki pantangan makanan tertentu, Kak?"*
* **Teknik CRO:** Menyebutkan contoh nyata (lansia, anak, pantangan) sehingga jamaah tinggal mencocokkan.

---

### 12. `qual_decision_01` (`identification.json`)
* **Masalah:** Terdengar agak interogatif mengenai otoritas finansial jamaah.
* **Teks Lama (Before):**
  > *"Untuk pilih paketnya nanti Kakak yang menentukan atau perlu diskusi dulu sama pasangan atau keluarga?"*
* **Draft Usulan Baru (After):**
  > *"Untuk rencana keberangkatan ini, apakah nantinya Kakak yang memutuskan langsung atau ada pasangan/keluarga yang ikut berdiskusi bersama, Kak?"*
* **Teknik CRO:** Bahasa lebih halus, menempatkan diskusi keluarga sebagai hal positif.

---

### 13. `greeting_status_wa_01` (`greeting.json`)
* **Masalah:** Kalimat penutup kurang menggigit dan sedikit membingungkan.
* **Teks Lama (Before):**
  > *"Yang di status tadi keberangkatan {{bulan}}, Kak 😊 Kakak memang sedang ada rencana umroh juga bersama keluarga?"*
* **Draft Usulan Baru (After):**
  > *"Betul sekali Kak, yang di status tadi untuk keberangkatan bulan **{{bulan}}** 😊 Apakah Kakak sedang mencari jadwal keberangkatan di bulan tersebut?"*
* **Teknik CRO:** Mengunci minat pada bulan yang dipajang di status WhatsApp.

---

### 14. `obj_bandingkan_travel_01` - Gali (`objection.json`)
* **Masalah:** 4 variabel perbandingan sekaligus.
* **Teks Lama (Before):**
  > *"Yang paling ingin Kakak bandingkan itu harga, hotel, jadwal, atau travelnya? Dari penawaran lain, bagian apa yang paling menarik buat Kakak?"*
* **Draft Usulan Baru (After):**
  > *"Tentu Kak, membandingkan itu hak jamaah agar yakin. Dari penawaran travel lain yang Kakak lihat, bagian apa yang paling menarik perhatian Kakak: **harganya yang lebih murah** atau **ada fasilitas tertentu**?"*
* **Teknik CRO:** Mengisolasi akar pembanding (apakah karena perang harga murni atau fitur tertentu).

---

## Rekomendasi Langkah Selanjutnya

Jika naskah draft perbaikan 1, 2, dan 3 di atas telah disetujui:
1. Kita dapat menerapkan perubahan secara langsung ke masing-masing file:
   - `scripts-chat/greeting.json`
   - `scripts-chat/objection.json`
   - `scripts-chat/followups.json`
   - `scripts-chat/identification.json`
   - `scripts-chat/closing.json`
2. Menjalankan pengujian rendering di interface Copilot (`index.php` dan tab Chat) untuk memastikan seluruh placeholder dinamis terisi dengan sempurna tanpa ada script yang error atau rusak.

