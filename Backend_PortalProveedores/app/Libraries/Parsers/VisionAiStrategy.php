<?php

namespace App\Libraries\Parsers;

class VisionAiStrategy implements InvoiceParserInterface
{
    private $apiKey;
    private $apiUrl;
    private $model;
    private $lastAiError = null;
    private $providerCode = null;
    private $rawText = null;

    public function __construct()
    {
        // OpenAI config
        $this->apiKey = env('AI_API_KEY');
        // Usamos el endpoint de openai estándar
        $this->apiUrl = env('AI_API_URL') ?: 'https://api.openai.com/v1/chat/completions';
        // Se recomienda gpt-4o o gpt-4o-mini para multimodal
        $this->model = env('AI_MODEL') ?: 'gpt-4o'; 
    }

    public function setProviderCode(?string $code): void
    {
        $this->providerCode = $code;
    }

    public function setRawText(string $text): void
    {
        $this->rawText = $text;
    }

    public function canParse(string $text): bool
    {
        // Esta estrategia puede parsear CUALQUIER PDF porque lo renderiza a imagen.
        return true;
    }

    public function parse(string $filePath): array
    {
        log_message('info', "Iniciando VisionAiStrategy para: " . basename($filePath));
        
        // 1. Convertir PDF a PNGs
        $imagesBase64 = $this->convertPdfToImages($filePath);
        if (empty($imagesBase64)) {
            return [['error' => 'No se pudo renderizar el PDF a imagen.']];
        }

        // 2. Llamada a la API Visual
        $aiResponse = $this->callVisionApi($imagesBase64);

        if (!$aiResponse || !isset($aiResponse['items'])) {
            $err = $this->lastAiError ?? 'La IA no devolvió un JSON válido estructurado.';
            log_message('error', "VisionAiStrategy Error: " . $err);
            return [['error' => "Error en procesamiento visual: $err"]];
        }

        // Devuelve el objeto como un array de facturas (aunque sea 1 sola)
        // ya que el controlador espera un array de facturas.
        return [$aiResponse];
    }

    /**
     * Convierte cada página del PDF en una imagen PNG y la codifica en Base64.
     * Requiere Ghostscript instalado en el servidor.
     */
    private function convertPdfToImages(string $filePath): array
    {
        $imagesBase64 = [];
        $tempDir = WRITEPATH . 'temp_vision_' . uniqid() . '/';
        
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        try {
            $os = php_uname('s');
            $isWin = str_contains(strtoupper($os), 'WIN');
            $gsPath = $isWin ? 'gswin64c' : 'gs';

            // Resolución de 300 DPI recomendada para lectura de IA
            $imagePattern = $tempDir . 'page_%d.png';
            $gsCmd = "\"$gsPath\" -dNOPAUSE -dBATCH -sDEVICE=png16m -r300 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=\"$imagePattern\" \"$filePath\" 2>&1";

            exec($gsCmd, $gsOutput, $gsReturn);

            if ($gsReturn !== 0) {
                log_message('error', "Ghostscript failed en VisionAiStrategy (Code $gsReturn): " . implode("\n", $gsOutput));
                return [];
            }

            $images = glob($tempDir . "page_*.png");
            natsort($images);

            // RUN OCR FALLBACK ONLY IF PDF TEXT LAYER IS MISSING OR CORRUPTED
            if (empty($this->rawText) || strlen(trim($this->rawText)) < 100) {
                log_message('warning', "VisionAiStrategy: Raw text is missing or too short, running Tesseract OCR fallback.");
                $ocrText = "";
                $tesseractPath = $isWin ? 'C:\Program Files\Tesseract-OCR\tesseract.exe' : '/usr/bin/tesseract';
            
            foreach ($images as $img) {
                try {
                    $ocr = new \thiagoalessio\TesseractOCR\TesseractOCR($img);
                    if (file_exists($tesseractPath)) {
                        $ocr->executable($tesseractPath);
                    }
                    $ocr->lang('spa', 'eng');
                    $ocr->psm(6); // Asumir un solo bloque de texto uniforme (mejor para mantener la estructura de la tabla)
                    $ocrText .= $ocr->run() . "\n--- PAGE SEPARATOR ---\n";
                } catch (\Throwable $e) {
                    log_message('error', "Tesseract OCR Error: " . $e->getMessage());
                }
            }
            
                if (strlen(trim($ocrText)) > 50) {
                    $this->rawText = $ocrText;
                    log_message('info', "Tesseract OCR successful. Extracted " . strlen($ocrText) . " characters.");
                }
            }

            foreach ($images as $img) {
                $imageData = file_get_contents($img);
                if ($imageData) {
                    $base64 = base64_encode($imageData);
                    $imagesBase64[] = "data:image/png;base64," . $base64;
                }
            }

        } catch (\Throwable $e) {
            log_message('error', "Error renderizando PDF: " . $e->getMessage());
        } finally {
            $files = glob($tempDir . "*");
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($tempDir);
        }

        return $imagesBase64;
    }

