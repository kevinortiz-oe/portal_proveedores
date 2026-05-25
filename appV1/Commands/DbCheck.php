<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class DbCheck extends BaseCommand
{
    protected $group = 'Debug';
    protected $name = 'db:check';
    protected $description = 'Displays columns of providers, users, and facturas tables.';

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        $tables = ['proveedores', 'usuarios', 'facturas'];

        foreach ($tables as $table) {
            CLI::write("--- TABLE: $table ---", 'yellow');
            if ($db->tableExists($table)) {
                $fields = $db->getFieldNames($table);
                foreach ($fields as $field) {
                    CLI::write($field);
                }
            } else {
                CLI::error("Table $table does not exist.");
            }
            CLI::write("");
        }
    }
}
