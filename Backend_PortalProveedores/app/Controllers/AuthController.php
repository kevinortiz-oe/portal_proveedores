<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\UserModel;
use App\Models\ProviderModel;
use App\Models\UserSessionModel;

class AuthController extends BaseController
{
    use ResponseTrait;

    public function login()
    {
        // Obtener datos del request JSON
        $json = $this->request->getJSON();

        if (!$json) {
            return $this->fail('No se enviaron datos', 400);
        }

        $correo = $json->email ?? null;
        $password = $json->password ?? null;
        $codigoProveedor = $json->providerCode ?? null;

        if (!$correo || !$password || !$codigoProveedor) {
            return $this->fail('Faltan datos requeridos (email, password, providerCode)', 400);
        }

        // 1. Validar Código de Proveedor
        $providerModel = new ProviderModel();
        $provider = $providerModel->where('codigo_proveedor', $codigoProveedor)->first();

        if (!$provider) {
            return $this->failNotFound('Código de Proveedor inválido');
        }

        if (!$provider['activo']) {
            return $this->failForbidden('El proveedor no está activo');
        }

        // 2. Buscar usuario por correo (independiente de su proveedor_id asignado)
        $userModel = new UserModel();
        $user = $userModel->where('correo', $correo)->first();

        if (!$user) {
            // Por seguridad, mensaje genérico para no revelar existencia de usuario.
            return $this->failUnauthorized('Credenciales inválidas');
        }

        if (!$user['activo']) {
            return $this->failForbidden('Usuario inactivo');
        }

        // 3. Verificar contraseña
        if (!password_verify($password, $user['contrasena_hash'])) {
            return $this->failUnauthorized('Credenciales inválidas');
        }

        // 4. Generar Token de Sesión
        $sessionToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // 5. Guardar sesión - Obtener IP real (manejar proxies y normalizar localhost)
        $sessionModel = new UserSessionModel();

        $clientIp = $this->request->getIPAddress();
        // Normalizar IPv6 localhost a IPv4
        if ($clientIp === '::1') {
            $clientIp = '127.0.0.1';
        }
        // Intentar obtener IP real si está detrás de proxy
        $forwardedFor = $this->request->getHeaderLine('X-Forwarded-For');
        if (!empty($forwardedFor)) {
            $ips = explode(',', $forwardedFor);
            $clientIp = trim($ips[0]);
        }

        $sessionModel->insert([
            'usuario_id' => $user['id'],
            'token_sesion' => $sessionToken,
            'direccion_ip' => $clientIp,
            'agente_usuario' => $this->request->getUserAgent()->getAgentString(),
            'fecha_inicio' => date('Y-m-d H:i:s'),
            'fecha_expiracion' => $expiresAt,
            'activo' => true
        ]);

        // 6. Retornar respuesta exitosa
        return $this->respond([
            'status' => 200,
            'message' => 'Login exitoso',
            'token' => $sessionToken,
            'user' => [
                'id' => $user['id'],
                'nombre' => $user['nombre_completo'],
                'correo' => $user['correo'],
                'rol' => $user['rol'],
                'provider' => [
                    'id' => $provider['id'],
                    'code' => $provider['codigo_proveedor'],
                    'name' => $provider['nombre']
                ]
            ]
        ]);
    }

    /**
     * Cerrar sesión y actualizar fecha_cierre
     */
    public function logout()
    {
        $json = $this->request->getJSON();
        $token = $json->token ?? null;

        if (!$token) {
            return $this->fail('Token requerido', 400);
        }

        $sessionModel = new UserSessionModel();

        // Buscar sesión activa por token
        $session = $sessionModel->where('token_sesion', $token)
            ->where('activo', true)
            ->first();

        if (!$session) {
            return $this->fail('Sesión no encontrada o ya cerrada', 404);
        }

        // Actualizar fecha_cierre y marcar como inactiva
        $sessionModel->update($session['id'], [
            'fecha_cierre' => date('Y-m-d H:i:s'),
            'activo' => false
        ]);

        return $this->respond([
            'status' => 200,
            'message' => 'Sesión cerrada correctamente'
        ]);
    }