    private function identifyProviderCategory(string $text): string
    {
        $textUpper = strtoupper($text);

        // --- HINTS BY DB CODE ---
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
        if (str_contains($textUpper, 'DURMAN') || str_contains($textUpper, 'DURECO')) return 'Durman';
        if (str_contains($textUpper, 'LUXLITE') || str_contains($textUpper, 'ILUMINACION CONTINENTAL')) return 'Luxlite';
        if (str_contains($textUpper, 'AQUASISTEMAS')) return 'Aquasistemas';
        if (str_contains($textUpper, 'FABRITUB')) return 'Fabritub';
        if (str_contains($textUpper, 'TOOLCRAFT')) return 'Toolcraft';

        return 'default';
    }

    private function callVisionApi(array $imagesBase64)
    {
        if (empty($this->apiKey)) {
            $this->lastAiError = "API Key no configurada.";
            return null;
        }

        $providerInstructions = "";
        $identifiedCategory = $this->identifyProviderCategory($this->rawText ?? '');
        
        if ($identifiedCategory && $identifiedCategory !== 'default') {
            $promptsDir = APPPATH . 'Prompts/providers/';
            $providerFile = $promptsDir . $identifiedCategory . '.md';
            if (file_exists($providerFile)) {
                $providerInstructions = "\n\nREGLAS ESPECÍFICAS PARA ESTE PROVEEDOR:\n" . file_get_contents($providerFile);
                log_message('info', "Inyectando reglas del proveedor: " . $identifiedCategory);
            }
        }

        $systemPrompt = <<<EOT
Eres un experto extrayendo datos de facturas financieras.
Analiza las imágenes proporcionadas (páginas de una factura) y extrae la información en un único objeto JSON estructurado.

$providerInstructions

REGLAS CRÍTICAS PARA TABLAS (ITEMS):
1. LECTURA FILA POR FILA: Lee la tabla de detalles de izquierda a derecha, línea por línea. NUNCA mezcles la cantidad de una fila con el precio o código de otra fila. 
2. NO RESUMAS NI OMITAS: Extrae absolutamente TODAS las filas de la factura, sin importar cuántas sean. 
3. EXTRACCIÓN LITERAL: El código y la descripción deben ser idénticos a los de la imagen. 
4. REGLA MATEMÁTICA (CRÍTICA): Para cada fila, el 'importe' DEBE ser estrictamente el resultado de (cantidad * valorUnitario). NUNCA sumes impuestos ni restes descuentos al 'importe'. Extrae los impuestos y descuentos ÚNICAMENTE en sus campos respectivos ('montoImpuesto' y 'montoDescuento').
REGLAS DE FORMATO:
1. Extrae los montos numéricos EXACTAMENTE como aparecen en el texto crudo.
2. ELIMINA símbolos de moneda ($, Q, etc) y comas de miles. Usa punto para decimales (ej. 1,506.20 -> 1506.20).
3. Si un campo no existe en la factura, déjalo como null o cadena vacía.

Debes responder ÚNICAMENTE con el siguiente esquema JSON:
{
  "verificacion_matematica_previa": "string (OBLIGATORIO: Escribe paso a paso la lectura de la tabla de izquierda a derecha. Ejemplo: 'Fila 1: 48 * 35.71 = 1714.08. Fila 2: 700 * 16.13 = 11291.00...')",
  "fecha": "YYYY-MM-DD",
  "uuid": "string (identificador fiscal único)",
  "serie": "string (código alfanumérico, ej: C5004D18. CRÍTICO: Distingue siempre O-letra de 0-cero. En secuencias numéricas usa SIEMPRE el dígito 0)",
  "numero": "string (número de documento, puede contener letras y dígitos. CRÍTICO: Distingue siempre O-letra de 0-cero. Si una 'O' está rodeada de dígitos, es el dígito 0)",
  "moneda": "string (USD, GTQ, MXN)",
  "tipo_cambio": float,
  "subtotal": float,
  "total_impuestos": float,
  "total_descuento": float,
  "total": float,
  "nit_emisor": "string",
  "nombre_emisor": "string",
  "nit_receptor": "string",
  "nombre_receptor": "string",
  "no_pedido": "string",
  "items": [
    {
      "codigo": "string",
      "descripcion": "string",
      "cantidad": float,
      "unidadMedida": "string",
      "valorUnitario": float,
      "importe": float,
      "montoImpuesto": float,
      "montoDescuento": float,
      "tipoBienServicio": "Bien o Servicio",
      "oc_detalle": "string",
      "alerta_codigo_dudoso": boolean
    }
  ]
}
EOT;

        $content = [
            [
                "type" => "text",
                "text" => "Extrae los datos de esta factura según las instrucciones del sistema. Tienes dos fuentes de información: la imagen y el texto crudo. Utiliza el texto crudo para garantizar que extraes los números y dígitos exactos, PERO si notas que el texto crudo tiene errores evidentes de lectura (ej. letras mezcladas como 'nel Rael' en lugar de 'MLED', o '890.04' en lugar de '890.00'), CONFÍA EN LA IMAGEN para corregirlos.\n\nADVERTENCIA DE CÓDIGOS (alerta_codigo_dudoso): Si tienes que corregir un código usando la imagen porque el texto crudo del OCR contenía basura o era ilegible, o si tienes dudas de que el código extraído sea correcto, debes marcar 'alerta_codigo_dudoso' como true para ese ítem.\n\nTEXTO CRUDO EXTRAÍDO:\n" . ($this->rawText ?? 'No disponible')
            ]
        ];

        foreach ($imagesBase64 as $b64) {
            $content[] = [
                "type" => "image_url",
                "image_url" => [
                    "url" => $b64,
                    "detail" => "high"
                ]
            ];
        }

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $content]
            ],
            'temperature' => 0.0, // Cero para máxima precisión
            'response_format' => ['type' => 'json_object'] // Structured Outputs
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . trim($this->apiKey),
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || !$result) {
            $this->lastAiError = "API Fail: HTTP $httpCode | $curlError";
            log_message('error', $this->lastAiError);
            log_message('error', "Result: " . print_r($result, true));
            return null;
        }

        $body = json_decode($result, true);

        if (isset($body['error'])) {
            $this->lastAiError = "OpenAI Error: " . ($body['error']['message'] ?? 'Unknown error');
            return null;
        }

        if (isset($body['choices'][0]['message']['content'])) {
            $jsonResponse = $body['choices'][0]['message']['content'];
            log_message('info', "Respuesta Vision API: " . $jsonResponse);
            $parsed = json_decode($jsonResponse, true);
            if (!$parsed) {
                $this->lastAiError = "JSON inválido devuelto por la IA";
                return null;
            }
            // Post-proceso: normalizar serie, numero y UUID corrigiendo confusión OCR
            if (isset($parsed['serie'])) {
                $parsed['serie'] = $this->normalizeAlphanumericCode($parsed['serie']);
            }
            if (isset($parsed['numero'])) {
                $parsed['numero'] = $this->normalizeAlphanumericCode($parsed['numero']);
            }
            if (isset($parsed['uuid'])) {
                $parsed['uuid'] = $this->normalizeUuid($parsed['uuid']);
            }
            // Validación de serie vs UUID (DTE Guatemala):
            // En facturas DTE, la serie es el primer segmento del UUID (8 chars).
            // Si la IA extrajo una serie truncada (ej: 'C34DA' en vez de 'C34DA1EA'),
            // la corregimos extrayéndola del primer segmento del UUID normalizado.
            if (!empty($parsed['uuid']) && !empty($parsed['serie'])) {
                $uuidSegments = explode('-', $parsed['uuid']);
                $firstSegment = strtoupper($uuidSegments[0] ?? '');
                $serieUpper   = strtoupper($parsed['serie']);
                if (
                    strlen($firstSegment) > strlen($serieUpper) &&
                    str_starts_with($firstSegment, $serieUpper)
                ) {
                    log_message('info', "VisionAi: Serie truncada corregida desde UUID: '{$parsed['serie']}' → '{$firstSegment}'");
                    $parsed['serie'] = $firstSegment;
                }
            }
            // Normalizar códigos de producto en los ítems (Strategy 1: adyacencia a dígito)
            // Es la más segura para códigos mixtos (ej: 6O305 → 60305)
            if (!empty($parsed['items']) && is_array($parsed['items'])) {
                foreach ($parsed['items'] as &$item) {
                    if (!empty($item['codigo'])) {
                        $item['codigo'] = $this->normalizeAlphanumericCode($item['codigo']);
                    }
                }
                unset($item); // Romper la referencia
            }
            // Fallback rawText: se activa si no_pedido está vacío O si tiene un valor que
            // no se parece a un OC válido (formato esperado: VOL/CEL-\d{4+} o \d{5+} puro).
            $noPedidoVal = trim($parsed['no_pedido'] ?? '');
            $noPedidoValido = !empty($noPedidoVal) && (
                preg_match('/^(VOL|CEL)-\d{4,}$/i', $noPedidoVal) ||   // VOL-208320
                preg_match('/^\d{5,}$/', $noPedidoVal)                   // 208320 sin prefijo
            );
            if (!$noPedidoValido && !empty($this->rawText)) {
                // Verificar cuántas OCs únicas hay en el texto crudo
                preg_match_all('/\b((?:VOL|CEL)[-\s]?\d{4,})\b/i', $this->rawText, $allOcs);
                $uniqueOcs = array_unique(array_map('strtoupper', $allOcs[1] ?? []));
                
                if (count($uniqueOcs) > 1) {
                    log_message('info', "VisionAi fallback abortado: Se encontraron múltiples OCs distintas en el texto.");
                    $parsed['no_pedido'] = null;
                } else {
                    // Estrategia A: buscar explícitamente después de "Observaciones: VOL-..."
                    if (preg_match('/observaci[o\xf3]n(?:es)?\s*[:]\s*((VOL|CEL)[-\s]?\d{4,})/i', $this->rawText, $matches)) {
                        $parsed['no_pedido'] = trim(str_replace(' ', '-', $matches[1]));
                        log_message('info', "VisionAi fallback (Observaciones): no_pedido = " . $parsed['no_pedido']);
                    }
                    // Estrategia B: cualquier VOL-NNNNNN en el texto (≥5 dígitos consecutivos)
                    elseif (preg_match('/\b((?:VOL|CEL)-\d{5,})\b/i', $this->rawText, $matches)) {
                        $parsed['no_pedido'] = strtoupper($matches[1]);
                        log_message('info', "VisionAi fallback (amplio): no_pedido = " . $parsed['no_pedido']);
                    } else {
                        // No se encontró un OC válido → limpiar el valor basura
                        if (!empty($noPedidoVal)) {
                            log_message('info', "VisionAi: no_pedido basura descartado: '$noPedidoVal'");
                        }
                        $parsed['no_pedido'] = null;
                    }
                }
            }
            return $parsed;
        }

        return null;
    }

    /**
     * Normaliza un código alfanumérico corrigiendo la confusión OCR entre la letra 'O' y el dígito '0'.
     *
     * Estrategia 1 (Adyacencia a dígito):
     *   Si una 'O' está directamente al lado de un dígito, se reemplaza por '0'.
     *   Ej: C5OO4D18 → C5004D18
     *
     * Estrategia 2 (Detección de patrón Hex):
     *   Aplica correcciones OCR estándar (O→0, S→5, I→1, Z→2) de forma tentativa.
     *   Si el resultado es una cadena puramente hexadecimal (solo 0-9, A-F) de 6+ chars,
     *   es casi seguro que el código ENTERO es un hash/UUID-segment y se aplican las correcciones.
     *   Esto captura casos como 7COCFB95 (O entre dos letras C) → 7C0CFB95.
     *
     * Ejemplos:
     *   7COCFB95   → 7C0CFB95  (serie DTE Guatemala - segmento hex del UUID)
     *   C5OO4D18   → C5004D18
     *   1139O9665  → 113909665
     *   VOLVO      → VOLVO     (no cambia, V no es hex, no aplica estrategia 2)
     *   TECNOLITE  → TECNOLITE (no cambia, T/N no son hex)
     */
    private function normalizeAlphanumericCode(string $value): string
    {
        if (empty($value)) return $value;

        // --- Estrategia 1: Adyacencia a dígito ---
        $prev = null;
        while ($prev !== $value) {
            $prev = $value;
            $value = preg_replace('/(?<=\d)[Oo]|[Oo](?=\d)/', '0', $value);
        }

        // --- Estrategia 2: Detección de patrón Hexadecimal ---
        // Aplicar correcciones tentativas de OCR para hex
        $candidate = $value;
        $candidate = preg_replace('/[OoQUu]/', '0', $candidate); // O, o, Q, U → 0
        $candidate = preg_replace('/[Ss]/',    '5', $candidate); // S, s → 5
        $candidate = preg_replace('/[Ii]/',    '1', $candidate); // I, i → 1
        $candidate = preg_replace('/[Zz]/',    '2', $candidate); // Z, z → 2

        // Si el candidato corregido es hex puro y tiene 6+ chars, aplicar las correcciones
        if (strlen($candidate) >= 6 && preg_match('/^[0-9A-Fa-f]+$/i', $candidate)) {
            log_message('info', "normalizeAlphanumericCode: Patrón HEX detectado. '{$value}' → '{$candidate}'");
            return strtoupper($candidate);
        }

        return $value;
    }

    /**
     * Normaliza el UUID de una factura aplicando las correcciones OCR estándar para hex.
     * UUID siempre es hex puro con guiones (formato 8-4-4-4-12).
     * Correcciones: O→0, S→5, I→1, Z→2, G→6, T→7
     *
     * Ejemplo:
     *   7C0CFB95-1B8B-4A0E-9934-GE3F7A9A70CB  →  7C0CFB95-1B8B-4A0E-9934-6E3F7A9A70CB
     */
    private function normalizeUuid(string $value): string
    {
        if (empty($value)) return $value;

        // Mapa completo de correcciones OCR para hex
        $from = ['O', 'o', 'Q', 'U', 'u', 'S', 's', 'I', 'l', 'i', 'Z', 'z', 'G', 'g', 'T', 't'];
        $to   = ['0', '0', '0', '0', '0', '5', '5', '1', '1', '1', '2', '2', '6', '6', '7', '7'];

        // Quitar guíones para trabajar con la cadena cruda
        $clean = str_replace('-', '', $value);
        $clean = str_replace($from, $to, $clean);
        $clean = strtoupper($clean);

        // Si es hex puro de 32 chars, reformat como 8-4-4-4-12
        if (strlen($clean) === 32 && preg_match('/^[0-9A-F]+$/', $clean)) {
            $formatted = implode('-', [
                substr($clean, 0,  8),
                substr($clean, 8,  4),
                substr($clean, 12, 4),
                substr($clean, 16, 4),
                substr($clean, 20, 12)
            ]);
            log_message('info', "normalizeUuid: '{$value}' → '{$formatted}'");
            return $formatted;
        }

        // Si el UUID no tiene 32 chars (DTE GT puede tener formato diferente),
        // aplicar correcciones in-place preservando guíones
        $result = strtoupper(str_replace($from, $to, $value));
        log_message('info', "normalizeUuid (in-place): '{$value}' → '{$result}'");
        return $result;
    }
}
