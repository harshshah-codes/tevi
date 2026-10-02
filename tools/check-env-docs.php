<?php
// Cross-check: every env var config.php reads must be documented in .env.example,
// and .env.example must not carry vars nothing reads.
$src = file_get_contents(__DIR__ . '/../app/Config/config.php');
preg_match_all('/\$_ENV\[\s*[\'"]([A-Z0-9_]+)[\'"]\s*\]/', $src, $m);
$need = array_values(array_unique($m[1]));
sort($need);

$have = [];
foreach (file(__DIR__ . '/../.env.example', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $t = trim($line);
    if (str_starts_with($t, '#') || strpos($t, '=') === false) {
        continue;
    }
    [$k, $v] = explode('=', $t, 2);
    $have[trim($k)] = trim($v);
}

$missing = array_diff($need, array_keys($have));
$orphan = array_diff(array_keys($have), $need);

echo 'config.php reads ' . count($need) . " vars:\n  " . implode("\n  ", $need) . "\n\n";
echo $missing
    ? 'MISSING from .env.example: ' . implode(', ', $missing) . "\n"
    : "OK - every var config.php reads is documented\n";
echo $orphan
    ? 'ORPHAN in .env.example (nothing reads these): ' . implode(', ', $orphan) . "\n"
    : "OK - no undocumented entries\n";