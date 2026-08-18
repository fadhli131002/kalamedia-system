<?php
$files = glob(__DIR__ . '/../*.php');
$subfiles = glob(__DIR__ . '/../**/*.php');
$all = array_merge($files, $subfiles);
$hasError = false;
foreach ($all as $f) {
    if (is_file($f)) {
        $out = [];
        $ret = 0;
        exec("php -l \"$f\"", $out, $ret);
        if ($ret !== 0) {
            echo "Lint Error in: $f\n" . implode("\n", $out) . "\n";
            $hasError = true;
        }
    }
}
if (!$hasError) {
    echo "100% PASS: All " . count($all) . " PHP files verified with zero syntax errors!\n";
}
