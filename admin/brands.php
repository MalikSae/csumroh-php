<?php
$pageTitle = 'Kelola Brand Travel - Super Admin';
require_once __DIR__ . '/../includes/header.php';
require_role('superadmin');

$db = get_db();
$message = '';
$error = '';

// Handle Form Submission (Add or Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $db->prepare("SELECT name FROM brands WHERE id = ?");
                $stmt->execute([$id]);
                $brandName = $stmt->fetchColumn();

                if ($brandName) {
                    $del = $db->prepare("DELETE FROM brands WHERE id = ?");
                    $del->execute([$id]);
                    $message = "Brand travel <strong>" . htmlspecialchars($brandName) . "</strong> dan seluruh paket terkait berhasil dihapus.";
                }
            } catch (PDOException $e) {
                $error = "Gagal menghapus brand: " . $e->getMessage();
            }
        }
    } elseif ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $ppiu_number = trim($_POST['ppiu_number'] ?? '');
        $bank_name = trim($_POST['bank_name'] ?? '');
        $bank_account_number = trim($_POST['bank_account_number'] ?? '');
        $bank_account_holder = trim($_POST['bank_account_holder'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $meta_pixel_id = trim($_POST['meta_pixel_id'] ?? '');
        $meta_access_token = trim($_POST['meta_access_token'] ?? '');
        $facebook_page_id = trim($_POST['facebook_page_id'] ?? '');

        if (empty($name) || empty($code)) {
            $error = 'Nama brand dan kode unik wajib diisi.';
        } else {
            // Auto format code
            $code = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '-', $code));

            if ($action === 'add') {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO brands (name, code, ppiu_number, bank_name, bank_account_number, bank_account_holder, address, phone, meta_pixel_id, meta_access_token, facebook_page_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, $code, $ppiu_number, $bank_name, $bank_account_number, $bank_account_holder, $address, $phone, $meta_pixel_id, $meta_access_token, $facebook_page_id]);
                    $message = "Brand travel <strong>" . htmlspecialchars($name) . "</strong> berhasil ditambahkan.";
                } catch (PDOException $e) {
                    $error = 'Gagal menyimpan: ' . $e->getMessage();
                }
            } elseif ($action === 'edit') {
                $id = (int)($_POST['id'] ?? 0);
                try {
                    $stmt = $db->prepare("
                        UPDATE brands 
                        SET name = ?, code = ?, ppiu_number = ?, bank_name = ?, 
                            bank_account_number = ?, bank_account_holder = ?, address = ?, phone = ?,
                            meta_pixel_id = ?, meta_access_token = ?, facebook_page_id = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $code, $ppiu_number, $bank_name, $bank_account_number, $bank_account_holder, $address, $phone, $meta_pixel_id, $meta_access_token, $facebook_page_id, $id]);
                    $message = "Brand travel <strong>" . htmlspecialchars($name) . "</strong> berhasil diperbarui.";
                } catch (PDOException $e) {
                    $error = 'Gagal memperbarui: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch all brands
$brands = $db->query("
    SELECT b.*, 
           COUNT(DISTINCT p.id) AS package_count,
           COUNT(DISTINCT u.id) AS cs_count
    FROM brands b
    LEFT JOIN packages p ON b.id = p.brand_id
    LEFT JOIN users u ON b.id = u.brand_id AND u.role = 'cs'
    GROUP BY b.id
    ORDER BY b.id ASC
")->fetchAll();

$editBrand = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($brands as $b) {
        if ((int)$b['id'] === $editId) {
            $editBrand = $b;
            break;
        }
    }
}
?>

<div class="max-w-7xl mx-auto space-y-6" x-data="{ showModal: <?= $editBrand ? 'true' : 'false' ?> }">
    
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 sm:pb-6 border-b border-zinc-200 gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="index.php" class="text-xs text-zinc-500 hover:text-black inline-flex items-center gap-1 font-semibold transition">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    <span>Dashboard</span>
                </a>
                <span class="text-zinc-300">/</span>
                <span class="text-xs font-semibold text-black">Master Brand Travel</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-black tracking-tight mt-1">Kelola 5 Brand Travel</h1>
            <p class="text-xs text-zinc-500 mt-0.5">Pengaturan izin PPIU Kemenag, rekening transfer DP resmi, dan identitas brand</p>
        </div>
        <div>
            <button type="button" @click="showModal = true; resetForm();"
                class="px-4 py-2 bg-black hover:bg-zinc-800 text-white text-xs font-semibold rounded-xl transition shadow-sm flex items-center gap-2">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Tambah Brand Baru</span>
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

    <!-- Brands Grid List -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 mb-8">
        <?php foreach ($brands as $b): ?>
            <div class="bg-white border border-zinc-200 rounded-xl p-5 hover:border-black transition flex flex-col justify-between shadow-2xs">
                <div>
                    <div class="flex items-start justify-between gap-2 mb-3">
                        <div>
                            <span class="text-[10px] font-mono uppercase bg-zinc-100 px-2 py-0.5 rounded text-zinc-600 font-semibold">
                                <?= htmlspecialchars($b['code']) ?>
                            </span>
                            <h3 class="text-base font-bold text-black mt-1.5"><?= htmlspecialchars($b['name']) ?></h3>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <a href="brands.php?edit=<?= $b['id'] ?>" class="text-xs px-2.5 py-1 border border-zinc-300 rounded hover:bg-zinc-50 hover:border-black transition text-zinc-700 font-medium">
                                Edit
                            </a>
                            <form method="POST" action="brands.php" class="inline" onsubmit="return confirm('Peringatan: Yakin ingin menghapus brand \'<?= htmlspecialchars(addslashes($b['name'])) ?>\'? Seluruh paket umroh di bawah brand ini akan ikut terhapus.')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                <button type="submit" class="text-xs px-2 py-1 border border-zinc-200 rounded hover:border-rose-300 hover:bg-rose-50 hover:text-rose-600 transition text-zinc-400">
                                    Hapus
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="space-y-2 text-xs pt-3 border-t border-zinc-100 text-zinc-600">
                        <div>
                            <span class="text-zinc-400 block text-[10px] uppercase font-semibold">Izin PPIU Kemenag:</span>
                            <span class="font-mono text-zinc-800"><?= htmlspecialchars($b['ppiu_number'] ?: 'Belum diisi') ?></span>
                        </div>
                        <div>
                            <span class="text-zinc-400 block text-[10px] uppercase font-semibold">Rekening Bank Resmi Transfer DP:</span>
                            <div class="font-semibold text-zinc-900"><?= htmlspecialchars($b['bank_name'] ?: '-') ?> &bull; <?= htmlspecialchars($b['bank_account_number'] ?: '-') ?></div>
                            <div class="text-[11px] text-zinc-500">a/n <?= htmlspecialchars($b['bank_account_holder'] ?: '-') ?></div>
                        </div>
                        <div>
                            <span class="text-zinc-400 block text-[10px] uppercase font-semibold">Kontak & Alamat:</span>
                            <div><?= htmlspecialchars($b['phone'] ?: '-') ?></div>
                            <div class="text-[11px] text-zinc-400 truncate"><?= htmlspecialchars($b['address'] ?: '-') ?></div>
                        </div>
                        <div>
                            <span class="text-zinc-400 block text-[10px] uppercase font-semibold">Meta CAPI & CTWA:</span>
                            <div class="flex items-center gap-1 mt-0.5">
                                <?php if (!empty($b['meta_pixel_id'])): ?>
                                    <span class="px-1.5 py-0.5 bg-zinc-900 text-white rounded text-[10px] font-mono">Pixel: <?= htmlspecialchars($b['meta_pixel_id']) ?></span>
                                <?php else: ?>
                                    <span class="text-zinc-400 text-[11px] italic">Belum disetting</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 pt-3 border-t border-zinc-100 flex items-center justify-between text-[11px] text-zinc-500">
                    <span class="font-medium text-zinc-800"><?= $b['package_count'] ?> Paket Umroh</span>
                    <span>&bull;</span>
                    <span class="font-medium text-zinc-800"><?= $b['cs_count'] ?> CS Bertugas</span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Modal Form Tambah / Edit Brand -->
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/60 flex items-center justify-center p-4">
        <div @click.away="showModal = false" class="bg-white border border-zinc-200 rounded-xl max-w-lg w-full p-6 shadow-xl relative max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-zinc-100 sticky top-0 bg-white z-10">
                <h3 class="text-sm font-bold text-black uppercase tracking-wider" id="modalTitle">
                    <?= $editBrand ? 'Edit Data Brand Travel' : 'Tambah Brand Travel Baru' ?>
                </h3>
                <button type="button" @click="showModal = false" class="text-zinc-400 hover:text-black text-lg font-bold">&times;</button>
            </div>

            <form method="POST" action="brands.php" class="space-y-3.5 text-xs">
                <input type="hidden" name="action" id="formAction" value="<?= $editBrand ? 'edit' : 'add' ?>">
                <input type="hidden" name="id" id="formId" value="<?= $editBrand['id'] ?? '' ?>">

                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Nama Brand Travel *</label>
                    <input type="text" name="name" id="formName" required
                        class="w-full px-3 py-2 border border-zinc-300 rounded focus:outline-none focus:border-black"
                        placeholder="Contoh: Azhan Tour & Travel"
                        value="<?= htmlspecialchars($editBrand['name'] ?? '') ?>">
                </div>

                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Kode Unik / Slug *</label>
                    <input type="text" name="code" id="formCode" required
                        class="w-full px-3 py-2 border border-zinc-300 rounded focus:outline-none focus:border-black font-mono"
                        placeholder="contoh: azhan-tour"
                        value="<?= htmlspecialchars($editBrand['code'] ?? '') ?>">
                    <p class="text-[10px] text-zinc-400 mt-0.5">Digunakan sebagai pengenal sistem (huruf kecil & tanda minus)</p>
                </div>

                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Nomor Izin PPIU Kemenag</label>
                    <input type="text" name="ppiu_number" id="formPpiu"
                        class="w-full px-3 py-2 border border-zinc-300 rounded focus:outline-none focus:border-black font-mono"
                        placeholder="Contoh: 123/PPIU/KEMENAG/2023"
                        value="<?= htmlspecialchars($editBrand['ppiu_number'] ?? '') ?>">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">Nama Bank Transfer</label>
                        <input type="text" name="bank_name" id="formBankName"
                            class="w-full px-3 py-2 border border-zinc-300 rounded focus:outline-none focus:border-black"
                            placeholder="Contoh: BSI / Mandiri"
                            value="<?= htmlspecialchars($editBrand['bank_name'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">Nomor Rekening</label>
                        <input type="text" name="bank_account_number" id="formBankAcc"
                            class="w-full px-3 py-2 border border-zinc-300 rounded focus:outline-none focus:border-black font-mono"
                            placeholder="Contoh: 7188291021"
                            value="<?= htmlspecialchars($editBrand['bank_account_number'] ?? '') ?>">
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-zinc-700 mb-1">Atas Nama Rekening (PT / Yayasan)</label>
                    <input type="text" name="bank_account_holder" id="formBankHolder"
                        class="w-full px-3 py-2 border border-zinc-300 rounded focus:outline-none focus:border-black"
                        placeholder="Contoh: PT Azhan Wisata Mandiri"
                        value="<?= htmlspecialchars($editBrand['bank_account_holder'] ?? '') ?>">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">No. Kontak / WA Pusat</label>
                        <input type="text" name="phone" id="formPhone"
                            class="w-full px-3 py-2 border border-zinc-300 rounded focus:outline-none focus:border-black"
                            placeholder="Contoh: 081234567890"
                            value="<?= htmlspecialchars($editBrand['phone'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1">Alamat Kantor Pusat</label>
                        <input type="text" name="address" id="formAddress"
                            class="w-full px-3 py-2 border border-zinc-300 rounded focus:outline-none focus:border-black"
                            placeholder="Kota / Alamat Ringkas"
                            value="<?= htmlspecialchars($editBrand['address'] ?? '') ?>">
                    </div>
                </div>

                <!-- Meta Conversions API & CTWA Settings Section -->
                <div class="pt-3 border-t border-zinc-200">
                    <h4 class="font-bold text-zinc-900 uppercase text-[11px] mb-2 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        Meta Conversions API (CAPI) & CTWA Tracking
                    </h4>
                    <p class="text-[10px] text-zinc-500 mb-3">Untuk mengirimkan event Purchase (DP Closing) otomatis ke Ads Manager.</p>

                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold text-zinc-700 mb-1">Meta Pixel ID</label>
                                <input type="text" name="meta_pixel_id" id="formPixelId"
                                    class="w-full px-3 py-2 border border-zinc-300 rounded focus:outline-none focus:border-black font-mono"
                                    placeholder="Contoh: 123456789012345"
                                    value="<?= htmlspecialchars($editBrand['meta_pixel_id'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="block font-semibold text-zinc-700 mb-1">Facebook Page ID</label>
                                <input type="text" name="facebook_page_id" id="formPageId"
                                    class="w-full px-3 py-2 border border-zinc-300 rounded focus:outline-none focus:border-black font-mono"
                                    placeholder="Contoh: 109876543210987"
                                    value="<?= htmlspecialchars($editBrand['facebook_page_id'] ?? '') ?>">
                            </div>
                        </div>
                        <div>
                            <label class="block font-semibold text-zinc-700 mb-1">Meta CAPI Access Token</label>
                            <textarea name="meta_access_token" id="formAccessToken" rows="2"
                                class="w-full px-3 py-2 border border-zinc-300 rounded focus:outline-none focus:border-black font-mono text-[11px]"
                                placeholder="EAAG..."><?= htmlspecialchars($editBrand['meta_access_token'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="pt-4 flex items-center justify-between gap-2 border-t border-zinc-100">
                    <div>
                        <?php if ($editBrand): ?>
                            <button type="submit" name="action" value="delete" 
                                onclick="return confirm('Peringatan: Yakin ingin menghapus brand travel ini? Seluruh paket umroh di bawah brand ini akan ikut terhapus.')"
                                class="text-xs text-zinc-400 hover:text-black hover:underline">
                                Hapus Brand Ini
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="showModal = false"
                            class="px-3.5 py-2 border border-zinc-300 text-zinc-700 hover:bg-zinc-50 rounded text-xs transition">
                            Batal
                        </button>
                        <button type="submit"
                            class="px-4 py-2 bg-black hover:bg-zinc-800 text-white rounded text-xs font-semibold transition inline-flex items-center gap-1.5">
                            <span>Simpan Data Brand</span>
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
    function resetForm() {
        document.getElementById('formAction').value = 'add';
        document.getElementById('formId').value = '';
        document.getElementById('formName').value = '';
        document.getElementById('formCode').value = '';
        document.getElementById('formPpiu').value = '';
        document.getElementById('formBankName').value = '';
        document.getElementById('formBankAcc').value = '';
        document.getElementById('formBankHolder').value = '';
        document.getElementById('formPhone').value = '';
        document.getElementById('formAddress').value = '';
        document.getElementById('formPixelId').value = '';
        document.getElementById('formPageId').value = '';
        document.getElementById('formAccessToken').value = '';
        document.getElementById('modalTitle').innerText = 'Tambah Brand Travel Baru';
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

