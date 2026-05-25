<?php

namespace App\Libraries\Parsers;

use Smalot\PdfParser\Parser;

class GenericStrategy implements InvoiceParserInterface
{
    protected $patterns;
    protected $itemPatterns;

    public function __construct()
    {
        // Define dictionary patterns (Copied from V2)
        $this->patterns = [
            'nit_emisor' => [
                '/NIT(?:\s+[A-Z]+)*[:\.\s]*([\d\-kK]+)/i',
                '/Nit Emisor[:\s]*([\d\-kK]+)/i',
                '/NIT:\s*(\d+)/i',
                '/Nit[:\s]*(47301481)/i',
                '/CNPJ[:\s]*([\d\.\/\-]+)/i',               // Brasil
                '/RUC[:\s]*([\d\-]+)/i',                    // Panamá
                '/R\.F\.C:?\s*([A-Z0-9]+)/i',               // México
                '/CED\.\s?JURIDICA[:\s]*(\d+)/i',           // Costa Rica
            ],
            'nit_receptor' => [
                '/NIT Receptor[:\s]*([\d\-kK]+)/i',
                '/NIT Cliente[:\s]*([\d\-kK]+)/i',
                '/NIT Comprador[:\s]*([\d\-kK]+)/i',
                '/NIT[:\s]*([\d\-]+)/i',
                '/RUC\/Tax ID[:\s]*([\d\-]+)/i',            // Panamá
                '/Identificacion[:\s]*([\d\-]+)/i',         // Eagle Electric
                '/Número de Registro de Identidad Fiscal[:\s]*([\d\-]+)/i', // Condumex
            ],
            'uuid' => [
                '/([A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12})/i',
                '/Autorización[:\s]*([A-F0-9\-]+)/i',
                '/Cufe[:\s\n]*([0-9]{40,})/i',
                '/Folio Fiscal[:\s]*([A-F0-9\-]+)/i',
            ],
            'serie' => [
                '/Serie[:\s]*([A-Z0-9]+)/i',
                '/Factura\s+Electrónica\s+N°\s+([A-Z0-9]+)/i',
            ],
            'numero' => [
                '/(?:No\.|NUMERO|DTE|nº|Number)[:\s#]+([A-Z0-9\-]+)/i',
                '/Factura\s+#\s*([A-Z0-9\-]+)/i',
                '/Document Number[:\s]*(\d+)/i',
                '/Factura\/Invoice\s*([A-Z0-9]+)/i',
            ],
            'fecha' => [
                '/(\d{2})\/(\d{2})\/(\d{4})/',
                '/(\d{4})-(\d{2})-(\d{2})/',
                '/(\d{1,2})\s+de\s+([A-Z]+)\s+de\s+(\d{4})/i',
                '/Date[:\s]*(\d{2}\/\d{2}\/\d{4})/i',
            ],
            'total' => [
                '/TOTAL[:\s]+[Q\$]?\s*([\d\.,]+)/i',
                '/Total a Pagar[:\s]+[Q\$]?\s*([\d\.,]+)/i',
                '/Monto Total[:\s]+[Q\$]?\s*([\d\.,]+)/i',
                '/Total\s+USD[:\s\n]*([\d\.,]+)/i',
                '/TOTAL\s*[\n\s]*\$?([\d\.,]+)/i',
            ],
            'subtotal' => [
                '/SUB\s*TOTAL[:\s]+[Q\$]?\s*([\d\.,]+)/i',
                '/Sub-Total[:\s]+[Q\$]?\s*([\d\.,]+)/i',
            ],
            'descripcion_global' => [
                '/DESCRIPCI[ÓO]N\s*\/?\s*DESCRIPTION\s*[:\.\s]+(.*?)(?:\n|$|Total|Monto)/iu',
                '/DESCRIPCI[ÓO]N\s*[:\.\s]+(.*?)(?:\n|$|Total|Monto)/iu',
                '/DESCRIPTION\s*[:\.\s]+(.*?)(?:\n|$|Total|Monto)/iu',
            ]
        ];

        // Define item patterns (Copied from V2)
        $this->itemPatterns = [
            [
                'regex' => '/^([\d\.,]+)\s+(.*?)\s+([A-Z0-9]+)\s+(?:[\d\w\/]*\s*)?([\d\.]*,\d{2,4})\s+([\d\.]*,\d{2,4})$/iu',
                'map' => [1 => 'cantidad', 2 => 'descripcion', 3 => 'codigo', 4 => 'precio_u', 5 => 'total']
            ],
            [
                'regex' => '/^(\d+)\s+(.*?)\s+([A-Z0-9\-]+)\s+([A-Z0-9\-]+)\s+([\d\.,]+)\s+(?:Unid|OS)\s+([\d\.,]+)\s+([\d\.,]+)$/iu',
                'map' => [1 => 'linea', 2 => 'descripcion', 4 => 'codigo', 5 => 'cantidad', 6 => 'precio_u', 7 => 'total']
            ],
            [
                'regex' => '/^[\d]*\s+(?:Unid|OS)\s+([\d\.,]+)\s+(.*?)\s+\$([\d\.,]+)\s+\$([\d\.,]+)$/iu',
                'map' => [1 => 'linea', 3 => 'descripcion', 2 => 'cantidad', 4 => 'precio_u', 5 => 'total']
            ],
            [
                'regex' => '/^(\d{10,})\s+(.*?)\s+([\d\.,]+\/?[A-Z]*)\s+([\d\.,]+\/?[A-Z]*)\s+([\d\.,]+)$/iu',
                'map' => [1 => 'codigo', 2 => 'descripcion', 3 => 'cantidad', 4 => 'precio_u', 5 => 'total']
            ],
            [
                'regex' => '/^(.*?)\s+[\$Q]?\s*([\d\.]*,\d{2,4}|[\d,]*\.\d{2,4})\s+[\$Q]?\s*([\d\.]*,\d{2,4}|[\d,]*\.\d{2,4})$/iu',
                'map' => [1 => 'descripcion', 2 => 'precio_u', 3 => 'total']
            ]
        ];
    }

