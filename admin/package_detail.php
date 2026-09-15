<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_login();

$db = get_db();
$currentUser = get_logged_user();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: packages.php');
    exit;
}

// Handle POST actions on detail page (archive / unarchive / delete)
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle_status') {
        $stmt = $db->prepare("UPDATE packages SET is_active = IF(is_active=1, 0, 1) WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Status publikasi paket berhasil diubah.";
    } elseif ($action === 'delete') {
        $stmt = $db->prepare("DELETE FROM packages WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: packages.php?msg=deleted');
        exit;
    }
}

// Fetch package detail with Brand join
$stmt = $db->prepare("
    SELECT p.*, b.name AS brand_name, b.code AS brand_code, b.ppiu_number, b.phone AS brand_phone
    FROM packages p
    JOIN brands b ON p.brand_id = b.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$package = $stmt->fetch();

if (!$package) {
    echo "<div class='max-w-4xl mx-auto py-12 text-center text-zinc-500'>Paket umroh tidak ditemukan. <a href='packages.php' class='underline text-black font-semibold'>Kembali ke Daftar</a></div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Format departure date
$jadwalText = $package['departure_info'] ?: '-';
if (!empty($package['departure_date'])) {
    $jadwalText = date('d M Y', strtotime($package['departure_date']));
}

// Parse hotels
$hotels = [];
if (!empty($package['hotels'])) {
    $decoded = json_decode($package['hotels'], true);
    if (is_array($decoded)) $hotels = $decoded;
}
if (empty($hotels)) {
    if (!empty($package['hotel_makkah'])) $hotels[] = ['city' => 'Makkah', 'name' => $package['hotel_makkah'], 'stars' => 5];
    if (!empty($package['hotel_madinah'])) $hotels[] = ['city' => 'Madinah', 'name' => $package['hotel_madinah'], 'stars' => 5];
}

// Parse itinerary lines
$itinLines = array_filter(array_map('trim', explode("\n", $package['itinerary'] ?? '')));

// Clean up title to prevent duplicate "PAKET UMROH UMROH..."
$cleanTitle = trim($package['name']);
if (!preg_match('/^paket\s+/i', $cleanTitle)) {
    if (!preg_match('/^umroh\s+/i', $cleanTitle)) {
        $cleanTitle = 'Paket Umroh ' . $cleanTitle;
    } else {
        $cleanTitle = 'Paket ' . $cleanTitle;
    }
}

// Maskapai (hindari duplikasi tulisan Direct/Transit)
$airlineStr = $package['airline'] ?: '-';
$flightTypeStr = ($package['flight_type'] ?? 'direct') === 'direct' ? 'Direct' : 'Transit';
if ($airlineStr !== '-' && !stripos($airlineStr, $flightTypeStr)) {
    $airlineStr .= " ({$flightTypeStr})";
}

// 1. WhatsApp Format: Ringkasan Paket
$waSummary = "*" . strtoupper($cleanTitle) . "*\n";
$waSummary .= "Travel: " . $package['brand_name'] . "\n\n";
$waSummary .= "📅 Keberangkatan: " . $jadwalText . " (" . ($package['duration'] ?: '-') . ")\n";
$waSummary .= "✈️ Maskapai: " . $airlineStr . "\n";
foreach ($hotels as $h) {
    $waSummary .= "🏨 Hotel " . ($h['city'] ?? '') . ": " . ($h['name'] ?? '') . " (★" . ($h['stars'] ?? 5) . ")\n";
}
$waSummary .= "\n💰 *HARGA PAKET:*\n";
$waSummary .= "• Quad (Kamar Ber-4): " . ($package['price_quad'] ?: $package['price']) . "\n";
if (!empty($package['price_triple'])) $waSummary .= "• Triple (Kamar Ber-3): " . $package['price_triple'] . "\n";
if (!empty($package['price_double'])) $waSummary .= "• Double (Kamar Ber-2): " . $package['price_double'] . "\n";
$waSummary .= "• Minimal DP: " . $package['dp'] . "\n";

if (!empty($package['facilities_included'])) {
    $incItems = array_filter(array_map('trim', explode("\n", $package['facilities_included'])));
    if (!empty($incItems)) {
        $waSummary .= "\n✨ *FASILITAS INCLUDE:*\n";
        foreach ($incItems as $item) {
            $waSummary .= "• " . $item . "\n";
        }
    }
}

if (!empty($package['is_promo'])) {
    $waSummary .= "\n🎁 *PROMO KHUSUS:* Potongan " . $package['promo_discount'] . (!empty($package['promo_deadline']) ? " (s.d. " . date('d M Y', strtotime($package['promo_deadline'])) . ")" : "") . "\n";
}
$waSummary .= "\nSisa Kuota: " . ($package['quota_remaining'] ?? '-') . " Seat\n";
$waSummary .= "Info pendaftaran silakan balas pesan ini. Terima kasih! 🙏";

// 2. WhatsApp Format: Itinerary Rundown
$waItinerary = "*" . strtoupper($cleanTitle) . "*\n";
$waItinerary .= "*Rundown Agenda Perjalanan*\n";
$waItinerary .= "Travel: " . $package['brand_name'] . "\n\n";
$waItinerary .= "📅 Keberangkatan: " . $jadwalText . " (" . ($package['duration'] ?: '-') . ")\n";
$waItinerary .= "✈️ Penerbangan: " . $airlineStr . "\n\n";
$waItinerary .= "🗓️ *AGENDA HARIAN:*\n";

if (!empty($itinLines)) {
    foreach ($itinLines as $line) {
        $waItinerary .= "📍 " . $line . "\n";
    }
} else {
    $waItinerary .= "_Rincian agenda harian sedang disiapkan oleh tim operasional._\n";
}

$waItinerary .= "\n_Catatan: Jadwal dan ziarah dapat disesuaikan dengan kondisi operasional di lapangan demi kenyamanan & kelancaran jamaah._\n\n";
$waItinerary .= "Ada agenda atau kegiatan yang ingin ditanyakan lebih detail, Kak? Boleh kami kirimkan brosur PDF lengkapnya juga ya Kak 🙏";

$pageTitle = 'Detail Paket - ' . htmlspecialchars($package['name']) . ' - CS Umroh';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto space-y-6 pb-20" x-data="{ copied: false, copiedItin: false, copyTab: 'summary' }">

    <!-- Header Navigation & Actions (Proportional 12-Col Layout) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6 items-start pb-5 sm:pb-6 border-b border-zinc-200">
        <!-- Kolom Kiri: Breadcrumb, Judul & Metadata (Col 8) -->
        <div class="lg:col-span-8 space-y-2">
            <!-- Breadcrumb -->
            <div class="flex items-center gap-2 text-xs">
                <a href="packages.php" class="text-zinc-500 hover:text-black inline-flex items-center gap-1 font-semibold transition">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    <span>Master Paket Umroh</span>
                </a>
                <span class="text-zinc-300">/</span>
                <span class="font-semibold text-black">Detail Paket</span>
            </div>

            <!-- Judul & Badge Inline -->
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-extrabold text-black tracking-tight leading-snug">
                <span><?= htmlspecialchars($package['name']) ?></span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold align-middle ml-2 shadow-2xs whitespace-nowrap <?= $package['is_active'] ? 'bg-black text-white' : 'bg-zinc-100 text-zinc-600' ?>">
                    <?= $package['is_active'] ? 'Aktif' : 'Arsip' ?>
                </span>
            </h1>

            <!-- Metadata Info -->
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500 pt-0.5">
                <span>Travel: <strong class="text-zinc-800 font-semibold"><?= htmlspecialchars($package['brand_name']) ?></strong></span>
                <span class="text-zinc-300">&bull;</span>
                <span>Keberangkatan: <strong class="text-zinc-800 font-semibold"><?= htmlspecialchars($jadwalText) ?></strong> (<?= htmlspecialchars($package['duration'] ?: '-') ?>)</span>
                <?php if ($package['quota_remaining'] !== null && $package['quota_remaining'] !== ''): ?>
                    <span class="text-zinc-300">&bull;</span>
                    <span>Sisa: <strong class="text-zinc-800 font-semibold"><?= (int)$package['quota_remaining'] ?> Seat</strong></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Kolom Kanan: Tombol Aksi (Col 4, Aligned Right) -->
        <div class="lg:col-span-4 flex items-center lg:justify-end gap-2 shrink-0 pt-1 lg:pt-5">
            <a href="package_form.php?id=<?= $package['id'] ?>"
               class="px-4 py-2 bg-black hover:bg-zinc-800 text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center gap-1.5 whitespace-nowrap">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                <span>Edit Paket</span>
            </a>

            <!-- Toggle Archive Form -->
            <form method="POST" class="inline m-0">
                <input type="hidden" name="action" value="toggle_status">
                <button type="submit" 
                        class="px-3.5 py-2 border border-zinc-300 hover:border-black bg-white text-zinc-800 hover:text-black text-xs font-semibold rounded-xl transition flex items-center gap-1.5 shadow-2xs whitespace-nowrap">
                    <?php if ($package['is_active']): ?>
                        <svg class="w-3.5 h-3.5 text-zinc-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                        <span>Arsipkan</span>
                    <?php else: ?>
                        <svg class="w-3.5 h-3.5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Aktifkan</span>
                    <?php endif; ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Alert Notification -->
    <?php if (!empty($message)): ?>
        <div class="p-4 bg-zinc-900 text-white rounded-xl text-xs flex items-center justify-between shadow-sm">
            <span><?= htmlspecialchars($message) ?></span>
            <button type="button" onclick="this.parentElement.remove()" class="text-zinc-400 hover:text-white">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Main Content Layout (2 Columns: 8 Cols Content + 4 Cols Sidebar) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- ========================================== -->
        <!-- KOLOM KIRI (KONTEN UTAMA) - 8 COLS         -->
        <!-- ========================================== -->
        <div class="lg:col-span-8 space-y-6">

            <!-- 1. SKEMA HARGA & DP -->
            <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <h2 class="text-sm font-bold text-black uppercase tracking-wider text-[11px]">Skema Harga & DP</h2>
                    <?php if (!empty($package['is_promo'])): ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 border border-amber-300 text-amber-900">
                            <span>Promo: <?= htmlspecialchars($package['promo_discount'] ?: 'Diskon Spesial') ?></span>
                            <?php if (!empty($package['promo_deadline'])): ?>
                                <span class="opacity-70">&bull; s.d. <?= date('d M Y', strtotime($package['promo_deadline'])) ?></span>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <!-- Quad -->
                    <div class="p-3.5 bg-zinc-50 border border-zinc-200 rounded-xl space-y-1">
                        <span class="text-[11px] font-semibold text-zinc-500 block">Quad (Kamar Ber-4)</span>
                        <div class="text-base font-extrabold text-black"><?= htmlspecialchars($package['price_quad'] ?: $package['price']) ?></div>
                    </div>

                    <!-- Triple -->
                    <div class="p-3.5 bg-zinc-50 border border-zinc-200 rounded-xl space-y-1">
                        <span class="text-[11px] font-semibold text-zinc-500 block">Triple (Kamar Ber-3)</span>
                        <div class="text-base font-extrabold text-black"><?= htmlspecialchars($package['price_triple'] ?: '-') ?></div>
                    </div>

                    <!-- Double -->
                    <div class="p-3.5 bg-zinc-50 border border-zinc-200 rounded-xl space-y-1">
                        <span class="text-[11px] font-semibold text-zinc-500 block">Double (Kamar isi 2)</span>
                        <div class="text-base font-extrabold text-black"><?= htmlspecialchars($package['price_double'] ?: '-') ?></div>
                    </div>

                    <!-- Infant -->
                    <div class="p-3.5 bg-zinc-50 border border-zinc-200 rounded-xl space-y-1">
                        <span class="text-[11px] font-semibold text-zinc-500 block">Infant (&lt; 2 Thn)</span>
                        <div class="text-base font-extrabold text-black"><?= htmlspecialchars($package['price_infant'] ?: '-') ?></div>
                    </div>
                </div>

                <div class="p-3 bg-zinc-100/70 border border-zinc-200 rounded-xl flex items-center justify-between text-xs">
                    <span class="font-semibold text-zinc-700">Minimal Transfer Uang Muka (DP):</span>
                    <span class="font-extrabold text-black text-sm"><?= htmlspecialchars($package['dp']) ?></span>
                </div>
            </div>

            <!-- 2. PENERBANGAN & AKOMODASI HOTEL -->
            <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
                <div class="pb-3 border-b border-zinc-100">
                    <h2 class="text-sm font-bold text-black uppercase tracking-wider text-[11px]">Penerbangan & Akomodasi Hotel</h2>
                </div>

                <!-- Penerbangan -->
                <div class="flex items-center justify-between p-3.5 bg-zinc-50 border border-zinc-200 rounded-xl text-xs">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-black text-white flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"></path>
                            </svg>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-400 block font-semibold">Maskapai Penerbangan</span>
                            <span class="font-bold text-black text-sm"><?= htmlspecialchars($package['airline'] ?: 'TBA') ?></span>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= ($package['flight_type'] ?? 'direct') === 'direct' ? 'bg-black text-white' : 'bg-zinc-200 text-zinc-800' ?>">
                        <?= ($package['flight_type'] ?? 'direct') === 'direct' ? 'Direct' : 'Transit' ?>
                    </span>
                </div>

                <!-- Hotel Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <?php foreach ($hotels as $h): ?>
                        <div class="p-3.5 bg-zinc-50 border border-zinc-200 rounded-xl space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="px-2 py-0.5 rounded bg-zinc-200 text-zinc-800 text-[10px] font-bold">
                                    <?= htmlspecialchars($h['city'] ?? 'Hotel') ?>
                                </span>
                                <span class="text-amber-500 font-bold text-xs">
                                    <?= str_repeat('★', (int)($h['stars'] ?? 5)) ?>
                                </span>
                            </div>
                            <div class="font-bold text-black text-sm pt-0.5">
                                <?= htmlspecialchars($h['name'] ?: '-') ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 3. FASILITAS TERMASUK & TIDAK TERMASUK -->
            <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
                <div class="pb-3 border-b border-zinc-100">
                    <h2 class="text-sm font-bold text-black uppercase tracking-wider text-[11px]">Fasilitas Paket</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                    <!-- Termasuk -->
                    <div class="space-y-2">
                        <div class="font-bold text-black flex items-center gap-1.5 pb-1 border-b border-zinc-100">
                            <svg class="w-4 h-4 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            <span>Termasuk (Include)</span>
                        </div>
                        <?php 
                        $incItems = array_filter(array_map('trim', explode("\n", $package['facilities_included'] ?? '')));
                        ?>
                        <?php if (empty($incItems)): ?>
                            <p class="text-zinc-400 italic">Belum ada rincian fasilitas termasuk.</p>
                        <?php else: ?>
                            <ul class="space-y-1.5 text-zinc-700">
                                <?php foreach ($incItems as $item): ?>
                                    <li class="flex items-start gap-2">
                                        <span class="text-black font-bold mt-0.5">&bull;</span>
                                        <span><?= htmlspecialchars($item) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <!-- Tidak Termasuk -->
                    <div class="space-y-2">
                        <div class="font-bold text-zinc-600 flex items-center gap-1.5 pb-1 border-b border-zinc-100">
                            <svg class="w-4 h-4 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            <span>Tidak Termasuk (Exclude)</span>
                        </div>
                        <?php 
                        $excItems = array_filter(array_map('trim', explode("\n", $package['facilities_excluded'] ?? '')));
                        ?>
                        <?php if (empty($excItems)): ?>
                            <p class="text-zinc-400 italic">Belum ada rincian fasilitas tidak termasuk.</p>
                        <?php else: ?>
                            <ul class="space-y-1.5 text-zinc-500">
                                <?php foreach ($excItems as $item): ?>
                                    <li class="flex items-start gap-2">
                                        <span class="text-zinc-400 font-bold mt-0.5">&bull;</span>
                                        <span><?= htmlspecialchars($item) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 4. ITINERARY PERJALANAN -->
            <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
                <div class="pb-3 border-b border-zinc-100 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-black uppercase tracking-wider text-[11px]">Itinerary Perjalanan</h2>
                    <?php if (!empty($itinLines)): ?>
                        <button type="button" 
                                @click="navigator.clipboard.writeText(<?= htmlspecialchars(json_encode($waItinerary), ENT_QUOTES, 'UTF-8') ?>); copiedItin = true; setTimeout(() => copiedItin = false, 2000)" 
                                class="px-2.5 py-1 bg-zinc-100 hover:bg-black hover:text-white text-zinc-700 text-[11px] font-semibold rounded-lg transition flex items-center gap-1.5 shadow-2xs">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                            <span x-show="!copiedItin">Salin Format WA</span>
                            <span x-show="copiedItin" class="text-emerald-500 font-bold" x-cloak>Tersalin! ✓</span>
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (empty($itinLines)): ?>
                    <p class="text-xs text-zinc-400 italic">Belum ada rincian itinerary perjalanan.</p>
                <?php else: ?>
                    <div class="space-y-3 text-xs">
                        <?php foreach ($itinLines as $line): ?>
                            <div class="p-3 bg-zinc-50/70 border border-zinc-200/80 rounded-xl flex items-start gap-3">
                                <div class="w-2 h-2 rounded-full bg-black shrink-0 mt-1.5"></div>
                                <div class="text-zinc-800 leading-relaxed font-sans"><?= htmlspecialchars($line) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- ========================================== -->
        <!-- KOLOM KANAN (SIDEBAR INFO & ACTIONS) - 4   -->
        <!-- ========================================== -->
        <div class="lg:col-span-4 space-y-5 lg:sticky lg:top-20">

            <!-- PANEL 1: FLYER PAKET -->
            <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 shadow-xs space-y-3 text-xs">
                <div class="pb-2 border-b border-zinc-100 flex items-center justify-between">
                    <h3 class="font-bold text-black uppercase tracking-wider text-[11px]">Flyer Paket</h3>
                    <?php if (!empty($package['flyer_image'])): ?>
                        <a href="<?= get_base_url() ?>/<?= htmlspecialchars($package['flyer_image']) ?>" target="_blank" class="text-[11px] text-zinc-500 hover:text-black font-semibold">
                            Buka Resolusi Penuh &nearr;
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (!empty($package['flyer_image'])): ?>
                    <div class="rounded-xl overflow-hidden border border-zinc-200 bg-zinc-100">
                        <img src="<?= get_base_url() ?>/<?= htmlspecialchars($package['flyer_image']) ?>" alt="Flyer <?= htmlspecialchars($package['name']) ?>" class="w-full h-auto object-cover">
                    </div>
                <?php else: ?>
                    <div class="p-8 border-2 border-dashed border-zinc-200 rounded-xl text-center bg-zinc-50">
                        <svg class="w-8 h-8 text-zinc-300 mx-auto mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                        <p class="text-zinc-500 font-semibold text-xs">Belum Ada Gambar Flyer</p>
                        <a href="package_form.php?id=<?= $package['id'] ?>" class="text-[11px] text-black font-bold hover:underline mt-1 inline-block">
                            + Upload Flyer
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PANEL 2: SALIN FORMAT CHAT WHATSAPP -->
            <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 shadow-xs space-y-3 text-xs">
                <div class="pb-2 border-b border-zinc-100 flex items-center justify-between gap-2">
                    <!-- Tab Switcher -->
                    <div class="flex items-center gap-1 p-0.5 bg-zinc-100 rounded-lg">
                        <button type="button" 
                                @click="copyTab = 'summary'" 
                                :class="copyTab === 'summary' ? 'bg-white text-black font-bold shadow-2xs' : 'text-zinc-500 hover:text-black font-medium'" 
                                class="px-2.5 py-1 rounded-md text-[11px] transition">
                            Ringkasan
                        </button>
                        <button type="button" 
                                @click="copyTab = 'itinerary'" 
                                :class="copyTab === 'itinerary' ? 'bg-white text-black font-bold shadow-2xs' : 'text-zinc-500 hover:text-black font-medium'" 
                                class="px-2.5 py-1 rounded-md text-[11px] transition">
                            Itinerary
                        </button>
                    </div>

                    <!-- Copy Button Based on Active Tab -->
                    <button type="button" 
                            @click="navigator.clipboard.writeText(copyTab === 'summary' ? <?= htmlspecialchars(json_encode($waSummary), ENT_QUOTES, 'UTF-8') ?> : <?= htmlspecialchars(json_encode($waItinerary), ENT_QUOTES, 'UTF-8') ?>); copied = true; setTimeout(() => copied = false, 2000)" 
                            class="text-xs font-bold text-black hover:underline flex items-center gap-1 shrink-0">
                        <span x-show="!copied">Salin Teks</span>
                        <span x-show="copied" class="text-emerald-600 font-bold" x-cloak>Tersalin! ✓</span>
                    </button>
                </div>

                <!-- Textarea Tab 1: Ringkasan Paket -->
                <div x-show="copyTab === 'summary'">
                    <textarea readonly rows="6" class="w-full p-2.5 bg-zinc-50 border border-zinc-200 rounded-xl font-mono text-[11px] leading-relaxed text-zinc-700 select-all"><?= htmlspecialchars($waSummary) ?></textarea>
                    <p class="text-[10px] text-zinc-400 mt-1">Format ringkasan paket (harga, hotel & fasilitas) untuk prospek baru.</p>
                </div>

                <!-- Textarea Tab 2: Itinerary Harian -->
                <div x-show="copyTab === 'itinerary'" x-cloak>
                    <textarea readonly rows="6" class="w-full p-2.5 bg-zinc-50 border border-zinc-200 rounded-xl font-mono text-[11px] leading-relaxed text-zinc-700 select-all"><?= htmlspecialchars($waItinerary) ?></textarea>
                    <p class="text-[10px] text-zinc-400 mt-1">Format rundown harian untuk prospek yang menanyakan jadwal & ziarah.</p>
                </div>
            </div>

            <!-- PANEL 3: METADATA -->
            <div class="bg-zinc-50 border border-zinc-200 rounded-2xl p-4 text-[11px] text-zinc-500 space-y-1.5">
                <div class="flex justify-between">
                    <span>ID Paket:</span>
                    <strong class="text-zinc-800 font-mono">#<?= $package['id'] ?></strong>
                </div>
                <div class="flex justify-between">
                    <span>Terdaftar Pada:</span>
                    <strong class="text-zinc-800"><?= date('d M Y H:i', strtotime($package['created_at'])) ?></strong>
                </div>
            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

