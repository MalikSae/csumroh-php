<?php
$pageTitle = 'Dashboard Super Admin - CS Umroh';
require_once __DIR__ . '/../includes/header.php';
require_role('superadmin');

$db = get_db();

// Metrics
$totalBrands = (int)$db->query("SELECT COUNT(*) FROM brands")->fetchColumn();
$totalPackages = (int)$db->query("SELECT COUNT(*) FROM packages WHERE is_active = 1")->fetchColumn();
$totalCS = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'cs' AND is_active = 1")->fetchColumn();
$totalProspects = (int)$db->query("SELECT COUNT(*) FROM prospects")->fetchColumn();

// Fetch 5 Brands with stats
$brands = $db->query("
    SELECT b.*, 
           COUNT(DISTINCT p.id) AS package_count,
           COUNT(DISTINCT u.id) AS cs_count
    FROM brands b
    LEFT JOIN packages p ON b.id = p.brand_id AND p.is_active = 1
    LEFT JOIN users u ON b.id = u.brand_id AND u.role = 'cs' AND u.is_active = 1
    GROUP BY b.id
    ORDER BY b.id ASC
")->fetchAll();
?>

<div class="max-w-7xl mx-auto space-y-8">
    
    <!-- Title & Overview matching reference "Welcome back, Cooper!" -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-black tracking-tight">
                Welcome back, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>!
            </h1>
            <p class="text-xs text-zinc-500 mt-1">
                Here's your current multi-brand system overview and operational status today.
            </p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="<?= $baseUrl ?>/index.php" 
               class="px-4 py-2 bg-black text-white text-xs font-semibold rounded-xl hover:bg-zinc-800 transition flex items-center gap-2 shadow-sm">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <span>Masuk ke CS Workspace</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                </svg>
            </a>
        </div>
    </div>

    <!-- Metrics Cards (Reference SaaS Style: 1 Solid Black + 3 Crisp White) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        
        <!-- Featured Black Card: Brand Travel -->
        <div class="p-6 bg-zinc-950 text-white border border-zinc-800 rounded-2xl shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-400">Total Brand Travel</span>
                <div class="w-8 h-8 rounded-xl bg-zinc-800 text-white flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"></path>
                        <path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path>
                        <path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-extrabold tracking-tight text-white"><?= $totalBrands ?> Brand</div>
                <div class="text-[11px] text-zinc-400 mt-1 flex items-center gap-1.5">
                    <span class="text-amber-400 font-medium">100% PPIU Aktif</span>
                    <span>&bull;</span>
                    <span>Multi-brand</span>
                </div>
            </div>
        </div>

        <!-- Card 2: Paket Umroh Aktif -->
        <div class="p-6 bg-white border border-zinc-200/80 rounded-2xl shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-500">Paket Umroh Aktif</span>
                <div class="w-8 h-8 rounded-xl border border-zinc-200 bg-zinc-50 text-zinc-700 flex items-center justify-center">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m7.5 4.27 9 5.15"></path>
                        <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-extrabold tracking-tight text-black"><?= $totalPackages ?> Paket</div>
                <div class="text-[11px] text-zinc-400 mt-1">Siap ditawarkan CS</div>
            </div>
        </div>

        <!-- Card 3: Akun CS Bertugas -->
        <div class="p-6 bg-white border border-zinc-200/80 rounded-2xl shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-500">Akun CS Bertugas</span>
                <div class="w-8 h-8 rounded-xl border border-zinc-200 bg-zinc-50 text-zinc-700 flex items-center justify-center">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-extrabold tracking-tight text-black"><?= $totalCS ?> CS</div>
                <div class="text-[11px] text-zinc-400 mt-1">Terbagi di 5 brand travel</div>
            </div>
        </div>

        <!-- Card 4: Total Prospek Jamaah -->
        <div class="p-6 bg-white border border-zinc-200/80 rounded-2xl shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-500">Total Prospek Masuk</span>
                <div class="w-8 h-8 rounded-xl border border-zinc-200 bg-zinc-50 text-zinc-700 flex items-center justify-center">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-extrabold tracking-tight text-black"><?= $totalProspects ?> Prospek</div>
                <div class="text-[11px] text-zinc-400 mt-1">Tercatat di pipeline konversi</div>
            </div>
        </div>

    </div>

    <!-- Quick Navigation Panels (Clean Monochrome & Pure SVG Icons) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <a href="brands.php" class="p-6 bg-white border border-zinc-200/80 hover:border-black rounded-2xl transition group shadow-sm flex flex-col justify-between">
            <div>
                <div class="w-10 h-10 bg-black text-white rounded-xl flex items-center justify-center mb-4 group-hover:scale-95 transition-transform">
                    <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"></path>
                        <path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path>
                        <path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"></path>
                        <path d="M10 6h4"></path>
                        <path d="M10 10h4"></path>
                        <path d="M10 14h4"></path>
                    </svg>
                </div>
                <h3 class="text-base font-bold text-black group-hover:underline">Kelola Brand Travel</h3>
                <p class="text-xs text-zinc-500 mt-1.5 leading-relaxed">
                    Atur legalitas nomor izin PPIU Kemenag, nomor rekening resmi transfer DP bank, kontak, dan alamat kantor 5 brand travel.
                </p>
            </div>
            <div class="mt-5 text-xs font-semibold text-black flex items-center gap-1.5">
                <span>Buka Kelola Brand</span>
                <svg class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                </svg>
            </div>
        </a>

        <a href="packages.php" class="p-6 bg-white border border-zinc-200/80 hover:border-black rounded-2xl transition group shadow-sm flex flex-col justify-between">
            <div>
                <div class="w-10 h-10 bg-black text-white rounded-xl flex items-center justify-center mb-4 group-hover:scale-95 transition-transform">
                    <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m7.5 4.27 9 5.15"></path>
                        <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path>
                        <path d="m3.3 7 8.7 5 8.7-5"></path>
                        <path d="M12 22V12"></path>
                    </svg>
                </div>
                <h3 class="text-base font-bold text-black group-hover:underline">Kelola Paket Umroh</h3>
                <p class="text-xs text-zinc-500 mt-1.5 leading-relaxed">
                    Tambah atau perbarui harga, DP, maskapai, hotel Makkah & Madinah, dan jadwal keberangkatan untuk setiap brand travel.
                </p>
            </div>
            <div class="mt-5 text-xs font-semibold text-black flex items-center gap-1.5">
                <span>Buka Kelola Paket</span>
                <svg class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                </svg>
            </div>
        </a>

        <a href="users.php" class="p-6 bg-white border border-zinc-200/80 hover:border-black rounded-2xl transition group shadow-sm flex flex-col justify-between">
            <div>
                <div class="w-10 h-10 bg-black text-white rounded-xl flex items-center justify-center mb-4 group-hover:scale-95 transition-transform">
                    <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <h3 class="text-base font-bold text-black group-hover:underline">Kelola Akun CS</h3>
                <p class="text-xs text-zinc-500 mt-1.5 leading-relaxed">
                    Buat akun CS baru, atur kata sandi, dan tugaskan akun CS ke salah satu brand travel secara spesifik.
                </p>
            </div>
            <div class="mt-5 text-xs font-semibold text-black flex items-center gap-1.5">
                <span>Buka Kelola Akun CS</span>
                <svg class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                </svg>
            </div>
        </a>
    </div>

    <!-- Multi-Brand Status Table (Styled matching reference "Latest Orders") -->
    <div class="bg-white border border-zinc-200/80 rounded-2xl overflow-hidden shadow-sm">
        <div class="p-5 sm:p-6 border-b border-zinc-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-black tracking-tight">Status 5 Brand Travel Perusahaan</h2>
                <p class="text-xs text-zinc-500 mt-0.5">Ringkasan izin PPIU Kemenag dan rekening bank resmi DP per brand</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="brands.php" class="px-3.5 py-1.5 border border-zinc-200 hover:border-black text-zinc-700 text-xs font-semibold rounded-xl transition flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <span>Kelola Seluruh Brand</span>
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-50 border-b border-zinc-200 text-zinc-600 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="py-3 px-4">Nama Brand Travel</th>
                        <th class="py-3 px-4">Izin PPIU Kemenag</th>
                        <th class="py-3 px-4">Rekening Resmi DP</th>
                        <th class="py-3 px-4 text-center">Paket Aktif</th>
                        <th class="py-3 px-4 text-center">CS Ditugaskan</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    <?php foreach ($brands as $b): ?>
                        <tr class="hover:bg-zinc-50/50 transition">
                            <td class="py-3.5 px-4 font-semibold text-zinc-900">
                                <?= htmlspecialchars($b['name']) ?>
                                <div class="text-[10px] text-zinc-400 font-mono"><?= htmlspecialchars($b['code']) ?></div>
                            </td>
                            <td class="py-3.5 px-4 text-zinc-600 font-mono text-[11px]">
                                <?= htmlspecialchars($b['ppiu_number'] ?: 'Belum diisi') ?>
                            </td>
                            <td class="py-3.5 px-4 text-zinc-700">
                                <div><strong><?= htmlspecialchars($b['bank_name'] ?: '-') ?></strong>: <?= htmlspecialchars($b['bank_account_number'] ?: '-') ?></div>
                                <div class="text-[10px] text-zinc-400">a/n <?= htmlspecialchars($b['bank_account_holder'] ?: '-') ?></div>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-block px-2 py-0.5 text-[11px] rounded bg-zinc-100 font-medium text-zinc-800">
                                    <?= $b['package_count'] ?> Paket
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-block px-2 py-0.5 text-[11px] rounded bg-zinc-100 font-medium text-zinc-800">
                                    <?= $b['cs_count'] ?> CS
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <a href="brands.php?edit=<?= $b['id'] ?>" class="text-xs font-semibold text-black hover:underline">
                                    Edit Brand
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

