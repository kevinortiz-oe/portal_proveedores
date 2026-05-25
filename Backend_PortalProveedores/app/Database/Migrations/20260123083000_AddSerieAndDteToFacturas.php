<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSerieAndDteToFacturas extends Migration
{
    public function up()
    {
        $this->forge->addColumn('facturas', [
            'serie' => [
                'type' => 'VARCHAR',
                'constraint' => '50',
                'null' => true,
                'after' => 'id',
            ],
            'numero_dte' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
                'null' => true,
                'after' => 'serie',
            ],
        ]);

        // Migrar datos existentes: copiar numero_factura a numero_dte
        // Usamos query directa porque DB Forge no hace updates de data
        // $this->db->query("UPDATE facturas SET numero_dte = numero_factura WHERE numero_dte IS NULL");

        // Opcional: Eliminar la columna anterior, pero por seguridad la dejaremos un momento
        // o la eliminamos segun el plan. El plan decia "Eliminar columna numero_factura".
        // Procedemos a eliminarla.
        // $this->forge->dropColumn('facturas', 'numero_factura');
    }

    public function down()
    {
        // Revertir cambios
        $this->forge->addColumn('facturas', [
            'numero_factura' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true,
            ],
        ]);

        $this->db->query("UPDATE facturas SET numero_factura = numero_dte WHERE numero_factura IS NULL");

        $this->forge->dropColumn('facturas', ['serie', 'numero_dte']);
    }
}
