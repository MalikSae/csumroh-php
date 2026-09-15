<?php
$pageTitle = 'Data Prospek & Pipeline - CS Umroh';
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

// Fetch active packages for this brand (for modal dropdown)
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
?>

<div class="max-w-7xl mx-auto space-y-6" x-data="prospectsPage()">

    <!-- Top Welcome Header (Matching Reference SaaS style) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-black tracking-tight">
                Data Prospek & Pipeline
            </h1>
            <p class="text-xs text-zinc-500 mt-1">
                Kelola pipeline konversi calon jamaah dari tahap prospek baru hingga closing DP.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <!-- Super Admin Custom Dropdown Brand Switcher -->
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

            <button type="button" @click="openNewLeadModal()"
                class="px-4 py-2 bg-black hover:bg-zinc-800 text-white text-xs font-semibold rounded-xl transition shadow-sm flex items-center gap-2">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <span>Catat Prospek Baru</span>
            </button>
        </div>
    </div>

    <!-- Quick Overview Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 sm:gap-5">
        <!-- Featured Black Card: Active Brand & Total Prospects -->
        <div class="p-4 sm:p-6 bg-zinc-950 text-white border border-zinc-800 rounded-2xl shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-400">Total Prospek Jamaah</span>
                <div class="w-8 h-8 rounded-xl bg-zinc-800 text-white flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white" x-text="prospectsList.length + ' Jamaah'"></div>
                <div class="text-[11px] text-zinc-400 mt-1 flex items-center gap-1.5">
                    <span class="text-amber-400 font-medium"><?= htmlspecialchars($brand['name']) ?></span>
                    <span>&bull;</span>
                    <span>Tercatat</span>
                </div>
            </div>
        </div>

        <!-- Card 2: Proses Closing & Penawaran -->
        <div class="p-4 sm:p-6 bg-white border border-zinc-200/80 rounded-2xl shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-500">Dalam Proses (Penawaran / Closing)</span>
                <div class="w-8 h-8 rounded-xl border border-zinc-200 bg-zinc-50 text-zinc-700 flex items-center justify-center">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-black" 
                     x-text="prospectsList.filter(p => ['closing', 'offered', 'followup', 'objection'].includes(p.status)).length + ' Jamaah'"></div>
                <div class="text-[11px] text-zinc-400 mt-1">Aktif berkomunikasi di Copilot</div>
            </div>
        </div>

        <!-- Card 3: Closing Won (DP Masuk) -->
        <div class="p-4 sm:p-6 bg-white border border-zinc-200/80 rounded-2xl shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-500">Closing Won (DP Masuk)</span>
                <div class="w-8 h-8 rounded-xl border border-zinc-200 bg-zinc-50 text-zinc-700 flex items-center justify-center">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-black" 
                     x-text="prospectsList.filter(p => p.status === 'closed_won').length + ' Berhasil'"></div>
                <div class="text-[11px] text-zinc-400 mt-1">Calon jamaah terkonfirmasi booking</div>
            </div>
        </div>
    </div>

    <!-- Filter Status Bar & Search Input -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 bg-white p-3 sm:p-3.5 border border-zinc-200/80 rounded-2xl shadow-xs">
        <div class="flex items-center gap-1.5 text-xs overflow-x-auto no-scrollbar flex-nowrap pb-1 md:pb-0">
            <button type="button" @click="statusFilter = ''"
                :class="statusFilter === '' ? 'bg-black text-white font-semibold shadow-xs' : 'bg-white border border-zinc-200 text-zinc-700 hover:border-black'"
                class="px-3 py-1.5 rounded-xl transition text-xs font-medium shrink-0 whitespace-nowrap">
                Semua (<span x-text="prospectsList.length"></span>)
            </button>
            <button type="button" @click="statusFilter = 'new'"
                :class="statusFilter === 'new' ? 'bg-sky-600 text-white font-semibold shadow-xs' : 'bg-sky-50 text-sky-700 border border-sky-200 hover:bg-sky-100'"
                class="px-3 py-1.5 rounded-xl transition text-xs font-medium shrink-0 whitespace-nowrap">
                Baru
            </button>
            <button type="button" @click="statusFilter = 'identifying'"
                :class="statusFilter === 'identifying' ? 'bg-indigo-600 text-white font-semibold shadow-xs' : 'bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100'"
                class="px-3 py-1.5 rounded-xl transition text-xs font-medium shrink-0 whitespace-nowrap">
                Identifikasi
            </button>
            <button type="button" @click="statusFilter = 'offered'"
                :class="statusFilter === 'offered' ? 'bg-purple-600 text-white font-semibold shadow-xs' : 'bg-purple-50 text-purple-700 border border-purple-200 hover:bg-purple-100'"
                class="px-3 py-1.5 rounded-xl transition text-xs font-medium shrink-0 whitespace-nowrap">
                Ditawarkan
            </button>
            <button type="button" @click="statusFilter = 'objection'"
                :class="statusFilter === 'objection' ? 'bg-orange-600 text-white font-semibold shadow-xs' : 'bg-orange-50 text-orange-800 border border-orange-300 hover:bg-orange-100'"
                class="px-3 py-1.5 rounded-xl transition text-xs font-medium shrink-0 whitespace-nowrap">
                Keberatan
            </button>
            <button type="button" @click="statusFilter = 'closing'"
                :class="statusFilter === 'closing' ? 'bg-amber-600 text-white font-semibold shadow-xs' : 'bg-amber-50 text-amber-800 border border-amber-300 hover:bg-amber-100'"
                class="px-3 py-1.5 rounded-xl transition text-xs font-medium shrink-0 whitespace-nowrap">
                Proses Closing
            </button>
            <button type="button" @click="statusFilter = 'followup'"
                :class="statusFilter === 'followup' ? 'bg-yellow-600 text-white font-semibold shadow-xs' : 'bg-yellow-50 text-yellow-800 border border-yellow-300 hover:bg-yellow-100'"
                class="px-3 py-1.5 rounded-xl transition text-xs font-medium shrink-0 whitespace-nowrap">
                Follow-up
            </button>
            <button type="button" @click="statusFilter = 'closed_won'"
                :class="statusFilter === 'closed_won' ? 'bg-emerald-600 text-white font-semibold shadow-xs' : 'bg-emerald-50 text-emerald-800 border border-emerald-300 hover:bg-emerald-100'"
                class="px-3 py-1.5 rounded-xl transition text-xs font-medium shrink-0 whitespace-nowrap">
                Closing (Won)
            </button>
            <button type="button" @click="statusFilter = 'nurture'"
                :class="statusFilter === 'nurture' ? 'bg-slate-700 text-white font-semibold shadow-xs' : 'bg-slate-100 text-slate-700 border border-slate-300 hover:bg-slate-200'"
                class="px-3 py-1.5 rounded-xl transition text-xs font-medium shrink-0 whitespace-nowrap">
                Nurture
            </button>
            <button type="button" @click="statusFilter = 'closed_lost'"
                :class="statusFilter === 'closed_lost' ? 'bg-zinc-700 text-white font-semibold shadow-xs' : 'bg-zinc-100 text-zinc-500 border border-zinc-200 hover:bg-zinc-200'"
                class="px-3 py-1.5 rounded-xl transition text-xs font-medium shrink-0 whitespace-nowrap">
                Batal (Lost)
            </button>
        </div>

        <div class="relative w-full md:w-64 shrink-0">
            <svg class="w-3.5 h-3.5 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            <input type="text" x-model="searchQuery" placeholder="Cari nama / nomor WA..."
                   class="w-full pl-9 pr-3 py-2 bg-zinc-50 border border-zinc-200 rounded-xl text-xs text-zinc-800 placeholder-zinc-400 focus:outline-none focus:border-black focus:bg-white transition">
        </div>
    </div>

    <!-- Prospects Table (Streamlined SaaS Layout) -->
    <div class="bg-white border border-zinc-200/80 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs min-w-[680px]">
                <thead class="bg-zinc-50/80 border-b border-zinc-200 text-zinc-500 uppercase tracking-wider font-semibold text-[11px]">
                    <tr>
                        <th class="py-4 px-5">Nama Jamaah & Kontak</th>
                        <th class="py-4 px-5">Paket Minat</th>
                        <th class="py-4 px-5">Status Pipeline</th>
                        <th class="py-4 px-5">Riwayat Waktu</th>
                        <th class="py-4 px-5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <template x-for="p in filteredProspects" :key="p.id">
                        <tr class="hover:bg-zinc-50/60 transition">
                            <!-- Jamaah & Phone -->
                            <td class="py-4 px-5 align-top">
                                <a :href="'prospect_detail.php?id=' + p.id"
                                   class="font-bold text-black text-sm hover:underline inline-flex items-center gap-1.5 group">
                                    <span x-text="p.name"></span>
                                    <svg class="w-3.5 h-3.5 text-zinc-400 group-hover:text-black transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="m9 18 6-6-6-6"/>
                                    </svg>
                                </a>
                                <div class="flex items-center gap-2 mt-1">
                                    <template x-if="p.phone">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-mono text-zinc-800 font-semibold text-xs" x-text="formatIndoPhone(p.phone)"></span>
                                            <a :href="'https://wa.me/' + (p.phone.startsWith('0') ? '62' + p.phone.substring(1) : p.phone)" target="_blank" 
                                               class="text-[10px] text-zinc-800 hover:bg-zinc-100 font-semibold flex items-center gap-1 bg-white border border-zinc-200 px-2 py-0.5 rounded transition shadow-2xs">
                                                <svg class="w-3 h-3 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="m3 21 1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"></path>
                                                </svg>
                                                <span>Chat WA</span>
                                            </a>
                                        </div>
                                    </template>
                                    <template x-if="!p.phone">
                                        <span class="font-sans text-zinc-400 text-[11px] italic">Tanpa no. WA (Lead Iklan)</span>
                                    </template>
                                </div>
                            </td>

                            <!-- Paket Minat -->
                            <td class="py-4 px-5 align-top">
                                <div class="font-semibold text-zinc-900" x-text="p.package_name || '- Belum dipilih -'"></div>
                                <div class="text-[11px] text-zinc-400 font-mono mt-0.5" x-text="p.package_price || ''"></div>
                            </td>

                            <!-- Status & Stage -->
                            <td class="py-4 px-5 align-top">
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-semibold"
                                      :class="getStatusBadgeClass(p.status)"
                                      x-text="getStatusLabel(p.status)"></span>
                                <div class="text-[10px] text-zinc-400 uppercase tracking-wider mt-1.5">
                                    Tahap: <span class="font-semibold text-zinc-700 capitalize" x-text="p.current_stage"></span>
                                </div>
                            </td>

                            <!-- Riwayat Waktu -->
                            <td class="py-4 px-5 align-top space-y-1 text-[11px]">
                                <div class="text-zinc-500">
                                    Masuk: <span class="font-mono text-zinc-700 font-medium" x-text="formatDateShort(p.created_at)"></span>
                                </div>
                                <div class="text-zinc-500">
                                    Follow-up: <span class="font-mono font-medium text-zinc-800" x-text="p.last_followup_at ? formatDateShort(p.last_followup_at) : 'Belum pernah'"></span>
                                </div>
                                <template x-if="p.next_followup_date">
                                    <div class="text-amber-800 font-medium">
                                        Next: <span class="font-mono" x-text="p.next_followup_date"></span>
                                    </div>
                                </template>
                            </td>

                            <!-- Aksi -->
                            <td class="py-4 px-5 text-right align-top space-x-2 whitespace-nowrap">
                                <!-- Detail & Riwayat Button -->
                                <a :href="'prospect_detail.php?id=' + p.id"
                                   class="px-2.5 py-1.5 bg-white border border-zinc-300 hover:border-black text-zinc-800 font-semibold rounded-lg text-xs transition inline-flex items-center gap-1.5 shadow-2xs">
                                    <svg class="w-3.5 h-3.5 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                                    </svg>
                                    <span>Riwayat & Detail</span>
                                </a>

                                <!-- Load into Copilot -->
                                <a :href="'index.php?load_prospect=' + p.id" 
                                   class="px-2.5 py-1.5 bg-black hover:bg-zinc-800 text-white font-semibold rounded-lg text-xs transition inline-flex items-center gap-1.5 shadow-2xs">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="9 18 15 12 9 6"></polyline>
                                    </svg>
                                    <span>Copilot</span>
                                </a>

                                <!-- Edit / Delete -->
                                <button type="button" @click="editProspect(p)" class="text-xs text-zinc-500 hover:text-black font-medium">
                                    Edit
                                </button>
                                <button type="button" @click="deleteProspect(p.id)" class="text-xs text-zinc-400 hover:text-rose-600">
                                    Hapus
                                </button>
                            </td>
                        </tr>
                    </template>

                    <template x-if="filteredProspects.length === 0">
                        <tr>
                            <td colspan="5" class="py-12 text-center text-zinc-400">
                                Belum ada prospek pada filter ini. Klik <strong>+ Catat Prospek Baru</strong> di atas.
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL: CATAT / EDIT PROSPEK (Full Custom Dropdown) -->
    <div x-show="showLeadModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/60 flex items-center justify-center p-4">
        <div @click.away="showLeadModal = false" class="bg-white border border-zinc-200 rounded-2xl max-w-md w-full p-6 shadow-xl relative max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-zinc-100">
                <h3 class="text-sm font-bold text-black uppercase tracking-wider" x-text="leadForm.id ? 'Edit Data Prospek' : 'Catat Prospek Baru'"></h3>
                <button type="button" @click="showLeadModal = false" class="text-zinc-400 hover:text-black text-lg font-bold">&times;</button>
            </div>

            <form @submit.prevent="saveLead()" class="space-y-3.5 text-xs">
                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Nama Calon Jamaah *</label>
                    <input type="text" x-model="leadForm.name" required
                        class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black font-semibold text-zinc-900"
                        placeholder="Contoh: Bu Siti Rohmah">
                </div>

                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">No. WhatsApp / HP</label>
                    <input type="text" x-model="leadForm.phone"
                        class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black font-mono"
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
                        class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black text-xs"
                        placeholder="Contoh: Ingin berangkat berdua suami, cari hotel dekat masjid, budget 30jt per orang"></textarea>
                </div>

                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Tanggal Follow-up Berikutnya</label>
                    <input type="date" x-model="leadForm.next_followup_date"
                        class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black font-mono text-xs">
                </div>

                <div class="pt-4 flex items-center justify-end gap-2 border-t border-zinc-100">
                    <button type="button" @click="showLeadModal = false"
                        class="px-3.5 py-2 border border-zinc-300 text-zinc-700 hover:bg-zinc-50 rounded-xl text-xs transition">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-black hover:bg-zinc-800 text-white rounded-xl text-xs font-semibold transition inline-flex items-center gap-1.5 shadow-xs">
                        <span>Simpan Prospek</span>
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function prospectsPage() {
    return {
        prospectsList: <?= json_encode($initialProspects) ?>,
        packagesList: <?= json_encode($packages) ?>,
        statusFilter: '',
        searchQuery: '',
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

        get filteredProspects() {
            let list = this.prospectsList;
            if (this.statusFilter) {
                list = list.filter(p => p.status === this.statusFilter);
            }
            if (this.searchQuery.trim()) {
                const q = this.searchQuery.toLowerCase();
                list = list.filter(p => 
                    (p.name && p.name.toLowerCase().includes(q)) || 
                    (p.phone && p.phone.includes(q)) ||
                    (p.package_name && p.package_name.toLowerCase().includes(q))
                );
            }
            return list;
        },

        getStatusLabel(status) {
            const found = this.statusOptions.find(s => s.val === status);
            return found ? found.label : status;
        },

        getStageLabel(stage) {
            const found = this.stageOptions.find(s => s.val === stage);
            return found ? found.label : stage;
        },

        getPackageLabel(pkgId) {
            if (!pkgId) return '-- Belum Memilih Paket --';
            const found = this.packagesList.find(p => p.id == pkgId);
            return found ? (found.name + ' (' + found.price + ')') : '-- Belum Memilih Paket --';
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

        formatDateShort(dateStr) {
            if (!dateStr) return '-';
            try {
                const d = new Date(dateStr);
                return d.toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric'
                });
            } catch (e) {
                return dateStr;
            }
        },

        formatIndoPhone(phone) {
            if (!phone) return '';
            let p = phone.toString().replace(/[^0-9]/g, '');
            if (p.startsWith('62')) {
                p = '0' + p.substring(2);
            }
            if (p.length === 10) return `${p.slice(0, 4)}-${p.slice(4, 7)}-${p.slice(7)}`;
            if (p.length === 11) return `${p.slice(0, 4)}-${p.slice(4, 7)}-${p.slice(7)}`;
            if (p.length === 12) return `${p.slice(0, 4)}-${p.slice(4, 8)}-${p.slice(8)}`;
            if (p.length >= 13) return `${p.slice(0, 4)}-${p.slice(4, 8)}-${p.slice(8)}`;
            return p;
        },

        openNewLeadModal() {
            this.leadForm = {
                id: null,
                name: '',
                phone: '',
                package_id: '',
                current_stage: 'greeting',
                status: 'new',
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

        async saveLead() {
            const payload = {
                action: this.leadForm.id ? 'update' : 'create',
                ...this.leadForm,
                brand_id: <?= (int)$brand['id'] ?>
            };

            try {
                const res = await fetch('api/prospects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    window.showToast(data.message || 'Data prospek berhasil disimpan!');
                    this.showLeadModal = false;
                    await this.reloadProspects();
                } else {
                    alert(data.message || data.error || 'Gagal menyimpan prospek');
                }
            } catch (err) {
                console.error(err);
                alert('Terjadi kesalahan jaringan.');
            }
        },

        async deleteProspect(id) {
            if (!confirm('Hapus data prospek ini?')) return;
            try {
                const res = await fetch('api/prospects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete',
                        id: id
                    })
                });
                const data = await res.json();
                if (data.success) {
                    window.showToast('Data prospek berhasil dihapus');
                    this.prospectsList = this.prospectsList.filter(p => p.id != id);
                }
            } catch (err) {
                console.error(err);
            }
        },

        async reloadProspects() {
            try {
                const res = await fetch('api/prospects.php?brand_id=<?= (int)$brand['id'] ?>&action=list');
                const data = await res.json();
                if (data.success) {
                    this.prospectsList = data.data;
                }
            } catch (err) {
                console.error(err);
            }
        }
    };
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
