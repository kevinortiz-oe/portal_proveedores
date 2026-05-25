<?php

namespace App\Libraries;

class InvoiceValidator
{
    /**
     * Valida una factura estructurada devolviendo si es válida y una lista de errores.
     *
     * @param array $invoiceData El array asociativo devuelto por el parser visual.
     * @return array [ 'isValid' => bool, 'status' => string, 'errors' => array ]
     */
    public function validate(array $invoiceData): array
    {
        $errors = [];
        $isValid = true;

        // Tolerancias
        $lineTolerance = 0.01;
        $globalTolerance = 0.05;

        // --- 1. Validación de Datos Maestros ---
        $requiredFields = [
            'fecha' => 'Fecha de factura',
            'total' => 'Total',
            'nit_emisor' => 'NIT Emisor',
            'nit_receptor' => 'NIT Receptor'
        ];

        foreach ($requiredFields as $field => $label) {
            if (empty($invoiceData[$field]) && $invoiceData[$field] !== 0.0 && $invoiceData[$field] !== 0) {
                $isValid = false;
                $errors[] = [
                    'campo' => $field,
                    'mensaje' => "El campo clave '{$label}' está vacío o nulo."
                ];
            }
        }

        // --- 2. Validación de Línea (Ítem) ---
        $items = $invoiceData['items'] ?? [];
        $sumOfItemsImporte = 0.0;

        foreach ($items as $index => $item) {
            $cantidad = (float)($item['cantidad'] ?? 0);
            $valorUnitario = (float)($item['valorUnitario'] ?? 0);
            $importe = (float)($item['importe'] ?? 0);
            
            $sumOfItemsImporte += $importe;

            $calculatedImporte = $cantidad * $valorUnitario;
            $diff = abs($calculatedImporte - $importe);

            if ($diff > $lineTolerance) {
                $isValid = false;
                $errors[] = [
                    'item_index' => $index,
                    'campo' => 'importe',
                    'mensaje' => "Fallo aritmético en la línea " . ($index + 1) . ": cantidad ($cantidad) * valorUnitario ($valorUnitario) = $calculatedImporte, pero el importe extraído es $importe. Diferencia: $diff (Tolerancia: $lineTolerance)"
                ];
            }

            // --- 2.5 Validación de Código Dudoso (Visión vs OCR) ---
            if (!empty($item['alerta_codigo_dudoso'])) {
                $isValid = false;
                $desc = !empty($item['descripcion']) ? $item['descripcion'] : 'Ítem ' . ($index + 1);
                $errors[] = [
                    'item_index' => $index,
                    'campo' => 'codigo',
                    'mensaje' => "⚠️ Advertencia de Lectura: La Inteligencia Artificial detectó borrosidad o discrepancias al extraer el código del artículo '{$desc}'. Por favor, verifique manualmente el código asignado."
                ];
            }
        }

        // --- 3. Validación de Subtotal ---
        $subtotal = (float)($invoiceData['subtotal'] ?? 0);
        $diffSubtotal = abs($sumOfItemsImporte - $subtotal);

        if ($diffSubtotal > $globalTolerance && count($items) > 0) {
            $isValid = false;
            $errors[] = [
                'campo' => 'subtotal',
                'mensaje' => "Fallo aritmético de Subtotal: La suma de importes de las líneas ($sumOfItemsImporte) no coincide con el subtotal general ($subtotal). Diferencia: $diffSubtotal (Tolerancia: $globalTolerance)"
            ];
        }

        // --- 4. Validación de Total ---
        $totalImpuestos = (float)($invoiceData['total_impuestos'] ?? 0);
        $totalDescuento = (float)($invoiceData['total_descuento'] ?? 0);
        $total = (float)($invoiceData['total'] ?? 0);

        // subtotal + total_impuestos - total_descuento == total
        $calculatedTotal = $subtotal + $totalImpuestos - $totalDescuento;
        $diffTotal = abs($calculatedTotal - $total);

        if ($diffTotal > $globalTolerance) {
            $isValid = false;
            $errors[] = [
                'campo' => 'total',
                'mensaje' => "Fallo aritmético de Total: subtotal ($subtotal) + impuestos ($totalImpuestos) - descuento ($totalDescuento) = $calculatedTotal, pero el total extraído es $total. Diferencia: $diffTotal (Tolerancia: $globalTolerance)"
            ];
        }

        $status = $isValid ? 'AUTO-APROBADO' : 'PENDIENTE DE REVISION';

        return [
            'isValid' => $isValid,
            'status' => $status,
            'errors' => $errors
        ];
    }
}
