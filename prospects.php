<?php
$pageTitle = 'CRM Prospek & Pipeline Umroh';
require_once __DIR__ . '/includes/header.php';

$db = get_db();
$currentUser = get_logged_user();

// For Super Admin, allow brand switcher; For CS, lock to assigned brand
$activeBrandId = $currentUser['brand_id'];
if ($currentUser['role'] === 'superadmin') {
    $activeBrandId = isset($_GET['brand_id']) ? (int)$_GET['brand_id'] : ($activeBrandId ?: 1);
}

// Fetch active brand details
$stmt = $db->prepare("SELECT * FROM brands WHERE id = ? LIMIT 1");
$stmt->execute([$activeBrandId]);
$brand = $stmt->fetch();
if (!$brand) {
    $brand = $db->query("SELECT * FROM brands ORDER BY id ASC LIMIT 1")->fetch();
}

// Fetch active packages for this brand
$stmt = $db->prepare("
    SELECT id, brand_id, name, price, dp, price_quad, price_triple, price_double, price_infant,
           quota_remaining, departure_date, duration, airline, hotel_makkah, hotel_madinah, flyer_image
    FROM packages 
    WHERE brand_id = ? AND is_active = 1 
    ORDER BY departure_date ASC, id DESC
");
$stmt->execute([$brand['id']]);
$packages = $stmt->fetchAll();

// All brands for Super Admin switcher
$allBrands = [];
if ($currentUser['role'] === 'superadmin') {
    $allBrands = $db->query("SELECT id, name FROM brands ORDER BY id ASC")->fetchAll();
}

// Load initial prospects list with joins
$sql = "
    SELECT p.*, b.name AS brand_name,
           pkg.name AS package_name, pkg.price AS package_price, pkg.dp AS package_dp,
           pkg.price_quad, pkg.price_triple, pkg.price_double, pkg.price_infant,
           pkg.quota_remaining, pkg.departure_date, pkg.airline AS package_airline,
           pkg.hotel_makkah AS package_hotel_makkah, pkg.hotel_madinah AS package_hotel_madinah,
           pkg.flyer_image,
           u.name AS cs_name
    FROM prospects p
    JOIN brands b ON p.brand_id = b.id
    LEFT JOIN packages pkg ON p.package_id = pkg.id
    LEFT JOIN users u ON p.user_id = u.id
";
$params = [];
if ($currentUser['role'] !== 'superadmin') {
    $sql .= " WHERE p.brand_id = ? AND p.user_id = ?";
    $params = [$brand['id'], $currentUser['id']];
} else {
    $sql .= " WHERE p.brand_id = ?";
    $params = [$brand['id']];
}
$sql .= " ORDER BY p.updated_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$initialProspects = $stmt->fetchAll();
?>

<div class="max-w-7xl mx-auto space-y-6 pb-12" x-data="prospectsPage()">

    <!-- Top CRM Header & Control Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-2 border-b border-zinc-200">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-black text-white">CRM Enterprise</span>
                <span class="text-xs text-zinc-400 font-mono">Brand: <?= htmlspecialchars($brand['name']) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-black tracking-tight mt-1">
                Data Prospek & Pipeline Umroh
            </h1>
            <p class="text-xs text-zinc-500 mt-0.5">
                Kelola siklus prospek jamaah, kualifikasi NPGD, pemantauan kuota seat, dan konversi deal.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Super Admin Custom Dropdown Brand Switcher -->
            <?php if ($currentUser['role'] === 'superadmin'): ?>
                <div class="flex items-center gap-1.5 text-xs bg-white border border-zinc-200 p-1.5 rounded-xl shadow-2xs">
                    <span class="text-zinc-400 font-medium pl-2">Brand:</span>
                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                        <button type="button" @click="open = !open"
                                class="px-2.5 py-1 rounded-lg text-xs bg-zinc-50 border border-zinc-200 font-semibold text-black focus:outline-none flex items-center gap-1.5 hover:bg-zinc-100 transition">
                            <span><?= htmlspecialchars($brand['name']) ?></span>
                            <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="m6 9 6 6 6-6"/>
                            </svg>
                        </button>
                        <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                             class="absolute right-0 z-50 mt-1.5 w-56 bg-white border border-zinc-200 rounded-xl shadow-xl py-1 text-xs divide-y divide-zinc-50">
                            <?php foreach ($allBrands as $b): ?>
                                <a href="prospects.php?brand_id=<?= $b['id'] ?>"
                                   class="px-3 py-2 flex items-center justify-between hover:bg-zinc-50 transition <?= (int)$brand['id'] === (int)$b['id'] ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700' ?>">
                                    <span><?= htmlspecialchars($b['name']) ?></span>
                                    <?php if ((int)$brand['id'] === (int)$b['id']): ?>
                                        <svg class="w-3.5 h-3.5 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- View Mode Switcher: Kanban vs Table -->
            <div class="flex items-center bg-zinc-100 p-1 rounded-xl border border-zinc-200 text-xs">
                <button type="button" @click="viewMode = 'kanban'"
                        class="px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1.5"
                        :class="viewMode === 'kanban' ? 'bg-white text-black shadow-2xs' : 'text-zinc-600 hover:text-black'">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="18" rx="1"/><rect x="14" y="3" width="7" height="11" rx="1"/></svg>
                    <span>Kanban Pipeline</span>
                </button>
                <button type="button" @click="viewMode = 'table'"
                        class="px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1.5"
                        :class="viewMode === 'table' ? 'bg-white text-black shadow-2xs' : 'text-zinc-600 hover:text-black'">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                    <span>Tabel Detail</span>
                </button>
            </div>

            <!-- Export CSV -->
            <button type="button" @click="exportCSV()" title="Export Data Prospek ke CSV"
                    class="p-2 bg-white hover:bg-zinc-50 border border-zinc-200 text-zinc-700 rounded-xl transition shadow-2xs">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            </button>

            <!-- New Lead Button -->
            <button type="button" @click="openCreateModal()"
                    class="px-4 py-2 bg-black hover:bg-zinc-800 text-white text-xs font-semibold rounded-xl transition shadow-sm flex items-center gap-2">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Catat Prospek Baru</span>
            </button>
        </div>
    </div>

    <!-- CRM Metric KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Metric 1: Total Active Leads -->
        <div class="p-4 bg-white border border-zinc-200 rounded-2xl shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-zinc-500">Prospek Aktif Pipeline</span>
                <span class="p-2 rounded-xl bg-zinc-100 text-zinc-800">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
            </div>
            <div class="mt-3">
                <div class="text-2xl font-black text-black tracking-tight" x-text="metrics.activeCount + ' Jamaah'"></div>
                <div class="text-[11px] text-zinc-400 mt-0.5">Dari total <span x-text="prospects.length"></span> database prospek</div>
            </div>
        </div>

        <!-- Metric 2: Total Pipeline Deal Value -->
        <div class="p-4 bg-zinc-950 text-white border border-zinc-800 rounded-2xl shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-zinc-400">Estimasi Nilai Pipeline</span>
                <span class="p-2 rounded-xl bg-zinc-800 text-white">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </span>
            </div>
            <div class="mt-3">
                <div class="text-xl sm:text-2xl font-black text-white font-mono tracking-tight" x-text="formatRupiah(metrics.activePipelineValue)"></div>
                <div class="text-[11px] text-zinc-400 mt-0.5">Potensi transaksi berjalan</div>
            </div>
        </div>

        <!-- Metric 3: Won Revenue & Deals -->
        <div class="p-4 bg-white border border-zinc-200 rounded-2xl shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-zinc-500">Closing Sukses (Won)</span>
                <span class="p-2 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
            </div>
            <div class="mt-3">
                <div class="text-2xl font-black text-emerald-700 font-mono tracking-tight" x-text="formatRupiah(metrics.wonValue)"></div>
                <div class="text-[11px] text-zinc-500 mt-0.5"><span class="font-bold text-zinc-800" x-text="metrics.wonCount"></span> jamaah resmi closing DP</div>
            </div>
        </div>

        <!-- Metric 4: Follow-up Due Today / Overdue -->
        <div class="p-4 bg-white border rounded-2xl shadow-2xs flex flex-col justify-between"
             :class="metrics.dueCount > 0 ? 'border-amber-300 bg-amber-50/20' : 'border-zinc-200'">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold" :class="metrics.dueCount > 0 ? 'text-amber-800' : 'text-zinc-500'">Follow-up Hari Ini / Lewat</span>
                <span class="p-2 rounded-xl" :class="metrics.dueCount > 0 ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-zinc-100 text-zinc-600'">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </span>
            </div>
            <div class="mt-3">
                <div class="text-2xl font-black tracking-tight"
                     :class="metrics.dueCount > 0 ? 'text-amber-800' : 'text-black'"
                     x-text="metrics.dueCount + ' Jamaah'"></div>
                <button type="button" @click="activeFilterPill = 'due_today'"
                        class="text-[11px] font-semibold underline mt-0.5 transition hover:opacity-80"
                        :class="metrics.dueCount > 0 ? 'text-amber-800' : 'text-zinc-400'">
                    Lihat daftar yang perlu disapa &rarr;
                </button>
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white border border-zinc-200 rounded-2xl p-4 shadow-2xs space-y-3">
        <!-- Top Toolbar: Search & Pills -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            <!-- Search Box -->
            <div class="relative flex-1 max-w-md">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-zinc-400">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </span>
                <input type="text" x-model="searchQuery"
                       placeholder="Cari nama jamaah, WhatsApp, kota, catatan..."
                       class="w-full pl-9 pr-3 py-2 bg-zinc-50 border border-zinc-200 rounded-xl text-xs focus:bg-white focus:border-black focus:ring-1 focus:ring-black outline-none transition">
                <button type="button" x-show="searchQuery" @click="searchQuery = ''"
                        class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-zinc-400 hover:text-black">
                    &times;
                </button>
            </div>

            <!-- Quick Filter Pills -->
            <div class="flex flex-wrap items-center gap-1.5 text-xs">
                <button type="button" @click="activeFilterPill = 'all'"
                        class="px-3 py-1.5 rounded-xl font-semibold transition cursor-pointer border"
                        :class="activeFilterPill === 'all' ? 'bg-black text-white border-black shadow-2xs' : 'bg-white text-zinc-700 border-zinc-200 hover:bg-zinc-50'">
                    Semua (<span x-text="prospects.length"></span>)
                </button>
                <button type="button" @click="activeFilterPill = 'high_intent'"
                        class="px-3 py-1.5 rounded-xl font-semibold transition cursor-pointer border flex items-center gap-1"
                        :class="activeFilterPill === 'high_intent' ? 'bg-black text-white border-black shadow-2xs' : 'bg-white text-zinc-700 border-zinc-200 hover:bg-zinc-50'">
                    <span>🔥 High Intent</span>
                    <span class="text-[10px] opacity-80" x-text="'(' + countHighIntent + ')'"></span>
                </button>
                <button type="button" @click="activeFilterPill = 'due_today'"
                        class="px-3 py-1.5 rounded-xl font-semibold transition cursor-pointer border flex items-center gap-1"
                        :class="activeFilterPill === 'due_today' ? 'bg-amber-600 text-white border-amber-600 shadow-2xs' : 'bg-white text-zinc-700 border-zinc-200 hover:bg-zinc-50'">
                    <span>⏰ Follow-up Hari Ini</span>
                    <span class="text-[10px] opacity-80" x-text="'(' + metrics.dueCount + ')'"></span>
                </button>
                <button type="button" @click="activeFilterPill = 'won'"
                        class="px-3 py-1.5 rounded-xl font-semibold transition cursor-pointer border flex items-center gap-1"
                        :class="activeFilterPill === 'won' ? 'bg-emerald-700 text-white border-emerald-700 shadow-2xs' : 'bg-white text-zinc-700 border-zinc-200 hover:bg-zinc-50'">
                    <span>🏆 Closed Won</span>
                    <span class="text-[10px] opacity-80" x-text="'(' + metrics.wonCount + ')'"></span>
                </button>
                <button type="button" @click="activeFilterPill = 'new'"
                        class="px-3 py-1.5 rounded-xl font-semibold transition cursor-pointer border"
                        :class="activeFilterPill === 'new' ? 'bg-zinc-800 text-white border-zinc-800 shadow-2xs' : 'bg-white text-zinc-700 border-zinc-200 hover:bg-zinc-50'">
                    Baru (<span x-text="countNew"></span>)
                </button>
            </div>
        </div>

        <!-- Secondary Filters Row -->
        <div class="pt-2.5 border-t border-zinc-100 flex flex-wrap items-center gap-2.5 text-xs text-zinc-600">
            <!-- Filter Paket -->
            <div class="flex items-center gap-1.5">
                <span class="text-zinc-400 font-medium">Paket:</span>
                <select x-model="filterPackage"
                        class="px-2.5 py-1.5 bg-zinc-50 border border-zinc-200 rounded-lg text-xs font-medium text-black focus:border-black outline-none">
                    <option value="">Semua Paket (Termasuk Belum Memilih)</option>
                    <option value="none">-- Belum Menentukan Paket --</option>
                    <?php foreach ($packages as $pkg): ?>
                        <option value="<?= $pkg['id'] ?>"><?= htmlspecialchars($pkg['name']) ?> (<?= htmlspecialchars($pkg['price']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Sumber Lead -->
            <div class="flex items-center gap-1.5">
                <span class="text-zinc-400 font-medium">Sumber Lead:</span>
                <select x-model="filterSource"
                        class="px-2.5 py-1.5 bg-zinc-50 border border-zinc-200 rounded-lg text-xs font-medium text-black focus:border-black outline-none">
                    <option value="">Semua Sumber</option>
                    <option value="whatsapp">WhatsApp Langsung</option>
                    <option value="meta_ads">Meta Ads (CTWA)</option>
                    <option value="website_form">Website Form</option>
                    <option value="referral">Rekomendasi Jamaah</option>
                    <option value="walk_in">Walk-in Kantor</option>
                    <option value="repeat_order">Alumni / Repeat Order</option>
                </select>
            </div>

            <!-- Reset Filters -->
            <button type="button" x-show="searchQuery || activeFilterPill !== 'all' || filterPackage || filterSource"
                    @click="resetFilters()"
                    class="text-[11px] text-zinc-500 hover:text-black underline ml-auto">
                Reset Filter
            </button>
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- VIEW 1: KANBAN BOARD PIPELINE (8 COLUMNS)                         -->
    <!-- ================================================================= -->
    <div x-show="viewMode === 'kanban'" class="space-y-4">
        <div class="flex items-center justify-between text-xs text-zinc-500 px-1">
            <span>Geser horizontal untuk melihat seluruh kolom pipa konversi &rarr;</span>
            <span class="font-mono" x-text="filteredProspects.length + ' prospek tampil'"></span>
        </div>

        <!-- Horizontal Scrollable Kanban Container -->
        <div class="flex gap-4 overflow-x-auto pb-6 pt-1 snap-x scrollbar-thin">
            <template x-for="col in pipelineStages" :key="col.status">
                <div class="w-80 shrink-0 flex flex-col bg-zinc-50 border border-zinc-200 rounded-2xl overflow-hidden shadow-2xs">
                    <!-- Column Header -->
                    <div class="p-3.5 bg-white border-b border-zinc-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full" :class="col.dotClass"></span>
                            <h3 class="font-bold text-xs text-black" x-text="col.title"></h3>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-zinc-100 text-zinc-700"
                                  x-text="getStageProspects(col.status).length"></span>
                        </div>
                        <div class="text-[10px] font-mono text-zinc-500 font-medium"
                             x-text="formatRupiah(getStageValue(col.status))"></div>
                    </div>

                    <!-- Column Cards Container -->
                    <div class="p-3 flex-1 space-y-3 overflow-y-auto max-h-[calc(100vh-320px)] min-h-[160px]">
                        <!-- Empty Stage Placeholder -->
                        <template x-if="getStageProspects(col.status).length === 0">
                            <div class="h-28 border-2 border-dashed border-zinc-200 rounded-xl flex items-center justify-center text-center p-3 text-[11px] text-zinc-400">
                                Tidak ada prospek di tahap ini
                            </div>
                        </template>

                        <!-- Prospect Kanban Card -->
                        <template x-for="item in getStageProspects(col.status)" :key="item.id">
                            <div class="bg-white border border-zinc-200 hover:border-black rounded-xl p-3.5 space-y-3 transition shadow-2xs">
                                <!-- Card Header: Lead Source & Follow-up Urgency -->
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase font-mono tracking-wider border"
                                              :class="getLeadSourceBadge(item.lead_source)">
                                            <span x-text="formatLeadSource(item.lead_source)"></span>
                                        </span>
                                        <template x-if="item.city">
                                            <span class="text-[10px] text-zinc-500 font-medium" x-text="'📍 ' + item.city"></span>
                                        </template>
                                    </div>
                                    <!-- Follow-up Due Badge -->
                                    <template x-if="item.next_followup_date">
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold border"
                                              :class="getFollowupBadgeClass(item.next_followup_date)">
                                            <span x-text="formatFollowupShort(item.next_followup_date)"></span>
                                        </span>
                                    </template>
                                </div>

                                <!-- Prospect Name & WhatsApp -->
                                <div>
                                    <a :href="'prospect_detail.php?id=' + item.id"
                                       class="font-bold text-xs text-black hover:underline block leading-tight"
                                       x-text="item.name"></a>
                                    <div class="flex items-center justify-between text-[11px] text-zinc-500 mt-1 font-mono">
                                        <span x-text="item.phone || '-'"></span>
                                        <span class="text-[10px] text-zinc-400" x-text="formatTimeAgo(item.updated_at)"></span>
                                    </div>
                                </div>

                                <!-- Package & Room Pax Breakdown -->
                                <div class="p-2 bg-zinc-50 border border-zinc-150 rounded-lg text-[11px] space-y-1">
                                    <div class="font-semibold text-zinc-900 truncate"
                                         :class="!item.package_id ? 'italic text-zinc-400' : ''"
                                         x-text="item.package_name || 'Belum Menentukan Paket'"></div>
                                    
                                    <div class="flex items-center justify-between text-[10px] text-zinc-500">
                                        <span x-text="formatPaxRooms(item)"></span>
                                        <template x-if="item.package_id && item.quota_remaining !== null">
                                            <span :class="item.quota_remaining <= 5 ? 'text-amber-700 font-bold' : 'text-zinc-500'"
                                                  x-text="'Sisa: ' + item.quota_remaining + ' seat'"></span>
                                        </template>
                                    </div>
                                </div>

                                <!-- Financial Value & Deal Amount -->
                                <div class="flex items-center justify-between pt-1 border-t border-zinc-100">
                                    <div>
                                        <div class="text-[9px] text-zinc-400 uppercase font-semibold">Estimasi Deal</div>
                                        <div class="font-black text-xs font-mono text-zinc-900"
                                             x-text="formatRupiah(item.deal_value || calcCardDealValue(item))"></div>
                                    </div>
                                    <div class="text-right">
                                        <template x-if="item.dp_amount > 0">
                                            <div>
                                                <div class="text-[9px] text-emerald-700 uppercase font-bold">DP Masuk</div>
                                                <div class="font-black text-[11px] font-mono text-emerald-700" x-text="formatRupiah(item.dp_amount)"></div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <!-- Quick Actions & Stage Mover Dropdown -->
                                <div class="pt-2 border-t border-zinc-100 flex items-center justify-between gap-1.5">
                                    <div class="flex items-center gap-1">
                                        <!-- WhatsApp Chat Link -->
                                        <template x-if="item.phone">
                                            <a :href="'chat.php?phone=' + item.phone"
                                               title="Buka Chat WhatsApp"
                                               class="p-1.5 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 transition">
                                                <svg class="w-3.5 h-3.5 text-emerald-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 21 1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"/></svg>
                                            </a>
                                        </template>
                                        <!-- Open 360 Detail Profile -->
                                        <a :href="'prospect_detail.php?id=' + item.id"
                                           title="Profil Lengkap 360°"
                                           class="p-1.5 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 transition">
                                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                                        </a>
                                        <!-- Quick Log Followup Modal -->
                                        <button type="button" @click="openQuickFollowupModal(item)"
                                                title="Catat Follow-up / Aktivitas"
                                                class="p-1.5 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 transition">
                                            <svg class="w-3.5 h-3.5 text-zinc-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>
                                    </div>

                                    <!-- Move Stage Quick Dropdown -->
                                    <div class="relative" x-data="{ openMove: false }" @click.outside="openMove = false">
                                        <button type="button" @click="openMove = !openMove"
                                                class="px-2 py-1 rounded-lg text-[10px] font-semibold bg-zinc-100 hover:bg-zinc-200 text-zinc-800 transition flex items-center gap-1">
                                            <span>Geser</span>
                                            <svg class="w-2.5 h-2.5 text-zinc-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                                        </button>
                                        <div x-show="openMove" x-cloak x-transition.opacity.duration.100ms
                                             class="absolute right-0 bottom-full mb-1 w-44 bg-white border border-zinc-200 rounded-xl shadow-xl py-1 z-50 text-[11px] divide-y divide-zinc-50">
                                            <div class="px-2.5 py-1 text-[9px] font-bold uppercase text-zinc-400">Pindahkan Tahap:</div>
                                            <template x-for="st in pipelineStages" :key="st.status">
                                                <button type="button"
                                                        x-show="st.status !== item.status"
                                                        @click="quickChangeStatus(item, st.status); openMove = false"
                                                        class="w-full text-left px-2.5 py-1.5 hover:bg-zinc-50 flex items-center gap-2">
                                                    <span class="w-2 h-2 rounded-full" :class="st.dotClass"></span>
                                                    <span x-text="st.title"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- VIEW 2: TABLE LIST VIEW (RICH & SORTABLE)                         -->
    <!-- ================================================================= -->
    <div x-show="viewMode === 'table'" class="bg-white border border-zinc-200 rounded-2xl overflow-hidden shadow-2xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-zinc-700 divide-y divide-zinc-200">
                <thead class="bg-zinc-50 text-[10px] font-bold text-zinc-500 uppercase tracking-wider">
                    <tr>
                        <th class="py-3.5 px-4">Calon Jamaah & Domisili</th>
                        <th class="py-3.5 px-3">Paket Umroh & Jadwal</th>
                        <th class="py-3.5 px-3">Kamar & Pax</th>
                        <th class="py-3.5 px-3">Estimasi Deal Value</th>
                        <th class="py-3.5 px-3">Tahapan Pipa</th>
                        <th class="py-3.5 px-3">Follow-up Berikutnya</th>
                        <th class="py-3.5 px-3">CS PIC</th>
                        <th class="py-3.5 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <template x-if="filteredProspects.length === 0">
                        <tr>
                            <td colspan="8" class="py-12 text-center text-zinc-400">
                                Tidak ada data prospek yang sesuai dengan filter pencarian.
                            </td>
                        </tr>
                    </template>

                    <template x-for="p in filteredProspects" :key="p.id">
                        <tr class="hover:bg-zinc-50/70 transition">
                            <!-- Jamaah & Domisili -->
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-3">
                                    <!-- WA Avatar / Initials -->
                                    <div class="w-9 h-9 rounded-full bg-zinc-900 text-white font-bold flex items-center justify-center text-xs shrink-0 overflow-hidden border border-zinc-200">
                                        <template x-if="p.photo_url">
                                            <img :src="p.photo_url" class="w-full h-full object-cover">
                                        </template>
                                        <template x-if="!p.photo_url">
                                            <span x-text="getInitials(p.name)"></span>
                                        </template>
                                    </div>
                                    <div class="min-w-0">
                                        <a :href="'prospect_detail.php?id=' + p.id"
                                           class="font-bold text-xs text-black hover:underline block truncate"
                                           x-text="p.name"></a>
                                        <div class="flex items-center gap-1.5 text-[11px] text-zinc-500 font-mono mt-0.5">
                                            <span x-text="p.phone || '-'"></span>
                                            <template x-if="p.city">
                                                <span class="text-zinc-400" x-text="'• ' + p.city"></span>
                                            </template>
                                        </div>
                                        <div class="mt-1">
                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase font-mono border"
                                                  :class="getLeadSourceBadge(p.lead_source)"
                                                  x-text="formatLeadSource(p.lead_source)"></span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Paket & Jadwal -->
                            <td class="py-3.5 px-3">
                                <div class="font-medium text-black truncate max-w-[200px]"
                                     :class="!p.package_id ? 'italic text-zinc-400' : ''"
                                     x-text="p.package_name || 'Belum Menentukan Paket'"></div>
                                <div class="text-[11px] text-zinc-500 mt-0.5">
                                    <span x-text="p.target_month || (p.departure_date ? formatDateIndo(p.departure_date) : 'Jadwal Fleksibel')"></span>
                                </div>
                                <template x-if="p.package_id && p.quota_remaining !== null">
                                    <div class="text-[10px] mt-0.5"
                                         :class="p.quota_remaining <= 5 ? 'text-amber-700 font-bold' : 'text-zinc-400'"
                                         x-text="'Sisa ' + p.quota_remaining + ' seat'"></div>
                                </template>
                            </td>

                            <!-- Kamar & Pax -->
                            <td class="py-3.5 px-3">
                                <div class="text-[11px] font-medium text-zinc-900" x-text="formatPaxRooms(p)"></div>
                                <template x-if="p.room_preference">
                                    <div class="text-[10px] text-zinc-400 capitalize" x-text="'Tipe: ' + p.room_preference"></div>
                                </template>
                            </td>

                            <!-- Deal Value -->
                            <td class="py-3.5 px-3">
                                <div class="font-black text-xs font-mono text-black"
                                     x-text="formatRupiah(p.deal_value || calcCardDealValue(p))"></div>
                                <template x-if="p.dp_amount > 0">
                                    <div class="text-[10px] text-emerald-700 font-semibold font-mono mt-0.5"
                                         x-text="'DP: ' + formatRupiah(p.dp_amount)"></div>
                                </template>
                            </td>

                            <!-- Status Pipa (Quick Dropdown) -->
                            <td class="py-3.5 px-3">
                                <select :value="p.status"
                                        @change="quickChangeStatus(p, $event.target.value)"
                                        class="px-2 py-1 rounded-lg text-xs font-semibold border cursor-pointer focus:outline-none"
                                        :class="getStatusBadgeClass(p.status)">
                                    <option value="new">1. Baru</option>
                                    <option value="identifying">2. Identifikasi</option>
                                    <option value="offered">3. Ditawarkan</option>
                                    <option value="objection">4. Keberatan (TGJP)</option>
                                    <option value="followup">5. Follow-up</option>
                                    <option value="closing">6. Closing</option>
                                    <option value="nurture">7. Nurture</option>
                                    <option value="closed_won">8. Closed Won</option>
                                    <option value="closed_lost">9. Closed Lost</option>
                                </select>
                            </td>

                            <!-- Follow-up Berikutnya -->
                            <td class="py-3.5 px-3">
                                <template x-if="p.next_followup_date">
                                    <div>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border inline-block"
                                              :class="getFollowupBadgeClass(p.next_followup_date)"
                                              x-text="formatDateIndo(p.next_followup_date)"></span>
                                        <div class="text-[10px] text-zinc-400 mt-0.5" x-text="formatFollowupRelative(p.next_followup_date)"></div>
                                    </div>
                                </template>
                                <template x-if="!p.next_followup_date">
                                    <button type="button" @click="openQuickFollowupModal(p)"
                                            class="text-[11px] text-zinc-400 hover:text-black italic underline">
                                        + Jadwalkan
                                    </button>
                                </template>
                            </td>

                            <!-- CS PIC -->
                            <td class="py-3.5 px-3 text-zinc-600 font-medium" x-text="p.cs_name || '-'"></td>

                            <!-- Actions -->
                            <td class="py-3.5 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <template x-if="p.phone">
                                        <a :href="'chat.php?phone=' + p.phone" title="Live Chat WhatsApp"
                                           class="p-1.5 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 transition">
                                            <svg class="w-3.5 h-3.5 text-emerald-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 21 1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"/></svg>
                                        </a>
                                    </template>
                                    <a :href="'prospect_detail.php?id=' + p.id" title="Detail Profil 360°"
                                       class="px-2.5 py-1.5 rounded-lg bg-black hover:bg-zinc-800 text-white font-semibold text-[11px] transition">
                                        Detail
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- MODAL 1: CATAT PROSPEK BARU (FULL CRM STANDAR UMROH)              -->
    <!-- ================================================================= -->
    <div x-show="openModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/60 flex items-center justify-center p-4">
        <div @click.away="openModal = false" class="bg-white border border-zinc-200 rounded-2xl max-w-2xl w-full p-6 shadow-2xl space-y-4 my-8">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-200">
                <div>
                    <h3 class="font-bold text-base text-black">Catat Prospek Baru (Standar CRM)</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">Input data jamaah, preferensi paket, kamar, dan kualifikasi awal.</p>
                </div>
                <button type="button" @click="openModal = false" class="text-zinc-400 hover:text-black font-bold text-xl">&times;</button>
            </div>

            <form @submit.prevent="saveProspect()" class="space-y-4 text-xs">
                <!-- Section 1: Data Kontak & Domisili -->
                <div class="bg-zinc-50 p-3.5 rounded-xl border border-zinc-200 space-y-3">
                    <div class="font-bold text-zinc-900 uppercase text-[10px] tracking-wider">1. Identitas & Kontak Dasar</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-zinc-700 mb-1">Nama Calon Jamaah *</label>
                            <input type="text" x-model="form.name" required placeholder="Contoh: Bpk. H. Rahmat Subagyo"
                                   class="w-full px-3 py-2 bg-white border border-zinc-300 rounded-lg focus:border-black outline-none text-xs">
                        </div>
                        <div>
                            <label class="block font-semibold text-zinc-700 mb-1">Nomor WhatsApp *</label>
                            <input type="text" x-model="form.phone" required placeholder="0812xxxx atau 62812xxxx"
                                   class="w-full px-3 py-2 bg-white border border-zinc-300 rounded-lg focus:border-black outline-none text-xs font-mono">
                        </div>
                        <div>
                            <label class="block font-semibold text-zinc-700 mb-1">Kota Domisili</label>
                            <input type="text" x-model="form.city" placeholder="Contoh: Jakarta Selatan, Surabaya"
                                   class="w-full px-3 py-2 bg-white border border-zinc-300 rounded-lg focus:border-black outline-none text-xs">
                        </div>
                        <div>
                            <label class="block font-semibold text-zinc-700 mb-1">Sumber Prospek (Lead Source)</label>
                            <select x-model="form.lead_source"
                                    class="w-full px-3 py-2 bg-white border border-zinc-300 rounded-lg focus:border-black outline-none text-xs">
                                <option value="whatsapp">WhatsApp Langsung</option>
                                <option value="meta_ads">Meta Ads (Facebook/IG CTWA)</option>
                                <option value="website_form">Formulir Website</option>
                                <option value="referral">Rekomendasi Jamaah</option>
                                <option value="walk_in">Walk-in Kantor</option>
                                <option value="repeat_order">Alumni / Repeat Order</option>
                                <option value="other">Lainnya</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Kebutuhan Umroh & Paket -->
                <div class="bg-zinc-50 p-3.5 rounded-xl border border-zinc-200 space-y-3">
                    <div class="font-bold text-zinc-900 uppercase text-[10px] tracking-wider">2. Kebutuhan Umroh & Pilihan Paket</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block font-semibold text-zinc-700 mb-1">Pilih Paket Umroh (Opsional jika belum pasti)</label>
                            <select x-model="form.package_id" @change="onPackageChange()"
                                    class="w-full px-3 py-2 bg-white border border-zinc-300 rounded-lg focus:border-black outline-none text-xs">
                                <option value="">-- Belum Menentukan Paket (Kualifikasi) --</option>
                                <?php foreach ($packages as $pkg): ?>
                                    <option value="<?= $pkg['id'] ?>">
                                        <?= htmlspecialchars($pkg['name']) ?> — <?= htmlspecialchars($pkg['price']) ?> 
                                        <?= !empty($pkg['departure_date']) ? ' (' . date('d M Y', strtotime($pkg['departure_date'])) . ')' : '' ?>
                                        <?= $pkg['quota_remaining'] !== null ? ' • Sisa ' . $pkg['quota_remaining'] . ' seat' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-zinc-700 mb-1">Target Periode Keberangkatan</label>
                            <input type="text" x-model="form.target_month" placeholder="Contoh: November 2026, Ramadhan"
                                   class="w-full px-3 py-2 bg-white border border-zinc-300 rounded-lg focus:border-black outline-none text-xs">
                        </div>
                        <div>
                            <label class="block font-semibold text-zinc-700 mb-1">Kisaran Budget per Jamaah</label>
                            <select x-model="form.budget_range"
                                    class="w-full px-3 py-2 bg-white border border-zinc-300 rounded-lg focus:border-black outline-none text-xs">
                                <option value="">-- Pilih Range Budget --</option>
                                <option value="< 28 Juta">Hemat (< Rp 28 Juta)</option>
                                <option value="28 - 35 Juta">Standar / Reguler (Rp 28 - 35 Juta)</option>
                                <option value="> 35 Juta">VIP / Bintang 5 (> Rp 35 Juta)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Rincian Pax per Kamar & Kalkulasi Deal Value Otomatis -->
                    <div class="pt-2 border-t border-zinc-200">
                        <label class="block font-semibold text-zinc-700 mb-1.5">Jumlah Jamaah & Rincian Kamar:</label>
                        <div class="grid grid-cols-4 gap-2 text-center">
                            <div class="bg-white p-2 rounded-lg border border-zinc-200">
                                <span class="text-[10px] text-zinc-500 font-semibold block">Quad (Ber-4)</span>
                                <input type="number" min="0" x-model.number="form.pax_quad" @input="calcDealValueLive()"
                                       class="w-full text-center font-bold text-sm py-1 border-b border-zinc-300 focus:border-black outline-none">
                            </div>
                            <div class="bg-white p-2 rounded-lg border border-zinc-200">
                                <span class="text-[10px] text-zinc-500 font-semibold block">Triple (Ber-3)</span>
                                <input type="number" min="0" x-model.number="form.pax_triple" @input="calcDealValueLive()"
                                       class="w-full text-center font-bold text-sm py-1 border-b border-zinc-300 focus:border-black outline-none">
                            </div>
                            <div class="bg-white p-2 rounded-lg border border-zinc-200">
                                <span class="text-[10px] text-zinc-500 font-semibold block">Double (Ber-2)</span>
                                <input type="number" min="0" x-model.number="form.pax_double" @input="calcDealValueLive()"
                                       class="w-full text-center font-bold text-sm py-1 border-b border-zinc-300 focus:border-black outline-none">
                            </div>
                            <div class="bg-white p-2 rounded-lg border border-zinc-200">
                                <span class="text-[10px] text-zinc-500 font-semibold block">Infant (Bayi)</span>
                                <input type="number" min="0" x-model.number="form.pax_infant" @input="calcDealValueLive()"
                                       class="w-full text-center font-bold text-sm py-1 border-b border-zinc-300 focus:border-black outline-none">
                            </div>
                        </div>

                        <!-- Live Deal Value Indicator -->
                        <div class="mt-2.5 px-3 py-2 bg-zinc-900 text-white rounded-lg flex items-center justify-between text-xs">
                            <span class="text-zinc-400">Total Estimasi Deal:</span>
                            <span class="font-black text-sm font-mono text-white" x-text="formatRupiah(form.deal_value)"></span>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Status & Follow-up Awal -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">Status Awal di Pipeline</label>
                        <select x-model="form.status"
                                class="w-full px-3 py-2 bg-white border border-zinc-300 rounded-lg focus:border-black outline-none text-xs">
                            <option value="new">1. Prospek Baru (New)</option>
                            <option value="identifying">2. Identifikasi Kebutuhan</option>
                            <option value="offered">3. Paket Ditawarkan</option>
                            <option value="objection">4. Penanganan Keberatan (TGJP)</option>
                            <option value="followup">5. Follow-up Terjadwal</option>
                            <option value="closing">6. Tahap Closing / Reservasi</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">Jadwal Follow-up Berikutnya</label>
                        <input type="date" x-model="form.next_followup_date"
                               class="w-full px-3 py-2 bg-white border border-zinc-300 rounded-lg focus:border-black outline-none text-xs">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block font-semibold text-zinc-700 mb-1">Catatan Kebutuhan Jamaah (NPGD)</label>
                        <textarea x-model="form.notes" rows="2" placeholder="Contoh: Berangkat berdua dengan istri, utamakan jarak hotel dekat karena lansia..."
                                  class="w-full px-3 py-2 bg-white border border-zinc-300 rounded-lg focus:border-black outline-none text-xs"></textarea>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="pt-3 border-t border-zinc-200 flex items-center justify-end gap-2">
                    <button type="button" @click="openModal = false"
                            class="px-4 py-2 border border-zinc-300 hover:bg-zinc-100 rounded-xl text-xs font-semibold text-zinc-700 transition">
                        Batal
                    </button>
                    <button type="submit" :disabled="saving"
                            class="px-5 py-2 bg-black hover:bg-zinc-800 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                        <span x-text="saving ? 'Menyimpan...' : 'Simpan ke Pipeline CRM'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- MODAL 2: MODAL LOST REASON (JIKA STATUS CLOSED_LOST)              -->
    <!-- ================================================================= -->
    <div x-show="openLostModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/60 flex items-center justify-center p-4">
        <div @click.away="openLostModal = false" class="bg-white border border-zinc-200 rounded-2xl max-w-md w-full p-5 shadow-2xl space-y-4">
            <div class="flex items-center justify-between pb-2.5 border-b border-zinc-200">
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-full bg-rose-100 text-rose-700 flex items-center justify-center font-bold text-xs">!</span>
                    <h3 class="font-bold text-sm text-black">Alasan Pembatalan (Lost Reason)</h3>
                </div>
                <button type="button" @click="openLostModal = false" class="text-zinc-400 hover:text-black font-bold text-lg">&times;</button>
            </div>
            
            <p class="text-xs text-zinc-600 leading-relaxed">
                Catat alasan batal untuk evaluasi bisnis dan penyesuaian penawaran travel ke depan.
            </p>

            <form @submit.prevent="confirmLostStatus()" class="space-y-3 text-xs">
                <div>
                    <label class="block font-semibold text-zinc-800 mb-1">Alasan Utama Batal *</label>
                    <select x-model="lostForm.reason" required
                            class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs focus:border-black outline-none bg-white">
                        <option value="">-- Pilih Alasan --</option>
                        <option value="harga_kemahalan">Harga Kemahalan / Budget Kurang</option>
                        <option value="jadwal_bentrok">Jadwal Bentrok Pekerjaan / Cuti</option>
                        <option value="pilih_travel_lain">Memilih Travel Lain (Kompetitor)</option>
                        <option value="kendala_paspor">Kendala Paspor / Dokumen</option>
                        <option value="masalah_kesehatan">Masalah Kesehatan / Sakit</option>
                        <option value="keluarga_tidak_setuju">Keluarga Belum Sepakat</option>
                        <option value="no_response">Tidak Ada Respons (Ghosting)</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                </div>

                <div>
                    <label class="block font-semibold text-zinc-800 mb-1">Catatan Tambahan</label>
                    <textarea x-model="lostForm.detail" rows="3" placeholder="Jelaskan detail alasan jamaah jika ada..."
                              class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-xs focus:border-black outline-none bg-white"></textarea>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2">
                    <button type="button" @click="openLostModal = false"
                            class="px-3.5 py-1.5 border border-zinc-300 rounded-lg text-zinc-700 hover:bg-zinc-100 transition">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-lg font-bold transition">
                        Simpan Status Lost
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- MODAL 3: QUICK LOG FOLLOW-UP & INTERAKSI                          -->
    <!-- ================================================================= -->
    <div x-show="openFollowupModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/60 flex items-center justify-center p-4">
        <div @click.away="openFollowupModal = false" class="bg-white border border-zinc-200 rounded-2xl max-w-md w-full p-5 shadow-2xl space-y-3.5">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-200">
                <div>
                    <h3 class="font-bold text-sm text-black">Catat Interaksi & Follow-up</h3>
                    <p class="text-[11px] text-zinc-500" x-text="activeProspect ? activeProspect.name : ''"></p>
                </div>
                <button type="button" @click="openFollowupModal = false" class="text-zinc-400 hover:text-black font-bold text-lg">&times;</button>
            </div>

            <form @submit.prevent="submitQuickFollowup()" class="space-y-3 text-xs">
                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Catatan Hasil Interaksi *</label>
                    <textarea x-model="quickFollowupForm.note" required rows="3"
                              placeholder="Misal: Sudah telepon jamaah, sepakat ambil kamar Double, minta dikirimi rekening besok..."
                              class="w-full px-3 py-2 border border-zinc-300 rounded-lg focus:border-black outline-none bg-white text-xs"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">Jadwal Sapa Kembali</label>
                        <input type="date" x-model="quickFollowupForm.next_date"
                               class="w-full px-3 py-1.5 border border-zinc-300 rounded-lg focus:border-black outline-none bg-white text-xs">
                    </div>
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">Ubah Status (Opsional)</label>
                        <select x-model="quickFollowupForm.status"
                                class="w-full px-3 py-1.5 border border-zinc-300 rounded-lg focus:border-black outline-none bg-white text-xs">
                            <option value="">-- Tetap Sama --</option>
                            <option value="identifying">Identifikasi</option>
                            <option value="offered">Ditawarkan</option>
                            <option value="objection">Keberatan</option>
                            <option value="followup">Follow-up</option>
                            <option value="closing">Closing</option>
                            <option value="closed_won">Closed Won</option>
                        </select>
                    </div>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2">
                    <button type="button" @click="openFollowupModal = false"
                            class="px-3.5 py-1.5 border border-zinc-300 rounded-lg text-zinc-700 hover:bg-zinc-100 transition">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-1.5 bg-black hover:bg-zinc-800 text-white rounded-lg font-bold transition">
                        Simpan Catatan
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- Pass Initial Data to Alpine -->
<script>
function prospectsPage() {
    return {
        viewMode: 'kanban', // 'kanban' or 'table'
        searchQuery: '',
        activeFilterPill: 'all', // 'all', 'high_intent', 'due_today', 'won', 'new'
        filterPackage: '',
        filterSource: '',
        saving: false,

        // Master Data from PHP
        prospects: <?= json_encode($initialProspects, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE) ?> || [],
        packages: <?= json_encode($packages, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE) ?> || [],
        brandId: <?= (int)$brand['id'] ?>,

        // 8 Pipeline Stages
        pipelineStages: [
            { status: 'new',         title: '1. Prospek Baru',      dotClass: 'bg-zinc-400' },
            { status: 'identifying', title: '2. Identifikasi',       dotClass: 'bg-blue-500' },
            { status: 'offered',     title: '3. Ditawarkan',        dotClass: 'bg-indigo-500' },
            { status: 'objection',   title: '4. Keberatan (TGJP)',   dotClass: 'bg-amber-500' },
            { status: 'followup',    title: '5. Follow-up',         dotClass: 'bg-sky-500' },
            { status: 'closing',     title: '6. Closing / Reservasi',dotClass: 'bg-purple-600' },
            { status: 'closed_won',  title: '7. Deal Menang (Won)', dotClass: 'bg-emerald-600' },
            { status: 'closed_lost', title: '8. Batal / Lost',      dotClass: 'bg-rose-500' },
        ],

        // Modals state
        openModal: false,
        openLostModal: false,
        openFollowupModal: false,
        activeProspect: null,

        // Form state for new lead
        form: {
            id: null,
            name: '',
            phone: '',
            city: '',
            lead_source: 'whatsapp',
            package_id: '',
            target_month: '',
            budget_range: '',
            room_preference: '',
            pax_quad: 0,
            pax_triple: 0,
            pax_double: 0,
            pax_infant: 0,
            deal_value: 0,
            status: 'new',
            current_stage: 'greeting',
            notes: '',
            next_followup_date: ''
        },

        lostForm: {
            prospectId: null,
            reason: '',
            detail: ''
        },

        quickFollowupForm: {
            prospectId: null,
            note: '',
            next_date: '',
            status: ''
        },

        // Metrics Computed
        get metrics() {
            const today = new Date().toISOString().split('T')[0];
            let activeCount = 0;
            let activePipelineValue = 0;
            let wonCount = 0;
            let wonValue = 0;
            let dueCount = 0;

            this.prospects.forEach(p => {
                const isWon = p.status === 'closed_won';
                const isLost = p.status === 'closed_lost';
                const dVal = Number(p.deal_value) || this.calcCardDealValue(p);

                if (!isWon && !isLost) {
                    activeCount++;
                    activePipelineValue += dVal;
                }
                if (isWon) {
                    wonCount++;
                    wonValue += dVal;
                }
                if (!isWon && !isLost && p.next_followup_date && p.next_followup_date <= today) {
                    dueCount++;
                }
            });

            return { activeCount, activePipelineValue, wonCount, wonValue, dueCount };
        },

        get countHighIntent() {
            return this.prospects.filter(p => p.status === 'closing' || p.status === 'offered').length;
        },

        get countNew() {
            return this.prospects.filter(p => p.status === 'new').length;
        },

        // Filtered list
        get filteredProspects() {
            const today = new Date().toISOString().split('T')[0];
            const q = this.searchQuery.toLowerCase().trim();

            return this.prospects.filter(p => {
                // Search query
                if (q) {
                    const matchName = (p.name || '').toLowerCase().includes(q);
                    const matchPhone = (p.phone || '').includes(q);
                    const matchCity = (p.city || '').toLowerCase().includes(q);
                    const matchPkg = (p.package_name || '').toLowerCase().includes(q);
                    const matchNotes = (p.notes || '').toLowerCase().includes(q);
                    if (!matchName && !matchPhone && !matchCity && !matchPkg && !matchNotes) {
                        return false;
                    }
                }

                // Quick Pills
                if (this.activeFilterPill === 'high_intent') {
                    if (p.status !== 'closing' && p.status !== 'offered') return false;
                } else if (this.activeFilterPill === 'due_today') {
                    if (p.status === 'closed_won' || p.status === 'closed_lost') return false;
                    if (!p.next_followup_date || p.next_followup_date > today) return false;
                } else if (this.activeFilterPill === 'won') {
                    if (p.status !== 'closed_won') return false;
                } else if (this.activeFilterPill === 'new') {
                    if (p.status !== 'new') return false;
                }

                // Filter Package
                if (this.filterPackage === 'none') {
                    if (p.package_id) return false;
                } else if (this.filterPackage && Number(p.package_id) !== Number(this.filterPackage)) {
                    return false;
                }

                // Filter Lead Source
                if (this.filterSource && (p.lead_source || 'whatsapp') !== this.filterSource) {
                    return false;
                }

                return true;
            });
        },

        getStageProspects(status) {
            return this.filteredProspects.filter(p => p.status === status);
        },

        getStageValue(status) {
            return this.getStageProspects(status).reduce((acc, p) => acc + (Number(p.deal_value) || this.calcCardDealValue(p)), 0);
        },

        resetFilters() {
            this.searchQuery = '';
            this.activeFilterPill = 'all';
            this.filterPackage = '';
            this.filterSource = '';
        },

        // Helper calculations
        calcCardDealValue(item) {
            if (!item.package_id) return 0;
            const pkg = this.packages.find(p => p.id == item.package_id);
            if (!pkg) return 0;

            const clean = (val, def = 0) => {
                if (!val) return def;
                const n = String(val).replace(/[^\d]/g, '');
                return n ? Number(n) : def;
            };

            const defPrice = clean(pkg.price);
            const pQuad = clean(pkg.price_quad, defPrice);
            const pTriple = clean(pkg.price_triple, defPrice);
            const pDouble = clean(pkg.price_double, defPrice);
            const pInfant = clean(pkg.price_infant, 0);

            const q = Number(item.pax_quad) || 0;
            const t = Number(item.pax_triple) || 0;
            const d = Number(item.pax_double) || 0;
            const i = Number(item.pax_infant) || 0;

            return (q * pQuad) + (t * pTriple) + (d * pDouble) + (i * pInfant);
        },

        calcDealValueLive() {
            this.form.deal_value = this.calcCardDealValue(this.form);
        },

        onPackageChange() {
            this.calcDealValueLive();
        },

        formatRupiah(val) {
            if (!val || isNaN(val) || val <= 0) return 'Rp 0';
            return 'Rp ' + Number(val).toLocaleString('id-ID');
        },

        formatPaxRooms(item) {
            const parts = [];
            if (item.pax_quad > 0) parts.push(item.pax_quad + ' Quad');
            if (item.pax_triple > 0) parts.push(item.pax_triple + ' Triple');
            if (item.pax_double > 0) parts.push(item.pax_double + ' Double');
            if (item.pax_infant > 0) parts.push(item.pax_infant + ' Infant');
            return parts.length > 0 ? parts.join(', ') : 'Belum atur kamar';
        },

        getInitials(name) {
            if (!name) return 'J';
            return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
        },

        formatDateIndo(dateStr) {
            if (!dateStr) return '-';
            try {
                const d = new Date(dateStr);
                return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
            } catch(e) { return dateStr; }
        },

        formatFollowupShort(dateStr) {
            if (!dateStr) return '';
            const today = new Date().toISOString().split('T')[0];
            if (dateStr === today) return 'Hari Ini';
            if (dateStr < today) return 'Lewat Jadwal';
            return this.formatDateIndo(dateStr);
        },

        formatFollowupRelative(dateStr) {
            if (!dateStr) return '';
            const today = new Date().toISOString().split('T')[0];
            if (dateStr === today) return 'Jadwal Hari Ini';
            if (dateStr < today) return 'Terlewat dari jadwal';
            return 'Terjadwal';
        },

        getFollowupBadgeClass(dateStr) {
            const today = new Date().toISOString().split('T')[0];
            if (dateStr === today) return 'bg-amber-100 text-amber-800 border-amber-300 font-bold';
            if (dateStr < today) return 'bg-rose-100 text-rose-800 border-rose-300 font-bold';
            return 'bg-zinc-100 text-zinc-700 border-zinc-200';
        },

        formatLeadSource(source) {
            const map = {
                'whatsapp': 'WhatsApp',
                'meta_ads': 'Meta Ads',
                'website_form': 'Website',
                'referral': 'Referral',
                'walk_in': 'Walk-in',
                'repeat_order': 'Alumni',
                'other': 'Lainnya'
            };
            return map[source] || source || 'WhatsApp';
        },

        getLeadSourceBadge(source) {
            switch(source) {
                case 'meta_ads': return 'bg-blue-50 text-blue-700 border-blue-200';
                case 'referral': return 'bg-purple-50 text-purple-700 border-purple-200';
                case 'website_form': return 'bg-sky-50 text-sky-700 border-sky-200';
                case 'walk_in': return 'bg-amber-50 text-amber-800 border-amber-200';
                default: return 'bg-zinc-100 text-zinc-700 border-zinc-200';
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

        formatTimeAgo(dateStr) {
            if (!dateStr) return '';
            const sec = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
            if (sec < 60) return 'baru saja';
            if (sec < 3600) return Math.floor(sec / 60) + 'm lalu';
            if (sec < 86400) return Math.floor(sec / 3600) + 'j lalu';
            return Math.floor(sec / 86400) + 'h lalu';
        },

        // Modal Action Openers
        openCreateModal() {
            this.form = {
                id: null,
                name: '',
                phone: '',
                city: '',
                lead_source: 'whatsapp',
                package_id: '',
                target_month: '',
                budget_range: '',
                room_preference: '',
                pax_quad: 0,
                pax_triple: 0,
                pax_double: 0,
                pax_infant: 0,
                deal_value: 0,
                status: 'new',
                current_stage: 'greeting',
                notes: '',
                next_followup_date: ''
            };
            this.openModal = true;
        },

        openQuickFollowupModal(prospect) {
            this.activeProspect = prospect;
            this.quickFollowupForm = {
                prospectId: prospect.id,
                note: '',
                next_date: prospect.next_followup_date || '',
                status: ''
            };
            this.openFollowupModal = true;
        },

        // Quick Stage Change handler
        async quickChangeStatus(prospect, newStatus) {
            if (newStatus === 'closed_lost') {
                this.lostForm = {
                    prospectId: prospect.id,
                    reason: '',
                    detail: ''
                };
                this.activeProspect = prospect;
                this.openLostModal = true;
                return;
            }

            try {
                const res = await fetch('api/prospects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_stage',
                        id: prospect.id,
                        status: newStatus
                    })
                });
                const data = await res.json();
                if (data.success) {
                    prospect.status = newStatus;
                } else {
                    alert('Gagal mengubah status: ' + (data.error || 'Terjadi kesalahan'));
                }
            } catch(e) {
                alert('Gagal menghubungi server.');
            }
        },

        async confirmLostStatus() {
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
                        id: this.lostForm.prospectId,
                        status: 'closed_lost',
                        lost_reason: this.lostForm.reason,
                        lost_reason_detail: this.lostForm.detail
                    })
                });
                const data = await res.json();
                if (data.success) {
                    const p = this.prospects.find(x => x.id == this.lostForm.prospectId);
                    if (p) {
                        p.status = 'closed_lost';
                        p.lost_reason = this.lostForm.reason;
                        p.lost_reason_detail = this.lostForm.detail;
                    }
                    this.openLostModal = false;
                } else {
                    alert(data.error || 'Gagal menyimpan status');
                }
            } catch(e) {
                alert('Gagal menghubungi server.');
            }
        },

        async submitQuickFollowup() {
            if (!this.quickFollowupForm.note) return;
            try {
                const res = await fetch('api/prospects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'log_followup',
                        id: this.quickFollowupForm.prospectId,
                        note: this.quickFollowupForm.note,
                        next_followup_date: this.quickFollowupForm.next_date,
                        status: this.quickFollowupForm.status || null
                    })
                });
                const data = await res.json();
                if (data.success) {
                    const p = this.prospects.find(x => x.id == this.quickFollowupForm.prospectId);
                    if (p) {
                        if (this.quickFollowupForm.next_date) p.next_followup_date = this.quickFollowupForm.next_date;
                        if (this.quickFollowupForm.status) p.status = this.quickFollowupForm.status;
                    }
                    this.openFollowupModal = false;
                } else {
                    alert(data.error || 'Gagal menyimpan');
                }
            } catch(e) {
                alert('Gagal menghubungi server.');
            }
        },

        async saveProspect() {
            this.saving = true;
            try {
                const payload = Object.assign({}, this.form, {
                    action: 'save',
                    brand_id: this.brandId
                });
                const res = await fetch('api/prospects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success && data.prospect) {
                    this.prospects.unshift(data.prospect);
                    this.openModal = false;
                } else {
                    alert(data.error || 'Gagal menyimpan prospek');
                }
            } catch(e) {
                alert('Gagal menghubungi server.');
            }
            this.saving = false;
        },

        exportCSV() {
            const rows = [
                ['ID', 'Nama Jamaah', 'Nomor WA', 'Kota', 'Sumber Lead', 'Paket Diminati', 'Status', 'Deal Value', 'DP Masuk', 'Next Followup', 'CS PIC']
            ];
            this.filteredProspects.forEach(p => {
                rows.push([
                    p.id,
                    '"' + (p.name || '').replace(/"/g, '""') + '"',
                    p.phone || '',
                    '"' + (p.city || '').replace(/"/g, '""') + '"',
                    p.lead_source || '',
                    '"' + (p.package_name || 'Belum Pilih').replace(/"/g, '""') + '"',
                    p.status,
                    p.deal_value || 0,
                    p.dp_amount || 0,
                    p.next_followup_date || '',
                    '"' + (p.cs_name || '').replace(/"/g, '""') + '"'
                ]);
            });

            const csvContent = 'data:text/csv;charset=utf-8,' + rows.map(e => e.join(',')).join('\n');
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'data_prospek_crm_' + new Date().toISOString().split('T')[0] + '.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
