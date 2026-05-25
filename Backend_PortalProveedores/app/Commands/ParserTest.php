<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\InvoiceParserManager;

class ParserTest extends BaseCommand
{
    protected $group = 'Parser';
    protected $name = 'parser:test';
    protected $description = 'Run regression tests on invoices in writable/test_invoices';

    public function run(array $params)
    {
        CLI::write('Starting Regression Tests...', 'yellow');

        $path = WRITEPATH . 'test_invoices/';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        $files = glob($path . '*.pdf');
        if (empty($files)) {
            CLI::error("No PDF files found in $path. Please upload some files.");
            return;
        }

        $manager = new InvoiceParserManager();
        $success = 0;
        $total = count($files);

        foreach ($files as $file) {
            $filename = basename($file);
            CLI::write("Testing: $filename", 'white');

            $start = microtime(true);
            $result = $manager->process($file);
            $time = round(microtime(true) - $start, 3);

            if (isset($result['error'])) {
                CLI::error("  [FAIL] Error: " . $result['error']);
            } elseif (empty($result['items'])) {
                CLI::error("  [FAIL] No items found.");
                // Show debug info
                if (isset($result['raw_text_snippet']))
                    CLI::write("Snippet: " . substr($result['raw_text_snippet'], 0, 100));
            } else {
                $itemCount = count($result['items']);
                $totalAmount = $result['total'];
                CLI::write("  [PASS] Found $itemCount items. Total: $totalAmount ($time s)", 'green');
                $success++;
            }
        }

        CLI::write("------------------------------------------------", 'white');
        CLI::write("Tests Completed: $success/$total passed.", $success == $total ? 'green' : 'red');
    }
}
