<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddNoPedidoToFacturas extends Migration
{
    public function up()
    {
        $fields = [
            'no_pedido' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
                'null' => true,
                'after' => 'total_otros_descuentos' // Place it after the last added field usually
            ],
        ];

        $this->forge->addColumn('facturas', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('facturas', 'no_pedido');
    }
}
