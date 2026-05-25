<?php

namespace App\Libraries\Parsers;

class CloudAiStrategy implements InvoiceParserInterface
{
    private $apiKey;
    private $apiUrl;
    private $model;
    private $lastAiError = null;
    private $providerCode = null;
    private $identifiedCategory = null;

    public function __construct()
    {
        // Load from .env or config
        $this->apiKey = getenv('AI_API_KEY');
        $this->apiUrl = getenv('AI_API_URL') ?: 'https://api.groq.com/openai/v1/chat/completions';
        $this->model = getenv('AI_MODEL') ?: 'llama3-8b-8192';
    }

    /**
     * Set the numeric provider code to load specific prompt instructions.
     */
    public function setProviderCode(?string $code): void
    {
        $this->providerCode = $code;
    }

    public function canParse(string $text): bool
    {
        return true;
    }

    public function parse(string $filePath): array
    {
        $pages = $this->extractTextByPages($filePath);
        if (empty($pages)) {
            return [['error' => 'PDF text too short or empty (OCR required but not available)']];
        }

        $allInvoices = [];
        $totalPages = count($pages);

        // Process with chunks of 3 pages with 1-page overlap (1-2-3, 3-4-5, 5-6-7...)
        for ($i = 0; $i < $totalPages; $i += 2) {
            $chunkPages = [$pages[$i]];
            if ($i + 1 < $totalPages) {
                $chunkPages[] = $pages[$i + 1];
            }
            if ($i + 2 < $totalPages) {
                $chunkPages[] = $pages[$i + 2];
            }

            $currentRange = ($i + 1) . "-" . min($i + 3, $totalPages);
            $chunkText = implode("\n--- PAGE SEPARATOR ---\n", $chunkPages);

            if (strlen(trim($chunkText)) < 30)
                continue;

            log_message('error', "AI Processing Chunk (Pages $currentRange): " . basename($filePath) . " | Provider: " . ($this->providerCode ?? 'Default'));
            $aiResponse = $this->callAiApi($chunkText);

            if ($aiResponse && isset($aiResponse['invoices'])) {
                foreach ($aiResponse['invoices'] as $invoiceData) {
                    $mapped = $this->mapResponseToStructure($invoiceData);
                    $allInvoices[] = $mapped;
                }
            } else {
                log_message('error', "AI fail on pages $currentRange of $filePath");
            }

            if ($i + 3 >= $totalPages)
                break;

            usleep(300000);
        }

        if (empty($allInvoices)) {
            $err = $this->lastAiError ?? 'No se detectaron facturas en ninguna página';
            log_message('error', "CloudAiStrategy: Extraction failed for " . basename($filePath) . " - Detail: $err");
            return [['error' => "No se pudo extraer información: $err"]];
        }

        return $this->consolidateInvoices($allInvoices);
    }

    private function consolidateInvoices(array $invoices): array
    {
        $consolidated = [];

        foreach ($invoices as $inv) {
            $id = $inv['uuid'] ?? ($inv['serie'] . '_' . $inv['numero']);

            if (empty(trim($id, '_ '))) {
                $consolidated[] = $inv;
                continue;
            }

            if (!isset($consolidated[$id])) {
                $consolidated[$id] = $inv;
            } else {
                if (!empty($inv['items'])) {
                    foreach ($inv['items'] as $newItem) {
                        $consolidated[$id]['items'][] = $newItem;
                    }
                }

                foreach ($inv as $key => $val) {
                    if ($key !== 'items' && (empty($consolidated[$id][$key]) || $consolidated[$id][$key] === 'Unknown') && !empty($val)) {
                        $consolidated[$id][$key] = $val;
                    }
                }
            }
        }

        foreach ($consolidated as &$finalInv) {
            $globalOc = $finalInv['no_pedido'] ?? null;
            if (!empty($globalOc)) {
                foreach ($finalInv['items'] as &$item) {
                    if (empty($item['oc_detalle'])) {
                        $item['oc_detalle'] = $globalOc;
                    }
                }
            }
        }

        return array_values($consolidated);
    }

    private function extractTextByPages($filePath): array
    {
        $pagesText = [];

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            $pages = $pdf->getPages();

            foreach ($pages as $page) {
                $pagesText[] = $page->getText();
            }
        } catch (\Exception $e) {
            log_message('error', "PDF Parser failed: " . $e->getMessage());
        }

