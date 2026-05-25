<?php

namespace App\Libraries\Parsers;

class SignifyStrategy implements InvoiceParserInterface
{
    public function canParse(string $text): bool
    {
        return stripos($text, 'Signify') !== false || stripos($text, 'DISTRIBUIDORA VOLANTIS') !== false;
    }

    public function parse(string $filePath): array
    {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();

        // Similar header extraction as Generic (can be abstracted later)
        $cleanText = preg_replace('/\r\n/', "\n", $text);

        // Logic specific to Signify:
        // Items are split across lines. Key marker is the line with "Quantity / Unit Price / Unit Total"
        // Example: "2,000 / PZA 5.70 / PZA 11,400.00"

        $items = [];
        $lines = explode("\n", $cleanText);

        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);

            // Regex for the numbers line: Quantity / Unit ... Price / Unit ... Total
            // 2,000 / PZA 5.70 / PZA 11,400.00
            if (preg_match('/^([\d,]+)\s*\/\s*[A-Z]+\s+([\d,]+\.\d{2})\s*\/\s*[A-Z]+\s+([\d,]+\.\d{2})/', $line, $m)) {

                // Found the numbers. Now look BACK for description and code
                $qty = $this->cleanAmount($m[1]);
                $price = $this->cleanAmount($m[2]);
                $total = $this->cleanAmount($m[3]);

                $description = "Item";
                $code = null;

                // Look back 1 line for Description
                if (isset($lines[$i - 1])) {
                    $description = trim($lines[$i - 1]);
                }

                // Look back 2 lines for Code (if it looks like a code)
                if (isset($lines[$i - 2]) && preg_match('/^\d{10,}$/', trim($lines[$i - 2]))) {
                    $code = trim($lines[$i - 2]);
                    // If code is there, maybe description is at i-1. Correct.
                }
                // Sometimes Description is split? For now keep simple lookback.

                $items[] = [
                    'cantidad' => $qty,
                    'descripcion' => $description,
                    'valorUnitario' => $price,
                    'importe' => $total,
                    'codigo' => $code,
                    'unidadMedida' => 'UNIDAD', // PZA
                    'tipoBienServicio' => 'BIEN'
                ];
            }
        }

        // Extract Header Data (reuse logic or keep simple)
        // For MVP of this strategy, we return basic data + items
        $total = 0;
        foreach ($items as $it)
            $total += $it['importe'];

        preg_match('/Número \/\s*Document Number\s*(\d+)/i', $cleanText, $numM);
        preg_match('/Fecha \/\s*Document Date\s*(\d{2}\/\d{2}\/\d{4})/i', $cleanText, $dateM);

        return [
            [
                'fecha' => $dateM[1] ?? null,
                'numero' => $numM[1] ?? null,
                'serie' => null,
                'moneda' => 'USD', // Signify appears to be USD based on sample
                'subtotal' => $total,
                'total' => $total,
                'nit_emisor' => null, // TODO: Extract if needed
                'nombre_emisor' => 'Signify Caribbean, Inc.',
                'items' => $items
            ]
        ];
    }

    private function cleanAmount($str)
    {
        return (float) str_replace(',', '', $str);
    }
}
