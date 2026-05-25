<?php

namespace App\Models;

use CodeIgniter\Model;

class InvoiceModel extends Model
{
    protected $table = 'facturas';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $allowedFields = [
        'proveedor_id',
        'proveedor',
        'usuario_id',
        'serie',
        'numero_dte',
        'fecha_factura',
        'uuid_sat',
        'moneda',
        'tipo_cambio',
        'subtotal',
        'total_impuestos',
        'total',
        'nombre_archivo_xml_original',
        'nombre_archivo_pdf_original',
        'nombre_archivo_xml_almacenado',
        'nombre_archivo_pdf_almacenado',
        'ruta_archivo',
        'estado',
        'fuente_extraccion',
        'mensaje_error',
        'nombre_emisor',
        'nit_emisor',
        'direccion_emisor',
        'nombre_receptor',
        'nit_receptor',
        'direccion_receptor',
        'fecha_certificacion',
        'total_descuento',
        'total_otros_descuentos',
        'no_pedido',
        'empresa_compra',
        'dias_credito',
        'termino_compra',
        'fecha_vencimiento',
        'orden_pedido'
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'fecha_creacion';
    protected $updatedField = 'fecha_actualizacion';
}
