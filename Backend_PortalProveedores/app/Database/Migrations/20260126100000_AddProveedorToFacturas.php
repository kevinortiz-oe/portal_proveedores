<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProveedorToFacturas extends Migration
{
    public function up()
    {
        $fields = [
            'proveedor' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
                'after' => 'proveedor_id'
            ],
        ];
        $this->forge->addColumn('facturas', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('facturas', 'proveedor');
    }
}
