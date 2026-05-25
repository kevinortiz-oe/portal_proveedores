<?php
// Debug Script to view raw text of a PDF
require_once __DIR__ . '/../vendor/autoload.php';

$file = $argv[1] ?? 'writable/test_invoices/FACTURA 1057020943.pdf';
if (!file_exists($file))
    die("File not found: $file\n");

$parser = new \Smalot\PdfParser\Parser();
$pdf = $parser->parseFile($file);
$text = $pdf->getText();

echo "---------------- START RAW TEXT ----------------\n";
echo $text;
echo "\n---------------- END RAW TEXT ----------------\n";
