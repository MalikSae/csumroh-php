<?php
$pageTitle = 'Koneksi WhatsApp - CS Umroh';
require_once __DIR__ . '/includes/header.php';

$db = get_db();
$userBrandId = ($user['role'] === 'superadmin' && !empty($_GET['brand_id'])) 
                ? (int)$_GET['brand_id'] 
                : ($user['brand_id'] ?: 1);

// Get brands list for Super Admin dropdown
$brandsList = [];
if ($isSuperAdmin) {
    $brandsList = $db->query("SELECT id, name, code, phone FROM brands ORDER BY id ASC")->fetchAll();
}

// Get current brand info
$stmtB = $db->prepare("SELECT * FROM brands WHERE id = ?");
$stmtB->execute([$userBrandId]);
$currentBrand = $stmtB->fetch();
?>

<div class="max-w-4xl mx-auto space-y-6" x-data="whatsappConnectApp(<?= $userBrandId ?>)" x-init="init()">
    
    <!-- Top Header & Brand Selector -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 sm:pb-6 border-b border-zinc-200 gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="index.php" class="text-xs text-zinc-500 hover:text-black inline-flex items-center gap-1 font-semibold transition">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    <span>Copilot Chat</span>
                </a>
                <span class="text-zinc-300">/</span>
                <span class="text-xs font-semibold text-black">Integrasi WhatsApp Gateway</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-black tracking-tight mt-1">Koneksi WhatsApp Resmi</h1>
            <p class="text-xs text-zinc-500 mt-0.5">
                Hubungkan nomor WhatsApp resmi travel untuk menerima chat jamaah dan sync riwayat otomatis.
            </p>
        </div>

        <?php if ($isSuperAdmin): ?>
            <!-- Super Admin Brand Selector -->
            <div x-data="{ openBrand: false }" class="relative" @click.outside="openBrand = false">
                <div class="flex items-center gap-2 bg-white border border-zinc-200 p-1 rounded-xl shadow-2xs">
                    <label class="text-[11px] font-semibold text-zinc-500 pl-2">Brand:</label>
                    <button type="button" @click="openBrand = !openBrand"
                            class="px-2.5 py-1 bg-white hover:bg-zinc-50 border border-zinc-200 rounded-lg text-xs font-bold text-zinc-900 transition flex items-center gap-1.5 shadow-2xs">
                        <span><?= htmlspecialchars($currentBrand['name'] ?? 'Pilih Brand') ?></span>
                        <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0" :class="openBrand ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>
                </div>
                <div x-show="openBrand" x-cloak x-transition.opacity.duration.150ms
                     class="absolute right-0 z-50 mt-1 min-w-[200px] bg-white border border-zinc-200 rounded-xl shadow-xl py-1 text-xs divide-y divide-zinc-100">
                    <?php foreach ($brandsList as $b): ?>
                        <button type="button" @click="changeBrand(<?= $b['id'] ?>); openBrand = false"
                                class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition <?= $b['id'] == $userBrandId ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700' ?>">
                            <span><?= htmlspecialchars($b['name']) ?></span>
                            <?php if ($b['id'] == $userBrandId): ?>
                                <svg class="w-3.5 h-3.5 text-black shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Active Brand Status Banner -->
    <div class="bg-white border border-zinc-200 rounded-2xl p-5 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3.5">
            <div class="w-11 h-11 bg-zinc-100 rounded-xl flex items-center justify-center font-bold text-base text-zinc-800 border border-zinc-200 shrink-0">
                <svg class="w-6 h-6 text-zinc-800" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                    <path d="M12 18h.01"/>
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-bold text-black"><?= htmlspecialchars($currentBrand['name'] ?? 'Brand Travel') ?></h2>
                    <span class="text-[10px] font-mono uppercase bg-zinc-100 px-2 py-0.5 rounded text-zinc-600 font-semibold">
                        <?= htmlspecialchars($currentBrand['code'] ?? '') ?>
                    </span>
                </div>
                <p class="text-xs text-zinc-500 mt-0.5">
                    Nomor Terdaftar: <span class="font-mono text-zinc-800 font-medium"><?= htmlspecialchars($currentBrand['phone'] ?: 'Belum disetting') ?></span>
                </p>
            </div>
        </div>

        <!-- Connection Status Badge -->
        <div class="flex items-center gap-2">
            <template x-if="status === 'connected'">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-black text-white shadow-xs">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Terhubung Aktif</span>
                </div>
            </template>
            <template x-if="(status === 'qr_ready' || qrCode) && status !== 'connected'">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-zinc-100 text-zinc-900 border border-zinc-300">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    <span>Menunggu Scan QR</span>
                </div>
            </template>
            <template x-if="status === 'connecting' && !qrCode">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-zinc-100 text-zinc-600 border border-zinc-200">
                    <svg class="animate-spin w-3.5 h-3.5 text-zinc-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>Menghubungkan...</span>
                </div>
            </template>
            <template x-if="status === 'disconnected' && !qrCode">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-zinc-100 text-zinc-600 border border-zinc-200">
                    <span class="w-2 h-2 rounded-full bg-zinc-400"></span>
                    <span>Tidak Terhubung</span>
                </div>
            </template>
        </div>
    </div>

    <!-- MAIN PANEL: STATE BASED -->
    
    <!-- 1. CONNECTED STATE -->
    <div x-show="status === 'connected'" x-cloak class="bg-white border border-zinc-200 rounded-2xl p-6 sm:p-8 text-center space-y-6 shadow-2xs">
        <div class="w-16 h-16 mx-auto bg-zinc-900 text-white rounded-2xl flex items-center justify-center shadow-sm">
            <svg class="w-8 h-8 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>

        <div class="max-w-md mx-auto space-y-2">
            <h3 class="text-xl font-extrabold text-black">WhatsApp Berhasil Terhubung</h3>
            <p class="text-xs text-zinc-500 leading-relaxed">
                Nomor WhatsApp aktif dan siap menerima pesan calon jamaah. Riwayat percakapan lama telah disinkronkan ke database sistem.
            </p>
            <div class="pt-3">
                <span class="inline-block px-4 py-2 bg-zinc-100 rounded-xl font-mono text-sm font-bold text-zinc-900">
                    <span x-text="phoneNumber ? '+62 ' + phoneNumber.replace(/^0|^62/, '') : 'Nomor WhatsApp Aktif'"></span>
                </span>
            </div>
        </div>

        <div class="pt-4 border-t border-zinc-100 flex flex-col sm:flex-row items-center justify-center gap-3">
            <a :href="'chat.php?brand_id=' + brandId" class="w-full sm:w-auto px-6 py-2.5 bg-black hover:bg-zinc-800 text-white text-xs font-semibold rounded-xl transition shadow-sm inline-flex items-center justify-center gap-2">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <span>Buka Live Chat Workspace</span>
            </a>
            <button type="button" @click="disconnectSession()" :disabled="isLoading"
                class="w-full sm:w-auto px-5 py-2.5 border border-zinc-300 hover:bg-zinc-100 text-zinc-800 text-xs font-semibold rounded-xl transition">
                <span x-show="!isLoading">Putuskan Sesi WhatsApp</span>
                <span x-show="isLoading">Memproses...</span>
            </button>
        </div>
    </div>

    <!-- 2. QR READY STATE (Displays whenever qrCode is available) -->
    <div x-show="(status === 'qr_ready' || qrCode) && status !== 'connected'" x-cloak class="bg-white border border-zinc-200 rounded-2xl p-6 sm:p-8 space-y-6 shadow-2xs">
        <div class="max-w-xl mx-auto text-center space-y-2">
            <h3 class="text-xl font-extrabold text-black">Pindai QR Code dengan WhatsApp</h3>
            <p class="text-xs text-zinc-500 leading-relaxed">
                Buka WhatsApp di HP Anda, buka Menu / Pengaturan &gt; Perangkat Tertaut &gt; Tautkan Perangkat, lalu arahkan kamera ke QR Code di bawah.
            </p>
        </div>

        <div class="flex flex-col md:flex-row items-center justify-center gap-8 pt-4">
            <!-- QR Code Display Box -->
            <div class="p-4 bg-white border-2 border-zinc-900 rounded-2xl shadow-sm text-center">
                <template x-if="qrCode">
                    <img :src="qrCode" alt="Scan WhatsApp QR" class="w-56 h-56 mx-auto rounded-lg">
                </template>
                <div class="mt-3 flex items-center justify-center gap-2 text-[11px] text-zinc-500">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Menunggu pemindaian...</span>
                </div>
            </div>

            <!-- Scanning Guide Steps -->
            <div class="space-y-4 max-w-sm text-xs text-zinc-600">
                <div class="flex items-start gap-3">
                    <div class="w-6 h-6 rounded-full bg-black text-white flex items-center justify-center font-bold text-[11px] shrink-0">1</div>
                    <div>
                        <div class="font-bold text-zinc-900">Buka WhatsApp di Ponsel</div>
                        <div class="text-[11px] text-zinc-500">Gunakan nomor resmi travel <strong class="text-black"><?= htmlspecialchars($currentBrand['name'] ?? '') ?></strong>.</div>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-6 h-6 rounded-full bg-black text-white flex items-center justify-center font-bold text-[11px] shrink-0">2</div>
                    <div>
                        <div class="font-bold text-zinc-900">Pilih "Perangkat Tertaut"</div>
                        <div class="text-[11px] text-zinc-500">Ketuk titik tiga di pojok kanan atas (Android) atau menu Pengaturan (iPhone).</div>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-6 h-6 rounded-full bg-black text-white flex items-center justify-center font-bold text-[11px] shrink-0">3</div>
                    <div>
                        <div class="font-bold text-zinc-900">Tautkan Perangkat & Scan QR</div>
                        <div class="text-[11px] text-zinc-500">Arahkan kamera ponsel Anda ke kotak QR code di samping kiri.</div>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="button" @click="startSession()" :disabled="isLoading"
                        class="px-4 py-2 border border-zinc-300 hover:border-black rounded-xl text-xs font-semibold text-zinc-800 transition flex items-center gap-2">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                        <span>Muat Ulang QR Code</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 2b. CONNECTING SKELETON STATE (While waiting for QR from Gateway) -->
    <div x-show="status === 'connecting' && !qrCode" x-cloak class="bg-white border border-zinc-200 rounded-2xl p-6 sm:p-10 text-center space-y-5 shadow-2xs">
        <div class="w-16 h-16 mx-auto bg-zinc-100 rounded-2xl flex items-center justify-center border border-zinc-200">
            <svg class="animate-spin w-8 h-8 text-zinc-800" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
        </div>
        <div class="max-w-md mx-auto space-y-1.5">
            <h3 class="text-xl font-extrabold text-black">Menghubungi WhatsApp Gateway...</h3>
            <p class="text-xs text-zinc-500 leading-relaxed">
                Sedang menginisialisasi sesi Baileys dan membuat kode QR WhatsApp. Mohon tunggu sejenak.
            </p>
        </div>
        <div class="pt-2">
            <button type="button" @click="startSession()" :disabled="isLoading"
                class="px-4 py-2 border border-zinc-300 hover:border-black rounded-xl text-xs font-semibold text-zinc-800 transition inline-flex items-center gap-2">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                <span>Muat Ulang Permintaan</span>
            </button>
        </div>
    </div>

    <!-- 3. DISCONNECTED / IDLE STATE -->
    <div x-show="status === 'disconnected' && !qrCode" class="bg-white border border-zinc-200 rounded-2xl p-6 sm:p-10 text-center space-y-6 shadow-2xs">
        <div class="w-16 h-16 mx-auto bg-zinc-100 rounded-2xl flex items-center justify-center border border-zinc-200">
            <svg class="w-8 h-8 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                <line x1="2" y1="2" x2="22" y2="22"></line>
            </svg>
        </div>

        <div class="max-w-md mx-auto space-y-2">
            <h3 class="text-xl font-extrabold text-black">WhatsApp Belum Terhubung</h3>
            <p class="text-xs text-zinc-500 leading-relaxed">
                Nomor WhatsApp untuk brand <strong class="text-black"><?= htmlspecialchars($currentBrand['name'] ?? '') ?></strong> saat ini sedang tidak aktif. Hubungkan sekarang untuk mulai melayani calon jamaah langsung dari sistem.
            </p>
        </div>

        <div class="pt-2">
            <button type="button" @click="startSession()" :disabled="isLoading"
                class="px-6 py-3 bg-black hover:bg-zinc-800 text-white text-xs font-semibold rounded-xl transition shadow-sm inline-flex items-center gap-2">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                <span x-show="!isLoading">Minta QR Code & Hubungkan WhatsApp</span>
                <span x-show="isLoading">Menghubungkan ke Gateway...</span>
            </button>
        </div>

        <div class="text-[11px] text-zinc-400">
            Didukung oleh WhatsApp Multi-Device Gateway Baileys mandiri (tanpa biaya langganan bulanan).
        </div>
    </div>

