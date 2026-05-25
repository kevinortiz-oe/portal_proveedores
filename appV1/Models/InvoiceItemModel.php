<?php

namespace App\Models;

use CodeIgniter\Model;

class InvoiceItemModel extends Model
{
    protected $table = 'detalles_factura';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $allowedFields = [
        'factura_id',
        'codigo',
        'descripcion',
        'cantidad',
        'unidad_medida',
        'precio_unitario',
        'importe_total',
        'monto_impuesto',
        'tipo_bien_servicio',
        'descuento',
        'otros_descuentos',
        'oc_detalle',
        'fecha_creacion'
    ];
    protected $useTimestamps = false;
    protected $dateFormat = 'date';
    protected $createdField = 'fecha_creacion';
    protected $updatedField = ''; // No updated_at in schema for items
}
