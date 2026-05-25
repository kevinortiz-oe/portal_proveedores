<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDiscountTotalsToFacturas extends Migration
{
    public function up()
    {
        $fields = [
            'total_descuento' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0.00],
            'total_otros_descuentos' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0.00],
        ];
        $this->forge->addColumn('facturas', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('facturas', ['total_descuento', 'total_otros_descuentos']);
    }
}
