<?php
function parse_number_input($val): float {
    if (is_numeric($val)) return floatval($val);
    $val = trim((string)$val);
    if ($val === '') return 0.0;
    $val = preg_replace('/[^\d.,]/', '', $val);
    // If format like 2.440 or 45.000 or 125.000 (single dot followed by exactly 3 digits, no comma)
    if (preg_match('/^\d{1,3}\.\d{3}$/', $val) && strpos($val, ',') === false) {
        $val = str_replace('.', '', $val);
    } elseif (substr_count($val, '.') > 1 && strpos($val, ',') !== false) {
        $val = str_replace('.', '', $val);
        $val = str_replace(',', '.', $val);
    } elseif (substr_count($val, '.') > 1) {
        $val = str_replace('.', '', $val);
    } elseif (substr_count($val, ',') > 1) {
        $val = str_replace(',', '', $val);
    } elseif (strpos($val, ',') !== false && strpos($val, '.') === false) {
        $val = str_replace(',', '.', $val);
    }
    return floatval($val);
}

$tests = [
    '2.440',
    '45.000',
    '52.40',
    '3.85',
    '4.8',
    '1.800.000.000',
    'Rp2.440',
    '3968'
];

foreach ($tests as $t) {
    echo "$t => " . parse_number_input($t) . "\n";
}