    public function canParse(string $text): bool
    {
        // This generic strategy always returns true as a fallback
        return true;
    }

    public function parse(string $filePath): array
    {
        if (!file_exists($filePath))
            return ['error' => 'File not found'];

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();

            // Basic OCR Fallback
            if (strlen(trim($text)) < 50)
                $text = $this->runOcr($filePath);

            $text = preg_replace('/\r\n/', "\n", $text);
            $cleanText = $text;

            $header = $this->extractFromDict($cleanText, ['uuid', 'serie', 'numero', 'nit_emisor', 'nit_receptor', 'nombre_emisor', 'receptor_nombre', 'fecha', 'descripcion_global']);
            $totals = $this->extractFromDict($cleanText, ['total', 'subtotal']);

            if (empty($header['nit_emisor']) || empty($header['nit_receptor'])) {
                $this->refineNits($cleanText, $header);
            }

            $header['fecha'] = $this->normalizeDate($header['fecha'] ?? null);
            $this->detectLocale($cleanText);
            $items = $this->extractItemsImproved($text);

            // Fallback Logic
            if (count($items) == 0 && !empty($header['descripcion_global']) && !empty($totals['total'])) {
                $items[] = [
                    'cantidad' => 1,
                    'descripcion' => $header['descripcion_global'],
                    'valorUnitario' => $this->cleanAmount($totals['total']),
                    'importe' => $this->cleanAmount($totals['total']),
                    'unidadMedida' => 'UNIDAD',
                    'tipoBienServicio' => 'BIEN',
                    'codigo' => null
                ];
            }

            $currency = $this->detectCurrency($cleanText);

            return [
                [
                    'fecha' => $header['fecha'],
                    'serie' => $header['serie'] ?? null,
                    'numero' => $header['numero'] ?? null,
                    'uuid' => $header['uuid'] ?? null,
                    'moneda' => $currency,
                    'subtotal' => $this->cleanAmount($totals['subtotal'] ?? 0),
                    'total' => $this->cleanAmount($totals['total'] ?? 0),
                    'nit_emisor' => $header['nit_emisor'] ?? null,
                    'nombre_emisor' => substr($header['nombre_emisor'] ?? $header['nit_emisor'] ?? 'Unknown', 0, 250),
                    'nit_receptor' => $header['nit_receptor'] ?? null,
                    'nombre_receptor' => substr($header['receptor_nombre'] ?? $header['nit_receptor'] ?? 'Unknown', 0, 250),
                    'items' => $items
                ]
            ];

        } catch (\Exception $e) {
            return [["error" => $e->getMessage()]];
        }
    }

    // --- Private Helper Methods (Ported from V2) --- 

    private function extractItemsImproved($text)
    {
        $lines = explode("\n", $text);

        // Pass 1: Strict
        $items = $this->scanLineItems($lines, true);

        // Pass 2: Permissive
        if (empty($items)) {
            $items = $this->scanLineItems($lines, false);
        }
        return $items;
    }

    private function scanLineItems($lines, $requireHeader)
    {
        $items = [];
        $inItemsSection = !$requireHeader;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($requireHeader && !$inItemsSection && preg_match('/DESCRIPCI[ÓO]N|DESCRIPTION|ITEM|CANTIDAD|Quantity/i', $line)) {
                $inItemsSection = true;
                continue;
            }

            if (preg_match('/^(TOTAL|SUBTOTAL|Subtotal|Total|Página|\*{5,})/i', $line)) {
                if ($requireHeader)
                    $inItemsSection = false;
                else
                    break;
            }

            if (!$inItemsSection || strlen($line) < 10)
                continue;
            if (stripos($line, 'Tipo de cambio') !== false)
                continue;

            foreach ($this->itemPatterns as $pattern) {
                if (preg_match($pattern['regex'], $line, $matches)) {
                    $item = $this->createItemFromMatches($matches, $pattern['map']);
                    if ($item['importe'] > 0)
                        $items[] = $item;
                    break;
                }
            }
        }
        return $items;
    }

    private function createItemFromMatches($matches, $map)
    {
        $item = [
            'cantidad' => 1,
            'descripcion' => 'Item',
            'valorUnitario' => 0,
            'importe' => 0,
            'unidadMedida' => 'UNIDAD',
            'tipoBienServicio' => 'BIEN',
            'codigo' => null
        ];

        foreach ($map as $groupIndex => $field) {
            if (!isset($matches[$groupIndex]))
                continue;
            $value = trim($matches[$groupIndex]);

            if ($field === 'precio_u')
                $item['valorUnitario'] = $this->cleanAmount($value);
            elseif ($field === 'total')
                $item['importe'] = $this->cleanAmount($value);
            elseif ($field === 'cantidad')
                $item['cantidad'] = (float) $this->cleanAmount($value); // Use cleanAmount here too
            elseif ($field === 'descripcion')
                $item['descripcion'] = $value;
            elseif ($field === 'codigo')
                $item['codigo'] = $value;
        }

        if ($item['valorUnitario'] == 0 && $item['importe'] > 0 && $item['cantidad'] > 0)
            $item['valorUnitario'] = $item['importe'] / $item['cantidad'];
        if ($item['importe'] == 0 && $item['valorUnitario'] > 0 && $item['cantidad'] > 0)
            $item['importe'] = $item['valorUnitario'] * $item['cantidad'];

        return $item;
    }

    private function extractFromDict($text, $fields)
    {
        $results = [];
        foreach ($fields as $field) {
            $results[$field] = null;
            if (!isset($this->patterns[$field]))
                continue;
            foreach ($this->patterns[$field] as $regex) {
                if (preg_match($regex, $text, $matches)) {
                    $results[$field] = isset($matches[1]) ? trim($matches[1]) : trim($matches[0]);
                    break;
                }
            }
        }
        return $results;
    }

    private function refineNits($text, &$header)
    {
        $identifiers = [];
        $patterns = [
            '/(?:NIT|RUC|RFC|CNPJ)[:\s]*([\d\-\.\/A-Z]+)/i',
            '/(\d{6,}\-\d)/',
            '/(\d{8,})/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    if (strlen($match) > 4)
                        $identifiers[] = trim($match);
                }
            }
        }
        $identifiers = array_unique($identifiers);
        $identifiers = array_values($identifiers);

        if (empty($header['nit_emisor']) && isset($identifiers[0]))
            $header['nit_emisor'] = $identifiers[0];
        if (empty($header['nit_receptor']) && isset($identifiers[1]))
            $header['nit_receptor'] = $identifiers[1];
    }

    private $detectedLocale = 'US'; // Default

    private function detectLocale($text)
    {
        // Scan for numbers with two separators to determine locale
        // EU/LatAm: 1.250,50 or 12.345,67
        // US: 1,250.50 or 12,345.67
        if (preg_match('/[\d]+\.[\d]{3},[\d]{2}/', $text)) {
            $this->detectedLocale = 'EU';
        } elseif (preg_match('/[\d]+,[\d]{3}\.[\d]{2}/', $text)) {
            $this->detectedLocale = 'US';
        }
    }

    private function cleanAmount($str)
    {
        $clean = trim($str);
        $clean = preg_replace('/[^\d\.,]/', '', $clean); // Remove currency symbols etc

        if ($this->detectedLocale === 'EU') {
            // In EU, . is thousands, , is decimal
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } else {
            // In US, , is thousands, . is decimal
            // But also handle ambiguous cases like 2.880 when dots are used for thousands
            if (strpos($clean, ',') === false && strpos($clean, '.') !== false) {
                // If there's only a dot and it's followed by 3 digits (e.g., 2.880)
                // and we've seen dots as thousands elsewhere or it looks like a large quantity
                if (preg_match('/\.\d{3}$/', $clean)) {
                    // Check if it's likely a thousands separator
                    // For quantities, 2.880 is more likely to be 2880 than 2.88 precisely
                    // This is a heuristic.
                    $clean = str_replace('.', '', $clean);
                }
            } else {
                $clean = str_replace(',', '', $clean);
            }
        }
        return (float) $clean;
    }

    private function normalizeDate($dateStr)
    {
        if (!$dateStr)
            return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr))
            return $dateStr;
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dateStr, $m))
            return "{$m[3]}-{$m[2]}-{$m[1]}";

        $meses = [
            'ene' => '01',
            'feb' => '02',
            'mar' => '03',
            'abr' => '04',
            'may' => '05',
            'jun' => '06',
            'jul' => '07',
            'ago' => '08',
            'sep' => '09',
            'oct' => '10',
            'nov' => '11',
            'dic' => '12',
            'enero' => '01',
            'febrero' => '02',
            'marzo' => '03',
            'abril' => '04',
            'mayo' => '05',
            'junio' => '06',
            'julio' => '07',
            'agosto' => '08',
            'septiembre' => '09',
            'octubre' => '10',
            'noviembre' => '11',
            'diciembre' => '12'
        ];

        $clean = strtolower($dateStr);
        foreach ($meses as $mesStr => $mesNum) {
            if (strpos($clean, $mesStr) !== false) {
                if (preg_match('/(\d{1,2}).*?' . $mesStr . '.*?(\d{4})/', $clean, $m)) {
                    $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                    return "{$m[2]}-{$mesNum}-{$day}";
                }
            }
        }
        return null;
    }

    private function detectCurrency($text)
    {
        if (preg_match('/USD|D[ÓO]LARES|US\$|\$/i', $text))
            return 'USD';
        return 'GTQ';
    }

    private function runOcr($filePath)
    {
        if (class_exists('Thiagoalessio\TesseractOCR\TesseractOCR')) {
            try {
                return (new \Thiagoalessio\TesseractOCR\TesseractOCR($filePath))->run();
            } catch (\Exception $e) {
                return "";
            }
        }
        return "";
    }
}
