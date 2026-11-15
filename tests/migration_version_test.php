<?php
$root = isset($argv[1]) ? rtrim($argv[1], DIRECTORY_SEPARATOR) : dirname(__DIR__);
$runnerPath = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'migrate.php';
$migrationDir = $root . DIRECTORY_SEPARATOR . 'migrations';
if (!is_file($runnerPath) || !is_dir($migrationDir)) {
    fwrite(STDERR, "FAIL: migration runner or migrations directory not found\n");
    exit(1);
}

$runner = file_get_contents($runnerPath);
$migrations = glob($migrationDir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
sort($migrations, SORT_STRING);
$latest = $migrations ? basename(end($migrations)) : '';
if (!preg_match("/define\\('LV_SCHEMA_VERSION',\\s*'([^']+)'\\)/", $runner, $match)) {
    fwrite(STDERR, "FAIL: LV_SCHEMA_VERSION is not defined\n");
    exit(1);
}

if ($match[1] !== $latest) {
    fwrite(STDERR, "FAIL: runner marker {$match[1]} skips latest migration {$latest}\n");
    exit(1);
}

echo "PASS: runner marker {$match[1]} matches latest migration\n";
