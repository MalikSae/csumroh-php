<?php
require_once __DIR__ . '/../includes/auth_check.php';

$file = $_GET['file'] ?? 'greeting';
$allowed = ['greeting', 'identification', 'offer', 'closing', 'objection', 'followups'];
if (!in_array($file, $allowed)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'File tidak valid']);
    exit;
}

$path = __DIR__ . '/../scripts-chat/' . $file . '.json';
if (!file_exists($path)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'File tidak ditemukan']);
    exit;
}

$data = json_decode(file_get_contents($path), true);
$rawScripts = $data['scripts'] ?? [];
$normalized = [];

foreach ($rawScripts as $item) {
    // Objection format: uses 'tgjp' object instead of flat 'script'
    if (!isset($item['script']) && isset($item['tgjp'])) {
        $tgjp = $item['tgjp'];

        // Build a combined readable script from TGJP steps
        $parts = [];

        // TERIMA — pick first
        $terima = $tgjp['terima'] ?? [];
        if (!empty($terima)) {
            $parts[] = '🤝 *Terima:*' . "\n" . (is_array($terima) ? $terima[0] : $terima);
        }

        // GALI — pick first
        $gali = $tgjp['gali'] ?? [];
        if (!empty($gali)) {
            $parts[] = '🔍 *Gali:*' . "\n" . (is_array($gali) ? $gali[0] : $gali);
        }

        // JAWAB — pick first that has a 'script' key
        $jawab = $tgjp['jawab'] ?? [];
        foreach ($jawab as $j) {
            if (isset($j['script'])) {
                $parts[] = '💬 *Jawab:*' . "\n" . $j['script'];
                break;
            }
        }

        // PASTIKAN — pick first
        $pastikan = $tgjp['pastikan'] ?? [];
        if (!empty($pastikan)) {
            $parts[] = '✅ *Pastikan:*' . "\n" . (is_array($pastikan) ? $pastikan[0] : $pastikan);
        }

        $item['script']   = implode("\n\n", $parts);
        $item['use_when'] = implode(' / ', array_slice($item['prospect_examples'] ?? [], 0, 2));

        // Build clean steps array for sending individually (no labels)
        $steps = [];
        if (!empty($terima)) {
            $steps[] = ['label' => 'T', 'text' => is_array($terima) ? $terima[0] : $terima];
        }
        if (!empty($gali)) {
            $steps[] = ['label' => 'G', 'text' => is_array($gali) ? $gali[0] : $gali];
        }
        foreach (($tgjp['jawab'] ?? []) as $j) {
            if (isset($j['script'])) {
                $steps[] = ['label' => 'J', 'text' => $j['script']];
                break;
            }
        }
        if (!empty($pastikan)) {
            $steps[] = ['label' => 'P', 'text' => is_array($pastikan) ? $pastikan[0] : $pastikan];
        }
        $item['steps'] = $steps;
    }

    // NPGD framework mapping for identification scripts (ringkas tanpa inisial)
    if ($file === 'identification') {
        $npgdMap = [
            'need'          => ['code' => 'need',          'label' => 'Need',          'name' => 'Need',          'desc' => 'Kebutuhan'],
            'pain'          => ['code' => 'pain',          'label' => 'Pain',          'name' => 'Pain',          'desc' => 'Kendala'],
            'gain'          => ['code' => 'gain',          'label' => 'Gain',          'name' => 'Gain',          'desc' => 'Manfaat'],
            'dream'         => ['code' => 'dream',         'label' => 'Dream',         'name' => 'Dream',         'desc' => 'Impian'],
            'qualification' => ['code' => 'qualification', 'label' => 'Kualifikasi',   'name' => 'Kualifikasi',   'desc' => 'Info Praktis'],
            'combination'   => ['code' => 'combination',   'label' => 'Kombinasi',     'name' => 'Kombinasi',     'desc' => 'Terpadu'],
            'transition'    => ['code' => 'transition',    'label' => 'Transisi',      'name' => 'Transisi',      'desc' => 'Penawaran'],
        ];
        $cat = strtolower($item['category'] ?? '');
        $item['npgd'] = $npgdMap[$cat] ?? null;
    }

    // POV transform: agent → CS
    $item['script']   = str_replace(['{{agent_name}}', 'mitra agen', 'Mitra Agen'], ['{{cs_name}}', 'tim CS', 'Tim CS'], $item['script'] ?? '');
    $item['use_when'] = str_replace(['Agen ', 'agen '], ['CS ', 'cs '], $item['use_when'] ?? '');

    $normalized[] = $item;
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'file'    => $file,
    'nama'    => $data['nama'] ?? $file,
    'scripts' => $normalized,
]);