    // Método temporal para crear un usuario de prueba (hash password)
    // ELIMINAR EN PRODUCCIÓN
    public function createTestUser()
    {
        $providerCode = $this->request->getVar('code');
        $email = $this->request->getVar('email');
        $pass = $this->request->getVar('password');

        if (!$providerCode || !$email || !$pass)
            return $this->fail('Datos incompletos');

        $provModel = new ProviderModel();
        $userModel = new UserModel();

        // Check provider
        $prov = $provModel->where('codigo_proveedor', $providerCode)->first();
        if (!$prov) {
            // Create provider
            $provId = $provModel->insert([
                'codigo_proveedor' => $providerCode,
                'nombre' => 'Proveedor ' . $providerCode,
                'activo' => true
            ]);
        } else {
            $provId = $prov['id'];
        }

        // Create user
        $userId = $userModel->insert([
            'proveedor_id' => $provId,
            'correo' => $email,
            'contrasena_hash' => password_hash($pass, PASSWORD_DEFAULT),
            'nombre_completo' => 'Usuario Test',
            'rol' => 'admin',
            'activo' => true
        ]);

        return $this->respondCreated(['id' => $userId, 'msg' => 'Usuario creado']);
    }

    /**
     * Crear usuario desde el panel de administración (Solo Admin)
     */
    public function createUser()
    {
        $json = $this->request->getJSON();

        if (!$json) {
            return $this->fail('No se enviaron datos', 400);
        }

        // Validar campos requeridos
        $providerCode = $json->providerCode ?? null;
        $email = $json->email ?? null;
        $password = $json->password ?? null;
        $nombreCompleto = $json->nombreCompleto ?? null;
        $rol = $json->rol ?? 'usuario';

        if (!$providerCode || !$email || !$password || !$nombreCompleto) {
            return $this->fail('Faltan datos requeridos (providerCode, email, password, nombreCompleto)', 400);
        }

        $provModel = new ProviderModel();
        $userModel = new UserModel();

        // Verificar que el proveedor existe
        $proveedor = $provModel->where('codigo_proveedor', $providerCode)->first();
        if (!$proveedor) {
            return $this->fail('El código de proveedor no existe', 404);
        }

        // Verificar que el email no esté registrado
        $existingUser = $userModel->where('correo', $email)->first();
        if ($existingUser) {
            return $this->fail('El correo ya está registrado', 409);
        }

        // Crear usuario
        $userId = $userModel->insert([
            'proveedor_id' => $proveedor['id'],
            'correo' => $email,
            'contrasena_hash' => password_hash($password, PASSWORD_DEFAULT),
            'nombre_completo' => $nombreCompleto,
            'rol' => $rol,
            'activo' => true
        ]);

        if (!$userId) {
            return $this->fail('Error al crear el usuario', 500);
        }

        return $this->respondCreated([
            'status' => 201,
            'message' => 'Usuario creado exitosamente',
            'user' => [
                'id' => $userId,
                'email' => $email,
                'nombre' => $nombreCompleto,
                'rol' => $rol,
                'providerCode' => $providerCode
            ]
        ]);
    }

    /**
     * Obtener lista de proveedores (para dropdown en formulario de creación)
     */
    public function getProviders()
    {
        $provModel = new ProviderModel();
        // Filtrar solo los que están activos (Usar true para compatibilidad con PostgreSQL)
        $providers = $provModel->where('activo', true)->findAll(); 

        return $this->respond([
            'status' => 200,
            'providers' => array_map(function ($p) {
                return [
                    'id' => $p['id'],
                    'codigo' => $p['codigo_proveedor'],
                    'nombre' => $p['nombre'],
                    'empresa_compra' => $p['empresa_compra'] ?? null,
                    'activo' => (bool) $p['activo']  // campo que faltaba
                ];
            }, $providers)
        ]);
    }

    /**
     * Listar empresas compradoras (para dropdown en formulario de proveedor)
     */
    public function getEmpresas()
    {
        $db = \Config\Database::connect();
        $empresas = $db->query('SELECT id_empresa, numero_empresa, nombre_empresa FROM empresas ORDER BY nombre_empresa ASC')->getResultArray();

        return $this->respond([
            'status' => 200,
            'empresas' => $empresas
        ]);
    }