</div>

<script>
function whatsappConnectApp(brandId) {
    return {
        brandId: brandId,
        status: 'disconnected',
        phoneNumber: null,
        qrCode: null,
        isLoading: false,
        pollTimer: null,

        init() {
            this.checkStatus();
            // Start polling every 3 seconds
            this.pollTimer = setInterval(() => {
                this.checkStatus();
            }, 3000);
        },

        changeBrand(newId) {
            window.location.href = 'whatsapp_connect.php?brand_id=' + newId;
        },

        async checkStatus() {
            try {
                const res = await fetch(`api/whatsapp.php?action=status&brand_id=${this.brandId}`);
                const data = await res.json();
                if (data.success) {
                    this.status = data.status;
                    this.phoneNumber = data.phone_number;
                    this.qrCode = data.qr_code;
                }
            } catch (err) {
                console.warn('Status check failed', err);
            }
        },

        async startSession() {
            this.isLoading = true;
            try {
                const res = await fetch('api/whatsapp.php?action=start_session', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ brand_id: this.brandId })
                });
                const data = await res.json();
                if (data.success) {
                    this.status = data.status;
                    this.qrCode = data.qr_code;
                    this.checkStatus();
                } else {
                    alert(data.error || 'Gagal memulai sesi WhatsApp.');
                }
            } catch (err) {
                alert('Gagal menghubungi server backend.');
            } finally {
                this.isLoading = false;
            }
        },

        async disconnectSession() {
            if (!confirm('Yakin ingin memutuskan sesi WhatsApp brand ini? Chat baru tidak akan otomatis masuk ke sistem.')) {
                return;
            }

            this.isLoading = true;
            try {
                const res = await fetch('api/whatsapp.php?action=logout_session', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ brand_id: this.brandId })
                });
                const data = await res.json();
                if (data.success) {
                    this.status = 'disconnected';
                    this.phoneNumber = null;
                    this.qrCode = null;
                }
            } catch (err) {
                alert('Gagal memutuskan sesi.');
            } finally {
                this.isLoading = false;
            }
        }
    };
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

