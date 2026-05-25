<?php
namespace App\Libraries;

use Smalot\PdfParser\Parser;

class PdfInvoiceParserV2
{
    /**
     * DICCIONARIO DE REGLAS (PATRONES) MEJORADO
     */
    protected $patterns = [
        // ========== NIT/RUC/RFC EMISOR ==========
        'nit_emisor' => [
            // Originales
            '/NIT(?:\s+[A-Z]+)*[:\.\s]*([\d\-kK]+)/i',
            '/Nit Emisor[:\s]*([\d\-kK]+)/i',
            '/NIT:\s*(\d+)/i',
            '/Nit[:\s]*(47301481)/i',

            // Internacionales detectados
            '/CNPJ[:\s]*([\d\.\/\-]+)/i', // Brasil
            '/RUC[:\s]*([\d\-]+)/i', // Panamá
            '/R\.F\.C:?\s*([A-Z0-9]+)/i', // México
            '/CED\.\s?JURIDICA[:\s]*(\d+)/i', // Costa Rica
            '/RFC\.?\s*([A-Z0-9]+)/i', // México alternativa
            '/RUC[:\s]*([\d\-]+)/i', // Panamá alternativa
        ],

        // ========== NIT/RUC/RFC RECEPTOR ==========
        'nit_receptor' => [
            '/NIT Receptor[:\s]*([\d\-kK]+)/i',
            '/NIT Cliente[:\s]*([\d\-kK]+)/i',
            '/NIT Comprador[:\s]*([\d\-kK]+)/i',
            '/NIT[:\s]*([\d\-]+)/i',
            '/RUC\/Tax ID[:\s]*([\d\-]+)/i',
            '/Identificacion[:\s]*([\d\-]+)/i',
            '/Número de Registro de Identidad Fiscal[:\s]*([\d\-]+)/i',
            '/NIT:\s*(\d{6,}\-\d)/i', // Formato El Salvador
            '/NIT:\s*(\d{8,})/i', // Formato Guatemala
        ],

        // ========== NOMBRE EMISOR ==========
        'nombre_emisor' => [
            '/(?:Solicitante|Sold - To)[:\s\n]+([A-ZÑÁÉÍÓÚÜ\s\.,\-&]+)(?=\n|ILN|NIT|RUC)/iu',
            '/(?:Factura De|Invoice From)[:\s\n]+([^\n]+)/iu',
            '/^([A-ZÑÁÉÍÓÚÜ\s\.,\-&]{10,})(?=\n|RUC|NIT|R\.F\.C)/iu',
            '/(?:EMISOR|EMISOR:)\s+([^\n]+)/iu',
            '/SIGNIFY\s+[^\n]+/iu', // Caso específico
            '/CONDUMEX\s+SA\s+DE\s+CV/iu', // Caso específico
            '/(?:Comercializadora|Distribuidora)[^\n]+/iu',
        ],

        // ========== NOMBRE RECEPTOR ==========
        'nombre_receptor' => [
            '/(?:Facturado A|Bill To)[:\s\n]+([A-ZÑÁÉÍÓÚÜ\s\.,\-&]+)(?=\n|NIT|RUC|RFC)/iu',
            '/(?:Importador|Importer)[:\s\n]+([^\n]+)/iu',
            '/(?:Cliente|Client)[:\s\n]+([^\n]+)/iu',
            '/(?:CELASA|DISTRIBUIDORA VOLANTIS)[^\n]+/iu', // Casos específicos
        ],

        // ========== UUID/CUFE/CLAVE ==========
        'uuid' => [
            '/([A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12})/i',
            '/Autorización[:\s]*([A-F0-9\-]+)/i',
            '/Cufe[:\s\n]*([0-9A-Z\-]{40,})/i', // Panamá: Código CUFE
            '/Clave Númerica[:\s]*(\d{40,})/i', // Costa Rica
            '/Clave Número[:\s]*(\d{40,})/i', // Costa Rica alternativa
            '/Folio Fiscal[:\s]*([A-F0-9\-]+)/i', // México
            '/No\. de Certificado[:\s]*([0-9]+)/i', // México CFDI
        ],

        // ========== SERIE ==========
        'serie' => [
            '/Serie[:\s]*([A-Z0-9]+)/i',
            '/Factura\s+Electrónica\s+N°\s+([A-Z0-9\-]+)/i',
            '/Factura\s+de\s+Exportación\s+([A-Z0-9]+)/i',
            '/FACTURA\s+DE\s+EXPORTACIÓN\s+([A-Z0-9]+)/i',
        ],

        // ========== NÚMERO ==========
        'numero' => [
            '/(?:No\.|NUMERO|DTE|nº|Number|Número)[:\s#]+([A-Z0-9\-]+)/i',
            '/Factura\s+#\s*([A-Z0-9\-]+)/i',
            '/Document Number[:\s]*([\d\-]+)/i',
            '/Factura\/Invoice\s*([A-Z0-9\-]+)/i',
            '/Factura\s+de\s+Exportación\s+(\d{12,})/i',
            '/Factura\s+(\d{10,})/i',
        ],

        // ========== FECHA ==========
        'fecha' => [
            '/(\d{2})[\/\-](\d{2})[\/\-](\d{4})/',
            '/(\d{4})[\/\-](\d{2})[\/\-](\d{2})/',
            '/Fecha[:\s]*(\d{2}\/\d{2}\/\d{4})/i',
            '/Date[:\s]*(\d{2}\/\d{2}\/\d{4})/i',
            '/Fecha de emisión[:\s]*(\d{4}-\d{2}-\d{2})/i',
            '/(\d{1,2})\s+de\s+([a-z]+)\s+de\s+(\d{4})/i',
            '/Document Date[:\s]*(\d{2}\/\d{2}\/\d{4})/i',
        ],

        // ========== TOTAL ==========
        'total' => [
            '/TOTAL[:\s]+(?:USD\s*)?[\$\s]*([\d\.,]+)/i',
            '/Total a Pagar[:\s]+(?:USD\s*)?[\$\s]*([\d\.,]+)/i',
            '/Monto Total[:\s]+(?:USD\s*)?[\$\s]*([\d\.,]+)/i',
            '/Total\s+USD[:\s\n]*([\d\.,]+)/i',
            '/Valor Factura[:\s\n]*([\d\.,]+)/i',
            '/Fob\s+Puerto\/Santos\s*([\d\.,]+)/i',
            '/TOTAL\s*[\n\s]*\$?\s*([\d\.,]+)/i',
            '/Total invoice value[:\s]*([\d\.,]+)/i',
            '/Total de Rebajas\s*([\d\.,]+)/i', // Brasil: descuentos
        ],

        // ========== SUBTOTAL ==========
        'subtotal' => [
            '/SUB\s*TOTAL[:\s]+(?:USD\s*)?[\$\s]*([\d\.,]+)/i',
            '/Sub-Total[:\s]+(?:USD\s*)?[\$\s]*([\d\.,]+)/i',
            '/Subtotal[:\s]+(?:USD\s*)?[\$\s]*([\d\.,]+)/i',
            '/Subtotal Net Value[:\s]*([\d\.,]+)/i',
            '/Net Value[:\s]*([\d\.,]+)/i',
        ],

        // ========== DESCRIPCIÓN GLOBAL ==========
        'descripcion_global' => [
            '/DESCRIPCI[ÓO]N\s*\/?\s*DESCRIPTION\s*[:\.\s]+(.*?)(?=\n|Total|Monto|VALOR|$)/ius',
            '/DESCRIPCI[ÓO]N\s*[:\.\s]+(.*?)(?=\n|Total|Monto|VALOR|$)/ius',
            '/DESCRIPTION\s*[:\.\s]+(.*?)(?=\n|Total|Monto|VALOR|$)/ius',
            '/Por concepto de\s*[:\.\s]+(.*?)(?=\n|Total|$)/ius',
        ],

        // ========== MONEDA ==========
        'moneda' => [
            '/Moneda[:\s]*([A-Z]{3})/i',
            '/Currency[:\s]*([A-Z]{3})/i',
            '/USD|D[ÓO]LARES|US\$/i',
        ],
    ];

