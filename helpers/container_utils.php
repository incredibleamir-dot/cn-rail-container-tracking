<?php
/**
 * Container Utilities - Normalize, Validate, Format
 */

$LETTER_VALUES = [
    'A'=>10,'B'=>12,'C'=>13,'D'=>14,'E'=>15,'F'=>16,'G'=>17,'H'=>18,'I'=>19,'J'=>20,
    'K'=>21,'L'=>23,'M'=>24,'N'=>25,'O'=>26,'P'=>27,'Q'=>28,'R'=>29,'S'=>30,'T'=>31,
    'U'=>32,'V'=>34,'W'=>35,'X'=>36,'Y'=>37,'Z'=>38,
];

function containerNormalize($input) {
    $input = strtoupper(trim($input));
    $input = preg_replace('/[^A-Z0-9]/', '', $input);
    return $input;
}

function containerNormalizeForAPI($containerNumber) {
    $containerNumber = containerNormalize($containerNumber);

    if (strlen($containerNumber) === 11 && preg_match('/^([A-Z]{4})([0-9]{7})$/', $containerNumber, $m)) {
        $prefix = $m[1];
        $numeric = substr($m[2], 0, 6);
        $normalized = ltrim((string)(int)$numeric, '0');
        if ($normalized === '') $normalized = '0';
        return $prefix . $normalized;
    }

    if (strlen($containerNumber) === 10 && preg_match('/^[A-Z]{4}[0-9]{6}$/', $containerNumber)) {
        $prefix = substr($containerNumber, 0, 4);
        $numeric = substr($containerNumber, 4);
        $normalized = ltrim((string)(int)$numeric, '0');
        if ($normalized === '') $normalized = '0';
        return $prefix . $normalized;
    }

    return $containerNumber;
}

function containerValidate($number) {
    global $LETTER_VALUES;

    $number = containerNormalize($number);
    if (!preg_match('/^([A-Z]{4})(\d{7})$/', $number, $m)) {
        return ['valid' => false, 'reason' => 'Format: must be 4 letters + 7 digits'];
    }

    $prefix = $m[1];
    $serial = $m[2];
    $checkDigit = (int)$serial[6];

    $total = 0;
    for ($i = 0; $i < 4; $i++) {
        $val = $LETTER_VALUES[$prefix[$i]] ?? null;
        if ($val === null) {
            return ['valid' => false, 'reason' => "Invalid character in prefix: {$prefix[$i]}"];
        }
        $total += $val * pow(2, $i);
    }

    for ($i = 0; $i < 6; $i++) {
        $total += (int)$serial[$i] * pow(2, $i + 4);
    }

    $cd = $total % 11;
    if ($cd === 10) $cd = 0;

    if ($cd !== $checkDigit) {
        return ['valid' => false, 'reason' => "Check digit mismatch (expected {$cd}, got {$checkDigit})"];
    }

    return ['valid' => true];
}

function containerFormatDisplay($number) {
    $clean = containerNormalize($number);
    if (strlen($clean) === 11) {
        return '<span class="cn-owner">' . substr($clean, 0, 4) . '</span>'
             . '<span class="cn-serial">' . substr($clean, 4, 6) . '</span>'
             . '<span class="cn-check">' . substr($clean, 10, 1) . '</span>';
    }
    if (strlen($clean) === 10) {
        return '<span class="cn-owner">' . substr($clean, 0, 4) . '</span>'
             . '<span class="cn-serial">' . substr($clean, 4) . '</span>';
    }
    return $clean;
}

function formatLoadEmpty($status) {
    if ($status === 'L') return 'Loaded';
    if ($status === 'E') return 'Empty';
    if ($status === 'C') return 'Chassis';
    return htmlspecialchars($status ?? 'N/A');
}

function isWeekend($date) {
    if (!$date) return false;
    try {
        $dt = new DateTime($date);
        $day = (int)$dt->format('N');
        return ($day === 6 || $day === 7);
    } catch (Exception $e) {
        return false;
    }
}

function parseTagsString($tags) {
    if (empty($tags)) return [];
    return array_map('trim', explode(',', $tags));
}

function customsStatus($raw) {
    if (empty($raw)) return 'none';
    $l = strtolower(trim($raw));

    $cleared = [
        'broker filed pars',
        'broker has filed',
        'cleared customs',
        'remanifest to freight forwarder',
    ];
    foreach ($cleared as $phrase) {
        if (strpos($l, $phrase) !== false) return 'cleared';
    }

    if (strpos($l, 'in bond') !== false) return 'hold';

    return 'unknown';
}

function tagsToString(array $tags) {
    $clean = array_filter(array_map(function($t) {
        return strtolower(trim($t));
    }, $tags));
    return implode(',', array_unique($clean));
}
