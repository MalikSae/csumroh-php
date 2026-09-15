<?php
$pageTitle = 'Detail & Riwayat Prospek - CS Umroh';
require_once __DIR__ . '/includes/header.php';

$db = get_db();
$currentUser = get_logged_user();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo "<script>window.location.href='prospects.php';</script>";
    exit;
}

// Fetch prospect details with joins
$sql = "
    SELECT p.*, b.name AS brand_name, b.code AS brand_code,
           pkg.name AS package_name, pkg.price AS package_price, pkg.dp AS package_dp,
           pkg.airline AS package_airline, pkg.duration AS package_duration,
           pkg.hotel_makkah AS package_hotel_makkah, pkg.hotel_madinah AS package_hotel_madinah,
           pkg.departure_info AS package_departure_info, pkg.highlights AS package_highlights,
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
    echo "<div class='max-w-4xl mx-auto py-12 text-center text-zinc-500'>Data prospek tidak ditemukan atau Anda tidak memiliki akses. <a href='prospects.php' class='underline text-black font-semibold'>Kembali ke Data Prospek</a></div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Fetch packages for this brand (for edit modal & quick update)
$stmt = $db->prepare("SELECT * FROM packages WHERE brand_id = ? AND is_active = 1 ORDER BY id DESC");
$stmt->execute([$prospect['brand_id']]);
$packages = $stmt->fetchAll();