        $totalText = implode("", $pagesText);
        $hasViakon = str_contains(strtoupper($totalText), 'VIAKON');
        $hasItems = str_contains(strtoupper($totalText), 'VALOR UNITARIO') || str_contains(strtoupper($totalText), 'IMPORTE');

        if (strlen(trim($totalText)) < 100 || ($hasViakon && !$hasItems)) {
            $reason = (strlen(trim($totalText)) < 100) ? "short text" : "Viakon hybrid image layer";
            log_message('warning', "Triggering OCR fallback (Reason: $reason): " . basename($filePath));

            $ocrText = $this->runOcr($filePath);
            if (strlen(trim($ocrText)) > 50) {
                $ocrPages = explode("\n--- PAGE SEPARATOR ---\n", $ocrText);
                return array_values(array_filter($ocrPages, function ($p) {
                    return strlen(trim($p)) > 20;
                }));
            }
        }

        return $pagesText;
    }

    private function runOcr($filePath): string
    {
        $tempDir = WRITEPATH . 'temp_ocr_' . uniqid() . '/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $fullOcrText = "";

        try {
            $os = php_uname('s');
            $isWin = str_contains(strtoupper($os), 'WIN');

            $gsPath = $isWin ? 'gswin64c' : 'gs';
            $tesseractPath = $isWin
                ? 'C:\Program Files\Tesseract-OCR\tesseract.exe'
                : '/usr/bin/tesseract';

            $imagePattern = $tempDir . 'page_%d.png';
            $gsCmd = "\"$gsPath\" -dNOPAUSE -dBATCH -sDEVICE=pnggray -r400 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=\"$imagePattern\" \"$filePath\" 2>&1";

            exec($gsCmd, $gsOutput, $gsReturn);

            if ($gsReturn !== 0) {
                log_message('error', "Ghostscript failed (Code $gsReturn): " . implode("\n", $gsOutput));
                return "";
            }

            $images = glob($tempDir . "page_*.png");
            natsort($images);

            foreach ($images as $img) {
                $ocr = new \thiagoalessio\TesseractOCR\TesseractOCR($img);
                if (file_exists($tesseractPath)) {
                    $ocr->executable($tesseractPath);
                }
                $ocr->lang('spa', 'eng');
                $ocr->psm(1);
                $fullOcrText .= $ocr->run() . "\n--- PAGE SEPARATOR ---\n";
            }

        } catch (\Throwable $e) {
            log_message('error', "OCR Process Error: " . $e->getMessage());
        } finally {
            $files = glob($tempDir . "*");
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($tempDir);
        }

        return $fullOcrText;
    }

    private function getPrompt(string $invoiceText): string
    {
        $promptsDir = APPPATH . 'Prompts/';
        $basePrompt = @file_get_contents($promptsDir . 'base_invoice_prompt.md') ?: '### ROLE\nYou are a precise invoice parser.';
        
        // DYNAMIC IDENTIFICATION: Identify the provider category from the text
        $this->identifiedCategory = $this->identifyProviderCategory($invoiceText);
        $identifiedCategory = $this->identifiedCategory;
        
        $providerSnippet = '';
        $providerFile = $promptsDir . 'providers/' . $identifiedCategory . '.md';
        
        if (file_exists($providerFile)) {
            $providerSnippet = file_get_contents($providerFile);
            log_message('error', "[AI-Router] Identified category: $identifiedCategory. Loading rules.");
        } else {
            $providerSnippet = @file_get_contents($promptsDir . 'providers/default.md') ?: '';
            log_message('error', "[AI-Router] No specialized rules for $identifiedCategory, using default.");
        }

        return $basePrompt . "\n\n" . $providerSnippet . "\n\n### INSTRUCTIONS\n- **Credit Days (dias_credito)**: Extract the number found in **\"Plazo del Crédito\"** (e.g., \"60\" -> 60).\n- **Incoterm (termino_compra)**: In the **\"Memo\"** section, look for the word after **\"INCOTERM\"** (e.g., \"INCOTERM CIP\" -> \"CIP\").\n\n### INVOICE TEXT:\n" . $invoiceText;
    }

    /**
     * Scans the providers directory and asks the AI to classify the invoice
     */
    private function identifyProviderCategory(string $text): string
    {
        $textUpper = strtoupper($text);

        // --- HINTS BY CODE ---
        if ($this->providerCode == '7') return 'Sylvania';
        if ($this->providerCode == '666' || $this->providerCode == '5810') return 'Eagle';
        if ($this->providerCode == '11673') return 'Tecnolite';
        if ($this->providerCode == '11295') return 'Nacel';

        // --- HEURISTICS (FAST & RELIABLE) ---
        if (str_contains($textUpper, 'SYLVANIA')) return 'Sylvania';
        if (str_contains($textUpper, 'EAGLE ELECTRIC')) return 'Eagle';
        if (str_contains($textUpper, 'VIAKON')) return 'Viakon';
        if (str_contains($textUpper, 'ZENER')) return 'Zener';
        if (str_contains($textUpper, 'TECNOLITE')) return 'Tecnolite';
        if (str_contains($textUpper, 'NACEL') || str_contains($textUpper, 'CONDUMEX')) return 'Nacel';
        if (str_contains($textUpper, 'MEXICHEM') || str_contains($textUpper, 'AMANCO')) return 'Amanco';
        if (str_contains($textUpper, 'LUXLITE') || str_contains($textUpper, 'ILUMINACION CONTINENTAL')) return 'Luxlite';
        if (str_contains($textUpper, 'AQUASISTEMAS')) return 'Aquasistemas';

        // --- AI ROUTER (FLEXIBLE) ---
        $promptsDir = APPPATH . 'Prompts/providers/';
        $files = glob($promptsDir . '*.md');
        $categories = [];
        foreach ($files as $file) {
            $name = basename($file, '.md');
            if ($name !== 'default' && !str_ends_with($name, '_TEMPLATE')) {
                $categories[] = $name;
            }
        }

        if (empty($categories)) return 'default';

        $categoriesList = implode(', ', $categories);
        $routerPrompt = "Analyze the following invoice text and determine which provider category it belongs to. 
        Available categories: [$categoriesList].
        If it does not clearly belong to any, respond exactly with 'default'.
        Respond ONLY with the category name.

        INVOICE TEXT (FIRST 2000 CHARS):
        " . substr($text, 0, 2000);

        $identified = $this->callAiApiForClassification($routerPrompt);
        log_message('error', "[AI-Router] AI classified as: " . $identified);
        
        return $identified;
    }

    /**
     * Simplified AI call for classification
     */
    private function callAiApiForClassification(string $prompt): string
    {
        $apiKey = getenv('OPENAI_API_KEY');
        if (!$apiKey) return 'default';

        $payload = json_encode([
            'model' => 'gpt-4o-mini', 
            'messages' => [
                ['role' => 'system', 'content' => 'You are a provider classifier. Respond only with the name.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.1,
            'max_tokens' => 20
        ]);

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: ' . 'Bearer ' . $apiKey
        ]);

        $result = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($result, true);
        $content = trim($response['choices'][0]['message']['content'] ?? 'default');
        
        return preg_replace('/[^a-zA-Z0-9_]/', '', $content);
    }

    private function callAiApi($invoiceText)
    {
        $safeText = $invoiceText;

        $prompt = $this->getPrompt($safeText);

        if (empty($this->apiKey)) {
            echo "[DEBUG] API Key is empty!\n";
            return null;
        }

        $payload = json_encode([
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a precise JSON extraction engine (Construction Materials). Output JSON only with structure: {"invoices": [{"fecha": "YYYY-MM-DD", "serie": "string", "numero": "string", "uuid": "string", "moneda": "USD|GTQ", "tipo_cambio": float, "subtotal": float, "total_impuestos": float, "total": float, "nit_emisor": "string", "nombre_emisor": "string", "nombre_receptor": "string", "no_pedido": "string", "memo": "string", "dias_credito": integer, "bultos": integer, "items": [{"descripcion": "string", "cantidad": float, "unidadMedida": "string", "valorUnitario": float, "importe": float, "montoImpuesto": float, "tipoBienServicio": "string", "codigo": "string", "oc_detalle": "string"}]}]}'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.1,
            'response_format' => ['type' => 'json_object']
        ]);

        $maxRetries = 3;
        $retryCount = 0;
        $result = null;

        while ($retryCount < $maxRetries) {
            $ch = curl_init($this->apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . trim($this->apiKey),
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload)
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 429) {
                $retryCount++;
                $waitTime = pow(2, $retryCount);
                log_message('warning', "AI Rate Limit (429). Retrying in {$waitTime}s... (Attempt $retryCount)");
                sleep($waitTime);
                continue;
            }

            if ($httpCode !== 200 || !$result) {
                $this->lastAiError = "API Fail: HTTP $httpCode | $curlError";
                return null;
            }

            break;
        }

        $body = json_decode($result, true);

        if (isset($body['error'])) {
            $this->lastAiError = "OpenAI Error: " . ($body['error']['message'] ?? 'Unknown error');
            return null;
        }

        if (isset($body['choices'][0]['message'])) {
            $message = $body['choices'][0]['message'];
            if (isset($message['refusal']) && $message['refusal'] !== null) {
                $this->lastAiError = "AI Refusal: " . $message['refusal'];
                return null;
            }
            if (isset($message['content'])) {
                $content = $message['content'];
                log_message('error', "[AI-Response] Raw JSON: " . $content);
                $parsed = json_decode($content, true);
                if (!$parsed) {
                    $this->lastAiError = "JSON inválido de la IA";
                    return null;
                }
                return $parsed;
            }
        }

        return null;
    }

    private function mapResponseToStructure($aiData)
    {
        $items = $aiData['items'] ?? [];
        $noPedidoGeneral = $aiData['no_pedido'] ?? null;
        
        $emisor = strtoupper($aiData['nombre_emisor'] ?? '');
        $isCondumex = str_contains($emisor, 'CONDUMEX');
        $tc = isset($aiData['tipo_cambio']) ? (float)str_replace([' ', ','], '', (string)$aiData['tipo_cambio']) : 1.0;

        // 1. First pass: Sanitize and find a candidate for general OC if header is empty
        foreach ($items as &$item) {
            $normalize = function ($val) {
                if (!is_string($val)) return (float) $val;
                $val = trim($val);
                if (empty($val)) return 0.0;

                // Handle Eagle/European format 1.234,56
                if (str_contains($val, '.') && str_contains($val, ',')) {
                    if (strrpos($val, '.') < strrpos($val, ',')) { // 1.234,56
                        return (float) str_replace([',', '.'], ['.', ''], $val);
                    } else { // 1,234.56
                        return (float) str_replace(',', '', $val);
                    }
                }

                // If it's a dot followed by exactly 3 digits (e.g., 1.008), it's likely a thousand separator
                if (preg_match('/^\d+\.\d{3}$/', $val)) {
                    return (float) str_replace('.', '', $val);
                }

                return (float) str_replace([' ', ','], '', $val);
            };

            // Sanitize numeric fields
            if (isset($item['cantidad'])) $item['cantidad'] = $normalize($item['cantidad']);
            if (isset($item['valorUnitario'])) $item['valorUnitario'] = $normalize($item['valorUnitario']);
            if (isset($item['importe'])) $item['importe'] = $normalize($item['importe']);
            if (isset($item['montoImpuesto'])) $item['montoImpuesto'] = $normalize($item['montoImpuesto']);

            // Conversion logic removed per user request: "no realizar calculos"

            $qty = $item['cantidad'] ?? 0;
            $price = $item['valorUnitario'] ?? 0;
            $total = $item['importe'] ?? 0;

            // Guard logic removed per user request: "no realizar ningun tipo de calculo"

            // Provider specific code cleaning
            $codigo = $item['codigo'] ?? null;
            $ocDetalle = $item['oc_detalle'] ?? null;
            $emisor = strtoupper($aiData['nombre_emisor'] ?? '');

            if (str_contains($emisor, 'LIGHT-TEC') || str_contains($emisor, 'TECNOLITE') || $this->providerCode == '11673') {
                // Regex for Z12-, ZI2-, Zl2- (OCR noise variations)
                if ($codigo && preg_match('/^Z[1IL]2-/i', $codigo)) {
                    $item['codigo'] = substr($codigo, 4);
                }
            }

            // Cleanup: If the code is too long and contains spaces, it's likely a misread description
            if (isset($item['codigo']) && strlen($item['codigo']) > 45 && str_contains($item['codigo'], ' ')) {
                $item['codigo'] = null;
            }

            if (str_contains($emisor, 'LUXITE') || str_contains($emisor, 'LUXLITE') || str_contains($emisor, 'ILUMINACION CONTINENTAL')) {
                if ($codigo) {
                    $cleanedCode = str_replace(['D', 'O', 'o'], '0', substr($codigo, 3));
                    $item['codigo'] = strtoupper(substr($codigo, 0, 3) . $cleanedCode);
                }
            }

            // Swap Guard: if AI put REF in code
            if ($codigo && (str_contains(strtoupper($codigo), 'REF') || str_contains(strtoupper($codigo), 'PO') || str_contains(strtoupper($codigo), 'CEL-'))) {
                if (!$ocDetalle) {
                    $item['oc_detalle'] = $codigo;
                    $item['codigo'] = null;
                }
            }

            if (empty($noPedidoGeneral) && !empty($item['oc_detalle'])) {
                if (!str_contains(strtoupper($item['oc_detalle']), 'CEL-')) {
                    $noPedidoGeneral = $item['oc_detalle'];
                }
            }
        }

        // Automatic recalculation removed per user request
        $hdrSubtotal = (float)($aiData['subtotal'] ?? 0);
        $hdrImpuestos = (float)($aiData['total_impuestos'] ?? 0);
        $hdrTotal = (float)($aiData['total'] ?? 0);

        $finalSerie = $aiData['serie'] ?? null;
        if ($finalSerie && preg_match('/^[0-9A-F]*O[0-9A-F]*$/i', $finalSerie)) {
            $finalSerie = str_ireplace('O', '0', $finalSerie);
        }

        $finalUuid = $aiData['uuid'] ?? ($aiData['documento_interno'] ?? null);
        if ($finalUuid) {
            $finalUuid = strtoupper((string)$finalUuid);
            // Replace common OCR mistakes for Hexadecimal strings
            $replaceMap = [
                'O' => '0', 'Q' => '0', 'U' => '0',
                'S' => '5',
                'I' => '1', 'L' => '1', 'T' => '7',
                'Z' => '2',
                'G' => '6',
                'Y' => '7'
            ];
            foreach ($replaceMap as $wrong => $right) {
                $finalUuid = str_replace($wrong, $right, $finalUuid);
            }
            // Strip any remaining invalid characters (keep hex and hyphen)
            $finalUuid = preg_replace('/[^0-9A-F\-]/', '', $finalUuid);
        }

        return [
            'fecha' => $aiData['fecha'] ?? null,
            'serie' => $finalSerie,
            'numero' => $aiData['numero'] ?? null,
            'uuid' => $finalUuid,
            'moneda' => $aiData['moneda'] ?? 'USD',
            'tipo_cambio' => $tc,
            'subtotal' => $hdrSubtotal,
            'total_impuestos' => $hdrImpuestos,
            'total' => $hdrTotal,
            'nit_emisor' => $aiData['nit_emisor'] ?? null,
            'nombre_emisor' => $aiData['nombre_emisor'] ?? 'Unknown',
            'nombre_receptor' => $aiData['nombre_receptor'] ?? 'Unknown',
            'no_pedido' => $noPedidoGeneral ?: null,
            'memo' => $aiData['memo'] ?? null,
            'dias_credito' => $aiData['dias_credito'] ?? null,
            'termino_compra' => $aiData['termino_compra'] ?? null,
            
            // DYNAMIC PROVIDER SWITCHING RULES
            'switch_provider' => $this->detectProviderSwitch($aiData),
            
            'items' => array_map(function ($item) use ($noPedidoGeneral) {
                return [
                    'descripcion' => $item['descripcion'] ?? 'Sin descripción',
                    'cantidad' => $item['cantidad'] ?? 0,
                    'unidadMedida' => $item['unidadMedida'] ?? null,
                    'valorUnitario' => $item['valorUnitario'] ?? 0,
                    'importe' => $item['importe'] ?? 0,
                    'montoImpuesto' => $item['montoImpuesto'] ?? 0,
                    'tipoBienServicio' => $item['tipoBienServicio'] ?? 'Bien',
                    'codigo' => $item['codigo'] ?? null,
                    'oc_detalle' => !empty($noPedidoGeneral) 
                        ? (string)$noPedidoGeneral 
                        : (!empty($item['oc_detalle']) ? (string)$item['oc_detalle'] : null)
                ];
            }, $items)
        ];
    }

    /**
     * Detects if an invoice should be reassigned to a different provider code
     * based on its content (Memo, Emisor, etc.)
     */
    private function detectProviderSwitch($aiData): ?string
    {
        $memo = strtoupper($aiData['memo'] ?? '');
        $currentCode = (string)$this->providerCode;

        // CASE 1: EAGLE -> Switch to 5810 if it's "Fuera de Area"
        // We now check if the emisor name contains EAGLE to be more generic
        $emisor = strtoupper($aiData['nombre_emisor'] ?? '');
        if (str_contains($emisor, 'EAGLE')) {
            if (str_contains($memo, 'FUERA DE AREA')) {
                return '5810';
            }
            if (str_contains($memo, 'COSTA RICA')) {
                return '666';
            }
        }

        // Add more cases here in the future:
        // if ($currentCode === 'OTHER_CODE') { ... }

        return null;
    }
}
