<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Redirect if already logged in
if (is_logged_in()) {
    $user = get_logged_user();
    if ($user && $user['role'] === 'superadmin') {
        header('Location: ../admin/brands.php');
    } else {
        header('Location: ../index.php');
    }
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Silakan masukkan email dan password.';
    } else {
        $db = get_db();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['brand_id'] = $user['brand_id'];

            if ($user['role'] === 'superadmin') {
                header('Location: ../admin/brands.php');
            } else {
                header('Location: ../index.php');
            }
            exit;
        } else {
            $error = 'Email atau password salah, atau akun Anda belum aktif.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CS Umroh Copilot</title>
    <!-- Google Fonts: Google Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.cdnfonts.com/css/google-sans">
    
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
        html, body, input, button, select, textarea, h1, h2, h3, h4, h5, h6, p, span, a {
            font-family: 'Google Sans', 'Product Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
        }
    </style>
</head>
<body class="bg-zinc-50 text-zinc-900 min-h-screen flex flex-col justify-center items-center p-4 antialiased selection:bg-black selection:text-white">

    <div class="w-full max-w-md">
        <!-- Brand Title Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-lg bg-black text-white font-bold text-xl mb-3 shadow-sm">
                CS
            </div>
            <h1 class="text-2xl font-bold text-black tracking-tight">CS Umroh Copilot</h1>

        </div>

        <!-- Login Card (Strict Monochrome) -->
        <div class="bg-white border border-zinc-200 rounded-xl p-6 md:p-8 shadow-sm">
            <h2 class="text-lg font-semibold text-black mb-1">Masuk ke Akun</h2>
            <p class="text-xs text-zinc-500 mb-6">Gunakan kredensial email dan kata sandi Anda</p>

            <?php if (!empty($error)): ?>
                <div class="mb-5 p-3.5 text-xs bg-zinc-100 border border-zinc-300 text-zinc-900 rounded-md">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="space-y-4">
                <div>
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-zinc-600 mb-1.5">Email CS / Admin</label>
                    <input type="email" id="email" name="email" required
                        class="w-full px-3.5 py-2.5 bg-white border border-zinc-300 rounded-md text-sm text-zinc-900 placeholder-zinc-400 focus:outline-none focus:border-black focus:ring-1 focus:ring-black transition"
                        placeholder="nama@csumroh.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div x-data="{ showPassword: false }">
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-zinc-600 mb-1.5">Kata Sandi</label>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'" id="password" name="password" required
                            class="w-full pl-3.5 pr-10 py-2.5 bg-white border border-zinc-300 rounded-md text-sm text-zinc-900 placeholder-zinc-400 focus:outline-none focus:border-black focus:ring-1 focus:ring-black transition"
                            placeholder="••••••••">
                        <button type="button" @click="showPassword = !showPassword"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-zinc-400 hover:text-black focus:outline-none transition"
                            tabindex="-1"
                            :title="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'">
                            <!-- Eye icon (when hidden) -->
                            <svg x-show="!showPassword" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <!-- Eye-off icon (when shown) -->
                            <svg x-show="showPassword" x-cloak class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path>
                                <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path>
                                <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path>
                                <line x1="2" y1="2" x2="22" y2="22"></line>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit"
                    class="w-full mt-2 py-2.5 px-4 bg-black hover:bg-zinc-800 text-white font-medium text-sm rounded-md transition duration-150 shadow-sm inline-flex items-center justify-center gap-2">
                    <span>Masuk Sekarang</span>
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-zinc-400 mt-6">
            &copy; <?= date('Y') ?> CS Umroh Copilot. Sistem Internal Perusahaan.
        </p>
    </div>

</body>
</html>

