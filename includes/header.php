<?php
/**
 * Global Header Component (Modern SaaS Sidebar + Topbar Layout)
 * Strict Monochrome (Black & White), Zero Emojis, Pure SVG Icons
 * 100% Mobile & Split-Screen Responsive
 */

require_once __DIR__ . '/auth_check.php';

$baseUrl = get_base_url();
$user = require_login($baseUrl);
$isSuperAdmin = ($user['role'] === 'superadmin');
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$pageTitle = $pageTitle ?? 'CS Umroh Copilot';
$isFullHeight = !empty($isFullHeight) || (isset($_SERVER['SCRIPT_NAME']) && str_ends_with($_SERVER['SCRIPT_NAME'], 'chat.php'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <!-- Google Fonts: Google Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.cdnfonts.com/css/google-sans">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Google Sans"', '"Product Sans"', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <!-- Alpine.js CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @font-face {
            font-family: 'Google Sans';
            src: local('Google Sans Regular'), local('Google Sans'), local('Product Sans'), url('https://fonts.cdnfonts.com/s/14955/ProductSans-Regular.woff') format('woff');
            font-weight: 400;
            font-style: normal;
        }
        @font-face {
            font-family: 'Google Sans';
            src: local('Google Sans Bold'), local('Google Sans-Bold'), local('Product Sans Bold'), url('https://fonts.cdnfonts.com/s/14955/ProductSans-Bold.woff') format('woff');
            font-weight: 700;
            font-style: normal;
        }
        [x-cloak] { display: none !important; }
        html, body, input, button, select, textarea, h1, h2, h3, h4, h5, h6, p, span, a, td, th {
            font-family: 'Google Sans', 'Product Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-[#F8F9FA] text-zinc-900 <?= $isFullHeight ? 'h-screen overflow-hidden' : 'min-h-screen' ?> flex antialiased selection:bg-black selection:text-white text-sm" 
      x-data="{ 
          mobileMenuOpen: false, 
          sidebarCollapsed: localStorage.getItem('csumroh_sidebar_collapsed') === 'true',
          toggleSidebar() {
              this.sidebarCollapsed = !this.sidebarCollapsed;
              localStorage.setItem('csumroh_sidebar_collapsed', this.sidebarCollapsed);
          }
      }">

    <!-- ============================================================== -->
    <!-- LEFT SIDEBAR (Desktop: md and up)                              -->
    <!-- ============================================================== -->
    <aside class="hidden md:flex flex-col justify-between shrink-0 min-h-screen sticky top-0 h-screen z-30 select-none bg-white border-r border-zinc-200/80 transition-[width] duration-200 ease-in-out"
           :class="sidebarCollapsed ? 'w-[72px]' : 'w-64'">
        
        <div class="flex-1 overflow-y-auto" :class="sidebarCollapsed ? 'p-2.5 overflow-x-hidden no-scrollbar' : 'p-5'">
            <!-- App Logo & Brand Title -->
            <div class="flex items-center mb-7" :class="sidebarCollapsed ? 'justify-center' : 'gap-3'">
                <a href="<?= $baseUrl ?>/index.php" class="flex items-center gap-3 group" :title="sidebarCollapsed ? 'CS Umroh - AZHAN GRUP' : ''">
                    <div class="w-10 h-10 aspect-square bg-black text-white rounded-xl flex items-center justify-center font-extrabold text-sm shadow-sm group-hover:scale-95 transition-transform shrink-0">
                        <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                        </svg>
                    </div>
                    <div x-show="!sidebarCollapsed" x-cloak class="overflow-hidden whitespace-nowrap">
                        <span class="font-extrabold text-base tracking-tight text-black block leading-none">
                            CS Umroh
                        </span>
                        <span class="text-[10px] font-semibold text-zinc-500 uppercase tracking-wider block mt-1">
                            AZHAN GRUP
                        </span>
                    </div>
                </a>
            </div>

            <!-- Navigation Links -->
            <div class="space-y-6">
                <!-- Section 1: CS Workspace -->
                <div>
                    <div x-show="!sidebarCollapsed" x-cloak class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider px-3 mb-2 whitespace-nowrap">
                        Menu Utama
                    </div>
                    <div x-show="sidebarCollapsed" x-cloak class="h-px bg-zinc-200/80 w-8 mx-auto my-3"></div>
                    <nav :class="sidebarCollapsed ? 'space-y-2' : 'space-y-1'">
                        <!-- Live WhatsApp Chat -->
                        <a href="<?= $baseUrl ?>/chat.php" 
                           title="Live Chat WA"
                           :class="sidebarCollapsed ? 'w-10 h-10 aspect-square p-0 justify-center mx-auto' : 'w-full justify-between px-3.5 py-2.5'"
                           class="flex items-center rounded-xl text-xs font-semibold transition <?= str_contains($currentUri, 'chat.php') ? 'bg-black text-white shadow-sm' : 'text-zinc-600 hover:text-black hover:bg-zinc-100/70' ?>">
                            <div class="flex items-center" :class="sidebarCollapsed ? 'justify-center' : 'gap-3'">
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                </svg>
                                <span x-show="!sidebarCollapsed" x-cloak class="whitespace-nowrap">Live Chat WA</span>
                            </div>
                            <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0" :class="sidebarCollapsed ? 'hidden' : ''"></span>
                        </a>

                        <!-- Copilot Chat -->
                        <a href="<?= $baseUrl ?>/index.php" 
                           title="Bank Skrip & SOP"
                           :class="sidebarCollapsed ? 'w-10 h-10 aspect-square p-0 justify-center mx-auto' : 'w-full gap-3 px-3.5 py-2.5'"
                           class="flex items-center rounded-xl text-xs font-semibold transition <?= (str_contains($currentUri, 'index.php') && !str_contains($currentUri, 'admin') && !str_contains($currentUri, 'lms') && !str_contains($currentUri, 'prospects.php')) || ($currentUri === $baseUrl || $currentUri === $baseUrl . '/' || $currentUri === '/') ? 'bg-black text-white shadow-sm' : 'text-zinc-600 hover:text-black hover:bg-zinc-100/70' ?>">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                            </svg>
                            <span x-show="!sidebarCollapsed" x-cloak class="whitespace-nowrap">Bank Skrip & SOP</span>
                        </a>

                        <!-- Data Prospek -->
                        <a href="<?= $baseUrl ?>/prospects.php" 
                           title="Data Prospek"
                           :class="sidebarCollapsed ? 'w-10 h-10 aspect-square p-0 justify-center mx-auto' : 'w-full gap-3 px-3.5 py-2.5'"
                           class="flex items-center rounded-xl text-xs font-semibold transition <?= str_contains($currentUri, 'prospects.php') || str_contains($currentUri, 'prospect_detail.php') ? 'bg-black text-white shadow-sm' : 'text-zinc-600 hover:text-black hover:bg-zinc-100/70' ?>">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            <span x-show="!sidebarCollapsed" x-cloak class="whitespace-nowrap">Data Prospek</span>
                        </a>

                        <!-- Koneksi WhatsApp -->
                        <a href="<?= $baseUrl ?>/whatsapp_connect.php" 
                           title="Koneksi WA"
                           :class="sidebarCollapsed ? 'w-10 h-10 aspect-square p-0 justify-center mx-auto' : 'w-full gap-3 px-3.5 py-2.5'"
                           class="flex items-center rounded-xl text-xs font-semibold transition <?= str_contains($currentUri, 'whatsapp_connect.php') ? 'bg-black text-white shadow-sm' : 'text-zinc-600 hover:text-black hover:bg-zinc-100/70' ?>">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                                <path d="M12 18h.01"/>
                            </svg>
                            <span x-show="!sidebarCollapsed" x-cloak class="whitespace-nowrap">Koneksi WA</span>
                        </a>

                        <!-- LMS Belajar Mandiri -->
                        <a href="<?= $baseUrl ?>/lms/index.php" 
                           title="LMS Belajar Mandiri"
                           :class="sidebarCollapsed ? 'w-10 h-10 aspect-square p-0 justify-center mx-auto' : 'w-full gap-3 px-3.5 py-2.5'"
                           class="flex items-center rounded-xl text-xs font-semibold transition <?= str_contains($currentUri, '/lms') ? 'bg-black text-white shadow-sm' : 'text-zinc-600 hover:text-black hover:bg-zinc-100/70' ?>">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            </svg>
                            <span x-show="!sidebarCollapsed" x-cloak class="whitespace-nowrap">LMS Belajar Mandiri</span>
                        </a>
                    </nav>
                </div>

                <!-- Section 2: Super Admin Menus -->
                <?php if ($isSuperAdmin): ?>
                    <div>
                        <div x-show="!sidebarCollapsed" x-cloak class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider px-3 mb-2 whitespace-nowrap">
                            Super Admin
                        </div>
                        <div x-show="sidebarCollapsed" x-cloak class="h-px bg-zinc-200/80 w-8 mx-auto my-3"></div>
                        <nav :class="sidebarCollapsed ? 'space-y-2' : 'space-y-1'">
                            <!-- Dashboard Overview -->
                            <a href="<?= $baseUrl ?>/admin/index.php" 
                               title="Overview Admin"
                               :class="sidebarCollapsed ? 'w-10 h-10 aspect-square p-0 justify-center mx-auto' : 'w-full gap-3 px-3.5 py-2.5'"
                               class="flex items-center rounded-xl text-xs font-semibold transition <?= str_contains($currentUri, 'admin/index.php') ? 'bg-black text-white shadow-sm' : 'text-zinc-600 hover:text-black hover:bg-zinc-100/70' ?>">
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="14" width="7" height="7"></rect>
                                    <rect x="3" y="14" width="7" height="7"></rect>
                                </svg>
                                <span x-show="!sidebarCollapsed" x-cloak class="whitespace-nowrap">Overview Admin</span>
                            </a>

                            <!-- Brand Travel -->
                            <a href="<?= $baseUrl ?>/admin/brands.php" 
                               title="Brand Travel"
                               :class="sidebarCollapsed ? 'w-10 h-10 aspect-square p-0 justify-center mx-auto' : 'w-full gap-3 px-3.5 py-2.5'"
                               class="flex items-center rounded-xl text-xs font-semibold transition <?= str_contains($currentUri, 'brands.php') ? 'bg-black text-white shadow-sm' : 'text-zinc-600 hover:text-black hover:bg-zinc-100/70' ?>">
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"></path>
                                    <path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path>
                                    <path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"></path>
                                    <path d="M10 6h4"></path>
                                    <path d="M10 10h4"></path>
                                    <path d="M10 14h4"></path>
                                    <path d="M10 18h4"></path>
                                </svg>
                                <span x-show="!sidebarCollapsed" x-cloak class="whitespace-nowrap">Brand Travel</span>
                            </a>

                            <!-- Paket Umroh -->
                            <a href="<?= $baseUrl ?>/admin/packages.php" 
                               title="Paket Umroh"
                               :class="sidebarCollapsed ? 'w-10 h-10 aspect-square p-0 justify-center mx-auto' : 'w-full gap-3 px-3.5 py-2.5'"
                               class="flex items-center rounded-xl text-xs font-semibold transition <?= str_contains($currentUri, 'packages.php') ? 'bg-black text-white shadow-sm' : 'text-zinc-600 hover:text-black hover:bg-zinc-100/70' ?>">
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m7.5 4.27 9 5.15"></path>
                                    <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path>
                                    <path d="m3.3 7 8.7 5 8.7-5"></path>
                                    <path d="M12 22V12"></path>
                                </svg>
                                <span x-show="!sidebarCollapsed" x-cloak class="whitespace-nowrap">Paket Umroh</span>
                            </a>

                            <!-- Akun CS -->
                            <a href="<?= $baseUrl ?>/admin/users.php" 
                               title="Akun CS"
                               :class="sidebarCollapsed ? 'w-10 h-10 aspect-square p-0 justify-center mx-auto' : 'w-full gap-3 px-3.5 py-2.5'"
                               class="flex items-center rounded-xl text-xs font-semibold transition <?= str_contains($currentUri, 'users.php') ? 'bg-black text-white shadow-sm' : 'text-zinc-600 hover:text-black hover:bg-zinc-100/70' ?>">
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                                <span x-show="!sidebarCollapsed" x-cloak class="whitespace-nowrap">Akun CS</span>
                            </a>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </aside>

    <!-- ============================================================== -->
    <!-- MOBILE OFF-CANVAS DRAWER (Slide-out on < md)                   -->
    <!-- ============================================================== -->
    <div x-show="mobileMenuOpen" x-cloak class="fixed inset-0 z-50 md:hidden flex" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div x-show="mobileMenuOpen"
             x-transition:enter="transition-opacity ease-linear duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="mobileMenuOpen = false"
             class="fixed inset-0 bg-black/60 backdrop-blur-xs"></div>

        <!-- Slide-out Drawer -->
        <div x-show="mobileMenuOpen"
             x-transition:enter="transition ease-in-out duration-250 transform"
             x-transition:enter-start="-translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in-out duration-200 transform"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="-translate-x-full"
             class="relative flex-1 flex flex-col max-w-xs w-full bg-white border-r border-zinc-200 shadow-2xl z-50">
            
            <!-- Drawer Header -->
            <div class="p-4 flex items-center justify-between border-b border-zinc-100">
                <a href="<?= $baseUrl ?>/index.php" class="flex items-center gap-2.5">
                    <div class="w-8 h-8 bg-black text-white rounded-xl flex items-center justify-center font-extrabold text-xs shadow-sm">
                        <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                        </svg>
                    </div>
                    <div>
                        <span class="font-extrabold text-sm tracking-tight text-black block leading-none">CS Umroh</span>
                        <span class="text-[9px] font-semibold text-zinc-500 uppercase tracking-wider block mt-1">AZHAN GRUP</span>
                    </div>
                </a>
                <button type="button" @click="mobileMenuOpen = false" class="p-2 text-zinc-400 hover:text-black rounded-lg transition" aria-label="Tutup menu">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <!-- Drawer Links -->
            <div class="flex-1 overflow-y-auto p-4 space-y-5">
                <div>
                    <div class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider px-3 mb-2">Menu Utama</div>
                    <nav class="space-y-1">
                        <a href="<?= $baseUrl ?>/chat.php" class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-semibold transition <?= str_contains($currentUri, 'chat.php') ? 'bg-black text-white shadow-sm' : 'text-zinc-600 hover:text-black hover:bg-zinc-100/70' ?>">
                            <div class="flex items-center gap-3">
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                                <span>Live Chat WA</span>
                            </div>
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        </a>
                        <a href="<?= $baseUrl ?>/index.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition <?= (str_contains($currentUri, 'index.php') && !str_contains($currentUri, 'admin') && !str_contains($currentUri, 'lms') && !str_contains($currentUri, 'prospects.php')) || ($currentUri === $baseUrl || $currentUri === $baseUrl . '/' || $currentUri === '/') ? 'bg-black text-white shadow-sm' : 'text-zinc-600 hover:text-black hover:bg-zinc-100/70' ?>">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                            <span>Bank Skrip & SOP</span>
                        </a>
                        <a href="<?= $baseUrl ?>/prospects.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition <?= str_contains($currentUri, 'prospects.php') || str_contains($currentUri, 'prospect_detail.php') ? 'bg-black text-white shadow-sm' : 'text-zinc-600 hover:text-black hover:bg-zinc-100/70' ?>">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            <span>Data Prospek</span>
                        </a>
                        <a href="<?= $baseUrl ?>/whatsapp_connect.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition <?= str_contains($currentUri, 'whatsapp_connect.php') ? 'bg-black text-white shadow-sm' : 'text-zinc-600 hover:text-black hover:bg-zinc-100/70' ?>">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                            <span>Koneksi WA</span>
                        </a>
                        <a href="<?= $baseUrl ?>/lms/index.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition <?= str_contains($currentUri, '/lms') ? 'bg-black text-white shadow-sm' : 'text-zinc-600 hover:text-black hover:bg-zinc-100/70' ?>">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                            <span>LMS Belajar Mandiri</span>
                        </a>
                    </nav>
                </div>

                <?php if ($isSuperAdmin): ?>
                    <div>
                        <div class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider px-3 mb-2">Super Admin</div>
                        <nav class="space-y-1">
                            <a href="<?= $baseUrl ?>/admin/index.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition <?= str_contains($currentUri, 'admin/index.php') ? 'bg-black text-white shadow-sm' : 'text-zinc-600 hover:text-black hover:bg-zinc-100/70' ?>">
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                                <span>Overview Admin</span>
                            </a>
                            <a href="<?= $baseUrl ?>/admin/brands.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition <?= str_contains($currentUri, 'brands.php') ? 'bg-black text-white shadow-sm' : 'text-zinc-600 hover:text-black hover:bg-zinc-100/70' ?>">
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"></path><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"></path><path d="M10 6h4"></path><path d="M10 10h4"></path><path d="M10 14h4"></path><path d="M10 18h4"></path></svg>
                                <span>Brand Travel</span>
                            </a>
                            <a href="<?= $baseUrl ?>/admin/packages.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition <?= str_contains($currentUri, 'packages.php') ? 'bg-black text-white shadow-sm' : 'text-zinc-600 hover:text-black hover:bg-zinc-100/70' ?>">
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"></path><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path><path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path></svg>
                                <span>Paket Umroh</span>
                            </a>
                            <a href="<?= $baseUrl ?>/admin/users.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition <?= str_contains($currentUri, 'users.php') ? 'bg-black text-white shadow-sm' : 'text-zinc-600 hover:text-black hover:bg-zinc-100/70' ?>">
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                <span>Akun CS</span>
                            </a>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- ============================================================== -->
    <!-- MAIN APPLICATION WRAPPER                                       -->
    <!-- ============================================================== -->
    <div class="flex-1 flex flex-col min-w-0 <?= $isFullHeight ? 'h-screen overflow-hidden' : 'min-h-screen' ?>">
        
        <!-- Topbar (Responsive SaaS Style) -->
        <header class="h-16 bg-white border-b border-zinc-200/80 flex items-center justify-between px-3.5 sm:px-6 md:px-8 sticky top-0 z-20 shrink-0">
            
            <!-- Left: Unified Navigation Toggle & Search Input -->
            <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                <!-- Single Navigation Toggle Button (Desktop: Collapse/Expand Sidebar, Mobile: Open Drawer) -->
                <button type="button" 
                        @click="window.innerWidth < 768 ? mobileMenuOpen = true : toggleSidebar()" 
                        class="p-2 text-zinc-600 hover:text-black hover:bg-zinc-100 rounded-xl transition shrink-0 flex items-center justify-center" 
                        :title="sidebarCollapsed ? 'Buka Sidebar' : 'Tutup Sidebar'" 
                        aria-label="Toggle navigasi">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>

                <div class="relative w-36 sm:w-64 md:w-80">
                    <svg class="w-3.5 h-3.5 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <input type="text" placeholder="Cari..." 
                           class="w-full pl-9 pr-7 sm:pr-10 py-1.5 sm:py-2 bg-zinc-50 border border-zinc-200/80 rounded-xl text-xs text-zinc-700 placeholder-zinc-400 focus:outline-none focus:border-black focus:bg-white transition">
                    <span class="hidden sm:inline-block absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] font-mono text-zinc-400 bg-white border border-zinc-200 px-1.5 py-0.5 rounded shadow-2xs">⌘K</span>
                </div>
            </div>

            <!-- Right: User Profile & Actions -->
            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                
                <!-- Notification Icon -->
                <button type="button" title="Notifikasi" class="p-2 text-zinc-400 hover:text-black hover:bg-zinc-100 rounded-xl transition relative">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <span class="absolute top-1.5 right-1.5 w-1.5 h-1.5 bg-black rounded-full"></span>
                </button>

                <!-- Role Tag -->
                <?php if ($isSuperAdmin): ?>
                    <span class="hidden sm:inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-semibold bg-black text-white">
                        Super Admin
                    </span>
                <?php else: ?>
                    <span class="hidden sm:inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-semibold border border-zinc-300 bg-zinc-50 text-zinc-800">
                        <?= htmlspecialchars($user['brand_name'] ?? 'CS') ?>
                    </span>
                <?php endif; ?>

                <!-- User Profile & Logout -->
                <div class="flex items-center gap-2 pl-2 sm:pl-3 border-l border-zinc-200">
                    <div class="w-8 h-8 rounded-full bg-black text-white flex items-center justify-center font-bold text-xs shadow-xs shrink-0">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </div>
                    <div class="hidden md:block text-left">
                        <div class="text-xs font-bold text-black leading-tight"><?= htmlspecialchars($user['name']) ?></div>
                        <div class="text-[10px] text-zinc-400 leading-tight"><?= htmlspecialchars($user['email']) ?></div>
                    </div>

                    <a href="<?= $baseUrl ?>/auth/logout.php" 
                       title="Keluar dari Akun"
                       class="p-1.5 text-zinc-400 hover:text-black hover:bg-zinc-100 rounded-lg transition ml-0.5"
                       aria-label="Logout">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                    </a>
                </div>

            </div>
        </header>

        <!-- Main Dynamic Content Body (Responsive Mobile Padding) -->
        <?php if (!empty($isFullHeight)): ?>
            <main class="flex-1 overflow-hidden flex flex-col relative min-h-0">
        <?php else: ?>
            <main class="flex-1 p-3.5 sm:p-6 md:p-8 pb-20 md:pb-8">
        <?php endif; ?>
