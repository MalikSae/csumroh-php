<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_role('superadmin');

$db = get_db();
$message = '';
$error = '';

// Check flash messages from query string
if (isset($_GET['msg'])) {
    $pkgName = htmlspecialchars($_GET['name'] ?? 'Paket Umroh');
    if ($_GET['msg'] === 'created') {
        $message = "Paket umroh <strong>{$pkgName}</strong> berhasil diterbitkan.";
    } elseif ($_GET['msg'] === 'updated') {
        $message = "Perubahan paket umroh <strong>{$pkgName}</strong> berhasil disimpan.";
    } elseif ($_GET['msg'] === 'deleted') {
        $message = "Paket umroh berhasil dihapus.";
    }
}

// Handle POST actions (delete & toggle status)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM packages WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Paket umroh berhasil dihapus.";
    } elseif ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("UPDATE packages SET is_active = IF(is_active=1, 0, 1) WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Status keaktifan paket berhasil diubah.";
    }
}

// Fetch Brands for dropdown & filter
$brands = $db->query("SELECT id, name FROM brands ORDER BY id ASC")->fetchAll();

// Brand package counts for pills
$brandCounts = [];
$countsRaw = $db->query("SELECT brand_id, COUNT(*) as cnt FROM packages GROUP BY brand_id")->fetchAll();
foreach ($countsRaw as $cr) {
    $brandCounts[(int)$cr['brand_id']] = (int)$cr['cnt'];
}
$totalAllPackagesInDb = array_sum($brandCounts);

