<?php

namespace App\Libraries;

use App\Libraries\Parsers\InvoiceParserInterface;
use App\Libraries\Parsers\GenericStrategy;

class InvoiceParserManager
{
    protected $strategies = [];

    public function __construct()
    {
        // Register strategies (Cloud AI is Primary)
        $this->strategies[] = new \App\Libraries\Parsers\CloudAiStrategy();

        // Legacy Strategies (Disabled per user request)
        // $this->strategies[] = new \App\Libraries\Parsers\SignifyStrategy();
        // $this->strategies[] = new GenericStrategy();
    }

    public function process(string $filePath, ?string $providerCode = null): array
    {
        if (!file_exists($filePath)) {
            return ['error' => 'File not found'];
        }

        // Extract text once to decide strategy (optimization)
        $parser = new \Smalot\PdfParser\Parser();
        try {
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
        } catch (\Exception $e) {
            return ['error' => 'PDF Load Error: ' . $e->getMessage()];
        }

        // Iterate strategies to find one that can parse
        foreach ($this->strategies as $strategy) {
            if ($strategy->canParse($text)) {
                // If it's the Cloud AI strategy, set the provider code
                if ($strategy instanceof \App\Libraries\Parsers\CloudAiStrategy) {
                    $strategy->setProviderCode($providerCode);
                }
                
                return $strategy->parse($filePath);
            }
        }

        return ['error' => 'No suitable parser strategy found.'];
    }
}
