<?php

namespace App\Models;

use CodeIgniter\Model;

class UserSessionModel extends Model
{
    protected $table = 'sesiones_usuarios';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'usuario_id',
        'token_sesion',
        'direccion_ip',
        'agente_usuario',
        'fecha_inicio',
        'fecha_expiracion',
        'fecha_cierre',
        'activo'
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = false; // We manage timestamps manually for this table mostly
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
}
