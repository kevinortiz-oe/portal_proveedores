<?php

/**
 * Script de prueba manual para aislar y probar la Capa 1 (Visión) y Capa 2 (Validación)
 * Uso desde consola: php test_vision.php /ruta/a/factura.pdf
 */

// Cargar entorno de CodeIgniter 4 CLI
define('FCPATH', __DIR__ . '/public' . DIRECTORY_SEPARATOR);
require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Libraries\Parsers\VisionAiStrategy;
use App\Libraries\InvoiceValidator;

if ($argc < 2) {
    echo "Error: Debes proporcionar la ruta al PDF.\n";
    echo "Uso: php test_vision.php <ruta_al_pdf>\n";
    exit(1);
}

$pdfPath = $argv[1];
if (!file_exists($pdfPath)) {
    echo "Error: No se encontró el archivo $pdfPath\n";
    exit(1);
}

echo "=== INICIANDO PRUEBA DE VISION AI Y VALIDACIÓN ===\n";
echo "Archivo: $pdfPath\n\n";

echo "[CAPA 1] Ejecutando VisionAiStrategy...\n";
$visionStrategy = new VisionAiStrategy();
$invoicesFound = $visionStrategy->parse($pdfPath);

if (empty($invoicesFound) || isset($invoicesFound[0]['error'])) {
    echo "Error en Capa 1:\n";
    print_r($invoicesFound[0] ?? 'Error desconocido');
    exit(1);
}

echo "\n[CAPA 1] Resultado Extracción:\n";
$invoiceData = $invoicesFound[0];
print_r($invoiceData);

echo "\n=========================================\n";
echo "[CAPA 2] Ejecutando Validación Matemática...\n";
$validator = new InvoiceValidator();
$result = $validator->validate($invoiceData);

echo "\n[CAPA 2] Resultado Validación:\n";
echo "Estado: " . $result['status'] . "\n";
echo "Válido: " . ($result['isValid'] ? 'SÍ' : 'NO') . "\n";

if (!$result['isValid']) {
    echo "Errores detectados:\n";
    foreach ($result['errors'] as $err) {
        echo "- Campo [{$err['campo']}]: {$err['mensaje']}\n";
    }
}

echo "\nPrueba finalizada.\n";
