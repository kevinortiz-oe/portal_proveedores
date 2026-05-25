<?php

namespace App\Libraries;

use App\Libraries\Parsers\InvoiceParserInterface;
use App\Libraries\Parsers\GenericStrategy;

class InvoiceParserManager
{
    protected $strategies = [];

    public function __construct()
    {
        // Register strategies (Vision AI is Primary)
        $this->strategies[] = new \App\Libraries\Parsers\VisionAiStrategy();
        
        // Fallback to old Cloud AI if needed
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
                // If the strategy supports setting a provider code (like CloudAi or VisionAi), pass it
                if (method_exists($strategy, 'setProviderCode')) {
                    $strategy->setProviderCode($providerCode);
                }
                
                // Inject the raw text extracted from the PDF to anchor the AI and prevent hallucination
                if (method_exists($strategy, 'setRawText')) {
                    $strategy->setRawText($text);
                }
                
                return $strategy->parse($filePath);
            }
        }

        return ['error' => 'No suitable parser strategy found.'];
    }
}
