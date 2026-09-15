<?php
/**
 * Scripts & Conversion Loader Service
 * Handles reading native JSON data and transforming legacy Agent POV to Customer Service (CS) POV.
 */

function transform_pov_text(string $text): string {
    // 1. Replace placeholder variables
    $text = str_replace('{{agent_name}}', '{{cs_name}}', $text);

    // 2. Transform phrases to Customer Service POV
    $replacements = [
        'mitra konsultan umroh dari {{travel}}' => 'Customer Service resmi {{travel}}',
        'mitra konsultan resmi {{travel}}'     => 'Customer Service resmi {{travel}}',
        'konsultan perwakilan resmi di {{travel}}' => 'tim layanan jamaah resmi di {{travel}}',
        'konsultan umroh resmi {{travel}}'     => 'Customer Service resmi {{travel}}',
        'konsultan umroh dari {{travel}}'      => 'Customer Service {{travel}}',
        'konsultan umroh {{travel}}'           => 'Customer Service {{travel}}',
        'mitra resmi travel'                   => 'tim layanan resmi travel',
        'referral link agen'                   => 'website resmi atau layanan chat',
        'pipeline agen'                        => 'daftar antrean layanan',
        'Mitra Agen'                           => 'Customer Service',
        'mitra agen'                           => 'Customer Service',
        'konsultan/mitra agen resmi travel'    => 'Customer Service resmi travel',
        'Agen'                                 => 'CS',
        'agen'                                 => 'CS',
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $text);
}

/**
 * Format conversation scripts with clean, readable WhatsApp line breaks (\n\n).
 * Prevents cramped single-paragraph text ("dinding teks") and structures the message into:
 * 1. Warm Greeting / Empathy
 * 2. Self-Identity / Travel Brand / Context
 * 3. Value Proposition / Body
 * 4. Call to Action / Closing Question
 */
function format_whatsapp_script(string $text): string {
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    // If text already has intentional double line breaks, keep them and normalize
    if (substr_count($text, "\n\n") >= 1 || substr_count($text, "\n") >= 2) {
        return preg_replace("/\n{3,}/", "\n\n", $text);
    }

    // 1. Separate initial greeting with emoji, comma, or exclamation/period
    // Examples: "Assalamu'alaikum Kak {{nama}} 😊", "Wa'alaikumussalam Kak {{nama}} 😊", "Halo Kak {{nama}} 😊"
    $text = preg_replace('/^((?:Assalamu[\']alaikum|Wa[\']alaikumussalam|Halo|Selamat\s+(?:pagi|siang|sore|malam)|Bismillah(?:irrahmanirrahim)?)[^\n\.\?\!]*?(?:😊|👋|🙏|\!|\.))\s+/iu', "$1\n\n", $text);

    // 2. Separate intro "Salam kenal, saya {{cs_name}}..." or "Saya {{cs_name}}..."
    $text = preg_replace('/(?<=[.!?😊👋🙏\n])\s*(Salam kenal,?\s+saya\s+\{\{cs_name\}\}[^.!?]*?[.!?])/iu', "\n\n$1\n\n", $text);
    $text = preg_replace('/(?<=[.!?😊👋🙏\n])\s*(Saya\s+\{\{cs_name\}\}[^.!?]*?[.!?])/iu', "\n\n$1\n\n", $text);

    // 3. Separate Call to Action / Closing Questions at the end of the message
    $text = preg_replace('/(?<=[.!?😊👋🙏])\s+((?:Kalau\s+boleh\s+tahu|Kira-kira|Rencananya|Apakah|Supaya\s+saya|Biar\s+saya|Boleh\s+tahu|Ada\s+yang|Bisa\s+kami\s+bantu|Bisa\s+saya\s+bantu|Masih\s+mau\s+saya|Sejauh\s+ini\s+sudah|Kakak\s+sedang\s+cari|Kakak\s+ada\s+rencana|Bagaimana\s+Kak)[^.!?\n]*?\?)$/iu', "\n\n$1", $text);

    // Also catch final questions if preceded by a sentence ending with . or ! or emoji
    $text = preg_replace('/(?<=[.!?😊👋🙏])\s+([A-Z0-9\{][^.!?\n]{15,}\?)$/u', "\n\n$1", $text);

    // 4. Normalize spaces and multiple newlines
    $text = preg_replace("/[ \t]+/", " ", $text);
    $text = preg_replace("/\n\s*\n\s*\n+/", "\n\n", $text);
    $text = preg_replace("/\n +/", "\n", $text);
    $text = preg_replace("/ +\n/", "\n", $text);

    return trim($text);
}

