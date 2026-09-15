<?php
$isFullHeight = true;
$pageTitle = 'Live WhatsApp Chat & Copilot - CS Umroh';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/data/scripts_loader.php';

$db = get_db();
if ($user['role'] === 'superadmin') {
    if (!empty($_GET['brand_id'])) {
        $userBrandId = (int)$_GET['brand_id'];
    } else {
        // Auto-detect connected WhatsApp session brand if available, else default to 1
        $connBrandStmt = $db->query("SELECT brand_id FROM whatsapp_sessions WHERE status = 'connected' ORDER BY last_connected_at DESC LIMIT 1");
        $connBrand = $connBrandStmt ? $connBrandStmt->fetchColumn() : null;
        $userBrandId = $connBrand ? (int)$connBrand : 1;
    }
} else {
    $userBrandId = $user['brand_id'] ?: 1;
}

// Fetch brand details
$stmtB = $db->prepare("SELECT * FROM brands WHERE id = ?");
$stmtB->execute([$userBrandId]);
$brand = $stmtB->fetch();

// Fetch brand packages
$stmtP = $db->prepare("SELECT * FROM packages WHERE brand_id = ? AND is_active = 1 ORDER BY id ASC");
$stmtP->execute([$userBrandId]);
$packages = $stmtP->fetchAll();