    /**
     * Patrones para ítems MEJORADOS
     */
    protected $itemPatterns = [
        // ========== PATRÓN BRASIL (Curaçao) ==========
// Formato: 70 Acqua Duo Ultra ... 7517228DUK0051 ... 92,75 ... 6.492,50
        [
            'regex' => '/^([\d\.,]+)\s+(.*?)\s+([A-Z0-9]{10,})\s+(?:[\d\w\/]*\s*)?([\d\.,]+)\s+([\d\.,]+)$/iu',
            'map' => [1 => 'cantidad', 2 => 'descripcion', 3 => 'codigo', 4 => 'precio_u', 5 => 'total']
        ],

        // ========== PATRÓN LEGRAND ==========
// Formato: 1 DINCHUS DE EXTENSIÓN ... SV1-18911 ... 555238 ... 10.00 Unid ... 5.06100 ... 50.61000
        [
            'regex' => '/^(\d+)\s+(.*?)\s+([A-Z0-9\-]+)\s+([A-Z0-9\-]+)\s+([\d\.,]+)\s+(?:Unid|OS)\s+([\d\.,]+)\s+([\d\.,]+)$/iu',
            'map' => [1 => 'linea', 2 => 'descripcion', 4 => 'codigo', 5 => 'cantidad', 6 => 'precio_u', 7 => 'total']
        ],

        // ========== PATRÓN EAGLE ELECTRIC ==========
// Formato: 3116 Unid 300 78-W ... $0.71 ... $213.00
        [
            'regex' => '/^(\d+)\s+(?:Unid|OS)\s+([\d\.,]+)\s+(.*?)\s+\$?([\d\.,]+)\s+\$?([\d\.,]+)$/iu',
            'map' => [1 => 'linea', 3 => 'descripcion', 2 => 'cantidad', 4 => 'precio_u', 5 => 'total']
        ],

        // ========== PATRÓN SIGNIFY ==========
// Formato: 000929004285112 MAS LEDtube ... 2,000 / PZA ... 5.70 / PZA ... 11,400.00
        [
            'regex' => '/^(\d{12,})\s+(.*?)\s+([\d\.,]+)\s*\/\s*[A-Z]+\s+([\d\.,]+)\s*\/\s*[A-Z]+\s+([\d\.,]+)$/iu',
            'map' => [1 => 'codigo', 2 => 'descripcion', 3 => 'cantidad', 4 => 'precio_u', 5 => 'total']
        ],

        // ========== PATRÓN CONDUMEX ==========
// Formato: 3006932 Condumex ... 36.480 01 ... 12.23 ... 446.13
        [
            'regex' => '/^(\d+)\s+(.*?)\s+([\d\.,]+)\s+\d+\s+([\d\.,]+)\s+([\d\.,]+)$/iu',
            'map' => [1 => 'codigo', 2 => 'descripcion', 3 => 'cantidad', 4 => 'precio_u', 5 => 'total']
        ],

        // ========== PATRÓN GENÉRICO TABLA ==========
// Detecta filas con al menos 3 números (cantidad, precio, total)
        [
            'regex' => '/^(.*?)\s+([\d\.,]+)\s+([\d\.,]+)\s+([\d\.,]+)$/iu',
            'map' => [1 => 'descripcion', 2 => 'cantidad', 3 => 'precio_u', 4 => 'total']
        ],

        // ========== PATRÓN SIMPLE (descripción + precio + total) ==========
        [
            'regex' => '/^(.*?)\s+[\$Q]?\s*([\d\.,]+)\s+[\$Q]?\s*([\d\.,]+)$/iu',
            'map' => [1 => 'descripcion', 2 => 'precio_u', 3 => 'total']
        ],
    ];

