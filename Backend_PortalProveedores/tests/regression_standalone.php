<?php

// Standalone Regression Test Script
// Bypasses CodeIgniter framework to avoid missing 'Intl' extension requirement

require_once __DIR__ . '/../vendor/autoload.php';

// Manually load our classes since we are outside CI4 autoloader
require_once __DIR__ . '/../app/Libraries/Parsers/InvoiceParserInterface.php';
require_once __DIR__ . '/../app/Libraries/Parsers/GenericStrategy.php';
require_once __DIR__ . '/../app/Libraries/Parsers/SignifyStrategy.php';
require_once __DIR__ . '/../app/Libraries/InvoiceParserManager.php';

// Load .env manually for standalone test
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0)
            continue;
        if (strpos($line, '=') === false)
            continue;

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

use App\Libraries\InvoiceParserManager;

echo "\n============================================\n";
echo "   INVOICE PARSER V3 - REGRESSION TEST\n";
echo "============================================\n\n";

$path = __DIR__ . '/../writable/test_invoices/';
if (!is_dir($path)) {
    die("Error: Directory $path not found.\n");
}

$files = glob($path . '*.pdf');
if (empty($files)) {
    die("Error: No PDF files found in $path\n");
}

$manager = new InvoiceParserManager();
$results = [];

foreach ($files as $file) {
    $filename = basename($file);
    echo "Processing: $filename ... ";

    $start = microtime(true);
    $data = $manager->process($file);
    $time = round(microtime(true) - $start, 3);

    if (isset($data['error'])) {
        echo "[FAIL] \n  -> Error: " . $data['error'] . "\n";
        $results[] = ['file' => $filename, 'status' => 'FAIL', 'msg' => $data['error']];
    } elseif (empty($data['items'])) {
        echo "[WARN] No items found.\n";
        $results[] = ['file' => $filename, 'status' => 'WARN', 'msg' => 'No items found', 'total' => $data['total']];
    } else {
        $count = count($data['items']);
        echo "[OK] Found $count items. Total: " . $data['total'] . " ($time s)\n";
        $results[] = ['file' => $filename, 'status' => 'PASS', 'items' => $count, 'total' => $data['total']];
    }
}

echo "\n--------------------------------------------\n";
echo "SUMMARY:\n";
$passed = 0;
foreach ($results as $r) {
    if ($r['status'] == 'PASS')
        $passed++;
    echo str_pad($r['status'], 6) . " | " . str_pad(substr($r['file'], 0, 30), 32) . " | Data: " . ($r['items'] ?? 0) . " items, Total: " . ($r['total'] ?? 0) . "\n";
}
echo "\nTotal Passed: $passed / " . count($files) . "\n";
