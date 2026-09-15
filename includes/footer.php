        </main>

        <!-- Global Minimal Footer (Hidden on mobile bottom to avoid bottom nav clash) -->
        <?php if (empty($isFullHeight)): ?>
        <footer class="border-t border-zinc-200/80 bg-white py-3.5 px-4 sm:px-6 md:px-8 mt-auto flex items-center justify-between text-[11px] text-zinc-400 mb-14 md:mb-0 shrink-0">
            <div>
                CS Umroh Copilot &copy; <?= date('Y') ?> &bull; AZHAN GRUP
            </div>
            <div class="hidden sm:block text-zinc-400">
                Sistem Konversi 5 Brand Travel Umroh
            </div>
        </footer>
        <?php endif; ?>

    </div> <!-- Close flex-1 main wrapper -->

    <!-- ============================================================== -->
    <!-- MOBILE BOTTOM NAVIGATION BAR (md:hidden)                       -->
    <!-- ============================================================== -->
    <?php if (empty($isFullHeight)): ?>
    <nav class="md:hidden fixed bottom-0 left-0 right-0 h-14 bg-white/95 backdrop-blur-md border-t border-zinc-200/90 z-40 flex items-center justify-around px-2 select-none shadow-lg">
        <!-- Copilot Tab -->
        <a href="<?= $baseUrl ?>/index.php" 
           class="flex flex-col items-center justify-center flex-1 py-1 transition <?= (str_contains($currentUri, 'index.php') && !str_contains($currentUri, 'admin') && !str_contains($currentUri, 'lms') && !str_contains($currentUri, 'prospects.php')) || ($currentUri === $baseUrl || $currentUri === $baseUrl . '/' || $currentUri === '/') ? 'text-black font-bold' : 'text-zinc-400 hover:text-zinc-700' ?>">
            <svg class="w-5 h-5 mb-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <span class="text-[10px] leading-tight">Copilot</span>
        </a>

        <!-- Prospects Tab -->
        <a href="<?= $baseUrl ?>/prospects.php" 
           class="flex flex-col items-center justify-center flex-1 py-1 transition <?= str_contains($currentUri, 'prospects.php') || str_contains($currentUri, 'prospect_detail.php') ? 'text-black font-bold' : 'text-zinc-400 hover:text-zinc-700' ?>">
            <svg class="w-5 h-5 mb-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span class="text-[10px] leading-tight">Prospek</span>
        </a>

        <!-- LMS Tab -->
        <a href="<?= $baseUrl ?>/lms/index.php" 
           class="flex flex-col items-center justify-center flex-1 py-1 transition <?= str_contains($currentUri, '/lms') ? 'text-black font-bold' : 'text-zinc-400 hover:text-zinc-700' ?>">
            <svg class="w-5 h-5 mb-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
            </svg>
            <span class="text-[10px] leading-tight">LMS</span>
        </a>

        <!-- Menu Drawer Opener -->
        <button type="button" @click="mobileMenuOpen = true"
                class="flex flex-col items-center justify-center flex-1 py-1 text-zinc-400 hover:text-zinc-700 transition">
            <svg class="w-5 h-5 mb-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
            <span class="text-[10px] leading-tight">Menu</span>
        </button>
    </nav>
    <?php endif; ?>

    <!-- Global Toast Notification (Strict Monochrome) -->
    <div x-data="{
            show: false,
            message: '',
            timeout: null,
            trigger(msg) {
                this.message = msg;
                this.show = true;
                clearTimeout(this.timeout);
                this.timeout = setTimeout(() => { this.show = false; }, 2500);
            }
         }"
         @toast.window="trigger($event.detail)"
         class="fixed bottom-16 md:bottom-5 right-4 sm:right-5 z-50 pointer-events-none">
        <div x-show="show"
             x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-2 scale-95"
             class="pointer-events-auto bg-black text-white px-4 py-2.5 rounded-xl shadow-xl text-xs font-medium flex items-center gap-2.5 border border-zinc-800">
            <svg class="w-4 h-4 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
            </svg>
            <span x-text="message"></span>
        </div>
    </div>

    <!-- Global Helper Scripts -->
    <script>
        window.showToast = function(msg) {
            window.dispatchEvent(new CustomEvent('toast', { detail: msg }));
        };

        window.copyToClipboard = function(text, successMsg = 'Tersalin ke Clipboard!') {
            if (!text) return;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    window.showToast(successMsg);
                }).catch(() => {
                    fallbackCopy(text, successMsg);
                });
            } else {
                fallbackCopy(text, successMsg);
            }
        };

        function fallbackCopy(text, successMsg) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            textArea.style.top = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                window.showToast(successMsg);
            } catch (err) {
                window.showToast('Gagal menyalin teks');
            }
            textArea.remove();
        }
    </script>
</body>
</html>
