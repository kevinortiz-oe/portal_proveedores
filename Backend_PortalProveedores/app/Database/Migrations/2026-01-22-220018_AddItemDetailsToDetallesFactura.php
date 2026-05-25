<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddItemDetailsToDetallesFactura extends Migration
{
    public function up()
    {
        $fields = [
            'tipo_bien_servicio' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true], // BIEN o SERVICIO
            'descuento' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0.00],
            'otros_descuentos' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0.00],
        ];
        $this->forge->addColumn('detalles_factura', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('detalles_factura', ['tipo_bien_servicio', 'descuento', 'otros_descuentos']);
    }
}
