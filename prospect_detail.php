<?php
$pageTitle = 'Detail Prospek 360° - CRM Umroh';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/data/scripts_loader.php';

$db = get_db();
$currentUser = get_logged_user();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo "<script>window.location.href='prospects.php';</script>";
    exit;
}

// Fetch prospect details with complete CRM, Brand, and Package joins
$sql = "
    SELECT p.*, 
           b.name AS brand_name, b.code AS brand_code, b.ppiu_number, 
           b.bank_name, b.bank_account_number, b.bank_account_holder, b.phone AS brand_phone,
           pkg.name AS package_name, pkg.price AS package_price, pkg.dp AS package_dp,
           pkg.airline AS package_airline, pkg.duration AS package_duration,
           pkg.hotel_makkah AS package_hotel_makkah, pkg.hotel_madinah AS package_hotel_madinah,
           pkg.departure_info AS package_departure_info, pkg.highlights AS package_highlights,
           pkg.departure_date, pkg.flight_type, pkg.quota_remaining,
           pkg.price_quad, pkg.price_triple, pkg.price_double, pkg.price_infant,
           pkg.facilities_included, pkg.facilities_excluded, pkg.itinerary, 
           pkg.is_promo, pkg.promo_discount, pkg.promo_deadline, pkg.flyer_image,
           u.name AS cs_name, u.email AS cs_email
    FROM prospects p
    JOIN brands b ON p.brand_id = b.id
    LEFT JOIN packages pkg ON p.package_id = pkg.id
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
";
$params = [$id];
if ($currentUser['role'] !== 'superadmin') {
    $sql .= " AND p.brand_id = ?";
    $params[] = $currentUser['brand_id'];
}
$stmt = $db->prepare($sql);
$stmt->execute($params);
$prospect = $stmt->fetch();

