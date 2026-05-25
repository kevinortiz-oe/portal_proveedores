<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class InitialSchema extends Migration
{
    public function up()
    {
        // 1. Users Table
        $this->forge->addField([
            'id' => ['type' => 'SERIAL', 'unsigned' => true], // SERIAL for Postgres
            'username' => ['type' => 'VARCHAR', 'constraint' => 100],
            'email' => ['type' => 'VARCHAR', 'constraint' => 255, 'unique' => true],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => true],
            'updated_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('users', true);
        // 2. Proveedores Table
        $this->forge->addField([
            'id' => ['type' => 'SERIAL', 'unsigned' => true],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 255],
            'nit' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'direccion' => ['type' => 'TEXT', 'null' => true],
            'telefono' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'email' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => true],
            'updated_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('proveedores', true);
        // 3. Facturas Table (Base fields only - others added by migrations)
        $this->forge->addField([
            'id' => ['type' => 'SERIAL', 'unsigned' => true],
            'usuario_id' => ['type' => 'INT', 'null' => true],
            'proveedor' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], // Legacy field
            'fecha_factura' => ['type' => 'DATE', 'null' => true],
            'uuid_sat' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'moneda' => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'GTQ'],
            'tipo_cambio' => ['type' => 'DECIMAL', 'constraint' => '10,4', 'default' => 1.0000],
            'subtotal' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0.00],
            'total_impuestos' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0.00],
            'total' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0.00],
            'nombre_archivo_xml_original' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'nombre_archivo_pdf_original' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'nombre_archivo_xml_almacenado' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'nombre_archivo_pdf_almacenado' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'ruta_archivo' => ['type' => 'TEXT', 'null' => true],
            'estado' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'pendiente'],
            'fuente_extraccion' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'mensaje_error' => ['type' => 'TEXT', 'null' => true],
            'fecha_creacion' => ['type' => 'TIMESTAMP', 'null' => true],
            'fecha_actualizacion' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('facturas', true);
        // 4. Detalles Factura
        $this->forge->addField([
            'id' => ['type' => 'SERIAL', 'unsigned' => true],
            'factura_id' => ['type' => 'INT'],
            'cantidad' => ['type' => 'DECIMAL', 'constraint' => '15,4', 'default' => 0.0000],
            'descripcion' => ['type' => 'TEXT', 'null' => true],
            'codigo' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'unidad_medida' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'precio_unitario' => ['type' => 'DECIMAL', 'constraint' => '15,4', 'default' => 0.0000],
            'importe_total' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0.00],
            'monto_impuesto' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0.00],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => true],
            'updated_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('factura_id', 'facturas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('detalles_factura', true);
    }
    public function down()
    {
        $this->forge->dropTable('detalles_factura', true);
        $this->forge->dropTable('facturas', true);
        $this->forge->dropTable('proveedores', true);
        $this->forge->dropTable('users', true);
    }
}