    /**
     * Crear proveedor desde el panel de administración (Solo Admin)
     */
    public function createProvider()
    {
        $json = $this->request->getJSON();

        if (!$json) {
            return $this->fail('No se enviaron datos', 400);
        }

        $codigo = $json->codigo ?? null;
        $nombre = $json->nombre ?? null;
        $numeroEmpresa = isset($json->empresa_compra) ? (int) $json->empresa_compra : null;

        if (!$codigo || !$nombre) {
            return $this->fail('El código y nombre del proveedor son requeridos', 400);
        }

        if (!$numeroEmpresa) {
            return $this->fail('Debe seleccionar una empresa compradora', 400);
        }

        $provModel = new ProviderModel();

        // Verificar que el código no exista
        $existing = $provModel->where('codigo_proveedor', $codigo)->first();
        if ($existing) {
            return $this->fail('El código de proveedor ya existe', 409);
        }

        // Crear proveedor
        $provId = $provModel->insert([
            'codigo_proveedor' => $codigo,
            'nombre' => $nombre,
            'empresa_compra' => $numeroEmpresa,
            'activo' => true
        ]);

        if (!$provId) {
            return $this->fail('Error al crear el proveedor', 500);
        }

        return $this->respondCreated([
            'status' => 201,
            'message' => 'Proveedor creado exitosamente',
            'provider' => [
                'id' => $provId,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'empresa_compra' => $numeroEmpresa
            ]
        ]);
    }

    /**
     * Listar todos los usuarios (para gestión en panel admin)
     */
    public function getUsers()
    {
        $userModel = new UserModel();
        $provModel = new ProviderModel();
        // Filtrar solo usuarios activos (Usar true para compatibilidad con PostgreSQL)
        $users = $userModel->where('activo', true)->findAll();
        $providers = $provModel->findAll();

        $provMap = [];
        foreach ($providers as $p) {
            $provMap[$p['id']] = ['codigo' => $p['codigo_proveedor'], 'nombre' => $p['nombre']];
        }

        return $this->respond([
            'status' => 200,
            'users' => array_map(function ($u) use ($provMap) {
                return [
                    'id' => $u['id'],
                    'nombre_completo' => $u['nombre_completo'],
                    'correo' => $u['correo'],
                    'rol' => $u['rol'],
                    'activo' => $u['activo'],
                    'proveedor_id' => $u['proveedor_id'],
                    'proveedor_codigo' => $provMap[$u['proveedor_id']]['codigo'] ?? '-',
                    'proveedor_nombre' => $provMap[$u['proveedor_id']]['nombre'] ?? '-',
                ];
            }, $users)
        ]);
    }

    /**
     * Actualizar usuario (desde panel admin)
     */
    public function updateUser($id)
    {
        $json = $this->request->getJSON();
        if (!$json)
            return $this->fail('No se enviaron datos', 400);

        $userModel = new UserModel();
        $user = $userModel->find($id);
        if (!$user)
            return $this->failNotFound('Usuario no encontrado');

        $data = [];
        if (isset($json->nombre_completo))
            $data['nombre_completo'] = $json->nombre_completo;
        if (isset($json->correo))
            $data['correo'] = $json->correo;
        if (isset($json->rol))
            $data['rol'] = $json->rol;
        if (isset($json->activo))
            $data['activo'] = (bool) $json->activo;
        if (isset($json->proveedor_id))
            $data['proveedor_id'] = (int) $json->proveedor_id;
        if (!empty($json->password))
            $data['contrasena_hash'] = password_hash($json->password, PASSWORD_DEFAULT);

        if (empty($data))
            return $this->fail('Sin datos para actualizar', 400);

        $userModel->update($id, $data);

        return $this->respond(['status' => 200, 'message' => 'Usuario actualizado correctamente']);
    }

    /**
     * Actualizar proveedor (desde panel admin)
     */
    public function updateProvider($id)
    {
        $json = $this->request->getJSON();
        if (!$json)
            return $this->fail('No se enviaron datos', 400);

        $provModel = new ProviderModel();
        $prov = $provModel->find($id);
        if (!$prov)
            return $this->failNotFound('Proveedor no encontrado');

        $data = [];
        if (isset($json->nombre))
            $data['nombre'] = $json->nombre;
        if (isset($json->codigo_proveedor))
            $data['codigo_proveedor'] = $json->codigo_proveedor;
        if (isset($json->activo))
            $data['activo'] = (bool) $json->activo;
        if (isset($json->empresa_compra))
            $data['empresa_compra'] = (int) $json->empresa_compra;

        if (empty($data))
            return $this->fail('Sin datos para actualizar', 400);

        $provModel->update($id, $data);
        return $this->respond(['status' => 200, 'message' => 'Proveedor actualizado correctamente']);
    }
}
