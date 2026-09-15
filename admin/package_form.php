<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_role('superadmin');

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
$isEdit = ($id > 0);
$error = '';
$success = '';

// Fetch all brands with details for custom dropdown
$brands = $db->query("SELECT id, name, code, ppiu_number FROM brands ORDER BY id ASC")->fetchAll();

// Default values for new package
$package = [
    'id' => 0,
    'brand_id' => $brands[0]['id'] ?? 0,
    'name' => '',
    'departure_date' => '',
    'duration_days' => 9,
    'quota_remaining' => 45,
    'airline' => '',
    'flight_type' => 'direct',
    'hotels' => [
        ['city' => 'Makkah', 'name' => '', 'stars' => 5],
        ['city' => 'Madinah', 'name' => '', 'stars' => 5],
    ],
    'price_quad' => '',
    'price_triple' => '',
    'price_double' => '',
    'price_infant' => '',
    'dp' => '5.000.000',
    'facilities_included' => "Tiket Pesawat PP\nVisa Umroh & Asuransi\nHotel Makkah & Madinah\nMakan 3x Sehari (Menu Indo)\nBus AC & Muthawwif\nAir Zamzam & Perlengkapan\nHandling Bandara PP",
    'facilities_excluded' => "Paspor & Vaksin\nPengeluaran Pribadi (Laundry/Telp)\nKelebihan Bagasi\nTiket Domestik ke Jakarta",
    'itinerary' => "Hari 1: Kumpul di Bandara Soekarno-Hatta (CGK), proses check-in dan penerbangan menuju Arab Saudi.\nHari 2: Tiba di Madinah, check-in hotel, istirahat dan ibadah di Masjid Nabawi.\nHari 3: Ziarah Kota Madinah (Masjid Quba, Jabal Uhud, Kebun Kurma, Masjid Qiblatain).\nHari 4: Ziarah Raudhah & Makam Rasulullah SAW, kajian persiapan manasik ihram.\nHari 5: Mengambil Miqat di Bir Ali, bertolak ke Makkah dengan Kereta Cepat, check-in hotel dan pelaksanaan Umroh Pertama.\nHari 6: Istirahat dan memperbanyak ibadah thawaf sunnah di Masjidil Haram.\nHari 7: Ziarah Kota Makkah (Jabal Tsur, Padang Arafah, Muzdalifah, Mina, Jabal Rahmah, Miqat Ji'ranah untuk Umroh Kedua).\nHari 8: Thawaf Wada' (perpisahan) dan persiapan kepulangan menuju Bandara Jeddah.\nHari 9: Penerbangan kembali ke Tanah Air dan tiba di Bandara Soekarno-Hatta dengan selamat.",
    'is_promo' => 0,
    'promo_discount' => '',
    'promo_deadline' => '',
    'flyer_image' => null,
    'is_active' => 1,
];