// Fetch activity logs
$stmt = $db->prepare("
    SELECT pl.*, u.name as user_name
    FROM prospect_logs pl
    LEFT JOIN users u ON pl.user_id = u.id
    WHERE pl.prospect_id = ?
    ORDER BY pl.created_at DESC, pl.id DESC
");
$stmt->execute([$prospect['id']]);
$logs = $stmt->fetchAll();

// Fetch last WhatsApp message timestamp for this prospect
$lastMsgStmt = $db->prepare("
    SELECT MAX(timestamp) as last_msg_ts
    FROM chat_messages
    WHERE prospect_id = ? AND is_deleted = 0
");
$lastMsgStmt->execute([$prospect['id']]);
$lastMsgRow = $lastMsgStmt->fetch();
$lastMsgTs = $lastMsgRow['last_msg_ts'] ? (int)$lastMsgRow['last_msg_ts'] : null;

?>

<div class="max-w-7xl mx-auto space-y-6 pb-12" x-data="prospectDetailPage()">

    <!-- Top Navigation & Actions Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-2 border-b border-zinc-200">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <a href="prospects.php" class="text-xs text-zinc-500 hover:text-black font-semibold inline-flex items-center gap-1.5 transition">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m15 18-6-6 6-6"/>
                    </svg>
                    <span>Kembali ke Data Prospek</span>
                </a>
                <span class="text-zinc-300">/</span>
                <span class="text-xs text-zinc-400 font-mono">ID #<?= $prospect['id'] ?></span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-black tracking-tight" x-text="prospect.name"></h1>
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold" :class="getStatusBadgeClass(prospect.status)" x-text="getStatusLabel(prospect.status)"></span>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 mt-1">
                <span>Brand: <strong class="text-zinc-800"><?= htmlspecialchars($prospect['brand_name']) ?></strong></span>
                <span>&bull;</span>
                <span>CS Penanggung Jawab: <strong class="text-zinc-800"><?= htmlspecialchars($prospect['cs_name'] ?? '-') ?></strong></span>
                <span>&bull;</span>
                <?php
                    // Last activity: prefer last WA message, fallback to updated_at
                    if ($lastMsgTs) {
                        $lastActivityLabel = 'Chat terakhir';
                        $lastActivityTime  = date('d M Y, H:i', $lastMsgTs);
                        $diffSec = time() - $lastMsgTs;
                    } else {
                        $lastActivityLabel = 'Diperbarui';
                        $lastActivityTime  = date('d M Y, H:i', strtotime($prospect['updated_at']));
                        $diffSec = time() - strtotime($prospect['updated_at']);
                    }
                    // Human-readable diff
                    if ($diffSec < 60)           $diffText = 'baru saja';
                    elseif ($diffSec < 3600)     $diffText = floor($diffSec/60) . ' mnt lalu';
                    elseif ($diffSec < 86400)    $diffText = floor($diffSec/3600) . ' jam lalu';
                    elseif ($diffSec < 604800)   $diffText = floor($diffSec/86400) . ' hari lalu';
                    else                          $diffText = $lastActivityTime;
                ?>
                <span class="inline-flex items-center gap-1">
                    <svg class="w-3 h-3 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                    <?= $lastActivityLabel ?>:
                    <strong class="text-zinc-700" title="<?= $lastActivityTime ?>"><?= $diffText ?></strong>
                </span>
            </div>

        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center gap-2">
            <?php if (!empty($prospect['phone'])): ?>
                <a href="https://wa.me/<?= $prospect['phone'] ?>" target="_blank"
                   class="px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-xl text-xs font-semibold transition flex items-center gap-2 shadow-xs">
                    <svg class="w-3.5 h-3.5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m3 21 1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"></path>
                    </svg>
                    <span>Chat WhatsApp</span>
                </a>
            <?php endif; ?>

            <a href="index.php?load_prospect=<?= $prospect['id'] ?>"
               class="px-3.5 py-2 bg-black hover:bg-zinc-800 text-white rounded-xl text-xs font-semibold transition flex items-center gap-2 shadow-xs">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
                <span>Muat ke Copilot</span>
            </a>

            <button type="button" @click="openEditModal()"
                    class="px-3.5 py-2 bg-white hover:bg-zinc-50 border border-zinc-300 text-zinc-800 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 shadow-xs">
                <svg class="w-3.5 h-3.5 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
                </svg>
                <span>Edit Profil</span>
            </button>

            <button type="button" @click="deleteCurrentProspect()"
                    class="px-3 py-2 bg-white hover:bg-rose-50 border border-zinc-200 hover:border-rose-200 text-zinc-400 hover:text-rose-600 rounded-xl text-xs transition">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- 3 Metric Cards: Tanggal Masuk, Terakhir Follow-up, Jadwal Berikutnya -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 sm:gap-5">
        <!-- Card 1: Tanggal Masuk -->
        <div class="p-4 sm:p-5 bg-white border border-zinc-200/80 rounded-2xl shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-500">Tanggal Masuk Prospek</span>
                <div class="w-8 h-8 rounded-xl bg-zinc-100 border border-zinc-200 text-zinc-700 flex items-center justify-center">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/>
                        <line x1="16" x2="16" y1="2" y2="6"/>
                        <line x1="8" x2="8" y1="2" y2="6"/>
                        <line x1="3" x2="21" y1="10" y2="10"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-base sm:text-lg font-bold text-black font-mono">
                    <?= date('d M Y, H:i', strtotime($prospect['created_at'])) ?> WIB
                </div>
                <div class="text-[11px] text-zinc-400 mt-0.5">Waktu pertama kali didaftarkan</div>
            </div>
        </div>

        <!-- Card 2: Terakhir Follow-up -->
        <div class="p-4 sm:p-5 bg-white border border-zinc-200/80 rounded-2xl shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-500">Terakhir Di-Follow Up</span>
                <div class="w-8 h-8 rounded-xl bg-zinc-100 border border-zinc-200 text-zinc-700 flex items-center justify-center">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-base sm:text-lg font-bold text-black font-mono" x-text="prospect.last_followup_at ? formatDate(prospect.last_followup_at) : 'Belum pernah'"></div>
                <div class="text-[11px] text-zinc-400 mt-0.5">Aktivitas komunikasi terakhir</div>
            </div>
        </div>

        <!-- Card 3: Target Follow-up Berikutnya -->
        <div class="p-4 sm:p-5 bg-white border border-zinc-200/80 rounded-2xl shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-500">Jadwal Follow-up Berikutnya</span>
                <div class="w-8 h-8 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 flex items-center justify-center">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-base sm:text-lg font-bold font-mono"
                     :class="prospect.next_followup_date ? 'text-amber-900' : 'text-zinc-400'"
                     x-text="prospect.next_followup_date || 'Belum dijadwalkan'"></div>
                <div class="text-[11px] text-zinc-400 mt-0.5">Pengingat kontak prospek kembali</div>
            </div>
        </div>
    </div>

    <!-- Main Grid: Left (Dossier & Profil) vs Right (Catat Followup & Riwayat Lengkap) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Left Column: Profil, Paket Minat & NPGD (5 cols) -->
        <div class="lg:col-span-5 space-y-6">

            <!-- Card 1: Data Kontak & Identitas -->
            <div class="bg-white border border-zinc-200/80 rounded-2xl p-5 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-black flex items-center gap-2">
                        <svg class="w-4 h-4 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        <span>Informasi Calon Jamaah</span>
                    </h2>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-zinc-400 block text-[11px]">Nama Lengkap</span>
                        <div class="font-bold text-zinc-900 text-sm mt-0.5" x-text="prospect.name"></div>
                    </div>

                    <div>
                        <span class="text-zinc-400 block text-[11px]">Nomor WhatsApp</span>
                        <div class="font-mono font-semibold text-zinc-900 mt-0.5 flex items-center gap-2">
                            <span x-text="prospect.phone || '- Tidak ada nomor -'"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-1">
                        <div>
                            <span class="text-zinc-400 block text-[11px]">Tahap Obrolan (SOP)</span>
                            <div class="font-semibold text-zinc-900 capitalize mt-0.5" x-text="prospect.current_stage"></div>
                        </div>
                        <div>
                            <span class="text-zinc-400 block text-[11px]">Status Konversi</span>
                            <span class="inline-block mt-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold"
                                  :class="getStatusBadgeClass(prospect.status)"
                                  x-text="getStatusLabel(prospect.status)"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Paket Umroh yang Diminati -->
            <div class="bg-white border border-zinc-200/80 rounded-2xl p-5 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-black flex items-center gap-2">
                        <svg class="w-4 h-4 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="20" height="14" x="2" y="7" rx="2" ry="2"/>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                        </svg>
                        <span>Paket Umroh Pilihan</span>
                    </h2>
                </div>

                <?php if ($prospect['package_name']): ?>
                    <div class="space-y-3 text-xs">
                        <div>
                            <div class="text-sm font-bold text-black"><?= htmlspecialchars($prospect['package_name']) ?></div>
                            <div class="text-xs font-bold text-amber-700 font-mono mt-0.5"><?= htmlspecialchars($prospect['package_price']) ?></div>
                        </div>

                        <div class="grid grid-cols-2 gap-2.5 pt-2 border-t border-zinc-100 text-[11px]">
                            <div>
                                <span class="text-zinc-400">Minimal DP Seat:</span>
                                <div class="font-semibold font-mono text-zinc-800"><?= htmlspecialchars($prospect['package_dp'] ?? '-') ?></div>
                            </div>
                            <div>
                                <span class="text-zinc-400">Durasi Perjalanan:</span>
                                <div class="font-semibold text-zinc-800"><?= htmlspecialchars($prospect['package_duration'] ?? '-') ?></div>
                            </div>
                            <div>
                                <span class="text-zinc-400">Penerbangan / Maskapai:</span>
                                <div class="font-semibold text-zinc-800"><?= htmlspecialchars($prospect['package_airline'] ?? '-') ?></div>
                            </div>
                            <div>
                                <span class="text-zinc-400">Jadwal Keberangkatan:</span>
                                <div class="font-semibold text-zinc-800"><?= htmlspecialchars($prospect['package_departure_info'] ?? '-') ?></div>
                            </div>
                            <div class="col-span-2">
                                <span class="text-zinc-400">Akomodasi Hotel:</span>
                                <div class="font-medium text-zinc-800 mt-0.5">
                                    Makkah: <?= htmlspecialchars($prospect['package_hotel_makkah'] ?? '-') ?> &bull; 
                                    Madinah: <?= htmlspecialchars($prospect['package_hotel_madinah'] ?? '-') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="py-6 text-center text-zinc-400 text-xs">
                        Calon jamaah belum menentukan pilihan paket umroh.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Card 3: Catatan Kebutuhan Calon Jamaah (NPGD) -->
            <div class="bg-white border border-zinc-200/80 rounded-2xl p-5 shadow-xs space-y-3">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-black flex items-center gap-2">
                        <svg class="w-4 h-4 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" x2="8" y1="13" y2="13"/>
                            <line x1="16" x2="8" y1="17" y2="17"/>
                            <line x1="10" x2="8" y1="9" y2="9"/>
                        </svg>
                        <span>Dossier Kebutuhan (NPGD)</span>
                    </h2>
                    <span class="text-[10px] text-zinc-400 font-mono">Need &bull; Pain &bull; Gain &bull; Dream</span>
                </div>

                <div class="bg-zinc-50 border border-zinc-200 rounded-xl p-3.5 text-xs text-zinc-800 leading-relaxed min-h-[90px] whitespace-pre-line"
                     x-text="prospect.notes || '- Belum ada catatan kebutuhan jamaah -'"></div>
            </div>

        </div>

        <!-- Right Column: Catat Follow-up & Timeline Riwayat (7 cols) -->
        <div class="lg:col-span-7 space-y-6">

            <!-- Card: Form Catat Follow-up Cepat -->
            <div class="bg-white border border-zinc-200/80 rounded-2xl p-5 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-black flex items-center gap-2">
                        <svg class="w-4 h-4 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        <span>Catat Hasil Follow-up Baru</span>
                    </h2>
                    <span class="text-[11px] text-zinc-400">Otomatis memperbarui waktu terakhir kontak</span>
                </div>

                <form @submit.prevent="submitFollowup()" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">Catatan Hasil Komunikasi / Follow-up *</label>
                        <textarea x-model="followupForm.note" required rows="3"
                                  class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black text-xs placeholder-zinc-400"
                                  placeholder="Contoh: Menghubungi Bu Siti via WhatsApp. Beliau sudah membaca rincian paket dan sedang berdiskusi dengan suami terkait jadwal cuti kerja. Rencana follow-up 2 hari lagi."></textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Custom Dropdown for Status -->
                        <div>
                            <label class="block font-semibold text-zinc-700 mb-1">Perbarui Status Prospek</label>
                            <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                                <button type="button" @click="open = !open"
                                        class="w-full flex items-center justify-between px-3 py-2 border border-zinc-300 bg-white rounded-xl text-xs font-semibold text-zinc-900 focus:outline-none focus:border-black shadow-xs">
                                    <span x-text="getStatusLabel(followupForm.status)"></span>
                                    <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="m6 9 6 6 6-6"/>
                                    </svg>
                                </button>
                                <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                                     class="absolute z-50 mt-1 w-full bg-white border border-zinc-200 rounded-xl shadow-xl max-h-56 overflow-y-auto py-1 text-xs divide-y divide-zinc-50">
                                    <template x-for="st in statusOptions" :key="st.val">
                                        <button type="button" @click="followupForm.status = st.val; open = false"
                                                class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                                :class="followupForm.status === st.val ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                            <span x-text="st.label"></span>
                                            <svg x-show="followupForm.status === st.val" class="w-3.5 h-3.5 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="20 6 9 17 4 12"/>
                                            </svg>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Date input for Next Followup -->
                        <div>
                            <label class="block font-semibold text-zinc-700 mb-1">Target Follow-up Berikutnya</label>
                            <input type="date" x-model="followupForm.next_followup_date"
                                   class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black text-xs font-mono">
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button type="submit" :disabled="isSubmittingFollowup"
                                class="px-4 py-2 bg-black hover:bg-zinc-800 text-white rounded-xl text-xs font-semibold transition inline-flex items-center gap-2 shadow-xs disabled:opacity-50">
                            <span x-text="isSubmittingFollowup ? 'Menyimpan...' : 'Simpan Follow-up & Update Riwayat'"></span>
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="22" y1="2" x2="11" y2="13"/>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Card: Riwayat Perubahan & Audit Trail -->
            <div class="bg-white border border-zinc-200/80 rounded-2xl p-5 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-black flex items-center gap-2">
                        <svg class="w-4 h-4 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 20v-6M6 20V10M18 20V4"/>
                        </svg>
                        <span>Riwayat Aktivitas & Perubahan</span>
                    </h2>
                    <span class="text-[11px] text-zinc-500 font-semibold" x-text="logsList.length + ' Riwayat'"></span>
                </div>

                <!-- Timeline List -->
                <div class="relative pl-6 space-y-6 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-zinc-200">
                    <template x-for="(item, idx) in logsList" :key="item.id || idx">
                        <div class="relative group">
                            <!-- Bullet Icon -->
                            <div class="absolute -left-6 top-0.5 w-5 h-5 rounded-full border-2 border-white bg-black text-white flex items-center justify-center shadow-xs">
                                <template x-if="item.action_type === 'created'">
                                    <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                </template>
                                <template x-if="item.action_type === 'followup_logged'">
                                    <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                </template>
                                <template x-if="item.action_type === 'status_changed'">
                                    <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                                </template>
                                <template x-if="item.action_type === 'updated'">
                                    <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                </template>
                            </div>

                            <!-- Log Content Card -->
                            <div class="bg-zinc-50 border border-zinc-200/90 rounded-xl p-3.5 text-xs transition hover:border-zinc-300">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-bold text-black" x-text="item.title"></span>
                                    <span class="text-[10px] text-zinc-400 font-mono" x-text="formatDate(item.created_at)"></span>
                                </div>
                                <div class="text-[11px] text-zinc-500 mt-0.5 flex items-center gap-1.5">
                                    <span>Oleh: <strong class="text-zinc-700" x-text="item.user_name || 'Sistem / CS'"></strong></span>
                                </div>
                                <div class="text-zinc-700 mt-2 text-xs leading-relaxed whitespace-pre-line border-t border-zinc-200/60 pt-2"
                                     x-text="item.description || '-'"></div>
                            </div>
                        </div>
                    </template>

                    <template x-if="logsList.length === 0">
                        <div class="text-center py-6 text-zinc-400 text-xs">
                            Belum ada catatan riwayat untuk prospek ini.
                        </div>
                    </template>
                </div>
            </div>

        </div>

    </div>

    <!-- MODAL EDIT PROSPEK (Full Custom Dropdown) -->
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/60 flex items-center justify-center p-4">
        <div @click.away="showEditModal = false" class="bg-white border border-zinc-200 rounded-2xl max-w-lg w-full p-6 shadow-xl relative max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-zinc-100">
                <h3 class="text-sm font-bold text-black uppercase tracking-wider">Edit Data Prospek Jamaah</h3>
                <button type="button" @click="showEditModal = false" class="text-zinc-400 hover:text-black text-lg font-bold">&times;</button>
            </div>

            <form @submit.prevent="saveProspectEdit()" class="space-y-3.5 text-xs">
                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Nama Calon Jamaah *</label>
                    <input type="text" x-model="editForm.name" required
                           class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black font-semibold text-zinc-900">
                </div>

                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">No. WhatsApp / HP</label>
                    <input type="text" x-model="editForm.phone"
                           class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black font-mono"
                           placeholder="Contoh: 081234567890">
                </div>

                <!-- Custom Dropdown for Package Selection -->
                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Paket Umroh Diminati</label>
                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                        <button type="button" @click="open = !open"
                                class="w-full flex items-center justify-between px-3 py-2 border border-zinc-300 bg-white rounded-xl text-xs font-semibold text-zinc-900 focus:outline-none focus:border-black shadow-xs">
                            <span x-text="getPackageLabel(editForm.package_id)"></span>
                            <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="m6 9 6 6 6-6"/>
                            </svg>
                        </button>
                        <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                             class="absolute z-50 mt-1 w-full bg-white border border-zinc-200 rounded-xl shadow-xl max-h-56 overflow-y-auto py-1 text-xs divide-y divide-zinc-50">
                            <button type="button" @click="editForm.package_id = ''; open = false"
                                    class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 text-zinc-500 italic">
                                <span>-- Belum Memilih Paket --</span>
                            </button>
                            <template x-for="pkg in packagesList" :key="pkg.id">
                                <button type="button" @click="editForm.package_id = pkg.id; open = false"
                                        class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                        :class="editForm.package_id == pkg.id ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                    <span x-text="pkg.name + ' (' + pkg.price + ')'"></span>
                                    <svg x-show="editForm.package_id == pkg.id" class="w-3.5 h-3.5 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
                                <span x-text="getStageLabel(editForm.current_stage)"></span>
                                <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="m6 9 6 6 6-6"/>
                                </svg>
                            </button>
                            <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                                 class="absolute z-50 mt-1 w-full bg-white border border-zinc-200 rounded-xl shadow-xl max-h-56 overflow-y-auto py-1 text-xs divide-y divide-zinc-50">
                                <template x-for="stg in stageOptions" :key="stg.val">
                                    <button type="button" @click="editForm.current_stage = stg.val; open = false"
                                            class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                            :class="editForm.current_stage === stg.val ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                        <span x-text="stg.label"></span>
                                        <svg x-show="editForm.current_stage === stg.val" class="w-3.5 h-3.5 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
                                <span x-text="getStatusLabel(editForm.status)"></span>
                                <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="m6 9 6 6 6-6"/>
                                </svg>
                            </button>
                            <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                                 class="absolute z-50 mt-1 w-full bg-white border border-zinc-200 rounded-xl shadow-xl max-h-56 overflow-y-auto py-1 text-xs divide-y divide-zinc-50">
                                <template x-for="st in statusOptions" :key="st.val">
                                    <button type="button" @click="editForm.status = st.val; open = false"
                                            class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                            :class="editForm.status === st.val ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                        <span x-text="st.label"></span>
                                        <svg x-show="editForm.status === st.val" class="w-3.5 h-3.5 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="20 6 9 17 4 12"/>
                                        </svg>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Catatan Kebutuhan Jamaah (NPGD)</label>
                    <textarea x-model="editForm.notes" rows="3"
                              class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black text-xs"
                              placeholder="Catatan detail kebutuhan jamaah..."></textarea>
                </div>

                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Target Follow-up Berikutnya</label>
                    <input type="date" x-model="editForm.next_followup_date"
                           class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black text-xs font-mono">
                </div>

                <div class="pt-4 flex items-center justify-end gap-2 border-t border-zinc-100">
                    <button type="button" @click="showEditModal = false"
                            class="px-3.5 py-2 border border-zinc-300 text-zinc-700 hover:bg-zinc-50 rounded-xl text-xs transition">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-black hover:bg-zinc-800 text-white rounded-xl text-xs font-semibold transition inline-flex items-center gap-1.5 shadow-xs">
                        <span>Simpan Perubahan</span>
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function prospectDetailPage() {
    return {
        prospect: <?= json_encode($prospect) ?>,
        packagesList: <?= json_encode($packages) ?>,
        logsList: <?= json_encode($logs) ?>,
        showEditModal: false,
        isSubmittingFollowup: false,

        followupForm: {
            note: '',
            status: '<?= $prospect['status'] ?>',
            next_followup_date: '<?= $prospect['next_followup_date'] ?? '' ?>'
        },

        editForm: {
            id: <?= $prospect['id'] ?>,
            name: '<?= addslashes($prospect['name']) ?>',
            phone: '<?= addslashes($prospect['phone'] ?? '') ?>',
            package_id: '<?= $prospect['package_id'] ?? '' ?>',
            current_stage: '<?= $prospect['current_stage'] ?? 'greeting' ?>',
            status: '<?= $prospect['status'] ?>',
            notes: <?= json_encode($prospect['notes'] ?? '') ?>,
            next_followup_date: '<?= $prospect['next_followup_date'] ?? '' ?>'
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
                case 'new': return 'bg-sky-50 text-sky-700 border border-sky-200';
                case 'identifying': return 'bg-indigo-50 text-indigo-700 border border-indigo-200';
                case 'offered': return 'bg-purple-50 text-purple-700 border border-purple-200';
                case 'closing': return 'bg-amber-50 text-amber-800 border border-amber-300 font-semibold';
                case 'objection': return 'bg-orange-50 text-orange-800 border border-orange-300 font-semibold';
                case 'followup': return 'bg-yellow-50 text-yellow-800 border border-yellow-300 font-semibold';
                case 'nurture': return 'bg-slate-100 text-slate-700 border border-slate-300';
                case 'closed_won': return 'bg-emerald-50 text-emerald-800 border border-emerald-300 font-bold';
                case 'closed_lost': return 'bg-zinc-100 text-zinc-500 border border-zinc-200 line-through';
                default: return 'bg-zinc-100 text-zinc-700 border border-zinc-200';
            }
        },

        formatDate(dateString) {
            if (!dateString) return '-';
            try {
                const d = new Date(dateString);
                return d.toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                }) + ' WIB';
            } catch (e) {
                return dateString;
            }
        },

        openEditModal() {
            this.showEditModal = true;
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

        async saveProspectEdit() {
            try {
                const payload = {
                    action: 'update',
                    ...this.editForm,
                    brand_id: this.prospect.brand_id
                };

                const res = await fetch('api/prospects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    window.showToast('Data prospek berhasil diperbarui!');
                    this.showEditModal = false;
                    // update local prospect data
                    this.prospect.name = this.editForm.name;
                    this.prospect.phone = this.editForm.phone;
                    this.prospect.package_id = this.editForm.package_id;
                    this.prospect.current_stage = this.editForm.current_stage;
                    this.prospect.status = this.editForm.status;
                    this.prospect.notes = this.editForm.notes;
                    this.prospect.next_followup_date = this.editForm.next_followup_date;

                    // update package details if package changed
                    if (this.editForm.package_id) {
                        const pkg = this.packagesList.find(p => p.id == this.editForm.package_id);
                        if (pkg) {
                            this.prospect.package_name = pkg.name;
                            this.prospect.package_price = pkg.price;
                        }
                    } else {
                        this.prospect.package_name = null;
                        this.prospect.package_price = null;
                    }

                    await this.reloadLogs();
                } else {
                    alert(data.error || 'Gagal memperbarui prospek');
                }
            } catch (err) {
                console.error(err);
                alert('Terjadi kesalahan koneksi.');
            }
        },

        async deleteCurrentProspect() {
            if (!confirm('Hapus prospek ini secara permanen? Seluruh riwayat percakapan akan terhapus.')) return;
            try {
                const res = await fetch('api/prospects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete',
                        id: this.prospect.id
                    })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.href = 'prospects.php';
                }
            } catch (err) {
                console.error(err);
            }
        },

        async reloadLogs() {
            try {
                const res = await fetch(`api/prospects.php?action=get_logs&id=${this.prospect.id}`);
                const data = await res.json();
                if (data.success) {
                    this.logsList = data.data;
                }
            } catch (err) {
                console.error(err);
            }
        }
    };
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

