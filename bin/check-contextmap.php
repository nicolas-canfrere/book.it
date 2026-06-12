#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Tools\ContextMap\ContextMapChecker;

$root   = dirname(__DIR__);

if (!file_exists($root . '/contextmap.yaml')) {
    fwrite(STDERR, "contextmap.yaml not found. Run: make contextmap\n");
    exit(2);
}

$result = (new ContextMapChecker())->check($root . '/contextmap.yaml', $root . '/src');

foreach ($result['ok'] as $msg) {
    echo "[OK]   {$msg}\n";
}
foreach ($result['fail'] as $msg) {
    echo "[FAIL] {$msg}\n";
}

if ([] !== $result['fail']) {
    exit(1);
}