// Fetch Copilot scripts
$allScripts = get_all_scripts();
$scriptsJson = json_encode($allScripts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Superadmin brands
$brandsList = [];
if ($isSuperAdmin) {
    $brandsList = $db->query("SELECT id, name, code, phone FROM brands ORDER BY id ASC")->fetchAll();
}

$activeJidParam = $_GET['jid'] ?? '';
$activeProspectIdParam = (int)($_GET['prospect_id'] ?? 0);
?>

<script>
window.chatWorkspaceConfig = {
    brandId: <?= (int)$userBrandId ?>,
    csName: <?= json_encode($user['name'] ?? 'CS') ?>,
    brandName: <?= json_encode($brand['name'] ?? 'Travel Umroh') ?>,
    brandBank: <?= json_encode(!empty($brand['bank_name']) ? ($brand['bank_name'] . ' No. ' . ($brand['bank_account_number'] ?? '')) : 'Bank Rekening Resmi Perusahaan') ?>,
    brandHolder: <?= json_encode($brand['bank_account_holder'] ?? ($brand['name'] ?? '')) ?>,
    brandPpiu: <?= json_encode($brand['ppiu_number'] ?? '-') ?>,
    packages: <?= json_encode($packages) ?>,
    rawScripts: <?= $scriptsJson ?: '{}' ?>,
    initialJid: <?= json_encode($activeJidParam) ?>,
    initialProspectId: <?= (int)$activeProspectIdParam ?>
};
</script>

<style>
.wa-chat-canvas {
    background-color: #efeae2;
    background-image: url("data:image/svg+xml,%3Csvg width='80' height='80' viewBox='0 0 80 80' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23c9bfb2' fill-opacity='0.22' fill-rule='evenodd'%3E%3Cpath d='M11 14.01V7a1 1 0 0 1 2 0v7.01A5 5 0 0 1 18 19v1a1 1 0 0 1-2 0v-1a3 3 0 0 0-3-3h-2a3 3 0 0 0-3 3v1a1 1 0 0 1-2 0v-1a5 5 0 0 1 5-4.99zM35 4a4 4 0 1 1 0 8 4 4 0 0 1 0-8zm0 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM12 60c1.66 0 3 1.34 3 3 0 1.3-.84 2.4-2 2.82V68a1 1 0 0 1-2 0v-2.18A2.99 2.99 0 0 1 9 63c0-1.66 1.34-3 3-3zm50-40c2.76 0 5 2.24 5 5s-2.24 5-5 5-5-2.24-5-5 2.24-5 5-5zm-2 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6zm-30 45a4 4 0 1 1 0 8 4 4 0 0 1 0-8zm0 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm36-22a3 3 0 1 1 0 6 3 3 0 0 1 0-6zm-18-20a2 2 0 1 1 0 4 2 2 0 0 1 0-4z'/%3E%3C/g%3E%3C/svg%3E");
}
</style>

<div class="flex-1 flex flex-col h-full bg-white overflow-hidden min-h-0" 
     x-data="whatsappChatWorkspace(window.chatWorkspaceConfig)"
     x-init="init()">

    <!-- Top Workspace Sub-Header (Monochrome Minimalist) -->
    <div class="h-12 border-b border-zinc-200/80 px-4 sm:px-6 flex items-center justify-between bg-zinc-50/70 shrink-0 select-none">
        <div class="flex items-center gap-2.5">
            <!-- Connection Pulse Dot -->
            <div class="w-2.5 h-2.5 rounded-full shrink-0" :class="connectionStatus === 'connected' ? 'bg-emerald-500 animate-pulse' : (connectionStatus === 'qr_ready' ? 'bg-amber-500' : 'bg-zinc-400')" title="Status Koneksi"></div>

            <!-- Opsi Pilih Travel Dropdown (Pindah ke Kiri) -->
            <?php if ($isSuperAdmin): ?>
                <div x-data="{ openBrand: false }" class="relative" @click.outside="openBrand = false">
                    <button type="button" @click="openBrand = !openBrand"
                            class="px-2.5 py-1 bg-white hover:bg-zinc-50 border border-zinc-300 rounded-lg text-xs font-bold text-zinc-900 transition flex items-center gap-1.5 shadow-2xs cursor-pointer">
                        <span><?= htmlspecialchars($brand['name'] ?? 'Pilih Brand') ?></span>
                        <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0" :class="openBrand ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>
                    <div x-show="openBrand" x-cloak x-transition.opacity.duration.150ms
                         class="absolute left-0 z-50 mt-1 min-w-[200px] bg-white border border-zinc-200 rounded-xl shadow-xl py-1 text-xs divide-y divide-zinc-100">
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
            <?php else: ?>
                <span class="text-xs font-bold text-black"><?= htmlspecialchars($brand['name'] ?? 'Brand Travel') ?></span>
            <?php endif; ?>
            
            <a href="whatsapp_connect.php?brand_id=<?= $userBrandId ?>" 
               title="Kelola Koneksi WhatsApp"
               class="px-2 py-0.5 rounded text-[11px] font-medium border border-zinc-300 bg-white hover:bg-zinc-100 text-zinc-700 transition flex items-center gap-1">
                <span x-text="connectionStatus === 'connected' ? 'Terhubung' : 'Scan QR / Sambungkan'"></span>
            </a>
        </div>

        <div class="flex items-center gap-2 sm:gap-3">
            <!-- Mobile View Toggle Buttons -->
            <div class="flex lg:hidden items-center bg-zinc-200/60 p-0.5 rounded-lg text-xs font-semibold">
                <button type="button" @click="mobileTab = 'chats'" 
                        :class="mobileTab === 'chats' ? 'bg-white text-black shadow-2xs' : 'text-zinc-600'" 
                        class="px-2.5 py-1 rounded-md transition">Chat</button>
                <button type="button" @click="mobileTab = 'thread'" 
                        :class="mobileTab === 'thread' ? 'bg-white text-black shadow-2xs' : 'text-zinc-600'" 
                        class="px-2.5 py-1 rounded-md transition">Percakapan</button>
                <button type="button" @click="mobileTab = 'prospect'; prospectPanelOpen = true" 
                        :class="mobileTab === 'prospect' ? 'bg-white text-black shadow-2xs' : 'text-zinc-600'" 
                        class="px-2.5 py-1 rounded-md transition">Prospek</button>
            </div>

            <!-- Right Panel Tab Switcher (Desktop) -->
            <div class="hidden lg:flex items-center bg-zinc-100 border border-zinc-200 rounded-xl p-0.5 gap-0.5">
                <button type="button"
                        @click="prospectPanelOpen = true; rightTab = 'prospect'"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition"
                        :class="prospectPanelOpen && rightTab === 'prospect' ? 'bg-black text-white shadow-sm' : 'text-zinc-600 hover:text-black hover:bg-zinc-200'">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span>Detail Prospek</span>
                </button>
                <button type="button"
                        @click="prospectPanelOpen = true; rightTab = 'copilot'; loadCopilotScripts()"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition"
                        :class="prospectPanelOpen && rightTab === 'copilot' ? 'bg-black text-white shadow-sm' : 'text-zinc-600 hover:text-black hover:bg-zinc-200'">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    <span>Copilot</span>
                </button>
            </div>
        </div>


    </div>

    <!-- 3-COLUMN INTEGRATED WORKSPACE -->
    <div class="flex-1 flex overflow-hidden min-h-0">
        
        <!-- ============================================================== -->
        <!-- COLUMN 1: CONTACTS & CHAT LIST (Width ~30% / 320px-380px)       -->
        <!-- ============================================================== -->
        <div class="w-full lg:w-80 xl:w-96 border-r border-zinc-200 flex flex-col shrink-0 bg-white min-h-0"
             :class="{ 'hidden lg:flex': mobileTab !== 'chats', 'flex': mobileTab === 'chats' }">
            
            <!-- When Connected: Search & New Chat Bar -->
            <div x-show="connectionStatus === 'connected'" class="p-3 border-b border-zinc-200 bg-[#f0f2f5] space-y-2">
                <div class="relative">
                    <svg class="w-3.5 h-3.5 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" x-model="searchQuery" @input="filterChats()" placeholder="Cari nama, nomor WA, chat..." 
                           class="w-full pl-8 pr-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs text-zinc-800 placeholder-zinc-400 focus:outline-none focus:border-[#00a884] focus:ring-1 focus:ring-[#00a884] transition shadow-2xs">
                </div>

                <div class="flex items-center justify-between text-[11px] text-zinc-500 px-1">
                    <span class="font-medium" x-text="`${filteredChats.length} Percakapan`"></span>
                    <button type="button" @click="openNewChatModal = true" class="font-bold text-[#00a884] hover:underline inline-flex items-center gap-1">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <span>Chat Nomor Baru</span>
                    </button>
                </div>
            </div>

            <!-- When Disconnected: Disconnected Message in Column 1 -->
            <div x-show="connectionStatus !== 'connected'" class="flex-1 flex flex-col items-center justify-center p-6 text-center">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 border border-zinc-200 flex items-center justify-center mb-3 shadow-2xs">
                    <svg class="w-6 h-6 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                        <path d="M10.68 13.31a16 16 0 0 0 3.41 2.6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7 2 2 0 0 1 1.72 2v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.42 19.42 0 0 1-3.33-2.67m-2.67-3.34a19.79 19.79 0 0 1-3.07-8.63A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91"/>
                        <line x1="22" y1="2" x2="2" y2="22"/>
                    </svg>
                </div>
                <h4 class="font-bold text-xs text-black">WhatsApp Terputus</h4>
                <p class="text-[11px] text-zinc-400 mt-1 max-w-[200px] leading-relaxed">
                    Layar live chat kosong karena WhatsApp untuk brand ini sedang tidak terhubung.
                </p>
                <a :href="`whatsapp_connect.php?brand_id=${brandId}`" 
                   class="mt-4 px-3 py-1.5 bg-[#00a884] hover:bg-[#02906f] text-white text-[11px] font-semibold rounded-lg transition inline-flex items-center gap-1.5 shadow-2xs">
                    <span>Scan QR / Sambungkan</span>
                </a>
            </div>

            <!-- Chat Threads List (Only when Connected) -->
            <div x-show="connectionStatus === 'connected'" class="flex-1 overflow-y-auto divide-y divide-zinc-100">
                <template x-if="filteredChats.length === 0">
                    <div class="p-8 text-center text-xs text-zinc-400">
                        <svg class="w-8 h-8 mx-auto text-zinc-300 mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        <p class="font-semibold text-zinc-700">Belum ada percakapan</p>
                        <p class="text-[11px] text-zinc-400 mt-1">Pesan dari calon jamaah akan otomatis muncul di sini.</p>
                    </div>
                </template>

                <template x-for="c in filteredChats" :key="c.remote_jid">
                    <div @click="selectChat(c)"
                         class="p-3.5 transition cursor-pointer flex items-start gap-3 select-none relative"
                         :class="activeChat && activeChat.remote_jid === c.remote_jid 
                                    ? 'bg-[#f0f2f5] border-l-4 border-[#00a884]' 
                                    : (isChatUnread(c) 
                                        ? 'bg-[#f0fbf7] hover:bg-[#e7f7f1] border-l-4 border-[#25d366]' 
                                        : 'bg-white hover:bg-[#f5f6f6] border-l-4 border-transparent')">
                        
                        <!-- Avatar with unread indicator -->
                        <div class="relative shrink-0">
                            <div class="w-10 h-10 rounded-full overflow-hidden text-white font-bold text-xs flex items-center justify-center border transition"
                                 :class="isChatUnread(c) 
                                            ? 'bg-[#00a884] border-[#25d366] shadow-2xs ring-2 ring-emerald-100' 
                                            : 'bg-[#6b7c85] border-zinc-200'">
                                <template x-if="c.photo_url">
                                    <img :src="c.photo_url" :alt="getChatTitle(c)" class="w-full h-full object-cover" @error="c.photo_url = null">
                                </template>
                                <template x-if="!c.photo_url">
                                    <span x-text="getAvatarLetter(c)"></span>
                                </template>
                            </div>
                            <!-- Unread dot on avatar -->
                            <template x-if="isChatUnread(c)">
                                <span class="absolute -top-0.5 -right-0.5 w-3 h-3 bg-[#25d366] border-2 border-white rounded-full ring-1 ring-emerald-300"></span>
                            </template>
                        </div>

                        <!-- Chat Details -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <div class="text-xs truncate" 
                                     :class="isChatUnread(c) ? 'font-black text-[#111b21]' : 'font-bold text-[#111b21]'" 
                                     x-text="getChatTitle(c)"></div>
                                
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <span class="text-[10px] shrink-0" 
                                          :class="isChatUnread(c) ? 'font-bold text-[#25d366]' : 'text-[#667781]'" 
                                          x-text="formatChatListTime(c.last_timestamp)"></span>
                                    
                                    <!-- Unread Message Counter Badge -->
                                    <template x-if="isChatUnread(c)">
                                        <span class="min-w-[20px] h-[20px] px-1.5 bg-[#25d366] text-white text-[10px] font-black rounded-full flex items-center justify-center shrink-0 shadow-2xs leading-none"
                                              x-text="getUnreadCountText(c)"></span>
                                    </template>
                                </div>
                            </div>

                            <div class="text-xs truncate mt-1" 
                                 :class="isChatUnread(c) ? 'font-semibold text-zinc-900' : 'text-zinc-500'">
                                <template x-if="c.last_is_deleted == 1">
                                    <span class="italic text-zinc-400 inline-flex items-center gap-1">
                                        <svg class="w-3 h-3 text-zinc-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                                        <span>Pesan telah ditarik</span>
                                    </span>
                                </template>
                                <template x-if="c.last_is_deleted != 1">
                                    <span>
                                        <span x-show="c.last_is_from_me == 1" class="font-semibold text-zinc-700">Anda: </span>
                                        <span x-text="c.last_message || 'Lampiran media'"></span>
                                    </span>
                                </template>
                            </div>

                            <!-- Stage & Status Tag -->
                            <div class="mt-1.5 flex items-center gap-1.5 flex-wrap">
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border shadow-2xs" 
                                      :class="getStatusBadgeClass(c.prospect_status)"
                                      x-text="getStatusLabel(c.prospect_status)"></span>
                                <template x-if="c.meta_referral_marker">
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-black text-white shrink-0" title="Masuk dari Iklan Meta CTWA">
                                        Meta Ad
                                    </span>
                                </template>
                                <template x-if="c.package_name">
                                    <span class="text-[10px] text-zinc-400 truncate max-w-[120px]" x-text="`&bull; ${c.package_name}`"></span>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- COLUMN 2: LIVE WHATSAPP BUBBLE THREAD (Flex-1)                 -->
        <!-- ============================================================== -->
        <div class="flex-1 min-w-0 flex flex-col bg-[#FAF9F6] border-r border-zinc-200 min-h-0"
             :class="{ 'hidden lg:flex': mobileTab !== 'thread', 'flex': mobileTab === 'thread' }">
            
            <!-- State 1: When WhatsApp is Disconnected -->
            <template x-if="connectionStatus !== 'connected'">
                <div class="flex-1 flex flex-col items-center justify-center p-8 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-white border border-zinc-200 flex items-center justify-center mb-4 shadow-2xs">
                        <svg class="w-8 h-8 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                            <path d="M10.68 13.31a16 16 0 0 0 3.41 2.6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7 2 2 0 0 1 1.72 2v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.42 19.42 0 0 1-3.33-2.67m-2.67-3.34a19.79 19.79 0 0 1-3.07-8.63A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91"/>
                            <line x1="22" y1="2" x2="2" y2="22"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-zinc-900">Layar Chat Live Kosong (WhatsApp Terputus)</h3>
                    <p class="text-xs text-zinc-500 max-w-sm mt-1.5 leading-relaxed">
                        Sesi WhatsApp untuk brand ini sedang terputus atau belum dihubungkan. Silakan sambungkan WhatsApp untuk memuat chat dan membalas pesan secara langsung.
                    </p>
                    <a :href="`whatsapp_connect.php?brand_id=${brandId}`" 
                       class="mt-5 px-4 py-2 bg-black hover:bg-zinc-800 text-white text-xs font-semibold rounded-xl transition inline-flex items-center gap-2 shadow-2xs">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                            <path d="M12 18h.01"/>
                        </svg>
                        <span>Sambungkan WhatsApp Sekarang</span>
                    </a>
                </div>
            </template>

            <!-- State 2: When Connected and No Chat Selected -->
            <template x-if="connectionStatus === 'connected' && !activeChat">
                <div class="flex-1 flex flex-col items-center justify-center p-8 text-center text-zinc-400">
                    <div class="w-16 h-16 rounded-2xl bg-white border border-zinc-200 flex items-center justify-center mb-3 shadow-2xs">
                        <svg class="w-8 h-8 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-zinc-800">Pilih Percakapan WhatsApp</h3>
                    <p class="text-xs text-zinc-400 max-w-xs mt-1">
                        Pilih kontak jamaah dari daftar di sebelah kiri untuk membaca chat dan membalas langsung.
                    </p>
                </div>
            </template>

            <!-- State 3: When Connected and Active Chat Selected -->
            <template x-if="connectionStatus === 'connected' && activeChat">
                <div class="flex-1 flex flex-col h-full overflow-hidden min-h-0">

                    
                    <!-- Chat Header with Prospect Quick Actions -->
                    <div class="h-16 bg-[#f0f2f5] border-b border-zinc-200 px-4 flex items-center justify-between shrink-0 shadow-2xs">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-full overflow-hidden bg-[#00a884] text-white font-bold text-xs flex items-center justify-center shrink-0 border border-zinc-200 shadow-2xs">
                                <template x-if="activeChat && activeChat.photo_url">
                                    <img :src="activeChat.photo_url" :alt="getChatTitle(activeChat)" class="w-full h-full object-cover" @error="activeChat.photo_url = null">
                                </template>
                                <template x-if="!activeChat || !activeChat.photo_url">
                                    <span x-text="getAvatarLetter(activeChat)"></span>
                                </template>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-xs sm:text-sm font-bold text-[#111b21] truncate" x-text="getChatTitle(activeChat)"></h3>
                                    <template x-if="activeChat.meta_referral_marker">
                                        <span class="px-1.5 py-0.5 text-[9px] font-bold bg-black text-white rounded">
                                            Meta CTWA
                                        </span>
                                    </template>
                                </div>
                                <div class="flex items-center gap-2 text-[11px] text-zinc-500">
                                    <template x-if="hasValidPhone(activeChat.phone)">
                                        <div class="flex items-center gap-1.5 bg-zinc-100 px-2 py-0.5 rounded border border-zinc-200">
                                            <svg class="w-3.5 h-3.5 text-zinc-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                            <span class="font-mono text-black font-bold text-xs" x-text="formatIndoPhone(activeChat.phone)"></span>
                                        </div>
                                    </template>
                                    <template x-if="!hasValidPhone(activeChat.phone)">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-[10px] px-2 py-0.5 rounded bg-zinc-100 border border-zinc-200 text-zinc-500 font-medium italic">
                                                No. WA tidak diketahui
                                            </span>
                                            <button type="button" @click="openEditContactModal(activeChat)"
                                                    class="text-[10px] font-bold text-black hover:underline inline-flex items-center gap-0.5">
                                                <span>+ Masukkan No. HP</span>
                                            </button>
                                        </div>
                                    </template>
                                    <template x-if="!activeChat.prospect_id">
                                        <button type="button" @click="prospectPanelOpen = true; mobileTab = 'prospect'"
                                           class="text-[10px] font-bold text-black hover:underline inline-flex items-center gap-0.5 ml-1 bg-zinc-100 hover:bg-zinc-200 px-1.5 py-0.5 rounded border border-zinc-200 transition cursor-pointer">
                                            <span>+ Daftarkan Prospek</span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                        </div>

                        <!-- Status Selector Dropdown (Instant Auto Meta CAPI on Closing Won) -->
                        <div class="flex items-center gap-2">
                            <template x-if="activeChat.prospect_id">
                                <div x-data="{ openStatus: false }" 
                                class="relative flex items-center gap-1.5" 
                                @click.outside="openStatus = false">
                                    <label class="text-[10px] font-semibold text-zinc-400 hidden sm:inline">Status:</label>
                                    <button type="button" @click="openStatus = !openStatus"
                                            class="px-2.5 py-1 rounded-lg text-xs font-bold transition flex items-center gap-1.5 shadow-2xs border"
                                            :class="getStatusBadgeClass(activeChat.prospect_status || 'new')">
                                        <span x-text="getStatusLabel(activeChat.prospect_status || 'new')"></span>
                                        <svg class="w-3 h-3 opacity-70 transition-transform duration-200 shrink-0" :class="openStatus ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="m6 9 6 6 6-6"/>
                                        </svg>
                                    </button>
                                    <div x-show="openStatus" x-cloak x-transition.opacity.duration.150ms
                                         class="absolute right-0 top-full z-50 mt-1.5 w-60 bg-white border border-zinc-200 rounded-xl shadow-xl py-1.5 text-xs divide-y divide-zinc-50">
                                        <template x-for="st in statusOptions" :key="st.val">
                                            <button type="button" 
                                                    @click="updateProspectStatus(st.val); openStatus = false"
                                                    class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                                    :class="(activeChat.prospect_status || 'new') === st.val ? 'bg-zinc-50 font-bold' : ''">
                                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold border shadow-2xs"
                                                      :class="getStatusBadgeClass(st.val)"
                                                      x-text="st.label"></span>
                                                <svg x-show="(activeChat.prospect_status || 'new') === st.val" class="w-3.5 h-3.5 text-black shrink-0 ml-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                    <polyline points="20 6 9 17 4 12"/>
                                                </svg>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Meta Ad CTWA Attribution Banner (if applicable) -->
                    <template x-if="activeChat.meta_referral_marker">
                        <div class="px-4 py-1.5 bg-zinc-100 border-b border-zinc-200 text-[11px] text-zinc-700 flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-zinc-900" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                                <span>Iklan Meta CTWA: <strong class="text-black" x-text="activeChat.meta_referral_marker"></strong></span>
                            </div>
                            <span class="text-[10px] text-zinc-500">CAPI Tracking Siap</span>
                        </div>
                    </template>

                    <!-- Bubble Chat Stream (WhatsApp Doodle Background) -->
                    <div class="relative flex-1 flex flex-col min-h-0">
                        <div id="chatStreamBox" 
                             @scroll.passive="onChatScroll()"
                             class="flex-1 overflow-y-auto overflow-x-hidden p-4 space-y-3 min-h-0 wa-chat-canvas">
                            <template x-if="messagesLoading">
                            <div class="text-center py-6 text-xs text-zinc-400">Memuat riwayat chat...</div>
                        </template>

                        <template x-for="(m, idx) in activeMessages" :key="m.message_id || m.id">
                            <div class="space-y-3">
                                <!-- Date Divider between Days (WhatsApp Web Style) -->
                                <template x-if="shouldShowDateDivider(activeMessages, idx)">
                                    <div class="flex items-center justify-center my-3.5 select-none">
                                        <div class="px-3 py-1 rounded-lg bg-white/95 border border-zinc-200/80 text-[11px] font-medium text-zinc-600 shadow-[0_1px_0.5px_rgba(11,20,26,0.13)] tracking-wide">
                                            <span x-text="formatDateDivider(m.timestamp)"></span>
                                        </div>
                                    </div>
                                </template>

                                <div class="flex flex-col group/msg" :class="m.is_from_me == 1 ? 'items-end' : 'items-start'">
                                    <div class="relative max-w-[78%] sm:max-w-[70%]">
                                    
                                    <!-- Action Toolbar (Opposite Side on Hover: Left for CS, Right for Prospek) -->
                                    <template x-if="m.is_deleted != 1">
                                        <div class="absolute top-1.5 opacity-0 group-hover/msg:opacity-100 focus-within:opacity-100 transition duration-150 z-20 pointer-events-none group-hover/msg:pointer-events-auto select-none whitespace-nowrap"
                                             :class="m.is_from_me == 1 ? 'right-full mr-2' : 'left-full ml-2'">
                                            <div class="flex items-center gap-0.5 px-1.5 py-0.5 bg-white border border-zinc-200/90 rounded-full shadow-[0_2px_5px_rgba(0,0,0,0.14)]">
                                                <!-- React Trigger Button with Quick Reaction Popover -->
                                                <div x-data="{ openReact: false }" class="relative inline-flex items-center" @click.outside="openReact = false">
                                                    <button type="button" 
                                                            @click="openReact = !openReact" 
                                                            title="Beri reaksi emoji"
                                                            class="w-6 h-6 rounded-full flex items-center justify-center text-zinc-500 hover:text-black hover:bg-zinc-100 transition">
                                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/>
                                                        </svg>
                                                    </button>

                                                    <!-- Quick React Floating Toolbar -->
                                                    <div x-show="openReact" 
                                                         x-cloak 
                                                         class="absolute bottom-full mb-1.5 left-1/2 -translate-x-1/2 z-30 flex items-center gap-1 p-1 bg-white border border-zinc-200 rounded-full shadow-xl">
                                                        <template x-for="r in ['👍', '❤️', '😂', '😮', '😢', '🙏']" :key="r">
                                                            <button type="button" 
                                                                    @click="toggleReaction(m, r); openReact = false"
                                                                    class="w-7 h-7 rounded-full flex items-center justify-center text-sm hover:scale-125 hover:bg-zinc-100 transition"
                                                                    :class="m.reaction === r ? 'bg-zinc-100 ring-2 ring-emerald-500' : ''"
                                                                    x-text="r"></button>
                                                        </template>
                                                    </div>
                                                </div>

                                                <!-- Reply Button -->
                                                <button type="button" 
                                                        @click="setReplyingTo(m)" 
                                                        title="Balas / Kutip pesan ini"
                                                        class="w-6 h-6 rounded-full flex items-center justify-center text-zinc-500 hover:text-black hover:bg-zinc-100 transition">
                                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/>
                                                    </svg>
                                                </button>

                                                <!-- Delete Button (Outbound Only) -->
                                                <template x-if="m.is_from_me == 1">
                                                    <button type="button" 
                                                            @click="openDeleteModal(m)" 
                                                            title="Tarik / Hapus pesan ini dari WhatsApp"
                                                            class="w-6 h-6 rounded-full flex items-center justify-center text-zinc-500 hover:text-red-600 hover:bg-zinc-100 transition">
                                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M3 6h18m-2 0v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6m3 0V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                                                        </svg>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                     <!-- BUBBLE CASE 1: DELETED / REVOKED MESSAGE -->
                                     <template x-if="m.is_deleted == 1">
                                         <div class="w-full rounded-lg p-2.5 sm:p-3 shadow-[0_1px_0.5px_rgba(11,20,26,0.13)] text-xs leading-relaxed"
                                              :class="(m.is_from_me == 1 ? 'bg-[#d9fdd3]/80 text-[#667781] rounded-tr-none' : 'bg-white/90 text-[#667781] rounded-tl-none')">
                                             
                                             <!-- Revoked Header Row -->
                                             <div class="flex items-center justify-between gap-3">
                                                 <div class="flex items-center gap-1.5 italic text-[#8696a0] font-medium text-xs">
                                                     <svg class="w-3.5 h-3.5 text-[#8696a0] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                         <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                                                     </svg>
                                                     <span x-text="m.is_from_me == 1 ? ('Pesan ditarik oleh ' + (m.sender_name ? ('CS ' + m.sender_name) : 'Anda')) : 'Pesan ditarik oleh pengirim'"></span>
                                                 </div>

                                                 <!-- Eye Toggle Icon Button (Pure Icon) -->
                                                 <button type="button" 
                                                         @click="m.show_deleted = !m.show_deleted" 
                                                         :title="m.show_deleted ? 'Sembunyikan' : 'Tampilkan teks asli'"
                                                         class="w-6 h-6 rounded-md text-zinc-600 hover:text-black hover:bg-zinc-200/80 transition flex items-center justify-center border border-zinc-200 bg-white shadow-2xs shrink-0">
                                                     <!-- Eye Open Icon (when hidden) -->
                                                     <template x-if="!m.show_deleted">
                                                         <svg class="w-3.5 h-3.5 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                             <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                                             <circle cx="12" cy="12" r="3"/>
                                                         </svg>
                                                     </template>
                                                     <!-- Eye Off Icon (when revealed) -->
                                                     <template x-if="m.show_deleted">
                                                         <svg class="w-3.5 h-3.5 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                             <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24m-6.72-1.07A10.66 10.66 0 0 1 2 12s3-7 10-7a9.74 9.74 0 0 1 5.39 1.61M12 19c-3.14 0-6.1-1.39-8.2-3.82M21.94 13.91C21.98 13.28 22 12.64 22 12c0-2.39-1.2-5.17-3.8-7.17"/>
                                                             <line x1="2" y1="2" x2="22" y2="22"/>
                                                         </svg>
                                                     </template>
                                                 </button>
                                             </div>

                                             <!-- Revealed Original Text (No redundant labels) -->
                                             <div x-show="m.show_deleted" x-cloak class="mt-2.5 pt-2 border-t border-dashed border-zinc-300">
                                                 <div class="whitespace-pre-wrap break-words text-xs text-zinc-800 bg-white p-2.5 rounded-lg border border-zinc-200 font-sans shadow-2xs" x-text="m.message_text"></div>
                                             </div>

                                             <!-- Timestamp & Revoked Tag -->
                                             <div class="mt-1.5 flex items-center justify-end gap-1 text-[9px] text-[#8696a0]">
                                                 <span x-text="formatBubbleTime(m.timestamp)"></span>
                                                 <span class="text-[9px] font-semibold text-[#8696a0]">&bull; Ditarik</span>
                                             </div>
                                         </div>
                                     </template>

                                     <!-- BUBBLE CASE 2: NORMAL ACTIVE MESSAGE (WHATSAPP 1:1 COLOR PALETTE) -->
                                     <template x-if="m.is_deleted != 1">
                                         <div class="w-full rounded-lg p-2.5 sm:p-3 shadow-[0_1px_0.5px_rgba(11,20,26,0.13)] text-xs leading-relaxed relative"
                                              :class="(m.is_from_me == 1 ? 'bg-[#d9fdd3] text-[#111b21] rounded-tr-none' : 'bg-white text-[#111b21] rounded-tl-none') + (m.reaction ? ' mb-2.5' : '')">
                                             
                                             <!-- Quoted / Replied Message Inside Bubble -->
                                             <template x-if="m.quoted_text">
                                                 <div class="mb-2 p-2 rounded-lg border-l-4 text-[11px] leading-tight select-none"
                                                      :class="m.is_from_me == 1 ? 'bg-[#cbf0c2] border-[#00a884] text-[#111b21]' : 'bg-[#f0f2f5] border-[#00a884] text-[#111b21]'">
                                                     <div class="font-bold text-[10px] mb-0.5 truncate text-[#00a884]"
                                                          x-text="m.quoted_sender || (m.is_from_me == 1 ? 'Calon Jamaah' : 'Anda')"></div>
                                                     <div class="truncate italic text-zinc-600" x-text="m.quoted_text"></div>
                                                 </div>
                                             </template>

                                             <!-- Sender PushName on Inbound -->
                                             <template x-if="m.is_from_me == 0 && m.sender_name">
                                                 <div class="font-bold text-[10px] text-[#00a884] mb-1" x-text="m.sender_name"></div>
                                             </template>

                                             <!-- Sender CS Name on Outbound (Multi-CS Collaboration) -->
                                             <template x-if="m.is_from_me == 1 && m.sender_name">
                                                 <div class="font-bold text-[10px] text-emerald-800 mb-1" x-text="m.sender_name"></div>
                                             </template>

                                             <!-- Image Attachment Thumbnail Preview -->
                                             <template x-if="m.media_url && isImageMessage(m)">
                                                 <div class="mb-2 overflow-hidden rounded-xl border max-w-sm" :class="m.is_from_me == 1 ? 'border-[#b9e6b1] bg-[#d9fdd3]' : 'border-zinc-200 bg-zinc-50'">
                                                     <img :src="m.media_url" 
                                                          @click="openLightbox(m.media_url)"
                                                          class="w-full max-h-64 object-cover rounded-lg cursor-pointer hover:opacity-90 transition block" 
                                                          alt="Gambar" 
                                                          loading="lazy">
                                                 </div>
                                             </template>

                                             <!-- Video Attachment Player -->
                                             <template x-if="m.media_url && isVideoMessage(m)">
                                                 <div class="mb-2 overflow-hidden rounded-xl border max-w-sm bg-black"
                                                      :class="m.is_from_me == 1 ? 'border-[#b9e6b1]' : 'border-zinc-200'">
                                                     <video controls playsinline class="w-full max-h-72 object-contain rounded-lg block" preload="metadata" :src="m.media_url">
                                                         Browser Anda tidak mendukung pemutar video HTML5.
                                                     </video>
                                                 </div>
                                             </template>

                                             <!-- Voice Note / Audio Player (Clean WhatsApp Style - Zero Over-wording) -->
                                             <template x-if="m.media_url && isAudioMessage(m)">
                                                 <div class="mb-1 flex items-center gap-2 min-w-[240px] sm:min-w-[270px]">
                                                     <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 bg-[#00a884] text-white shadow-2xs">
                                                         <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                             <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/>
                                                             <path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/>
                                                         </svg>
                                                     </div>
                                                     <audio controls class="flex-1 min-w-0 h-8" preload="metadata" :src="m.media_url">
                                                         Browser Anda tidak mendukung pemutar audio.
                                                     </audio>
                                                 </div>
                                             </template>

                                             <!-- Document / File Attachment Card -->
                                             <template x-if="m.media_url && !isImageMessage(m) && !isVideoMessage(m) && !isAudioMessage(m)">
                                                 <div class="mb-2 p-2.5 rounded-xl border flex items-center justify-between gap-3 max-w-sm"
                                                      :class="m.is_from_me == 1 ? 'bg-[#c5ebb7] border-[#b0dc9f] text-[#111b21]' : 'bg-[#f0f2f5] border-zinc-200 text-[#111b21]'">
                                                     <div class="flex items-center gap-2.5 min-w-0">
                                                         <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 bg-[#00a884] text-white">
                                                             <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                 <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                                                                 <polyline points="14 2 14 8 20 8"/>
                                                             </svg>
                                                         </div>
                                                         <div class="min-w-0">
                                                             <div class="font-semibold text-xs truncate" x-text="getDocumentName(m)"></div>
                                                         </div>
                                                     </div>
                                                     <a :href="m.media_url" download target="_blank"
                                                        class="px-2.5 py-1 rounded-md text-[11px] font-semibold transition shrink-0 flex items-center gap-1 bg-white hover:bg-zinc-50 text-zinc-800 border border-zinc-200 shadow-2xs">
                                                         <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                                         <span>Unduh</span>
                                                     </a>
                                                 </div>
                                             </template>

                                            <!-- Message Body & Inline Docked Timestamp (WhatsApp Web Style) -->
                                            <div class="text-xs leading-relaxed break-words overflow-hidden">
                                                <template x-if="hasDisplayText(m)">
                                                    <span class="whitespace-pre-wrap" x-text="m.message_text"></span>
                                                </template>

                                                <!-- Docked Inline Timestamp & Read Status -->
                                                <span class="float-right ml-2.5 mt-1 -mb-0.5 inline-flex items-center gap-1 text-[10px] select-none shrink-0 text-[#667781]">
                                                    <span x-text="formatBubbleTime(m.timestamp)"></span>
                                                    <template x-if="m.is_from_me == 1">
                                                        <span class="inline-flex items-center ml-0.5">
                                                            <!-- Case 1: Read by Prospect (Centang Biru) -->
                                                            <template x-if="isMessageRead(m)">
                                                                <svg class="w-3.5 h-3.5 text-[#53bdeb] shrink-0" title="Telah dibaca oleh prospek (Read)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                                    <path d="M18 6 7 17l-5-5"/>
                                                                    <path d="m22 10-7.5 7.5L13 16"/>
                                                                </svg>
                                                            </template>
                                                            <!-- Case 2: Sent to Server (Centang 1 Abu-abu) -->
                                                            <template x-if="!isMessageRead(m) && isMessageSent(m)">
                                                                <svg class="w-3.5 h-3.5 text-[#8696a0] shrink-0" title="Terkirim (Sent)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                    <path d="M20 6 9 17l-5-5"/>
                                                                </svg>
                                                            </template>
                                                            <!-- Case 3: Delivered to Prospect (Centang 2 Abu-abu) -->
                                                            <template x-if="!isMessageRead(m) && !isMessageSent(m)">
                                                                <svg class="w-3.5 h-3.5 text-[#8696a0] shrink-0" title="Tersampaikan ke WhatsApp prospek (Delivered)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                    <path d="M18 6 7 17l-5-5"/>
                                                                    <path d="m22 10-7.5 7.5L13 16"/>
                                                                </svg>
                                                            </template>
                                                        </span>
                                                    </template>
                                                </span>
                                            </div>

                                            <!-- Reaction Badge on Bubble -->
                                            <template x-if="m.reaction">
                                                <div class="absolute -bottom-2.5 select-none cursor-pointer z-10"
                                                     :class="m.is_from_me == 1 ? 'right-3' : 'left-3'"
                                                     @click="toggleReaction(m, m.reaction)"
                                                     title="Klik untuk menarik reaksi">
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full bg-white border border-zinc-200 shadow-xs text-xs transition hover:scale-110"
                                                          x-text="m.reaction"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            </div>
                        </template>
                    </div>

                    <!-- Floating Scroll-to-Bottom Button (WhatsApp Web Style) -->
                    <div x-show="isScrolledUp" 
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 translate-y-2 scale-90"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 translate-y-2 scale-90"
                         x-cloak
                         class="absolute bottom-3 right-4 z-20 select-none">
                        <button type="button" 
                                @click="scrollToBottom(false)" 
                                title="Gulir ke pesan terbaru"
                                class="relative w-10 h-10 rounded-full bg-white border border-zinc-200/90 shadow-[0_2px_8px_rgba(0,0,0,0.18)] flex items-center justify-center text-[#667781] hover:text-black hover:bg-zinc-50 transition active:scale-95">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m6 9 6 6 6-6"/>
                            </svg>
                            <template x-if="unreadBelowCount > 0">
                                <span class="absolute -top-1.5 -right-1.5 min-w-[20px] h-5 px-1 bg-[#25d366] text-white text-[10px] font-bold rounded-full flex items-center justify-center shadow-xs"
                                      x-text="unreadBelowCount"></span>
                            </template>
                        </button>
                    </div>
                </div>

                    <!-- Input Area (WhatsApp Web 1:1) -->
                    <div class="bg-[#f0f2f5] border-t border-zinc-200/80 shrink-0">
                        <!-- Quick Response Chips -->
                        <div class="px-3 sm:px-4 pt-2 pb-1 flex items-center gap-1.5 overflow-x-auto no-scrollbar text-[11px]">
                            <button type="button" @click="insertQuickTemplate('greeting')" 
                                    class="px-3 py-1 bg-white hover:bg-zinc-50 hover:text-[#00a884] hover:border-[#00a884]/60 text-zinc-700 rounded-full font-medium shrink-0 border border-zinc-200/80 shadow-2xs transition">
                                Salam Pembuka
                            </button>
                            <button type="button" @click="insertQuickTemplate('package')" 
                                    class="px-3 py-1 bg-white hover:bg-zinc-50 hover:text-[#00a884] hover:border-[#00a884]/60 text-zinc-700 rounded-full font-medium shrink-0 border border-zinc-200/80 shadow-2xs transition">
                                Rincian Paket
                            </button>
                            <button type="button" @click="insertQuickTemplate('bank')" 
                                    class="px-3 py-1 bg-white hover:bg-zinc-50 hover:text-[#00a884] hover:border-[#00a884]/60 text-zinc-700 rounded-full font-medium shrink-0 border border-zinc-200/80 shadow-2xs transition">
                                Rekening DP
                            </button>
                            <button type="button" @click="insertQuickTemplate('closing')" 
                                    class="px-3 py-1 bg-white hover:bg-zinc-50 hover:text-[#00a884] hover:border-[#00a884]/60 text-zinc-700 rounded-full font-medium shrink-0 border border-zinc-200/80 shadow-2xs transition">
                                Dorong Closing
                            </button>
                        </div>

                        <!-- Quoted Message Reply Preview Banner -->
                        <template x-if="replyingTo">
                            <div class="px-3 sm:px-4 pt-1.5 pb-0.5">
                                <div class="flex items-center justify-between gap-3 p-2.5 bg-white border-l-4 border-[#00a884] rounded-xl text-xs shadow-2xs">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 text-[10px] font-bold text-[#00a884] mb-0.5">
                                            <svg class="w-3 h-3 text-[#00a884] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/>
                                            </svg>
                                            <span>Membalas <span class="font-black" x-text="replyingTo.sender_name || (replyingTo.is_from_me == 1 ? 'Anda' : 'Calon Jamaah')"></span>:</span>
                                        </div>
                                        <div class="text-[11px] text-zinc-600 truncate italic" x-text="replyingTo.message_text || (replyingTo.media_url ? '[Lampiran Berkas / Gambar]' : '')"></div>
                                    </div>
                                    <button type="button" 
                                            @click="cancelReply()" 
                                            title="Batal membalas"
                                            class="p-1 rounded-full text-zinc-400 hover:text-black hover:bg-zinc-100 transition">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- Input Toolbar & Form -->
                        <form @submit.prevent="sendMessage()" class="px-3 sm:px-4 py-2 flex items-end gap-1.5 relative">
                            <!-- Hidden file inputs -->
                            <input type="file" x-ref="imageFileInput" accept="image/jpeg,image/png,image/webp,image/gif" @change="onFileSelected($event, 'image')" class="hidden">
                            <input type="file" x-ref="docFileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.csv" @change="onFileSelected($event, 'document')" class="hidden">
                            <input type="file" x-ref="flyerFileInput" accept="image/jpeg,image/png,image/webp" @change="handleFlyerFileSelected($event)" class="hidden">

                            <!-- Left Action Buttons (Emoji & Attachment) -->
                            <div class="flex items-center gap-0.5 shrink-0 mb-1">
                                <!-- Button Emoji Picker -->
                                <div x-data="{ openEmoji: false }" class="relative inline-flex" @click.outside="openEmoji = false">
                                    <button type="button" 
                                            @click="openEmoji = !openEmoji" 
                                            title="Sisipkan Emoji"
                                            class="w-9 h-9 flex items-center justify-center rounded-full text-zinc-500 hover:text-zinc-800 hover:bg-zinc-200/70 transition"
                                            :class="openEmoji ? 'bg-zinc-200/80 text-zinc-800' : ''">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="10"/>
                                            <path d="M8 14s1.5 2 4 2 4-2 4-2"/>
                                            <line x1="9" y1="9" x2="9.01" y2="9"/>
                                            <line x1="15" y1="9" x2="15.01" y2="9"/>
                                        </svg>
                                    </button>

                                    <!-- Emoji Picker Popover -->
                                    <div x-show="openEmoji" 
                                         x-cloak 
                                         class="absolute bottom-full mb-2 left-0 z-30 w-72 sm:w-80 bg-white border border-zinc-200 rounded-2xl shadow-xl overflow-hidden text-xs">
                                        <!-- Category Tabs -->
                                        <div class="flex items-center border-b border-zinc-200 bg-zinc-50 p-1 gap-1 text-[11px] font-medium">
                                            <button type="button" 
                                                    @click="activeEmojiTab = 'smileys'"
                                                    class="flex-1 py-1 px-1.5 text-center rounded-lg transition"
                                                    :class="activeEmojiTab === 'smileys' ? 'bg-white shadow-2xs font-bold text-black border border-zinc-200' : 'text-zinc-500 hover:text-zinc-800'">
                                                😊 Wajah
                                            </button>
                                            <button type="button" 
                                                    @click="activeEmojiTab = 'gestures'"
                                                    class="flex-1 py-1 px-1.5 text-center rounded-lg transition"
                                                    :class="activeEmojiTab === 'gestures' ? 'bg-white shadow-2xs font-bold text-black border border-zinc-200' : 'text-zinc-500 hover:text-zinc-800'">
                                                👍 Tangan
                                            </button>
                                            <button type="button" 
                                                    @click="activeEmojiTab = 'umroh'"
                                                    class="flex-1 py-1 px-1.5 text-center rounded-lg transition"
                                                    :class="activeEmojiTab === 'umroh' ? 'bg-white shadow-2xs font-bold text-black border border-zinc-200' : 'text-zinc-500 hover:text-zinc-800'">
                                                🕋 Religi
                                            </button>
                                            <button type="button" 
                                                    @click="activeEmojiTab = 'symbols'"
                                                    class="flex-1 py-1 px-1.5 text-center rounded-lg transition"
                                                    :class="activeEmojiTab === 'symbols' ? 'bg-white shadow-2xs font-bold text-black border border-zinc-200' : 'text-zinc-500 hover:text-zinc-800'">
                                                ✨ Simbol
                                            </button>
                                        </div>

                                        <!-- Emoji Grid -->
                                        <div class="p-2.5 max-h-48 overflow-y-auto grid grid-cols-7 gap-1">
                                            <template x-for="emo in (emojiList[activeEmojiTab] || [])" :key="emo">
                                                <button type="button" 
                                                        @click="insertEmoji(emo)"
                                                        class="h-9 w-9 flex items-center justify-center text-lg rounded-lg hover:bg-zinc-100 hover:scale-120 transition select-none"
                                                        x-text="emo"></button>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <!-- Attachment Menu Popover (WhatsApp Web Paperclip) -->
                                <div x-data="{ openAttach: false }" class="relative inline-flex" @click.outside="openAttach = false">
                                    <button type="button" 
                                            @click="openAttach = !openAttach" 
                                            title="Lampirkan File / Gambar"
                                            class="w-9 h-9 flex items-center justify-center rounded-full text-zinc-500 hover:text-zinc-800 hover:bg-zinc-200/70 transition"
                                            :class="openAttach ? 'bg-zinc-200/80 text-zinc-800' : ''">
                                        <svg class="w-5 h-5 -rotate-45" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                                        </svg>
                                    </button>

                                    <!-- Attachment Options Popup -->
                                    <div x-show="openAttach" 
                                         x-cloak 
                                         x-transition:enter="transition ease-out duration-100"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         x-transition:leave="transition ease-in duration-75"
                                         x-transition:leave-start="opacity-100 scale-100"
                                         x-transition:leave-end="opacity-0 scale-95"
                                         class="absolute bottom-full mb-2 left-0 z-30 w-48 bg-white border border-zinc-200 rounded-2xl shadow-xl overflow-hidden py-1.5 text-xs">
                                        
                                        <button type="button" 
                                                @click="openSendFlyerModal(); openAttach = false"
                                                class="w-full px-3 py-2.5 flex items-center gap-3 hover:bg-zinc-50 text-zinc-700 hover:text-black transition border-b border-zinc-100">
                                            <div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                                                    <circle cx="9" cy="9" r="2"/>
                                                    <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                                                </svg>
                                            </div>
                                            <div class="text-left min-w-0 flex-1">
                                                <div class="font-medium text-xs leading-tight">Flyer Paket</div>
                                                <div class="text-[10px] text-zinc-400 truncate" x-text="selectedProspectPackage ? selectedProspectPackage.name : 'Flyer Brosur'"></div>
                                            </div>
                                        </button>

                                        <button type="button" 
                                                @click="$refs.imageFileInput.click(); openAttach = false"
                                                class="w-full px-3 py-2.5 flex items-center gap-3 hover:bg-zinc-50 text-zinc-700 hover:text-black transition">
                                            <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                                                    <circle cx="9" cy="9" r="2"/>
                                                    <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                                                </svg>
                                            </div>
                                            <span class="font-medium">Foto & Video</span>
                                        </button>

                                        <button type="button" 
                                                @click="$refs.docFileInput.click(); openAttach = false"
                                                class="w-full px-3 py-2.5 flex items-center gap-3 hover:bg-zinc-50 text-zinc-700 hover:text-black transition">
                                            <div class="w-8 h-8 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
                                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                                                    <polyline points="14 2 14 8 20 8"/>
                                                </svg>
                                            </div>
                                            <span class="font-medium">Dokumen</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Textarea WhatsApp Style -->
                            <div class="flex-1 min-w-0 bg-white rounded-lg px-3.5 py-2 shadow-xs border border-transparent focus-within:border-zinc-300 transition">
                                <textarea id="chatMessageInput"
                                          x-model="messageInput" 
                                          @keydown="handleChatInputKeydown($event)"
                                          @input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 120) + 'px'"
                                          rows="1" 
                                          placeholder="Ketik pesan WhatsApp..." 
                                          class="w-full bg-transparent border-0 p-0 text-xs text-zinc-900 focus:outline-none focus:ring-0 resize-none placeholder:text-zinc-400 max-h-32 leading-relaxed"
                                          style="min-height: 22px;"></textarea>
                            </div>
                            
                            <!-- Send Button (Circular WhatsApp Web Style) -->
                            <button type="submit" 
                                    :disabled="!messageInput.trim() || isSending"
                                    title="Kirim Pesan WhatsApp (Enter)"
                                    class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 transition mb-0.5 shadow-2xs"
                                    :class="messageInput.trim() && !isSending ? 'bg-[#00a884] hover:bg-[#02906f] text-white cursor-pointer active:scale-95 shadow-xs' : 'bg-transparent text-[#8696a0] cursor-not-allowed'">
                                <template x-if="!isSending">
                                    <svg class="w-4 h-4 ml-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="22" y1="2" x2="11" y2="13"/>
                                        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                                    </svg>
                                </template>
                                <template x-if="isSending">
                                    <svg class="w-4 h-4 animate-spin text-zinc-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                                    </svg>
                                </template>
                            </button>
                        </form>
                    </div>

                </div>
            </template>
        </div>

        <!-- ============================================================== -->
        <!-- COLUMN 3: DETAIL PROSPEK PANEL (Collapsible, ~25-30% / w-96)   -->
        <!-- ============================================================== -->
        <div class="w-full lg:w-96 xl:w-[400px] border-l border-zinc-200 bg-white flex-col shrink-0 min-h-0"
             x-show="prospectPanelOpen || mobileTab === 'prospect'"
             x-cloak
             :class="(prospectPanelOpen ? 'lg:flex ' : 'lg:hidden ') + (mobileTab === 'prospect' ? 'flex' : 'hidden')">
            
            <!-- Panel Header -->
            <div class="p-3.5 border-b border-zinc-200 bg-zinc-50/70 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2">
                    <template x-if="rightTab === 'prospect'">
                        <div class="w-6 h-6 rounded-lg bg-black text-white flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                    </template>
                    <template x-if="rightTab === 'copilot'">
                        <div class="w-6 h-6 rounded-lg bg-black text-white flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        </div>
                    </template>
                    <h3 class="font-extrabold text-xs text-black" x-text="rightTab === 'copilot' ? 'Copilot Script' : 'Detail Prospek'"></h3>
                </div>

                <div class="flex items-center gap-1.5">
                    <template x-if="rightTab === 'prospect' && activeProspect && activeProspect.id">
                        <span class="text-[10px] font-mono text-zinc-500 bg-white px-1.5 py-0.5 rounded border border-zinc-200 font-bold" x-text="'#' + activeProspect.id"></span>
                    </template>
                    <button type="button" @click="prospectPanelOpen = false; if (mobileTab === 'prospect') mobileTab = 'thread'" 
                            title="Tutup Panel"
                            class="text-zinc-400 hover:text-black p-1 rounded-md hover:bg-zinc-100 transition">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
            </div>

            <!-- Content Area (Scrollable) -->
            <!-- === TAB: DETAIL PROSPEK === -->
            <div class="flex-1 overflow-y-auto p-4 space-y-4 text-xs" x-show="rightTab === 'prospect'" x-cloak>

                <!-- STATE 1: BELUM ADA CHAT DIPILIH -->
                <template x-if="!activeChat">
                    <div class="py-16 text-center text-zinc-400 space-y-3">
                        <div class="w-12 h-12 rounded-2xl bg-zinc-100 border border-zinc-200 flex items-center justify-center mx-auto text-zinc-400">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        <div class="space-y-1">
                            <p class="font-bold text-zinc-700">Belum Ada Percakapan Dipilih</p>
                            <p class="text-[11px] text-zinc-400 max-w-[220px] mx-auto">Pilih salah satu kontak di sebelah kiri untuk melihat atau mengedit data prospek.</p>
                        </div>
                    </div>
                </template>

                <!-- STATE LOADING: MEMUAT DATA PROSPEK -->
                <template x-if="activeChat && !activeProspect && messagesLoading">
                    <div class="py-16 text-center text-zinc-400 space-y-3">
                        <div class="w-8 h-8 rounded-full border-2 border-zinc-200 border-t-black animate-spin mx-auto"></div>
                        <p class="text-xs font-semibold text-zinc-600">Memuat detail prospek...</p>
                    </div>
                </template>

                <!-- STATE 2: CHAT DIPILIH TAPI BELUM ADA PROSPEK -->
                <template x-if="activeChat && !activeProspect && !messagesLoading">
                    <div class="space-y-4">
                        <div class="p-4 bg-zinc-50 border border-zinc-200 rounded-2xl space-y-3 text-center">
                            <div class="w-10 h-10 rounded-full bg-zinc-200 flex items-center justify-center mx-auto text-zinc-600 font-bold text-sm">
                                <span x-text="getAvatarLetter(activeChat)"></span>
                            </div>
                            <div>
                                <h4 class="font-bold text-zinc-900 text-sm" x-text="getChatTitle(activeChat)"></h4>
                                <p class="text-zinc-500 font-mono text-[11px] mt-0.5" x-text="formatIndoPhone(activeChat.phone) || activeChat.phone || 'Nomor HP belum tercatat'"></p>
                            </div>
                            <div class="pt-1">
                                <span class="inline-block text-[10px] text-amber-800 bg-amber-50 border border-amber-200 px-2.5 py-0.5 rounded-full font-semibold">
                                    Kontak Baru (Belum Masuk Pipeline)
                                </span>
                            </div>
                        </div>

                        <div class="p-4 bg-white border border-zinc-200 rounded-2xl space-y-3 shadow-2xs">
                            <div class="space-y-1">
                                <h5 class="font-bold text-black text-xs">Jadikan Prospek Umroh</h5>
                                <p class="text-[11px] text-zinc-500 leading-relaxed">
                                    Daftarkan kontak ini ke pipeline konversi untuk mencatat paket yang diminati, kebutuhan khusus, dan tahapan closing.
                                </p>
                            </div>

                            <div>
                                <label class="block font-semibold text-zinc-700 mb-1 text-[11px]">Nama Lengkap Calon Jamaah</label>
                                <input type="text" x-model="newProspectName" 
                                       :placeholder="getChatTitle(activeChat)"
                                       class="w-full px-3 py-2 border border-zinc-300 rounded-xl focus:outline-none focus:border-black text-xs font-semibold">
                            </div>

                            <div x-data="{ openRegPkg: false }" class="relative" @click.outside="openRegPkg = false">
                                <label class="block font-semibold text-zinc-700 mb-1 text-[11px]">Pilihan Paket Awal (Opsional)</label>
                                <button type="button" @click="openRegPkg = !openRegPkg"
                                        class="w-full px-3 py-2 bg-white hover:bg-zinc-50 border border-zinc-300 rounded-xl text-xs font-medium text-zinc-900 transition flex items-center justify-between gap-2 shadow-2xs text-left">
                                    <span class="truncate" :class="!newProspectPackageId ? 'text-zinc-400 font-normal italic' : 'text-zinc-900 font-semibold'" x-text="packages.find(p => p.id == newProspectPackageId) ? `${packages.find(p => p.id == newProspectPackageId).name} (${packages.find(p => p.id == newProspectPackageId).price})` : '-- Belum Menentukan Paket --'"></span>
                                    <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0" :class="openRegPkg ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="m6 9 6 6 6-6"/>
                                    </svg>
                                </button>
                                <div x-show="openRegPkg" x-cloak x-transition.opacity.duration.150ms
                                     class="absolute left-0 right-0 top-full z-50 mt-1 max-h-48 overflow-y-auto bg-white border border-zinc-200 rounded-xl shadow-xl py-1 text-xs divide-y divide-zinc-50">
                                    <button type="button" 
                                            @click="newProspectPackageId = ''; openRegPkg = false"
                                            class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition border-b border-zinc-100"
                                            :class="!newProspectPackageId ? 'font-bold text-black bg-zinc-50' : 'text-zinc-500'">
                                        <span class="truncate pr-2 italic text-zinc-500">-- Belum Menentukan Paket --</span>
                                        <svg x-show="!newProspectPackageId" class="w-3.5 h-3.5 text-black shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <polyline points="20 6 9 17 4 12"/>
                                        </svg>
                                    </button>
                                    <template x-for="pkg in packages" :key="pkg.id">
                                        <button type="button" 
                                                @click="newProspectPackageId = pkg.id; openRegPkg = false"
                                                class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                                :class="newProspectPackageId == pkg.id ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                            <span class="truncate pr-2" x-text="`${pkg.name} (${pkg.price})`"></span>
                                            <svg x-show="newProspectPackageId == pkg.id" class="w-3.5 h-3.5 text-black shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                <polyline points="20 6 9 17 4 12"/>
                                            </svg>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <button type="button" 
                                    @click="registerProspectFromChat()" 
                                    :disabled="isRegisteringProspect"
                                    class="w-full py-2.5 bg-black hover:bg-zinc-800 disabled:bg-zinc-300 text-white rounded-xl font-bold text-xs transition flex items-center justify-center gap-1.5 shadow-2xs cursor-pointer">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                                <span x-show="!isRegisteringProspect">+ Daftarkan Prospek Baru</span>
                                <span x-show="isRegisteringProspect">Mendaftarkan...</span>
                            </button>
                        </div>
                    </div>
                </template>

                <!-- STATE 3: PROSPEK AKTIF TERDAFTAR -->
                <template x-if="activeChat && activeProspect">
                    <form @submit.prevent="saveProspectDetails()" class="space-y-4">
                        
                        <!-- 1. KARTU IDENTITAS KONTAK -->
                        <div class="p-3.5 bg-zinc-50/70 border border-zinc-200/90 rounded-2xl space-y-3">
                            <div class="flex items-center justify-between pb-2 border-b border-zinc-200/60">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500">Identitas Calon Jamaah</span>
                                <template x-if="activeChat.meta_referral_marker">
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-black text-white" title="Masuk dari Meta Ad CTWA">
                                        Meta Ad
                                    </span>
                                </template>
                            </div>

                            <!-- Field Nama -->
                            <div>
                                <label class="block font-semibold text-zinc-600 mb-1 text-[11px]">Nama Prospek</label>
                                <input type="text" x-model="prospectForm.name" required
                                       class="w-full px-3 py-1.5 bg-white border border-zinc-300 rounded-xl text-xs font-bold text-black focus:outline-none focus:border-black transition">
                            </div>

                            <!-- Field No. WhatsApp (Read-only) -->
                            <div>
                                <label class="block font-semibold text-zinc-600 mb-1 text-[11px]">Nomor WhatsApp</label>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-full px-3 py-1.5 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-mono font-bold text-zinc-700 select-all"
                                         x-text="prospectForm.phone || '-'"></div>
                                    <button type="button" @click="copyToClipboard(prospectForm.phone)"
                                            title="Salin Nomor"
                                            class="p-2 border border-zinc-300 bg-white hover:bg-zinc-100 rounded-xl text-zinc-700 transition shrink-0 shadow-2xs cursor-pointer">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                    </button>
                                </div>
                                <p class="text-[10px] text-zinc-400 mt-1">Nomor terikat ke sesi WhatsApp, tidak dapat diubah.</p>
                            </div>

                        </div>

                        <!-- 2. STATUS PIPELINE (9 STATUS KONVERSI) -->
                        <div class="p-3.5 bg-white border border-zinc-200/90 rounded-2xl space-y-2.5 shadow-2xs">
                            <div class="flex items-center justify-between">
                                <label class="font-bold text-zinc-800 text-[11px] uppercase tracking-wider">Status Konversi</label>
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border shadow-2xs"
                                      :class="getStatusBadgeClass(prospectForm.status)"
                                      x-text="getStatusLabel(prospectForm.status)"></span>
                            </div>

                            <!-- Status Selector Dropdown -->
                            <div x-data="{ openSt: false }" class="relative" @click.outside="openSt = false">
                                <button type="button" @click="openSt = !openSt"
                                        class="w-full px-3 py-2 bg-zinc-50 hover:bg-zinc-100 border border-zinc-300 rounded-xl text-xs font-bold text-zinc-900 transition flex items-center justify-between shadow-2xs">
                                    <span x-text="getStatusLabel(prospectForm.status)"></span>
                                    <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0" :class="openSt ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="m6 9 6 6 6-6"/>
                                    </svg>
                                </button>
                                <div x-show="openSt" x-cloak x-transition.opacity.duration.150ms
                                     class="absolute left-0 right-0 z-50 mt-1 max-h-56 overflow-y-auto bg-white border border-zinc-200 rounded-xl shadow-xl py-1 text-xs divide-y divide-zinc-50">
                                    <template x-for="st in statusOptions" :key="st.val">
                                        <button type="button" 
                                                @click="prospectForm.status = st.val; openSt = false"
                                                class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                                :class="prospectForm.status === st.val ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border shadow-2xs"
                                                  :class="getStatusBadgeClass(st.val)"
                                                  x-text="st.label"></span>
                                            <svg x-show="prospectForm.status === st.val" class="w-3.5 h-3.5 text-black shrink-0 ml-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                <polyline points="20 6 9 17 4 12"/>
                                            </svg>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- 3. PILIHAN PAKET UMROH & QUICK CHAT SHORTCUTS -->
                        <div class="p-3.5 bg-white border border-zinc-200/90 rounded-2xl space-y-3 shadow-2xs">
                            <div class="flex items-center justify-between pb-1 border-b border-zinc-100">
                                <label class="font-bold text-zinc-800 text-[11px] uppercase tracking-wider">Paket Umroh Diminati</label>
                                <template x-if="selectedProspectPackage">
                                    <a :href="`admin/package_detail.php?id=${selectedProspectPackage.id}`" target="_blank"
                                       class="text-[10px] font-bold text-black hover:underline flex items-center gap-0.5">
                                        <span>Detail &nearr;</span>
                                    </a>
                                </template>
                            </div>

                            <!-- Package Dropdown -->
                            <div x-data="{ openPkgDetail: false }" class="relative" @click.outside="openPkgDetail = false">
                                <button type="button" @click="openPkgDetail = !openPkgDetail"
                                        class="w-full px-3 py-2 bg-zinc-50 hover:bg-zinc-100 border border-zinc-300 rounded-xl text-xs font-semibold transition flex items-center justify-between shadow-2xs text-left">
                                    <span class="truncate" :class="!selectedProspectPackage ? 'text-zinc-400 font-normal italic' : 'text-zinc-900 font-semibold'"
                                          x-text="selectedProspectPackage ? `${selectedProspectPackage.name} (${selectedProspectPackage.price})` : '-- Belum Menentukan Paket --'"></span>
                                    <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0 ml-1" :class="openPkgDetail ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="m6 9 6 6 6-6"/>
                                    </svg>
                                </button>
                                <div x-show="openPkgDetail" x-cloak x-transition.opacity.duration.150ms
                                     class="absolute left-0 right-0 z-50 mt-1 max-h-52 overflow-y-auto bg-white border border-zinc-200 rounded-xl shadow-xl py-1 text-xs divide-y divide-zinc-50">
                                    <button type="button" 
                                            @click="prospectForm.package_id = ''; selectedPackageId = null; openPkgDetail = false"
                                            class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition border-b border-zinc-100"
                                            :class="!prospectForm.package_id ? 'font-bold text-black bg-zinc-50' : 'text-zinc-500'">
                                        <div class="min-w-0 pr-2">
                                            <div class="italic text-zinc-500">-- Belum Menentukan Paket --</div>
                                        </div>
                                        <svg x-show="!prospectForm.package_id" class="w-3.5 h-3.5 text-black shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <polyline points="20 6 9 17 4 12"/>
                                        </svg>
                                    </button>
                                    <template x-for="pkg in packages" :key="pkg.id">
                                        <button type="button" 
                                                @click="prospectForm.package_id = pkg.id; selectedPackageId = pkg.id; openPkgDetail = false"
                                                class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                                :class="prospectForm.package_id == pkg.id ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                            <div class="min-w-0 pr-2">
                                                <div class="font-medium truncate" x-text="pkg.name"></div>
                                                <div class="text-[10px] text-zinc-500" x-text="`${pkg.price} • ${pkg.duration || '9 Hari'}`"></div>
                                            </div>
                                            <svg x-show="prospectForm.package_id == pkg.id" class="w-3.5 h-3.5 text-black shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                <polyline points="20 6 9 17 4 12"/>
                                            </svg>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <!-- State Kosong Jika Belum Menentukan Paket -->
                            <template x-if="!selectedProspectPackage">
                                <div class="p-3.5 bg-zinc-50 rounded-xl border border-dashed border-zinc-200 text-center text-zinc-400 text-[11px] space-y-1">
                                    <p class="font-semibold text-zinc-600">Belum ada paket yang dipilih</p>
                                    <p class="text-[10px] text-zinc-400 leading-tight">Pilih paket umroh di dropdown atas jika calon jamaah sudah menentukan pilihan.</p>
                                </div>
                            </template>

                            <!-- Package Specs Quick Card -->
                            <template x-if="selectedProspectPackage">
                                <div class="p-2.5 bg-zinc-50 rounded-xl border border-zinc-200 space-y-1.5 text-[11px] text-zinc-600">
                                    <div class="flex justify-between">
                                        <span class="text-zinc-400">Harga Quad:</span>
                                        <strong class="text-black font-semibold" x-text="selectedProspectPackage.price_quad || selectedProspectPackage.price"></strong>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-zinc-400">Minimal DP:</span>
                                        <strong class="text-black font-semibold" x-text="selectedProspectPackage.dp || '-'"></strong>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-zinc-400">Maskapai:</span>
                                        <span class="text-zinc-800 font-medium truncate max-w-[180px]" x-text="selectedProspectPackage.airline || '-'"></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-zinc-400">Hotel Makkah:</span>
                                        <span class="text-zinc-800 font-medium truncate max-w-[180px]" x-text="selectedProspectPackage.hotel_makkah || '-'"></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-zinc-400">Jadwal:</span>
                                        <span class="text-zinc-800 font-medium" x-text="`${selectedProspectPackage.departure_date || selectedProspectPackage.departure_info || '-'} (${selectedProspectPackage.duration || '-'})`"></span>
                                    </div>

                                    <!-- Mini Flyer Preview Row -->
                                    <div class="pt-2 mt-1 border-t border-zinc-200/70 flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <template x-if="selectedProspectPackage.flyer_image">
                                                <img :src="selectedProspectPackage.flyer_image" 
                                                     @click="openLightbox(selectedProspectPackage.flyer_image)"
                                                     class="w-10 h-10 object-cover rounded-lg border border-zinc-300 shrink-0 cursor-pointer hover:opacity-80 transition"
                                                     alt="Flyer">
                                            </template>
                                            <template x-if="!selectedProspectPackage.flyer_image">
                                                <div class="w-10 h-10 rounded-lg border border-dashed border-zinc-300 bg-zinc-100 flex items-center justify-center text-zinc-400 shrink-0">
                                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                                                    </svg>
                                                </div>
                                            </template>
                                            <div class="min-w-0">
                                                <span class="block font-bold text-zinc-900 text-[10px] truncate" x-text="selectedProspectPackage.flyer_image ? 'Flyer Brosur Resmi' : 'Flyer Gambar'"></span>
                                                <span class="block text-[9px] text-zinc-400" x-text="selectedProspectPackage.flyer_image ? 'Format gambar siap kirim ke WA' : 'Pilih file flyer dari komputer'"></span>
                                            </div>
                                        </div>
                                        <button type="button" @click="openSendFlyerModal()" 
                                                class="px-2.5 py-1 bg-black hover:bg-zinc-800 text-white rounded-lg text-[10px] font-bold transition flex items-center gap-1 shrink-0 cursor-pointer shadow-2xs">
                                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                                            <span>Kirim</span>
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <!-- Quick Action Buttons to WhatsApp Chat -->
                            <template x-if="selectedProspectPackage">
                                <div class="grid grid-cols-2 gap-2 pt-1">
                                    <button type="button" @click="insertPackageSummaryToChat()"
                                            class="px-2.5 py-1.5 bg-zinc-100 hover:bg-black hover:text-white border border-zinc-300 rounded-xl text-[10px] font-semibold text-zinc-800 transition flex items-center justify-center gap-1 shadow-2xs cursor-pointer">
                                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        <span>Kirim Format Paket</span>
                                    </button>
                                    <button type="button" @click="insertItineraryToChat()"
                                            class="px-2.5 py-1.5 bg-zinc-100 hover:bg-black hover:text-white border border-zinc-300 rounded-xl text-[10px] font-semibold text-zinc-800 transition flex items-center justify-center gap-1 shadow-2xs cursor-pointer">
                                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                        <span>Kirim Itinerary</span>
                                    </button>
                                </div>
                            </template>
                        </div>

                        <!-- 4. JUMLAH JAMAAH (PAX) -->
                        <div class="p-3.5 bg-white border border-zinc-200/90 rounded-2xl space-y-3 shadow-2xs">
                            <div class="flex items-center justify-between pb-1 border-b border-zinc-100">
                                <label class="font-bold text-zinc-800 text-[11px] uppercase tracking-wider">Jumlah Jamaah (Pax)</label>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full transition"
                                      :class="totalPax > 0 ? 'bg-black text-white' : 'bg-zinc-100 text-zinc-500'"
                                      x-text="`Total: ${totalPax} Pax`"></span>
                            </div>

                            <div class="grid grid-cols-2 gap-2.5">
                                <!-- Quad -->
                                <div class="p-2.5 bg-zinc-50 border border-zinc-200/80 rounded-xl space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-zinc-900 text-xs">Quad</span>
                                        <span class="text-[10px] text-zinc-400">Kamar Ber-4</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-1">
                                        <button type="button" @click="changePax('pax_quad', -1)" 
                                                class="w-7 h-7 rounded-lg border border-zinc-300 bg-white hover:bg-zinc-100 active:scale-95 flex items-center justify-center font-bold text-xs text-zinc-700 transition cursor-pointer shadow-2xs">
                                            -
                                        </button>
                                        <input type="number" min="0" x-model.number="prospectForm.pax_quad" 
                                               class="w-10 text-center font-bold text-xs py-1 border border-zinc-300 rounded-lg bg-white text-zinc-900 focus:outline-none focus:border-black focus:ring-1 focus:ring-black">
                                        <button type="button" @click="changePax('pax_quad', 1)" 
                                                class="w-7 h-7 rounded-lg bg-black hover:bg-zinc-800 active:scale-95 text-white flex items-center justify-center font-bold text-xs transition cursor-pointer shadow-2xs">
                                            +
                                        </button>
                                    </div>
                                </div>

                                <!-- Triple -->
                                <div class="p-2.5 bg-zinc-50 border border-zinc-200/80 rounded-xl space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-zinc-900 text-xs">Triple</span>
                                        <span class="text-[10px] text-zinc-400">Kamar Ber-3</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-1">
                                        <button type="button" @click="changePax('pax_triple', -1)" 
                                                class="w-7 h-7 rounded-lg border border-zinc-300 bg-white hover:bg-zinc-100 active:scale-95 flex items-center justify-center font-bold text-xs text-zinc-700 transition cursor-pointer shadow-2xs">
                                            -
                                        </button>
                                        <input type="number" min="0" x-model.number="prospectForm.pax_triple" 
                                               class="w-10 text-center font-bold text-xs py-1 border border-zinc-300 rounded-lg bg-white text-zinc-900 focus:outline-none focus:border-black focus:ring-1 focus:ring-black">
                                        <button type="button" @click="changePax('pax_triple', 1)" 
                                                class="w-7 h-7 rounded-lg bg-black hover:bg-zinc-800 active:scale-95 text-white flex items-center justify-center font-bold text-xs transition cursor-pointer shadow-2xs">
                                            +
                                        </button>
                                    </div>
                                </div>

                                <!-- Double -->
                                <div class="p-2.5 bg-zinc-50 border border-zinc-200/80 rounded-xl space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-zinc-900 text-xs">Double</span>
                                        <span class="text-[10px] text-zinc-400">Kamar Ber-2</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-1">
                                        <button type="button" @click="changePax('pax_double', -1)" 
                                                class="w-7 h-7 rounded-lg border border-zinc-300 bg-white hover:bg-zinc-100 active:scale-95 flex items-center justify-center font-bold text-xs text-zinc-700 transition cursor-pointer shadow-2xs">
                                            -
                                        </button>
                                        <input type="number" min="0" x-model.number="prospectForm.pax_double" 
                                               class="w-10 text-center font-bold text-xs py-1 border border-zinc-300 rounded-lg bg-white text-zinc-900 focus:outline-none focus:border-black focus:ring-1 focus:ring-black">
                                        <button type="button" @click="changePax('pax_double', 1)" 
                                                class="w-7 h-7 rounded-lg bg-black hover:bg-zinc-800 active:scale-95 text-white flex items-center justify-center font-bold text-xs transition cursor-pointer shadow-2xs">
                                            +
                                        </button>
                                    </div>
                                </div>

                                <!-- Infant -->
                                <div class="p-2.5 bg-zinc-50 border border-zinc-200/80 rounded-xl space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-zinc-900 text-xs">Infant</span>
                                        <span class="text-[10px] text-zinc-400">Bayi &lt;2th</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-1">
                                        <button type="button" @click="changePax('pax_infant', -1)" 
                                                class="w-7 h-7 rounded-lg border border-zinc-300 bg-white hover:bg-zinc-100 active:scale-95 flex items-center justify-center font-bold text-xs text-zinc-700 transition cursor-pointer shadow-2xs">
                                            -
                                        </button>
                                        <input type="number" min="0" x-model.number="prospectForm.pax_infant" 
                                               class="w-10 text-center font-bold text-xs py-1 border border-zinc-300 rounded-lg bg-white text-zinc-900 focus:outline-none focus:border-black focus:ring-1 focus:ring-black">
                                        <button type="button" @click="changePax('pax_infant', 1)" 
                                                class="w-7 h-7 rounded-lg bg-black hover:bg-zinc-800 active:scale-95 text-white flex items-center justify-center font-bold text-xs transition cursor-pointer shadow-2xs">
                                            +
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 5. CATATAN PREFERENSI & KEBUTUHAN KHUSUS -->
                        <div class="p-3.5 bg-white border border-zinc-200/90 rounded-2xl space-y-2 shadow-2xs">
                            <label class="font-bold text-zinc-800 text-[11px] uppercase tracking-wider block">Catatan Kebutuhan Calon Jamaah</label>
                            <textarea x-model="prospectForm.notes" rows="3"
                                      placeholder="Contoh: Rencana berangkat 4 orang sekeluarga, ada ibu lansia pakai kursi roda, minta kamar dekat lift..."
                                      class="w-full p-2.5 bg-zinc-50 border border-zinc-300 rounded-xl text-xs text-zinc-800 focus:outline-none focus:border-black focus:bg-white transition resize-none"></textarea>
                        </div>

                        <!-- 5. TANGGAL FOLLOW-UP BERIKUTNYA -->
                        <div class="p-3.5 bg-white border border-zinc-200/90 rounded-2xl space-y-2 shadow-2xs">
                            <label class="font-bold text-zinc-800 text-[11px] uppercase tracking-wider block">Jadwal Follow-Up Berikutnya</label>
                            <input type="date" x-model="prospectForm.next_followup_date"
                                   class="w-full px-3 py-2 bg-zinc-50 border border-zinc-300 rounded-xl text-xs font-semibold text-zinc-800 focus:outline-none focus:border-black focus:bg-white transition">
                            <p class="text-[10px] text-zinc-400">Atur tanggal jika prospek meminta waktu untuk musyawarah keluarga.</p>
                        </div>

                        <!-- 6. TOMBOL SIMPAN DETAIL PROSPEK -->
                        <div class="pt-1 space-y-2">
                            <button type="submit" 
                                    :disabled="isSavingProspect"
                                    class="w-full py-2.5 bg-black hover:bg-zinc-800 disabled:bg-zinc-300 text-white rounded-xl font-bold text-xs transition flex items-center justify-center gap-1.5 shadow-2xs cursor-pointer">
                                <template x-if="!isSavingProspect && !prospectSavedSuccess">
                                    <span class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                        <span>Simpan Perubahan Prospek</span>
                                    </span>
                                </template>
                                <template x-if="isSavingProspect">
                                    <span>Menyimpan ke Database...</span>
                                </template>
                                <template x-if="prospectSavedSuccess">
                                    <span class="text-emerald-400 font-bold flex items-center gap-1">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                        <span>Tersimpan Sukses! ✓</span>
                                    </span>
                                </template>
                            </button>

                            <!-- Link ke Halaman Penuh -->
                            <div class="text-center pt-1">
                                <a :href="`prospect_detail.php?id=${activeProspect.id}`" target="_blank"
                                   class="text-[11px] text-zinc-500 hover:text-black font-semibold inline-flex items-center gap-1">
                                    <span>Buka Detail Lengkap (Audit Log)</span>
                                    <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                </a>
                            </div>

                        </div>

                    </form>
                </template>

            </div>

            <!-- === TAB: COPILOT SCRIPT === -->
            <div class="flex-1 overflow-y-auto text-xs flex flex-col" x-show="rightTab === 'copilot'" x-cloak>

                <!-- Category Tabs -->
                <div class="flex gap-1 px-3 pt-3 pb-2 border-b border-zinc-100 flex-wrap">
                    <template x-for="cat in copilotCategories" :key="cat.file">
                        <button type="button"
                                @click="copilotActiveFile = cat.file; npgdFilter = 'all'; loadCopilotScripts()"
                                class="px-2.5 py-1 rounded-lg text-[10px] font-bold transition border"
                                :class="copilotActiveFile === cat.file ? 'bg-black text-white border-black' : 'bg-white text-zinc-600 border-zinc-200 hover:bg-zinc-100'">
                            <span x-text="cat.label"></span>
                        </button>
                    </template>
                </div>

                <!-- Search Box -->
                <div class="px-3 pt-2 pb-1.5">
                    <input type="text" x-model="copilotSearch" placeholder="Cari script..."
                           class="w-full px-3 py-1.5 bg-zinc-50 border border-zinc-200 rounded-xl text-xs focus:outline-none focus:border-black transition">
                </div>

                <!-- Sub-filter (Khusus Tab Identifikasi) -->
                <template x-if="copilotActiveFile === 'identification'">
                    <div class="px-3 pb-2 flex gap-1 items-center flex-wrap">
                        <button type="button" @click="npgdFilter = 'all'"
                                class="px-2 py-0.5 rounded text-[10px] font-bold transition border"
                                :class="npgdFilter === 'all' ? 'bg-black text-white border-black' : 'bg-white text-zinc-600 border-zinc-200 hover:bg-zinc-100'">
                            Semua
                        </button>
                        <button type="button" @click="npgdFilter = 'need'"
                                class="px-2 py-0.5 rounded text-[10px] font-bold transition border"
                                :class="npgdFilter === 'need' ? 'bg-blue-600 text-white border-blue-600 shadow-2xs' : 'bg-blue-50/70 text-blue-700 border-blue-200 hover:bg-blue-100'">
                            Need
                        </button>
                        <button type="button" @click="npgdFilter = 'pain'"
                                class="px-2 py-0.5 rounded text-[10px] font-bold transition border"
                                :class="npgdFilter === 'pain' ? 'bg-rose-600 text-white border-rose-600 shadow-2xs' : 'bg-rose-50/70 text-rose-700 border-rose-200 hover:bg-rose-100'">
                            Pain
                        </button>
                        <button type="button" @click="npgdFilter = 'gain'"
                                class="px-2 py-0.5 rounded text-[10px] font-bold transition border"
                                :class="npgdFilter === 'gain' ? 'bg-emerald-600 text-white border-emerald-600 shadow-2xs' : 'bg-emerald-50/70 text-emerald-700 border-emerald-200 hover:bg-emerald-100'">
                            Gain
                        </button>
                        <button type="button" @click="npgdFilter = 'dream'"
                                class="px-2 py-0.5 rounded text-[10px] font-bold transition border"
                                :class="npgdFilter === 'dream' ? 'bg-purple-600 text-white border-purple-600 shadow-2xs' : 'bg-purple-50/70 text-purple-700 border-purple-200 hover:bg-purple-100'">
                            Dream
                        </button>
                        <button type="button" @click="npgdFilter = 'qualification'"
                                class="px-2 py-0.5 rounded text-[10px] font-bold transition border"
                                :class="npgdFilter === 'qualification' ? 'bg-amber-600 text-white border-amber-600 shadow-2xs' : 'bg-amber-50/70 text-amber-700 border-amber-200 hover:bg-amber-100'">
                            Kualifikasi
                        </button>
                    </div>
                </template>


                <!-- Script List -->
                <div class="flex-1 overflow-y-auto px-3 pb-4 space-y-2.5">
                    <template x-if="copilotLoading">
                        <div class="py-8 text-center text-zinc-400 text-[11px]">Memuat script...</div>
                    </template>
                    <template x-if="!copilotLoading && !filteredCopilotScripts.length">
                        <div class="py-8 text-center text-zinc-400 text-[11px]">Tidak ada script ditemukan.</div>
                    </template>
                    <template x-for="(s, idx) in filteredCopilotScripts" :key="s.id || idx">
                        <div x-data="{ open: false }" class="border border-zinc-200 rounded-xl overflow-hidden bg-white">

                            <!-- === TGJP (Keberatan) — Accordion === -->
                            <template x-if="s.steps && s.steps.length > 0">
                                <div>
                                    <!-- Accordion Header (clickable) -->
                                    <button type="button" @click="open = !open"
                                            class="w-full flex items-start justify-between px-3 py-2.5 bg-zinc-50 hover:bg-zinc-100 transition text-left gap-2">
                                        <div class="min-w-0 flex-1">
                                            <p class="font-bold text-zinc-900 text-[11px] leading-tight" x-text="s.title"></p>
                                            <p class="text-[10px] text-zinc-400 mt-0.5 leading-tight" x-text="s.use_when"></p>
                                        </div>
                                        <div class="flex items-center gap-1.5 shrink-0 pt-0.5">
                                            <template x-if="s.npgd">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border shadow-2xs inline-block"
                                                      :class="getNpgdBadgeClass(s.npgd.code)"
                                                      x-text="s.npgd.label"></span>
                                            </template>
                                            <svg class="w-3.5 h-3.5 text-zinc-400 shrink-0 transition-transform duration-200"
                                                 :class="open ? 'rotate-180' : ''"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                <path d="m6 9 6 6 6-6"/>
                                            </svg>
                                        </div>
                                    </button>
                                    <!-- Accordion Body — T G J P steps -->
                                    <div x-show="open" x-collapse class="divide-y divide-zinc-100">
                                        <template x-for="(step, si) in s.steps" :key="si">
                                            <div class="px-3 py-2.5 flex items-start gap-2.5">
                                                <!-- Step label badge -->
                                                <span class="shrink-0 w-5 h-5 rounded-md bg-black text-white text-[10px] font-extrabold flex items-center justify-center mt-0.5"
                                                      x-text="step.label"></span>
                                                <!-- Step text -->
                                                <p class="flex-1 text-[11px] text-zinc-700 leading-relaxed whitespace-pre-line"
                                                   x-text="replaceCopilotVars(step.text)"></p>
                                                <!-- Kirim button -->
                                                <button type="button"
                                                        @click="insertCopilotToChat(step.text, null)"
                                                        title="Kirim pesan ini"
                                                        class="shrink-0 px-2.5 py-1 bg-black hover:bg-zinc-800 text-white rounded-lg text-[10px] font-bold transition flex items-center gap-1 cursor-pointer">
                                                    <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                                                    Kirim
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <!-- === Script Biasa — Flat Card === -->
                            <template x-if="!s.steps || !s.steps.length">
                                <div>
                                    <!-- Header dengan Label di Sisi Kanan -->
                                    <div class="px-3 py-2.5 bg-zinc-50 border-b border-zinc-100 flex items-start justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <p class="font-bold text-zinc-900 text-[11px] leading-tight" x-text="s.title"></p>
                                            <p class="text-[10px] text-zinc-400 mt-0.5 leading-tight" x-text="s.use_when"></p>
                                        </div>
                                        <!-- Label Kategori di Sisi Kanan -->
                                        <template x-if="s.npgd">
                                            <span class="shrink-0 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border shadow-2xs"
                                                  :class="getNpgdBadgeClass(s.npgd.code)"
                                                  x-text="s.npgd.label"></span>
                                        </template>
                                    </div>


                                    <!-- Body -->
                                    <div class="p-3">
                                        <p class="text-zinc-700 leading-relaxed whitespace-pre-line text-[11px]"
                                           x-text="replaceCopilotVars(s.script)"></p>
                                    </div>
                                    <!-- Actions -->
                                    <div class="px-3 pb-2 flex gap-1.5">
                                        <button type="button"
                                                @click="copyCopilotScript(s.script)"
                                                class="flex-1 py-1.5 bg-zinc-100 hover:bg-zinc-200 border border-zinc-200 rounded-lg text-[10px] font-bold text-zinc-700 transition flex items-center justify-center gap-1 cursor-pointer">
                                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                            Salin
                                        </button>
                                        <button type="button"
                                                @click="insertCopilotToChat(s.script, null)"
                                                class="flex-1 py-1.5 bg-black hover:bg-zinc-800 text-white rounded-lg text-[10px] font-bold transition flex items-center justify-center gap-1 cursor-pointer">
                                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                                            Kirim
                                        </button>
                                    </div>
                                </div>
                            </template>

                        </div>
                    </template>
                </div>
            </div>

        </div>

    </div>

    <!-- MODAL: CHAT NOMOR BARU -->
    <div x-show="openNewChatModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/60 flex items-center justify-center p-4">
        <div @click.away="openNewChatModal = false" class="bg-white border border-zinc-200 rounded-2xl max-w-sm w-full p-5 shadow-xl space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                <h3 class="text-sm font-bold text-black">Chat Baru</h3>
                <button type="button" @click="openNewChatModal = false" class="text-zinc-400 hover:text-black font-bold text-lg">&times;</button>
            </div>

            <form @submit.prevent="startNewChat()" class="space-y-3 text-xs">
                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Nomor WhatsApp *</label>
                    <input type="text" x-model="newChatPhone" required placeholder="08123456789" 
                           class="w-full px-3 py-2 border border-zinc-300 rounded-lg font-mono focus:border-black focus:ring-1 focus:ring-black">
                </div>
                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Nama Jamaah (Opsional)</label>
                    <input type="text" x-model="newChatName" placeholder="Nama calon jamaah" 
                           class="w-full px-3 py-2 border border-zinc-300 rounded-lg focus:border-black focus:ring-1 focus:ring-black">
                </div>
                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Pesan Pertama *</label>
                    <textarea x-model="newChatMessage" rows="3" required placeholder="Tulis pesan..." 
                              class="w-full px-3 py-2 border border-zinc-300 rounded-lg focus:border-black focus:ring-1 focus:ring-black"></textarea>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2">
                    <button type="button" @click="openNewChatModal = false" class="px-3 py-1.5 border border-zinc-300 rounded-lg text-zinc-700 hover:bg-zinc-50">Batal</button>
                    <button type="submit" :disabled="isSending" class="px-4 py-1.5 bg-black hover:bg-zinc-800 text-white rounded-lg font-semibold">
                        <span x-show="!isSending">Kirim Pesan</span>
                        <span x-show="isSending">Mengirim...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Kontak Jamaah / Prospek -->
    <div x-show="openEditModal" x-cloak class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="openEditModal = false" class="bg-white rounded-xl border border-zinc-300 shadow-2xl max-w-md w-full p-5 text-xs">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-200">
                <div>
                    <h3 class="font-bold text-sm text-black">Edit Kontak</h3>
                </div>
                <button type="button" @click="openEditModal = false" class="text-zinc-400 hover:text-black font-bold text-lg">&times;</button>
            </div>
            
            <form @submit.prevent="saveContactEdit()" class="space-y-3 mt-4 text-xs">
                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Nama Jamaah / Kontak *</label>
                    <input type="text" x-model="editContactForm.name" required placeholder="Contoh: Bpk. H. Rahmat Subagyo"
                           class="w-full px-3 py-2 border border-zinc-300 rounded-lg focus:border-black focus:ring-1 focus:ring-black text-xs">
                </div>

                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Nomor WhatsApp</label>
                    <input type="text" :value="formatIndoPhone(editContactForm.phone) || editContactForm.phone || '-'" readonly
                           class="w-full px-3 py-2 bg-zinc-100 border border-zinc-200 rounded-lg text-xs font-mono text-zinc-600 select-all focus:outline-none">
                </div>

                <div x-data="{ openContactPkg: false }" class="relative" @click.outside="openContactPkg = false">
                    <label class="block font-semibold text-zinc-700 mb-1">Paket Umroh Diminati</label>
                    <button type="button" @click="openContactPkg = !openContactPkg"
                            class="w-full px-3 py-2 bg-white hover:bg-zinc-50 border border-zinc-300 rounded-lg text-xs font-medium text-zinc-900 transition flex items-center justify-between gap-2 shadow-2xs text-left">
                        <span class="truncate" x-text="packages.find(p => p.id == editContactForm.package_id) ? `${packages.find(p => p.id == editContactForm.package_id).name} (${packages.find(p => p.id == editContactForm.package_id).price})` : '-- Pilih Paket (Opsional) --'"></span>
                        <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0" :class="openContactPkg ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>
                    <div x-show="openContactPkg" x-cloak x-transition.opacity.duration.150ms
                         class="absolute left-0 right-0 top-full z-50 mt-1 max-h-48 overflow-y-auto bg-white border border-zinc-200 rounded-xl shadow-xl py-1 text-xs divide-y divide-zinc-50">
                        <button type="button" 
                                @click="editContactForm.package_id = ''; openContactPkg = false"
                                class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                :class="!editContactForm.package_id ? 'font-bold text-black bg-zinc-50' : 'text-zinc-500'">
                            <span>-- Tidak ada paket / Reset --</span>
                            <svg x-show="!editContactForm.package_id" class="w-3.5 h-3.5 text-black shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                        </button>
                        <template x-for="pkg in packages" :key="pkg.id">
                            <button type="button" 
                                    @click="editContactForm.package_id = pkg.id; openContactPkg = false"
                                    class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                    :class="editContactForm.package_id == pkg.id ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                <span class="truncate pr-2" x-text="`${pkg.name} (${pkg.price})`"></span>
                                <svg x-show="editContactForm.package_id == pkg.id" class="w-3.5 h-3.5 text-black shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                            </button>
                        </template>
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Catatan Prospek</label>
                    <textarea x-model="editContactForm.notes" rows="2" placeholder="Catatan preferensi, rencana keberangkatan..."
                              class="w-full px-3 py-2 border border-zinc-300 rounded-lg focus:border-black focus:ring-1 focus:ring-black text-xs"></textarea>
                </div>

                <div class="pt-3 flex items-center justify-end gap-2 border-t border-zinc-100">
                    <button type="button" @click="openEditModal = false" class="px-3 py-1.5 border border-zinc-300 rounded-lg text-zinc-700 hover:bg-zinc-50">Batal</button>
                    <button type="submit" :disabled="isSavingContact" class="px-4 py-1.5 bg-black hover:bg-zinc-800 text-white rounded-lg font-semibold">
                        <span x-show="!isSavingContact">Simpan Kontak</span>
                        <span x-show="isSavingContact">Menyimpan...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: KONFIRMASI HAPUS / TARIK PESAN (CLEAN WHATSAPP COPY) -->
    <div x-show="deleteTargetMessage" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="deleteTargetMessage = null" class="bg-white border border-zinc-200 rounded-2xl max-w-sm w-full p-5 shadow-2xl space-y-3">
            <h3 class="text-sm font-bold text-zinc-900">Hapus pesan?</h3>
            <p class="text-xs text-zinc-600 leading-normal">Hapus pesan ini untuk semua orang di obrolan?</p>

            <div class="pt-2 flex items-center justify-end gap-2">
                <button type="button" @click="deleteTargetMessage = null" 
                        class="px-3.5 py-1.5 border border-zinc-300 rounded-lg text-zinc-700 hover:bg-zinc-50 text-xs font-semibold transition">
                    Batal
                </button>
                <button type="button" @click="confirmDeleteMessage()" :disabled="isDeletingMessage" 
                        class="px-4 py-1.5 bg-black hover:bg-zinc-800 text-white rounded-lg text-xs font-bold transition disabled:opacity-50">
                    <span x-show="!isDeletingMessage">Hapus untuk Semua</span>
                    <span x-show="isDeletingMessage">Menghapus...</span>
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: PREVIEW & KIRIM MEDIA (GAMBAR / DOKUMEN) -->
    <div x-show="showMediaModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/60 flex items-center justify-center p-4">
        <div @click.away="cancelMediaUpload()" class="bg-white border border-zinc-200 rounded-2xl max-w-md w-full p-5 shadow-2xl space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-black text-white flex items-center justify-center shrink-0">
                        <template x-if="pendingMedia?.type === 'image'">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                                <circle cx="9" cy="9" r="2"/>
                                <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                            </svg>
                        </template>
                        <template x-if="pendingMedia?.type !== 'image'">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                            </svg>
                        </template>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-black" 
                            x-text="pendingMedia?.isPackageFlyer ? 'Kirim Flyer Paket Umroh' : (pendingMedia?.type === 'image' ? 'Kirim Gambar' : 'Kirim Berkas')"></h3>
                    </div>
                </div>
                <button type="button" @click="cancelMediaUpload()" class="text-zinc-400 hover:text-black font-bold text-lg">&times;</button>
            </div>

            <!-- Preview Body -->
            <div class="space-y-3">
                <!-- Package Flyer Info Banner -->
                <template x-if="pendingMedia?.isPackageFlyer">
                    <div class="px-3 py-2 bg-zinc-50 border border-zinc-200 rounded-xl flex items-center justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <span class="block font-bold text-zinc-900 text-xs truncate" x-text="pendingMedia?.packageName"></span>
                            <span class="block text-[10px] text-zinc-400" x-text="pendingMedia?.existingPath ? 'Flyer resmi paket (Database)' : 'File flyer kustom'"></span>
                        </div>
                        <button type="button" @click="$refs.flyerFileInput.click()" 
                                class="px-2.5 py-1 bg-white hover:bg-zinc-100 border border-zinc-300 text-zinc-800 rounded-lg text-[10px] font-bold shrink-0 transition cursor-pointer shadow-2xs">
                            Ganti File
                        </button>
                    </div>
                </template>

                <!-- Image Preview -->
                <template x-if="pendingMedia?.type === 'image'">
                    <div class="relative rounded-xl overflow-hidden border border-zinc-200 bg-zinc-50 flex items-center justify-center max-h-64 p-2">
                        <img :src="pendingMedia?.previewUrl" class="max-h-60 w-auto object-contain rounded-lg shadow-2xs" alt="Preview Gambar">
                    </div>
                </template>

                <!-- Document Card Preview -->
                <template x-if="pendingMedia?.type !== 'image'">
                    <div class="p-3 bg-zinc-50 border border-zinc-200 rounded-xl flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-black text-white flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-bold text-xs text-zinc-900 truncate" x-text="pendingMedia?.name"></div>
                            <div class="text-[11px] text-zinc-500" x-text="pendingMedia?.sizeFormatted"></div>
                        </div>
                    </div>
                </template>

                <!-- Caption Input -->
                <div>
                    <label class="block font-semibold text-zinc-600 mb-1 text-[11px]">Keterangan Pesan (Caption WhatsApp):</label>
                    <textarea x-model="pendingMedia.caption" rows="2" 
                              placeholder="Tambah keterangan pesan..."
                              class="w-full px-3 py-2 border border-zinc-300 rounded-xl text-xs text-zinc-900 focus:outline-none focus:border-black focus:ring-1 focus:ring-black resize-none leading-relaxed"></textarea>
                </div>
            </div>

            <div class="pt-2 flex items-center justify-end gap-2 border-t border-zinc-100">
                <button type="button" @click="cancelMediaUpload()" 
                        :disabled="isSendingMedia"
                        class="px-3.5 py-1.5 border border-zinc-300 rounded-lg text-zinc-700 hover:bg-zinc-50 text-xs font-semibold cursor-pointer">
                    Batal
                </button>
                <button type="button" @click="submitMediaUpload()" 
                        :disabled="isSendingMedia"
                        class="px-4 py-1.5 bg-black hover:bg-zinc-800 disabled:bg-zinc-200 disabled:text-zinc-400 text-white rounded-lg text-xs font-bold flex items-center gap-1.5 shadow-xs cursor-pointer">
                    <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                    </svg>
                    <span x-show="!isSendingMedia" x-text="pendingMedia?.isPackageFlyer ? 'Kirim Flyer ke WhatsApp' : 'Kirim'"></span>
                    <span x-show="isSendingMedia" x-text="pendingMedia?.isPackageFlyer ? 'Mengirim Flyer...' : 'Mengirim...'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: LIGHTBOX IMAGE VIEWER -->
    <div x-show="lightboxUrl" x-cloak class="fixed inset-0 z-50 overflow-hidden bg-black/90 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="lightboxUrl = null" class="relative max-w-4xl w-full max-h-[90vh] flex flex-col items-center">
            <!-- Close Button -->
            <button type="button" @click="lightboxUrl = null" 
                    class="absolute -top-10 right-0 text-white hover:text-zinc-300 font-bold text-2xl p-1 leading-none">
                &times;
            </button>
            
            <img :src="lightboxUrl" class="max-w-full max-h-[80vh] object-contain rounded-lg shadow-2xl" alt="Lihat Foto">

            <div class="mt-3 flex items-center gap-3">
                <a :href="lightboxUrl" download target="_blank"
                   class="px-3 py-1.5 bg-white hover:bg-zinc-100 text-black text-xs font-semibold rounded-lg shadow-xs flex items-center gap-1.5 transition">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>Unduh Gambar</span>
                </a>
                <button type="button" @click="lightboxUrl = null" 
                        class="px-3 py-1.5 bg-zinc-800 hover:bg-zinc-700 text-white text-xs font-semibold rounded-lg transition">
                    Tutup
                </button>
            </div>
        </div>
    </div>

</div>

<script>
function whatsappChatWorkspace(config) {
    config = config || {};
    return {
        brandId: config.brandId || 1,
        csName: config.csName || 'Fitri',
        brandName: config.brandName || 'Travel Umroh',
        brandBank: config.brandBank || 'Bank Rekening Resmi Perusahaan',
        brandHolder: config.brandHolder || '',
        brandPpiu: config.brandPpiu || '-',
        packages: config.packages || [],
        selectedPackageId: null,
        rawScripts: config.rawScripts || {},
        copilotSearch: '',
        connectionStatus: 'disconnected',
        chats: [],
        filteredChats: [],
        searchQuery: '',
        activeChat: null,
        activeMessages: [],
        messagesLoading: false,
        messageInput: '',
        isSending: false,
        replyingTo: null,
        isScrolledUp: false,
        unreadBelowCount: 0,
        activeEmojiTab: 'smileys',
        emojiList: {
            smileys: ['😊', '🙏', '🤲', '😄', '😁', '😃', '😅', '😇', '🥰', '😍', '🤩', '😘', '🙂', '🤗', '🤔', '🤝', '👍', '👌', '👏', '🙌', '🎉'],
            gestures: ['👍', '👌', '👏', '🤝', '🙌', '🙏', '🤲', '✌️', '🤞', '💪', '👋', '✍️', '☝️', '👉', '👇', '👈', '🫡', '❤️', '🌹', '✨', '⭐'],
            umroh: ['🕋', '🕌', '✈️', '🧳', '🤲', '🙏', '📖', '🌙', '⭐', '🏨', '🚌', '🌴', '☕', '💧', '🏷️', '🎟️', '📋', '✅', '📌', '🤍', '💚'],
            symbols: ['✅', '✨', '⭐', '🔥', '💡', '📌', '📍', '💰', '💳', '💵', '📞', '📱', '💬', '📢', '⏰', '📅', '🎯', '💯', '🚀', '🎁', '⚠️']
        },
        prospectPanelOpen: true,
        rightTab: 'prospect', // 'prospect' | 'copilot'
        // Copilot state
        copilotCategories: [
            { file: 'greeting',       label: 'Greeting' },
            { file: 'identification', label: 'Identifikasi' },
            { file: 'offer',          label: 'Penawaran' },
            { file: 'closing',        label: 'Closing' },
            { file: 'objection',      label: 'Keberatan' },
            { file: 'followups',      label: 'Follow Up' },
        ],
        copilotActiveFile: 'greeting',
        copilotScripts: [],
        copilotLoading: false,
        copilotSearch: '',
        npgdFilter: 'all', // 'all' | 'N' | 'P' | 'G' | 'D' | 'Q'
        get filteredCopilotScripts() {
            let list = this.copilotScripts;
            if (this.copilotActiveFile === 'identification' && this.npgdFilter && this.npgdFilter !== 'all') {
                list = list.filter(s => s.npgd && s.npgd.code === this.npgdFilter);
            }
            if (!this.copilotSearch.trim()) return list;
            const q = this.copilotSearch.toLowerCase();
            return list.filter(s =>
                (s.title || '').toLowerCase().includes(q) ||
                (s.script || '').toLowerCase().includes(q) ||
                (s.npgd && s.npgd.label && s.npgd.label.toLowerCase().includes(q)) ||
                (s.npgd && s.npgd.desc && s.npgd.desc.toLowerCase().includes(q))
            );
        },
        activeProspect: null,
        isSavingProspect: false,
        prospectSavedSuccess: false,
        isRegisteringProspect: false,
        newProspectName: '',
        newProspectPackageId: '',
        prospectForm: {
            id: 0,
            name: '',
            phone: '',
            package_id: '',
            status: 'new',
            current_stage: 'greeting',
            notes: '',
            next_followup_date: '',
            pax_quad: 0,
            pax_triple: 0,
            pax_double: 0,
            pax_infant: 0
        },
        copilotOpen: false,
        copilotTab: 'sop',
        sopStage: 'greeting',
        mobileTab: 'chats',
        openNewChatModal: false,
        newChatPhone: '',
        newChatName: '',
        newChatMessage: '',
        openEditModal: false,
        isSavingContact: false,
        deleteTargetMessage: null,
        isDeletingMessage: false,
        showMediaModal: false,
        pendingMedia: null,
        isSendingMedia: false,
        lightboxUrl: null,
        editContactForm: {
            id: 0,
            name: '',
            phone: '',
            package_id: '',
            notes: '',
            remote_jid: ''
        },
        pollTimer: null,
        isFetchingPhotos: false,
        initialJid: config.initialJid || '',
        initialProspectId: config.initialProspectId || 0,

        async init() {
            await this.checkStatus();
            if (this.connectionStatus === 'connected') {
                await this.loadChats();

                // Auto-select initial conversation ONLY if explicitly requested via URL (e.g. from prospects list)
                if (this.initialJid) {
                    const found = this.chats.find(c => c.remote_jid === this.initialJid);
                    if (found) {
                        this.selectChat(found);
                    } else {
                        this.selectChat({ remote_jid: this.initialJid, phone: this.initialJid.split('@')[0], prospect_id: this.initialProspectId });
                    }
                } else if (this.initialProspectId) {
                    const foundP = this.chats.find(c => c.prospect_id == this.initialProspectId);
                    if (foundP) {
                        this.selectChat(foundP);
                    }
                }
            } else {
                this.chats = [];
                this.filteredChats = [];
                this.activeChat = null;
                this.activeMessages = [];
            }

            // Set background pollers
            this.pollTimer = setInterval(() => {
                this.checkStatus();
                if (this.connectionStatus === 'connected') {
                    this.loadChats(true);
                    if (this.activeChat) {
                        this.loadMessages(this.activeChat.remote_jid, true);
                    }
                }
            }, 3000);
        },

        changeBrand(newId) {
            window.location.href = 'chat.php?brand_id=' + newId;
        },

        async checkStatus() {
            try {
                const res = await fetch(`api/whatsapp.php?action=status&brand_id=${this.brandId}`);
                const data = await res.json();
                if (data.success) {
                    const prevStatus = this.connectionStatus;
                    this.connectionStatus = data.status;
                    if (this.connectionStatus !== 'connected') {
                        this.chats = [];
                        this.filteredChats = [];
                        this.activeChat = null;
                        this.activeMessages = [];
                    } else if (prevStatus !== 'connected') {
                        // Newly reconnected, fetch chats
                        await this.loadChats();
                    }
                }
            } catch (e) {}
        },

        async loadChats(isSilent = false) {
            if (this.connectionStatus !== 'connected') {
                this.chats = [];
                this.filteredChats = [];
                this.activeChat = null;
                this.activeMessages = [];
                return;
            }
            try {
                const res = await fetch(`api/whatsapp.php?action=chats_list&brand_id=${this.brandId}`);
                const data = await res.json();
                if (data.success) {
                    if (data.connected === false) {
                        this.connectionStatus = data.status || 'disconnected';
                        this.chats = [];
                        this.filteredChats = [];
                        this.activeChat = null;
                        this.activeMessages = [];
                        return;
                    }
                    this.chats = data.data || [];
                    // Preserve local read state for currently activeChat
                    if (this.activeChat) {
                        const activeInList = this.chats.find(c => c.remote_jid === this.activeChat.remote_jid);
                        if (activeInList) {
                            activeInList.is_unread = 0;
                            activeInList.last_status = 'read';
                            activeInList.unread_count = 0;
                        }
                    }
                    this.filterChats();

                    // Auto-fetch missing profile pictures in background
                    const missingPhotos = this.chats.filter(c => !c.photo_url && (c.phone || c.remote_jid)).map(c => ({
                        phone: c.phone || '',
                        jid: c.remote_jid || ''
                    }));
                    if (missingPhotos.length > 0) {
                        this.fetchMissingProfilePictures(missingPhotos);
                    }
                }
            } catch (e) {}
        },

        filterChats() {
            if (!this.searchQuery.trim()) {
                this.filteredChats = this.chats;
                return;
            }
            const q = this.searchQuery.toLowerCase();
            this.filteredChats = this.chats.filter(c => {
                const title = this.getChatTitle(c).toLowerCase();
                const phone = (c.phone || '').toLowerCase();
                const lastMsg = (c.last_message || '').toLowerCase();
                return title.includes(q) || phone.includes(q) || lastMsg.includes(q);
            });
        },

        async selectChat(chat) {
            this.activeChat = chat;
            this.prospectSavedSuccess = false;
            if (chat) {
                // Immediately mark as read in local UI state
                chat.is_unread = 0;
                chat.last_status = 'read';
                chat.unread_count = 0;
                this.newProspectName = this.getChatTitle(chat);
                this.newProspectPackageId = chat.package_id || '';
            }

            // Immediately populate activeProspect if contact is already a registered prospect
            // This guarantees the form renders instantly (0ms) without any flash/flicker of the "Kontak Baru" view
            if (chat && chat.prospect_id) {
                this.activeProspect = {
                    id: chat.prospect_id,
                    name: chat.prospect_name || chat.sender_name || '',
                    phone: chat.phone || '',
                    package_id: chat.package_id || '',
                    status: chat.prospect_status || 'new',
                    current_stage: chat.prospect_stage || 'greeting',
                    notes: chat.notes || '',
                    next_followup_date: chat.next_followup_date ? chat.next_followup_date.substring(0, 10) : '',
                    pax_quad: parseInt(chat.pax_quad) || 0,
                    pax_triple: parseInt(chat.pax_triple) || 0,
                    pax_double: parseInt(chat.pax_double) || 0,
                    pax_infant: parseInt(chat.pax_infant) || 0,
                    meta_referral_marker: chat.meta_referral_marker || null,
                    photo_url: chat.photo_url || null,
                    package_name: chat.package_name || '',
                    package_price: chat.package_price || '',
                    package_dp: chat.package_dp || '',
                    package_airline: chat.package_airline || '',
                    package_hotel_makkah: chat.hotel_makkah || '',
                    package_hotel_madinah: chat.hotel_madinah || '',
                    package_departure_info: chat.departure_info || '',
                    package_duration: chat.duration || '',
                    package_highlights: chat.highlights || ''
                };
                this.syncProspectForm(this.activeProspect);
            } else {
                this.activeProspect = null;
            }
            this.selectedPackageId = (chat && chat.package_id) ? chat.package_id : null;
            this.mobileTab = 'thread';
            this.isScrolledUp = false;
            this.unreadBelowCount = 0;
            await this.loadMessages(chat.remote_jid, false);
            this.$nextTick(() => {
                const el = document.getElementById('chatMessageInput');
                if (el) el.focus();
            });
        },

        async loadMessages(remoteJid, isSilent = false) {
            if (!isSilent) this.messagesLoading = true;
            try {
                // Check if user was near bottom before load (within 150px)
                const wasNearBottom = !this.isScrolledUp;
                const prevCount = (this.activeMessages || []).length;

                const pPhone = encodeURIComponent(this.activeChat?.phone || '');
                const pId = encodeURIComponent(this.activeChat?.prospect_id || '');
                const res = await fetch(`api/whatsapp.php?action=messages&brand_id=${this.brandId}&remote_jid=${encodeURIComponent(remoteJid)}&phone=${pPhone}&prospect_id=${pId}`);
                const data = await res.json();
                if (data.success) {
                    const prevShowMap = {};
                    (this.activeMessages || []).forEach(m => {
                        if (m.show_deleted) prevShowMap[m.message_id || m.id] = true;
                    });
                    this.activeMessages = (data.messages || []).map(m => {
                        const key = m.message_id || m.id;
                        m.show_deleted = !!prevShowMap[key];
                        return m;
                    });

                    if (data.prospect) {
                        this.activeProspect = data.prospect;
                        // Only sync form on initial chat click (!isSilent) or when switching prospect
                        // This prevents 3-second background polling from overwriting user inputs/notes
                        if (!isSilent || !this.prospectForm.id || this.prospectForm.id !== data.prospect.id) {
                            this.syncProspectForm(data.prospect);
                        }
                        this.activeChat.prospect_id = data.prospect.id;
                        if (data.prospect.name && !data.prospect.name.startsWith('Calon Jamaah') && !data.prospect.name.startsWith('Jamaah ')) {
                            this.activeChat.prospect_name = data.prospect.name;
                        }
                        if (data.prospect.photo_url) {
                            this.activeChat.photo_url = data.prospect.photo_url;
                        }
                        this.activeChat.prospect_status = data.prospect.status;
                        this.activeChat.package_id = data.prospect.package_id;
                        this.activeChat.package_name = data.prospect.package_name;
                        this.activeChat.meta_referral_marker = data.prospect.meta_referral_marker;
                        this.selectedPackageId = data.prospect.package_id || null;
                    } else {
                        this.activeProspect = null;
                        this.newProspectName = this.getChatTitle(this.activeChat) || '';
                        this.newProspectPackageId = '';
                        this.selectedPackageId = null;
                    }
                    if (data.packages && data.packages.length > 0) {
                        this.packages = data.packages;
                    }

                    const newCount = (data.messages || []).length;
                    const hasNewIncoming = newCount > prevCount;

                    if (isSilent && !wasNearBottom && hasNewIncoming) {
                        this.unreadBelowCount += (newCount - prevCount);
                    }

                    // Auto-scroll to bottom ONLY if:
                    // 1. Initial click on chat (!isSilent)
                    // 2. OR user was already at the bottom and a new message arrived
                    if (!isSilent) {
                        this.unreadBelowCount = 0;
                        this.isScrolledUp = false;
                        this.$nextTick(() => {
                            this.scrollToBottom(true);
                        });
                    } else if (wasNearBottom && hasNewIncoming) {
                        this.$nextTick(() => {
                            this.scrollToBottom(false);
                        });
                    }
                    // When isSilent and user has scrolled up to read history: DO NOT SCROLL!
                }
            } catch (e) {
            } finally {
                if (!isSilent) this.messagesLoading = false;
            }
        },

        onChatScroll() {
            const el = document.getElementById('chatStreamBox');
            if (!el) return;
            const distanceFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight;
            this.isScrolledUp = distanceFromBottom > 150;
            if (!this.isScrolledUp) {
                this.unreadBelowCount = 0;
            }
        },

        scrollToBottom(force = false) {
            const el = document.getElementById('chatStreamBox');
            if (!el) return;
            if (force) {
                el.scrollTop = el.scrollHeight;
            } else {
                el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
            }
            this.isScrolledUp = false;
            this.unreadBelowCount = 0;
        },

        openDeleteModal(m) {
            this.deleteTargetMessage = m;
        },

        async confirmDeleteMessage() {
            if (!this.deleteTargetMessage || this.isDeletingMessage) return;
            const target = this.deleteTargetMessage;
            this.isDeletingMessage = true;

            try {
                const res = await fetch('api/whatsapp.php?action=delete_message', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        brand_id: this.brandId,
                        message_id: target.message_id,
                        remote_jid: target.remote_jid || this.activeChat?.remote_jid || '',
                        phone: target.phone || this.activeChat?.phone || ''
                    })
                });
                const data = await res.json();
                if (data.success) {
                    target.is_deleted = 1;
                    target.show_deleted = false;
                    this.deleteTargetMessage = null;
                    if (this.activeChat) {
                        this.activeChat.last_is_deleted = 1;
                    }
                    this.loadChats(true);
                } else {
                    alert(data.error || 'Gagal menarik pesan');
                }
            } catch (e) {
                alert('Gagal menghubungi gateway WhatsApp');
            } finally {
                this.isDeletingMessage = false;
            }
        },

        async toggleReaction(m, emoji) {
            if (!m || !this.activeChat) return;
            const newReaction = (m.reaction === emoji) ? '' : emoji;
            const prevReaction = m.reaction;
            
            // Optimistic update
            m.reaction = newReaction;

            try {
                const res = await fetch('api/whatsapp.php?action=react', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        brand_id: this.brandId,
                        remote_jid: m.remote_jid || this.activeChat.remote_jid,
                        phone: m.phone || this.activeChat.phone || '',
                        message_id: m.message_id,
                        from_me: m.is_from_me == 1,
                        emoji: newReaction
                    })
                });
                const data = await res.json();
                if (!data.success) {
                    m.reaction = prevReaction;
                    alert(data.error || 'Gagal mengirim reaksi');
                }
            } catch (e) {
                m.reaction = prevReaction;
                alert('Gagal menghubungi gateway WhatsApp');
            }
        },

        insertEmoji(emoji) {
            const textarea = document.getElementById('chatMessageInput');
            if (!textarea) {
                this.messageInput += emoji;
                return;
            }
            const start = textarea.selectionStart ?? this.messageInput.length;
            const end = textarea.selectionEnd ?? this.messageInput.length;
            const text = this.messageInput;
            this.messageInput = text.substring(0, start) + emoji + text.substring(end);
            this.$nextTick(() => {
                textarea.focus();
                textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
            });
        },

        setReplyingTo(m) {
            this.replyingTo = m;
            this.$nextTick(() => {
                const textarea = document.getElementById('chatMessageInput');
                if (textarea) textarea.focus();
            });
        },

        cancelReply() {
            this.replyingTo = null;
        },

        async sendMessage() {
            const text = this.messageInput.trim();
            if (!text || !this.activeChat || this.isSending) return;

            this.isSending = true;
            const quoted = this.replyingTo ? {
                message_id: this.replyingTo.message_id,
                text: (this.replyingTo.message_text || (this.replyingTo.media_url ? '[Lampiran Berkas / Gambar]' : '')).substring(0, 300),
                sender: this.replyingTo.sender_name || (this.replyingTo.is_from_me == 1 ? 'Anda' : 'Calon Jamaah'),
                from_me: this.replyingTo.is_from_me == 1
            } : null;

            try {
                const res = await fetch('api/whatsapp.php?action=send_message', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        brand_id: this.brandId,
                        remote_jid: this.activeChat.remote_jid,
                        phone: this.activeChat.phone || '',
                        text: text,
                        prospect_id: this.activeChat.prospect_id || null,
                        quoted_message_id: quoted ? quoted.message_id : null,
                        quoted_text: quoted ? quoted.text : null,
                        quoted_sender: quoted ? quoted.sender : null,
                        quoted_from_me: quoted ? quoted.from_me : false
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.messageInput = '';
                    const txtEl = document.getElementById('chatMessageInput');
                    if (txtEl) txtEl.style.height = 'auto';
                    this.replyingTo = null;
                    this.activeMessages.push({
                        message_id: data.message_id,
                        remote_jid: this.activeChat.remote_jid,
                        phone: this.activeChat.phone || '',
                        sender_name: data.sender_name || this.csName || 'CS',
                        is_from_me: 1,
                        is_deleted: 0,
                        show_deleted: false,
                        message_text: text,
                        status: data.status || 'sent',
                        timestamp: data.timestamp,
                        quoted_message_id: quoted ? quoted.message_id : null,
                        quoted_text: quoted ? quoted.text : null,
                        quoted_sender: quoted ? quoted.sender : null,
                        reaction: null
                    });
                    this.$nextTick(() => this.scrollToBottom(true));
                    this.loadChats(true);
                } else {
                    alert(data.error || 'Gagal mengirim pesan');
                }
            } catch (e) {
                alert('Gagal menghubungi gateway WhatsApp');
            } finally {
                this.isSending = false;
            }
        },

        onFileSelected(event, type) {
            const file = event.target.files?.[0];
            if (!file) return;

            if (!this.activeChat) {
                alert('Pilih kontak obrolan terlebih dahulu untuk mengirim lampiran.');
                event.target.value = '';
                return;
            }

            const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
            let sizeFormatted = `${sizeMb} MB`;
            if (file.size < 1024 * 1024) {
                sizeFormatted = `${(file.size / 1024).toFixed(1)} KB`;
            }

            let previewUrl = null;
            if (type === 'image' || file.type.startsWith('image/')) {
                type = 'image';
                previewUrl = URL.createObjectURL(file);
            }

            this.pendingMedia = {
                file: file,
                type: type,
                name: file.name,
                sizeFormatted: sizeFormatted,
                previewUrl: previewUrl,
                caption: ''
            };
            this.showMediaModal = true;
            event.target.value = '';
        },

        cancelMediaUpload() {
            if (this.pendingMedia?.previewUrl && this.pendingMedia?.file) {
                try { URL.revokeObjectURL(this.pendingMedia.previewUrl); } catch (e) {}
            }
            this.pendingMedia = null;
            this.showMediaModal = false;
            this.isSendingMedia = false;
        },

        openSendFlyerModal() {
            if (!this.activeChat) {
                alert('Pilih kontak obrolan terlebih dahulu untuk mengirim flyer.');
                return;
            }

            const pkg = this.selectedProspectPackage;
            if (!pkg) {
                alert('Pilih paket umroh terlebih dahulu.');
                return;
            }

            let cleanTitle = (pkg.name || '').trim();
            if (!cleanTitle.match(/^paket\s+/i)) {
                cleanTitle = cleanTitle.match(/^umroh\s+/i) ? ('Paket ' + cleanTitle) : ('Paket Umroh ' + cleanTitle);
            }

            let caption = `*${cleanTitle.toUpperCase()}*\n`;
            caption += `Travel: ${this.brandName}\n\n`;
            caption += `📅 Jadwal: ${pkg.departure_date || pkg.departure_info || '-'} (${pkg.duration || '9 Hari'})\n`;
            caption += `✈️ Maskapai: ${pkg.airline || '-'}\n`;
            if (pkg.hotel_makkah) caption += `🏨 Hotel Makkah: ${pkg.hotel_makkah}\n`;
            if (pkg.hotel_madinah) caption += `🏨 Hotel Madinah: ${pkg.hotel_madinah}\n`;
            caption += `💰 Quad Mulai: ${pkg.price_quad || pkg.price}\n\n`;
            caption += `Berikut brosur & flyer resmi perjalanannya ya Kak. Silakan dipelajari detail jadwal & fasilitasnya, jika ada yang ingin ditanyakan silakan balas pesan ini ya Kak 🙏`;

            if (pkg.flyer_image) {
                this.pendingMedia = {
                    file: null,
                    existingPath: pkg.flyer_image,
                    type: 'image',
                    name: 'Flyer - ' + cleanTitle,
                    sizeFormatted: 'Flyer Brosur Resmi',
                    previewUrl: pkg.flyer_image,
                    caption: caption,
                    isPackageFlyer: true,
                    packageName: cleanTitle
                };
                this.showMediaModal = true;
            } else {
                if (this.$refs.flyerFileInput) {
                    this.$refs.flyerFileInput.click();
                }
            }
        },

        handleFlyerFileSelected(event) {
            const file = event.target.files?.[0];
            if (!file) return;

            if (!this.activeChat) {
                alert('Pilih kontak obrolan terlebih dahulu.');
                event.target.value = '';
                return;
            }

            const pkg = this.selectedProspectPackage;
            let cleanTitle = pkg ? (pkg.name || '').trim() : 'Paket Umroh';
            if (!cleanTitle.match(/^paket\s+/i)) {
                cleanTitle = cleanTitle.match(/^umroh\s+/i) ? ('Paket ' + cleanTitle) : ('Paket Umroh ' + cleanTitle);
            }

            let caption = `*${cleanTitle.toUpperCase()}*\n`;
            caption += `Travel: ${this.brandName}\n\n`;
            if (pkg) {
                caption += `📅 Jadwal: ${pkg.departure_date || pkg.departure_info || '-'} (${pkg.duration || '9 Hari'})\n`;
                caption += `✈️ Maskapai: ${pkg.airline || '-'}\n`;
                caption += `💰 Quad Mulai: ${pkg.price_quad || pkg.price}\n\n`;
            }
            caption += `Berikut brosur & flyer resmi perjalanannya ya Kak. Silakan dipelajari detail jadwal & fasilitasnya, jika ada yang ingin ditanyakan silakan balas pesan ini ya Kak 🙏`;

            const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
            let sizeFormatted = `${sizeMb} MB`;
            if (file.size < 1024 * 1024) {
                sizeFormatted = `${(file.size / 1024).toFixed(1)} KB`;
            }

            this.pendingMedia = {
                file: file,
                existingPath: null,
                type: 'image',
                name: file.name,
                sizeFormatted: sizeFormatted,
                previewUrl: URL.createObjectURL(file),
                caption: caption,
                isPackageFlyer: true,
                packageName: cleanTitle
            };
            this.showMediaModal = true;
            event.target.value = '';
        },

        async submitMediaUpload() {
            if (!this.pendingMedia || (!this.pendingMedia.file && !this.pendingMedia.existingPath) || !this.activeChat || this.isSendingMedia) return;

            this.isSendingMedia = true;
            const formData = new FormData();
            formData.append('action', 'send_media');
            formData.append('brand_id', this.brandId);
            formData.append('remote_jid', this.activeChat.remote_jid);
            formData.append('phone', this.activeChat.phone || '');
            formData.append('prospect_id', this.activeChat.prospect_id || '');
            formData.append('media_type', this.pendingMedia.type);
            formData.append('caption', this.pendingMedia.caption || '');

            if (this.pendingMedia.existingPath) {
                formData.append('existing_file_path', this.pendingMedia.existingPath);
            } else if (this.pendingMedia.file) {
                formData.append('file', this.pendingMedia.file);
            }

            try {
                const res = await fetch('api/whatsapp.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    this.activeMessages.push({
                        message_id: data.message_id,
                        remote_jid: this.activeChat.remote_jid,
                        phone: this.activeChat.phone || '',
                        sender_name: data.sender_name || this.csName || 'CS',
                        is_from_me: 1,
                        is_deleted: 0,
                        show_deleted: false,
                        message_type: data.media_type,
                        media_url: data.media_url,
                        message_text: data.text,
                        status: data.status || 'sent',
                        timestamp: data.timestamp
                    });
                    this.cancelMediaUpload();
                    this.$nextTick(() => this.scrollToBottom(true));
                    this.loadChats(true);
                } else {
                    alert(data.error || 'Gagal mengirim media ke WhatsApp');
                    this.isSendingMedia = false;
                }
            } catch (e) {
                alert('Gagal menghubungi gateway WhatsApp');
                this.isSendingMedia = false;
            }
        },

        openLightbox(url) {
            if (url) this.lightboxUrl = url;
        },

        isImageMessage(m) {
            if (!m) return false;
            if (m.message_type === 'image' || m.message_type === 'imageMessage') return true;
            if (m.media_url && /\.(jpg|jpeg|png|webp|gif)$/i.test(m.media_url)) return true;
            return false;
        },

        isVideoMessage(m) {
            if (!m) return false;
            if (m.message_type === 'video' || m.message_type === 'videoMessage') return true;
            if (m.media_url && /\.(mp4|m4v|mov|webm|mkv|3gp)$/i.test(m.media_url)) return true;
            return false;
        },

        isAudioMessage(m) {
            if (!m) return false;
            if (m.message_type === 'audio' || m.message_type === 'audioMessage' || m.message_type === 'voice' || m.message_type === 'ptt') return true;
            if (m.media_url && /\.(ogg|opus|mp3|m4a|wav|aac)$/i.test(m.media_url)) return true;
            return false;
        },

        getDocumentName(m) {
            if (!m) return 'Dokumen';
            if (m.message_text && !m.message_text.startsWith('uploads/') && !m.message_text.startsWith('http') && m.message_text !== '[Dokumen]') {
                return m.message_text;
            }
            if (m.media_url) {
                const parts = m.media_url.split('/');
                return parts[parts.length - 1];
            }
            return 'Dokumen';
        },

        hasDisplayText(m) {
            if (!m || !m.message_text) return false;
            const txt = m.message_text.trim();
            if (!txt) return false;
            if (m.media_url) {
                if (this.isImageMessage(m) && (txt === '[Gambar]' || txt === m.media_url)) return false;
                if (this.isVideoMessage(m) && (txt === '[Video]' || txt === m.media_url)) return false;
                if (this.isAudioMessage(m) && (txt === '[Voice Note]' || txt === '[Audio]' || txt === m.media_url)) return false;
                if (!this.isImageMessage(m) && !this.isVideoMessage(m) && !this.isAudioMessage(m) && (txt === '[Dokumen]' || txt === m.media_url || txt === this.getDocumentName(m))) return false;
            }
            return true;
        },

        async startNewChat() {
            let phone = this.newChatPhone.trim().replace(/[^0-9]/g, '');
            if (!phone) return;
            if (phone.startsWith('0')) phone = '62' + phone.substring(1);
            if (!phone.startsWith('62')) phone = '62' + phone;

            const targetJid = phone + '@s.whatsapp.net';
            const text = this.newChatMessage.trim();

            this.isSending = true;
            try {
                const res = await fetch('api/whatsapp.php?action=send_message', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        brand_id: this.brandId,
                        remote_jid: targetJid,
                        text: text
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.openNewChatModal = false;
                    this.newChatPhone = '';
                    this.newChatName = '';
                    this.newChatMessage = '';
                    await this.loadChats();
                    const standardPhone = '0' + phone.substring(2);
                    const matchingChat = this.chats.find(c => c.phone === standardPhone || c.phone === phone || c.remote_jid === targetJid);
                    if (matchingChat) {
                        this.selectChat(matchingChat);
                    } else {
                        this.selectChat({ remote_jid: targetJid, phone: standardPhone });
                    }
                } else {
                    alert(data.error || 'Gagal mengirim pesan');
                }
            } catch (e) {
                alert('Gagal menghubungi gateway WhatsApp');
            } finally {
                this.isSending = false;
            }
        },

        async updateProspectStatus(newStatus) {
            if (!this.activeChat || !this.activeChat.prospect_id) return;

            try {
                const res = await fetch('api/prospects.php?action=update_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: this.activeChat.prospect_id,
                        status: newStatus
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.activeChat.prospect_status = newStatus;
                    this.loadChats(true);

                    if (newStatus === 'closed_won' && data.meta_capi) {
                        alert(data.meta_capi.message || 'Status Closing Won & Meta CAPI Event Purchase berhasil dikirim!');
                    }
                }
            } catch (e) {
                alert('Gagal memperbarui status');
            }
        },

        useScriptInChat(text) {
            this.messageInput = text;
            if (this.mobileTab !== 'thread') {
                this.mobileTab = 'thread';
            }
            this.$nextTick(() => {
                const el = document.getElementById('chatMessageInput');
                if (el) {
                    el.style.height = 'auto';
                    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
                    el.focus();
                    el.scrollTop = el.scrollHeight;
                }
            });
        },

        handleChatInputKeydown(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (this.messageInput && this.messageInput.trim() && !this.isSending) {
                    this.sendMessage();
                }
            }
        },

        copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Skrip berhasil disalin ke clipboard!');
            });
        },

        // ── COPILOT METHODS ─────────────────────────────────────────────────
        async loadCopilotScripts() {
            this.copilotLoading = true;
            this.copilotScripts = [];
            try {
                const res = await fetch(`api/scripts.php?file=${this.copilotActiveFile}`);
                const data = await res.json();
                if (data.success) {
                    this.copilotScripts = data.scripts || [];
                }
            } catch(e) {
                console.error('Gagal load script copilot:', e);
            }
            this.copilotLoading = false;
        },

        replaceCopilotVars(text) {
            if (!text) return '';
            const p = this.activeProspect || {};
            const form = this.prospectForm || {};
            const nama = (form.name || p.name || 'Kak').trim();
            const firstName = nama.split(' ')[0] || 'Kak';

            // Brand & CS Data
            const brandName = this.brandName || <?= json_encode($brand['name'] ?? '') ?> || 'Travel Umroh Kami';
            const csName = this.csName || <?= json_encode($_SESSION['name'] ?? '') ?> || 'Layanan Jamaah';
            const ppiu = this.brandPpiu && this.brandPpiu !== '-' ? this.brandPpiu : (brandName + ' (Izin PPIU Resmi Kemenag)');
            const rekening = this.brandBank || 'Bank Rekening Resmi Perusahaan';
            const namaRekening = this.brandHolder || brandName;

            // Selected Package Data
            const pkg = this.selectedPackage || {};
            const pkgName = pkg.name || 'Paket Umroh Pilihan';
            
            // Format Rupiah helper
            const formatRupiah = (val) => {
                if (!val) return '';
                const clean = String(val).replace(/[^\d]/g, '');
                return clean ? 'Rp ' + Number(clean).toLocaleString('id-ID') : String(val);
            };

            const harga = formatRupiah(pkg.price) || 'harga resmi';
            const dp = formatRupiah(pkg.dp) || 'DP terjangkau';
            
            // Hotel
            const hotelList = [pkg.hotel_makkah, pkg.hotel_madinah].filter(Boolean);
            const hotel = hotelList.length > 0 ? hotelList.join(' & ') : 'hotel bintang pilihan dekat pelataran masjid';
            const jarakHotel = pkg.hotel_makkah && (pkg.hotel_makkah.includes('★5') || pkg.hotel_makkah.includes('Bintang 5')) ? '±50-150m ke pelataran masjid' : 'dekat pelataran masjid (jalan kaki)';
            
            const airline = pkg.airline || 'maskapai direct tanpa transit';
            const departure = pkg.departure_info || 'jadwal keberangkatan terdekat';
            const duration = pkg.duration || '9-12 Hari';
            const highlights = pkg.highlights || 'akomodasi hotel bintang, visa umroh, handling bandara PP, dan perlengkapan eksklusif';

            // Prospect Pax Calculation
            const paxTotal = this.totalPax || 0;
            const jumlahJamaah = paxTotal > 0 ? `${paxTotal} orang` : 'Kakak dan keluarga';

            // Dynamic Context & Smart Helpers
            const bulan = new Date().toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
            const deadline = 'besok pukul 17:00 WIB';
            const seat = 'sisa 4 seat lagi';
            const promo = 'free perlengkapan eksklusif & handling bandara';
            const followupDate = form.next_followup_date || 'jadwal yang ditentukan';

            return text
                .replace(/\{\{nama\}\}/gi, firstName)
                .replace(/\{\{cs_name\}\}/gi, csName)
                .replace(/\{\{agent_name\}\}/gi, csName)
                .replace(/\{\{travel\}\}/gi, brandName)
                .replace(/\{\{ppiu\}\}/gi, ppiu)
                .replace(/\{\{rekening\}\}/gi, rekening)
                .replace(/\{\{nama_rekening\}\}/gi, namaRekening)
                .replace(/\{\{paket\}\}/gi, pkgName)
                .replace(/\{\{harga\}\}/gi, harga)
                .replace(/\{\{dp\}\}/gi, dp)
                .replace(/\{\{hotel\}\}/gi, hotel)
                .replace(/\{\{jarak_hotel\}\}/gi, jarakHotel)
                .replace(/\{\{maskapai\}\}/gi, airline)
                .replace(/\{\{tanggal\}\}/gi, departure)
                .replace(/\{\{durasi\}\}/gi, duration)
                .replace(/\{\{fasilitas_utama\}\}/gi, highlights)
                .replace(/\{\{jumlah_jamaah\}\}/gi, jumlahJamaah)
                .replace(/\{\{bulan\}\}/gi, bulan)
                .replace(/\{\{deadline\}\}/gi, deadline)
                .replace(/\{\{seat\}\}/gi, seat)
                .replace(/\{\{promo\}\}/gi, promo)
                .replace(/\{\{followup_date\}\}/gi, followupDate)
                .replace(/\{\{kota\}\}/gi, 'Jakarta')
                .replace(/\{\{sumber\}\}/gi, 'WhatsApp resmi');
        },


        copyCopilotScript(script) {
            const text = this.replaceCopilotVars(script);
            navigator.clipboard.writeText(text).then(() => {
                this.showToast('Script disalin ke clipboard ✓');
            }).catch(() => {
                alert('Gagal menyalin. Salin manual dari teks di atas.');
            });
        },

        insertCopilotToChat(script, _steps) {
            // Always insert single script text into chat input (TGJP handled per-step in HTML)
            const text = this.replaceCopilotVars(script);
            this.messageInput = text;
            // Do NOT switch tab — keep copilot open so CS can continue using it
            this.$nextTick(() => {
                const ta = document.getElementById('chatMessageInput');
                if (ta) { ta.focus(); ta.selectionStart = ta.selectionEnd = ta.value.length; }
            });
        },

        getNpgdBadgeClass(code) {
            switch ((code || '').toLowerCase()) {
                case 'need':
                    return 'bg-blue-50 text-blue-700 border-blue-200';
                case 'pain':
                    return 'bg-rose-50 text-rose-700 border-rose-200';
                case 'gain':
                    return 'bg-emerald-50 text-emerald-700 border-emerald-200';
                case 'dream':
                    return 'bg-purple-50 text-purple-700 border-purple-200';
                case 'qualification':
                    return 'bg-amber-50 text-amber-700 border-amber-200';
                case 'combination':
                    return 'bg-indigo-50 text-indigo-700 border-indigo-200';
                case 'transition':
                    return 'bg-teal-50 text-teal-700 border-teal-200';
                default:
                    return 'bg-zinc-100 text-zinc-700 border-zinc-200';
            }
        },
        // ────────────────────────────────────────────────────────────────────

        async fetchMissingProfilePictures(contactList) {
            if (!contactList || contactList.length === 0 || this.isFetchingPhotos) return;
            this.isFetchingPhotos = true;
            try {
                const res = await fetch(`api/whatsapp.php?action=fetch_profile_pictures&brand_id=${this.brandId}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ contacts: contactList })
                });
                const data = await res.json();
                if (data.success && data.results) {
                    for (const key in data.results) {
                        const photo = data.results[key];
                        if (!photo) continue;
                        const cleanKey = key.replace(/[^0-9]/g, '');
                        const stdKey = cleanKey.startsWith('62') ? ('0' + cleanKey.substring(2)) : cleanKey;

                        for (const c of this.chats) {
                            const cPhone = (c.phone || '').replace(/[^0-9]/g, '');
                            const stdPhone = cPhone.startsWith('62') ? ('0' + cPhone.substring(2)) : cPhone;
                            if (c.remote_jid === key || (stdPhone && stdPhone === stdKey)) {
                                c.photo_url = photo;
                            }
                        }
                        if (this.activeChat) {
                            const aPhone = (this.activeChat.phone || '').replace(/[^0-9]/g, '');
                            const stdPhone = aPhone.startsWith('62') ? ('0' + aPhone.substring(2)) : aPhone;
                            if (this.activeChat.remote_jid === key || (stdPhone && stdPhone === stdKey)) {
                                this.activeChat.photo_url = photo;
                            }
                        }
                    }
                }
            } catch (e) {
                // Ignore profile photo errors
            }
        },

        syncProspectForm(p) {
            if (!p) return;
            this.prospectForm = {
                id: p.id || 0,
                name: p.name || '',
                phone: p.phone || '',
                package_id: p.package_id || '',
                status: p.status || 'new',
                current_stage: p.current_stage || 'greeting',
                notes: p.notes || '',
                next_followup_date: p.next_followup_date ? p.next_followup_date.substring(0, 10) : '',
                pax_quad: parseInt(p.pax_quad) || 0,
                pax_triple: parseInt(p.pax_triple) || 0,
                pax_double: parseInt(p.pax_double) || 0,
                pax_infant: parseInt(p.pax_infant) || 0
            };
        },

        changePax(type, delta) {
            const current = parseInt(this.prospectForm[type]) || 0;
            const updated = Math.max(0, current + delta);
            this.prospectForm[type] = updated;
        },

        get totalPax() {
            return (parseInt(this.prospectForm.pax_quad) || 0) +
                   (parseInt(this.prospectForm.pax_triple) || 0) +
                   (parseInt(this.prospectForm.pax_double) || 0) +
                   (parseInt(this.prospectForm.pax_infant) || 0);
        },

        get selectedProspectPackage() {
            if (this.prospectForm && this.prospectForm.package_id) {
                const found = this.packages.find(p => p.id == this.prospectForm.package_id);
                if (found) return found;
            }
            if (this.activeProspect && this.activeProspect.package_id) {
                const found = this.packages.find(p => p.id == this.activeProspect.package_id);
                if (found) return found;
            }
            return null;
        },

        async saveProspectDetails() {
            if (!this.activeProspect || !this.activeProspect.id || this.isSavingProspect) return;
            this.isSavingProspect = true;
            this.prospectSavedSuccess = false;

            const prospectName = (this.prospectForm.name || '').trim() || 
                                 (this.activeProspect.name || '').trim() || 
                                 this.getChatTitle(this.activeChat) || 
                                 'Calon Jamaah';

            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('id', this.activeProspect.id);
            formData.append('brand_id', this.brandId);
            formData.append('name', prospectName);
            formData.append('phone', this.prospectForm.phone || this.activeProspect.phone || '');
            formData.append('package_id', this.prospectForm.package_id || '');
            formData.append('status', this.prospectForm.status || 'new');
            formData.append('current_stage', this.prospectForm.current_stage || 'greeting');
            formData.append('notes', this.prospectForm.notes || '');
            formData.append('next_followup_date', this.prospectForm.next_followup_date || '');
            formData.append('pax_quad', parseInt(this.prospectForm.pax_quad) || 0);
            formData.append('pax_triple', parseInt(this.prospectForm.pax_triple) || 0);
            formData.append('pax_double', parseInt(this.prospectForm.pax_double) || 0);
            formData.append('pax_infant', parseInt(this.prospectForm.pax_infant) || 0);
            formData.append('remote_jid', this.activeChat?.remote_jid || '');

            try {
                const res = await fetch('api/prospects.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    this.prospectSavedSuccess = true;
                    if (data.prospect) {
                        this.activeProspect = data.prospect;
                        this.syncProspectForm(data.prospect);
                    } else {
                        this.activeProspect.name = prospectName;
                        this.activeProspect.phone = this.prospectForm.phone;
                        this.activeProspect.package_id = this.prospectForm.package_id;
                        this.activeProspect.status = this.prospectForm.status;
                        this.activeProspect.notes = this.prospectForm.notes;
                        this.activeProspect.next_followup_date = this.prospectForm.next_followup_date;
                        this.activeProspect.pax_quad = this.prospectForm.pax_quad;
                        this.activeProspect.pax_triple = this.prospectForm.pax_triple;
                        this.activeProspect.pax_double = this.prospectForm.pax_double;
                        this.activeProspect.pax_infant = this.prospectForm.pax_infant;
                    }

                    if (this.activeChat) {
                        this.activeChat.prospect_name = prospectName;
                        this.activeChat.phone = this.prospectForm.phone;
                        this.activeChat.prospect_status = this.prospectForm.status;
                        this.activeChat.package_id = this.prospectForm.package_id;
                        this.activeChat.notes = this.prospectForm.notes;
                    }

                    this.loadChats(true);
                    setTimeout(() => {
                        this.prospectSavedSuccess = false;
                    }, 2500);
                } else {
                    alert(data.error || 'Gagal menyimpan detail prospek.');
                }
            } catch (e) {
                alert('Terjadi kesalahan jaringan saat menyimpan.');
            } finally {
                this.isSavingProspect = false;
            }
        },

        async registerProspectFromChat() {
            if (!this.activeChat || this.isRegisteringProspect) return;
            this.isRegisteringProspect = true;

            const candidateName = (this.newProspectName || '').trim() || this.getChatTitle(this.activeChat) || 'Calon Jamaah';
            let candidatePhone = (this.activeChat.phone || '').replace(/[^0-9]/g, '');
            if (candidatePhone.startsWith('0')) candidatePhone = '62' + candidatePhone.substring(1);

            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('brand_id', this.brandId);
            formData.append('name', candidateName);
            formData.append('phone', candidatePhone);
            formData.append('package_id', this.newProspectPackageId || '');
            formData.append('status', 'new');
            formData.append('current_stage', 'greeting');
            formData.append('remote_jid', this.activeChat.remote_jid || '');

            try {
                const res = await fetch('api/prospects.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success && data.prospect) {
                    this.activeProspect = data.prospect;
                    this.syncProspectForm(data.prospect);
                    if (this.activeChat) {
                        this.activeChat.prospect_id = data.prospect.id;
                        this.activeChat.prospect_name = data.prospect.name;
                        this.activeChat.prospect_status = data.prospect.status;
                        this.activeChat.package_id = data.prospect.package_id;
                    }
                    this.loadChats(true);
                } else {
                    alert(data.error || 'Gagal mendaftarkan prospek.');
                }
            } catch (e) {
                alert('Terjadi kesalahan jaringan.');
            } finally {
                this.isRegisteringProspect = false;
            }
        },

        insertPackageSummaryToChat() {
            const pkg = this.selectedProspectPackage;
            if (!pkg) return;

            let cleanTitle = (pkg.name || '').trim();
            if (!cleanTitle.match(/^paket\s+/i)) {
                if (!cleanTitle.match(/^umroh\s+/i)) {
                    cleanTitle = 'Paket Umroh ' + cleanTitle;
                } else {
                    cleanTitle = 'Paket ' + cleanTitle;
                }
            }

            let msg = `*${cleanTitle.toUpperCase()}*\n`;
            msg += `Travel: ${this.brandName}\n\n`;
            msg += `📅 Keberangkatan: ${pkg.departure_date || pkg.departure_info || '-'} (${pkg.duration || '9 Hari'})\n`;
            msg += `✈️ Maskapai: ${pkg.airline || '-'}\n`;
            if (pkg.hotel_makkah) msg += `🏨 Hotel Makkah: ${pkg.hotel_makkah}\n`;
            if (pkg.hotel_madinah) msg += `🏨 Hotel Madinah: ${pkg.hotel_madinah}\n`;

            msg += `\n💰 *HARGA PAKET:*\n`;
            msg += `• Quad (Kamar Ber-4): ${pkg.price_quad || pkg.price}\n`;
            if (pkg.price_triple) msg += `• Triple (Kamar Ber-3): ${pkg.price_triple}\n`;
            if (pkg.price_double) msg += `• Double (Kamar Ber-2): ${pkg.price_double}\n`;
            msg += `• Minimal DP: ${pkg.dp || 'Rp 5.000.000'}\n`;

            if (pkg.facilities_included) {
                const lines = pkg.facilities_included.split('\n').map(l => l.trim()).filter(l => l.length > 0);
                if (lines.length > 0) {
                    msg += `\n✨ *FASILITAS INCLUDE:*\n`;
                    lines.forEach(l => { msg += `• ${l}\n`; });
                }
            }

            if (pkg.quota_remaining !== null && pkg.quota_remaining !== undefined && pkg.quota_remaining !== '') {
                msg += `\nSisa Kuota: ${pkg.quota_remaining} Seat\n`;
            }
            msg += `\nInfo pendaftaran & konsultasi silakan balas pesan ini ya Kak 🙏`;

            this.messageInput = msg;
            if (this.mobileTab !== 'thread') {
                this.mobileTab = 'thread';
            }
            this.$nextTick(() => {
                const el = document.getElementById('chatMessageInput');
                if (el) {
                    el.style.height = 'auto';
                    el.style.height = Math.min(el.scrollHeight, 140) + 'px';
                    el.focus();
                }
            });
        },

        insertItineraryToChat() {
            const pkg = this.selectedProspectPackage;
            if (!pkg) return;

            let cleanTitle = (pkg.name || '').trim();
            if (!cleanTitle.match(/^paket\s+/i)) {
                if (!cleanTitle.match(/^umroh\s+/i)) {
                    cleanTitle = 'Paket Umroh ' + cleanTitle;
                } else {
                    cleanTitle = 'Paket ' + cleanTitle;
                }
            }

            let msg = `*${cleanTitle.toUpperCase()}*\n`;
            msg += `*Rundown Agenda Perjalanan*\n`;
            msg += `Travel: ${this.brandName}\n\n`;
            msg += `📅 Keberangkatan: ${pkg.departure_date || pkg.departure_info || '-'} (${pkg.duration || '9 Hari'})\n`;
            msg += `✈️ Penerbangan: ${pkg.airline || '-'}\n\n`;
            msg += `🗓️ *AGENDA HARIAN:*\n`;

            if (pkg.itinerary) {
                const lines = pkg.itinerary.split('\n').map(l => l.trim()).filter(l => l.length > 0);
                if (lines.length > 0) {
                    lines.forEach(l => { msg += `📍 ${l}\n`; });
                } else {
                    msg += `_Rincian agenda harian sedang disiapkan oleh tim operasional._\n`;
                }
            } else {
                msg += `_Rincian agenda harian sedang disiapkan oleh tim operasional._\n`;
            }

            msg += `\n_Catatan: Jadwal dan ziarah dapat disesuaikan dengan kondisi operasional di lapangan demi kenyamanan seluruh jamaah._\n\n`;
            msg += `Ada agenda atau kegiatan yang ingin ditanyakan lebih detail, Kak? Boleh kami kirimkan brosur PDF lengkapnya juga ya Kak 🙏`;

            this.messageInput = msg;
            if (this.mobileTab !== 'thread') {
                this.mobileTab = 'thread';
            }
            this.$nextTick(() => {
                const el = document.getElementById('chatMessageInput');
                if (el) {
                    el.style.height = 'auto';
                    el.style.height = Math.min(el.scrollHeight, 140) + 'px';
                    el.focus();
                }
            });
        },

        get selectedPackage() {
            if (this.selectedPackageId) {
                const found = this.packages.find(p => p.id == this.selectedPackageId);
                if (found) return found;
            }
            if (this.prospectForm && this.prospectForm.package_id) {
                const found = this.packages.find(p => p.id == this.prospectForm.package_id);
                if (found) return found;
            }
            if (this.activeProspect && this.activeProspect.package_id) {
                const found = this.packages.find(p => p.id == this.activeProspect.package_id);
                if (found) return found;
            }
            if (this.activeChat && this.activeChat.package_id) {
                const found = this.packages.find(p => p.id == this.activeChat.package_id);
                if (found) return found;
            }
            return null;
        },

        processPlaceholders(text) {
            if (!text || typeof text !== 'string') return '';
            let pName = 'Bapak/Ibu';
            if (this.activeChat) {
                const title = this.getChatTitle(this.activeChat);
                if (title && !title.match(/^[0-9\+\-\s@\.]+$/) && !title.startsWith('08') && !title.startsWith('628') && !title.includes('@')) {
                    pName = title;
                }
            }
            const pkg = this.selectedPackage;

            const pkgName = (pkg && pkg.name) || (this.activeChat && this.activeChat.package_name) || 'Paket Umroh Reguler';
            const pkgPrice = (pkg && pkg.price) || 'Rp 28.500.000';
            const pkgDp = (pkg && pkg.dp) || 'Rp 5.000.000';
            const pkgAirline = (pkg && pkg.airline) || 'Saudia / Garuda Direct';
            const pkgHotel = (pkg && (pkg.hotel_makkah || pkg.hotel_madinah))
                ? `${pkg.hotel_makkah || ''}${pkg.hotel_makkah && pkg.hotel_madinah ? ' & ' : ''}${pkg.hotel_madinah || ''}`
                : 'Hotel Bintang 4 Dekat Masjid';
            const pkgDeparture = (pkg && pkg.departure_info) || 'Jadwal Terdekat';
            const pkgDuration = (pkg && pkg.duration) || '9 Hari';
            const pkgHighlights = (pkg && pkg.highlights) || 'Free Kereta Cepat Haramain & Perlengkapan Lengkap';

            return text
                .replaceAll('{{nama}}', pName)
                .replaceAll('{{nama_jamaah}}', pName)
                .replaceAll('{{cs_name}}', this.csName)
                .replaceAll('{{agent_name}}', this.csName)
                .replaceAll('{{travel}}', this.brandName)
                .replaceAll('{{paket}}', pkgName)
                .replaceAll('{{ppiu}}', this.brandPpiu || 'Izin Resmi Kemenag')
                .replaceAll('{{rekening}}', this.brandBank || 'Bank Rekening Resmi Perusahaan')
                .replaceAll('{{nama_rekening}}', this.brandHolder || this.brandName)
                .replaceAll('{{dp}}', pkgDp)
                .replaceAll('{{harga}}', pkgPrice)
                .replaceAll('{{hotel}}', pkgHotel)
                .replaceAll('{{jarak_hotel}}', 'Dekat Pelataran Masjid')
                .replaceAll('{{maskapai}}', pkgAirline)
                .replaceAll('{{bulan}}', pkgDeparture)
                .replaceAll('{{tanggal}}', pkgDeparture)
                .replaceAll('{{durasi}}', pkgDuration)
                .replaceAll('{{kota}}', 'Jakarta')
                .replaceAll('{{seat}}', '2-3 seat')
                .replaceAll('{{jumlah_jamaah}}', '1-2 orang')
                .replaceAll('{{deadline}}', 'minggu ini')
                .replaceAll('{{followup_date}}', 'besok lusa')
                .replaceAll('{{promo}}', 'Promo Spesial Keberangkatan')
                .replaceAll('{{fasilitas_utama}}', pkgHighlights);
        },

        insertQuickTemplate(type) {
            let tpl = '';
            let name = 'Bapak/Ibu';
            if (this.activeChat) {
                const title = this.getChatTitle(this.activeChat);
                if (title && !title.match(/^[0-9\+\-\s@\.]+$/) && !title.startsWith('08') && !title.startsWith('628') && !title.includes('@')) {
                    name = title;
                }
            }
            if (type === 'greeting') {
                tpl = `Assalamu'alaikum Warahmatullahi Wabarakatuh, ${name} 😊\n\nSaya ${this.csName} dari tim layanan resmi ${this.brandName}.\n\nSenang sekali bisa membantu rencana ibadah umroh ${name} sekeluarga.\n\nAda program umroh atau rencana bulan keberangkatan yang sedang diincar?`;
            } else if (type === 'package') {
                const pkg = this.selectedPackage;
                const pName = (pkg && pkg.name) || (this.activeChat && this.activeChat.package_name) || 'Paket Umroh Reguler';
                const pPrice = (pkg && pkg.price) || 'Rp 28.500.000';
                const pDp = (pkg && pkg.dp) || 'Rp 5.000.000';
                const pHotel = (pkg && (pkg.hotel_makkah || pkg.hotel_madinah)) ? `${pkg.hotel_makkah} & ${pkg.hotel_madinah}` : 'Hotel Dekat Pelataran Masjid (Bintang 4/5)';
                const pAir = (pkg && pkg.airline) || 'Saudia / Garuda Direct';
                const pFeat = (pkg && pkg.highlights) || 'Free Kereta Cepat Haramain & Perlengkapan Lengkap';

                tpl = `Bismillah, berikut rincian paket umroh kami di ${this.brandName}:\n\n✨ Paket: ${pName}\n💰 Harga: ${pPrice} (DP: ${pDp})\n🏨 Hotel: ${pHotel}\n✈️ Maskapai: ${pAir}\n🚅 Fasilitas: ${pFeat}\n\nApakah ${name} berencana berangkat sendiri atau bersama keluarga?`;
            } else if (type === 'bank') {
                tpl = `Bismillah, untuk pengamanan seat keberangkatan di ${this.brandName}, pembayaran DP resmi disalurkan melalui rekening perusahaan resmi:\n\n🏦 Rekening: ${this.brandBank}\n👤 Atas Nama: ${this.brandHolder || this.brandName}\n\nSetelah transfer, mohon kirimkan bukti transfernya ya Kak, agar segera kami terbitkan invoice dan manifest resmi jamaah.`;
            } else if (type === 'closing') {
                tpl = `Bismillah ${name}, kuota seat untuk jadwal keberangkatan ini tersisa sedikit sekali.\n\nJika berkenan, kursinya bisa kami bantu amankan sekarang dengan DP, agar kepastian kamar hotel dekat pelataran tidak terisi jamaah lain.\n\nBisa kami bantu proses pendaftarannya hari ini ya ${name}?`;
            }
            this.messageInput = tpl;
            this.$nextTick(() => {
                const el = document.getElementById('chatMessageInput');
                if (el) {
                    el.style.height = 'auto';
                    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
                    el.focus();
                    el.scrollTop = el.scrollHeight;
                }
            });
        },

        openEditContactModal(chat) {
            if (!chat) return;
            let currentName = (chat.prospect_name || '').trim();
            if (currentName.startsWith('Calon Jamaah') || currentName.startsWith('Jamaah ') || currentName.match(/^[0-9\+\-\s@\.]+$/) || currentName.includes('@')) {
                currentName = (chat.sender_name && !chat.sender_name.startsWith('Calon Jamaah') && !chat.sender_name.match(/^[0-9\+\-\s@\.]+$/))
                    ? chat.sender_name
                    : '';
            }
            this.editContactForm = {
                id: chat.prospect_id || 0,
                name: currentName,
                phone: this.hasValidPhone(chat.phone) ? chat.phone : '',
                package_id: chat.package_id || '',
                notes: chat.notes || '',
                remote_jid: chat.remote_jid || ''
            };
            this.openEditModal = true;
        },

        async saveContactEdit() {
            this.isSavingContact = true;
            try {
                const payload = {
                    id: this.editContactForm.id,
                    brand_id: this.brandId,
                    name: this.editContactForm.name,
                    phone: this.editContactForm.phone,
                    package_id: this.editContactForm.package_id || null,
                    notes: this.editContactForm.notes,
                    remote_jid: this.editContactForm.remote_jid
                };

                const res = await fetch('api/prospects.php?action=save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    if (this.activeChat) {
                        this.activeChat.prospect_id = data.id;
                        this.activeChat.prospect_name = this.editContactForm.name;
                        if (this.editContactForm.phone) {
                            this.activeChat.phone = this.editContactForm.phone;
                        }
                        this.activeChat.package_id = this.editContactForm.package_id;
                        this.activeChat.notes = this.editContactForm.notes;
                    }
                    if (data.prospect) {
                        this.activeProspect = data.prospect;
                        this.syncProspectForm(data.prospect);
                    }
                    this.openEditModal = false;
                    await this.loadChats(true);
                } else {
                    alert(data.error || 'Gagal menyimpan kontak');
                }
            } catch (e) {
                alert('Terjadi kesalahan koneksi');
            } finally {
                this.isSavingContact = false;
            }
        },

        getChatTitle(c) {
            if (!c) return '';
            // 1. Check prospect_name if valid and not placeholder
            let name = (c.prospect_name || '').trim();
            if (name.startsWith('Calon Jamaah') || name.startsWith('Jamaah ') || name.toLowerCase() === 'calon jamaah baru') {
                name = '';
            }
            // If name is a real text name (not just digits/phone, not JID)
            if (name && !name.match(/^[0-9\+\-\s@\.]+$/) && !name.includes('@')) {
                return name;
            }

            // 2. Check sender_name (WhatsApp profile name / pushName)
            let sender = (c.sender_name || '').trim();
            if (sender.startsWith('Calon Jamaah') || sender.startsWith('Jamaah ') || sender.toLowerCase() === 'calon jamaah baru') {
                sender = '';
            }
            if (sender && !sender.match(/^[0-9\+\-\s@\.]+$/) && !sender.includes('@')) {
                return sender;
            }

            // 3. Fallback: Phone number formatted
            let phone = c.phone || (c.remote_jid ? c.remote_jid.split('@')[0] : '');
            if (phone && this.hasValidPhone(phone)) {
                return this.formatIndoPhone(phone);
            }

            // 4. Raw phone or remote_jid or name
            return name || sender || phone || c.remote_jid || 'Kontak';
        },

        getAvatarLetter(c) {
            const title = this.getChatTitle(c);
            if (!title) return 'W';
            const clean = title.replace(/^[\+\-\s0-9]+/, '').trim();
            return clean ? clean.charAt(0).toUpperCase() : (title.charAt(0).toUpperCase() || 'W');
        },

        hasValidPhone(phone) {
            if (!phone) return false;
            const p = phone.toString().replace(/[^0-9]/g, '');
            return (p.startsWith('08') || p.startsWith('628')) && p.length >= 10 && p.length <= 14;
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

        getStatusLabel(status) {
            const found = this.statusOptions.find(s => s.val === status);
            return found ? found.label : (status ? status.toUpperCase() : 'Prospek Baru');
        },

        formatStatusLabel(status) {
            return this.getStatusLabel(status);
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

        parseDate(ts) {
            if (!ts) return null;
            if (typeof ts === 'number' || !isNaN(Number(ts))) {
                const num = Number(ts);
                return new Date(num < 10000000000 ? num * 1000 : num);
            }
            return new Date(ts);
        },

        formatTime(ts) {
            if (!ts) return '';
            const d = this.parseDate(ts);
            if (!d || isNaN(d.getTime())) return '';
            const dateStr = d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
            const timeStr = d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':');
            return `${dateStr}, ${timeStr}`;
        },

        formatBubbleTime(ts) {
            if (!ts) return '';
            const d = this.parseDate(ts);
            if (!d || isNaN(d.getTime())) return '';
            return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':');
        },

        formatChatListTime(ts) {
            if (!ts) return '';
            const d = this.parseDate(ts);
            if (!d || isNaN(d.getTime())) return '';
            const now = new Date();
            const isToday = d.toDateString() === now.toDateString();
            
            const yesterday = new Date();
            yesterday.setDate(yesterday.getDate() - 1);
            const isYesterday = d.toDateString() === yesterday.toDateString();
            
            const timeStr = d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':');
            
            if (isToday) {
                return `Hari ini, ${timeStr}`;
            } else if (isYesterday) {
                return `Kemarin, ${timeStr}`;
            } else {
                const isCurrentYear = d.getFullYear() === now.getFullYear();
                const dateStr = d.toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'short',
                    year: isCurrentYear ? undefined : 'numeric'
                });
                return `${dateStr}, ${timeStr}`;
            }
        },

        shouldShowDateDivider(messages, idx) {
            if (!messages || idx >= messages.length) return false;
            if (idx === 0) return true;
            const prev = messages[idx - 1];
            const curr = messages[idx];
            if (!prev || !curr || !prev.timestamp || !curr.timestamp) return false;
            const d1 = this.parseDate(prev.timestamp);
            const d2 = this.parseDate(curr.timestamp);
            if (!d1 || !d2 || isNaN(d1.getTime()) || isNaN(d2.getTime())) return false;
            return d1.toDateString() !== d2.toDateString();
        },

        formatDateDivider(ts) {
            if (!ts) return '';
            const d = this.parseDate(ts);
            if (!d || isNaN(d.getTime())) return '';
            const now = new Date();
            if (d.toDateString() === now.toDateString()) {
                return 'Hari ini - ' + d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
            }
            const yesterday = new Date();
            yesterday.setDate(yesterday.getDate() - 1);
            if (d.toDateString() === yesterday.toDateString()) {
                return 'Kemarin - ' + d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
            }
            return d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        },

        isChatUnread(c) {
            if (!c) return false;
            if (this.activeChat && this.activeChat.remote_jid === c.remote_jid) return false;
            if (c.last_is_from_me == 1) return false;
            if (c.is_unread == 1) return true;
            if (c.unread_count && parseInt(c.unread_count, 10) > 0) return true;
            if (c.last_is_from_me == 0 && (!c.last_status || c.last_status !== 'read')) return true;
            return false;
        },

        getUnreadCountText(c) {
            if (!c) return '1';
            const count = parseInt(c.unread_count, 10);
            if (!isNaN(count) && count > 0) {
                return count > 99 ? '99+' : String(count);
            }
            return '1';
        },

        isMessageRead(m) {
            if (!m) return false;
            const s = String(m.status || '').toLowerCase();
            return s === 'read' || s === 'played';
        },

        isMessageSent(m) {
            if (!m) return false;
            const s = String(m.status || '').toLowerCase();
            return s === 'sent' || s === 'server_ack';
        },

        get currentSopScripts() {
            const raw = this.rawScripts[this.sopStage] || {};
            let list = [];
            if (Array.isArray(raw)) {
                list = raw;
            } else if (raw && Array.isArray(raw.scripts)) {
                list = raw.scripts;
            } else if (typeof raw === 'object') {
                for (let k in raw) {
                    if (k === 'scripts' && Array.isArray(raw[k])) {
                        list = raw[k];
                        break;
                    }
                }
            }
            if (this.copilotSearch && this.copilotSearch.trim()) {
                const q = this.copilotSearch.toLowerCase().trim();
                return list.filter(s => 
                    (s.title && s.title.toLowerCase().includes(q)) ||
                    (s.script && s.script.toLowerCase().includes(q)) ||
                    (s.use_when && s.use_when.toLowerCase().includes(q)) ||
                    (s.category && s.category.toLowerCase().includes(q))
                );
            }
            return list;
        },

        get objectionScripts() {
            const raw = this.rawScripts['objection'] || {};
            const list = raw.scripts || (Array.isArray(raw) ? raw : []);
            let items = [];
            for (let item of list) {
                if (item.tgjp) {
                    const phases = [
                        { key: 'terima', label: '1. Terima (Validasi)' },
                        { key: 'gali', label: '2. Gali Akar Masalah' },
                        { key: 'jawab', label: '3. Solusi & Edukasi' },
                        { key: 'pastikan', label: '4. Pastikan Closing' }
                    ];
                    for (let p of phases) {
                        const stepScripts = item.tgjp[p.key] || [];
                        for (let s of stepScripts) {
                            items.push({
                                title: `${item.title} - ${p.label}`,
                                category: item.category || 'TGJP',
                                use_when: s.use_when || item.use_when || '',
                                script: s.script || s.text || ''
                            });
                        }
                    }
                } else {
                    items.push({
                        title: item.title || item.objection || 'Keberatan',
                        category: item.category || 'TGJP',
                        use_when: item.use_when || '',
                        script: item.script || item.text || item.response || ''
                    });
                }
            }
            if (this.copilotSearch && this.copilotSearch.trim()) {
                const q = this.copilotSearch.toLowerCase().trim();
                return items.filter(s => 
                    (s.title && s.title.toLowerCase().includes(q)) ||
                    (s.script && s.script.toLowerCase().includes(q)) ||
                    (s.use_when && s.use_when.toLowerCase().includes(q)) ||
                    (s.category && s.category.toLowerCase().includes(q))
                );
            }
            return items;
        }
    };
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