// Month list from departure_date
$monthList = $db->query("
    SELECT DISTINCT DATE_FORMAT(departure_date, '%Y-%m') AS ym 
    FROM packages 
    WHERE departure_date IS NOT NULL AND departure_date > '2000-01-01'
    ORDER BY ym ASC
")->fetchAll(PDO::FETCH_COLUMN);

$indoMonths = [
    '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
    '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Agu',
    '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'
];

// Active Filters
$filterBrand = (int)($_GET['brand_id'] ?? 0);
$filterMonth = trim($_GET['month'] ?? '');
$filterQuota = trim($_GET['quota'] ?? '');
$filterStatus = isset($_GET['status']) && $_GET['status'] !== '' ? (string)$_GET['status'] : '';
$searchQuery = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$conditions = [];
$params = [];

if ($filterBrand > 0) {
    $conditions[] = "p.brand_id = ?";
    $params[] = $filterBrand;
}

if ($searchQuery !== '') {
    $conditions[] = "(p.name LIKE ? OR p.airline LIKE ? OR b.name LIKE ? OR p.departure_info LIKE ?)";
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($filterMonth !== '') {
    $conditions[] = "DATE_FORMAT(p.departure_date, '%Y-%m') = ?";
    $params[] = $filterMonth;
}

if ($filterQuota === 'available') {
    $conditions[] = "p.quota_remaining > 0";
} elseif ($filterQuota === 'low') {
    $conditions[] = "p.quota_remaining > 0 AND p.quota_remaining <= 5";
} elseif ($filterQuota === 'sold_out') {
    $conditions[] = "(p.quota_remaining = 0 OR p.quota_remaining IS NULL)";
}

if ($filterStatus === '1') {
    $conditions[] = "p.is_active = 1";
} elseif ($filterStatus === '0') {
    $conditions[] = "p.is_active = 0";
}

$whereSql = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// Count total matching
$countSql = "
    SELECT COUNT(*) 
    FROM packages p
    JOIN brands b ON p.brand_id = b.id
    {$whereSql}
";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalPackages = (int)$countStmt->fetchColumn();

// Calculate pagination
$totalPages = max(1, (int)ceil($totalPackages / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// Fetch page data
$dataSql = "
    SELECT p.*, b.name AS brand_name 
    FROM packages p
    JOIN brands b ON p.brand_id = b.id
    {$whereSql}
    ORDER BY p.id DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $db->prepare($dataSql);
$stmt->execute($params);
$packages = $stmt->fetchAll();

$hasActiveFilters = ($filterBrand > 0 || $filterMonth !== '' || $filterQuota !== '' || $filterStatus !== '' || $searchQuery !== '');

if (!function_exists('get_package_filter_url')) {
    function get_package_filter_url(array $overrides = []): string {
        $params = [
            'brand_id' => $_GET['brand_id'] ?? null,
            'q' => $_GET['q'] ?? null,
            'month' => $_GET['month'] ?? null,
            'quota' => $_GET['quota'] ?? null,
            'status' => $_GET['status'] ?? null,
            'page' => $_GET['page'] ?? null,
        ];
        foreach ($overrides as $k => $v) {
            if ($v === null || $v === '') {
                unset($params[$k]);
            } else {
                $params[$k] = $v;
            }
        }
        $clean = array_filter($params, fn($val) => $val !== null && $val !== '');
        return 'packages.php' . (!empty($clean) ? '?' . http_build_query($clean) : '');
    }
}

$pageTitle = 'Kelola Paket Umroh - Super Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 sm:pb-6 border-b border-zinc-200 gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="index.php" class="text-xs text-zinc-500 hover:text-black inline-flex items-center gap-1 font-semibold transition">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    <span>Dashboard</span>
                </a>
                <span class="text-zinc-300">/</span>
                <span class="text-xs font-semibold text-black">Master Paket Umroh</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-black tracking-tight mt-1">Kelola Paket Umroh Multi-Brand</h1>
            <p class="text-xs text-zinc-500 mt-0.5">Paket yang aktif di sini otomatis menjadi pilihan di Copilot Chat dan Mini Lead Tracker CS.</p>
        </div>
        <div>
            <a href="package_form.php"
               class="px-4 py-2 bg-black hover:bg-zinc-800 text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center gap-2">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Tambah Paket Baru</span>
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($message)): ?>
        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-900 flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                <span><?= $message ?></span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-800 font-bold">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-900 font-semibold flex items-center justify-between shadow-2xs">
            <div><?= $error ?></div>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-800 font-bold">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Filter & Search Section -->
    <div class="space-y-3">
        <!-- 1. Brand Pills (Horizontal Swipeable) -->
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar flex-nowrap bg-zinc-50 p-2 sm:p-2.5 rounded-2xl border border-zinc-200 text-xs">
            <span class="font-semibold text-zinc-500 shrink-0 pl-1">Brand:</span>
            <a href="<?= get_package_filter_url(['brand_id' => null, 'page' => 1]) ?>" 
               class="px-3 py-1.5 rounded-xl shrink-0 whitespace-nowrap transition <?= $filterBrand === 0 ? 'bg-black text-white font-semibold shadow-2xs' : 'bg-white border border-zinc-200 text-zinc-700 hover:border-black' ?>">
                Semua Brand (<?= $totalAllPackagesInDb ?>)
            </a>
            <?php foreach ($brands as $b): ?>
                <?php $bCount = $brandCounts[(int)$b['id']] ?? 0; ?>
                <a href="<?= get_package_filter_url(['brand_id' => $b['id'], 'page' => 1]) ?>" 
                   class="px-3 py-1.5 rounded-xl shrink-0 whitespace-nowrap transition <?= $filterBrand === (int)$b['id'] ? 'bg-black text-white font-semibold shadow-2xs' : 'bg-white border border-zinc-200 text-zinc-700 hover:border-black' ?>">
                    <?= htmlspecialchars($b['name']) ?> (<?= $bCount ?>)
                </a>
            <?php endforeach; ?>
        </div>

        <!-- 2. Search & Dropdown Filters Bar -->
        <form method="GET" action="packages.php" class="flex flex-col md:flex-row items-stretch md:items-center gap-2.5 bg-white p-2.5 sm:p-3 rounded-2xl border border-zinc-200 shadow-2xs">
            <?php if ($filterBrand > 0): ?>
                <input type="hidden" name="brand_id" value="<?= $filterBrand ?>">
            <?php endif; ?>
            <input type="hidden" name="page" value="1">

            <!-- Search Column -->
            <div class="relative flex-1">
                <svg class="w-4 h-4 text-zinc-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" 
                       placeholder="Cari nama paket, maskapai..."
                       class="w-full h-10 pl-10 pr-9 bg-zinc-50/70 border border-zinc-200 rounded-xl text-xs text-black placeholder-zinc-400 focus:bg-white focus:outline-none focus:border-black transition font-medium">
                <?php if ($searchQuery !== ''): ?>
                    <a href="<?= get_package_filter_url(['q' => null, 'page' => 1]) ?>" 
                       title="Hapus pencarian"
                       class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 rounded-full bg-zinc-200 hover:bg-zinc-300 text-zinc-600 flex items-center justify-center text-[10px] font-bold">
                        &times;
                    </a>
                <?php endif; ?>
            </div>

            <!-- Granular Filter Controls (Bulan, Kuota, Status, Reset) -->
            <div class="flex items-center gap-2 overflow-x-auto no-scrollbar flex-nowrap shrink-0">
                <!-- Filter Bulan -->
                <select name="month" onchange="this.form.submit()" 
                        class="h-10 text-xs font-semibold rounded-xl border border-zinc-200 bg-zinc-50/70 hover:bg-white focus:bg-white px-3 text-zinc-800 focus:border-black focus:outline-none transition shadow-2xs cursor-pointer">
                    <option value="">Semua Bulan</option>
                    <?php foreach ($monthList as $ym): ?>
                        <?php 
                        $parts = explode('-', $ym);
                        $lbl = ($indoMonths[$parts[1]] ?? $parts[1]) . ' ' . $parts[0];
                        ?>
                        <option value="<?= $ym ?>" <?= $filterMonth === $ym ? 'selected' : '' ?>>
                            Bulan: <?= $lbl ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Filter Kuota -->
                <select name="quota" onchange="this.form.submit()" 
                        class="h-10 text-xs font-semibold rounded-xl border border-zinc-200 bg-zinc-50/70 hover:bg-white focus:bg-white px-3 text-zinc-800 focus:border-black focus:outline-none transition shadow-2xs cursor-pointer">
                    <option value="">Semua Kuota</option>
                    <option value="available" <?= $filterQuota === 'available' ? 'selected' : '' ?>>Tersedia (>0)</option>
                    <option value="low" <?= $filterQuota === 'low' ? 'selected' : '' ?>>Menipis (≤5)</option>
                    <option value="sold_out" <?= $filterQuota === 'sold_out' ? 'selected' : '' ?>>Habis (0)</option>
                </select>

                <!-- Filter Status -->
                <select name="status" onchange="this.form.submit()" 
                        class="h-10 text-xs font-semibold rounded-xl border border-zinc-200 bg-zinc-50/70 hover:bg-white focus:bg-white px-3 text-zinc-800 focus:border-black focus:outline-none transition shadow-2xs cursor-pointer">
                    <option value="">Semua Status</option>
                    <option value="1" <?= $filterStatus === '1' ? 'selected' : '' ?>>Status: Aktif</option>
                    <option value="0" <?= $filterStatus === '0' ? 'selected' : '' ?>>Status: Arsip</option>
                </select>

                <!-- Submit Button -->
                <button type="submit" class="h-10 px-3.5 bg-black hover:bg-zinc-800 text-white rounded-xl text-xs font-semibold transition shadow-2xs flex items-center gap-1.5 shrink-0">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <span class="hidden sm:inline">Cari</span>
                </button>

                <!-- Reset Filter Button -->
                <?php if ($hasActiveFilters): ?>
                    <a href="packages.php" 
                       class="h-10 px-3 rounded-xl border border-zinc-200 hover:border-black bg-white hover:bg-zinc-50 text-zinc-600 hover:text-black text-xs font-semibold flex items-center gap-1 transition shadow-2xs shrink-0"
                       title="Reset semua filter">
                        <svg class="w-3.5 h-3.5 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                        <span>Reset</span>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Packages Table -->
    <div class="bg-white border border-zinc-200/80 rounded-2xl overflow-hidden shadow-xs">
        <div class="overflow-x-auto min-h-[300px]">
            <table class="w-full text-left text-xs min-w-[720px]">
                <thead class="bg-zinc-50 border-b border-zinc-200 text-zinc-600 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="py-3.5 px-4 font-bold text-zinc-700">Paket Umroh</th>
                        <th class="py-3.5 px-4 font-bold text-zinc-700">Keberangkatan</th>
                        <th class="py-3.5 px-4 font-bold text-zinc-700">Harga (Quad)</th>
                        <th class="py-3.5 px-4 font-bold text-zinc-700 text-center">Sisa Kuota</th>
                        <th class="py-3.5 px-4 font-bold text-zinc-700 text-center">Status</th>
                        <th class="py-3.5 px-4 font-bold text-zinc-700 text-right w-16">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    <?php if (empty($packages)): ?>
                        <tr>
                            <td colspan="6" class="py-14 text-center text-zinc-400">
                                <div class="max-w-sm mx-auto space-y-2">
                                    <svg class="w-8 h-8 mx-auto text-zinc-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                    </svg>
                                    <p class="font-semibold text-zinc-700 text-sm">Tidak ada paket umroh yang ditemukan</p>
                                    <p class="text-xs text-zinc-400">
                                        <?= $hasActiveFilters ? 'Coba sesuaikan kata kunci pencarian atau ubah pengaturan filter.' : 'Belum ada paket umroh yang tersimpan.' ?>
                                    </p>
                                    <?php if ($hasActiveFilters): ?>
                                        <div class="pt-1">
                                            <a href="packages.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-black text-white rounded-xl font-semibold hover:bg-zinc-800 text-xs">
                                                <span>Reset Semua Filter</span>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="pt-1">
                                            <a href="package_form.php" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-black text-white rounded-xl font-semibold hover:bg-zinc-800">
                                                <span>+ Tambah Paket Baru</span>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($packages as $p): ?>
                        <?php
                        // Format departure date
                        $jadwalText = $p['departure_info'] ?: '-';
                        if (!empty($p['departure_date'])) {
                            $jadwalText = date('d M Y', strtotime($p['departure_date']));
                        }
                        ?>
                        <tr class="hover:bg-zinc-50/70 transition">
                            <!-- Paket Umroh & Brand -->
                            <td class="py-4 px-4 align-middle">
                                <div class="space-y-0.5">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <a href="package_detail.php?id=<?= $p['id'] ?>" 
                                           class="font-bold text-black text-sm hover:underline tracking-tight">
                                            <?= htmlspecialchars($p['name']) ?>
                                        </a>
                                        <?php if (!empty($p['is_promo'])): ?>
                                            <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-50 border border-amber-300 text-amber-900">
                                                Promo
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-[11px] font-semibold text-zinc-500">
                                        <?= htmlspecialchars($p['brand_name']) ?>
                                    </div>
                                </div>
                            </td>

                            <!-- Keberangkatan (Tanggal & Durasi) -->
                            <td class="py-4 px-4 align-middle whitespace-nowrap">
                                <div class="font-bold text-black text-xs"><?= htmlspecialchars($jadwalText) ?></div>
                                <div class="text-[11px] text-zinc-500 font-medium"><?= htmlspecialchars($p['duration'] ?: '-') ?></div>
                            </td>

                            <!-- Harga (Quad) -->
                            <td class="py-4 px-4 align-middle whitespace-nowrap">
                                <div class="font-extrabold text-black text-sm"><?= htmlspecialchars($p['price_quad'] ?: $p['price']) ?></div>
                            </td>

                            <!-- Sisa Kuota -->
                            <td class="py-4 px-4 align-middle text-center whitespace-nowrap">
                                <?php if ($p['quota_remaining'] !== null && $p['quota_remaining'] !== ''): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold <?= (int)$p['quota_remaining'] > 5 ? 'bg-zinc-100 text-zinc-800' : 'bg-rose-50 text-rose-800 border border-rose-200' ?>">
                                        <?= (int)$p['quota_remaining'] ?> Seat
                                    </span>
                                <?php else: ?>
                                    <span class="text-zinc-400 text-xs">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- Status -->
                            <td class="py-4 px-4 align-middle text-center whitespace-nowrap">
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold <?= $p['is_active'] ? 'bg-black text-white' : 'bg-zinc-100 text-zinc-500' ?>">
                                    <?= $p['is_active'] ? 'Aktif' : 'Arsip' ?>
                                </span>
                            </td>

                            <!-- Aksi (Satu Icon Trigger dengan Dropdown: Lihat, Edit, Arsipkan) -->
                            <td class="py-4 px-4 align-middle text-right whitespace-nowrap">
                                <div class="relative inline-block text-left" x-data="{ open: false }">
                                    <button type="button" @click="open = !open" @click.outside="open = false" 
                                            title="Opsi Paket"
                                            class="w-8 h-8 rounded-xl border border-zinc-200 hover:border-black bg-white hover:bg-zinc-50 text-zinc-600 hover:text-black transition flex items-center justify-center shadow-2xs">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="1.5"></circle>
                                            <circle cx="19" cy="12" r="1.5"></circle>
                                            <circle cx="5" cy="12" r="1.5"></circle>
                                        </svg>
                                    </button>

                                    <!-- Dropdown Menu -->
                                    <div x-show="open" x-transition.origin.top.right x-cloak
                                         class="absolute right-0 top-full mt-1.5 w-36 bg-white border border-zinc-200 rounded-xl shadow-xl z-30 py-1 text-xs text-left">
                                        
                                        <!-- 1. Lihat -->
                                        <a href="package_detail.php?id=<?= $p['id'] ?>" 
                                           class="w-full px-3.5 py-2 hover:bg-zinc-100 flex items-center gap-2 text-zinc-700 hover:text-black font-semibold transition">
                                            <svg class="w-3.5 h-3.5 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                            <span>Lihat</span>
                                        </a>

                                        <!-- 2. Edit -->
                                        <a href="package_form.php?id=<?= $p['id'] ?>" 
                                           class="w-full px-3.5 py-2 hover:bg-zinc-100 flex items-center gap-2 text-zinc-700 hover:text-black font-semibold transition">
                                            <svg class="w-3.5 h-3.5 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                                            <span>Edit</span>
                                        </a>

                                        <div class="h-px bg-zinc-100 my-1"></div>

                                        <!-- 3. Arsipkan / Aktifkan -->
                                        <form method="POST" action="packages.php">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button type="submit" 
                                                    class="w-full px-3.5 py-2 hover:bg-zinc-100 flex items-center gap-2 text-zinc-700 hover:text-black font-semibold transition">
                                                <?php if ($p['is_active']): ?>
                                                    <svg class="w-3.5 h-3.5 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                                                    <span>Arsipkan</span>
                                                <?php else: ?>
                                                    <svg class="w-3.5 h-3.5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                                    <span>Aktifkan</span>
                                                <?php endif; ?>
                                            </button>
                                        </form>

                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Table Footer / Pagination -->
        <?php if ($totalPackages > $perPage): ?>
            <div class="p-3.5 sm:p-4 bg-white border-t border-zinc-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                <div class="text-zinc-500 font-medium text-center sm:text-left">
                    Menampilkan <strong class="text-black font-bold"><?= $offset + 1 ?></strong> – 
                    <strong class="text-black font-bold"><?= min($totalPackages, $offset + $perPage) ?></strong> 
                    dari <strong class="text-black font-bold"><?= $totalPackages ?></strong> paket
                </div>
                <div class="flex items-center justify-center gap-1">
                    <!-- Prev button -->
                    <?php if ($page > 1): ?>
                        <a href="<?= get_package_filter_url(['page' => $page - 1]) ?>" 
                           class="h-8 px-2.5 rounded-xl border border-zinc-200 bg-white hover:border-black text-zinc-700 hover:text-black font-semibold flex items-center gap-1 transition shadow-2xs">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                            <span>Prev</span>
                        </a>
                    <?php else: ?>
                        <span class="h-8 px-2.5 rounded-xl border border-zinc-100 bg-zinc-50 text-zinc-300 font-semibold flex items-center gap-1 cursor-not-allowed">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                            <span>Prev</span>
                        </span>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <?php
                    $startP = max(1, $page - 2);
                    $endP = min($totalPages, $page + 2);
                    if ($startP > 1): ?>
                        <a href="<?= get_package_filter_url(['page' => 1]) ?>" 
                           class="h-8 w-8 rounded-xl border border-zinc-200 bg-white hover:border-black text-zinc-700 hover:text-black font-semibold flex items-center justify-center transition shadow-2xs">
                            1
                        </a>
                        <?php if ($startP > 2): ?>
                            <span class="px-1 text-zinc-400">...</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $startP; $i <= $endP; $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="h-8 w-8 rounded-xl bg-black text-white font-bold flex items-center justify-center shadow-2xs">
                                <?= $i ?>
                            </span>
                        <?php else: ?>
                            <a href="<?= get_package_filter_url(['page' => $i]) ?>" 
                               class="h-8 w-8 rounded-xl border border-zinc-200 bg-white hover:border-black text-zinc-700 hover:text-black font-semibold flex items-center justify-center transition shadow-2xs">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($endP < $totalPages): ?>
                        <?php if ($endP < $totalPages - 1): ?>
                            <span class="px-1 text-zinc-400">...</span>
                        <?php endif; ?>
                        <a href="<?= get_package_filter_url(['page' => $totalPages]) ?>" 
                           class="h-8 w-8 rounded-xl border border-zinc-200 bg-white hover:border-black text-zinc-700 hover:text-black font-semibold flex items-center justify-center transition shadow-2xs">
                            <?= $totalPages ?>
                        </a>
                    <?php endif; ?>

                    <!-- Next button -->
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= get_package_filter_url(['page' => $page + 1]) ?>" 
                           class="h-8 px-2.5 rounded-xl border border-zinc-200 bg-white hover:border-black text-zinc-700 hover:text-black font-semibold flex items-center gap-1 transition shadow-2xs">
                            <span>Next</span>
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                        </a>
                    <?php else: ?>
                        <span class="h-8 px-2.5 rounded-xl border border-zinc-100 bg-zinc-50 text-zinc-300 font-semibold flex items-center gap-1 cursor-not-allowed">
                            <span>Next</span>
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($totalPackages > 0): ?>
            <div class="py-3 px-4 bg-zinc-50/60 border-t border-zinc-200/80 flex items-center justify-between text-[11px] text-zinc-500 font-medium">
                <span>Total <strong><?= $totalPackages ?></strong> paket umroh <?= $hasActiveFilters ? 'ditemukan' : 'tercatat' ?></span>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
