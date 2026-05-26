<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\InvoiceModel;
use App\Models\InvoiceItemModel;
use App\Models\ProviderModel;
use App\Models\UserModel;
use App\Libraries\XmlInvoiceParser;
use App\Libraries\InvoiceParserManager;

class InvoiceController extends BaseController
{
    use ResponseTrait;

    public function analyze()
    {
        set_time_limit(0);
        $json = $this->request->getJSON();
        $providerCode = $json->provider_code ?? null;
        $filePair = $json->file ?? null; // Expects a single pair object
        $userId = $this->request->getHeaderLine('X-User-Id') ?: 1;

        if (!$providerCode || !$filePair) {
            return $this->fail('Datos incompletos', 400);
        }

        $userModel = new UserModel();
        $userObj = $userModel->find($userId);
        if (!$userObj) return $this->failUnauthorized('Usuario no válido');

        $providerModel = new ProviderModel();
        $provider = null;
        if ($providerCode) {
            $provider = $providerModel->where('codigo_proveedor', $providerCode)->first();
        }
        if (!$provider && !empty($userObj['proveedor_id'])) {
            $provider = $providerModel->find($userObj['proveedor_id']);
        }
        if (!$provider) return $this->failNotFound('Proveedor no encontrado');

        $providerCode = $provider['codigo_proveedor'];
        $stagingPath = WRITEPATH . 'uploads/' . $providerCode . '/staging/';

        $xmlStored = $filePair->xml ?? null;
        $pdfStored = $filePair->pdf ?? null;
        $xmlOriginal = $filePair->xmlOriginal ?? $xmlStored;
        $pdfOriginal = $filePair->pdfOriginal ?? $pdfStored;

        $xmlPath = $xmlStored ? $stagingPath . $xmlStored : null;
        $pdfPath = $pdfStored ? $stagingPath . $pdfStored : null;

        $xmlParser = new XmlInvoiceParser();
        $pdfParser = new InvoiceParserManager();

        $invoicesFound = [];
        $errorMsg = null;
        $source = '';

        // 1. Try XML
        if ($xmlPath && file_exists($xmlPath)) {
            $parsedData = $xmlParser->parse($xmlPath);
            if ($parsedData) {
                $invoicesFound = [$parsedData];
                $source = 'xml';
            } else {
                $errorMsg = 'Fallo lectura XML';
            }
        }

        // 2. Try PDF
        if (empty($invoicesFound)) {
            if ($pdfPath && file_exists($pdfPath)) {
                $invoicesFound = $pdfParser->process($pdfPath, $providerCode);
                if ($invoicesFound && !isset($invoicesFound['error'])) {
                    $source = 'pdf';
                } else {
                    $errorMsg .= ($errorMsg ? ' y ' : '') . 'Fallo lectura PDF: ' . ($invoicesFound['error'] ?? 'Unknown');
                    $invoicesFound = [];
                }
            }
        }

        if (empty($invoicesFound)) {
            return $this->fail($errorMsg ?: 'No se pudo extraer información de los archivos', 422);
        }

        // Prepare data for frontend validation
        $response = [];
        foreach ($invoicesFound as $invoice) {
            // Basic data normalization
            $invoice['fuente_extraccion'] = $source;
            $invoice['xmlStored'] = $xmlStored;
            $invoice['pdfStored'] = $pdfStored;
            $invoice['xmlOriginal'] = $xmlOriginal;
            $invoice['pdfOriginal'] = $pdfOriginal;
            
            // Ensure fields exist for frontend
            $invoice['serie'] = $invoice['serie'] ?? '';
            $invoice['numero'] = $invoice['numero'] ?? ($invoice['folio'] ?? '');
            $invoice['fecha'] = !empty($invoice['fecha']) ? substr($invoice['fecha'], 0, 10) : date('Y-m-d');
            
            // Recolectar o validar OC de los items (Backfill global y Swap Guard V1)
            if (!empty($invoice['items'])) {
                foreach ($invoice['items'] as &$item) {
                    $codigo = $item['codigo'] ?? null;
                    $ocDetalle = $item['oc_detalle'] ?? null;
                    
                    // Swap Guard: Si la IA puso el PO/REF en el código por error
                    if ($codigo && (str_contains(strtoupper($codigo), 'REF') || str_contains(strtoupper($codigo), 'PO') || str_contains(strtoupper($codigo), 'CEL-') || str_contains(strtoupper($codigo), 'VOL-'))) {
                        if (!$ocDetalle) {
                            $item['oc_detalle'] = $codigo;
                            $item['codigo'] = null;
                            $ocDetalle = $codigo;
                        }
                    }

                    // Backfill: Si el pedido general está vacío pero la línea lo tiene.
                    // SOLO copiar si el OC no parece un código de catálogo de producto.
                    if (empty($invoice['no_pedido']) && !empty($ocDetalle)) {
                        $ocTest = preg_replace('/^(VOL-|CEL-|PO\s*|OC\s*-?|PEDIDO\s*|REF\s*-?)+/i', '', $ocDetalle);
                        $ocTest = str_replace(['(', ')'], '', $ocTest);
                        // Rechazar si es código de catálogo (2+ letras + dígito o guion)
                        $esCatalogo = preg_match('/^[A-Z]{2,}[\d-]/i', $ocTest);
                        if (!$esCatalogo && preg_match('/\d{3,}/', $ocTest) && !str_contains(strtoupper($ocDetalle), 'CEL-')) {
                            $invoice['no_pedido'] = $ocDetalle;
                        }
                    }
                }
            }

            // Clean no_pedido (remove prefixes) for the numeric field
            $cleanNoPedido = $invoice['no_pedido'] ?? '';
            $cleanNoPedido = preg_replace('/^(VOL-|CEL-|PO\s*|OC\s*-?|PEDIDO\s*|REF\s*-?)+/i', '', (string)$cleanNoPedido);
            
            // Extraer solo el primer token para evitar oraciones enteras extraídas por la IA
            $tokens = explode(' ', trim($cleanNoPedido));
            $cleanNoPedido = rtrim($tokens[0] ?? '', '.,;:/');
            
            // Si no contiene ningún número, descartarlo por ser ruido (ej. "Basado")
            if (!preg_match('/\d/', $cleanNoPedido)) {
                $cleanNoPedido = '';
            }
            $invoice['no_pedido'] = $cleanNoPedido;

            // Calculate Orden Pedido (Display only)
            $receptor = strtoupper($invoice['nombre_receptor'] ?? '');
            $prefix = str_contains($receptor, 'VOLANTIS') ? 'VOL-' : 'CEL-';
            if ($providerCode == '7' || $providerCode == '11673') {
                $prefix = 'VOL-';
            }
            $invoice['orden_pedido'] = !empty($cleanNoPedido) ? $prefix . $cleanNoPedido : null;

            $invoice['total'] = (float)($invoice['total'] ?? 0);
            $invoice['subtotal'] = (float)($invoice['subtotal'] ?? 0);
            $invoice['tipo_cambio'] = (float)($invoice['tipo_cambio'] ?? 1.0);
            
            if (!empty($invoice['items'])) {
                foreach ($invoice['items'] as &$item) {
                    $item['cantidad'] = (float)($item['cantidad'] ?? 0);
                    $item['valorUnitario'] = (float)($item['valorUnitario'] ?? 0);
                    $item['importe'] = (float)($item['importe'] ?? 0);

                    // Add prefix to oc_detalle for frontend display
                    if (!empty($item['oc_detalle'])) {
                        $cleanOc = preg_replace('/^(VOL-|CEL-|PO\s*|OC\s*-?|PEDIDO\s*|REF\s*-?)+/i', '', (string)$item['oc_detalle']);
                        
                        // Eliminar paréntesis (ej: (ELC-203NO) → ELC-203NO)
                        $cleanOc = str_replace(['(', ')'], '', $cleanOc);

                        $ocTokens = explode(' ', trim($cleanOc));
                        $cleanOc = rtrim($ocTokens[0] ?? '', '.,;:/');

                        // Rechazar si no contiene dígitos
                        if (!preg_match('/\d/', $cleanOc)) {
                            $cleanOc = '';
                        }

                        // Rechazar si parece un código de catálogo de producto:
                        // El patrón es: 2+ letras, guión, dígitos, letras al final (ej: ELC-203NO, CT-2115RW, LUX-065, LUM287)
                        // Un OC real es principalmente numérico (ej: 208320, 01A403 comienza con dígito)
                        if (!empty($cleanOc) && preg_match('/^[A-Z]{2,}[\d-]/i', $cleanOc)) {
                            // Parece un código de catálogo (ELC-203, CT-2115, LUX-065) → descartar
                            log_message('info', "OC descartado por parecer código de catálogo: '$cleanOc'");
                            $cleanOc = '';
                        }
                        
                        if (!empty($cleanOc)) {
                            $item['oc_detalle'] = $prefix . $cleanOc;
                        } elseif (!empty($cleanNoPedido)) {
                            $item['oc_detalle'] = $prefix . $cleanNoPedido;
                        } else {
                            $item['oc_detalle'] = null;
                        }
                    } elseif (!empty($cleanNoPedido)) {
                        // Default to header OC if item OC is empty
                        $item['oc_detalle'] = $prefix . $cleanNoPedido;
                    }
                }
            }
            // Capa 2: Validación Matemática
            $validator = new \App\Libraries\InvoiceValidator();
            $validationResult = $validator->validate($invoice);
            
            $invoice['estado_validacion'] = $validationResult['status'];
            $invoice['errores_validacion'] = $validationResult['isValid'] ? null : json_encode($validationResult['errors'], JSON_UNESCAPED_UNICODE);
            $invoice['isValid'] = $validationResult['isValid'];

            $response[] = $invoice;
        }

        return $this->respond([
            'status' => 200,
            'invoices' => $response
        ]);
    }

