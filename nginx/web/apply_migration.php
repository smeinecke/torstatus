<?php

declare(strict_types=1);

// Copyright (c) 2006-2007, Joseph B. Kowalski
// See LICENSE for licensing information

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/config.php';

$trackFile = __DIR__ . '/.applied_migrations.json';
$migrationDir = realpath(__DIR__ . '/../../mariadb/sql/migrations');
if ($migrationDir === false) {
    fwrite(STDERR, "Migration directory not found.\n");
    exit(1);
}

$server = (string)($SQL_Server ?? '');
$user = (string)($SQL_User ?? '');
$password = (string)($SQL_Pass ?? '');
$catalog = (string)($SQL_Catalog ?? '');

if ($server === '' || $catalog === '') {
    fwrite(STDERR, "Database configuration missing in config.php.\n");
    exit(1);
}

// Parse CLI arguments
/** @var int<1, max> $argc */
/** @var array<int, string> $argv */
$skipped = [];
for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--skip' && isset($argv[$i + 1])) {
        $skipped[] = $argv[$i + 1];
        $i++;
    }
}

// Load applied migrations
$applied = [];
if (file_exists($trackFile)) {
    $raw = file_get_contents($trackFile);
    if ($raw !== false) {
        $json = json_decode($raw, true);
        if (is_array($json) && isset($json['applied']) && is_array($json['applied'])) {
            $applied = $json['applied'];
        }
    }
}

$appliedMap = array_flip($applied);

// Discover migration files
$migrations = glob($migrationDir . '/202*.sql');
if ($migrations === false || $migrations === []) {
    echo "No migration files found.\n";
    exit(0);
}

sort($migrations);

// Connect to DB
mysqli_report(MYSQLI_REPORT_STRICT);
try {
    $mysqli = new mysqli($server, $user, $password, $catalog);
    if ($mysqli->connect_error) {
        fwrite(STDERR, "Connection failed: " . $mysqli->connect_error . "\n");
        exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

$hasError = false;

foreach ($migrations as $path) {
    $name = basename($path);

    if (isset($appliedMap[$name])) {
        echo "[SKIP] Already applied: {$name}\n";
        continue;
    }

    if (in_array($name, $skipped, true)) {
        echo "[SKIP] Explicitly skipped: {$name}\n";
        $applied[] = $name;
        continue;
    }

    $sql = file_get_contents($path);
    if ($sql === false) {
        fwrite(STDERR, "[ERROR] Could not read: {$name}\n");
        $hasError = true;
        continue;
    }

    // Remove USE commands; we are already connected to the correct catalog
    $lines = explode("\n", $sql);
    $filtered = [];
    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if (stripos($trimmed, 'USE ') === 0) {
            echo "[INFO] Skipping USE command in {$name}\n";
            continue;
        }
        $filtered[] = $line;
    }
    $sql = implode("\n", $filtered);

    echo "[APPLY] {$name} ... ";

    // Execute statements individually for clearer error reporting
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        static fn(string $s): bool => $s !== ''
    );

    $success = true;
    foreach ($statements as $stmt) {
        if (!$mysqli->query($stmt)) {
            fwrite(STDERR, "\n[ERROR] {$name}: " . $mysqli->error . "\n");
            $success = false;
            $hasError = true;
            break;
        }
    }

    if ($success) {
        echo "OK\n";
        $applied[] = $name;
    }
}

$mysqli->close();

// Persist tracking file
$tmpFile = $trackFile . '.tmp';
$written = file_put_contents(
    $tmpFile,
    json_encode(['applied' => $applied], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
    LOCK_EX
);
if ($written !== false) {
    rename($tmpFile, $trackFile);
} else {
    fwrite(STDERR, "[WARNING] Could not write tracking file: {$trackFile}\n");
}

if ($hasError) {
    exit(1);
}

echo "Done.\n";
exit(0);
