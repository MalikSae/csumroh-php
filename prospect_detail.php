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

// Fetch recent WhatsApp chat messages if linked
$chatMessages = [];
if (!empty($prospect['remote_jid']) || !empty($prospect['id'])) {
    $chatStmt = $db->prepare("
        SELECT id, message_text, is_from_me, timestamp, status, media_url
        FROM chat_messages
        WHERE prospect_id = ? OR (brand_id = ? AND remote_jid = ?)
        ORDER BY timestamp DESC
        LIMIT 5
    ");
    $chatStmt->execute([$prospect['id'], $prospect['brand_id'], $prospect['remote_jid'] ?? '']);
    $chatMessages = array_reverse($chatStmt->fetchAll());
}

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

// Load scripts for Copilot Quick Copy
$allScripts = get_all_scripts();
$scriptVars = [
    'prospect_name' => $prospect['name'] ?: 'Kakak',
    'cs_name' => $currentUser['name'] ?? 'CS Layanan Jamaah',
    'brand_name' => $prospect['brand_name'] ?? 'Travel Umroh Kami',
    'ppiu_number' => $prospect['ppiu_number'] ?? '-',
    'bank_name' => $prospect['bank_name'] ?? '',
    'bank_account_number' => $prospect['bank_account_number'] ?? '',
    'bank_account_holder' => $prospect['bank_account_holder'] ?? '',
    'package_name' => $prospect['package_name'] ?? 'Paket Umroh Reguler',
    'package_price' => $prospect['package_price'] ?? 'Rp 28.000.000',
    'package_dp' => $prospect['package_dp'] ?? 'Rp 5.000.000',
    'airline' => $prospect['package_airline'] ?? 'Penerbangan Direct',
    'hotel_makkah' => $prospect['package_hotel_makkah'] ?? 'Hotel Makkah',
    'hotel_madinah' => $prospect['package_hotel_madinah'] ?? 'Hotel Madinah',
    'departure_info' => $prospect['departure_date'] ? date('d M Y', strtotime($prospect['departure_date'])) : ($prospect['package_departure_info'] ?? 'Sesuai Jadwal'),
    'duration' => $prospect['package_duration'] ?? '9 Hari',
    'highlights' => $prospect['package_highlights'] ?? 'Akomodasi Nyaman',
    'pax' => ($totalAllPax ?: 1) . ' jamaah',
];

// Helper to format currency
function format_rp($num): string {
    return 'Rp ' . number_format((float)$num, 0, ',', '.');
}
?>

<div class="max-w-7xl mx-auto space-y-6 pb-16" x-data="prospect360Page()">

    <!-- ============================================================== -->
    <!-- 1. TOP 360° HEADER & FAST ACTION LAUNCHERS                     -->
    <!-- ============================================================== -->
    <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 sm:p-6 shadow-2xs space-y-4">
        
        <!-- Breadcrumbs & Quick Launch Bar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-zinc-100">
            <div class="flex items-center gap-2 text-xs">
                <a href="prospects.php" class="text-zinc-500 hover:text-black font-semibold inline-flex items-center gap-1.5 transition">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
                    <span>Kembali ke Pipeline</span>
                </a>
                <span class="text-zinc-300">/</span>
                <span class="font-mono text-zinc-400">ID #<?= $prospect['id'] ?></span>
                <span class="text-zinc-300">&bull;</span>
                <span class="text-zinc-600 font-medium"><?= htmlspecialchars($prospect['brand_name']) ?></span>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap items-center gap-2">
                <?php if (!empty($prospect['phone'])): ?>
                    <a href="https://wa.me/<?= $prospect['phone'] ?>" target="_blank"
                       class="px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-xl text-xs font-semibold transition flex items-center gap-2 shadow-2xs">
                        <svg class="w-3.5 h-3.5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="m3 21 1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"/>
                        </svg>
                        <span>WhatsApp Web</span>
                    </a>
                <?php endif; ?>

                <a href="chat.php?prospect_id=<?= $prospect['id'] ?><?= !empty($prospect['remote_jid']) ? ('&jid=' . urlencode($prospect['remote_jid'])) : '' ?>"
                   class="px-3.5 py-2 bg-white hover:bg-zinc-50 border border-zinc-300 text-zinc-800 rounded-xl text-xs font-semibold transition flex items-center gap-2 shadow-2xs">
                    <svg class="w-3.5 h-3.5 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <span>Live Chat</span>
                </a>

                <a href="index.php?load_prospect=<?= $prospect['id'] ?>"
                   class="px-3.5 py-2 bg-black hover:bg-zinc-800 text-white rounded-xl text-xs font-semibold transition flex items-center gap-2 shadow-2xs">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                    </svg>
                    <span>Muat ke Copilot</span>
                </a>

                <button type="button" @click="openEditModal()"
                        class="px-3.5 py-2 bg-white hover:bg-zinc-50 border border-zinc-300 text-zinc-800 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 shadow-2xs">
                    <svg class="w-3.5 h-3.5 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
                    </svg>
                    <span>Edit CRM</span>
                </button>

                <button type="button" @click="deleteProspect()" title="Hapus Prospek"
                        class="p-2 bg-white hover:bg-rose-50 border border-zinc-200 hover:border-rose-200 text-zinc-400 hover:text-rose-600 rounded-xl text-xs transition">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Identity Profile Row -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <!-- Avatar with fallback -->
                <div class="relative shrink-0">
                    <?php if (!empty($prospect['photo_url'])): ?>
                        <img src="<?= htmlspecialchars($prospect['photo_url']) ?>" 
                             alt="<?= htmlspecialchars($prospect['name']) ?>"
                             class="w-14 h-14 rounded-2xl object-cover border border-zinc-200 shadow-2xs"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                    <?php endif; ?>
                    <div class="<?= !empty($prospect['photo_url']) ? 'hidden' : 'flex' ?> w-14 h-14 rounded-2xl bg-black text-white font-bold text-lg items-center justify-center tracking-wider shadow-2xs">
                        <?php
                            $words = explode(' ', trim($prospect['name']));
                            $initials = strtoupper(substr($words[0] ?? 'J', 0, 1) . substr($words[1] ?? '', 0, 1));
                            echo $initials ?: 'JM';
                        ?>
                    </div>
                </div>

                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-black tracking-tight" x-text="prospect.name"></h1>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold border"
                              :class="getStatusBadgeClass(prospect.status)"
                              x-text="getStatusLabel(prospect.status)"></span>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500">
                        <span class="inline-flex items-center gap-1 font-mono text-zinc-700">
                            <svg class="w-3.5 h-3.5 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            <span x-text="prospect.phone || '-'"></span>
                        </span>
                        <span>&bull;</span>
                        <span class="inline-flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span x-text="prospect.city || 'Domisili Belum Dicatat'"></span>
                        </span>
                        <span>&bull;</span>
                        <span class="inline-flex items-center gap-1 text-zinc-600">
                            Sumber: <strong class="capitalize" x-text="formatLeadSource(prospect.lead_source)"></strong>
                        </span>
                        <span>&bull;</span>
                        <span>PIC: <strong class="text-zinc-800"><?= htmlspecialchars($prospect['cs_name'] ?? 'CS') ?></strong></span>
                    </div>
                </div>
            </div>

            <!-- Fast Action Pill for Lost / Active -->
            <template x-if="prospect.status === 'closed_lost'">
                <div class="flex items-center gap-2 bg-rose-50 border border-rose-200 px-3.5 py-2 rounded-xl text-xs text-rose-800">
                    <svg class="w-4 h-4 text-rose-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    <div>
                        <span class="font-bold block">Status: Closed Lost</span>
                        <span class="text-[11px] text-rose-600" x-text="prospect.lost_reason ? ('Alasan: ' + formatLostReason(prospect.lost_reason)) : 'Prospek dibatalkan'"></span>
                    </div>
                    <button type="button" @click="quickUpdateStage('followup')" 
                            class="ml-2 px-2.5 py-1 bg-white border border-rose-300 text-rose-900 rounded-lg text-[11px] font-semibold hover:bg-rose-100 transition">
                        Aktifkan Lagi
                    </button>
                </div>
            </template>
        </div>

        <!-- ============================================================== -->
        <!-- 2. INTERACTIVE STAGE CHEVRON PIPELINE BAR                     -->
        <!-- ============================================================== -->
        <div class="pt-3 border-t border-zinc-100">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-zinc-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    <span>Pipeline Tahapan Konversi Jamaah</span>
                </span>
                <span class="text-[11px] text-zinc-400">Klik tahapan untuk memajukan status seketika</span>
            </div>

            <!-- Responsive Stepper Container -->
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-1.5 p-1.5 bg-zinc-50 border border-zinc-200 rounded-2xl">
                <template x-for="(st, idx) in pipelineStages" :key="st.key">
                    <button type="button" @click="onStageChevronClick(st.key)"
                            :disabled="isUpdatingStage"
                            class="relative flex flex-col items-center justify-center py-2 px-2 rounded-xl text-xs transition border group text-center"
                            :class="getChevronClass(st.key, idx)">
                        <div class="flex items-center gap-1.5">
                            <span class="text-[10px] font-bold font-mono opacity-80" x-text="(idx + 1) + '.'"></span>
                            <span class="font-semibold truncate max-w-[85px] sm:max-w-none" x-text="st.short"></span>
                        </div>
                        <!-- Completed / Current indicator -->
                        <div class="mt-0.5">
                            <template x-if="prospect.status === st.key">
                                <span class="inline-block w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                            </template>
                            <template x-if="isStageCompleted(st.key) && prospect.status !== st.key">
                                <svg class="w-3 h-3 text-zinc-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            </template>
                        </div>
                    </button>
                </template>

                <!-- Dedicated Lost Trigger Button -->
                <button type="button" @click="openLostModalForm()"
                        :disabled="isUpdatingStage"
                        class="flex flex-col items-center justify-center py-2 px-2 rounded-xl text-xs transition border text-center"
                        :class="prospect.status === 'closed_lost' ? 'bg-zinc-800 text-white border-black font-bold shadow-xs' : 'bg-white text-zinc-500 border-zinc-200 hover:border-rose-300 hover:text-rose-700'">
                    <div class="flex items-center gap-1">
                        <svg class="w-3 h-3 text-zinc-400 group-hover:text-rose-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        <span class="font-semibold">Batal / Lost</span>
                    </div>
                    <span class="text-[9px] mt-0.5 opacity-70">Gagal Closing</span>
                </button>
            </div>
        </div>

    </div>

    <!-- ============================================================== -->
    <!-- 3. TOP FINANCIAL & PROGRESS METRICS STRIP                      -->
    <!-- ============================================================== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Metric 1: Deal Value -->
        <div class="p-4 bg-white border border-zinc-200/90 rounded-2xl shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-zinc-500">Estimasi Nilai Transaksi</span>
                <span class="p-1.5 rounded-xl bg-zinc-100 text-zinc-800">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </span>
            </div>
            <div class="mt-3">
                <div class="text-xl sm:text-2xl font-black text-black font-mono tracking-tight" x-text="formatRupiah(prospect.deal_value)"></div>
                <div class="text-[11px] text-zinc-400 mt-0.5 flex items-center gap-1">
                    <span>Total Pax:</span>
                    <strong class="text-zinc-700" x-text="calcTotalPax() + ' Jamaah'"></strong>
                </div>
            </div>
        </div>

        <!-- Metric 2: DP Paid & Balance -->
        <div class="p-4 bg-white border border-zinc-200/90 rounded-2xl shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-zinc-500">Status Pembayaran DP</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase border"
                      :class="getPaymentBadgeClass(prospect.payment_status)"
                      x-text="getPaymentLabel(prospect.payment_status)"></span>
            </div>
            <div class="mt-3">
                <div class="text-xl sm:text-2xl font-black font-mono tracking-tight"
                     :class="parseFloat(prospect.dp_amount) > 0 ? 'text-emerald-700' : 'text-zinc-400'"
                     x-text="formatRupiah(prospect.dp_amount)"></div>
                <div class="text-[11px] text-zinc-500 mt-0.5 flex items-center justify-between">
                    <span>Sisa: <strong class="font-mono text-zinc-900" x-text="formatRupiah(calcBalanceDue())"></strong></span>
                    <button type="button" @click="openPaymentModal()" class="text-xs font-semibold text-black hover:underline">
                        Edit DP
                    </button>
                </div>
            </div>
        </div>

        <!-- Metric 3: Target Keberangkatan & Countdown -->
        <div class="p-4 bg-white border border-zinc-200/90 rounded-2xl shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-zinc-500">Jadwal Keberangkatan</span>
                <span class="p-1.5 rounded-xl bg-zinc-100 text-zinc-800">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </span>
            </div>
            <div class="mt-3">
                <div class="text-base sm:text-lg font-bold text-black font-mono truncate"
                     x-text="prospect.departure_date ? formatDateOnly(prospect.departure_date) : (prospect.target_month || prospect.package_departure_info || 'Belum Ditentukan')"></div>
                <div class="text-[11px] mt-0.5">
                    <?php if ($daysToDeparture !== null): ?>
                        <?php if ($daysToDeparture > 0): ?>
                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-900 border border-amber-200">
                                H-<?= $daysToDeparture ?> Menuju Keberangkatan
                            </span>
                        <?php elseif ($daysToDeparture === 0): ?>
                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                Berangkat Hari Ini!
                            </span>
                        <?php else: ?>
                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold bg-zinc-100 text-zinc-600">
                                Sudah Berangkat (<?= abs($daysToDeparture) ?> hari lalu)
                            </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-zinc-400">Target: <?= htmlspecialchars($prospect['target_month'] ?: 'Fleksibel') ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Metric 4: Jadwal Follow-Up Berikutnya -->
        <div class="p-4 bg-white border border-zinc-200/90 rounded-2xl shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-zinc-500">Jadwal Follow-up Berikutnya</span>
                <span class="p-1.5 rounded-xl bg-zinc-100 text-zinc-800">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                </span>
            </div>
            <div class="mt-3">
                <div class="text-base sm:text-lg font-bold font-mono"
                     :class="prospect.next_followup_date ? 'text-black' : 'text-zinc-400'"
                     x-text="prospect.next_followup_date ? formatDateOnly(prospect.next_followup_date) : 'Belum Dijadwalkan'"></div>
                <div class="text-[11px] text-zinc-400 mt-0.5">
                    Terakhir kontak: <strong class="text-zinc-700" x-text="prospect.last_followup_at ? formatTimeAgo(prospect.last_followup_at) : 'Belum pernah'"></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- 4. ENTERPRISE 3-COLUMN CRM WORKSPACE                           -->
    <!-- ============================================================== -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- ========================================================== -->
        <!-- COLUMN 1: PROFIL CALON JAMAAH & KUALIFIKASI NPGD (4 cols)   -->
        <!-- ========================================================== -->
        <div class="lg:col-span-4 space-y-5">

            <!-- Card 1.1: Data Kontak & Akuisisi Lead -->
            <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-black flex items-center gap-2">
                        <svg class="w-4 h-4 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span>Kontak & Sumber Prospek</span>
                    </h2>
                    <button type="button" @click="openEditModal()" class="text-xs text-zinc-400 hover:text-black font-semibold">
                        Edit
                    </button>
                </div>

                <div class="space-y-3.5 text-xs">
                    <!-- WhatsApp -->
                    <div>
                        <span class="text-zinc-400 block text-[11px]">Nomor WhatsApp</span>
                        <div class="mt-1 flex items-center justify-between bg-zinc-50 border border-zinc-200/80 rounded-xl px-3 py-2">
                            <span class="font-mono font-bold text-zinc-900 text-sm" x-text="prospect.phone || '-'"></span>
                            <div class="flex items-center gap-1.5">
                                <?php if (!empty($prospect['phone'])): ?>
                                    <button type="button" @click="window.copyToClipboard(prospect.phone, 'Nomor WhatsApp tersalin!')"
                                            title="Salin Nomor"
                                            class="p-1.5 text-zinc-500 hover:text-black rounded-lg hover:bg-zinc-200 transition">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                    </button>
                                    <a href="https://wa.me/<?= $prospect['phone'] ?>" target="_blank"
                                       title="Buka Chat WhatsApp"
                                       class="p-1.5 text-emerald-700 hover:text-emerald-900 rounded-lg hover:bg-emerald-100 transition">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 21 1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"/></svg>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Kota & Sumber Lead -->
                    <div class="grid grid-cols-2 gap-3 pt-1">
                        <div>
                            <span class="text-zinc-400 block text-[11px]">Kota Domisili</span>
                            <div class="font-bold text-zinc-900 mt-0.5 flex items-center gap-1.5">
                                <svg class="w-3 h-3 text-zinc-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                                <span class="truncate" x-text="prospect.city || '- Belum Dicatat -'"></span>
                            </div>
                        </div>
                        <div>
                            <span class="text-zinc-400 block text-[11px]">Saluran Masuk (Lead)</span>
                            <span class="inline-block mt-0.5 px-2 py-0.5 rounded-md text-[11px] font-semibold border"
                                  :class="getLeadSourceClass(prospect.lead_source)"
                                  x-text="formatLeadSource(prospect.lead_source)"></span>
                        </div>
                    </div>

                    <!-- Meta Ads Attribution (if available) -->
                    <?php if (!empty($prospect['ad_id']) || !empty($prospect['campaign_id'])): ?>
                        <div class="p-2.5 bg-blue-50/70 border border-blue-200/80 rounded-xl space-y-1">
                            <div class="flex items-center gap-1.5 text-[11px] font-bold text-blue-900">
                                <svg class="w-3.5 h-3.5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                                <span>Meta Ads Attribution</span>
                            </div>
                            <div class="text-[10px] text-blue-800 font-mono space-y-0.5">
                                <?php if (!empty($prospect['ad_id'])): ?>
                                    <div>Ad ID: <?= htmlspecialchars($prospect['ad_id']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($prospect['campaign_id'])): ?>
                                    <div>Campaign: <?= htmlspecialchars($prospect['campaign_id']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Waktu Masuk Prospek -->
                    <div class="pt-2 border-t border-zinc-100 flex items-center justify-between text-[11px] text-zinc-400">
                        <span>Terdaftar:</span>
                        <span class="font-mono text-zinc-600"><?= date('d M Y, H:i', strtotime($prospect['created_at'])) ?> WIB</span>
                    </div>
                </div>
            </div>

            <!-- Card 1.2: Kualifikasi Kebutuhan Umroh (NPGD Discovery) -->
            <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-black flex items-center gap-2">
                        <svg class="w-4 h-4 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 4.24 4.24"/><path d="m14.83 9.17 4.24-4.24"/><path d="m14.83 14.83 4.24 4.24"/><path d="m9.17 14.83-4.24 4.24"/><circle cx="12" cy="12" r="4"/></svg>
                        <span>Kualifikasi Kebutuhan (NPGD)</span>
                    </h2>
                    <span class="text-[10px] text-zinc-400 font-mono">Need &bull; Pain &bull; Gain</span>
                </div>

                <div class="space-y-3 text-xs">
                    <!-- Target Keberangkatan & Budget -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-zinc-50 border border-zinc-200/80 rounded-xl p-2.5">
                            <span class="text-zinc-400 block text-[10px] uppercase tracking-wider font-semibold">Target Periode</span>
                            <div class="font-bold text-zinc-900 mt-1" x-text="prospect.target_month || '- Belum Tentu -'"></div>
                        </div>
                        <div class="bg-zinc-50 border border-zinc-200/80 rounded-xl p-2.5">
                            <span class="text-zinc-400 block text-[10px] uppercase tracking-wider font-semibold">Kisaran Budget</span>
                            <div class="font-bold text-zinc-900 mt-1" x-text="prospect.budget_range || '- Belum Ditentukan -'"></div>
                        </div>
                    </div>

                    <!-- Preferensi Kamar & Pengambil Keputusan -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-zinc-50 border border-zinc-200/80 rounded-xl p-2.5">
                            <span class="text-zinc-400 block text-[10px] uppercase tracking-wider font-semibold">Preferensi Kamar</span>
                            <div class="font-bold text-zinc-900 mt-1 capitalize" x-text="prospect.room_preference ? ('Kamar ' + prospect.room_preference) : '- Belum Pilih -'"></div>
                        </div>
                        <div class="bg-zinc-50 border border-zinc-200/80 rounded-xl p-2.5">
                            <span class="text-zinc-400 block text-[10px] uppercase tracking-wider font-semibold">Decision Maker</span>
                            <div class="font-bold text-zinc-900 mt-1 capitalize" x-text="formatDecisionMaker(prospect.decision_maker)"></div>
                        </div>
                    </div>

                    <!-- Kebutuhan Khusus / Kondisi Fisik -->
                    <div>
                        <span class="text-zinc-400 block text-[11px] mb-1">Kebutuhan Khusus / Lansia / Fisik</span>
                        <div class="p-3 bg-zinc-50 border border-zinc-200 rounded-xl text-zinc-800 leading-relaxed min-h-[50px]"
                             x-text="prospect.special_needs || '- Tidak ada kebutuhan khusus / fisik prima -'"></div>
                    </div>
                </div>
            </div>

            <!-- Card 1.3: Kesiapan Dokumen Pokok & Catatan Tambahan -->
            <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-black flex items-center gap-2">
                        <svg class="w-4 h-4 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
                        <span>Kesiapan Dokumen & Catatan</span>
                    </h2>
                </div>

                <div class="space-y-3.5 text-xs">
                    <!-- Status Paspor & Vaksin -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 border rounded-xl"
                             :class="prospect.passport_status === 'sudah_ada' ? 'bg-emerald-50/50 border-emerald-200' : 'bg-zinc-50 border-zinc-200'">
                            <span class="text-[10px] text-zinc-400 uppercase font-semibold block">Status Paspor</span>
                            <div class="font-bold text-zinc-900 mt-1 flex items-center gap-1.5">
                                <template x-if="prospect.passport_status === 'sudah_ada'">
                                    <svg class="w-3.5 h-3.5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                </template>
                                <span x-text="formatPassportStatus(prospect.passport_status)"></span>
                            </div>
                        </div>

                        <div class="p-3 border rounded-xl"
                             :class="prospect.vaccine_status === 'sudah' ? 'bg-emerald-50/50 border-emerald-200' : 'bg-zinc-50 border-zinc-200'">
                            <span class="text-[10px] text-zinc-400 uppercase font-semibold block">Vaksin Meningitis</span>
                            <div class="font-bold text-zinc-900 mt-1 flex items-center gap-1.5">
                                <template x-if="prospect.vaccine_status === 'sudah'">
                                    <svg class="w-3.5 h-3.5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                </template>
                                <span x-text="prospect.vaccine_status === 'sudah' ? 'Sudah Vaksin' : 'Belum Vaksin'"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Dossier Catatan Bebas CS -->
                    <div>
                        <span class="text-zinc-400 block text-[11px] mb-1">Catatan Tambahan Jamaah</span>
                        <div class="bg-zinc-50 border border-zinc-200 rounded-xl p-3 text-xs text-zinc-800 leading-relaxed whitespace-pre-line min-h-[60px]"
                             x-text="prospect.notes || '- Belum ada catatan khusus -'"></div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ========================================================== -->
        <!-- COLUMN 2: DEAL & PAKET HUB (FINANSIAL & KAMAR) (4 cols)    -->
        <!-- ========================================================== -->
        <div class="lg:col-span-4 space-y-5">

            <!-- Card 2.1: Paket Umroh Terpilih -->
            <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-black flex items-center gap-2">
                        <svg class="w-4 h-4 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></svg>
                        <span>Paket Umroh Pilihan</span>
                    </h2>
                    
                    <!-- Quick Switch Package Dropdown -->
                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                        <button type="button" @click="open = !open" 
                                class="text-xs font-semibold text-zinc-500 hover:text-black flex items-center gap-1">
                            <span>Ganti Paket</span>
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                             class="absolute right-0 z-50 mt-1.5 w-64 bg-white border border-zinc-200 rounded-xl shadow-xl py-1 text-xs divide-y divide-zinc-50">
                            <button type="button" @click="selectPackage(null); open = false"
                                    class="w-full px-3 py-2 text-left hover:bg-zinc-50 text-zinc-500 italic">
                                -- Belum Memilih Paket --
                            </button>
                            <template x-for="pkg in packagesList" :key="pkg.id">
                                <button type="button" @click="selectPackage(pkg.id); open = false"
                                        class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                        :class="prospect.package_id == pkg.id ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                    <div class="truncate pr-2">
                                        <div class="truncate" x-text="pkg.name"></div>
                                        <div class="text-[10px] text-zinc-400 font-mono" x-text="'Rp ' + pkg.price"></div>
                                    </div>
                                    <svg x-show="prospect.package_id == pkg.id" class="w-3.5 h-3.5 text-black shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Package Details -->
                <template x-if="prospect.package_id && currentPackage">
                    <div class="space-y-3.5 text-xs">
                        <div>
                            <div class="text-sm font-extrabold text-black leading-snug" x-text="currentPackage.name"></div>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="font-mono font-extrabold text-black text-sm" x-text="'Rp ' + currentPackage.price"></span>
                                <span class="text-[11px] text-zinc-500">/ Quad</span>
                            </div>
                        </div>

                        <!-- Departure & Quota Alerts -->
                        <div class="grid grid-cols-2 gap-2.5 p-3 bg-zinc-50 border border-zinc-200/90 rounded-xl">
                            <div>
                                <span class="text-zinc-400 block text-[10px] uppercase font-semibold">Sisa Kuota Seat</span>
                                <div class="font-bold mt-0.5 flex items-center gap-1.5"
                                     :class="currentPackage.quota_remaining <= 5 ? 'text-rose-700' : 'text-zinc-900'">
                                    <span class="font-mono text-sm" x-text="currentPackage.quota_remaining ?? '-'"></span>
                                    <span class="text-[10px]">Seat Tersisa</span>
                                </div>
                            </div>
                            <div>
                                <span class="text-zinc-400 block text-[10px] uppercase font-semibold">Penerbangan</span>
                                <div class="font-bold text-zinc-900 mt-0.5 truncate" x-text="currentPackage.airline || '-'"></div>
                            </div>
                        </div>

                        <!-- Hotels -->
                        <div class="space-y-1.5 text-[11px]">
                            <div class="flex items-start gap-2">
                                <span class="w-16 text-zinc-400 shrink-0 font-medium">Makkah:</span>
                                <span class="text-zinc-800 font-semibold" x-text="currentPackage.hotel_makkah || '-'"></span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="w-16 text-zinc-400 shrink-0 font-medium">Madinah:</span>
                                <span class="text-zinc-800 font-semibold" x-text="currentPackage.hotel_madinah || '-'"></span>
                            </div>
                        </div>

                        <!-- Action buttons for Package: Copy facilities & View flyer -->
                        <div class="pt-2 border-t border-zinc-100 flex items-center gap-2">
                            <button type="button" @click="copyPackageFacilities()"
                                    class="flex-1 py-1.5 px-2 bg-zinc-100 hover:bg-zinc-200 text-zinc-800 rounded-xl text-xs font-semibold transition flex items-center justify-center gap-1.5">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                <span>Salin Rincian</span>
                            </button>

                            <template x-if="currentPackage.flyer_image">
                                <button type="button" @click="showFlyerModal = true"
                                        class="py-1.5 px-3 bg-white hover:bg-zinc-50 border border-zinc-200 text-zinc-700 rounded-xl text-xs font-semibold transition flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                                    <span>Flyer</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="!prospect.package_id">
                    <div class="py-8 text-center space-y-3">
                        <div class="w-10 h-10 mx-auto rounded-full bg-zinc-100 border border-zinc-200 text-zinc-400 flex items-center justify-center">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect width="20" height="14" x="2" y="7" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                        </div>
                        <div class="text-xs text-zinc-500">
                            Calon jamaah belum menentukan pilihan paket umroh.
                        </div>
                        <div x-data="{ open: false }" class="relative inline-block" @click.outside="open = false">
                            <button type="button" @click="open = !open"
                                    class="px-4 py-2 bg-black hover:bg-zinc-800 text-white rounded-xl text-xs font-semibold transition shadow-2xs">
                                + Pilih Paket Sekarang
                            </button>
                            <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                                 class="absolute left-1/2 -translate-x-1/2 z-50 mt-2 w-64 bg-white border border-zinc-200 rounded-xl shadow-xl py-1 text-xs divide-y divide-zinc-50 text-left">
                                <template x-for="pkg in packagesList" :key="pkg.id">
                                    <button type="button" @click="selectPackage(pkg.id); open = false"
                                            class="w-full px-3 py-2 text-left hover:bg-zinc-50 transition">
                                        <div class="font-bold text-zinc-900 truncate" x-text="pkg.name"></div>
                                        <div class="text-[10px] text-zinc-400 font-mono" x-text="'Rp ' + pkg.price"></div>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Card 2.2: Rincian Kamar & Pax (Kalkulator Finansial) -->
            <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-black flex items-center gap-2">
                        <svg class="w-4 h-4 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/></svg>
                        <span>Rincian Kamar & Jumlah Jamaah</span>
                    </h2>
                    <span class="text-[11px] font-bold font-mono text-zinc-800" x-text="calcTotalPax() + ' Jamaah'"></span>
                </div>

                <div class="space-y-2.5 text-xs">
                    <!-- Quad -->
                    <div class="flex items-center justify-between p-2.5 bg-zinc-50 border border-zinc-200/80 rounded-xl">
                        <div>
                            <span class="font-bold text-zinc-900 block">Kamar Quad (4 Org)</span>
                            <span class="text-[10px] text-zinc-400 font-mono" x-text="formatRupiah(getPackageRoomPrice('quad')) + ' / org'"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center border border-zinc-300 rounded-lg bg-white overflow-hidden shadow-2xs">
                                <button type="button" @click="stepPax('pax_quad', -1)" :disabled="isUpdatingPax || prospect.pax_quad <= 0"
                                        class="w-7 h-7 flex items-center justify-center hover:bg-zinc-100 text-zinc-700 disabled:opacity-30">&minus;</button>
                                <span class="w-8 text-center font-bold font-mono text-xs" x-text="prospect.pax_quad || 0"></span>
                                <button type="button" @click="stepPax('pax_quad', 1)" :disabled="isUpdatingPax"
                                        class="w-7 h-7 flex items-center justify-center hover:bg-zinc-100 text-zinc-700 disabled:opacity-30">&plus;</button>
                            </div>
                        </div>
                    </div>

                    <!-- Triple -->
                    <div class="flex items-center justify-between p-2.5 bg-zinc-50 border border-zinc-200/80 rounded-xl">
                        <div>
                            <span class="font-bold text-zinc-900 block">Kamar Triple (3 Org)</span>
                            <span class="text-[10px] text-zinc-400 font-mono" x-text="formatRupiah(getPackageRoomPrice('triple')) + ' / org'"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center border border-zinc-300 rounded-lg bg-white overflow-hidden shadow-2xs">
                                <button type="button" @click="stepPax('pax_triple', -1)" :disabled="isUpdatingPax || prospect.pax_triple <= 0"
                                        class="w-7 h-7 flex items-center justify-center hover:bg-zinc-100 text-zinc-700 disabled:opacity-30">&minus;</button>
                                <span class="w-8 text-center font-bold font-mono text-xs" x-text="prospect.pax_triple || 0"></span>
                                <button type="button" @click="stepPax('pax_triple', 1)" :disabled="isUpdatingPax"
                                        class="w-7 h-7 flex items-center justify-center hover:bg-zinc-100 text-zinc-700 disabled:opacity-30">&plus;</button>
                            </div>
                        </div>
                    </div>

                    <!-- Double -->
                    <div class="flex items-center justify-between p-2.5 bg-zinc-50 border border-zinc-200/80 rounded-xl">
                        <div>
                            <span class="font-bold text-zinc-900 block">Kamar Double (2 Org)</span>
                            <span class="text-[10px] text-zinc-400 font-mono" x-text="formatRupiah(getPackageRoomPrice('double')) + ' / org'"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center border border-zinc-300 rounded-lg bg-white overflow-hidden shadow-2xs">
                                <button type="button" @click="stepPax('pax_double', -1)" :disabled="isUpdatingPax || prospect.pax_double <= 0"
                                        class="w-7 h-7 flex items-center justify-center hover:bg-zinc-100 text-zinc-700 disabled:opacity-30">&minus;</button>
                                <span class="w-8 text-center font-bold font-mono text-xs" x-text="prospect.pax_double || 0"></span>
                                <button type="button" @click="stepPax('pax_double', 1)" :disabled="isUpdatingPax"
                                        class="w-7 h-7 flex items-center justify-center hover:bg-zinc-100 text-zinc-700 disabled:opacity-30">&plus;</button>
                            </div>
                        </div>
                    </div>

                    <!-- Infant -->
                    <div class="flex items-center justify-between p-2.5 bg-zinc-50 border border-zinc-200/80 rounded-xl">
                        <div>
                            <span class="font-bold text-zinc-900 block">Bayi / Infant (&lt; 2 Thn)</span>
                            <span class="text-[10px] text-zinc-400 font-mono" x-text="formatRupiah(getPackageRoomPrice('infant')) + ' / bayi'"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center border border-zinc-300 rounded-lg bg-white overflow-hidden shadow-2xs">
                                <button type="button" @click="stepPax('pax_infant', -1)" :disabled="isUpdatingPax || prospect.pax_infant <= 0"
                                        class="w-7 h-7 flex items-center justify-center hover:bg-zinc-100 text-zinc-700 disabled:opacity-30">&minus;</button>
                                <span class="w-8 text-center font-bold font-mono text-xs" x-text="prospect.pax_infant || 0"></span>
                                <button type="button" @click="stepPax('pax_infant', 1)" :disabled="isUpdatingPax"
                                        class="w-7 h-7 flex items-center justify-center hover:bg-zinc-100 text-zinc-700 disabled:opacity-30">&plus;</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2.3: Ringkasan Finansial & Pembayaran DP -->
            <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-black flex items-center gap-2">
                        <svg class="w-4 h-4 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                        <span>Ringkasan Finansial & DP</span>
                    </h2>
                    <button type="button" @click="openPaymentModal()"
                            class="text-xs font-bold text-black hover:underline flex items-center gap-1">
                        <span>Update DP</span>
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="p-3.5 bg-zinc-50 border border-zinc-200 rounded-xl space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500">Total Nilai Transaksi:</span>
                            <span class="font-extrabold font-mono text-sm text-black" x-text="formatRupiah(prospect.deal_value)"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500">Target Minimal DP (Seat):</span>
                            <span class="font-bold font-mono text-zinc-700" x-text="formatRupiah(calcTargetDp())"></span>
                        </div>
                        <div class="flex items-center justify-between pt-1 border-t border-zinc-200/80">
                            <span class="font-semibold text-emerald-800">DP Terbayar:</span>
                            <span class="font-extrabold font-mono text-emerald-700" x-text="formatRupiah(prospect.dp_amount)"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-zinc-600">Sisa Pelunasan:</span>
                            <span class="font-extrabold font-mono text-black" x-text="formatRupiah(calcBalanceDue())"></span>
                        </div>
                    </div>

                    <!-- Payment Status Badge & Meta CAPI badge -->
                    <div class="flex items-center justify-between pt-1">
                        <span class="text-zinc-500">Status Pembayaran:</span>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase border"
                              :class="getPaymentBadgeClass(prospect.payment_status)"
                              x-text="getPaymentLabel(prospect.payment_status)"></span>
                    </div>

                    <template x-if="prospect.status === 'closed_won'">
                        <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center gap-2 text-xs text-emerald-900">
                            <svg class="w-4 h-4 text-emerald-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            <div>
                                <strong class="block">Closing Berhasil (Won)!</strong>
                                <span class="text-[11px] text-emerald-700">DP tercatat & event Meta CAPI Purchase terkirim.</span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Card 2.4: Alasan Batal (Tampil jika status closed_lost) -->
            <template x-if="prospect.status === 'closed_lost'">
                <div class="bg-zinc-50 border border-zinc-300 rounded-2xl p-5 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-zinc-200">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-rose-800 flex items-center gap-2">
                            <svg class="w-4 h-4 text-rose-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            <span>Evaluasi Pembatalan (Lost)</span>
                        </h2>
                    </div>
                    <div class="space-y-2 text-xs">
                        <div>
                            <span class="text-zinc-500 block text-[11px]">Alasan Pokok:</span>
                            <span class="font-bold text-zinc-900" x-text="formatLostReason(prospect.lost_reason)"></span>
                        </div>
                        <div>
                            <span class="text-zinc-500 block text-[11px]">Keterangan Detail:</span>
                            <div class="p-2.5 bg-white border border-zinc-200 rounded-xl text-zinc-700 mt-0.5 leading-relaxed"
                                 x-text="prospect.lost_reason_detail || '- Tidak ada keterangan detail -'"></div>
                        </div>
                    </div>
                </div>
            </template>

        </div>

        <!-- ========================================================== -->
        <!-- COLUMN 3: ACTIVITY HUB & TIMELINE KOMUNIKASI (4 cols)      -->
        <!-- ========================================================== -->
        <div class="lg:col-span-4 space-y-5">

            <!-- Card 3.1: Form Catat Follow-up Cepat -->
            <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-black flex items-center gap-2">
                        <svg class="w-4 h-4 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        <span>Catat Hasil Komunikasi</span>
                    </h2>
                    <span class="text-[10px] text-zinc-400">Update Waktu Kontak</span>
                </div>

                <!-- Fast Chips for Common Follow-up Results -->
                <div class="space-y-1.5">
                    <span class="text-[10px] uppercase font-bold text-zinc-400 tracking-wider">Template Catatan Cepat:</span>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="chip in quickChips" :key="chip">
                            <button type="button" @click="appendQuickChip(chip)"
                                    class="px-2 py-1 bg-zinc-100 hover:bg-zinc-200 text-zinc-700 rounded-lg text-[10px] font-medium transition">
                                <span x-text="chip"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <form @submit.prevent="submitFollowup()" class="space-y-3 text-xs">
                    <div>
                        <textarea x-model="followupForm.note" required rows="3"
                                  class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black text-xs placeholder-zinc-400"
                                  placeholder="Tulis ringkasan hasil percakapan / tindak lanjut jamaah..."></textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <!-- Update Status Dropdown -->
                        <div>
                            <label class="block font-semibold text-zinc-700 mb-1">Perbarui Status</label>
                            <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                                <button type="button" @click="open = !open"
                                        class="w-full flex items-center justify-between px-3 py-2 border border-zinc-300 bg-white rounded-xl text-xs font-semibold text-zinc-900 focus:outline-none focus:border-black shadow-2xs">
                                    <span class="truncate" x-text="getStatusLabel(followupForm.status)"></span>
                                    <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                                </button>
                                <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                                     class="absolute z-50 mt-1 w-full bg-white border border-zinc-200 rounded-xl shadow-xl max-h-56 overflow-y-auto py-1 text-xs divide-y divide-zinc-50">
                                    <template x-for="st in statusOptions" :key="st.val">
                                        <button type="button" @click="followupForm.status = st.val; open = false"
                                                class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                                :class="followupForm.status === st.val ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                            <span x-text="st.label"></span>
                                            <svg x-show="followupForm.status === st.val" class="w-3.5 h-3.5 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Date input for Next Followup -->
                        <div>
                            <label class="block font-semibold text-zinc-700 mb-1">Next Follow-up</label>
                            <input type="date" x-model="followupForm.next_followup_date"
                                   class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black text-xs font-mono">
                        </div>
                    </div>

                    <div class="pt-1 flex justify-end">
                        <button type="submit" :disabled="isSubmittingFollowup"
                                class="w-full py-2 bg-black hover:bg-zinc-800 text-white rounded-xl text-xs font-semibold transition flex items-center justify-center gap-2 shadow-2xs disabled:opacity-50">
                            <span x-text="isSubmittingFollowup ? 'Menyimpan...' : 'Simpan Log Aktivitas'"></span>
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Card 3.2: Quick Copy Skrip Komunikasi Relevan -->
            <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 shadow-2xs space-y-3.5" x-data="{ scriptTab: '<?= $prospect['status'] === 'closing' ? 'closing' : ($prospect['status'] === 'objection' ? 'objection' : 'followup') ?>' }">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-black flex items-center gap-2">
                        <svg class="w-4 h-4 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                        <span>Skrip Obrolan Relevan</span>
                    </h2>
                    <span class="text-[10px] text-zinc-400">1-Klik Salin WA</span>
                </div>

                <!-- Category Tabs -->
                <div class="flex items-center bg-zinc-100 p-1 rounded-xl text-xs">
                    <button type="button" @click="scriptTab = 'followup'"
                            class="flex-1 py-1 rounded-lg font-semibold transition text-center"
                            :class="scriptTab === 'followup' ? 'bg-white text-black shadow-2xs' : 'text-zinc-500 hover:text-black'">
                        Follow-Up
                    </button>
                    <button type="button" @click="scriptTab = 'closing'"
                            class="flex-1 py-1 rounded-lg font-semibold transition text-center"
                            :class="scriptTab === 'closing' ? 'bg-white text-black shadow-2xs' : 'text-zinc-500 hover:text-black'">
                        Closing DP
                    </button>
                    <button type="button" @click="scriptTab = 'objection'"
                            class="flex-1 py-1 rounded-lg font-semibold transition text-center"
                            :class="scriptTab === 'objection' ? 'bg-white text-black shadow-2xs' : 'text-zinc-500 hover:text-black'">
                        Keberatan
                    </button>
                </div>

                <!-- Tab Contents -->
                <div class="space-y-2.5 max-h-72 overflow-y-auto pr-1">
                    <template x-for="(sc, scIdx) in getCuratedScripts(scriptTab)" :key="scIdx">
                        <div class="p-3 bg-zinc-50 border border-zinc-200/80 rounded-xl space-y-2 text-xs group hover:border-zinc-300 transition">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-zinc-900" x-text="sc.title"></span>
                                <button type="button" @click="window.copyToClipboard(sc.text, 'Skrip berhasil disalin!')"
                                        class="px-2 py-1 bg-white hover:bg-black hover:text-white border border-zinc-200 rounded-lg text-[10px] font-semibold transition flex items-center gap-1">
                                    <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                    <span>Salin</span>
                                </button>
                            </div>
                            <div class="text-zinc-600 text-[11px] leading-relaxed whitespace-pre-line line-clamp-3 group-hover:line-clamp-none transition-all"
                                 x-text="sc.text"></div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Card 3.3: Riwayat Aktivitas & Timeline Terpadu -->
            <div class="bg-white border border-zinc-200/90 rounded-2xl p-5 shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-black flex items-center gap-2">
                        <svg class="w-4 h-4 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20v-6M6 20V10M18 20V4"/></svg>
                        <span>Timeline Aktivitas Terpadu</span>
                    </h2>
                    <span class="text-[11px] text-zinc-500 font-semibold" x-text="logsList.length + ' Entri'"></span>
                </div>

                <!-- Timeline List -->
                <div class="relative pl-6 space-y-5 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-zinc-200 max-h-96 overflow-y-auto pr-1">
                    <template x-for="(item, idx) in logsList" :key="item.id || idx">
                        <div class="relative group">
                            <!-- Bullet Icon -->
                            <div class="absolute -left-6 top-1 w-5 h-5 rounded-full border-2 border-white bg-black text-white flex items-center justify-center shadow-2xs">
                                <template x-if="item.action_type === 'created'">
                                    <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                </template>
                                <template x-if="item.action_type === 'followup_logged'">
                                    <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                </template>
                                <template x-if="item.action_type === 'status_changed'">
                                    <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                                </template>
                                <template x-if="item.action_type === 'package_assigned'">
                                    <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><rect width="20" height="14" x="2" y="7" rx="2" ry="2"/></svg>
                                </template>
                                <template x-if="item.action_type === 'meta_capi'">
                                    <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                </template>
                                <template x-if="!['created', 'followup_logged', 'status_changed', 'package_assigned', 'meta_capi'].includes(item.action_type)">
                                    <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                </template>
                            </div>

                            <!-- Content -->
                            <div class="bg-zinc-50 border border-zinc-200/90 rounded-xl p-3 text-xs transition hover:border-zinc-300">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-bold text-black" x-text="item.title"></span>
                                    <span class="text-[10px] text-zinc-400 font-mono" x-text="formatDateShort(item.created_at)"></span>
                                </div>
                                <div class="text-[11px] text-zinc-500 mt-0.5">
                                    Oleh: <strong class="text-zinc-700" x-text="item.user_name || 'Sistem / CS'"></strong>
                                </div>
                                <div class="text-zinc-700 mt-1.5 text-xs leading-relaxed whitespace-pre-line border-t border-zinc-200/60 pt-1.5"
                                     x-text="item.description || '-'"></div>
                            </div>
                        </div>
                    </template>

                    <template x-if="logsList.length === 0">
                        <div class="text-center py-6 text-zinc-400 text-xs">
                            Belum ada catatan aktivitas untuk prospek ini.
                        </div>
                    </template>
                </div>
            </div>

        </div>

    </div>

    <!-- ============================================================== -->
    <!-- 5. MODAL EDIT PROFIL CRM LENGKAP                              -->
    <!-- ============================================================== -->
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/60 flex items-center justify-center p-4">
        <div @click.away="showEditModal = false" class="bg-white border border-zinc-200 rounded-2xl max-w-2xl w-full p-6 shadow-2xl relative max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-zinc-100">
                <div>
                    <h3 class="text-sm font-bold text-black uppercase tracking-wider">Edit Profil CRM 360°</h3>
                    <p class="text-xs text-zinc-500">Perbarui identitas, kualifikasi NPGD, paket, dan status prospek.</p>
                </div>
                <button type="button" @click="showEditModal = false" class="text-zinc-400 hover:text-black text-xl font-bold">&times;</button>
            </div>

            <form @submit.prevent="saveProspectEdit()" class="space-y-4 text-xs">
                <!-- Row 1: Nama & WhatsApp -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">Nama Lengkap Jamaah *</label>
                        <input type="text" x-model="editForm.name" required
                               class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black font-semibold text-zinc-900">
                    </div>
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">No. WhatsApp / HP *</label>
                        <input type="text" x-model="editForm.phone" required
                               class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black font-mono">
                    </div>
                </div>

                <!-- Row 2: Kota & Sumber Lead -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">Kota Domisili</label>
                        <input type="text" x-model="editForm.city"
                               class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black"
                               placeholder="Contoh: Jakarta Selatan">
                    </div>
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">Sumber Prospek (Lead Source)</label>
                        <select x-model="editForm.lead_source" class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black bg-white">
                            <option value="whatsapp">WhatsApp Inbound</option>
                            <option value="meta_ads">Meta Ads (Facebook / Instagram)</option>
                            <option value="website_form">Formulir Website</option>
                            <option value="referral">Referral Alumni Jamaah</option>
                            <option value="walk_in">Walk-in Kantor</option>
                            <option value="repeat_order">Repeat Order</option>
                            <option value="other">Lainnya</option>
                        </select>
                    </div>
                </div>

                <!-- Row 3: NPGD (Target Month & Budget Range) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">Target Periode Keberangkatan</label>
                        <input type="text" x-model="editForm.target_month"
                               class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black"
                               placeholder="Contoh: November 2026, Awal Ramadhan">
                    </div>
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">Kisaran Budget</label>
                        <select x-model="editForm.budget_range" class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black bg-white">
                            <option value="">-- Pilih Budget --</option>
                            <option value="< 28 Juta">&lt; 28 Juta</option>
                            <option value="28 - 35 Juta">28 - 35 Juta</option>
                            <option value="> 35 Juta">&gt; 35 Juta</option>
                            <option value="Fleksibel">Fleksibel / Sesuai Fasilitas</option>
                        </select>
                    </div>
                </div>

                <!-- Row 4: Preferensi Kamar & Decision Maker -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">Preferensi Kamar</label>
                        <select x-model="editForm.room_preference" class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black bg-white">
                            <option value="">-- Belum Pasti --</option>
                            <option value="quad">Quad (1 Kamar 4 Orang)</option>
                            <option value="triple">Triple (1 Kamar 3 Orang)</option>
                            <option value="double">Double (1 Kamar 2 Orang)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">Pengambil Keputusan (Decision Maker)</label>
                        <select x-model="editForm.decision_maker" class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black bg-white">
                            <option value="">-- Pilih --</option>
                            <option value="diri_sendiri">Diri Sendiri</option>
                            <option value="pasangan">Pasangan (Suami/Istri)</option>
                            <option value="anak">Anak</option>
                            <option value="keluarga_besar">Keluarga Besar</option>
                            <option value="kantor">Instansi / Kantor</option>
                        </select>
                    </div>
                </div>

                <!-- Row 5: Paspor & Vaksin -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">Status Paspor</label>
                        <select x-model="editForm.passport_status" class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black bg-white">
                            <option value="sudah_ada">Sudah Ada &amp; Berlaku</option>
                            <option value="proses_buat">Sedang Proses Buat</option>
                            <option value="perlu_perpanjang">Perlu Perpanjang</option>
                            <option value="belum_ada">Belum Ada</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">Status Vaksin Meningitis</label>
                        <select x-model="editForm.vaccine_status" class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black bg-white">
                            <option value="sudah">Sudah Vaksin</option>
                            <option value="belum">Belum Vaksin</option>
                        </select>
                    </div>
                </div>

                <!-- Row 6: Kebutuhan Khusus -->
                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Kebutuhan Khusus / Kondisi Fisik</label>
                    <textarea x-model="editForm.special_needs" rows="2"
                              class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black text-xs"
                              placeholder="Contoh: Butuh kursi roda, lansia butuh pendamping kamar..."></textarea>
                </div>

                <!-- Row 7: Pilihan Paket Umroh -->
                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Paket Umroh Pilihan</label>
                    <select x-model="editForm.package_id" class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black bg-white">
                        <option value="">-- Belum Memilih Paket --</option>
                        <template x-for="pkg in packagesList" :key="pkg.id">
                            <option :value="pkg.id" x-text="pkg.name + ' (Rp ' + pkg.price + ')'"></option>
                        </template>
                    </select>
                </div>

                <!-- Row 8: Pax Breakdown -->
                <div class="grid grid-cols-4 gap-2.5 p-3 bg-zinc-50 border border-zinc-200 rounded-xl">
                    <div>
                        <label class="block text-[10px] font-semibold text-zinc-500 mb-0.5">Pax Quad</label>
                        <input type="number" min="0" x-model="editForm.pax_quad"
                               class="w-full px-2 py-1.5 border border-zinc-300 rounded-lg text-center font-bold font-mono">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-zinc-500 mb-0.5">Pax Triple</label>
                        <input type="number" min="0" x-model="editForm.pax_triple"
                               class="w-full px-2 py-1.5 border border-zinc-300 rounded-lg text-center font-bold font-mono">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-zinc-500 mb-0.5">Pax Double</label>
                        <input type="number" min="0" x-model="editForm.pax_double"
                               class="w-full px-2 py-1.5 border border-zinc-300 rounded-lg text-center font-bold font-mono">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-zinc-500 mb-0.5">Pax Bayi</label>
                        <input type="number" min="0" x-model="editForm.pax_infant"
                               class="w-full px-2 py-1.5 border border-zinc-300 rounded-lg text-center font-bold font-mono">
                    </div>
                </div>

                <!-- Row 9: Catatan Umum & Jadwal Followup -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">Status Prospek</label>
                        <select x-model="editForm.status" class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black bg-white">
                            <template x-for="st in statusOptions" :key="st.val">
                                <option :value="st.val" x-text="st.label"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">Target Follow-up Berikutnya</label>
                        <input type="date" x-model="editForm.next_followup_date"
                               class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black font-mono">
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Catatan Tambahan Jamaah</label>
                    <textarea x-model="editForm.notes" rows="2"
                              class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black text-xs"
                              placeholder="Catatan bebas riwayat prospek..."></textarea>
                </div>

                <div class="pt-4 flex items-center justify-end gap-2 border-t border-zinc-100">
                    <button type="button" @click="showEditModal = false"
                            class="px-4 py-2 border border-zinc-300 text-zinc-700 hover:bg-zinc-50 rounded-xl transition">
                        Batal
                    </button>
                    <button type="submit" :disabled="isSubmittingEdit"
                            class="px-5 py-2 bg-black hover:bg-zinc-800 text-white rounded-xl font-semibold transition inline-flex items-center gap-1.5 shadow-2xs disabled:opacity-50">
                        <span x-text="isSubmittingEdit ? 'Menyimpan...' : 'Simpan Perubahan CRM'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- 6. MODAL EVALUASI PEMBATALAN (LOST REASON)                     -->
    <!-- ============================================================== -->
    <div x-show="showLostModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/60 flex items-center justify-center p-4">
        <div @click.away="showLostModal = false" class="bg-white border border-zinc-200 rounded-2xl max-w-md w-full p-6 shadow-2xl relative">
            <div class="flex items-center justify-between pb-3 mb-4 border-b border-zinc-100">
                <div>
                    <h3 class="text-sm font-bold text-black uppercase tracking-wider">Tandai Prospek Batal (Closed Lost)</h3>
                    <p class="text-xs text-zinc-500">Pilih alasan pembatalan untuk evaluasi konversi.</p>
                </div>
                <button type="button" @click="showLostModal = false" class="text-zinc-400 hover:text-black text-xl font-bold">&times;</button>
            </div>

            <form @submit.prevent="submitLostStatus()" class="space-y-3.5 text-xs">
                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Alasan Utama Pembatalan *</label>
                    <select x-model="lostForm.reason" required class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black bg-white">
                        <option value="">-- Pilih Alasan Batal --</option>
                        <option value="harga_kemahalan">Harga dirasa kemahalan / di luar budget</option>
                        <option value="jadwal_bentrok">Jadwal keberangkatan bentrok kerja/cuti</option>
                        <option value="pilih_travel_lain">Memilih travel umroh lain</option>
                        <option value="kendala_paspor">Kendala pembuatan / perpanjangan paspor</option>
                        <option value="masalah_kesehatan">Kondisi kesehatan fisik belum memungkinkan</option>
                        <option value="keluarga_tidak_setuju">Keluarga / pasangan belum mengizinkan</option>
                        <option value="no_response">Tidak merespon (Ghosting setelah difollow-up)</option>
                        <option value="lainnya">Alasan lainnya</option>
                    </select>
                </div>

                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Keterangan / Detail Alasan</label>
                    <textarea x-model="lostForm.detail" rows="3"
                              class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black text-xs"
                              placeholder="Tuliskan catatan tambahan mengapa jamaah batal closing..."></textarea>
                </div>

                <div class="pt-3 flex items-center justify-end gap-2 border-t border-zinc-100">
                    <button type="button" @click="showLostModal = false"
                            class="px-4 py-2 border border-zinc-300 text-zinc-700 hover:bg-zinc-50 rounded-xl transition">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-zinc-900 hover:bg-black text-white rounded-xl font-semibold transition shadow-2xs">
                        Konfirmasi Batal (Closed Lost)
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- 7. MODAL UPDATE PEMBAYARAN DP                                  -->
    <!-- ============================================================== -->
    <div x-show="showPaymentModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/60 flex items-center justify-center p-4">
        <div @click.away="showPaymentModal = false" class="bg-white border border-zinc-200 rounded-2xl max-w-md w-full p-6 shadow-2xl relative">
            <div class="flex items-center justify-between pb-3 mb-4 border-b border-zinc-100">
                <div>
                    <h3 class="text-sm font-bold text-black uppercase tracking-wider">Update Pembayaran DP Seat</h3>
                    <p class="text-xs text-zinc-500">Catat nominal DP yang telah ditransfer oleh calon jamaah.</p>
                </div>
                <button type="button" @click="showPaymentModal = false" class="text-zinc-400 hover:text-black text-xl font-bold">&times;</button>
            </div>

            <form @submit.prevent="submitPaymentUpdate()" class="space-y-3.5 text-xs">
                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Nominal DP Masuk (Rp) *</label>
                    <input type="text" x-model="paymentForm.dp_amount" required
                           class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black font-mono font-bold text-sm"
                           placeholder="Contoh: 10000000">
                </div>

                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Status Pembayaran</label>
                    <select x-model="paymentForm.payment_status" class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black bg-white">
                        <option value="unpaid">Belum Bayar DP (Unpaid)</option>
                        <option value="partial_dp">DP Sebagian (Partial DP)</option>
                        <option value="paid_full">Lunas 100% (Paid Full)</option>
                    </select>
                </div>

                <div class="pt-3 flex items-center justify-end gap-2 border-t border-zinc-100">
                    <button type="button" @click="showPaymentModal = false"
                            class="px-4 py-2 border border-zinc-300 text-zinc-700 hover:bg-zinc-50 rounded-xl transition">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-black hover:bg-zinc-800 text-white rounded-xl font-semibold transition shadow-2xs">
                        Simpan Pembayaran
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- 8. MODAL PREVIEW FLYER BROSUR                                 -->
    <!-- ============================================================== -->
    <div x-show="showFlyerModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/80 flex items-center justify-center p-4">
        <div @click.away="showFlyerModal = false" class="bg-white border border-zinc-200 rounded-2xl max-w-2xl w-full p-4 shadow-2xl relative">
            <div class="flex items-center justify-between pb-3 mb-3 border-b border-zinc-100">
                <span class="text-xs font-bold text-black uppercase" x-text="currentPackage ? currentPackage.name : 'Brosur Paket'"></span>
                <button type="button" @click="showFlyerModal = false" class="text-zinc-400 hover:text-black text-xl font-bold">&times;</button>
            </div>
            <div class="flex justify-center bg-zinc-100 rounded-xl overflow-hidden max-h-[75vh]">
                <img :src="currentPackage ? currentPackage.flyer_image : ''" alt="Flyer Paket" class="object-contain max-h-[75vh] w-full">
            </div>
            <div class="pt-3 flex justify-end">
                <button type="button" @click="showFlyerModal = false"
                        class="px-4 py-2 bg-black text-white rounded-xl text-xs font-semibold">Tutup</button>
            </div>
        </div>
    </div>

</div>

<script>
function prospect360Page() {
    return {
        prospect: <?= json_encode($prospect) ?>,
        packagesList: <?= json_encode($packages) ?>,
        logsList: <?= json_encode($logs) ?>,
        
        // Modals state
        showEditModal: false,
        showLostModal: false,
        showPaymentModal: false,
        showFlyerModal: false,

        // Submitting flags
        isUpdatingStage: false,
        isUpdatingPax: false,
        isSubmittingFollowup: false,
        isSubmittingEdit: false,

        // Forms
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
            'Terkirim rincian paket via WA',
            'Sedang berdiskusi dengan suami/istri',
            'Minta waktu 2 hari untuk cek cuti',
            'Siap transfer DP seat hari ini',
            'Menanyakan jarak hotel ke pelataran masjid'
        ],

        pipelineStages: [
            { key: 'new', short: 'Baru' },
            { key: 'identifying', short: 'Identifikasi' },
            { key: 'offered', short: 'Penawaran' },
            { key: 'objection', short: 'Keberatan' },
            { key: 'followup', short: 'Follow-Up' },
            { key: 'closing', short: 'Closing' },
            { key: 'closed_won', short: 'Won (DP)' }
        ],

        statusOptions: [
            { val: 'new', label: 'Prospek Baru' },
            { val: 'identifying', label: 'Sedang Diidentifikasi' },
            { val: 'offered', label: 'Sudah Ditawarkan' },
            { val: 'closing', label: 'Proses Closing' },
            { val: 'objection', label: 'Ada Keberatan' },
            { val: 'followup', label: 'Perlu Follow-up' },
            { val: 'nurture', label: 'Nurture (Belum Sekarang)' },
            { val: 'closed_won', label: 'Closing (Won DP)' },
            { val: 'closed_lost', label: 'Batal (Closed Lost)' }
        ],

        get currentPackage() {
            if (!this.prospect.package_id) return null;
            return this.packagesList.find(p => p.id == this.prospect.package_id) || null;
        },

        // Helper calculations
        calcTotalPax() {
            const q = parseInt(this.prospect.pax_quad) || 0;
            const t = parseInt(this.prospect.pax_triple) || 0;
            const d = parseInt(this.prospect.pax_double) || 0;
            const i = parseInt(this.prospect.pax_infant) || 0;
            return (q + t + d + i) || 1;
        },

        calcAdultPax() {
            const q = parseInt(this.prospect.pax_quad) || 0;
            const t = parseInt(this.prospect.pax_triple) || 0;
            const d = parseInt(this.prospect.pax_double) || 0;
            return (q + t + d) || 1;
        },

        calcTargetDp() {
            const pkg = this.currentPackage;
            if (!pkg || !pkg.dp) return 0;
            const dpUnit = parseFloat(String(pkg.dp).replace(/[^\d]/g, '')) || 0;
            return this.calcAdultPax() * dpUnit;
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

        // Formatters
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

        getStatusLabel(st) {
            const found = this.statusOptions.find(s => s.val === st);
            return found ? found.label : st;
        },

        formatLeadSource(src) {
            switch(src) {
                case 'whatsapp': return 'WhatsApp Inbound';
                case 'meta_ads': return 'Meta Ads (FB/IG)';
                case 'website_form': return 'Website Form';
                case 'referral': return 'Referral Alumni';
                case 'walk_in': return 'Walk-In Kantor';
                case 'repeat_order': return 'Repeat Order';
                default: return src || 'WhatsApp';
            }
        },

        getLeadSourceClass(src) {
            switch(src) {
                case 'meta_ads': return 'bg-blue-50 text-blue-800 border-blue-200';
                case 'whatsapp': return 'bg-emerald-50 text-emerald-800 border-emerald-200';
                case 'website_form': return 'bg-purple-50 text-purple-800 border-purple-200';
                case 'referral': return 'bg-amber-50 text-amber-800 border-amber-200';
                default: return 'bg-zinc-100 text-zinc-700 border-zinc-200';
            }
        },

        formatPassportStatus(st) {
            switch(st) {
                case 'sudah_ada': return 'Sudah Ada & Berlaku';
                case 'proses_buat': return 'Sedang Proses Buat';
                case 'perlu_perpanjang': return 'Perlu Perpanjang';
                case 'belum_ada': return 'Belum Ada';
                default: return st || 'Belum Ada';
            }
        },

        formatDecisionMaker(dm) {
            switch(dm) {
                case 'diri_sendiri': return 'Diri Sendiri';
                case 'pasangan': return 'Pasangan (Suami/Istri)';
                case 'anak': return 'Anak';
                case 'keluarga_besar': return 'Keluarga Besar';
                case 'kantor': return 'Instansi / Kantor';
                default: return dm || '- Belum Dicatat -';
            }
        },

        formatLostReason(r) {
            switch(r) {
                case 'harga_kemahalan': return 'Harga di luar budget / kemahalan';
                case 'jadwal_bentrok': return 'Jadwal bentrok kerja / cuti';
                case 'pilih_travel_lain': return 'Memilih travel umroh lain';
                case 'kendala_paspor': return 'Kendala paspor';
                case 'masalah_kesehatan': return 'Kondisi kesehatan fisik';
                case 'keluarga_tidak_setuju': return 'Keluarga belum sepakat';
                case 'no_response': return 'Ghosting / Tidak ada respon';
                case 'lainnya': return 'Alasan lainnya';
                default: return r || 'Batal';
            }
        },

        getStatusBadgeClass(status) {
            switch(status) {
                case 'new': return 'bg-zinc-100 text-zinc-800 border-zinc-300';
                case 'identifying': return 'bg-blue-50 text-blue-800 border-blue-200';
                case 'offered': return 'bg-indigo-50 text-indigo-800 border-indigo-200';
                case 'objection': return 'bg-amber-50 text-amber-900 border-amber-300';
                case 'followup': return 'bg-sky-50 text-sky-800 border-sky-200';
                case 'closing': return 'bg-purple-50 text-purple-900 border-purple-300 font-bold';
                case 'closed_won': return 'bg-emerald-600 text-white border-emerald-700 font-bold';
                case 'closed_lost': return 'bg-zinc-200 text-zinc-600 border-zinc-300 line-through';
                default: return 'bg-zinc-100 text-zinc-700 border-zinc-200';
            }
        },

        getPaymentLabel(st) {
            switch(st) {
                case 'paid_full': return 'Lunas 100%';
                case 'partial_dp': return 'DP Sebagian';
                default: return 'Belum DP';
            }
        },

        getPaymentBadgeClass(st) {
            switch(st) {
                case 'paid_full': return 'bg-emerald-50 text-emerald-800 border-emerald-300';
                case 'partial_dp': return 'bg-amber-50 text-amber-900 border-amber-300';
                default: return 'bg-zinc-100 text-zinc-600 border-zinc-300';
            }
        },

        // Stepper Visual Helpers
        isStageCompleted(stageKey) {
            const order = ['new', 'identifying', 'offered', 'objection', 'followup', 'closing', 'closed_won'];
            const currentIdx = order.indexOf(this.prospect.status);
            const targetIdx = order.indexOf(stageKey);
            return currentIdx >= 0 && targetIdx >= 0 && currentIdx > targetIdx;
        },

        getChevronClass(stageKey, idx) {
            if (this.prospect.status === stageKey) {
                if (stageKey === 'closed_won') {
                    return 'bg-emerald-600 text-white border-emerald-700 font-bold shadow-xs';
                }
                return 'bg-black text-white border-black font-bold shadow-xs';
            }
            if (this.isStageCompleted(stageKey)) {
                return 'bg-zinc-100 text-zinc-900 border-zinc-300 font-semibold hover:bg-zinc-200';
            }
            return 'bg-white text-zinc-400 border-zinc-200 hover:border-zinc-400 hover:text-black';
        },

        // Stage Transitions
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
                    window.showToast('Tahapan prospek berhasil dipindahkan!');
                    await this.reloadLogs();
                } else {
                    alert('Gagal mengubah status: ' + (data.error || 'Terjadi kesalahan'));
                }
            } catch(e) {
                console.error(e);
                alert('Terjadi kesalahan koneksi.');
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
                    alert(data.error || 'Gagal mengubah status');
                }
            } catch(e) {
                alert('Terjadi kesalahan koneksi.');
            }
        },

        // Room Pax Steppers & Calculation
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
                    window.showToast('Kamar dan nilai transaksi diperbarui!');
                    await this.reloadLogs();
                } else {
                    alert(data.error || 'Gagal memperbarui rincian kamar');
                }
            } catch(e) {
                console.error(e);
            } finally {
                this.isUpdatingPax = false;
            }
        },

        // Package Switcher
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
                    window.showToast('Paket umroh berhasil diperbarui!');
                    await this.reloadLogs();
                } else {
                    alert(data.error || 'Gagal mengganti paket');
                }
            } catch(e) {
                alert('Terjadi kesalahan koneksi.');
            }
        },

        // Follow-up Submission
        appendQuickChip(chip) {
            if (this.followupForm.note) {
                this.followupForm.note += '. ' + chip;
            } else {
                this.followupForm.note = chip;
            }
        },

        async submitFollowup() {
            if (!this.followupForm.note.trim()) {
                alert('Catatan follow-up tidak boleh kosong.');
                return;
            }

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
                    window.showToast(data.message || 'Follow-up berhasil dicatat!');
                    this.prospect.status = this.followupForm.status;
                    this.prospect.next_followup_date = this.followupForm.next_followup_date;
                    this.prospect.last_followup_at = new Date().toISOString();
                    this.followupForm.note = '';
                    await this.reloadLogs();
                } else {
                    alert(data.error || 'Gagal mencatat follow-up');
                }
            } catch (err) {
                console.error(err);
                alert('Terjadi kesalahan koneksi.');
            } finally {
                this.isSubmittingFollowup = false;
            }
        },

        // Payment DP Update
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
                    window.showToast('Data pembayaran DP berhasil diperbarui!');
                    await this.reloadLogs();
                } else {
                    alert(data.error || 'Gagal memperbarui pembayaran');
                }
            } catch(e) {
                alert('Terjadi kesalahan koneksi.');
            }
        },

        // Full Edit Modal
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
                    window.showToast('Profil CRM berhasil diperbarui!');
                    await this.reloadLogs();
                } else {
                    alert(data.error || 'Gagal memperbarui prospek');
                }
            } catch(e) {
                console.error(e);
                alert('Terjadi kesalahan koneksi.');
            } finally {
                this.isSubmittingEdit = false;
            }
        },

        async deleteProspect() {
            if (!confirm('Hapus prospek ini secara permanen dari database?')) return;
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

        // Quick Scripts Curated per Stage
        getCuratedScripts(tab) {
            const nama = this.prospect.name || 'Kakak';
            const travel = '<?= addslashes($prospect['brand_name']) ?>';
            const paket = this.currentPackage ? this.currentPackage.name : 'Paket Umroh Reguler';
            const dp = this.currentPackage ? ('Rp ' + this.currentPackage.dp) : 'Rp 5.000.000';
            const cs = '<?= addslashes($currentUser['name'] ?? 'CS') ?>';

            if (tab === 'closing') {
                return [
                    {
                        title: 'Kunci Kuota Seat (Closing DP)',
                        text: `Bismillah Kak ${nama} 😊\n\nUntuk mengamankan kuota seat pada ${paket} bersama ${travel}, Kakak cukup melakukan booking seat dengan DP awal ${dp} per jamaah.\n\nApakah invoice dan nomor rekening resmi ${travel} bisa kami buatkan atas nama Kak ${nama} sekarang, Kak?`
                    },
                    {
                        title: 'Konfirmasi Rekening & Dokumen',
                        text: `Alhamdulillah, terima kasih banyak Kak ${nama} 🙏\n\nBerikut rekening resmi pendaftaran umroh ${travel}:\nBank: <?= addslashes($prospect['bank_name'] ?: 'BSI') ?> No. <?= addslashes($prospect['bank_account_number'] ?: '7123456789') ?>\nAtas Nama: <?= addslashes($prospect['bank_account_holder'] ?: $prospect['brand_name']) ?>\n\nMohon konfirmasi bukti transfer jika sudah melakukan pembayaran ya Kak, agar seat langsung kami kunci di sistem manifes.`
                    }
                ];
            } else if (tab === 'objection') {
                return [
                    {
                        title: 'TGJP Harga / Budget',
                        text: `Memang betul Kak ${nama}, jika dilihat sekilas selisih harga terlihat signifikan. Namun paket ini sudah all-in termasuk tiket direct tanpa transit, hotel bintang 4 dekat masjid, dan bimbingan ibadah intensif sehingga jamaah lansia pun sangat nyaman.\n\nApakah Kakak ingin kami hitungkan skema cicilan atau opsi tanggal keberangkatan yang lebih hemat, Kak?`
                    },
                    {
                        title: 'TGJP Diskusi Keluarga',
                        text: `Sangat baik sekali Kak ${nama}, ibadah ke baitullah memang paling berkah jika dimusyawarahkan bersama keluarga 😊\n\nBiar pembahasannya lebih mudah, apakah Kakak berkenan saya kirimkan rangkuman brosur PDF dan video kamar hotelnya untuk ditunjukkan ke keluarga?`
                    }
                ];
            } else {
                return [
                    {
                        title: 'Follow-up Sentimen Hangat',
                        text: `Assalamu'alaikum Kak ${nama} 😊\n\nSemoga hari ini penuh berkah ya, Kak. Menindaklanjuti rencana umroh Kakak kemarin, apakah sudah ada kesempatan berdiskusi mengenai jadwal keberangkatan ${paket}?\n\nJika ada pertanyaan mengenai fasilitas atau dokumen yang masih perlu dipastikan, saya siap bantu jelaskan Kak.`
                    },
                    {
                        title: 'Urgency Sisa Kuota Promo',
                        text: `Assalamu'alaikum Kak ${nama} 😊\n\nIzin sekadar mengingatkan, seat untuk ${paket} saat ini tersisa terbatas. Mengingat promo potongan harga akan segera ditutup, apakah Kakak ingin seat-nya diamankan terlebih dahulu hari ini?`
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
            window.copyToClipboard(text, 'Rincian paket berhasil disalin ke clipboard!');
        }
    };
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