    public function save()
    {
        $json = $this->request->getJSON();
        $invoiceData = $json->invoice ?? null;
        $userId = $this->request->getHeaderLine('X-User-Id') ?: 1;

        if (!$invoiceData) {
            return $this->fail('No se recibieron datos de factura', 400);
        }

        $userModel = new UserModel();
        $userObj = $userModel->find($userId);
        
        $providerModel = new ProviderModel();
        $provider = null;
        $reqProviderCode = $json->provider_code ?? ($invoiceData->provider ?? null);
        if ($reqProviderCode) {
            $provider = $providerModel->where('codigo_proveedor', $reqProviderCode)->first();
        }
        if (!$provider && !empty($userObj['proveedor_id'])) {
            $provider = $providerModel->find($userObj['proveedor_id']);
        }
        if (!$provider) return $this->failNotFound('Proveedor no encontrado');

        $db = \Config\Database::connect();
        $db->transStrict(true); // Ensure transaction failure if any query fails
        $db->transBegin();

        try {
            $invoiceModel = new InvoiceModel();
            $itemModel = new InvoiceItemModel();

            // Calculate Orden Pedido
            // Clean no_pedido (remove prefixes)
            $cleanNoPedido = $invoiceData->no_pedido ?? '';
            $cleanNoPedido = preg_replace('/^(VOL-|CEL-|PO\s*|OC\s*-?|PEDIDO\s*|REF\s*-?)+/i', '', $cleanNoPedido);
            
            $tokens = explode(' ', trim($cleanNoPedido));
            $cleanNoPedido = rtrim($tokens[0] ?? '', '.,;:/');

            if (!preg_match('/\d/', $cleanNoPedido)) {
                $cleanNoPedido = '';
            }
            
            $receptor = strtoupper($invoiceData->nombre_receptor ?? '');
            $prefix = str_contains($receptor, 'VOLANTIS') ? 'VOL-' : 'CEL-';
            if ($provider['codigo_proveedor'] == '7' || $provider['codigo_proveedor'] == '11673') {
                $prefix = 'VOL-';
            }
            $ordenPedido = !empty($cleanNoPedido) ? $prefix . $cleanNoPedido : null;

            // Eagle specific logic for due date
            $fechaVencimiento = null;
            if (($provider['codigo_proveedor'] == '666' || $provider['codigo_proveedor'] == '5810') && !empty($invoiceData->fecha) && !empty($invoiceData->dias_credito)) {
                $date = new \DateTime($invoiceData->fecha);
                $date->modify('+' . intval($invoiceData->dias_credito) . ' days');
                $fechaVencimiento = $date->format('Y-m-d');
            }
            $cleanValue = function($val) {
                if (is_string($val)) return str_replace(',', '', $val);
                return $val;
            };

            $dataToInsert = [
                'proveedor_id' => $provider['id'],
                'proveedor' => $provider['codigo_proveedor'],
                'usuario_id' => $userId,
                'serie' => $invoiceData->serie ?? null,
                'numero_dte' => $invoiceData->numero ?? null,
                'fecha_factura' => $invoiceData->fecha ?? null,
                'uuid_sat' => $invoiceData->uuid ?? null,
                'moneda' => $invoiceData->moneda ?? 'MXN',
                'tipo_cambio' => $cleanValue($invoiceData->tipo_cambio ?? 1.0),
                'subtotal' => $cleanValue($invoiceData->subtotal ?? 0),
                'total_impuestos' => $cleanValue($invoiceData->total_impuestos ?? 0),
                'total_descuento' => $cleanValue($invoiceData->total_descuento ?? 0),
                'total' => $cleanValue($invoiceData->total ?? 0),
                'nombre_archivo_xml_original' => $invoiceData->xmlOriginal ?? null,
                'nombre_archivo_pdf_original' => $invoiceData->pdfOriginal ?? null,
                'nombre_archivo_xml_almacenado' => $invoiceData->xmlStored ?? null,
                'nombre_archivo_pdf_almacenado' => $invoiceData->pdfStored ?? null,
                'ruta_archivo' => WRITEPATH . 'uploads/' . $provider['codigo_proveedor'] . '/processed/',
                'estado' => 'processed',
                'fuente_extraccion' => $invoiceData->fuente_extraccion ?? 'manual',
                'nombre_emisor' => $invoiceData->nombre_emisor ?? null,
                'nit_emisor' => $invoiceData->nit_emisor ?? null,
                'nombre_receptor' => $invoiceData->nombre_receptor ?? null,
                'nit_receptor' => $invoiceData->nit_receptor ?? null,
                'no_pedido' => $cleanNoPedido ?: null,
                'orden_pedido' => $ordenPedido,
                'fecha_vencimiento' => $fechaVencimiento,
                'empresa_compra' => $provider['empresa_compra'] ?? null
            ];

            log_message('error', 'DEBUG SAVE INVOICE DATA: ' . print_r($dataToInsert, true));

            $invoiceId = $invoiceModel->insert($dataToInsert);

            if (!$invoiceId) {
                $dbError = $db->error();
                throw new \Exception("Error al insertar cabecera: " . ($dbError['message'] ?? 'Desconocido') . " - " . print_r($invoiceModel->errors(), true));
            }

            if (!empty($invoiceData->items)) {
                foreach ($invoiceData->items as $item) {
                    $itemInsert = $itemModel->insert([
                        'factura_id' => $invoiceId,
                        'descripcion' => $item->descripcion ?? 'Sin descripción',
                        'cantidad' => (int)($cleanValue($item->cantidad ?? 0)),
                        'precio_unitario' => $cleanValue($item->valorUnitario ?? 0),
                        'importe_total' => $cleanValue($item->importe ?? 0),
                        'monto_impuesto' => $cleanValue($item->montoImpuesto ?? 0),
                        'descuento' => $cleanValue($item->montoDescuento ?? 0),
                        'codigo' => $item->codigo ?? null,
                        'tipo_bien_servicio' => $item->tipoBienServicio ?? 'Bien',
                        'oc_detalle' => (function($oc) {
                            if (empty($oc)) return null;
                            $oc = preg_replace('/^(VOL-|CEL-|PO\s*|OC\s*-?|PEDIDO\s*|REF\s*-?)+/i', '', $oc);
                            $tokens = explode(' ', trim($oc));
                            $cleanOc = rtrim($tokens[0] ?? '', '.,;:/');
                            return preg_match('/\d/', $cleanOc) ? $cleanOc : null;
                        })($item->oc_detalle ?? null),
                        'fecha_creacion' => $invoiceData->fecha ?? null
                    ]);

                    if (!$itemInsert) {
                        $dbError = $db->error();
                        throw new \Exception("Error al insertar detalle: " . ($dbError['message'] ?? 'Desconocido'));
                    }
                }
            }

            if ($db->transStatus() === false) {
                $db->transRollback();
                return $this->fail('Error en la transacción de base de datos', 500);
            }

            $db->transCommit();

            // Move files to processed directory
            $stagingPath = WRITEPATH . 'uploads/' . $provider['codigo_proveedor'] . '/staging/';
            $processedPath = WRITEPATH . 'uploads/' . $provider['codigo_proveedor'] . '/processed/';
            if (!is_dir($processedPath)) mkdir($processedPath, 0777, true);

            if (!empty($invoiceData->xmlStored) && file_exists($stagingPath . $invoiceData->xmlStored)) {
                rename($stagingPath . $invoiceData->xmlStored, $processedPath . $invoiceData->xmlStored);
            }
            if (!empty($invoiceData->pdfStored) && file_exists($stagingPath . $invoiceData->pdfStored)) {
                rename($stagingPath . $invoiceData->pdfStored, $processedPath . $invoiceData->pdfStored);
            }

            return $this->respond([
                'status' => 200,
                'message' => 'Factura guardada exitosamente',
                'invoice_id' => $invoiceId
            ]);

        } catch (\Exception $e) {
            $db->transRollback();
            $msg = $e->getMessage();
            
            // Detect duplicate UUID error (Postgres 23505)
            if (str_contains($msg, 'uk_factura_identificador') || str_contains($msg, '23505') || str_contains($msg, 'uuid_sat')) {
                 return $this->respond([
                    'status' => 409, // Conflict
                    'error' => 409,
                    'messages' => [
                        'error' => "Esta factura ya ha sido registrada previamente en el sistema (UUID Duplicado)."
                    ]
                ], 409);
            }

            return $this->fail($msg, 500);
        }
    }