// If Edit, load existing data
if ($isEdit) {
    $stmt = $db->prepare("SELECT * FROM packages WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if ($existing) {
        $package['id'] = $existing['id'];
        $package['brand_id'] = (int)$existing['brand_id'];
        $package['name'] = $existing['name'];
        $package['departure_date'] = $existing['departure_date'] ?: '';
        
        // Extract numeric duration
        if (!empty($existing['duration'])) {
            preg_match('/\d+/', $existing['duration'], $matches);
            $package['duration_days'] = !empty($matches[0]) ? (int)$matches[0] : 9;
        }
        
        $package['quota_remaining'] = $existing['quota_remaining'] !== null ? (int)$existing['quota_remaining'] : 0;
        $package['airline'] = $existing['airline'] ?: '';
        $package['flight_type'] = $existing['flight_type'] ?: 'direct';
        
        // Parse hotels JSON or fallback
        if (!empty($existing['hotels'])) {
            $parsedHotels = json_decode($existing['hotels'], true);
            if (is_array($parsedHotels) && count($parsedHotels) > 0) {
                $package['hotels'] = $parsedHotels;
            }
        } elseif (!empty($existing['hotel_makkah']) || !empty($existing['hotel_madinah'])) {
            $package['hotels'] = [];
            if (!empty($existing['hotel_makkah'])) {
                $package['hotels'][] = ['city' => 'Makkah', 'name' => $existing['hotel_makkah'], 'stars' => 5];
            }
            if (!empty($existing['hotel_madinah'])) {
                $package['hotels'][] = ['city' => 'Madinah', 'name' => $existing['hotel_madinah'], 'stars' => 5];
            }
        }
        
        // Prices
        $package['price_quad'] = $existing['price_quad'] ?: $existing['price'];
        $package['price_triple'] = $existing['price_triple'] ?: '';
        $package['price_double'] = $existing['price_double'] ?: '';
        $package['price_infant'] = $existing['price_infant'] ?: '';
        $package['dp'] = $existing['dp'] ?: '';
        
        // Facilities & Itinerary
        $package['facilities_included'] = $existing['facilities_included'] ?: ($existing['highlights'] ?: $package['facilities_included']);
        $package['facilities_excluded'] = $existing['facilities_excluded'] ?: $package['facilities_excluded'];
        $package['itinerary'] = $existing['itinerary'] ?: $package['itinerary'];
        
        // Promo
        $package['is_promo'] = (int)($existing['is_promo'] ?? 0);
        $package['promo_discount'] = $existing['promo_discount'] ?: '';
        $package['promo_deadline'] = $existing['promo_deadline'] ?: '';
        $package['flyer_image'] = $existing['flyer_image'] ?? null;
        $package['is_active'] = (int)$existing['is_active'];
    } else {
        $error = 'Paket umroh tidak ditemukan.';
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $brand_id = (int)($_POST['brand_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $departure_date = !empty($_POST['departure_date']) ? trim($_POST['departure_date']) : null;
    $duration_days = (int)($_POST['duration_days'] ?? 9);
    $duration = $duration_days > 0 ? "{$duration_days} Hari" : "9 Hari";
    $quota_remaining = isset($_POST['quota_remaining']) ? (int)$_POST['quota_remaining'] : 0;
    $airline = trim($_POST['airline'] ?? '');
    $flight_type = in_array($_POST['flight_type'] ?? '', ['direct', 'transit']) ? $_POST['flight_type'] : 'direct';

    // Hotels array from POST
    $hotelsPost = $_POST['hotels'] ?? [];
    $cleanedHotels = [];
    $hotel_makkah = '';
    $hotel_madinah = '';

    if (is_array($hotelsPost)) {
        foreach ($hotelsPost as $h) {
            $hName = trim($h['name'] ?? '');
            if ($hName !== '') {
                $hCity = trim($h['city'] ?? 'Makkah');
                $hStars = (int)($h['stars'] ?? 5);
                if ($hStars < 1) $hStars = 1;
                if ($hStars > 5) $hStars = 5;

                $cleanedHotels[] = [
                    'city' => $hCity,
                    'name' => $hName,
                    'stars' => $hStars
                ];

                if (stripos($hCity, 'Makkah') !== false && empty($hotel_makkah)) {
                    $hotel_makkah = $hName . " (Bintang {$hStars})";
                }
                if (stripos($hCity, 'Madinah') !== false && empty($hotel_madinah)) {
                    $hotel_madinah = $hName . " (Bintang {$hStars})";
                }
            }
        }
    }
    $hotelsJson = json_encode($cleanedHotels, JSON_UNESCAPED_UNICODE);

    // Helper function to format nominal cleanly as "Rp 29.500.000"
    $formatRp = function($val) {
        $digits = preg_replace('/\D/', '', $val ?? '');
        return !empty($digits) ? 'Rp ' . number_format((float)$digits, 0, ',', '.') : '';
    };

    // Prices (Strict Thousand Separator Formatting)
    $price_quad = $formatRp($_POST['price_quad'] ?? '');
    $price_triple = $formatRp($_POST['price_triple'] ?? '');
    $price_double = $formatRp($_POST['price_double'] ?? '');
    $price_infant = $formatRp($_POST['price_infant'] ?? '');
    $dp = $formatRp($_POST['dp'] ?? '');
    $primaryPrice = !empty($price_quad) ? $price_quad : $formatRp($_POST['price'] ?? '29500000');

    // Facilities & Itinerary
    $facilities_included = trim($_POST['facilities_included'] ?? '');
    $facilities_excluded = trim($_POST['facilities_excluded'] ?? '');
    $itinerary = trim($_POST['itinerary'] ?? '');
    $highlights = !empty($facilities_included) ? mb_substr($facilities_included, 0, 250) : '';

    // Departure Info string representation
    $departure_info = '';
    if (!empty($departure_date)) {
        $timestamp = strtotime($departure_date);
        $bulanIndo = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $d = (int)date('d', $timestamp);
        $m = (int)date('n', $timestamp);
        $y = date('Y', $timestamp);
        $departure_info = "{$d} {$bulanIndo[$m]} {$y}";
    }

    // Promo
    $is_promo = isset($_POST['is_promo']) ? 1 : 0;
    $promo_discount = ($is_promo && !empty($_POST['promo_discount'])) ? $formatRp($_POST['promo_discount']) : null;
    $promo_deadline = ($is_promo && !empty($_POST['promo_deadline'])) ? trim($_POST['promo_deadline']) : null;

    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Flyer Image Handling (Auto Compress & Convert to WebP via GD)
    $flyer_image = $package['flyer_image'] ?? null;
    $remove_flyer = !empty($_POST['remove_flyer']) && $_POST['remove_flyer'] === '1';

    if ($remove_flyer) {
        if (!empty($flyer_image) && file_exists(__DIR__ . '/../' . $flyer_image)) {
            @unlink(__DIR__ . '/../' . $flyer_image);
        }
        $flyer_image = null;
    }

    // Check if new flyer uploaded
    if (!empty($_FILES['flyer_image']['tmp_name']) && is_uploaded_file($_FILES['flyer_image']['tmp_name'])) {
        $uploadedTmp = $_FILES['flyer_image']['tmp_name'];
        $imageInfo = @getimagesize($uploadedTmp);
        if ($imageInfo) {
            $mime = $imageInfo['mime'];
            $srcImage = null;
            switch ($mime) {
                case 'image/jpeg':
                case 'image/jpg':
                    $srcImage = @imagecreatefromjpeg($uploadedTmp);
                    break;
                case 'image/png':
                    $srcImage = @imagecreatefrompng($uploadedTmp);
                    break;
                case 'image/webp':
                    $srcImage = @imagecreatefromwebp($uploadedTmp);
                    break;
            }

            if ($srcImage) {
                $origWidth = imagesx($srcImage);
                $origHeight = imagesy($srcImage);
                $maxWidth = 1200;
                $maxHeight = 1600;

                $newWidth = $origWidth;
                $newHeight = $origHeight;

                if ($origWidth > $maxWidth || $origHeight > $maxHeight) {
                    $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
                    $newWidth = (int)round($origWidth * $ratio);
                    $newHeight = (int)round($origHeight * $ratio);
                }

                $destImage = imagecreatetruecolor($newWidth, $newHeight);
                imagealphablending($destImage, false);
                imagesavealpha($destImage, true);
                $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
                imagefilledrectangle($destImage, 0, 0, $newWidth, $newHeight, $transparent);
                imagecopyresampled($destImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

                $uploadDir = __DIR__ . '/../uploads/flyers/';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }

                $flyerFilename = 'flyer_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.webp';
                $targetFile = $uploadDir . $flyerFilename;

                if (imagewebp($destImage, $targetFile, 80)) {
                    if (!empty($flyer_image) && file_exists(__DIR__ . '/../' . $flyer_image)) {
                        @unlink(__DIR__ . '/../' . $flyer_image);
                    }
                    $flyer_image = 'uploads/flyers/' . $flyerFilename;
                }

                imagedestroy($destImage);
                imagedestroy($srcImage);
            }
        }
    }

    // Validation
    if (empty($brand_id)) {
        $error = 'Pilih brand travel.';
    } elseif (empty($name)) {
        $error = 'Nama paket umroh wajib diisi.';
    } elseif (empty($price_quad) && empty($primaryPrice)) {
        $error = 'Harga paket Quad wajib diisi.';
    } else {
        if ($isEdit) {
            $stmt = $db->prepare("
                UPDATE packages SET 
                    brand_id = ?, name = ?, price = ?, dp = ?, airline = ?, flight_type = ?, 
                    hotel_makkah = ?, hotel_madinah = ?, hotels = ?, departure_info = ?, 
                    departure_date = ?, duration = ?, highlights = ?, quota_remaining = ?,
                    price_quad = ?, price_triple = ?, price_double = ?, price_infant = ?,
                    facilities_included = ?, facilities_excluded = ?, itinerary = ?,
                    is_promo = ?, promo_discount = ?, promo_deadline = ?, flyer_image = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $brand_id, $name, $primaryPrice, $dp, $airline, $flight_type,
                $hotel_makkah, $hotel_madinah, $hotelsJson, $departure_info,
                $departure_date, $duration, $highlights, $quota_remaining,
                $price_quad, $price_triple, $price_double, $price_infant,
                $facilities_included, $facilities_excluded, $itinerary,
                $is_promo, $promo_discount, $promo_deadline, $flyer_image, $is_active,
                $id
            ]);
            header("Location: packages.php?msg=updated&name=" . urlencode($name));
            exit;
        } else {
            $stmt = $db->prepare("
                INSERT INTO packages (
                    brand_id, name, price, dp, airline, flight_type, 
                    hotel_makkah, hotel_madinah, hotels, departure_info, 
                    departure_date, duration, highlights, quota_remaining,
                    price_quad, price_triple, price_double, price_infant,
                    facilities_included, facilities_excluded, itinerary,
                    is_promo, promo_discount, promo_deadline, flyer_image, is_active
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, 
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $brand_id, $name, $primaryPrice, $dp, $airline, $flight_type,
                $hotel_makkah, $hotel_madinah, $hotelsJson, $departure_info,
                $departure_date, $duration, $highlights, $quota_remaining,
                $price_quad, $price_triple, $price_double, $price_infant,
                $facilities_included, $facilities_excluded, $itinerary,
                $is_promo, $promo_discount, $promo_deadline, $flyer_image, $is_active
            ]);
            header("Location: packages.php?msg=created&name=" . urlencode($name));
            exit;
        }
    }
}

$pageTitle = $isEdit ? 'Edit Paket Umroh - Super Admin' : 'Tambah Paket Umroh Baru - Super Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto space-y-6 pb-20" 
     x-data="packageForm(<?= htmlspecialchars(json_encode($package), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($brands), ENT_QUOTES, 'UTF-8') ?>)">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 sm:pb-6 border-b border-zinc-200 gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="packages.php" class="text-xs text-zinc-500 hover:text-black inline-flex items-center gap-1 font-semibold transition">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    <span>Master Paket Umroh</span>
                </a>
                <span class="text-zinc-300">/</span>
                <span class="text-xs font-semibold text-black"><?= $isEdit ? 'Edit Paket' : 'Tambah Paket Baru' ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-black tracking-tight mt-1 flex items-center gap-2">
                <span><?= $isEdit ? 'Edit Paket Umroh' : 'Tambah Paket Umroh' ?></span>
                <span x-show="form.name" class="text-zinc-400 font-normal text-lg sm:text-xl truncate max-w-xs sm:max-w-md" x-text="'— ' + form.name"></span>
            </h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="packages.php" 
               class="px-3.5 py-2 border border-zinc-200 hover:border-black text-zinc-700 hover:text-black text-xs font-semibold rounded-xl transition flex items-center gap-1.5 bg-white shadow-2xs">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                <span>Kembali ke Daftar</span>
            </a>
        </div>
    </div>

    <!-- Error Alert -->
    <?php if (!empty($error)): ?>
        <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-900 font-semibold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-rose-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-800 font-bold">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Main Form Element (Two Columns Layout) -->
    <form id="packageFormElement" method="POST" enctype="multipart/form-data">

        <!-- Hidden Inputs for Form Submissions -->
        <input type="hidden" name="brand_id" :value="form.brand_id">
        <input type="hidden" name="flight_type" :value="form.flight_type">
        <input type="hidden" name="remove_flyer" :value="removeFlyer ? '1' : '0'">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- ========================================== -->
            <!-- KOLOM KIRI (UTAMA / KONTEN PAKET) - 8 Cols -->
            <!-- ========================================== -->
            <div class="lg:col-span-8 space-y-6">

                <!-- 1. INFORMASI POKOK: BRAND, NAMA PAKET, JADWAL & KUOTA -->
                <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
                    <!-- Brand Travel & Nama Paket Berdampingan (Pilih Brand Dulu, Baru Nama Paket) -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-start">
                        
                        <!-- 1. Brand Travel -->
                        <div class="md:col-span-4 relative">
                            <label class="block text-xs font-bold text-zinc-700 mb-1.5">
                                Brand Travel *
                            </label>
                            <button type="button" @click="brandDropdownOpen = !brandDropdownOpen" 
                                    class="w-full h-10 flex items-center justify-between px-3 border border-zinc-300 rounded-xl bg-white hover:border-black text-xs text-zinc-900 focus:outline-none focus:border-black focus:ring-1 focus:ring-black transition shadow-2xs">
                                <span class="font-bold truncate" x-text="getSelectedBrand().name"></span>
                                <svg class="w-4 h-4 text-zinc-400 shrink-0 transition-transform duration-200" 
                                     :class="brandDropdownOpen ? 'rotate-180 text-black' : ''" 
                                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </button>

                            <!-- Dropdown Menu -->
                            <div x-show="brandDropdownOpen" @click.outside="brandDropdownOpen = false" 
                                 x-transition.origin.top.duration.150ms x-cloak
                                 class="absolute left-0 top-full mt-1.5 w-full bg-white border border-zinc-200 rounded-xl shadow-xl z-40 py-1 max-h-60 overflow-y-auto">
                                <template x-for="b in brands" :key="b.id">
                                    <button type="button" 
                                            @click="form.brand_id = parseInt(b.id); brandDropdownOpen = false"
                                            class="w-full text-left px-3 py-2 hover:bg-zinc-100 flex items-center justify-between transition"
                                            :class="parseInt(form.brand_id) === parseInt(b.id) ? 'bg-zinc-50 font-bold text-black' : 'text-zinc-700'">
                                        <span class="truncate text-xs" x-text="b.name"></span>
                                        <span x-show="parseInt(form.brand_id) === parseInt(b.id)" class="text-black font-bold">✓</span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- 2. Nama Paket -->
                        <div class="md:col-span-8">
                            <label class="block text-xs font-bold text-zinc-700 mb-1.5">
                                Nama Paket *
                            </label>
                            <input type="text" name="name" x-model="form.name" required 
                                   placeholder="Umroh Reguler Bintang 5 Plus 12 Hari" 
                                   class="w-full h-10 px-3 text-xs font-bold text-black border border-zinc-300 rounded-xl focus:border-black focus:ring-1 focus:ring-black placeholder:text-zinc-300 transition">
                        </div>

                    </div>

                    <!-- Jadwal & Kuota (Dekat dengan Nama Paket) -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-3 border-t border-zinc-100 text-xs">
                        <div>
                            <label class="block text-xs font-bold text-zinc-700 mb-1.5">Tanggal Berangkat *</label>
                            <input type="date" name="departure_date" x-model="form.departure_date" required 
                                   class="w-full h-10 px-3 border border-zinc-300 rounded-xl bg-white font-sans text-xs focus:border-black focus:ring-1 focus:ring-black">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-zinc-700 mb-1.5">Durasi *</label>
                            <div class="relative">
                                <input type="number" name="duration_days" x-model="form.duration_days" min="1" max="60" required 
                                       placeholder="9" 
                                       class="w-full h-10 px-3 pr-12 border border-zinc-300 rounded-xl focus:border-black focus:ring-1 focus:ring-black font-bold text-xs">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-zinc-400 pointer-events-none">Hari</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-zinc-700 mb-1.5">Sisa Kuota *</label>
                            <div class="relative">
                                <input type="number" name="quota_remaining" x-model="form.quota_remaining" min="0" 
                                       placeholder="45" 
                                       class="w-full h-10 px-3 pr-12 border border-zinc-300 rounded-xl focus:border-black focus:ring-1 focus:ring-black font-bold text-xs">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-zinc-400 pointer-events-none">Seat</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. PENERBANGAN -->
                <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
                    <div class="pb-3 border-b border-zinc-100">
                        <h2 class="text-sm font-bold text-black">Penerbangan</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                        <div>
                            <label class="block text-xs font-bold text-zinc-700 mb-1.5">Maskapai</label>
                            <input type="text" name="airline" x-model="form.airline" 
                                   placeholder="Saudia Airlines / Garuda Indonesia / Qatar Airways" 
                                   class="w-full h-10 px-3 border border-zinc-300 rounded-xl focus:border-black focus:ring-1 focus:ring-black text-xs font-medium">
                        </div>

                        <!-- Custom Dropdown: Tipe Penerbangan -->
                        <div class="relative" x-data="{ open: false }">
                            <label class="block text-xs font-bold text-zinc-700 mb-1.5">Tipe Penerbangan</label>
                            <button type="button" @click="open = !open" 
                                    class="w-full h-10 flex items-center justify-between px-3 border border-zinc-300 rounded-xl bg-white font-semibold text-xs text-zinc-900 focus:outline-none focus:border-black focus:ring-1 focus:ring-black transition shadow-2xs">
                                <span x-text="getFlightLabel(form.flight_type)"></span>
                                <svg class="w-4 h-4 text-zinc-400 transition-transform duration-200" :class="open ? 'rotate-180 text-black' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </button>

                            <div x-show="open" @click.outside="open = false" x-transition.origin.top.duration.150ms x-cloak
                                 class="absolute left-0 top-full mt-1.5 w-full bg-white border border-zinc-200 rounded-xl shadow-lg z-30 py-1 text-xs">
                                <template x-for="opt in flightOptions" :key="opt.value">
                                    <button type="button" 
                                            @click="form.flight_type = opt.value; open = false"
                                            class="w-full text-left px-3.5 py-2 hover:bg-zinc-100 flex items-center justify-between transition"
                                            :class="form.flight_type === opt.value ? 'bg-zinc-50 font-bold text-black' : 'text-zinc-700'">
                                        <span x-text="opt.label"></span>
                                        <span x-show="form.flight_type === opt.value" class="text-black font-bold">✓</span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. HOTEL & AKOMODASI -->
                <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                        <h2 class="text-sm font-bold text-black">Hotel & Akomodasi</h2>
                        <button type="button" @click="addHotel()" 
                                class="h-8 px-3 bg-zinc-100 hover:bg-black hover:text-white text-zinc-800 text-xs font-semibold rounded-xl border border-zinc-200 transition flex items-center gap-1.5 shadow-2xs">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            <span>Tambah Hotel</span>
                        </button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(hotel, index) in form.hotels" :key="index">
                            <div class="p-3 bg-zinc-50/80 border border-zinc-200/80 rounded-xl grid grid-cols-1 md:grid-cols-12 gap-3 items-end text-xs"
                                 x-data="{ hoverStars: 0 }">
                                
                                <!-- Kota (Custom Dropdown) -->
                                <div class="md:col-span-3 relative" x-data="{ cityOpen: false }">
                                    <label class="block text-xs font-bold text-zinc-700 mb-1.5">Kota</label>
                                    <input type="hidden" :name="'hotels[' + index + '][city]'" :value="hotel.city">
                                    
                                    <button type="button" @click="cityOpen = !cityOpen" 
                                            class="w-full h-10 flex items-center justify-between px-3 border border-zinc-300 rounded-xl bg-white font-semibold text-xs text-zinc-900 focus:outline-none focus:border-black focus:ring-1 focus:ring-black transition shadow-2xs">
                                        <span class="truncate font-semibold" x-text="getCityLabel(hotel.city)"></span>
                                        <svg class="w-3.5 h-3.5 text-zinc-400 shrink-0 transition-transform duration-200" :class="cityOpen ? 'rotate-180 text-black' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="6 9 12 15 18 9"/>
                                        </svg>
                                    </button>

                                    <div x-show="cityOpen" @click.outside="cityOpen = false" x-transition.origin.top.duration.150ms x-cloak
                                         class="absolute left-0 top-full mt-1.5 w-full bg-white border border-zinc-200 rounded-xl shadow-lg z-30 py-1 text-xs">
                                        <template x-for="opt in cityOptions" :key="opt.value">
                                            <button type="button" 
                                                    @click="hotel.city = opt.value; cityOpen = false"
                                                    class="w-full text-left px-3 py-2 hover:bg-zinc-100 flex items-center justify-between transition"
                                                    :class="hotel.city === opt.value ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                                <span x-text="opt.label"></span>
                                                <span x-show="hotel.city === opt.value" class="text-black font-bold">✓</span>
                                            </button>
                                        </template>
                                    </div>
                                </div>

                                <!-- Nama Hotel -->
                                <div class="md:col-span-5">
                                    <label class="block text-xs font-bold text-zinc-700 mb-1.5">Nama Hotel</label>
                                    <input type="text" :name="'hotels[' + index + '][name]'" x-model="hotel.name" required
                                           placeholder="Pullman Zamzam Tower / Rove Al Madinah" 
                                           class="w-full h-10 px-3 border border-zinc-300 rounded-xl bg-white focus:border-black focus:ring-1 focus:ring-black font-medium text-xs">
                                </div>

                                <!-- Bintang Rating -->
                                <div class="md:col-span-3">
                                    <label class="block text-xs font-bold text-zinc-700 mb-1.5">Bintang</label>
                                    <input type="hidden" :name="'hotels[' + index + '][stars]'" :value="hotel.stars">
                                    
                                    <div class="h-10 flex items-center justify-center gap-1 px-2 bg-white border border-zinc-300 rounded-xl select-none shadow-2xs"
                                         @mouseleave="hoverStars = 0">
                                        <template x-for="star in [1, 2, 3, 4, 5]" :key="star">
                                            <button type="button" 
                                                    @mouseenter="hoverStars = star"
                                                    @click="hotel.stars = star" 
                                                    :title="star + ' Bintang'"
                                                    class="p-1 focus:outline-none transition-transform hover:scale-110">
                                                <svg class="w-4 h-4 transition-colors"
                                                     :class="(hoverStars ? (star <= hoverStars) : (star <= hotel.stars)) ? 'text-amber-400 fill-amber-400' : 'text-zinc-300 fill-zinc-100'"
                                                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                                </svg>
                                            </button>
                                        </template>
                                    </div>
                                </div>

                                <!-- Hapus Button -->
                                <div class="md:col-span-1 flex items-end justify-end md:justify-center">
                                    <button type="button" @click="removeHotel(index)" 
                                            :disabled="form.hotels.length <= 1"
                                            title="Hapus"
                                            class="h-10 w-10 rounded-xl border border-zinc-200 bg-white hover:bg-rose-50 hover:border-rose-300 text-zinc-400 hover:text-rose-600 transition flex items-center justify-center disabled:opacity-30 disabled:pointer-events-none shadow-2xs">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M3 6h18m-2 0v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6m3 0V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                                        </svg>
                                    </button>
                                </div>

                            </div>
                        </template>
                    </div>
                </div>

                <!-- 4. SKEMA HARGA & MINIMAL DP -->
                <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
                    <div class="pb-3 border-b border-zinc-100">
                        <h2 class="text-sm font-bold text-black">Harga Paket & DP</h2>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 text-xs">
                        <!-- Quad -->
                        <div class="p-3 bg-zinc-50/70 border border-zinc-200 rounded-xl space-y-1.5">
                            <label class="block text-xs font-bold text-zinc-800">Quad (Kamar Ber-4) *</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-zinc-400 select-none pointer-events-none">Rp</span>
                                <input type="text" name="price_quad" x-model="form.price_quad" 
                                       @input="form.price_quad = formatRupiah($event.target.value)"
                                       inputmode="numeric"
                                       required 
                                       placeholder="29.500.000" 
                                       class="w-full h-10 pl-9 pr-3 border border-zinc-300 rounded-xl bg-white font-bold text-zinc-900 focus:border-black focus:ring-1 focus:ring-black text-xs">
                            </div>
                        </div>

                        <!-- Triple -->
                        <div class="p-3 bg-zinc-50/70 border border-zinc-200 rounded-xl space-y-1.5">
                            <label class="block text-xs font-bold text-zinc-800">Triple (Kamar Ber-3)</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-zinc-400 select-none pointer-events-none">Rp</span>
                                <input type="text" name="price_triple" x-model="form.price_triple" 
                                       @input="form.price_triple = formatRupiah($event.target.value)"
                                       inputmode="numeric"
                                       placeholder="31.500.000" 
                                       class="w-full h-10 pl-9 pr-3 border border-zinc-300 rounded-xl bg-white font-bold text-zinc-900 focus:border-black focus:ring-1 focus:ring-black text-xs">
                            </div>
                        </div>

                        <!-- Double -->
                        <div class="p-3 bg-zinc-50/70 border border-zinc-200 rounded-xl space-y-1.5">
                            <label class="block text-xs font-bold text-zinc-800">Double (Kamar isi 2 orang)</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-zinc-400 select-none pointer-events-none">Rp</span>
                                <input type="text" name="price_double" x-model="form.price_double" 
                                       @input="form.price_double = formatRupiah($event.target.value)"
                                       inputmode="numeric"
                                       placeholder="34.000.000" 
                                       class="w-full h-10 pl-9 pr-3 border border-zinc-300 rounded-xl bg-white font-bold text-zinc-900 focus:border-black focus:ring-1 focus:ring-black text-xs">
                            </div>
                        </div>

                        <!-- Infant -->
                        <div class="p-3 bg-zinc-50/70 border border-zinc-200 rounded-xl space-y-1.5">
                            <label class="block text-xs font-bold text-zinc-800">Infant (&lt; 2 Thn)</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-zinc-400 select-none pointer-events-none">Rp</span>
                                <input type="text" name="price_infant" x-model="form.price_infant" 
                                       @input="form.price_infant = formatRupiah($event.target.value)"
                                       inputmode="numeric"
                                       placeholder="12.500.000" 
                                       class="w-full h-10 pl-9 pr-3 border border-zinc-300 rounded-xl bg-white font-bold text-zinc-900 focus:border-black focus:ring-1 focus:ring-black text-xs">
                            </div>
                        </div>

                        <!-- DP -->
                        <div class="p-3 bg-zinc-50/70 border border-zinc-200 rounded-xl space-y-1.5 sm:col-span-2">
                            <label class="block text-xs font-bold text-zinc-800">Minimal DP *</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-zinc-400 select-none pointer-events-none">Rp</span>
                                <input type="text" name="dp" x-model="form.dp" 
                                       @input="form.dp = formatRupiah($event.target.value)"
                                       inputmode="numeric"
                                       required 
                                       placeholder="5.000.000" 
                                       class="w-full h-10 pl-9 pr-3 border border-zinc-300 rounded-xl bg-white font-bold text-zinc-900 focus:border-black focus:ring-1 focus:ring-black text-xs">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. PROMO DISKON -->
                <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                        <h2 class="text-sm font-bold text-black">Promo & Diskon</h2>
                        <label class="relative inline-flex items-center cursor-pointer select-none">
                            <input type="checkbox" name="is_promo" x-model="form.is_promo" class="sr-only peer">
                            <div class="w-11 h-6 bg-zinc-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-zinc-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-black"></div>
                        </label>
                    </div>

                    <div x-show="form.is_promo" x-transition x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs pt-1">
                        <div>
                            <label class="block text-xs font-bold text-zinc-700 mb-1.5">Nilai Potongan</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-zinc-400 select-none pointer-events-none">Rp</span>
                                <input type="text" name="promo_discount" x-model="form.promo_discount" 
                                       @input="form.promo_discount = formatRupiah($event.target.value)"
                                       inputmode="numeric"
                                       placeholder="1.500.000" 
                                       class="w-full h-10 pl-9 pr-3 border border-zinc-300 rounded-xl font-bold text-zinc-900 focus:border-black focus:ring-1 focus:ring-black text-xs">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-zinc-700 mb-1.5">Batas Waktu Promo</label>
                            <input type="date" name="promo_deadline" x-model="form.promo_deadline" 
                                   class="w-full h-10 px-3 border border-zinc-300 rounded-xl bg-white focus:border-black focus:ring-1 focus:ring-black text-xs font-sans">
                        </div>
                    </div>
                </div>

                <!-- 6. FASILITAS -->
                <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
                    <div class="pb-3 border-b border-zinc-100">
                        <h2 class="text-sm font-bold text-black">Fasilitas</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-zinc-800 mb-1.5">Termasuk (Include)</label>
                            <textarea name="facilities_included" x-model="form.facilities_included" rows="7" 
                                      class="w-full p-3 border border-zinc-300 rounded-xl font-mono text-xs leading-relaxed focus:border-black focus:ring-1 focus:ring-black"
                                      placeholder="Tiket Pesawat PP&#10;Visa Umroh & Asuransi&#10;Hotel Makkah & Madinah&#10;Makan 3x Sehari"></textarea>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-zinc-800 mb-1.5">Tidak Termasuk (Exclude)</label>
                            <textarea name="facilities_excluded" x-model="form.facilities_excluded" rows="7" 
                                      class="w-full p-3 border border-zinc-300 rounded-xl font-mono text-xs leading-relaxed focus:border-black focus:ring-1 focus:ring-black"
                                      placeholder="Pembuatan Paspor&#10;Vaksinasi Meningitis&#10;Pengeluaran Pribadi"></textarea>
                        </div>
                    </div>
                </div>

                <!-- 7. ITINERARY -->
                <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
                    <div class="pb-3 border-b border-zinc-100">
                        <h2 class="text-sm font-bold text-black">Itinerary</h2>
                    </div>

                    <div class="text-xs">
                        <textarea name="itinerary" x-model="form.itinerary" rows="8" 
                                  class="w-full p-3 border border-zinc-300 rounded-xl font-mono text-xs leading-relaxed focus:border-black focus:ring-1 focus:ring-black"
                                  placeholder="Hari 1: Kumpul di Bandara dan penerbangan ke Jeddah&#10;Hari 2: Tiba di Madinah dan ziarah Raudhah&#10;Hari 3: Ziarah Kota Madinah"></textarea>
                    </div>
                </div>

            </div>

            <!-- ========================================== -->
            <!-- KOLOM KANAN (SIDEBAR WORDPRESS EDITOR) - 4 -->
            <!-- ========================================== -->
            <div class="lg:col-span-4 flex flex-col gap-5 lg:sticky lg:top-20">

                <!-- PANEL: GAMBAR FLYER PAKET (Di mobile sebelum tombol publikasi) -->
                <div class="order-1 lg:order-2 bg-white border border-zinc-200/90 rounded-2xl p-5 shadow-xs space-y-3 text-xs">
                    <div class="pb-2 border-b border-zinc-100">
                        <h3 class="font-bold text-black uppercase tracking-wider text-[11px]">Flyer Paket</h3>
                    </div>

                    <input type="file" name="flyer_image" x-ref="flyerInput" 
                           accept="image/jpeg,image/png,image/webp" 
                           @change="handleFlyerSelect($event)" 
                           class="hidden">

                    <div x-show="flyerPreview" class="space-y-3">
                        <div class="relative group rounded-xl overflow-hidden border border-zinc-200 bg-zinc-100">
                            <img :src="flyerPreview" alt="Flyer" class="w-full h-48 sm:h-56 object-cover">
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2">
                                <button type="button" @click="$refs.flyerInput.click()" 
                                        class="px-3 py-1.5 bg-white text-black font-bold text-xs rounded-xl shadow hover:bg-zinc-100 transition">
                                    Ganti
                                </button>
                                <button type="button" @click="deleteFlyer()" 
                                        class="px-3 py-1.5 bg-rose-600 text-white font-bold text-xs rounded-xl shadow hover:bg-rose-700 transition">
                                    Hapus
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-end">
                            <button type="button" @click="deleteFlyer()" class="text-[11px] text-rose-600 hover:underline">
                                Hapus Flyer
                            </button>
                        </div>
                    </div>

                    <div x-show="!flyerPreview" 
                         @click="$refs.flyerInput.click()" 
                         class="border-2 border-dashed border-zinc-300 hover:border-black rounded-xl p-6 text-center cursor-pointer transition bg-zinc-50/50 hover:bg-zinc-50">
                        <div class="w-9 h-9 rounded-full bg-zinc-200 text-zinc-600 flex items-center justify-center mx-auto mb-2">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                <polyline points="21 15 16 10 5 21"/>
                            </svg>
                        </div>
                        <div class="font-bold text-zinc-800 text-xs">Pilih Gambar Flyer</div>
                        <p class="text-[10px] text-zinc-400 mt-1">JPG, PNG, WebP</p>
                    </div>
                </div>

                <!-- PANEL: STATUS PUBLIKASI & TOMBOL AKSI (Di mobile paling bawah) -->
                <div class="order-2 lg:order-1 bg-white border border-zinc-200/90 rounded-2xl p-5 shadow-xs space-y-4 text-xs">
                    <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                        <h3 class="font-bold text-black uppercase tracking-wider text-[11px]">Publikasi</h3>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                              :class="form.is_active ? 'bg-black text-white' : 'bg-zinc-100 text-zinc-600'">
                            <span x-text="form.is_active ? 'Aktif' : 'Draft'"></span>
                        </span>
                    </div>

                    <label class="flex items-center gap-2.5 cursor-pointer select-none">
                        <input type="checkbox" name="is_active" x-model="form.is_active" 
                               class="w-4 h-4 text-black border-zinc-300 rounded focus:ring-black">
                        <span class="font-semibold text-zinc-900">Aktifkan paket</span>
                    </label>

                    <div class="pt-2 border-t border-zinc-100 space-y-2">
                        <button type="submit" 
                                class="w-full h-10 bg-black hover:bg-zinc-800 text-white font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2 text-xs">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            <span><?= $isEdit ? 'Simpan' : 'Terbitkan' ?></span>
                        </button>
                        <a href="packages.php" class="w-full h-10 flex items-center justify-center bg-zinc-50 hover:bg-zinc-100 text-zinc-700 font-semibold rounded-xl border border-zinc-200 transition text-xs">
                            Batal
                        </a>
                    </div>
                </div>

            </div>

        </div>

    </form>
</div>

<script>
function packageForm(initialData, brandsList) {
    const formatThousand = function(val) {
        if (val === null || val === undefined) return '';
        const digits = val.toString().replace(/\D/g, '');
        if (!digits) return '';
        const withoutLeadingZeros = digits.replace(/^0+(?=\d)/, '');
        return withoutLeadingZeros.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    };

    return {
        form: {
            brand_id: initialData.brand_id || (brandsList && brandsList[0] ? brandsList[0].id : 1),
            name: initialData.name || '',
            departure_date: initialData.departure_date || '',
            duration_days: initialData.duration_days || 9,
            quota_remaining: initialData.quota_remaining !== undefined ? initialData.quota_remaining : 45,
            airline: initialData.airline || '',
            flight_type: initialData.flight_type || 'direct',
            hotels: initialData.hotels && initialData.hotels.length > 0 ? initialData.hotels : [
                { city: 'Makkah', name: '', stars: 5 },
                { city: 'Madinah', name: '', stars: 5 }
            ],
            price_quad: formatThousand(initialData.price_quad || initialData.price || ''),
            price_triple: formatThousand(initialData.price_triple || ''),
            price_double: formatThousand(initialData.price_double || ''),
            price_infant: formatThousand(initialData.price_infant || ''),
            dp: formatThousand(initialData.dp || '5000000'),
            facilities_included: initialData.facilities_included || '',
            facilities_excluded: initialData.facilities_excluded || '',
            itinerary: initialData.itinerary || '',
            is_promo: !!initialData.is_promo,
            promo_discount: formatThousand(initialData.promo_discount || ''),
            promo_deadline: initialData.promo_deadline || '',
            is_active: initialData.is_active !== undefined ? !!initialData.is_active : true
        },

        brands: brandsList || [],
        brandDropdownOpen: false,

        cityOptions: [
            { value: 'Makkah', label: 'Makkah Al-Mukarramah' },
            { value: 'Madinah', label: 'Madinah Al-Munawwarah' },
            { value: 'Jeddah', label: 'Jeddah' },
            { value: 'Transit', label: 'Kota Transit / Tour' }
        ],

        flightOptions: [
            { value: 'direct', label: 'Direct' },
            { value: 'transit', label: 'Transit' }
        ],

        flyerPreview: initialData.flyer_image ? ('<?= get_base_url() ?>/' + initialData.flyer_image) : null,
        removeFlyer: false,

        formatRupiah(val) {
            return formatThousand(val);
        },

        getSelectedBrand() {
            const found = this.brands.find(b => parseInt(b.id) === parseInt(this.form.brand_id));
            return found || (this.brands[0] || { name: 'Pilih Brand', ppiu_number: '' });
        },

        getCityLabel(val) {
            const found = this.cityOptions.find(c => c.value === val);
            return found ? found.label : (val || 'Pilih Kota');
        },

        getFlightLabel(val) {
            const found = this.flightOptions.find(f => f.value === val);
            return found ? found.label : (val === 'transit' ? 'Transit' : 'Direct');
        },

        handleFlyerSelect(event) {
            const file = event.target.files ? event.target.files[0] : null;
            if (!file) return;
            this.removeFlyer = false;
            const reader = new FileReader();
            reader.onload = (e) => {
                this.flyerPreview = e.target.result;
            };
            reader.readAsDataURL(file);
        },

        deleteFlyer() {
            this.flyerPreview = null;
            this.removeFlyer = true;
            if (this.$refs.flyerInput) {
                this.$refs.flyerInput.value = '';
            }
        },

        addHotel() {
            this.form.hotels.push({
                city: 'Makkah',
                name: '',
                stars: 5
            });
        },

        removeHotel(index) {
            if (this.form.hotels.length > 1) {
                this.form.hotels.splice(index, 1);
            }
        }
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