    /**
     * Parsear PDF principal
     */
    public function parse($filePath)
    {
        log_message('error', "PDF PARSER V4.0 EXECUTING on: $filePath");

        if (!file_exists($filePath))
            return null;

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();

            // Si el texto es muy corto, intentar OCR
            if (strlen(trim($text)) < 100) {
                $text = $this->runOcr($filePath);
            }

            // Normalizar saltos de línea
            $text = preg_replace('/\r\n/', "\n", $text);
            $cleanText = $this->cleanText($text);

            // Extraer todos los campos
            $header = $this->extractAllFields($cleanText);

            // Refinar NITs si faltan
            if (empty($header['nit_emisor']) || empty($header['nit_receptor'])) {
                $this->refineNits($cleanText, $header);
            }

            // Normalizar fecha
            $header['fecha'] = $this->normalizeDate($header['fecha'] ?? null);

            // Extraer totales
            $totals = $this->extractTotals($cleanText);

            // Extraer items
            $items = $this->extractItemsImproved($cleanText);

            // Fallback para items si no se encontraron
            if (count($items) == 0 && !empty($header['descripcion_global']) && !empty($totals['total'])) {
                $items[] = $this->createFallbackItem($header['descripcion_global'], $totals['total']);
            }

            log_message('error', "PDFParser V4.0 Items Found: " . count($items));

            // Determinar moneda
            $currency = $this->detectCurrency($cleanText);

            // Preparar respuesta
            return [
                'fecha' => $header['fecha'],
                'serie' => $header['serie'] ?? null,
                'numero' => $header['numero'] ?? null,
                'folio' => $header['numero'] ?? null,
                'uuid' => $header['uuid'] ?? null,
                'moneda' => $currency,
                'subtotal' => $this->cleanAmount($totals['subtotal'] ?? 0),
                'total' => $this->cleanAmount($totals['total'] ?? 0),
                'total_impuestos' => 0,
                'nit_emisor' => $header['nit_emisor'] ?? null,
                'nombre_emisor' => substr($header['nombre_emisor'] ?? 'Unknown', 0, 250),
                'nit_receptor' => $header['nit_receptor'] ?? null,
                'nombre_receptor' => substr($header['nombre_receptor'] ?? 'Unknown', 0, 250),
                'direccion_receptor' => null,
                'items' => $items
            ];

        } catch (\Exception $e) {
            log_message('error', "PDFParser V4.0 Exception: " . $e->getMessage());
            return ["error" => $e->getMessage()];
        }
    }

    /**
     * Extraer todos los campos del texto
     */
    private function extractAllFields($text)
    {
        $results = [];

        foreach ($this->patterns as $field => $patterns) {
            $results[$field] = null;

            foreach ($patterns as $regex) {
                if (preg_match($regex, $text, $matches)) {
                    // Tomar el grupo 1 si existe, sino el grupo 0
                    $value = isset($matches[1]) ? trim($matches[1]) : trim($matches[0]);

                    // Limpiar valor
                    $value = $this->cleanFieldValue($value, $field);

                    if (!empty($value)) {
                        $results[$field] = $value;
                        break;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Extraer totales específicos
     */
    private function extractTotals($text)
    {
        $totals = ['total' => 0, 'subtotal' => 0];

        // Buscar total
        foreach ($this->patterns['total'] as $regex) {
            if (preg_match_all($regex, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $value = $this->cleanAmount($match[1]);
                    if ($value > $totals['total']) {
                        $totals['total'] = $value;
                    }
                }
            }
        }

        // Buscar subtotal
        foreach ($this->patterns['subtotal'] as $regex) {
            if (preg_match($regex, $text, $matches)) {
                $totals['subtotal'] = $this->cleanAmount($matches[1]);
                break;
            }
        }

        return $totals;
    }

    /**
     * Extraer items mejorado
     */
    private function extractItemsImproved($text)
    {
        $lines = explode("\n", $text);

        // Intento 1: Modo Estricto (Busca encabezados 'DESCRIPCION', etc)
        $items = $this->scanLineItems($lines, true);

        // Intento 2: Modo Permisivo (Si falló el 1, escanea todo hasta encontrar Totales)
        if (empty($items)) {
            $items = $this->scanLineItems($lines, false);
            if (!empty($items)) {
                log_message('error', "PDFParser: Items encontrados en segunda pasada (Permisiva).");
            }
        }
        return $items;
    }

    private function scanLineItems($lines, $requireHeader)
    {
        $items = [];
        $inItemsSection = !$requireHeader; // Si no es estricto, empezamos capturando

        foreach ($lines as $line) {
            $line = trim($line);

            // Detectar inicio (Solo modo estricto)
            if ($requireHeader && !$inItemsSection && preg_match('/DESCRIPCI[ÓO]N|DESCRIPTION|ITEM|CANTIDAD|Quantity/i', $line)) {
                $inItemsSection = true;
                continue;
            }

            // Detectar Fin: TOTALES (Stricter regex: Start of line)
            if (preg_match('/^(TOTAL|SUBTOTAL|Subtotal|Total|Página|\*{5,})/i', $line)) {
                if ($requireHeader)
                    $inItemsSection = false; // Estricto: Salimos
                else
                    break; // Permisivo: Terminamos definitivamente para no leer el footer
            }

            if (!$inItemsSection || strlen($line) < 10)
                continue;

            // Evitar lineas de "Tipo de cambio" (Doble check)
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

    /**
     * Crear item a partir de matches
     */
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
            if (!isset($matches[$groupIndex])) {
                continue;
            }

            $value = trim($matches[$groupIndex]);

            switch ($field) {
                case 'precio_u':
                    $item['valorUnitario'] = $this->cleanAmount($value);
                    break;
                case 'total':
                    $item['importe'] = $this->cleanAmount($value);
                    break;
                case 'cantidad':
                    $item['cantidad'] = (float) $this->cleanAmount($value);
                    break;
                case 'descripcion':
                    $item['descripcion'] = substr($value, 0, 200);
                    break;
                case 'codigo':
                    $item['codigo'] = $value;
                    break;
                case 'linea':
                    // No hacer nada, es solo para referencia
                    break;
            }
        }

        // Calcular valores si faltan
        if ($item['valorUnitario'] == 0 && $item['importe'] > 0 && $item['cantidad'] > 0) {
            $item['valorUnitario'] = $item['importe'] / $item['cantidad'];
        }

        if ($item['importe'] == 0 && $item['valorUnitario'] > 0 && $item['cantidad'] > 0) {
            $item['importe'] = $item['valorUnitario'] * $item['cantidad'];
        }

        return $item;
    }

    /**
     * Crear item de fallback
     */
    private function createFallbackItem($descripcion, $total)
    {
        return [
            'cantidad' => 1,
            'descripcion' => substr($descripcion, 0, 200),
            'valorUnitario' => $this->cleanAmount($total),
            'importe' => $this->cleanAmount($total),
            'unidadMedida' => 'UNIDAD',
            'tipoBienServicio' => 'BIEN',
            'codigo' => null
        ];
    }

    /**
     * Limpiar texto
     */
    private function cleanText($text)
    {
        // Remover múltiples espacios
        $text = preg_replace('/\s+/', ' ', $text);

        // Remover caracteres no imprimibles
        $text = preg_replace('/[^\x20-\x7E\xA0-\xFF]/u', ' ', $text);

        return $text;
    }

    /**
     * Limpiar valor de campo
     */
    private function cleanFieldValue($value, $field)
    {
        // Remover etiquetas comunes
        $value = preg_replace('/^[:\.\s]+|[:\.\s]+$/', '', $value);

        // Para nombres, mantener mayúsculas y caracteres especiales
        if (strpos($field, 'nombre') !== false || strpos($field, 'descripcion') !== false) {
            return trim($value);
        }

        // Para identificadores, remover espacios
        if (
            strpos($field, 'nit') !== false || strpos($field, 'ruc') !== false ||
            strpos($field, 'rfc') !== false || strpos($field, 'uuid') !== false ||
            strpos($field, 'numero') !== false || strpos($field, 'serie') !== false
        ) {
            return preg_replace('/\s+/', '', $value);
        }

        return trim($value);
    }

    /**
     * Limpiar monto (soporta coma decimal)
     */
    private function cleanAmount($str)
    {
        if (empty($str))
            return 0;

        $clean = trim(str_replace(' ', '', $str));

        // Determinar si es formato europeo (1.234,56) o americano (1,234.56)
        $lastComma = strrpos($clean, ',');
        $lastDot = strrpos($clean, '.');

        if ($lastComma !== false && $lastDot !== false) {
            // Tiene ambos, ver cuál está más a la derecha
            if ($lastComma > $lastDot) {
                // Formato europeo: 1.234,56 -> quitar puntos, convertir coma a punto
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                // Formato americano: 1,234.56 -> quitar comas
                $clean = str_replace(',', '', $clean);
            }
        } elseif ($lastComma !== false && $lastDot === false) {
            // Solo tiene comas, ver si es para decimales
            $parts = explode(',', $clean);
            if (count($parts) == 2 && strlen($parts[1]) <= 2) { // Formato europeo: 1234,56 
                $clean = str_replace(',', '.', $clean);
            } else { // Podría ser solo para miles 
                $clean = str_replace(',', '', $clean);
            }
        } else { // Formato normal, solo quitar comas
            $clean = str_replace(',', '', $clean);
        }
        return (float) $clean;
    } /** * Limpiar número (sin conversión de decimales) */


    private function cleanNumber($str)
    {
        $clean = trim(str_replace([' ', ','], '', $str));
        return (float) $clean;
    } /** * Refinar NITs */

    private function refineNits($text, &$header)
    { // Buscar todos los NITs/RUCs/RFCs en el texto
        $identifiers = []; // Patrones generales para identificadores fiscales
        $patterns = [
            '/(?:NIT|RUC|RFC|CNPJ)[:\s]*([\d\-\.\/A-Z]+)/i',
            '/(\d{6,}\-\d)/', //Formato El Salvador: 123456-7
            '/(\d{8,})/', // Formato Guatemala: 12345678
        ];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    if (strlen($match) > 4) {
                        $identifiers[] = trim($match);
                    }
                }
            }
        }

        $identifiers = array_unique($identifiers);

        // Asignar al emisor y receptor
        if (empty($header['nit_emisor']) && count($identifiers) > 0) {
            $header['nit_emisor'] = $identifiers[0];
        }

        if (empty($header['nit_receptor']) && count($identifiers) > 1) {
            $header['nit_receptor'] = $identifiers[1];
        } elseif (empty($header['nit_receptor']) && count($identifiers) > 0) {
            // Si solo hay uno, intentar determinar cuál es
            if (preg_match('/CELASA|DISTRIBUIDORA VOLANTIS/i', $text)) {
                $header['nit_receptor'] = $identifiers[0];
            }
        }
    }

    /**
     * Normalizar fecha
     */
    private function normalizeDate($dateStr)
    {
        if (!$dateStr)
            return null;

        // Mapeo de meses en español
        $meses = [
            'ene' => '01',
            'enero' => '01',
            'feb' => '02',
            'febrero' => '02',
            'mar' => '03',
            'marzo' => '03',
            'abr' => '04',
            'abril' => '04',
            'may' => '05',
            'mayo' => '05',
            'jun' => '06',
            'junio' => '06',
            'jul' => '07',
            'julio' => '07',
            'ago' => '08',
            'agosto' => '08',
            'sep' => '09',
            'septiembre' => '09',
            'oct' => '10',
            'octubre' => '10',
            'nov' => '11',
            'noviembre' => '11',
            'dic' => '12',
            'diciembre' => '12',
        ];

        // Formato: DD/MM/YYYY
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dateStr, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // Formato: YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateStr)) {
            return $dateStr;
        }

        // Formato: DD-MM-YYYY
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $dateStr, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // Formato con mes en texto: "8 de diciembre de 2025"
        $clean = strtolower($dateStr);
        $clean = preg_replace('/\s+/', ' ', $clean);

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

    /**
     * Detectar moneda
     */
    private function detectCurrency($text)
    {
        if (preg_match('/USD|D[ÓO]LARES|US\$|\$/i', $text)) {
            return 'USD';
        }

        if (preg_match('/GTQ|Quetzal/i', $text)) {
            return 'GTQ';
        }

        if (preg_match('/MXN|Peso mexicano/i', $text)) {
            return 'MXN';
        }

        if (preg_match('/BRL|Real/i', $text)) {
            return 'BRL';
        }

        return 'USD'; // Por defecto
    }

    /**
     * Ejecutar OCR si está disponible
     */
    private function runOcr($filePath)
    {
        if (class_exists('Thiagoalessio\TesseractOCR\TesseractOCR')) {
            try {
                return (new \Thiagoalessio\TesseractOCR\TesseractOCR($filePath))
                    ->lang('spa', 'eng', 'por') // Español, inglés, portugués
                    ->run();
            } catch (\Exception $e) {
                log_message('error', "OCR failed: " . $e->getMessage());
            }
        }
        return "";
    }
}