    public function stage()
    {
        // 1. Validar Usuario (simulado)
        $userId = $this->request->getHeaderLine('X-User-Id');
        if (!$userId)
            $userId = 1;

        // 2. Obtener Código Proveedor
        $providerCode = $this->request->getPost('provider_code');

        // Crear directorio de staging
        $stagingPath = WRITEPATH . 'uploads/' . $providerCode . '/staging/';
        if (!is_dir($stagingPath)) {
            mkdir($stagingPath, 0777, true);
        }

        $files = $this->request->getFiles();
        if (!$files) {
            return $this->fail('No files uploaded', 400);
        }

        $response = [];

        foreach ($files['files'] as $file) {
            if (!$file->isValid()) {
                $errorCode = $file->getError();
                $errorMsg = "Error desconocido al subir archivo.";

                if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
                    $limit = ini_get('upload_max_filesize');
                    $errorMsg = "El archivo '" . $file->getClientName() . "' excede el límite de PHP configurado en el servidor (Actual: $limit).";
                } elseif ($errorCode === UPLOAD_ERR_PARTIAL) {
                    $errorMsg = "El archivo se subió parcialmente. Intente de nuevo.";
                } elseif ($errorCode === UPLOAD_ERR_NO_FILE) {
                    $errorMsg = "No se recibió ningún archivo.";
                }

                return $this->fail($errorMsg, 400);
            }

            if (!$file->hasMoved()) {
                $originalName = $file->getClientName();
                $newName = $file->getRandomName();

                try {
                    $file->move($stagingPath, $newName);
                    $response[] = [
                        'originalName' => $originalName,
                        'storedName' => $newName,
                        'path' => $stagingPath
                    ];
                } catch (\Exception $e) {
                    log_message('error', "Error staging file: " . $e->getMessage());
                }
            }
        }

