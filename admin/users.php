<?php
$pageTitle = 'Kelola Akun CS - Super Admin';
require_once __DIR__ . '/../includes/header.php';
require_role('superadmin');

$db = get_db();
$message = '';
$error = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
        $role = in_array($_POST['role'] ?? '', ['cs', 'superadmin']) ? $_POST['role'] : 'cs';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name) || empty($email)) {
            $error = 'Nama dan email wajib diisi.';
        } elseif ($role === 'cs' && empty($brand_id)) {
            $error = 'Akun CS wajib ditugaskan ke salah satu Brand Travel.';
        } else {
            if ($action === 'add') {
                if (empty($password)) {
                    $error = 'Kata sandi wajib diisi untuk akun baru.';
                } else {
                    try {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("
                            INSERT INTO users (brand_id, name, email, password, role, is_active)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$brand_id, $name, $email, $hash, $role, $is_active]);
                        $message = "Akun <strong>" . htmlspecialchars($name) . "</strong> berhasil dibuat.";
                    } catch (PDOException $e) {
                        $error = 'Gagal membuat akun. Email tersebut mungkin sudah terdaftar.';
                    }
                }
            } else {
                $id = (int)($_POST['id'] ?? 0);
                try {
                    if (!empty($password)) {
                        // Update with new password
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("
                            UPDATE users 
                            SET brand_id = ?, name = ?, email = ?, password = ?, role = ?, is_active = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$brand_id, $name, $email, $hash, $role, $is_active, $id]);
                    } else {
                        // Update without changing password
                        $stmt = $db->prepare("
                            UPDATE users 
                            SET brand_id = ?, name = ?, email = ?, role = ?, is_active = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$brand_id, $name, $email, $role, $is_active, $id]);
                    }
                    $message = "Data akun <strong>" . htmlspecialchars($name) . "</strong> berhasil diperbarui.";
                } catch (PDOException $e) {
                    $error = 'Gagal memperbarui akun. Pastikan email belum digunakan user lain.';
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$_SESSION['user_id']) {
            $error = 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.';
        } else {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Akun berhasil dihapus.";
        }
    } elseif ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$_SESSION['user_id']) {
            $error = 'Anda tidak dapat menonaktifkan akun Anda sendiri.';
        } else {
            $stmt = $db->prepare("UPDATE users SET is_active = IF(is_active=1, 0, 1) WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Status keaktifan akun berhasil diubah.";
        }
    }
}

// Fetch brands for assignment dropdown
$brands = $db->query("SELECT id, name FROM brands ORDER BY id ASC")->fetchAll();

// Fetch users with brand name
$users = $db->query("
    SELECT u.*, b.name AS brand_name 
    FROM users u
    LEFT JOIN brands b ON u.brand_id = b.id
    ORDER BY u.role DESC, u.id ASC
")->fetchAll();

// Edit user loader
$editUser = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch() ?: null;
}
?>

