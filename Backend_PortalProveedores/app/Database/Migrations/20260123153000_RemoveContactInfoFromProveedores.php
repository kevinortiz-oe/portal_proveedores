<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveContactInfoFromProveedores extends Migration
{
    public function up()
    {
        // Drop direccion and telefono safely
        $this->db->query("ALTER TABLE proveedores DROP COLUMN IF EXISTS direccion");
        $this->db->query("ALTER TABLE proveedores DROP COLUMN IF EXISTS telefono");

        // Attempt to drop email_contacto if it exists (safe drop)
        $this->db->query("ALTER TABLE proveedores DROP COLUMN IF EXISTS email_contacto");
    }

    public function down()
    {
        // Restore columns as nullable
        $fields = [
            'direccion' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'telefono' => [
                'type' => 'VARCHAR',
                'constraint' => '50',
                'null' => true,
            ],
        ];
        $this->forge->addColumn('proveedores', $fields);
    }
}
