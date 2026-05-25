<?php

namespace App\Libraries;

class XmlInvoiceParser
{
    public function parse($filePath)
    {
        if (!file_exists($filePath))
            return null;

        try {
            $xmlContent = file_get_contents($filePath);
            $xml = @simplexml_load_string($xmlContent);

            if (!$xml)
                return null;

            $ns = $xml->getNamespaces(true);

            // Detectar formato
            if (isset($ns['dte'])) {
                return $this->parseDTE_GT($xml, $ns);
            } else {
                return $this->parseCFDI_MX($xml, $ns);
            }

        } catch (\Exception $e) {
            log_message('error', 'Error parsing XML: ' . $e->getMessage());
            return null;
        }
    }

    private function parseCFDI_MX($xml, $ns)
    {
        $xml->registerXPathNamespace('cfdi', $ns['cfdi'] ?? 'http://www.sat.gob.mx/cfd/4');
        $xml->registerXPathNamespace('tfd', $ns['tfd'] ?? 'http://www.sat.gob.mx/TimbreFiscalDigital');

        $data = [
            'folio' => (string) $xml['Folio'],
            'fecha' => (string) $xml['Fecha'],
            'moneda' => (string) $xml['Moneda'],
            'subtotal' => (float) $xml['SubTotal'],
            'total' => (float) $xml['Total'],
            'items' => []
        ];

        foreach ($xml->xpath('//tfd:TimbreFiscalDigital') as $tfd) {
            $data['uuid'] = (string) $tfd['UUID'];
        }

        $impuestos = $xml->xpath('//cfdi:Impuestos');
        if ($impuestos) {
            $totalImp = 0;
            foreach ($impuestos as $imp) {
                if (isset($imp['TotalImpuestosTrasladados'])) {
                    $totalImp += (float) $imp['TotalImpuestosTrasladados'];
                }
            }
            $data['total_impuestos'] = $totalImp;
        }

        foreach ($xml->xpath('//cfdi:Concepto') as $concepto) {
            $data['items'][] = [
                'descripcion' => (string) $concepto['Descripcion'],
                'cantidad' => (float) $concepto['Cantidad'],
                'valorUnitario' => (float) $concepto['ValorUnitario'],
                'importe' => (float) $concepto['Importe'],
                'claveProdServ' => (string) $concepto['ClaveProdServ']
            ];
        }

        return $data;
    }