        if (empty($response)) {
            return $this->fail('No se pudo guardar ningún archivo en el servidor. Verifique permisos o espacio en disco.', 500);
        }

        return $this->respond([
            'status' => 200,
            'message' => 'Files staged',
            'files' => $response
        ]);
    }

    public function deleteStaged()
    {
        $json = $this->request->getJSON();
        $providerCode = $json->provider_code ?? null;
        $fileNames = $json->files ?? [];

        if (!$providerCode || empty($fileNames)) {
            return $this->fail('Datos incompletos', 400);
        }

        $stagingPath = WRITEPATH . 'uploads/' . $providerCode . '/staging/';

        $deletedCount = 0;
        foreach ($fileNames as $fileName) {
            $filePath = $stagingPath . $fileName;
            if (file_exists($filePath) && is_file($filePath) && strpos($fileName, '..') === false) {
                unlink($filePath);
                $deletedCount++;
            }
        }

        return $this->respond([
            'status' => 200,
            'message' => 'Archivos eliminados',
            'deleted_count' => $deletedCount
        ]);
    }

    public function processBatch()
    {
        set_time_limit(0);
        log_message('error', 'ANTIGRAVITY DEBUG V100: Starting Process Batch');
        $json = $this->request->getJSON();
        log_message('error', 'ProcessBatch Start: ' . print_r($json, true));

        $providerCode = $json->provider_code ?? null;
        $filePairs = $json->files ?? [];
        $userId = $this->request->getHeaderLine('X-User-Id') ?: 1;

        if (!$providerCode || empty($filePairs)) {
            return $this->fail('Datos incompletos', 400);
        }

        $userModel = new UserModel();
        $userObj = $userModel->find($userId);
        if (!$userObj)
            return $this->failUnauthorized('Usuario no válido');

        $providerModel = new ProviderModel();
        $provider = null;
        if ($providerCode) {
            $provider = $providerModel->where('codigo_proveedor', $providerCode)->first();
        }
        if (!$provider && !empty($userObj['proveedor_id'])) {
            $provider = $providerModel->find($userObj['proveedor_id']);
        }

        if (!$provider)
            return $this->failNotFound('Proveedor no encontrado para el usuario actual');

        // Usar el código real de la tabla proveedores (proveedor_compra)
        $providerCode = $provider['codigo_proveedor'];

        $stagingPath = WRITEPATH . 'uploads/' . $providerCode . '/staging/';
        $processedPath = WRITEPATH . 'uploads/' . $providerCode . '/processed/';

        if (!is_dir($processedPath))
            mkdir($processedPath, 0777, true);

        $processedCount = 0;
        $processedData = [];
        $errors = [];

        // Instantiate Parsers
        $xmlParser = new XmlInvoiceParser();
        $pdfParser = new InvoiceParserManager();

        $db = \Config\Database::connect();

        foreach ($filePairs as $pair) {
            // Normalizar entrada
            $xmlStored = isset($pair->xml) && is_object($pair->xml) ? $pair->xml->storedName : ($pair->xml ?? null);
            $pdfStored = isset($pair->pdf) && is_object($pair->pdf) ? $pair->pdf->storedName : ($pair->pdf ?? null);

            $xmlOriginal = $pair->xmlOriginal ?? $xmlStored;
            $pdfOriginal = $pair->pdfOriginal ?? $pdfStored;

            if (!$xmlStored && !$pdfStored) {
                $errors[] = "Ni XML ni PDF encontrados";
                continue;
            }

            $xmlPath = $xmlStored ? $stagingPath . $xmlStored : null;
            $pdfPath = $pdfStored ? $stagingPath . $pdfStored : null;

            $invoicesPerFile = null;
            $status = 'processed';
            $source = '';
            $errorMsg = null;

            // 1. Intentar XML
            if ($xmlPath && file_exists($xmlPath)) {
                $parsedData = $xmlParser->parse($xmlPath);
                if ($parsedData) {
                    $invoicesPerFile = [$parsedData]; // XML usually has one, wrap in list
                    $source = 'xml';
                } else {
                    $errorMsg = 'Fallo lectura XML';
                }
            }

            // 2. Fallback o Primario PDF
            if (!$invoicesPerFile) {
                if ($pdfPath && file_exists($pdfPath)) {
                    $invoicesPerFile = $pdfParser->process($pdfPath, $providerCode);
                    if ($invoicesPerFile && !isset($invoicesPerFile['error'])) {
                        $source = 'pdf';
                        $status = 'processed';
                        $errorMsg = null;
                    } else {
                        $errorMsg .= ($errorMsg ? ' y ' : '') . 'Fallo lectura PDF: ' . ($invoicesPerFile['error'] ?? 'Unknown');
                        $invoicesPerFile = null;
                    }
                } else {
                    if (!$xmlPath)
                        $errorMsg = "Sin XML y sin PDF valido";
                }
            }

            if (!$invoicesPerFile || empty($invoicesPerFile)) {
                $errors[] = "Error en $pdfOriginal: $errorMsg";
                continue;
            }

            $fileHasSuccess = false;

            // LOOP THROUGH EACH INVOICE FOUND IN THE FILE
            foreach ($invoicesPerFile as $invoiceData) {
                // Pre-Deduplicación: Limpiar ítems antes de procesar la factura
                if (!empty($invoiceData['items'])) {
                    $unique_items = [];
                    foreach ($invoiceData['items'] as $it) {
                        $c = trim($it['codigo'] ?? '');
                        $q = round((float) ($it['cantidad'] ?? 0), 4);
                        $p = round((float) ($it['valorUnitario'] ?? 0), 4);
                        $t = round((float) ($it['importe'] ?? 0), 4);
                        $k = md5($c . '|' . $q . '|' . $p . '|' . $t);
                        if (!isset($unique_items[$k]))
                            $unique_items[$k] = $it;
                    }
                    $invoiceData['items'] = array_values($unique_items);
                }

                // Iniciar transacción por CADA FACTURA INDIVIDUAL
                $db->transBegin();

                if (isset($invoiceData['error'])) {
                    $db->transRollback();
                    $errors[] = "Error en una de las facturas de $pdfOriginal: " . $invoiceData['error'];
                    continue;
                }

                // Capa 2: Validación Matemática
                $validator = new \App\Libraries\InvoiceValidator();
                $validationResult = $validator->validate($invoiceData);
                
                // Actualizar estado basado en validación
                $status = $validationResult['status'];
                $erroresValidacionJson = null;
                if (!$validationResult['isValid']) {
                    $erroresValidacionJson = json_encode($validationResult['errors'], JSON_UNESCAPED_UNICODE);
                }

                // Sanitize fecha (solo fecha YYYY-MM-DD para evitar shift por timezone)
                $fechaFactura = !empty($invoiceData['fecha']) ? substr($invoiceData['fecha'], 0, 10) : null;
                
                // Dynamic Provider Switch (e.g. Eagle 666 vs 5810)
                $currentProviderId = $provider['id'];
                $currentProviderCode = $providerCode;
                $currentEmpresaCompra = $provider['empresa_compra'] ?? null;

                if (!empty($invoiceData['switch_provider'])) {
                    $switchedProvider = $providerModel->where('codigo_proveedor', $invoiceData['switch_provider'])->first();
                    if ($switchedProvider) {
                        $currentProviderId = $switchedProvider['id'];
                        $currentProviderCode = $switchedProvider['codigo_proveedor'];
                        $currentEmpresaCompra = $switchedProvider['empresa_compra'] ?? null;
                        log_message('error', "Switching provider to $currentProviderCode based on Memo detection");
                    }
                }

                // Calcular fecha de vencimiento (SOLO PARA EAGLE 666/5810)
                $fechaVencimiento = null;
                $isEagle = ($currentProviderCode == '666' || $currentProviderCode == '5810');
                
                if ($isEagle && $fechaFactura && !empty($invoiceData['dias_credito'])) {
                    try {
                        $date = new \DateTime($fechaFactura);
                        $date->modify('+' . intval($invoiceData['dias_credito']) . ' days');
                        $fechaVencimiento = $date->format('Y-m-d');
                    } catch (\Exception $e) {
                        log_message('error', "[Vencimiento] Error calculando fecha: " . $e->getMessage());
                    }
                }

                // Generar Orden de Pedido (VOL- para Volantis, CEL- para otros receptores como Celasa)
                $receptor = strtoupper($invoiceData['nombre_receptor'] ?? '');
                $prefix = str_contains($receptor, 'VOLANTIS') ? 'VOL-' : 'CEL-';

                // Sylvania & Tecnolite specific rule: always use VOL- prefix
                if ($currentProviderCode == '7' || $currentProviderCode == '11673') {
                    $prefix = 'VOL-';
                }

                $ordenPedido = !empty($invoiceData['no_pedido']) ? $prefix . $invoiceData['no_pedido'] : null;

                // DB Insert
                $invoiceModel = new InvoiceModel();
                $invoiceId = $invoiceModel->insert([
                    'proveedor_id' => $currentProviderId,
                    'proveedor' => $currentProviderCode,
                    'usuario_id' => $userId,
                    'serie' => $invoiceData['serie'] ?? null,
                    'numero_dte' => $invoiceData['numero'] ?? ($invoiceData['folio'] ?? null),
                    'fecha_factura' => $fechaFactura,
                    'uuid_sat' => $invoiceData['uuid'] ?? null,
                    'moneda' => $invoiceData['moneda'] ?? 'MXN',
                    'tipo_cambio' => $invoiceData['tipo_cambio'] ?? 1.0,
                    'subtotal' => $invoiceData['subtotal'] ?? 0,
                    'total_impuestos' => $invoiceData['total_impuestos'] ?? 0,
                    'total' => $invoiceData['total'] ?? 0,
                    'nombre_archivo_xml_original' => $xmlOriginal,
                    'nombre_archivo_pdf_original' => $pdfOriginal,
                    'nombre_archivo_xml_almacenado' => $xmlStored,
                    'nombre_archivo_pdf_almacenado' => $pdfStored,
                    'ruta_archivo' => $processedPath,
                    'estado' => $status,
                    'errores_validacion' => $erroresValidacionJson,
                    'fuente_extraccion' => $source,
                    'mensaje_error' => $errorMsg,
                    'nombre_emisor' => $invoiceData['nombre_emisor'] ?? null,
                    'nit_emisor' => $invoiceData['nit_emisor'] ?? null,
                    'direccion_emisor' => $invoiceData['direccion_emisor'] ?? null,
                    'nombre_receptor' => $invoiceData['nombre_receptor'] ?? null,
                    'nit_receptor' => $invoiceData['nit_receptor'] ?? null,
                    'direccion_receptor' => $invoiceData['direccion_receptor'] ?? null,
                    'fecha_certificacion' => $invoiceData['fecha_certificacion'] ?? null,
                    'total_descuento' => $invoiceData['total_descuento'] ?? 0,
                    'total_otros_descuentos' => $invoiceData['total_otros_descuentos'] ?? 0,
                    'no_pedido' => $invoiceData['no_pedido'] ?? null,
                    'empresa_compra' => $currentEmpresaCompra,
                    'dias_credito' => $invoiceData['dias_credito'] ?? null,
                    'termino_compra' => $invoiceData['termino_compra'] ?? null,
                    'fecha_vencimiento' => $fechaVencimiento,
                    'orden_pedido' => $ordenPedido
                ]);

                if (!$invoiceId) {
                    $errors[] = "Error al insertar cabecera de factura para $xmlOriginal: " . print_r($invoiceModel->errors(), true);
                    $db->transRollback();
                    continue;
                }

                // Insertar Items
                if (!empty($invoiceData['items'])) {
                    $itemModel = new InvoiceItemModel();
                    foreach ($invoiceData['items'] as $item) {

                        $itemInsert = $itemModel->insert([
                            'factura_id' => $invoiceId,
                            'descripcion' => $item['descripcion'] ?? 'Sin descripción',
                            'cantidad' => $item['cantidad'] ?? 0,
                            'unidad_medida' => $item['unidadMedida'] ?? null,
                            'precio_unitario' => $item['valorUnitario'] ?? 0,
                            'importe_total' => $item['importe'] ?? 0,
                            'monto_impuesto' => $item['montoImpuesto'] ?? 0,
                            'tipo_bien_servicio' => $item['tipoBienServicio'] ?? null,
                            'descuento' => $item['descuento'] ?? 0,
                            'otros_descuentos' => $item['otrosDescuentos'] ?? 0,
                            'codigo' => $item['codigo'] ?? null,
                            'oc_detalle' => $item['oc_detalle'] ?? null,
                            'fecha_creacion' => $fechaFactura
                        ]);

                        if (!$itemInsert) {
                            $db->transRollback();
                            $errors[] = "Error al insertar item en factura $xmlOriginal: " . print_r($itemModel->errors(), true);
                            continue 2; // Siguiente factura en el loop de invoicesPerFile
                        }
                    }
                }

                // COMMIT para esta factura específica
                if ($db->transStatus() === false) {
                    $db->transRollback();
                    $errors[] = "Falló la transacción de la factura $xmlOriginal";
                    continue;
                }

                $db->transCommit();
                $fileHasSuccess = true;

                $processedCount++;
                $processedData[] = [
                    'filename' => $pdfOriginal ?? $xmlOriginal,
                    'no_pedido' => $invoiceData['no_pedido'] ?? null,
                    'serie' => $invoiceData['serie'] ?? null,
                    'numero' => $invoiceData['numero'] ?? ($invoiceData['folio'] ?? null),
                    'total' => $invoiceData['total'] ?? 0,
                    'uuid' => $invoiceData['uuid'] ?? null
                ];
            }

            // SÓLO DESPUÉS de procesar todas las facturas del archivo, movemos los archivos si hubo éxito
            if ($fileHasSuccess) {
                if ($xmlPath && file_exists($xmlPath)) {
                    if (!@rename($xmlPath, $processedPath . $xmlStored)) {
                        @copy($xmlPath, $processedPath . $xmlStored);
                        @unlink($xmlPath);
                    }
                }

                if ($pdfPath && file_exists($pdfPath)) {
                    if (!@rename($pdfPath, $processedPath . $pdfStored)) {
                        @copy($pdfPath, $processedPath . $pdfStored);
                        @unlink($pdfPath);
                    }
                }
            }
        }

        return $this->respond([
            'status' => 200,
            'processed_count' => $processedCount,
            'data' => $processedData,
            'errors' => $errors
        ]);
    }

    public function viewPdf($providerCode, $filename)
    {
        // Simple file server for staged/processed PDFs
        // Check staging first
        $path = WRITEPATH . 'uploads/' . $providerCode . '/staging/' . $filename;
        if (!file_exists($path)) {
            // Check processed
            $path = WRITEPATH . 'uploads/' . $providerCode . '/processed/' . $filename;
        }

        if (!file_exists($path)) {
            return $this->failNotFound('Archivo no encontrado');
        }

        $file = new \CodeIgniter\Files\File($path);
        
        $this->response->setHeader('Content-Type', 'application/pdf');
        $this->response->setHeader('Content-Disposition', 'inline; filename="' . $file->getBasename() . '"');
        $this->response->setHeader('Content-Length', (string)$file->getSize());
        $this->response->setBody(file_get_contents($path));
        $this->response->send();
        exit;
    }
}
