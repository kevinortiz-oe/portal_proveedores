<?php

namespace App\Libraries\Parsers;

interface InvoiceParserInterface
{
    /**
     * Determines if this strategy supports the given file content/metadata.
     * @param string $text Extracted text from PDF
     * @param array $metadata Optional metadata
     * @return bool
     */
    public function canParse(string $text): bool;

    /**
     * Parses the PDF file and returns standardized data for all invoices found.
     * @param string $filePath Full path to PDF
     * @return array Standardized list of invoices [ invoice1, invoice2, ... ]
     */
    public function parse(string $filePath): array;
}
