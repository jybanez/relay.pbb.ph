<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/RelayKitInstaller.php';

$installPath = null;
for ($i = 1, $count = count($argv ?? []); $i < $count; $i++) {
    if (($argv[$i] ?? null) === '--install-path') {
        $installPath = $argv[++$i] ?? null;
    }
}

$status = RelayKitInstaller::status(is_string($installPath) && $installPath !== '' ? $installPath : null);

if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
}

echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
