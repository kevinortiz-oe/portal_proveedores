<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDetailsToFacturas extends Migration
{
    public function up()
    {
        $fields = [
            'nombre_emisor' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'nit_emisor' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'direccion_emisor' => ['type' => 'TEXT', 'null' => true],
            'nombre_receptor' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'nit_receptor' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'direccion_receptor' => ['type' => 'TEXT', 'null' => true],
            'fecha_certificacion' => ['type' => 'DATETIME', 'null' => true],
            'numero_acceso' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ];
        $this->forge->addColumn('facturas', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('facturas', [
            'nombre_emisor',
            'nit_emisor',
            'direccion_emisor',
            'nombre_receptor',
            'nit_receptor',
            'direccion_receptor',
            'fecha_certificacion',
            'numero_acceso'
        ]);
    }
}
