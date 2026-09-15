<?php
$pageTitle = 'CS Umroh Copilot - Workspace';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/data/scripts_loader.php';

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
$stmt = $db->prepare("SELECT * FROM packages WHERE brand_id = ? AND is_active = 1 ORDER BY id DESC");
$stmt->execute([$brand['id']]);
$packages = $stmt->fetchAll();

// All brands for Super Admin switcher
$allBrands = [];
if ($currentUser['role'] === 'superadmin') {
    $allBrands = $db->query("SELECT id, name FROM brands ORDER BY id ASC")->fetchAll();
}

// Load initial prospects list
$sql = "
    SELECT p.*, b.name AS brand_name, pkg.name AS package_name, pkg.price AS package_price, u.name AS cs_name
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

// Check if a prospect was loaded from Data Prospek page
$loadedProspect = null;
if (!empty($_GET['load_prospect'])) {
    $stmt = $db->prepare("SELECT * FROM prospects WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$_GET['load_prospect']]);
    $loadedProspect = $stmt->fetch();
}

// Load transformed scripts
$rawScripts = get_all_scripts();
?>

<div class="max-w-7xl mx-auto space-y-6" x-data="copilotApp()">

    <!-- Top Welcome Header (Matching Reference SaaS style) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-black tracking-tight">
                Welcome back, <?= htmlspecialchars(explode(' ', $currentUser['name'])[0]) ?>!
            </h1>
            <p class="text-xs text-zinc-500 mt-1">
                Kelola komunikasi calon jamaah, penanganan keberatan TGJP, dan pipeline konversi hari ini.
            </p>
        </div>

        <!-- Super Admin Brand Switcher (if superadmin) -->
        <?php if ($currentUser['role'] === 'superadmin'): ?>
            <div class="flex items-center gap-2 text-xs bg-white border border-zinc-200/80 p-1.5 rounded-xl shadow-xs">
                <span class="text-zinc-500 font-medium pl-2">Brand:</span>
                <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                    <button type="button" @click="open = !open"
                            class="px-2.5 py-1 rounded-lg text-xs bg-zinc-50 border border-zinc-200 font-semibold text-black focus:outline-none flex items-center gap-1.5 hover:bg-zinc-100 transition">
                        <span><?= htmlspecialchars($brand['name']) ?></span>
                        <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>
                    <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                         class="absolute right-0 z-50 mt-1.5 w-52 bg-white border border-zinc-200 rounded-xl shadow-xl py-1 text-xs divide-y divide-zinc-50">
                        <?php foreach ($allBrands as $b): ?>
                            <a href="index.php?brand_id=<?= $b['id'] ?>"
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
    </div>


    <!-- Quick Overview Stats (Compact & Mobile-First) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-5">
        <!-- Featured Black Card: Active Brand & Total Prospects -->
        <div class="p-4 sm:p-6 bg-zinc-950 text-white border border-zinc-800 rounded-xl sm:rounded-2xl shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-400">Total Prospek CS</span>
                <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg sm:rounded-xl bg-zinc-800 text-white flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-3 sm:mt-4">
                <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white" x-text="prospectsList.length + ' Jamaah'"></div>
                <div class="text-[11px] text-zinc-400 mt-0.5 sm:mt-1 flex items-center gap-1.5">
                    <span class="text-amber-400 font-medium"><?= htmlspecialchars($brand['name']) ?></span>
                    <span>&bull;</span>
                    <span>Aktif</span>
                </div>
            </div>
        </div>

        <!-- Card 2: Proses Closing & Penawaran -->
        <div class="p-4 sm:p-6 bg-white border border-zinc-200/80 rounded-xl sm:rounded-2xl shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-500">Proses Closing & Follow-up</span>
                <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg sm:rounded-xl border border-zinc-200 bg-zinc-50 text-zinc-700 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-3 sm:mt-4">
                <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-black" 
                     x-text="prospectsList.filter(p => ['closing', 'offered', 'followup'].includes(p.status)).length + ' Jamaah'"></div>
                <div class="text-[11px] text-zinc-400 mt-0.5 sm:mt-1">Tahap kualifikasi & penawaran</div>
            </div>
        </div>

        <!-- Card 3: Closing Won (DP Masuk) -->
        <div class="p-4 sm:p-6 bg-white border border-zinc-200/80 rounded-xl sm:rounded-2xl shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-500">Closing Won (DP Masuk)</span>
                <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg sm:rounded-xl border border-zinc-200 bg-zinc-50 text-zinc-700 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
            </div>
            <div class="mt-3 sm:mt-4">
                <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-black" 
                     x-text="prospectsList.filter(p => p.status === 'closed_won').length + ' Berhasil'"></div>
                <div class="text-[11px] text-zinc-400 mt-0.5 sm:mt-1">Calon jamaah terkonfirmasi booking</div>
            </div>
        </div>
    </div>

    <!-- Top Control Bar: Quick Variables (Strict Monochrome) -->
    <div class="bg-white border border-zinc-200 rounded-2xl p-4 sm:p-5 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-zinc-100">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-[11px] font-bold uppercase tracking-wider bg-black text-white px-2.5 py-0.5 rounded-lg">
                    <?= htmlspecialchars($brand['name']) ?>
                </span>
                <span class="text-xs text-zinc-500 font-mono">Izin PPIU: <?= htmlspecialchars($brand['ppiu_number'] ?: '-') ?></span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="openSaveLeadModal()"
                    class="px-3.5 py-2 bg-zinc-100 hover:bg-black hover:text-white border border-zinc-300 text-zinc-800 text-xs font-semibold rounded-xl transition flex items-center gap-1.5 shadow-2xs">
                    <span>+ Simpan ke Prospek</span>
                </button>
            </div>
        </div>

        <!-- Dynamic Variable Inputs -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-4 text-xs">
            <div>
                <label class="block text-zinc-600 font-semibold mb-1 text-xs">Nama Calon Jamaah ({{nama}})</label>
                <input type="text" x-model="prospectName" 
                    placeholder="Contoh: Bu Siti / Pak Budi"
                    class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black font-semibold text-zinc-900 bg-white">
            </div>

            <div>
                <label class="block text-zinc-600 font-semibold mb-1 text-xs">Pilihan Paket Umroh</label>
                <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                    <button type="button" @click="open = !open"
                            class="w-full flex items-center justify-between px-3 py-2 border border-zinc-300 bg-white rounded-xl text-xs font-semibold text-zinc-900 focus:outline-none focus:border-black shadow-2xs">
                        <span class="truncate" x-text="currentPkg.name ? currentPkg.name + ' (' + currentPkg.price + ')' : '-- Pilih Paket --'"></span>
                        <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0 ml-1" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>
                    <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                         class="absolute z-50 mt-1 w-full min-w-[240px] bg-white border border-zinc-200 rounded-xl shadow-xl max-h-56 overflow-y-auto py-1 text-xs divide-y divide-zinc-50">
                        <template x-for="pkg in packagesList" :key="pkg.id">
                            <button type="button" @click="selectedPackageId = pkg.id; updatePackage(); open = false"
                                    class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                    :class="selectedPackageId == pkg.id ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                <span class="truncate" x-text="pkg.name + ' (' + pkg.price + ')'"></span>
                                <svg x-show="selectedPackageId == pkg.id" class="w-3.5 h-3.5 text-black shrink-0 ml-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-zinc-600 font-semibold mb-1 text-xs">Nama CS ({{cs_name}})</label>
                <input type="text" x-model="csName" 
                    class="w-full px-3 py-2 border border-zinc-200 rounded-xl bg-zinc-50 text-zinc-700 font-medium" readonly>
            </div>

            <div>
                <label class="block text-zinc-600 font-semibold mb-1 text-xs">No. WA Jamaah (Opsional)</label>
                <input type="text" x-model="prospectPhone" 
                    placeholder="Contoh: 08123456789"
                    class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black font-mono text-zinc-900 bg-white">
            </div>
        </div>

        <!-- Selected Package Summary Badge -->
        <div class="mt-3.5 pt-3 border-t border-zinc-100 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 text-[11px] text-zinc-600">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <div><strong>Harga:</strong> <span x-text="currentPkg.price || '-'"></span></div>
                <span>&bull;</span>
                <div><strong>DP:</strong> <span x-text="currentPkg.dp || '-'"></span></div>
                <span>&bull;</span>
                <div><strong>Maskapai:</strong> <span x-text="currentPkg.airline || '-'"></span></div>
                <span>&bull;</span>
                <div><strong>Hotel Makkah:</strong> <span x-text="currentPkg.hotel_makkah || '-'"></span></div>
            </div>
            <div class="flex items-center gap-3">
                <template x-if="currentPkg.id">
                    <a :href="'admin/package_detail.php?id=' + currentPkg.id" target="_blank" 
                       class="font-bold text-black hover:underline inline-flex items-center gap-1 bg-zinc-100 hover:bg-zinc-200 px-2.5 py-1 rounded-lg transition shadow-2xs">
                        <span>Lihat Detail & Itinerary</span>
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    </a>
                </template>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs: 6 Conversion Stages (Swipeable Pills on Mobile) & Search -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1.5 flex-nowrap -mx-1 px-1">
            <button type="button" @click="activeStage = 'greeting'"
                :class="activeStage === 'greeting' ? 'bg-black text-white font-semibold shadow-xs' : 'bg-white border border-zinc-200 text-zinc-700 hover:border-black'"
                class="px-3.5 py-2 rounded-xl text-xs transition shrink-0 whitespace-nowrap min-h-[38px]">
                1. Greeting
            </button>
            <button type="button" @click="activeStage = 'identification'"
                :class="activeStage === 'identification' ? 'bg-black text-white font-semibold shadow-xs' : 'bg-white border border-zinc-200 text-zinc-700 hover:border-black'"
                class="px-3.5 py-2 rounded-xl text-xs transition shrink-0 whitespace-nowrap min-h-[38px]">
                2. Identifikasi (NPGD)
            </button>
            <button type="button" @click="activeStage = 'offer'"
                :class="activeStage === 'offer' ? 'bg-black text-white font-semibold shadow-xs' : 'bg-white border border-zinc-200 text-zinc-700 hover:border-black'"
                class="px-3.5 py-2 rounded-xl text-xs transition shrink-0 whitespace-nowrap min-h-[38px]">
                3. Penawaran (MRBVA)
            </button>
            <button type="button" @click="activeStage = 'closing'"
                :class="activeStage === 'closing' ? 'bg-black text-white font-semibold shadow-xs' : 'bg-white border border-zinc-200 text-zinc-700 hover:border-black'"
                class="px-3.5 py-2 rounded-xl text-xs transition shrink-0 whitespace-nowrap min-h-[38px]">
                4. Closing (CRA)
            </button>
            <button type="button" @click="activeStage = 'objection'"
                :class="activeStage === 'objection' ? 'bg-black text-white font-semibold shadow-xs' : 'bg-white border border-zinc-200 text-zinc-700 hover:border-black'"
                class="px-3.5 py-2 rounded-xl text-xs transition shrink-0 whitespace-nowrap min-h-[38px] flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
                <span>5. Keberatan (TGJP)</span>
            </button>
            <button type="button" @click="activeStage = 'followup'"
                :class="activeStage === 'followup' ? 'bg-black text-white font-semibold shadow-xs' : 'bg-white border border-zinc-200 text-zinc-700 hover:border-black'"
                class="px-3.5 py-2 rounded-xl text-xs transition shrink-0 whitespace-nowrap min-h-[38px]">
                6. Follow-up
            </button>
        </div>

        <!-- Search Input -->
        <div class="relative w-full md:w-64 shrink-0">
            <input type="text" x-model="searchQuery" 
                placeholder="Cari skrip (cth: 'mahal', 'dp')..." 
                class="w-full pl-8 pr-3 py-2 border border-zinc-300 rounded-xl text-xs bg-white text-zinc-900 focus:outline-none focus:border-black placeholder-zinc-400 shadow-2xs">
            <svg class="w-3.5 h-3.5 text-zinc-400 absolute left-2.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
        </div>
    </div>

    <!-- STAGE 1 s/d 4 & 6: STANDARD SCRIPT LIST -->
    <div x-show="activeStage !== 'objection'" class="space-y-4">
        <div class="bg-zinc-50 border border-zinc-200 rounded-xl p-3.5 text-xs text-zinc-700 flex flex-col sm:flex-row sm:items-start justify-between gap-2">
            <div>
                <span class="font-bold text-black uppercase tracking-wider text-[10px] block mb-1">Panduan SOP Tahap Ini:</span>
                <p x-text="currentStageDescription" class="leading-relaxed"></p>
            </div>
            <span class="text-[11px] font-mono text-zinc-500 px-2.5 py-1 bg-white border border-zinc-200 rounded-lg shrink-0 self-start"
                  x-text="filteredScripts.length + ' Template Skrip'"></span>
        </div>

        <div x-show="filteredScripts.length === 0" class="py-12 text-center text-zinc-400 bg-white border border-zinc-200 rounded-xl">
            Tidak ada skrip yang cocok dengan kata kunci pencarian Anda.
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <template x-for="item in filteredScripts" :key="item.id">
                <div class="bg-white border border-zinc-200 hover:border-black rounded-2xl p-4 sm:p-5 transition flex flex-col justify-between shadow-xs">
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div>
                                <span class="text-[10px] font-mono uppercase bg-zinc-100 text-zinc-600 px-2 py-0.5 rounded-md" x-text="item.category || 'general'"></span>
                                <h3 class="text-sm font-bold text-black mt-1.5" x-text="item.title"></h3>
                            </div>
                        </div>

                        <div x-show="item.use_when" class="text-[11px] text-zinc-500 mb-2.5 italic">
                            <span class="font-medium text-zinc-700 not-italic">Gunakan saat:</span> <span x-text="item.use_when"></span>
                        </div>

                        <div class="p-3.5 bg-zinc-50 border border-zinc-200 rounded-xl text-xs text-zinc-800 font-sans leading-relaxed whitespace-pre-wrap select-all selection:bg-black selection:text-white"
                             x-text="renderScript(item.script)">
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-zinc-100 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-1">
                            <template x-for="tag in (item.tags || [])" :key="tag">
                                <span class="text-[9px] font-mono text-zinc-400 bg-white border border-zinc-200 px-1.5 py-0.5 rounded" x-text="'#' + tag"></span>
                            </template>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" 
                                x-show="prospectPhone.trim().length > 5"
                                @click="openWhatsApp(renderScript(item.script))"
                                title="Kirim Langsung ke WhatsApp"
                                class="min-h-[38px] px-3 py-2 bg-emerald-50 border border-emerald-200 text-emerald-800 hover:bg-emerald-100 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 shadow-2xs">
                                <svg class="w-3.5 h-3.5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m3 21 1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"></path>
                                </svg>
                                <span>Kirim WA</span>
                            </button>
                            <button type="button" 
                                @click="copyText(renderScript(item.script), item.id)"
                                class="min-h-[38px] px-4 py-2 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 shadow-sm"
                                :class="copiedScriptId === item.id ? 'bg-emerald-600 text-white' : 'bg-black hover:bg-zinc-800 text-white'">
                                <svg x-show="copiedScriptId !== item.id" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path>
                                </svg>
                                <svg x-show="copiedScriptId === item.id" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                <span x-text="copiedScriptId === item.id ? 'Tersalin!' : 'Salin Pesan'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>


        <!-- STAGE 5: TGJP OBJECTION WIZARD -->
        <div x-show="activeStage === 'objection'" class="space-y-6">
            <div class="bg-zinc-50 border border-zinc-200 rounded-lg p-4 text-xs text-zinc-700">
                <h3 class="font-bold text-black uppercase tracking-wider text-xs mb-1">
                    Framework TGJP: Tangani Keberatan Tanpa Membantah & Tanpa Panik
                </h3>
                <p class="leading-relaxed text-zinc-600">
                    Gunakan 4 langkah berurutan: 
                    <strong>1. T (Terima)</strong> agar prospek didengar &bull; 
                    <strong>2. G (Gali)</strong> untuk mencari akar keberatan sesungguhnya &bull; 
                    <strong>3. J (Jawab)</strong> dengan solusi paket yang pas &bull; 
                    <strong>4. P (Pastikan)</strong> kesiapan jamaah melangkah ke Closing.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 border border-zinc-200 rounded-xl">
                <label class="text-xs font-bold text-black uppercase tracking-wider">Pilih Keberatan Jamaah:</label>
                <div x-data="{ open: false }" class="relative w-full sm:w-80" @click.outside="open = false">
                    <button type="button" @click="open = !open"
                            class="w-full flex items-center justify-between px-3 py-2 border border-zinc-300 bg-white rounded-lg text-xs font-semibold text-zinc-900 focus:outline-none focus:border-black shadow-2xs">
                        <span class="truncate" x-text="currentObjection ? currentObjection.title : '-- Pilih Keberatan --'"></span>
                        <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0 ml-1" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>
                    <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                         class="absolute z-50 mt-1 w-full bg-white border border-zinc-200 rounded-xl shadow-xl max-h-60 overflow-y-auto py-1 text-xs divide-y divide-zinc-50">
                        <template x-for="obj in objectionScripts" :key="obj.id">
                            <button type="button" @click="selectedObjectionId = obj.id; open = false"
                                    class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                    :class="selectedObjectionId == obj.id ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                <span class="truncate" x-text="obj.title"></span>
                                <svg x-show="selectedObjectionId == obj.id" class="w-3.5 h-3.5 text-black shrink-0 ml-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <template x-if="currentObjection">
                <div class="bg-white border border-zinc-200 rounded-xl p-5 shadow-sm space-y-6">
                    <div class="border-b border-zinc-100 pb-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-bold text-black" x-text="currentObjection.title"></h2>
                            <span class="text-[10px] font-mono uppercase bg-zinc-100 px-2 py-0.5 rounded text-zinc-600" x-text="currentObjection.category"></span>
                        </div>
                        <div class="mt-2 text-xs text-zinc-500">
                            <span class="font-semibold text-zinc-700">Contoh Kalimat Prospek:</span>
                            <div class="flex flex-wrap gap-1.5 mt-1">
                                <template x-for="ex in (currentObjection.prospect_examples || [])" :key="ex">
                                    <span class="bg-zinc-50 border border-zinc-200 px-2 py-0.5 rounded text-[11px] text-zinc-700 italic" x-text="'“' + ex + '”'"></span>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- 4 Steps TGJP Flow -->
                    <div class="space-y-4">
                        <!-- 1. TERIMA -->
                        <div class="p-4 border border-sky-200 rounded-xl bg-sky-50/30">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-5 h-5 bg-sky-600 text-white rounded-full flex items-center justify-center font-bold text-[10px] shadow-2xs">1</span>
                                    <span class="font-bold text-xs text-sky-950 uppercase tracking-wider">T - TERIMA (Validasi Perasaan Jamaah)</span>
                                </div>
                                <span class="text-[10px] text-sky-700 italic">Dengarkan tanpa membantah</span>
                            </div>
                            <div class="space-y-2.5">
                                <template x-for="(tScript, idx) in (currentObjection.tgjp?.terima || [])" :key="idx">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between p-3 sm:p-3.5 bg-white border border-sky-200/80 rounded-xl text-xs gap-2.5 shadow-2xs">
                                        <div class="text-zinc-800 leading-relaxed font-sans" x-text="renderScript(tScript)"></div>
                                        <button type="button" @click="copyText(renderScript(tScript), 't-' + idx)"
                                            class="min-h-[36px] px-3.5 py-1.5 rounded-lg text-xs font-semibold shrink-0 transition flex items-center justify-center gap-1.5 self-end sm:self-center"
                                            :class="copiedScriptId === ('t-' + idx) ? 'bg-emerald-600 text-white' : 'bg-black hover:bg-zinc-800 text-white'">
                                            <svg x-show="copiedScriptId === ('t-' + idx)" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                            <span x-text="copiedScriptId === ('t-' + idx) ? 'Tersalin' : 'Salin'"></span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- 2. GALI -->
                        <div class="p-4 border border-indigo-200 rounded-xl bg-indigo-50/30">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-5 h-5 bg-indigo-600 text-white rounded-full flex items-center justify-center font-bold text-[10px] shadow-2xs">2</span>
                                    <span class="font-bold text-xs text-indigo-950 uppercase tracking-wider">G - GALI (Temukan Akar Masalah)</span>
                                </div>
                                <span class="text-[10px] text-indigo-700 italic">Pertanyaan ringan mencari kejelasan</span>
                            </div>
                            <div class="space-y-2.5">
                                <template x-for="(gScript, idx) in (currentObjection.tgjp?.gali || [])" :key="idx">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between p-3 sm:p-3.5 bg-white border border-indigo-200/80 rounded-xl text-xs gap-2.5 shadow-2xs">
                                        <div class="text-zinc-800 leading-relaxed font-sans" x-text="renderScript(gScript)"></div>
                                        <button type="button" @click="copyText(renderScript(gScript), 'g-' + idx)"
                                            class="min-h-[36px] px-3.5 py-1.5 rounded-lg text-xs font-semibold shrink-0 transition flex items-center justify-center gap-1.5 self-end sm:self-center"
                                            :class="copiedScriptId === ('g-' + idx) ? 'bg-emerald-600 text-white' : 'bg-black hover:bg-zinc-800 text-white'">
                                            <svg x-show="copiedScriptId === ('g-' + idx)" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                            <span x-text="copiedScriptId === ('g-' + idx) ? 'Tersalin' : 'Salin'"></span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- 3. JAWAB -->
                        <div class="p-4 border border-amber-200 rounded-xl bg-amber-50/30">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-5 h-5 bg-amber-500 text-white rounded-full flex items-center justify-center font-bold text-[10px] shadow-2xs">3</span>
                                    <span class="font-bold text-xs text-amber-950 uppercase tracking-wider">J - JAWAB (Pilih Solusi Berdasarkan Alasan)</span>
                                </div>
                                <span class="text-[10px] text-amber-700 italic">Solutif & sesuai fakta</span>
                            </div>
                            <div class="space-y-2.5">
                                <template x-for="(jItem, idx) in (currentObjection.tgjp?.jawab || [])" :key="idx">
                                    <div class="p-3 sm:p-3.5 bg-white border border-amber-200/80 rounded-xl text-xs shadow-2xs space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[10px] font-mono uppercase bg-amber-100 text-amber-800 border border-amber-200 px-2 py-0.5 rounded font-semibold" 
                                                  x-text="'Alasan: ' + (jItem.reason || 'umum')"></span>
                                            <button type="button" @click="copyText(renderScript(jItem.script), 'j-' + idx)"
                                                class="min-h-[34px] px-3 py-1 rounded-lg text-xs font-semibold shrink-0 transition flex items-center gap-1.5"
                                                :class="copiedScriptId === ('j-' + idx) ? 'bg-emerald-600 text-white' : 'bg-black hover:bg-zinc-800 text-white'">
                                                <svg x-show="copiedScriptId === ('j-' + idx)" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                                <span x-text="copiedScriptId === ('j-' + idx) ? 'Tersalin' : 'Salin'"></span>
                                            </button>
                                        </div>
                                        <div class="text-zinc-800 leading-relaxed font-sans" x-text="renderScript(jItem.script)"></div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- 4. PASTIKAN -->
                        <div class="p-4 border border-emerald-200 rounded-xl bg-emerald-50/30">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-5 h-5 bg-emerald-600 text-white rounded-full flex items-center justify-center font-bold text-[10px] shadow-2xs">4</span>
                                    <span class="font-bold text-xs text-emerald-950 uppercase tracking-wider">P - PASTIKAN (Arahkan Kembali ke Closing)</span>
                                </div>
                                <span class="text-[10px] text-emerald-700 italic">Kunci kembali keputusan</span>
                            </div>
                            <div class="space-y-2.5">
                                <template x-for="(pScript, idx) in (currentObjection.tgjp?.pastikan || [])" :key="idx">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between p-3 sm:p-3.5 bg-white border border-emerald-200/80 rounded-xl text-xs gap-2.5 shadow-2xs">
                                        <div class="text-zinc-800 leading-relaxed font-sans" x-text="renderScript(pScript)"></div>
                                        <button type="button" @click="copyText(renderScript(pScript), 'p-' + idx)"
                                            class="min-h-[36px] px-3.5 py-1.5 rounded-lg text-xs font-semibold shrink-0 transition flex items-center justify-center gap-1.5 self-end sm:self-center"
                                            :class="copiedScriptId === ('p-' + idx) ? 'bg-emerald-600 text-white' : 'bg-black hover:bg-zinc-800 text-white'">
                                            <svg x-show="copiedScriptId === ('p-' + idx)" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                            <span x-text="copiedScriptId === ('p-' + idx) ? 'Tersalin' : 'Salin'"></span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

    <!-- ============================================================== -->
    <!-- MODAL: CATAT / EDIT PROSPEK                                   -->
    <!-- ============================================================== -->
    <div x-show="showLeadModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/60 flex items-center justify-center p-4">
        <div @click.away="showLeadModal = false" class="bg-white border border-zinc-200 rounded-xl max-w-md w-full p-6 shadow-xl relative">
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-zinc-100">
                <h3 class="text-sm font-bold text-black uppercase tracking-wider" x-text="leadForm.id ? 'Edit Data Prospek' : 'Catat Prospek Baru'"></h3>
                <button type="button" @click="showLeadModal = false" class="text-zinc-400 hover:text-black text-lg font-bold">&times;</button>
            </div>

            <form @submit.prevent="saveLead()" class="space-y-3.5 text-xs">
                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Nama Calon Jamaah *</label>
                    <input type="text" x-model="leadForm.name" required
                        class="w-full px-3 py-2 border border-zinc-300 rounded focus:outline-none focus:border-black font-semibold text-zinc-900"
                        placeholder="Contoh: Bu Siti Rohmah">
                </div>

                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">No. WhatsApp / HP</label>
                    <input type="text" x-model="leadForm.phone"
                        class="w-full px-3 py-2 border border-zinc-300 rounded focus:outline-none focus:border-black font-mono"
                        placeholder="Contoh: 081234567890">
                </div>

                <!-- Custom Dropdown for Package -->
                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Paket yang Diminati</label>
                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                        <button type="button" @click="open = !open"
                                class="w-full flex items-center justify-between px-3 py-2 border border-zinc-300 bg-white rounded-xl text-xs font-semibold text-zinc-900 focus:outline-none focus:border-black shadow-xs">
                            <span x-text="getPackageLabel(leadForm.package_id)"></span>
                            <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="m6 9 6 6 6-6"/>
                            </svg>
                        </button>
                        <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                             class="absolute z-50 mt-1 w-full bg-white border border-zinc-200 rounded-xl shadow-xl max-h-56 overflow-y-auto py-1 text-xs divide-y divide-zinc-50">
                            <button type="button" @click="leadForm.package_id = ''; open = false"
                                    class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 text-zinc-500 italic">
                                <span>-- Belum Memilih Paket --</span>
                            </button>
                            <template x-for="pkg in packagesList" :key="pkg.id">
                                <button type="button" @click="leadForm.package_id = pkg.id; open = false"
                                        class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                        :class="leadForm.package_id == pkg.id ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                    <span x-text="pkg.name + ' (' + pkg.price + ')'"></span>
                                    <svg x-show="leadForm.package_id == pkg.id" class="w-3.5 h-3.5 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <!-- Custom Dropdown for Stage -->
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">Tahap Obrolan (SOP)</label>
                        <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                            <button type="button" @click="open = !open"
                                    class="w-full flex items-center justify-between px-3 py-2 border border-zinc-300 bg-white rounded-xl text-xs font-semibold text-zinc-900 focus:outline-none focus:border-black shadow-xs">
                                <span x-text="getStageLabel(leadForm.current_stage)"></span>
                                <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="m6 9 6 6 6-6"/>
                                </svg>
                            </button>
                            <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                                 class="absolute z-50 mt-1 w-full bg-white border border-zinc-200 rounded-xl shadow-xl max-h-56 overflow-y-auto py-1 text-xs divide-y divide-zinc-50">
                                <template x-for="stg in stageOptions" :key="stg.val">
                                    <button type="button" @click="leadForm.current_stage = stg.val; open = false"
                                            class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                            :class="leadForm.current_stage === stg.val ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                        <span x-text="stg.label"></span>
                                        <svg x-show="leadForm.current_stage === stg.val" class="w-3.5 h-3.5 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="20 6 9 17 4 12"/>
                                        </svg>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Dropdown for Status -->
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">Status Prospek</label>
                        <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                            <button type="button" @click="open = !open"
                                    class="w-full flex items-center justify-between px-3 py-2 border border-zinc-300 bg-white rounded-xl text-xs font-semibold text-zinc-900 focus:outline-none focus:border-black shadow-xs">
                                <span x-text="getStatusLabel(leadForm.status)"></span>
                                <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="m6 9 6 6 6-6"/>
                                </svg>
                            </button>
                            <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                                 class="absolute z-50 mt-1 w-full bg-white border border-zinc-200 rounded-xl shadow-xl max-h-56 overflow-y-auto py-1 text-xs divide-y divide-zinc-50">
                                <template x-for="st in statusOptions" :key="st.val">
                                    <button type="button" @click="leadForm.status = st.val; open = false"
                                            class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                            :class="leadForm.status === st.val ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                        <span x-text="st.label"></span>
                                        <svg x-show="leadForm.status === st.val" class="w-3.5 h-3.5 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="20 6 9 17 4 12"/>
                                        </svg>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>


                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Catatan Kebutuhan Jamaah (Framework NPGD)</label>
                    <textarea x-model="leadForm.notes" rows="2"
                        class="w-full px-3 py-2 border border-zinc-300 rounded focus:outline-none focus:border-black"
                        placeholder="Contoh: Ingin berangkat berdua suami, cari hotel dekat masjid, budget 30jt per orang"></textarea>
                </div>

                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Tanggal Follow-up Berikutnya</label>
                    <input type="date" x-model="leadForm.next_followup_date"
                        class="w-full px-3 py-2 border border-zinc-300 rounded focus:outline-none focus:border-black font-mono">
                </div>

                <div class="pt-4 flex items-center justify-end gap-2 border-t border-zinc-100">
                    <button type="button" @click="showLeadModal = false"
                        class="px-3.5 py-2 border border-zinc-300 text-zinc-700 hover:bg-zinc-50 rounded text-xs transition">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-black hover:bg-zinc-800 text-white rounded text-xs font-semibold transition inline-flex items-center gap-1.5">
                        <span>Simpan Prospek</span>
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- Alpine.js Application Logic -->
<script>
function copilotApp() {
    return {
        activeStage: <?= json_encode($loadedProspect && !empty($loadedProspect['current_stage']) ? $loadedProspect['current_stage'] : 'greeting') ?>,
        searchQuery: '',
        prospectName: <?= json_encode($loadedProspect && !empty($loadedProspect['name']) ? $loadedProspect['name'] : 'Kakak') ?>,
        prospectPhone: <?= json_encode($loadedProspect && !empty($loadedProspect['phone']) ? $loadedProspect['phone'] : '') ?>,
        csName: <?= json_encode($currentUser['name']) ?>,
        brand: <?= json_encode($brand) ?>,
        packagesList: <?= json_encode($packages) ?>,
        selectedPackageId: <?= json_encode($loadedProspect && !empty($loadedProspect['package_id']) ? (int)$loadedProspect['package_id'] : (!empty($packages[0]['id']) ? (int)$packages[0]['id'] : 0)) ?>,
        currentPkg: {},
        allScriptsData: <?= json_encode($rawScripts) ?>,
        selectedObjectionId: '',
        copiedScriptId: null,

        // Leads State for quick saving
        prospectsList: <?= json_encode($initialProspects) ?>,
        showLeadModal: false,
        leadForm: {
            id: null,
            name: '',
            phone: '',
            package_id: '',
            current_stage: 'greeting',
            status: 'new',
            notes: '',
            next_followup_date: ''
        },

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

        stageOptions: [
            { val: 'greeting', label: '1. Greeting' },
            { val: 'identification', label: '2. Identifikasi' },
            { val: 'offer', label: '3. Penawaran' },
            { val: 'closing', label: '4. Closing' },
            { val: 'objection', label: '5. Keberatan' },
            { val: 'followup', label: '6. Follow-up' }
        ],

        getStatusLabel(status) {
            const found = this.statusOptions.find(s => s.val === status);
            return found ? found.label : (status || 'Prospek Baru');
        },

        getStageLabel(stage) {
            const found = this.stageOptions.find(s => s.val === stage);
            return found ? found.label : (stage || '1. Greeting');
        },

        getPackageLabel(pkgId) {
            if (!pkgId) return '-- Belum Memilih Paket --';
            const found = this.packagesList.find(p => p.id == pkgId);
            return found ? (found.name + ' (' + found.price + ')') : '-- Belum Memilih Paket --';
        },

        init() {
            this.updatePackage();
            if (this.objectionScripts.length > 0) {
                this.selectedObjectionId = this.objectionScripts[0].id;
            }
            if (<?= json_encode(!empty($loadedProspect)) ?>) {
                window.showToast('Data prospek ' + <?= json_encode($loadedProspect['name'] ?? '') ?> + ' berhasil dimuat!');
            }
        },

        updatePackage() {
            const found = this.packagesList.find(p => parseInt(p.id) === parseInt(this.selectedPackageId));
            this.currentPkg = found || (this.packagesList[0] || {});
        },

        get currentStageDescription() {
            const map = {
                greeting: 'Menyapa ramah calon jamaah baru, memperkenalkan diri sebagai tim CS resmi travel, dan membangun rasa nyaman sebelum menawarkan paket.',
                identification: 'Menggali kebutuhan (Need), kendala/kekhawatiran (Pain), harapan (Gain), dan impian (Dream) calon jamaah melalui obrolan mengalir.',
                offer: 'Menyajikan rekomendasi paket yang paling pas dengan formula Match - Reason - Benefit - Value - Action (bukan sekadar sebar brosur).',
                closing: 'Meminta keputusan secara jelas dan amanah untuk mengamankan seat melalui minimal DP resmi travel.',
                followup: 'Menyapa kembali prospek yang berhenti membalas sesuai konteks percakapan terakhir agar tidak ada prospek menggantung.'
            };
            return map[this.activeStage] || '';
        },

        get filteredScripts() {
            const currentList = (this.allScriptsData[this.activeStage]?.scripts) || [];
            if (!this.searchQuery.trim()) {
                return currentList;
            }
            const q = this.searchQuery.toLowerCase();
            return currentList.filter(item => {
                const title = (item.title || '').toLowerCase();
                const script = (item.script || '').toLowerCase();
                const useWhen = (item.use_when || '').toLowerCase();
                const tags = (item.tags || []).join(' ').toLowerCase();
                return title.includes(q) || script.includes(q) || useWhen.includes(q) || tags.includes(q);
            });
        },

        get objectionScripts() {
            return (this.allScriptsData['objection']?.scripts) || [];
        },

        get currentObjection() {
            return this.objectionScripts.find(o => o.id === this.selectedObjectionId) || this.objectionScripts[0] || null;
        },

        get filteredProspects() {
            if (!this.statusFilter) {
                return this.prospectsList;
            }
            return this.prospectsList.filter(p => p.status === this.statusFilter);
        },

        getStatusBadgeClass(status) {
            switch (status) {
                case 'new': return 'bg-sky-50 text-sky-700 border border-sky-200 font-semibold';
                case 'identifying': return 'bg-indigo-50 text-indigo-700 border border-indigo-200 font-semibold';
                case 'offered': return 'bg-purple-50 text-purple-700 border border-purple-200 font-semibold';
                case 'closing': return 'bg-amber-50 text-amber-800 border border-amber-300 font-semibold';
                case 'objection': return 'bg-orange-50 text-orange-800 border border-orange-300 font-semibold';
                case 'followup': return 'bg-yellow-50 text-yellow-800 border border-yellow-300 font-semibold';
                case 'nurture': return 'bg-slate-100 text-slate-700 border border-slate-300 font-medium';
                case 'closed_won': return 'bg-emerald-50 text-emerald-800 border border-emerald-300 font-bold';
                case 'closed_lost': return 'bg-zinc-100 text-zinc-500 border border-zinc-200 line-through';
                default: return 'bg-zinc-100 text-zinc-700 border border-zinc-200 font-medium';
            }
        },

        renderScript(template) {
            if (!template) return '';
            let text = template;
            const pName = this.prospectName.trim() || 'Kakak';
            const cName = this.csName || 'Fitri';
            const bName = this.brand?.name || 'Travel Kami';
            const ppiu = this.brand?.ppiu_number || '-';
            const rekening = (this.brand?.bank_name || '') + ' No. ' + (this.brand?.bank_account_number || '');
            const namaRekening = this.brand?.bank_account_holder || '';
            const pkgName = this.currentPkg.name || 'Paket Umroh Reguler';
            const harga = this.currentPkg.price || 'Rp 28.000.000';
            const dp = this.currentPkg.dp || 'Rp 5.000.000';
            const hotel = (this.currentPkg.hotel_makkah || 'Hotel Makkah') + ' & ' + (this.currentPkg.hotel_madinah || 'Hotel Madinah');
            const airline = this.currentPkg.airline || 'Saudia / Garuda';
            const departure = this.currentPkg.departure_info || 'Bulan Depan';
            const duration = this.currentPkg.duration || '9 Hari';
            const highlights = this.currentPkg.highlights || 'Akomodasi & Fasilitas Lengkap';

            text = text.replaceAll('{{nama}}', pName);
            text = text.replaceAll('{{cs_name}}', cName);
            text = text.replaceAll('{{agent_name}}', cName);
            text = text.replaceAll('{{travel}}', bName);
            text = text.replaceAll('{{ppiu}}', ppiu);
            text = text.replaceAll('{{rekening}}', rekening);
            text = text.replaceAll('{{nama_rekening}}', namaRekening);
            text = text.replaceAll('{{paket}}', pkgName);
            text = text.replaceAll('{{harga}}', harga);
            text = text.replaceAll('{{dp}}', dp);
            text = text.replaceAll('{{hotel}}', hotel);
            text = text.replaceAll('{{maskapai}}', airline);
            text = text.replaceAll('{{bulan}}', departure);
            text = text.replaceAll('{{tanggal}}', departure);
            text = text.replaceAll('{{durasi}}', duration);
            text = text.replaceAll('{{fasilitas_utama}}', highlights);

            return text;
        },

        copyText(text, id = null) {
            if (window.copyToClipboard) {
                window.copyToClipboard(text, 'Pesan berhasil disalin!');
            }
            if (id) {
                this.copiedScriptId = id;
                setTimeout(() => {
                    if (this.copiedScriptId === id) {
                        this.copiedScriptId = null;
                    }
                }, 2000);
            }
        },

        openWhatsApp(text) {
            let phone = this.prospectPhone.replace(/[^0-9]/g, '');
            if (phone.startsWith('0')) {
                phone = '62' + phone.substring(1);
            }
            const url = `https://wa.me/${phone}?text=${encodeURIComponent(text)}`;
            window.open(url, '_blank');
        },

        // Leads Pipeline Methods
        openNewLeadModal() {
            this.leadForm = {
                id: null,
                name: '',
                phone: '',
                package_id: this.selectedPackageId,
                current_stage: this.activeStage,
                status: 'new',
                notes: '',
                next_followup_date: ''
            };
            this.showLeadModal = true;
        },

        openSaveLeadModal() {
            this.leadForm = {
                id: null,
                name: (this.prospectName !== 'Kakak') ? this.prospectName : '',
                phone: this.prospectPhone || '',
                package_id: this.selectedPackageId,
                current_stage: this.activeStage,
                status: (this.activeStage === 'closing') ? 'closing' : (this.activeStage === 'objection' ? 'objection' : 'new'),
                notes: '',
                next_followup_date: ''
            };
            this.showLeadModal = true;
        },

        editProspect(p) {
            this.leadForm = {
                id: p.id,
                name: p.name,
                phone: p.phone || '',
                package_id: p.package_id || '',
                current_stage: p.current_stage || 'greeting',
                status: p.status || 'new',
                notes: p.notes || '',
                next_followup_date: p.next_followup_date || ''
            };
            this.showLeadModal = true;
        },

        loadProspectToCopilot(p) {
            this.prospectName = p.name;
            this.prospectPhone = p.phone || '';
            if (p.package_id) {
                this.selectedPackageId = p.package_id;
                this.updatePackage();
            }
            if (p.current_stage) {
                this.activeStage = p.current_stage;
            }
            this.mainView = 'copilot';
            window.showToast(`Data ${p.name} berhasil dimuat ke Copilot!`);
        },

        async saveLead() {
            const formData = new FormData();
            formData.append('action', 'save');
            if (this.leadForm.id) formData.append('id', this.leadForm.id);
            formData.append('name', this.leadForm.name);
            formData.append('phone', this.leadForm.phone);
            formData.append('package_id', this.leadForm.package_id);
            formData.append('current_stage', this.leadForm.current_stage);
            formData.append('status', this.leadForm.status);
            formData.append('notes', this.leadForm.notes);
            formData.append('next_followup_date', this.leadForm.next_followup_date);
            formData.append('brand_id', this.brand.id);

            try {
                const res = await fetch('api/prospects.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();
                if (result.success) {
                    this.showLeadModal = false;
                    window.showToast(result.message);
                    this.refreshProspects();
                } else {
                    alert(result.error || 'Gagal menyimpan prospek.');
                }
            } catch (err) {
                alert('Terjadi kesalahan jaringan.');
            }
        },

        async updateProspectStatus(id, newStatus) {
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('id', id);
            formData.append('status', newStatus);

            try {
                const res = await fetch('api/prospects.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();
                if (result.success) {
                    const item = this.prospectsList.find(p => parseInt(p.id) === parseInt(id));
                    if (item) item.status = newStatus;
                    window.showToast('Status berhasil diubah!');
                } else {
                    alert(result.error);
                }
            } catch (err) {
                alert('Gagal update status.');
            }
        },

        async deleteProspect(id) {
            if (!confirm('Hapus prospek ini dari pipeline?')) return;
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);

            try {
                const res = await fetch('api/prospects.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();
                if (result.success) {
                    this.prospectsList = this.prospectsList.filter(p => parseInt(p.id) !== parseInt(id));
                    window.showToast('Prospek berhasil dihapus');
                } else {
                    alert(result.error);
                }
            } catch (err) {
                alert('Gagal menghapus.');
            }
        },

        async refreshProspects() {
            try {
                const res = await fetch(`api/prospects.php?action=list&brand_id=${this.brand.id}`);
                const result = await res.json();
                if (result.success) {
                    this.prospectsList = result.data;
                }
            } catch (err) {}
        }
    };
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