if (!$prospect) {
    echo "<div class='max-w-4xl mx-auto py-16 text-center text-zinc-500'>
            <p class='text-sm mb-3'>Data prospek tidak ditemukan atau Anda tidak memiliki hak akses.</p>
            <a href='prospects.php' class='inline-flex items-center gap-1.5 px-4 py-2 bg-black text-white text-xs font-semibold rounded-xl hover:bg-zinc-800 transition'>
                Kembali ke Data Prospek
            </a>
          </div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Fetch all active packages for this brand (for package switcher & edit modal)
$stmt = $db->prepare("
    SELECT id, brand_id, name, price, dp, price_quad, price_triple, price_double, price_infant,
           quota_remaining, departure_date, duration, airline, hotel_makkah, hotel_madinah, flyer_image
    FROM packages 
    WHERE brand_id = ? AND is_active = 1 
    ORDER BY departure_date ASC, id DESC
");
$stmt->execute([$prospect['brand_id']]);
$packages = $stmt->fetchAll();

// Fetch activity audit logs
$stmt = $db->prepare("
    SELECT pl.*, u.name as user_name
    FROM prospect_logs pl
    LEFT JOIN users u ON pl.user_id = u.id
    WHERE pl.prospect_id = ?
    ORDER BY pl.created_at DESC, pl.id DESC
");
$stmt->execute([$prospect['id']]);
$logs = $stmt->fetchAll();

// Calculate Departure Countdown
$daysToDeparture = null;
if (!empty($prospect['departure_date'])) {
    $deptTs = strtotime($prospect['departure_date']);
    $todayTs = strtotime(date('Y-m-d'));
    $daysToDeparture = (int)round(($deptTs - $todayTs) / 86400);
}

// Calculate Financials
$cleanPrice = function($str) {
    if (!$str) return 0;
    return (float)preg_replace('/[^\d]/', '', (string)$str);
};
$defPrice = $cleanPrice($prospect['package_price'] ?? 0);
$pQuad = $cleanPrice($prospect['price_quad'] ?? $defPrice);
$pTriple = $cleanPrice($prospect['price_triple'] ?? $defPrice);
$pDouble = $cleanPrice($prospect['price_double'] ?? $defPrice);
$pInfant = $cleanPrice($prospect['price_infant'] ?? 0);
$pDp = $cleanPrice($prospect['package_dp'] ?? 0);

$totalAdultPax = (int)$prospect['pax_quad'] + (int)$prospect['pax_triple'] + (int)$prospect['pax_double'];
$totalAllPax = $totalAdultPax + (int)$prospect['pax_infant'];
$targetDp = $totalAdultPax * $pDp;
?>

<div class="max-w-6xl mx-auto space-y-6 pb-20" x-data="prospect360Page()">

    <!-- ============================================================== -->
    <!-- 1. BREADCRUMBS & TOP ACTIONS                                   -->
    <!-- ============================================================== -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-zinc-500">
        <div class="flex items-center gap-2">
            <a href="prospects.php" class="text-zinc-600 hover:text-black font-medium inline-flex items-center gap-1 transition">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
                <span>Pipeline Prospek</span>
            </a>
            <span class="text-zinc-300">/</span>
            <span class="font-mono text-zinc-400">#<?= $prospect['id'] ?></span>
            <span class="text-zinc-300">&bull;</span>
            <span class="text-zinc-700 font-medium"><?= htmlspecialchars($prospect['brand_name']) ?></span>
        </div>

        <div class="flex items-center gap-2">
            <?php if (!empty($prospect['phone'])): ?>
                <a href="https://wa.me/<?= $prospect['phone'] ?>" target="_blank"
                   class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-lg text-xs font-medium transition inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 21 1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"/></svg>
                    <span>WhatsApp</span>
                </a>
            <?php endif; ?>

            <a href="chat.php?prospect_id=<?= $prospect['id'] ?><?= !empty($prospect['remote_jid']) ? ('&jid=' . urlencode($prospect['remote_jid'])) : '' ?>"
               class="px-3 py-1.5 bg-white hover:bg-zinc-50 border border-zinc-200 text-zinc-700 rounded-lg text-xs font-medium transition inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-zinc-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <span>Live Chat</span>
            </a>

            <a href="index.php?load_prospect=<?= $prospect['id'] ?>"
               class="px-3 py-1.5 bg-black hover:bg-zinc-800 text-white rounded-lg text-xs font-medium transition inline-flex items-center gap-1.5 shadow-2xs">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                <span>Copilot</span>
            </a>

            <button type="button" @click="openEditModal()"
                    class="px-3 py-1.5 bg-white hover:bg-zinc-50 border border-zinc-200 text-zinc-700 rounded-lg text-xs font-medium transition inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-zinc-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                <span>Edit</span>
            </button>

            <button type="button" @click="deleteProspect()" title="Hapus Prospek"
                    class="p-1.5 text-zinc-400 hover:text-rose-600 rounded-lg transition">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- 2. CLEAN HEADER & SLIM PIPELINE PROGRESS STRIP                 -->
    <!-- ============================================================== -->
    <div class="bg-white border border-zinc-200/80 rounded-2xl p-6 shadow-2xs space-y-5">
        <!-- Lead Main Info -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <!-- Avatar -->
                <div class="shrink-0">
                    <?php if (!empty($prospect['photo_url'])): ?>
                        <img src="<?= htmlspecialchars($prospect['photo_url']) ?>" 
                             alt="<?= htmlspecialchars($prospect['name']) ?>"
                             class="w-13 h-13 rounded-full object-cover border border-zinc-200"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                    <?php endif; ?>
                    <div class="<?= !empty($prospect['photo_url']) ? 'hidden' : 'flex' ?> w-13 h-13 rounded-full bg-zinc-900 text-white font-bold text-base items-center justify-center">
                        <?php
                            $words = explode(' ', trim($prospect['name']));
                            $initials = strtoupper(substr($words[0] ?? 'J', 0, 1) . substr($words[1] ?? '', 0, 1));
                            echo $initials ?: 'JM';
                        ?>
                    </div>
                </div>

                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-bold text-zinc-900 tracking-tight" x-text="prospect.name"></h1>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium"
                              :class="getStatusBadgeClass(prospect.status)"
                              x-text="getStatusLabel(prospect.status)"></span>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500">
                        <span class="inline-flex items-center gap-1 font-mono text-zinc-700">
                            <span x-text="prospect.phone || '-'"></span>
                        </span>
                        <span>&bull;</span>
                        <span x-text="prospect.city || 'Domisili Belum Dicatat'"></span>
                        <span>&bull;</span>
                        <span>Sumber: <strong class="text-zinc-700 capitalize" x-text="formatLeadSource(prospect.lead_source)"></strong></span>
                        <span>&bull;</span>
                        <span>PIC: <strong class="text-zinc-700"><?= htmlspecialchars($prospect['cs_name'] ?? 'CS') ?></strong></span>
                    </div>
                </div>
            </div>

            <!-- Financial Quick Badge -->
            <div class="sm:text-right">
                <span class="text-[11px] text-zinc-400 block font-medium">Nilai Transaksi</span>
                <span class="text-2xl font-bold font-mono text-zinc-900 tracking-tight" x-text="formatRupiah(prospect.deal_value)"></span>
                <div class="text-[11px] text-zinc-500 mt-0.5 flex sm:justify-end items-center gap-2">
                    <span x-text="calcTotalPax() + ' Pax'"></span>
                    <span>&bull;</span>
                    <span :class="parseFloat(prospect.dp_amount) > 0 ? 'text-emerald-700 font-medium' : 'text-zinc-400'"
                          x-text="'DP ' + formatRupiah(prospect.dp_amount)"></span>
                </div>
            </div>
        </div>

        <!-- Sleek Slim Pipeline Track -->
        <div class="pt-4 border-t border-zinc-100">
            <div class="flex items-center justify-between text-[11px] text-zinc-400 mb-2 font-medium">
                <span>Tahapan Konversi</span>
                <button type="button" @click="openLostModalForm()" 
                        class="text-zinc-400 hover:text-rose-600 transition"
                        :class="prospect.status === 'closed_lost' ? 'text-rose-600 font-semibold' : ''">
                    <span x-text="prospect.status === 'closed_lost' ? 'Status: Closed Lost' : 'Tandai Batal (Lost)'"></span>
                </button>
            </div>

            <!-- Slim Stepper Segments -->
            <div class="flex items-center gap-1 sm:gap-1.5">
                <template x-for="(st, idx) in pipelineStages" :key="st.key">
                    <button type="button" @click="onStageChevronClick(st.key)"
                            :title="'Pindah ke: ' + st.label"
                            class="flex-1 py-1.5 px-2 rounded-lg text-[11px] font-medium transition text-center truncate border"
                            :class="getSegmentClass(st.key, idx)">
                        <span x-text="st.short"></span>
                    </button>
                </template>
            </div>

            <!-- Alert if closed_lost -->
            <template x-if="prospect.status === 'closed_lost'">
                <div class="mt-3 p-3 bg-zinc-50 border border-zinc-200 rounded-xl flex items-center justify-between text-xs text-zinc-700">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                        <span>Prospek Batal (Closed Lost): <strong x-text="formatLostReason(prospect.lost_reason)"></strong></span>
                        <span class="text-zinc-400" x-text="prospect.lost_reason_detail ? ('(' + prospect.lost_reason_detail + ')') : ''"></span>
                    </div>
                    <button type="button" @click="quickUpdateStage('followup')" 
                            class="text-xs font-semibold text-black hover:underline">
                        Aktifkan Kembali
                    </button>
                </div>
            </template>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- 3. BALANCED 2-COLUMN CRM WORKSPACE                             -->
    <!-- ============================================================== -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- ========================================================== -->
        <!-- LEFT COLUMN (7 cols): PENAWARAN PAKET & KUALIFIKASI (NPGD) -->
        <!-- ========================================================== -->
        <div class="lg:col-span-7 space-y-6">

            <!-- Card 1: Paket Umroh & Kalkulator Kamar -->
            <div class="bg-white border border-zinc-200/80 rounded-2xl p-6 shadow-2xs space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <div>
                        <h2 class="text-sm font-bold text-zinc-900">Paket & Rincian Kamar</h2>
                        <p class="text-xs text-zinc-400 mt-0.5">Pilihan program umroh, alokasi tipe kamar, dan kalkulasi nilai transaksi.</p>
                    </div>

                    <!-- Package Selector Custom Dropdown -->
                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                        <button type="button" @click="open = !open" 
                                class="px-2.5 py-1 text-xs font-medium text-zinc-600 hover:text-black border border-zinc-200 rounded-lg bg-zinc-50 hover:bg-zinc-100 transition inline-flex items-center gap-1">
                            <span x-text="prospect.package_id ? 'Ganti Paket' : 'Pilih Paket'"></span>
                            <svg class="w-3 h-3 text-zinc-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                             class="absolute right-0 z-50 mt-1.5 w-72 bg-white border border-zinc-200 rounded-xl shadow-xl py-1 text-xs divide-y divide-zinc-50">
                            <button type="button" @click="selectPackage(null); open = false"
                                    class="w-full px-3 py-2 text-left hover:bg-zinc-50 text-zinc-500 italic">
                                -- Belum Memilih Paket --
                            </button>
                            <template x-for="pkg in packagesList" :key="pkg.id">
                                <button type="button" @click="selectPackage(pkg.id); open = false"
                                        class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                        :class="prospect.package_id == pkg.id ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                    <div class="truncate pr-2">
                                        <div class="truncate font-medium text-zinc-900" x-text="pkg.name"></div>
                                        <div class="text-[10px] text-zinc-400 font-mono" x-text="'Rp ' + pkg.price + ' &bull; ' + (pkg.airline || '')"></div>
                                    </div>
                                    <svg x-show="prospect.package_id == pkg.id" class="w-3.5 h-3.5 text-black shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Package Info (if selected) -->
                <template x-if="prospect.package_id && currentPackage">
                    <div class="space-y-4">
                        <div>
                            <div class="text-base font-bold text-zinc-900" x-text="currentPackage.name"></div>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500 mt-1">
                                <span class="font-medium text-zinc-800" x-text="currentPackage.airline || 'Penerbangan Sesuai Jadwal'"></span>
                                <span>&bull;</span>
                                <span x-text="currentPackage.duration || '9 Hari'"></span>
                                <span>&bull;</span>
                                <span x-text="'Makkah: ' + (currentPackage.hotel_makkah || '-')"></span>
                                <span>&bull;</span>
                                <span x-text="'Madinah: ' + (currentPackage.hotel_madinah || '-')"></span>
                            </div>
                        </div>

                        <!-- Departure Date & Quota Pills -->
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <div class="px-2.5 py-1 bg-zinc-100 rounded-lg text-zinc-700 font-medium inline-flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <span x-text="prospect.departure_date ? formatDateOnly(prospect.departure_date) : (prospect.package_departure_info || 'Jadwal Reguler')"></span>
                            </div>

                            <template x-if="currentPackage.quota_remaining !== null">
                                <div class="px-2.5 py-1 rounded-lg text-xs font-medium inline-flex items-center gap-1.5"
                                     :class="currentPackage.quota_remaining <= 5 ? 'bg-rose-50 text-rose-800 border border-rose-200' : 'bg-zinc-100 text-zinc-700'">
                                    <span>Sisa Kuota: <strong x-text="currentPackage.quota_remaining + ' Seat'"></strong></span>
                                </div>
                            </template>

                            <button type="button" @click="copyPackageFacilities()"
                                    class="px-2.5 py-1 text-xs text-zinc-600 hover:text-black hover:bg-zinc-100 rounded-lg transition inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                <span>Salin Rincian</span>
                            </button>

                            <template x-if="currentPackage.flyer_image">
                                <button type="button" @click="showFlyerModal = true"
                                        class="px-2.5 py-1 text-xs text-zinc-600 hover:text-black hover:bg-zinc-100 rounded-lg transition inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                                    <span>Lihat Flyer</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="!prospect.package_id">
                    <div class="py-4 text-center text-xs text-zinc-400">
                        Belum memilih paket umroh. Gunakan menu "Pilih Paket" di atas untuk menghubungkan paket.
                    </div>
                </template>

                <!-- Room Stepper List (Clean, minimal rows) -->
                <div class="pt-3 border-t border-zinc-100 space-y-2">
                    <div class="flex items-center justify-between text-[11px] font-medium text-zinc-400 uppercase tracking-wider mb-1">
                        <span>Pilihan Kamar</span>
                        <span>Jumlah Jamaah</span>
                    </div>

                    <!-- Quad -->
                    <div class="flex items-center justify-between py-2 border-b border-zinc-50 text-xs">
                        <div>
                            <span class="font-medium text-zinc-900">Kamar Quad (4 Org)</span>
                            <span class="text-zinc-400 ml-2 font-mono" x-text="formatRupiah(getPackageRoomPrice('quad'))"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="stepPax('pax_quad', -1)" :disabled="isUpdatingPax || prospect.pax_quad <= 0"
                                    class="w-6 h-6 flex items-center justify-center rounded border border-zinc-200 hover:bg-zinc-100 text-zinc-700 disabled:opacity-30">&minus;</button>
                            <span class="w-6 text-center font-bold font-mono" x-text="prospect.pax_quad || 0"></span>
                            <button type="button" @click="stepPax('pax_quad', 1)" :disabled="isUpdatingPax"
                                    class="w-6 h-6 flex items-center justify-center rounded border border-zinc-200 hover:bg-zinc-100 text-zinc-700 disabled:opacity-30">&plus;</button>
                        </div>
                    </div>

                    <!-- Triple -->
                    <div class="flex items-center justify-between py-2 border-b border-zinc-50 text-xs">
                        <div>
                            <span class="font-medium text-zinc-900">Kamar Triple (3 Org)</span>
                            <span class="text-zinc-400 ml-2 font-mono" x-text="formatRupiah(getPackageRoomPrice('triple'))"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="stepPax('pax_triple', -1)" :disabled="isUpdatingPax || prospect.pax_triple <= 0"
                                    class="w-6 h-6 flex items-center justify-center rounded border border-zinc-200 hover:bg-zinc-100 text-zinc-700 disabled:opacity-30">&minus;</button>
                            <span class="w-6 text-center font-bold font-mono" x-text="prospect.pax_triple || 0"></span>
                            <button type="button" @click="stepPax('pax_triple', 1)" :disabled="isUpdatingPax"
                                    class="w-6 h-6 flex items-center justify-center rounded border border-zinc-200 hover:bg-zinc-100 text-zinc-700 disabled:opacity-30">&plus;</button>
                        </div>
                    </div>

                    <!-- Double -->
                    <div class="flex items-center justify-between py-2 border-b border-zinc-50 text-xs">
                        <div>
                            <span class="font-medium text-zinc-900">Kamar Double (2 Org)</span>
                            <span class="text-zinc-400 ml-2 font-mono" x-text="formatRupiah(getPackageRoomPrice('double'))"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="stepPax('pax_double', -1)" :disabled="isUpdatingPax || prospect.pax_double <= 0"
                                    class="w-6 h-6 flex items-center justify-center rounded border border-zinc-200 hover:bg-zinc-100 text-zinc-700 disabled:opacity-30">&minus;</button>
                            <span class="w-6 text-center font-bold font-mono" x-text="prospect.pax_double || 0"></span>
                            <button type="button" @click="stepPax('pax_double', 1)" :disabled="isUpdatingPax"
                                    class="w-6 h-6 flex items-center justify-center rounded border border-zinc-200 hover:bg-zinc-100 text-zinc-700 disabled:opacity-30">&plus;</button>
                        </div>
                    </div>

                    <!-- Infant -->
                    <div class="flex items-center justify-between py-2 text-xs">
                        <div>
                            <span class="font-medium text-zinc-900">Bayi / Infant (&lt; 2 Thn)</span>
                            <span class="text-zinc-400 ml-2 font-mono" x-text="formatRupiah(getPackageRoomPrice('infant'))"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="stepPax('pax_infant', -1)" :disabled="isUpdatingPax || prospect.pax_infant <= 0"
                                    class="w-6 h-6 flex items-center justify-center rounded border border-zinc-200 hover:bg-zinc-100 text-zinc-700 disabled:opacity-30">&minus;</button>
                            <span class="w-6 text-center font-bold font-mono" x-text="prospect.pax_infant || 0"></span>
                            <button type="button" @click="stepPax('pax_infant', 1)" :disabled="isUpdatingPax"
                                    class="w-6 h-6 flex items-center justify-center rounded border border-zinc-200 hover:bg-zinc-100 text-zinc-700 disabled:opacity-30">&plus;</button>
                        </div>
                    </div>
                </div>

                <!-- Financial Summary Bar -->
                <div class="pt-4 border-t border-zinc-100 flex flex-wrap items-center justify-between gap-4 text-xs">
                    <div class="flex items-center gap-6">
                        <div>
                            <span class="text-zinc-400 block text-[11px]">Total Nilai Deal</span>
                            <span class="font-bold font-mono text-zinc-900 text-base" x-text="formatRupiah(prospect.deal_value)"></span>
                        </div>
                        <div>
                            <span class="text-zinc-400 block text-[11px]">DP Terbayar</span>
                            <span class="font-bold font-mono text-emerald-700 text-base" x-text="formatRupiah(prospect.dp_amount)"></span>
                        </div>
                        <div>
                            <span class="text-zinc-400 block text-[11px]">Sisa Tagihan</span>
                            <span class="font-bold font-mono text-zinc-800 text-base" x-text="formatRupiah(calcBalanceDue())"></span>
                        </div>
                    </div>

                    <button type="button" @click="openPaymentModal()"
                            class="px-3 py-1.5 bg-zinc-900 hover:bg-black text-white rounded-lg text-xs font-medium transition shadow-2xs">
                        Update DP
                    </button>
                </div>
            </div>

            <!-- Card 2: Kualifikasi Kebutuhan Jamaah (NPGD) & Dokumen -->
            <div class="bg-white border border-zinc-200/80 rounded-2xl p-6 shadow-2xs space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <div>
                        <h2 class="text-sm font-bold text-zinc-900">Kualifikasi Kebutuhan & Dokumen</h2>
                        <p class="text-xs text-zinc-400 mt-0.5">Parameter discovery kebutuhan jamaah (NPGD) dan kesiapan administrasi.</p>
                    </div>
                    <button type="button" @click="openEditModal()" class="text-xs text-zinc-500 hover:text-black font-medium">
                        Ubah
                    </button>
                </div>

                <!-- Clean Editorial Grid (No boxes inside boxes!) -->
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-y-4 gap-x-6 text-xs">
                    <div>
                        <span class="text-zinc-400 block text-[11px] font-medium">Target Keberangkatan</span>
                        <span class="font-semibold text-zinc-900 mt-0.5 block" x-text="prospect.target_month || '-'"></span>
                    </div>
                    <div>
                        <span class="text-zinc-400 block text-[11px] font-medium">Kisaran Budget</span>
                        <span class="font-semibold text-zinc-900 mt-0.5 block" x-text="prospect.budget_range || '-'"></span>
                    </div>
                    <div>
                        <span class="text-zinc-400 block text-[11px] font-medium">Preferensi Kamar</span>
                        <span class="font-semibold text-zinc-900 mt-0.5 block capitalize" x-text="prospect.room_preference ? ('Kamar ' + prospect.room_preference) : '-'"></span>
                    </div>
                    <div>
                        <span class="text-zinc-400 block text-[11px] font-medium">Pengambil Keputusan</span>
                        <span class="font-semibold text-zinc-900 mt-0.5 block capitalize" x-text="formatDecisionMaker(prospect.decision_maker)"></span>
                    </div>
                    <div>
                        <span class="text-zinc-400 block text-[11px] font-medium">Status Paspor</span>
                        <span class="font-semibold mt-0.5 inline-block"
                              :class="prospect.passport_status === 'sudah_ada' ? 'text-emerald-700' : 'text-zinc-900'"
                              x-text="formatPassportStatus(prospect.passport_status)"></span>
                    </div>
                    <div>
                        <span class="text-zinc-400 block text-[11px] font-medium">Vaksin Meningitis</span>
                        <span class="font-semibold mt-0.5 inline-block"
                              :class="prospect.vaccine_status === 'sudah' ? 'text-emerald-700' : 'text-zinc-900'"
                              x-text="prospect.vaccine_status === 'sudah' ? 'Sudah Vaksin' : 'Belum Vaksin'"></span>
                    </div>
                </div>

                <!-- Kebutuhan Khusus -->
                <div class="pt-3 border-t border-zinc-100 text-xs">
                    <span class="text-zinc-400 block text-[11px] font-medium mb-1">Kebutuhan Khusus / Kondisi Fisik</span>
                    <p class="text-zinc-700 leading-relaxed" x-text="prospect.special_needs || 'Tidak ada catatan kebutuhan khusus.'"></p>
                </div>

                <!-- Catatan Tambahan -->
                <template x-if="prospect.notes">
                    <div class="pt-3 border-t border-zinc-100 text-xs">
                        <span class="text-zinc-400 block text-[11px] font-medium mb-1">Catatan Tambahan Jamaah</span>
                        <p class="text-zinc-700 leading-relaxed whitespace-pre-line" x-text="prospect.notes"></p>
                    </div>
                </template>
            </div>

        </div>

        <!-- ========================================================== -->
        <!-- RIGHT COLUMN (5 cols): ACTION HUB, SCRIPTS & TIMELINE      -->
        <!-- ========================================================== -->
        <div class="lg:col-span-5 space-y-6">

            <!-- Card 1: Catat Hasil Interaksi & Jadwal Follow-up -->
            <div class="bg-white border border-zinc-200/80 rounded-2xl p-6 shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <h2 class="text-sm font-bold text-zinc-900">Catat Follow-Up</h2>
                    <span class="text-[11px] text-zinc-400">
                        Terakhir: <strong class="text-zinc-600" x-text="prospect.last_followup_at ? formatTimeAgo(prospect.last_followup_at) : 'Belum pernah'"></strong>
                    </span>
                </div>

                <!-- Fast Chips -->
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="chip in quickChips" :key="chip">
                        <button type="button" @click="appendQuickChip(chip)"
                                class="px-2.5 py-1 bg-zinc-50 hover:bg-zinc-100 text-zinc-600 border border-zinc-200/70 rounded-lg text-[11px] transition">
                            <span x-text="chip"></span>
                        </button>
                    </template>
                </div>

                <form @submit.prevent="submitFollowup()" class="space-y-3 text-xs">
                    <div>
                        <textarea x-model="followupForm.note" required rows="3"
                                  class="w-full px-3 py-2 border border-zinc-200 rounded-xl focus:outline-none focus:border-black text-xs placeholder-zinc-400"
                                  placeholder="Catatan hasil percakapan atau janji follow-up..."></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <!-- Custom Dropdown: Update Status -->
                        <div>
                            <label class="block text-zinc-500 mb-1 font-medium text-[11px]">Update Status</label>
                            <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                                <button type="button" @click="open = !open"
                                        class="w-full flex items-center justify-between px-2.5 py-1.5 border border-zinc-200 bg-white rounded-lg text-xs font-medium text-zinc-900 focus:outline-none focus:border-black shadow-2xs">
                                    <span class="truncate" x-text="getStatusLabel(followupForm.status)"></span>
                                    <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                                </button>
                                <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                                     class="absolute z-50 mt-1 w-full bg-white border border-zinc-200 rounded-xl shadow-xl max-h-56 overflow-y-auto py-1 text-xs divide-y divide-zinc-50">
                                    <template x-for="st in statusOptions" :key="st.val">
                                        <button type="button" @click="followupForm.status = st.val; open = false"
                                                class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                                :class="followupForm.status === st.val ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                            <span x-text="st.label"></span>
                                            <svg x-show="followupForm.status === st.val" class="w-3.5 h-3.5 text-black shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-zinc-500 mb-1 font-medium text-[11px]">Follow-Up Berikutnya</label>
                            <input type="date" x-model="followupForm.next_followup_date"
                                   class="w-full px-2.5 py-1.5 border border-zinc-200 rounded-lg text-xs font-mono">
                        </div>
                    </div>

                    <button type="submit" :disabled="isSubmittingFollowup"
                            class="w-full py-2 bg-black hover:bg-zinc-800 text-white rounded-xl text-xs font-medium transition flex items-center justify-center gap-1.5 shadow-2xs disabled:opacity-50">
                        <span x-text="isSubmittingFollowup ? 'Menyimpan...' : 'Simpan Follow-Up'"></span>
                    </button>
                </form>
            </div>

            <!-- Card 2: Skrip Percakapan Relevan (Quick Copy) -->
            <div class="bg-white border border-zinc-200/80 rounded-2xl p-6 shadow-2xs space-y-4" x-data="{ scriptTab: '<?= $prospect['status'] === 'closing' ? 'closing' : ($prospect['status'] === 'objection' ? 'objection' : 'followup') ?>' }">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <h2 class="text-sm font-bold text-zinc-900">Skrip Obrolan WhatsApp</h2>
                    
                    <!-- Segmented Switcher -->
                    <div class="flex items-center bg-zinc-100 p-0.5 rounded-lg text-[11px]">
                        <button type="button" @click="scriptTab = 'followup'"
                                class="px-2.5 py-1 rounded-md font-medium transition"
                                :class="scriptTab === 'followup' ? 'bg-white text-black shadow-2xs' : 'text-zinc-500 hover:text-black'">
                            Follow-Up
                        </button>
                        <button type="button" @click="scriptTab = 'closing'"
                                class="px-2.5 py-1 rounded-md font-medium transition"
                                :class="scriptTab === 'closing' ? 'bg-white text-black shadow-2xs' : 'text-zinc-500 hover:text-black'">
                            Closing
                        </button>
                        <button type="button" @click="scriptTab = 'objection'"
                                class="px-2.5 py-1 rounded-md font-medium transition"
                                :class="scriptTab === 'objection' ? 'bg-white text-black shadow-2xs' : 'text-zinc-500 hover:text-black'">
                            Keberatan
                        </button>
                    </div>
                </div>

                <!-- Scripts List -->
                <div class="space-y-3">
                    <template x-for="(sc, scIdx) in getCuratedScripts(scriptTab)" :key="scIdx">
                        <div class="p-3 bg-zinc-50/70 border border-zinc-200/60 rounded-xl space-y-2 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-zinc-900" x-text="sc.title"></span>
                                <button type="button" @click="window.copyToClipboard(sc.text, 'Skrip berhasil disalin!')"
                                        class="text-xs text-zinc-500 hover:text-black font-medium inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                    <span>Salin</span>
                                </button>
                            </div>
                            <p class="text-zinc-600 text-[11px] leading-relaxed whitespace-pre-line" x-text="sc.text"></p>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Card 3: Riwayat Aktivitas & Timeline -->
            <div class="bg-white border border-zinc-200/80 rounded-2xl p-6 shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <h2 class="text-sm font-bold text-zinc-900">Riwayat Aktivitas</h2>
                    <span class="text-xs text-zinc-400 font-mono" x-text="logsList.length + ' catatan'"></span>
                </div>

                <!-- Timeline List -->
                <div class="relative pl-5 space-y-4 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-px before:bg-zinc-200 max-h-80 overflow-y-auto pr-1">
                    <template x-for="(item, idx) in logsList" :key="item.id || idx">
                        <div class="relative">
                            <div class="absolute -left-5 top-1.5 w-2 h-2 rounded-full bg-zinc-900 ring-4 ring-white"></div>
                            <div class="text-xs">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-zinc-900" x-text="item.title"></span>
                                    <span class="text-[10px] text-zinc-400 font-mono" x-text="formatDateShort(item.created_at)"></span>
                                </div>
                                <p class="text-zinc-600 mt-0.5 text-[11px] leading-relaxed whitespace-pre-line" x-text="item.description || '-'"></p>
                            </div>
                        </div>
                    </template>

                    <template x-if="logsList.length === 0">
                        <div class="text-center py-4 text-zinc-400 text-xs">
                            Belum ada riwayat aktivitas.
                        </div>
                    </template>
                </div>
            </div>

        </div>

    </div>

    <!-- ============================================================== -->
    <!-- 4. MODALS DENGAN 100% CUSTOM DROPDOWNS                        -->
    <!-- ============================================================== -->

    <!-- Modal 1: Edit Profil CRM -->
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/50 flex items-center justify-center p-4">
        <div @click.away="showEditModal = false" class="bg-white border border-zinc-200 rounded-2xl max-w-xl w-full p-6 shadow-xl relative max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 mb-4 border-b border-zinc-100">
                <h3 class="text-sm font-bold text-zinc-900">Edit Profil Prospek CRM</h3>
                <button type="button" @click="showEditModal = false" class="text-zinc-400 hover:text-black text-xl font-bold">&times;</button>
            </div>

            <form @submit.prevent="saveProspectEdit()" class="space-y-4 text-xs pb-6">
                <!-- Nama & WhatsApp -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-zinc-700 mb-1">Nama Jamaah *</label>
                        <input type="text" x-model="editForm.name" required class="w-full px-3 py-2 border border-zinc-200 rounded-lg focus:border-black outline-none font-semibold">
                    </div>
                    <div>
                        <label class="block font-medium text-zinc-700 mb-1">No. WhatsApp *</label>
                        <input type="text" x-model="editForm.phone" required class="w-full px-3 py-2 border border-zinc-200 rounded-lg focus:border-black outline-none font-mono">
                    </div>
                </div>

                <!-- Kota & Sumber Lead (Custom Dropdown) -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-zinc-700 mb-1">Kota Domisili</label>
                        <input type="text" x-model="editForm.city" class="w-full px-3 py-2 border border-zinc-200 rounded-lg focus:border-black outline-none" placeholder="Contoh: Bandung">
                    </div>
                    <div>
                        <label class="block font-medium text-zinc-700 mb-1">Sumber Lead</label>
                        <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                            <button type="button" @click="open = !open"
                                    class="w-full flex items-center justify-between px-3 py-2 border border-zinc-200 bg-white rounded-lg text-xs font-medium text-zinc-900 focus:outline-none focus:border-black shadow-2xs">
                                <span class="truncate" x-text="getLeadSourceLabel(editForm.lead_source)"></span>
                                <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                                 class="absolute z-50 mt-1 w-full bg-white border border-zinc-200 rounded-xl shadow-xl max-h-52 overflow-y-auto py-1 text-xs divide-y divide-zinc-50">
                                <template x-for="opt in leadSourceOptions" :key="opt.val">
                                    <button type="button" @click="editForm.lead_source = opt.val; open = false"
                                            class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                            :class="editForm.lead_source === opt.val ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                        <span x-text="opt.label"></span>
                                        <svg x-show="editForm.lead_source === opt.val" class="w-3.5 h-3.5 text-black shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Target Periode & Kisaran Budget (Custom Dropdown) -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-zinc-700 mb-1">Target Periode</label>
                        <input type="text" x-model="editForm.target_month" class="w-full px-3 py-2 border border-zinc-200 rounded-lg focus:border-black outline-none" placeholder="Contoh: November 2026">
                    </div>
                    <div>
                        <label class="block font-medium text-zinc-700 mb-1">Kisaran Budget</label>
                        <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                            <button type="button" @click="open = !open"
                                    class="w-full flex items-center justify-between px-3 py-2 border border-zinc-200 bg-white rounded-lg text-xs font-medium text-zinc-900 focus:outline-none focus:border-black shadow-2xs">
                                <span class="truncate" x-text="getBudgetLabel(editForm.budget_range)"></span>
                                <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                                 class="absolute z-50 mt-1 w-full bg-white border border-zinc-200 rounded-xl shadow-xl max-h-52 overflow-y-auto py-1 text-xs divide-y divide-zinc-50">
                                <template x-for="opt in budgetOptions" :key="opt.val">
                                    <button type="button" @click="editForm.budget_range = opt.val; open = false"
                                            class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                            :class="editForm.budget_range === opt.val ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                        <span x-text="opt.label"></span>
                                        <svg x-show="editForm.budget_range === opt.val" class="w-3.5 h-3.5 text-black shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Paspor & Vaksin (Custom Dropdowns) -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-zinc-700 mb-1">Status Paspor</label>
                        <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                            <button type="button" @click="open = !open"
                                    class="w-full flex items-center justify-between px-3 py-2 border border-zinc-200 bg-white rounded-lg text-xs font-medium text-zinc-900 focus:outline-none focus:border-black shadow-2xs">
                                <span class="truncate" x-text="getPassportLabel(editForm.passport_status)"></span>
                                <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                                 class="absolute z-50 mt-1 w-full bg-white border border-zinc-200 rounded-xl shadow-xl max-h-52 overflow-y-auto py-1 text-xs divide-y divide-zinc-50">
                                <template x-for="opt in passportOptions" :key="opt.val">
                                    <button type="button" @click="editForm.passport_status = opt.val; open = false"
                                            class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                            :class="editForm.passport_status === opt.val ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                        <span x-text="opt.label"></span>
                                        <svg x-show="editForm.passport_status === opt.val" class="w-3.5 h-3.5 text-black shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block font-medium text-zinc-700 mb-1">Status Vaksin</label>
                        <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                            <button type="button" @click="open = !open"
                                    class="w-full flex items-center justify-between px-3 py-2 border border-zinc-200 bg-white rounded-lg text-xs font-medium text-zinc-900 focus:outline-none focus:border-black shadow-2xs">
                                <span class="truncate" x-text="getVaccineLabel(editForm.vaccine_status)"></span>
                                <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                                 class="absolute z-50 mt-1 w-full bg-white border border-zinc-200 rounded-xl shadow-xl max-h-52 overflow-y-auto py-1 text-xs divide-y divide-zinc-50">
                                <template x-for="opt in vaccineOptions" :key="opt.val">
                                    <button type="button" @click="editForm.vaccine_status = opt.val; open = false"
                                            class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                            :class="editForm.vaccine_status === opt.val ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                        <span x-text="opt.label"></span>
                                        <svg x-show="editForm.vaccine_status === opt.val" class="w-3.5 h-3.5 text-black shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preferensi Kamar & Decision Maker (Custom Dropdowns) -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-zinc-700 mb-1">Preferensi Kamar</label>
                        <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                            <button type="button" @click="open = !open"
                                    class="w-full flex items-center justify-between px-3 py-2 border border-zinc-200 bg-white rounded-lg text-xs font-medium text-zinc-900 focus:outline-none focus:border-black shadow-2xs">
                                <span class="truncate" x-text="getRoomPrefLabel(editForm.room_preference)"></span>
                                <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                                 class="absolute z-50 mt-1 w-full bg-white border border-zinc-200 rounded-xl shadow-xl max-h-52 overflow-y-auto py-1 text-xs divide-y divide-zinc-50">
                                <template x-for="opt in roomPrefOptions" :key="opt.val">
                                    <button type="button" @click="editForm.room_preference = opt.val; open = false"
                                            class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                            :class="editForm.room_preference === opt.val ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                        <span x-text="opt.label"></span>
                                        <svg x-show="editForm.room_preference === opt.val" class="w-3.5 h-3.5 text-black shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block font-medium text-zinc-700 mb-1">Decision Maker</label>
                        <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                            <button type="button" @click="open = !open"
                                    class="w-full flex items-center justify-between px-3 py-2 border border-zinc-200 bg-white rounded-lg text-xs font-medium text-zinc-900 focus:outline-none focus:border-black shadow-2xs">
                                <span class="truncate" x-text="getDecisionMakerLabel(editForm.decision_maker)"></span>
                                <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                                 class="absolute z-50 mt-1 w-full bg-white border border-zinc-200 rounded-xl shadow-xl max-h-52 overflow-y-auto py-1 text-xs divide-y divide-zinc-50">
                                <template x-for="opt in decisionMakerOptions" :key="opt.val">
                                    <button type="button" @click="editForm.decision_maker = opt.val; open = false"
                                            class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                            :class="editForm.decision_maker === opt.val ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                        <span x-text="opt.label"></span>
                                        <svg x-show="editForm.decision_maker === opt.val" class="w-3.5 h-3.5 text-black shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block font-medium text-zinc-700 mb-1">Kebutuhan Khusus / Lansia</label>
                    <textarea x-model="editForm.special_needs" rows="2" class="w-full px-3 py-2 border border-zinc-200 rounded-lg focus:border-black outline-none"></textarea>
                </div>

                <div>
                    <label class="block font-medium text-zinc-700 mb-1">Catatan Bebas</label>
                    <textarea x-model="editForm.notes" rows="2" class="w-full px-3 py-2 border border-zinc-200 rounded-lg focus:border-black outline-none"></textarea>
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-zinc-100">
                    <button type="button" @click="showEditModal = false" class="px-4 py-2 border border-zinc-200 rounded-lg text-zinc-600 hover:bg-zinc-50 transition">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg font-medium hover:bg-zinc-800 transition shadow-2xs">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 2: Lost Status (Custom Dropdown) -->
    <div x-show="showLostModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/50 flex items-center justify-center p-4">
        <div @click.away="showLostModal = false" class="bg-white border border-zinc-200 rounded-2xl max-w-md w-full p-6 shadow-xl relative text-xs">
            <div class="flex items-center justify-between pb-3 mb-3 border-b border-zinc-100">
                <h3 class="text-sm font-bold text-zinc-900">Alasan Pembatalan (Closed Lost)</h3>
                <button type="button" @click="showLostModal = false" class="text-zinc-400 hover:text-black text-xl font-bold">&times;</button>
            </div>

            <form @submit.prevent="submitLostStatus()" class="space-y-3 pb-4">
                <div>
                    <label class="block font-medium text-zinc-700 mb-1">Alasan Utama *</label>
                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                        <button type="button" @click="open = !open"
                                class="w-full flex items-center justify-between px-3 py-2 border border-zinc-200 bg-white rounded-lg text-xs font-medium text-zinc-900 focus:outline-none focus:border-black shadow-2xs">
                            <span class="truncate" x-text="getLostReasonLabel(lostForm.reason)"></span>
                            <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                             class="absolute z-50 mt-1 w-full bg-white border border-zinc-200 rounded-xl shadow-xl max-h-52 overflow-y-auto py-1 text-xs divide-y divide-zinc-50">
                            <template x-for="opt in lostReasonOptions" :key="opt.val">
                                <button type="button" @click="lostForm.reason = opt.val; open = false"
                                        class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                        :class="lostForm.reason === opt.val ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                    <span x-text="opt.label"></span>
                                    <svg x-show="lostForm.reason === opt.val" class="w-3.5 h-3.5 text-black shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block font-medium text-zinc-700 mb-1">Detail Keterangan</label>
                    <textarea x-model="lostForm.detail" rows="3" class="w-full px-3 py-2 border border-zinc-200 rounded-lg focus:border-black outline-none" placeholder="Catatan singkat mengapa jamaah batal..."></textarea>
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-zinc-100">
                    <button type="button" @click="showLostModal = false" class="px-4 py-2 border border-zinc-200 rounded-lg text-zinc-600 hover:bg-zinc-50 transition">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-zinc-900 hover:bg-black text-white rounded-lg font-medium transition shadow-2xs">Tandai Lost</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 3: Payment DP (Custom Dropdown) -->
    <div x-show="showPaymentModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/50 flex items-center justify-center p-4">
        <div @click.away="showPaymentModal = false" class="bg-white border border-zinc-200 rounded-2xl max-w-md w-full p-6 shadow-xl relative text-xs">
            <div class="flex items-center justify-between pb-3 mb-3 border-b border-zinc-100">
                <h3 class="text-sm font-bold text-zinc-900">Pembaruan Pembayaran DP</h3>
                <button type="button" @click="showPaymentModal = false" class="text-zinc-400 hover:text-black text-xl font-bold">&times;</button>
            </div>

            <form @submit.prevent="submitPaymentUpdate()" class="space-y-3 pb-4">
                <div>
                    <label class="block font-medium text-zinc-700 mb-1">Nominal DP Masuk (Rp) *</label>
                    <input type="text" x-model="paymentForm.dp_amount" required class="w-full px-3 py-2 border border-zinc-200 rounded-lg font-mono font-bold focus:border-black outline-none">
                </div>

                <div>
                    <label class="block font-medium text-zinc-700 mb-1">Status Pembayaran</label>
                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                        <button type="button" @click="open = !open"
                                class="w-full flex items-center justify-between px-3 py-2 border border-zinc-200 bg-white rounded-lg text-xs font-medium text-zinc-900 focus:outline-none focus:border-black shadow-2xs">
                            <span class="truncate" x-text="getPaymentStatusLabel(paymentForm.payment_status)"></span>
                            <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                             class="absolute z-50 mt-1 w-full bg-white border border-zinc-200 rounded-xl shadow-xl max-h-52 overflow-y-auto py-1 text-xs divide-y divide-zinc-50">
                            <template x-for="opt in paymentStatusOptions" :key="opt.val">
                                <button type="button" @click="paymentForm.payment_status = opt.val; open = false"
                                        class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                        :class="paymentForm.payment_status === opt.val ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                    <span x-text="opt.label"></span>
                                    <svg x-show="paymentForm.payment_status === opt.val" class="w-3.5 h-3.5 text-black shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-zinc-100">
                    <button type="button" @click="showPaymentModal = false" class="px-4 py-2 border border-zinc-200 rounded-lg text-zinc-600 hover:bg-zinc-50 transition">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg font-medium hover:bg-zinc-800 transition shadow-2xs">Simpan DP</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 4: Flyer -->
    <div x-show="showFlyerModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/75 flex items-center justify-center p-4">
        <div @click.away="showFlyerModal = false" class="bg-white rounded-2xl max-w-lg w-full p-4 shadow-xl relative">
            <div class="flex justify-between items-center mb-2">
                <span class="text-xs font-semibold text-zinc-800" x-text="currentPackage ? currentPackage.name : ''"></span>
                <button type="button" @click="showFlyerModal = false" class="text-zinc-400 hover:text-black text-lg">&times;</button>
            </div>
            <img :src="currentPackage ? currentPackage.flyer_image : ''" class="max-h-[70vh] w-full object-contain rounded-xl">
        </div>
    </div>

</div>

<script>
function prospect360Page() {
    return {
        prospect: <?= json_encode($prospect) ?>,
        packagesList: <?= json_encode($packages) ?>,
        logsList: <?= json_encode($logs) ?>,
        
        showEditModal: false,
        showLostModal: false,
        showPaymentModal: false,
        showFlyerModal: false,

        isUpdatingStage: false,
        isUpdatingPax: false,
        isSubmittingFollowup: false,
        isSubmittingEdit: false,

        followupForm: {
            note: '',
            status: '<?= $prospect['status'] ?>',
            next_followup_date: '<?= $prospect['next_followup_date'] ?? '' ?>'
        },

        lostForm: {
            reason: '<?= $prospect['lost_reason'] ?? '' ?>',
            detail: '<?= addslashes($prospect['lost_reason_detail'] ?? '') ?>'
        },

        paymentForm: {
            dp_amount: '<?= (float)($prospect['dp_amount'] ?? 0) ?>',
            payment_status: '<?= $prospect['payment_status'] ?? 'unpaid' ?>'
        },

        editForm: {
            id: <?= $prospect['id'] ?>,
            name: <?= json_encode($prospect['name']) ?>,
            phone: <?= json_encode($prospect['phone'] ?? '') ?>,
            city: <?= json_encode($prospect['city'] ?? '') ?>,
            lead_source: <?= json_encode($prospect['lead_source'] ?? 'whatsapp') ?>,
            target_month: <?= json_encode($prospect['target_month'] ?? '') ?>,
            budget_range: <?= json_encode($prospect['budget_range'] ?? '') ?>,
            room_preference: <?= json_encode($prospect['room_preference'] ?? '') ?>,
            decision_maker: <?= json_encode($prospect['decision_maker'] ?? '') ?>,
            special_needs: <?= json_encode($prospect['special_needs'] ?? '') ?>,
            passport_status: <?= json_encode($prospect['passport_status'] ?? 'belum_ada') ?>,
            vaccine_status: <?= json_encode($prospect['vaccine_status'] ?? 'belum') ?>,
            package_id: '<?= $prospect['package_id'] ?? '' ?>',
            pax_quad: <?= (int)$prospect['pax_quad'] ?>,
            pax_triple: <?= (int)$prospect['pax_triple'] ?>,
            pax_double: <?= (int)$prospect['pax_double'] ?>,
            pax_infant: <?= (int)$prospect['pax_infant'] ?>,
            status: '<?= $prospect['status'] ?>',
            notes: <?= json_encode($prospect['notes'] ?? '') ?>,
            next_followup_date: '<?= $prospect['next_followup_date'] ?? '' ?>'
        },

        quickChips: [
            'Kirim rincian paket via WA',
            'Sedang diskusi dengan keluarga',
            'Minta waktu 2 hari cek cuti',
            'Siap transfer DP seat hari ini'
        ],

        pipelineStages: [
            { key: 'new', short: 'Baru', label: '1. Prospek Baru' },
            { key: 'identifying', short: 'Identifikasi', label: '2. Identifikasi Kebutuhan' },
            { key: 'offered', short: 'Penawaran', label: '3. Penawaran Paket' },
            { key: 'objection', short: 'Keberatan', label: '4. Penanganan Keberatan' },
            { key: 'followup', short: 'Follow-Up', label: '5. Follow-Up Rutin' },
            { key: 'closing', short: 'Closing', label: '6. Proses Closing' },
            { key: 'closed_won', short: 'Won (DP)', label: '7. Closed Won' }
        ],

        // Master Dropdown Options
        statusOptions: [
            { val: 'new', label: 'Prospek Baru' },
            { val: 'identifying', label: 'Sedang Diidentifikasi' },
            { val: 'offered', label: 'Sudah Ditawarkan' },
            { val: 'closing', label: 'Proses Closing' },
            { val: 'objection', label: 'Ada Keberatan' },
            { val: 'followup', label: 'Perlu Follow-up' },
            { val: 'nurture', label: 'Nurture' },
            { val: 'closed_won', label: 'Closing (Won DP)' },
            { val: 'closed_lost', label: 'Batal (Closed Lost)' }
        ],

        leadSourceOptions: [
            { val: 'whatsapp', label: 'WhatsApp Inbound' },
            { val: 'meta_ads', label: 'Meta Ads (FB/IG)' },
            { val: 'website_form', label: 'Website Form' },
            { val: 'referral', label: 'Referral Alumni' },
            { val: 'walk_in', label: 'Walk-In Kantor' },
            { val: 'repeat_order', label: 'Repeat Order' },
            { val: 'other', label: 'Lainnya' }
        ],

        budgetOptions: [
            { val: '', label: '-- Pilih Budget --' },
            { val: '< 28 Juta', label: '< 28 Juta' },
            { val: '28 - 35 Juta', label: '28 - 35 Juta' },
            { val: '> 35 Juta', label: '> 35 Juta' },
            { val: 'Fleksibel', label: 'Fleksibel' }
        ],

        passportOptions: [
            { val: 'sudah_ada', label: 'Sudah Ada & Berlaku' },
            { val: 'proses_buat', label: 'Sedang Proses Buat' },
            { val: 'perlu_perpanjang', label: 'Perlu Perpanjang' },
            { val: 'belum_ada', label: 'Belum Ada' }
        ],

        vaccineOptions: [
            { val: 'sudah', label: 'Sudah Vaksin' },
            { val: 'belum', label: 'Belum Vaksin' }
        ],

        roomPrefOptions: [
            { val: '', label: '-- Belum Pasti --' },
            { val: 'quad', label: 'Kamar Quad (4 Orang)' },
            { val: 'triple', label: 'Kamar Triple (3 Orang)' },
            { val: 'double', label: 'Kamar Double (2 Orang)' }
        ],

        decisionMakerOptions: [
            { val: '', label: '-- Belum Dicatat --' },
            { val: 'diri_sendiri', label: 'Diri Sendiri' },
            { val: 'pasangan', label: 'Pasangan (Suami/Istri)' },
            { val: 'anak', label: 'Anak' },
            { val: 'keluarga_besar', label: 'Keluarga Besar' },
            { val: 'kantor', label: 'Instansi / Kantor' }
        ],

        lostReasonOptions: [
            { val: '', label: '-- Pilih Alasan --' },
            { val: 'harga_kemahalan', label: 'Harga di luar budget / kemahalan' },
            { val: 'jadwal_bentrok', label: 'Jadwal bentrok kerja / cuti' },
            { val: 'pilih_travel_lain', label: 'Pilih travel umroh lain' },
            { val: 'kendala_paspor', label: 'Kendala paspor' },
            { val: 'masalah_kesehatan', label: 'Kondisi fisik / kesehatan' },
            { val: 'keluarga_tidak_setuju', label: 'Keluarga belum sepakat' },
            { val: 'no_response', label: 'Ghosting / tidak merespon' },
            { val: 'lainnya', label: 'Alasan lainnya' }
        ],

        paymentStatusOptions: [
            { val: 'unpaid', label: 'Belum Bayar DP (Unpaid)' },
            { val: 'partial_dp', label: 'DP Sebagian (Partial DP)' },
            { val: 'paid_full', label: 'Lunas 100% (Paid Full)' }
        ],

        get currentPackage() {
            if (!this.prospect.package_id) return null;
            return this.packagesList.find(p => p.id == this.prospect.package_id) || null;
        },

        calcTotalPax() {
            const q = parseInt(this.prospect.pax_quad) || 0;
            const t = parseInt(this.prospect.pax_triple) || 0;
            const d = parseInt(this.prospect.pax_double) || 0;
            const i = parseInt(this.prospect.pax_infant) || 0;
            return (q + t + d + i) || 1;
        },

        calcBalanceDue() {
            const deal = parseFloat(this.prospect.deal_value) || 0;
            const dp = parseFloat(this.prospect.dp_amount) || 0;
            return Math.max(0, deal - dp);
        },

        getPackageRoomPrice(type) {
            const pkg = this.currentPackage;
            if (!pkg) return 0;
            const def = parseFloat(String(pkg.price).replace(/[^\d]/g, '')) || 0;
            if (type === 'quad') return parseFloat(String(pkg.price_quad || def).replace(/[^\d]/g, '')) || def;
            if (type === 'triple') return parseFloat(String(pkg.price_triple || def).replace(/[^\d]/g, '')) || def;
            if (type === 'double') return parseFloat(String(pkg.price_double || def).replace(/[^\d]/g, '')) || def;
            if (type === 'infant') return parseFloat(String(pkg.price_infant || 0).replace(/[^\d]/g, '')) || 0;
            return def;
        },

        formatRupiah(num) {
            if (!num && num !== 0) return 'Rp 0';
            const val = parseFloat(num) || 0;
            return 'Rp ' + Math.round(val).toLocaleString('id-ID');
        },

        formatDateOnly(dStr) {
            if (!dStr) return '-';
            try {
                const d = new Date(dStr);
                return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
            } catch(e) { return dStr; }
        },

        formatDateShort(dStr) {
            if (!dStr) return '-';
            try {
                const d = new Date(dStr);
                return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) + ' WIB';
            } catch(e) { return dStr; }
        },

        formatTimeAgo(dateStr) {
            if (!dateStr) return '';
            const sec = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
            if (sec < 60) return 'baru saja';
            if (sec < 3600) return Math.floor(sec / 60) + 'm lalu';
            if (sec < 86400) return Math.floor(sec / 3600) + 'j lalu';
            return Math.floor(sec / 86400) + 'h lalu';
        },

        // Dropdown Label Getters
        getStatusLabel(st) {
            const found = this.statusOptions.find(s => s.val === st);
            return found ? found.label : (st || 'Prospek Baru');
        },

        getLeadSourceLabel(val) {
            const f = this.leadSourceOptions.find(o => o.val === val);
            return f ? f.label : (val || 'WhatsApp Inbound');
        },

        getBudgetLabel(val) {
            const f = this.budgetOptions.find(o => o.val === val);
            return f ? f.label : (val || '-- Pilih Budget --');
        },

        getPassportLabel(val) {
            const f = this.passportOptions.find(o => o.val === val);
            return f ? f.label : (val || 'Belum Ada');
        },

        getVaccineLabel(val) {
            const f = this.vaccineOptions.find(o => o.val === val);
            return f ? f.label : (val === 'sudah' ? 'Sudah Vaksin' : 'Belum Vaksin');
        },

        getRoomPrefLabel(val) {
            const f = this.roomPrefOptions.find(o => o.val === val);
            return f ? f.label : (val ? ('Kamar ' + val) : '-- Belum Pasti --');
        },

        getDecisionMakerLabel(val) {
            const f = this.decisionMakerOptions.find(o => o.val === val);
            return f ? f.label : (val || '-- Belum Dicatat --');
        },

        getLostReasonLabel(val) {
            const f = this.lostReasonOptions.find(o => o.val === val);
            return f ? f.label : (val || '-- Pilih Alasan --');
        },

        getPaymentStatusLabel(val) {
            const f = this.paymentStatusOptions.find(o => o.val === val);
            return f ? f.label : (val || 'Belum Bayar DP');
        },

        formatLeadSource(src) {
            return this.getLeadSourceLabel(src);
        },

        formatPassportStatus(st) {
            return this.getPassportLabel(st);
        },

        formatDecisionMaker(dm) {
            return this.getDecisionMakerLabel(dm);
        },

        formatLostReason(r) {
            return this.getLostReasonLabel(r);
        },

        getStatusBadgeClass(status) {
            switch(status) {
                case 'new': return 'bg-zinc-100 text-zinc-700';
                case 'identifying': return 'bg-blue-50 text-blue-800';
                case 'offered': return 'bg-indigo-50 text-indigo-800';
                case 'objection': return 'bg-amber-50 text-amber-900';
                case 'followup': return 'bg-sky-50 text-sky-800';
                case 'closing': return 'bg-purple-50 text-purple-900 font-semibold';
                case 'closed_won': return 'bg-emerald-600 text-white font-semibold';
                case 'closed_lost': return 'bg-zinc-200 text-zinc-500 line-through';
                default: return 'bg-zinc-100 text-zinc-700';
            }
        },

        isStageCompleted(stageKey) {
            const order = ['new', 'identifying', 'offered', 'objection', 'followup', 'closing', 'closed_won'];
            const currentIdx = order.indexOf(this.prospect.status);
            const targetIdx = order.indexOf(stageKey);
            return currentIdx >= 0 && targetIdx >= 0 && currentIdx > targetIdx;
        },

        getSegmentClass(stageKey, idx) {
            if (this.prospect.status === stageKey) {
                if (stageKey === 'closed_won') return 'bg-emerald-600 text-white border-emerald-700 font-semibold';
                return 'bg-zinc-900 text-white border-zinc-900 font-semibold';
            }
            if (this.isStageCompleted(stageKey)) {
                return 'bg-zinc-100 text-zinc-800 border-zinc-200 hover:bg-zinc-200';
            }
            return 'bg-white text-zinc-400 border-zinc-200 hover:text-black hover:border-zinc-300';
        },

        onStageChevronClick(stageKey) {
            if (this.prospect.status === stageKey) return;
            this.quickUpdateStage(stageKey);
        },

        openLostModalForm() {
            this.lostForm = {
                reason: this.prospect.lost_reason || '',
                detail: this.prospect.lost_reason_detail || ''
            };
            this.showLostModal = true;
        },

        async quickUpdateStage(newStatus) {
            this.isUpdatingStage = true;
            try {
                const res = await fetch('api/prospects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_stage',
                        id: this.prospect.id,
                        status: newStatus
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.prospect.status = newStatus;
                    this.followupForm.status = newStatus;
                    window.showToast('Tahapan prospek berhasil diperbarui.');
                    await this.reloadLogs();
                } else {
                    alert('Gagal mengubah status: ' + (data.error || 'Terjadi kesalahan'));
                }
            } catch(e) {
                console.error(e);
            } finally {
                this.isUpdatingStage = false;
            }
        },

        async submitLostStatus() {
            if (!this.lostForm.reason) {
                alert('Pilih alasan pembatalan.');
                return;
            }
            try {
                const res = await fetch('api/prospects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_stage',
                        id: this.prospect.id,
                        status: 'closed_lost',
                        lost_reason: this.lostForm.reason,
                        lost_reason_detail: this.lostForm.detail
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.prospect.status = 'closed_lost';
                    this.prospect.lost_reason = this.lostForm.reason;
                    this.prospect.lost_reason_detail = this.lostForm.detail;
                    this.showLostModal = false;
                    window.showToast('Prospek ditandai Closed Lost.');
                    await this.reloadLogs();
                } else {
                    alert(data.error || 'Gagal menyimpan');
                }
            } catch(e) {
                alert('Terjadi kesalahan.');
            }
        },

        async stepPax(key, delta) {
            const current = parseInt(this.prospect[key]) || 0;
            const updated = Math.max(0, current + delta);
            if (current === updated) return;

            const payload = {
                action: 'update_pax',
                id: this.prospect.id,
                pax_quad: key === 'pax_quad' ? updated : this.prospect.pax_quad,
                pax_triple: key === 'pax_triple' ? updated : this.prospect.pax_triple,
                pax_double: key === 'pax_double' ? updated : this.prospect.pax_double,
                pax_infant: key === 'pax_infant' ? updated : this.prospect.pax_infant
            };

            this.isUpdatingPax = true;
            try {
                const res = await fetch('api/prospects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    this.prospect[key] = updated;
                    this.prospect.deal_value = data.deal_value;
                    await this.reloadLogs();
                }
            } catch(e) {
                console.error(e);
            } finally {
                this.isUpdatingPax = false;
            }
        },

        async selectPackage(pkgId) {
            try {
                const res = await fetch('api/prospects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_package',
                        id: this.prospect.id,
                        package_id: pkgId
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.prospect.package_id = pkgId;
                    this.prospect.deal_value = data.deal_value;
                    window.showToast('Paket umroh berhasil diperbarui.');
                    await this.reloadLogs();
                }
            } catch(e) {
                console.error(e);
            }
        },

        appendQuickChip(chip) {
            if (this.followupForm.note) {
                this.followupForm.note += '. ' + chip;
            } else {
                this.followupForm.note = chip;
            }
        },

        async submitFollowup() {
            if (!this.followupForm.note.trim()) return;
            this.isSubmittingFollowup = true;
            try {
                const payload = {
                    action: 'log_followup',
                    id: this.prospect.id,
                    note: this.followupForm.note,
                    status: this.followupForm.status,
                    next_followup_date: this.followupForm.next_followup_date
                };

                const res = await fetch('api/prospects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    window.showToast('Follow-up berhasil disimpan.');
                    this.prospect.status = this.followupForm.status;
                    this.prospect.next_followup_date = this.followupForm.next_followup_date;
                    this.prospect.last_followup_at = new Date().toISOString();
                    this.followupForm.note = '';
                    await this.reloadLogs();
                }
            } catch (err) {
                console.error(err);
            } finally {
                this.isSubmittingFollowup = false;
            }
        },

        openPaymentModal() {
            this.paymentForm = {
                dp_amount: parseFloat(this.prospect.dp_amount) || 0,
                payment_status: this.prospect.payment_status || 'unpaid'
            };
            this.showPaymentModal = true;
        },

        async submitPaymentUpdate() {
            try {
                const res = await fetch('api/prospects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_payment',
                        id: this.prospect.id,
                        dp_amount: this.paymentForm.dp_amount,
                        payment_status: this.paymentForm.payment_status
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.prospect.dp_amount = data.dp_amount;
                    this.prospect.payment_status = data.payment_status;
                    this.showPaymentModal = false;
                    window.showToast('Pembayaran DP diperbarui.');
                    await this.reloadLogs();
                }
            } catch(e) {
                console.error(e);
            }
        },

        openEditModal() {
            this.editForm = {
                id: this.prospect.id,
                name: this.prospect.name,
                phone: this.prospect.phone || '',
                city: this.prospect.city || '',
                lead_source: this.prospect.lead_source || 'whatsapp',
                target_month: this.prospect.target_month || '',
                budget_range: this.prospect.budget_range || '',
                room_preference: this.prospect.room_preference || '',
                decision_maker: this.prospect.decision_maker || '',
                special_needs: this.prospect.special_needs || '',
                passport_status: this.prospect.passport_status || 'belum_ada',
                vaccine_status: this.prospect.vaccine_status || 'belum',
                package_id: this.prospect.package_id || '',
                pax_quad: this.prospect.pax_quad || 0,
                pax_triple: this.prospect.pax_triple || 0,
                pax_double: this.prospect.pax_double || 0,
                pax_infant: this.prospect.pax_infant || 0,
                status: this.prospect.status,
                notes: this.prospect.notes || '',
                next_followup_date: this.prospect.next_followup_date || ''
            };
            this.showEditModal = true;
        },

        async saveProspectEdit() {
            this.isSubmittingEdit = true;
            try {
                const payload = Object.assign({}, this.editForm, {
                    action: 'update',
                    brand_id: this.prospect.brand_id
                });

                const res = await fetch('api/prospects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success && data.prospect) {
                    this.prospect = data.prospect;
                    this.followupForm.status = data.prospect.status;
                    this.followupForm.next_followup_date = data.prospect.next_followup_date;
                    this.showEditModal = false;
                    window.showToast('Profil prospek diperbarui.');
                    await this.reloadLogs();
                } else {
                    alert(data.error || 'Gagal memperbarui prospek');
                }
            } catch(e) {
                console.error(e);
            } finally {
                this.isSubmittingEdit = false;
            }
        },

        async deleteProspect() {
            if (!confirm('Hapus prospek ini secara permanen?')) return;
            try {
                const res = await fetch('api/prospects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', id: this.prospect.id })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.href = 'prospects.php';
                }
            } catch(e) {
                console.error(e);
            }
        },

        async reloadLogs() {
            try {
                const res = await fetch(`api/prospects.php?action=get_logs&id=${this.prospect.id}`);
                const data = await res.json();
                if (data.success) {
                    this.logsList = data.data;
                }
            } catch(e) {
                console.error(e);
            }
        },

        getCuratedScripts(tab) {
            const nama = this.prospect.name || 'Kakak';
            const travel = '<?= addslashes($prospect['brand_name']) ?>';
            const paket = this.currentPackage ? this.currentPackage.name : 'Paket Umroh Reguler';
            const dp = this.currentPackage ? ('Rp ' + this.currentPackage.dp) : 'Rp 5.000.000';

            if (tab === 'closing') {
                return [
                    {
                        title: 'Kunci Kuota Seat (Closing DP)',
                        text: `Bismillah Kak ${nama} 😊\n\nUntuk mengamankan kuota seat pada ${paket} bersama ${travel}, Kakak cukup melakukan booking seat dengan DP awal ${dp} per jamaah.\n\nApakah invoice dan nomor rekening resmi ${travel} bisa kami buatkan sekarang, Kak?`
                    },
                    {
                        title: 'Konfirmasi Rekening Resmi',
                        text: `Alhamdulillah, terima kasih banyak Kak ${nama} 🙏\n\nBerikut rekening resmi pendaftaran umroh ${travel}:\nBank: <?= addslashes($prospect['bank_name'] ?: 'BSI') ?> No. <?= addslashes($prospect['bank_account_number'] ?: '7123456789') ?>\nAtas Nama: <?= addslashes($prospect['bank_account_holder'] ?: $prospect['brand_name']) ?>\n\nMohon konfirmasi bukti transfer jika sudah melakukan pembayaran ya Kak.`
                    }
                ];
            } else if (tab === 'objection') {
                return [
                    {
                        title: 'TGJP Harga / Budget',
                        text: `Memang betul Kak ${nama}, jika dilihat sekilas selisih harga terlihat signifikan. Namun paket ini sudah all-in termasuk tiket direct tanpa transit dan hotel bintang dekat masjid.\n\nApakah Kakak ingin kami hitungkan skema cicilan atau opsi tanggal keberangkatan yang lebih hemat, Kak?`
                    },
                    {
                        title: 'TGJP Diskusi Keluarga',
                        text: `Sangat baik sekali Kak ${nama}, ibadah ke baitullah memang paling berkah jika dimusyawarahkan bersama keluarga 😊\n\nBiar pembahasannya lebih mudah, apakah Kakak berkenan saya kirimkan rangkuman brosur PDF untuk ditunjukkan ke keluarga?`
                    }
                ];
            } else {
                return [
                    {
                        title: 'Follow-up Sentimen Hangat',
                        text: `Assalamu'alaikum Kak ${nama} 😊\n\nSemoga hari ini penuh berkah ya, Kak. Menindaklanjuti rencana umroh Kakak kemarin, apakah sudah ada kesempatan berdiskusi mengenai jadwal keberangkatan ${paket}?\n\nJika ada pertanyaan mengenai fasilitas atau dokumen, saya siap bantu jelaskan Kak.`
                    },
                    {
                        title: 'Pengingat Sisa Kuota',
                        text: `Assalamu'alaikum Kak ${nama} 😊\n\nIzin sekadar mengingatkan, seat untuk ${paket} saat ini tersisa terbatas. Apakah Kakak ingin seat-nya diamankan terlebih dahulu hari ini?`
                    }
                ];
            }
        },

        copyPackageFacilities() {
            if (!this.currentPackage) return;
            const text = `*${this.currentPackage.name}* (${this.currentPackage.duration})\n\n` +
                         `✈️ Maskapai: ${this.currentPackage.airline}\n` +
                         `🏨 Hotel Makkah: ${this.currentPackage.hotel_makkah}\n` +
                         `🏨 Hotel Madinah: ${this.currentPackage.hotel_madinah}\n` +
                         `💰 Harga: Rp ${this.currentPackage.price} (DP ${this.currentPackage.dp})\n\n` +
                         `*Fasilitas Termasuk:*\n${this.currentPackage.facilities_included || 'Tiket PP, Visa, Hotel, Makan 3x, Handling, Muthawwif'}\n\n` +
                         `Layanan Resmi: <?= addslashes($prospect['brand_name']) ?>`;
            window.copyToClipboard(text, 'Rincian paket berhasil disalin.');
        }
    };
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
