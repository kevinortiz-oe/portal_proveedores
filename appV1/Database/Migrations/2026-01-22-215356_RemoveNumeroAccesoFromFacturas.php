<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveNumeroAccesoFromFacturas extends Migration
{
    public function up()
    {
        $this->forge->dropColumn('facturas', 'numero_acceso');
    }

    public function down()
    {
        $fields = [
            'numero_acceso' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ];
        $this->forge->addColumn('facturas', $fields);
    }
}