function transform_pov_array(mixed $data, string $parentKey = ''): mixed {
    if (is_string($data)) {
        $text = transform_pov_text($data);
        // Only format line breaks for actual chat scripts/responses, not IDs, titles, categories, or tags
        $scriptKeys = ['script', 'text', 'response', 'terima', 'gali', 'pastikan'];
        if (in_array($parentKey, $scriptKeys, true)) {
            $text = format_whatsapp_script($text);
        }
        return $text;
    }
    if (is_array($data)) {
        foreach ($data as $key => $val) {
            $data[$key] = transform_pov_array($val, is_string($key) ? $key : $parentKey);
        }
    }
    return $data;
}

function load_json_file(string $path): array {
    if (!file_exists($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    return transform_pov_array($decoded);
}

function get_conversion_cycle(): array {
    static $cycle = null;
    if ($cycle === null) {
        $filePath = __DIR__ . '/../conversion-chat/conversion-cycle.json';
        $cycle = load_json_file($filePath);
    }
    return $cycle;
}

function get_all_scripts(): array {
    static $scripts = null;
    if ($scripts === null) {
        $baseDir = __DIR__ . '/../scripts-chat/';
        $scripts = [
            'greeting'       => load_json_file($baseDir . 'greeting.json'),
            'identification' => load_json_file($baseDir . 'identification.json'),
            'offer'          => load_json_file($baseDir . 'offer.json'),
            'closing'        => load_json_file($baseDir . 'closing.json'),
            'objection'      => load_json_file($baseDir . 'objection.json'),
            'followup'       => load_json_file($baseDir . 'followups.json'),
        ];
    }
    return $scripts;
}

/**
 * Replace placeholders in a script string with actual values.
 */
function apply_placeholders(string $script, array $vars): string {
    $map = [
        '{{nama}}'            => $vars['prospect_name'] ?? 'Kakak',
        '{{cs_name}}'         => $vars['cs_name'] ?? 'Fitri',
        '{{agent_name}}'      => $vars['cs_name'] ?? 'Fitri',
        '{{travel}}'          => $vars['brand_name'] ?? 'Travel Umroh Kami',
        '{{ppiu}}'            => $vars['ppiu_number'] ?? '-',
        '{{rekening}}'        => ($vars['bank_name'] ?? '') . ' No. ' . ($vars['bank_account_number'] ?? ''),
        '{{nama_rekening}}'   => $vars['bank_account_holder'] ?? '',
        '{{paket}}'           => $vars['package_name'] ?? 'Paket Umroh Reguler',
        '{{harga}}'           => $vars['package_price'] ?? 'Rp 28.000.000',
        '{{dp}}'              => $vars['package_dp'] ?? 'Rp 5.000.000',
        '{{hotel}}'           => ($vars['hotel_makkah'] ?? 'Hotel Makkah Bintang 4') . ' & ' . ($vars['hotel_madinah'] ?? 'Hotel Madinah Bintang 4'),
        '{{jarak_hotel}}'     => $vars['hotel_distance'] ?? 'Dekat Pelataran Masjid',
        '{{maskapai}}'        => $vars['airline'] ?? 'Saudia / Garuda Direct',
        '{{bulan}}'           => $vars['departure_info'] ?? 'Bulan Depan',
        '{{tanggal}}'         => $vars['departure_info'] ?? 'Sesuai Jadwal',
        '{{durasi}}'          => $vars['duration'] ?? '9 Hari',
        '{{fasilitas_utama}}' => $vars['highlights'] ?? 'Akomodasi Nyaman & Pembimbing Ibadah Berpengalaman',
        '{{deadline}}'        => $vars['deadline'] ?? 'minggu ini',
        '{{seat}}'            => $vars['seat'] ?? 'sisa beberapa seat lagi',
        '{{jumlah_jamaah}}'   => $vars['pax'] ?? '1 orang',
        '{{followup_date}}'   => $vars['followup_date'] ?? 'besok lusa',
        '{{promo}}'           => $vars['promo'] ?? 'Promo Spesial',
    ];

    return str_replace(array_keys($map), array_values($map), $script);
}