    private function parseDTE_GT($xml, $ns)
    {
        $xml->registerXPathNamespace('dte', $ns['dte']);

        // --- 1. DATOS GENERALES ---
        $datosGeneralesNodes = $xml->xpath('//dte:DatosGenerales');
        $datosGenerales = $datosGeneralesNodes[0] ?? null;

        $fechaRaw = $datosGenerales ? (string) $datosGenerales['FechaHoraEmision'] : '';
        $fecha = substr($fechaRaw, 0, 10);
        $moneda = $datosGenerales ? (string) $datosGenerales['CodigoMoneda'] : 'GTQ';

        // --- 2. FOLIO Y UUID ---
        // Global XPath para encontrar el nodo donde sea que este
        $numAuthNodes = $xml->xpath('//dte:NumeroAutorizacion');
        $numAuthNode = $numAuthNodes[0] ?? null;

        $uuid = $numAuthNode ? (string) $numAuthNode : '';
        $serie = '';
        $numero = '';

        if ($numAuthNode) {
            $attrs = $numAuthNode->attributes();
            $serie = (string) ($attrs['Serie'] ?? '');
            $numero = (string) ($attrs['Numero'] ?? '');
        }

        $folio = '';
        if ($serie && $numero) {
            $folio = "$serie-$numero";
        } else {
            $folio = $numero ?: $uuid;
        }

        // --- 3. TOTALES ---
        // Global XPath directo a GranTotal
        $granTotalNodes = $xml->xpath('//dte:GranTotal');
        $grandTotal = isset($granTotalNodes[0]) ? (float) $granTotalNodes[0] : 0;

        // Impuestos Globales
        $totalImpuestos = 0;
        // Buscar cualquier TotalImpuesto que tenga el atributo TotalMontoImpuesto
        $impuestosNodes = $xml->xpath('//dte:TotalImpuestos/dte:TotalImpuesto');
        foreach ($impuestosNodes as $impNode) {
            $attrs = $impNode->attributes();
            $totalImpuestos += (float) ($attrs['TotalMontoImpuesto'] ?? 0);
        }

        // Subtotal = GranTotal / 1.12
        $subtotal = $grandTotal > 0 ? $grandTotal / 1.12 : 0;

        // --- 5. EMISOR / RECEPTOR / OTROS ---
        // Emisor (Global XPath + Attributes)
        $emisorNodes = $xml->xpath('//dte:Emisor');
        $emisorNode = $emisorNodes[0] ?? null;
        $nombreEmisor = '';
        $nitEmisor = '';
        if ($emisorNode) {
            $attrs = $emisorNode->attributes();
            $nombreEmisor = (string) ($attrs['NombreEmisor'] ?? '');
            $nitEmisor = (string) ($attrs['NITEmisor'] ?? '');
        }

        // Direccion Emisor (Global XPath directo al texto)
        $dirEmisorNodes = $xml->xpath('//dte:Emisor/dte:DireccionEmisor/dte:Direccion');
        $direccionEmisor = isset($dirEmisorNodes[0]) ? (string) $dirEmisorNodes[0] : '';

        // Receptor (Global XPath + Attributes)
        $receptorNodes = $xml->xpath('//dte:Receptor');
        $receptorNode = $receptorNodes[0] ?? null;
        $nombreReceptor = '';
        $nitReceptor = '';
        if ($receptorNode) {
            $attrs = $receptorNode->attributes();
            $nombreReceptor = (string) ($attrs['NombreReceptor'] ?? '');
            $nitReceptor = (string) ($attrs['IDReceptor'] ?? ''); // XML usa IDReceptor
        }

        // Direccion Receptor
        $dirReceptorNodes = $xml->xpath('//dte:Receptor/dte:DireccionReceptor/dte:Direccion');
        $direccionReceptor = isset($dirReceptorNodes[0]) ? (string) $dirReceptorNodes[0] : '';

        // Fechas Adicionales
        $fechaCertNodes = $xml->xpath('//dte:Certificacion/dte:FechaHoraCertificacion');
        $fechaCertRaw = isset($fechaCertNodes[0]) ? (string) $fechaCertNodes[0] : null;

        // Debug Log si falla algo critico
        if (!$nombreEmisor)
            log_message('error', "DTE GT Debug: Emisor no encontrado");

        $data = [
            'folio' => $folio, // Mantener folio por compatibilidad si se usa en otros lados, pero DB usará serie/numero
            'serie' => $serie,
            'numero' => $numero,
            'fecha' => $fecha,
            'moneda' => $moneda,
            'subtotal' => $subtotal,
            'total' => $grandTotal,
            'total_impuestos' => $totalImpuestos,
            'uuid' => $uuid,
            'nombre_emisor' => $nombreEmisor,
            'nit_emisor' => $nitEmisor,
            'direccion_emisor' => $direccionEmisor,
            'nombre_receptor' => $nombreReceptor,
            'nit_receptor' => $nitReceptor,
            'direccion_receptor' => $direccionReceptor,
            'fecha_certificacion' => $fechaCertRaw,
            'numero_acceso' => null,
            'total_descuento' => 0,
            'total_otros_descuentos' => 0,
            'items' => []
        ];

        // --- 4. ITEMS ---
        $items = $xml->xpath('//dte:Item');
        $totalDescuentoAccum = 0;
        $totalOtrosDescuentosAccum = 0;

        foreach ($items as $item) {
            // Registrar namespace para búsquedas relativas seguras
            $item->registerXPathNamespace('dte', $ns['dte']);

            $desc = (string) ($item->xpath('dte:Descripcion')[0] ?? '');
            $cant = (float) ($item->xpath('dte:Cantidad')[0] ?? 0);
            $unidad = (string) ($item->xpath('dte:UnidadMedida')[0] ?? '');
            $precioU = (float) ($item->xpath('dte:PrecioUnitario')[0] ?? 0);
            $totalLinea = (float) ($item->xpath('dte:Total')[0] ?? 0);

            // New Fields Extraction
            $attrs = $item->attributes();
            $bienOServicioRaw = (string) ($attrs['BienOServicio'] ?? '');
            $tipoBienServicio = $bienOServicioRaw === 'S' ? 'SERVICIO' : ($bienOServicioRaw === 'B' ? 'BIEN' : $bienOServicioRaw);

            $descuento = (float) ($item->xpath('dte:Descuento')[0] ?? 0);
            $otrosDescuentos = (float) ($item->xpath('dte:OtrosDescuento')[0] ?? 0);

            $totalDescuentoAccum += $descuento;
            $totalOtrosDescuentosAccum += $otrosDescuentos;

            // Impuesto Item
            $montoImpuestoItem = 0;
            $impuestosItem = $item->xpath('dte:Impuestos/dte:Impuesto/dte:MontoImpuesto');
            if ($impuestosItem) {
                foreach ($impuestosItem as $montoNode) {
                    $montoImpuestoItem += (float) $montoNode;
                }
            }

            $data['items'][] = [
                'descripcion' => $desc,
                'cantidad' => $cant,
                'unidadMedida' => $unidad,
                'valorUnitario' => $precioU,
                'importe' => $totalLinea,
                'montoImpuesto' => $montoImpuestoItem,
                'tipoBienServicio' => $tipoBienServicio,
                'descuento' => $descuento,
                'otrosDescuentos' => $otrosDescuentos,
                'claveProdServ' => null
            ];
        }

        $data['total_descuento'] = $totalDescuentoAccum;
        $data['total_otros_descuentos'] = $totalOtrosDescuentosAccum;

        return $data;
    }
}