<div class="max-w-7xl mx-auto space-y-6" x-data="usersAdmin()">


    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 sm:pb-6 border-b border-zinc-200 gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="index.php" class="text-xs text-zinc-500 hover:text-black inline-flex items-center gap-1 font-semibold transition">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    <span>Dashboard</span>
                </a>
                <span class="text-zinc-300">/</span>
                <span class="text-xs font-semibold text-black">Master Pengguna CS</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-black tracking-tight mt-1">Kelola Akun Customer Service</h1>
            <p class="text-xs text-zinc-500 mt-0.5">Penugasan akun CS ke masing-masing Brand Travel perusahaan</p>
        </div>
        <div>
            <button type="button" @click="showModal = true; resetForm();"
                class="px-4 py-2 bg-black hover:bg-zinc-800 text-white text-xs font-semibold rounded-xl transition shadow-sm flex items-center gap-2">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Buat Akun CS Baru</span>
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($message)): ?>
        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-900 flex items-center justify-between">
            <div><?= $message ?></div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-800 font-bold">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-900 font-semibold flex items-center justify-between">
            <div><?= $error ?></div>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-800 font-bold">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Users Table (Strict Monochrome) -->
    <div class="bg-white border border-zinc-200/80 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs min-w-[650px]">
                <thead class="bg-zinc-50 border-b border-zinc-200 text-zinc-600 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="py-3.5 px-4">Nama Lengkap & Email</th>
                        <th class="py-3.5 px-4">Brand Penugasan</th>
                        <th class="py-3.5 px-4 text-center">Peran (Role)</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-zinc-50/50 transition">
                            <td class="py-4 px-4 font-semibold text-zinc-900">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-zinc-100 border border-zinc-300 flex items-center justify-center font-bold text-xs text-zinc-800">
                                        <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="text-sm text-black font-bold"><?= htmlspecialchars($u['name']) ?></div>
                                        <div class="text-[11px] text-zinc-500 font-mono"><?= htmlspecialchars($u['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 px-4">
                                <?php if ($u['role'] === 'superadmin'): ?>
                                    <span class="text-zinc-400 italic">Lintas Seluruh Brand (Global)</span>
                                <?php else: ?>
                                    <span class="font-semibold text-zinc-800">
                                        <?= htmlspecialchars($u['brand_name'] ?: 'Belum di-assign') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-4 text-center">
                                <?php if ($u['role'] === 'superadmin'): ?>
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-900 border border-amber-300 shadow-2xs">
                                        Super Admin
                                    </span>
                                <?php else: ?>
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-sky-50 text-sky-800 border border-sky-200 shadow-2xs">
                                        CS Umroh
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-4 text-center">
                                <form method="POST" action="users.php" class="inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" <?= ($u['id'] === (int)$_SESSION['user_id']) ? 'disabled title="Akun sendiri tidak dapat diubah"' : '' ?>
                                        class="px-2.5 py-0.5 rounded text-[11px] font-semibold transition <?= $u['is_active'] ? 'bg-emerald-50 text-emerald-800 border border-emerald-300 hover:bg-emerald-100' : 'bg-zinc-100 text-zinc-500 border border-zinc-200 line-through hover:bg-zinc-200' ?>">
                                        <?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                    </button>
                                </form>
                            </td>
                            <td class="py-4 px-4 text-right space-x-2">
                                <a href="users.php?edit=<?= $u['id'] ?>" class="text-xs font-semibold text-black hover:underline">
                                    Edit
                                </a>
                                <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                    <form method="POST" action="users.php" class="inline" onsubmit="return confirm('Hapus akun ini secara permanen?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="text-xs text-zinc-400 hover:text-rose-600 transition">
                                            Hapus
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Form Tambah / Edit User -->
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/60 flex items-center justify-center p-4">
        <div @click.away="showModal = false" class="bg-white border border-zinc-200 rounded-xl max-w-md w-full p-6 shadow-xl relative">
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-zinc-100">
                <h3 class="text-sm font-bold text-black uppercase tracking-wider" id="modalTitle">
                    <?= $editUser ? 'Edit Akun Pengguna' : 'Tambah Akun CS Baru' ?>
                </h3>
                <button type="button" @click="showModal = false" class="text-zinc-400 hover:text-black text-lg font-bold">&times;</button>
            </div>

            <form method="POST" action="users.php" class="space-y-4 text-xs">
                <input type="hidden" name="action" id="formAction" value="<?= $editUser ? 'edit' : 'add' ?>">
                <input type="hidden" name="id" id="formId" value="<?= $editUser['id'] ?? '' ?>">

                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Nama Lengkap CS *</label>
                    <input type="text" name="name" id="formName" required
                        class="w-full px-3 py-2 border border-zinc-300 rounded focus:outline-none focus:border-black"
                        placeholder="Contoh: Fitri / CS Fatimah"
                        value="<?= htmlspecialchars($editUser['name'] ?? '') ?>">
                </div>

                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Alamat Email (Untuk Login) *</label>
                    <input type="email" name="email" id="formEmail" required
                        class="w-full px-3 py-2 border border-zinc-300 rounded focus:outline-none focus:border-black"
                        placeholder="nama@csumroh.com"
                        value="<?= htmlspecialchars($editUser['email'] ?? '') ?>">
                </div>

                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">
                        Kata Sandi <?= $editUser ? '<span class="text-[10px] text-zinc-400 font-normal">(Kosongkan jika tidak diubah)</span>' : '*' ?>
                    </label>
                    <input type="password" name="password" id="formPassword" <?= $editUser ? '' : 'required' ?>
                        class="w-full px-3 py-2 border border-zinc-300 rounded focus:outline-none focus:border-black"
                        placeholder="••••••••">
                </div>

                <!-- Custom Dropdown for Brand Penugasan -->
                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Tugaskan ke Brand Travel *</label>
                    <input type="hidden" name="brand_id" :value="selectedBrandId">
                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                        <button type="button" @click="open = !open"
                                class="w-full flex items-center justify-between px-3 py-2 border border-zinc-300 bg-white rounded-xl text-xs font-semibold text-zinc-900 focus:outline-none focus:border-black shadow-xs">
                            <span x-text="selectedBrandLabel"></span>
                            <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="m6 9 6 6 6-6"/>
                            </svg>
                        </button>
                        <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                             class="absolute z-50 mt-1 w-full bg-white border border-zinc-200 rounded-xl shadow-xl max-h-56 overflow-y-auto py-1 text-xs divide-y divide-zinc-50">
                            <button type="button" @click="selectedBrandId = ''; open = false"
                                    class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 text-zinc-500 italic">
                                <span>-- Pilih Brand Penugasan CS --</span>
                            </button>
                            <template x-for="b in brands" :key="b.id">
                                <button type="button" @click="selectedBrandId = b.id; open = false"
                                        class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                        :class="selectedBrandId == b.id ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                    <span x-text="b.name"></span>
                                    <svg x-show="selectedBrandId == b.id" class="w-3.5 h-3.5 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                </button>
                            </template>
                        </div>
                    </div>
                    <p class="text-[10px] text-zinc-400 mt-1">CS ini hanya akan melihat paket dan menggunakan profil brand ini</p>
                </div>

                <!-- Custom Dropdown for Role -->
                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Peran Akses (Role)</label>
                    <input type="hidden" name="role" :value="selectedRole">
                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                        <button type="button" @click="open = !open"
                                class="w-full flex items-center justify-between px-3 py-2 border border-zinc-300 bg-white rounded-xl text-xs font-semibold text-zinc-900 focus:outline-none focus:border-black shadow-xs">
                            <span x-text="selectedRole === 'superadmin' ? 'Super Admin' : 'CS Umroh'"></span>
                            <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="m6 9 6 6 6-6"/>
                            </svg>
                        </button>
                        <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                             class="absolute z-50 mt-1 w-full bg-white border border-zinc-200 rounded-xl shadow-xl py-1 text-xs divide-y divide-zinc-50">
                            <button type="button" @click="selectedRole = 'cs'; open = false"
                                    class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                    :class="selectedRole === 'cs' ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                <span>CS Umroh</span>
                                <svg x-show="selectedRole === 'cs'" class="w-3.5 h-3.5 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                            </button>
                            <button type="button" @click="selectedRole = 'superadmin'; open = false"
                                    class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-zinc-50 transition"
                                    :class="selectedRole === 'superadmin' ? 'font-bold text-black bg-zinc-50' : 'text-zinc-700'">
                                <span>Super Admin</span>
                                <svg x-show="selectedRole === 'superadmin'" class="w-3.5 h-3.5 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <input type="checkbox" name="is_active" id="formIsActive" value="1" <?= (!$editUser || $editUser['is_active']) ? 'checked' : '' ?>
                        class="w-4 h-4 text-black border-zinc-300 rounded focus:ring-black">
                    <label for="formIsActive" class="font-semibold text-zinc-800">Akun Aktif (Bisa Login)</label>
                </div>

                <div class="pt-4 flex items-center justify-end gap-2 border-t border-zinc-100">
                    <button type="button" @click="showModal = false"
                        class="px-3.5 py-2 border border-zinc-300 text-zinc-700 hover:bg-zinc-50 rounded text-xs transition">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-black hover:bg-zinc-800 text-white rounded text-xs font-semibold transition inline-flex items-center gap-1.5">
                        <span>Simpan Akun</span>
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
    function usersAdmin() {
        return {
            showModal: <?= $editUser ? 'true' : 'false' ?>,
            selectedBrandId: '<?= $editUser ? $editUser['brand_id'] : '' ?>',
            selectedRole: '<?= $editUser ? $editUser['role'] : 'cs' ?>',
            brands: <?= json_encode($brands) ?>,
            get selectedBrandLabel() {
                if (!this.selectedBrandId) return '-- Pilih Brand Penugasan CS --';
                const b = this.brands.find(x => x.id == this.selectedBrandId);
                return b ? b.name : '-- Pilih Brand Penugasan CS --';
            },
            resetForm() {
                this.selectedBrandId = '';
                this.selectedRole = 'cs';
                document.getElementById('formAction').value = 'add';
                document.getElementById('formId').value = '';
                document.getElementById('formName').value = '';
                document.getElementById('formEmail').value = '';
                document.getElementById('formPassword').value = '';
                document.getElementById('formIsActive').checked = true;
                document.getElementById('modalTitle').innerText = 'Buat Akun CS Baru';
            }
        };
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

