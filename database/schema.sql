-- Esquema de Base de Datos para Sistema de Procesamiento de Facturas
-- Motor: PostgreSQL

-- Extension UUID eliminada por solicitud del usuario


-- 1. Tabla Proveedores
CREATE TABLE proveedores (
    id SERIAL PRIMARY KEY,
    codigo_proveedor VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(255) NOT NULL,
    direccion TEXT,
    telefono VARCHAR(50),
    
    -- Campos de Auditoría
    fecha_creacion TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    activo BOOLEAN DEFAULT TRUE
);

-- 2. Tabla Usuarios
CREATE TABLE usuarios (
    id SERIAL PRIMARY KEY,
    proveedor_id INTEGER REFERENCES proveedores(id) ON DELETE SET NULL,
    correo VARCHAR(255) NOT NULL UNIQUE,
    contrasena_hash VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(255),
    rol VARCHAR(50) DEFAULT 'usuario', -- 'admin', 'usuario', etc.
    
    -- Campos de Auditoría
    fecha_creacion TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    activo BOOLEAN DEFAULT TRUE
);

-- 3. Tabla Sesiones de Usuario (Auditoría/Seguridad)
CREATE TABLE sesiones_usuarios (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    token_sesion VARCHAR(255), -- Firma JWT o ID de sesión
    direccion_ip VARCHAR(45),
    agente_usuario TEXT, -- User Agent
    fecha_inicio TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion TIMESTAMP WITH TIME ZONE,
    fecha_cierre TIMESTAMP WITH TIME ZONE,
    activo BOOLEAN DEFAULT TRUE
);

-- 4. Tabla Facturas (Cabecera)
CREATE TABLE facturas (
    id SERIAL PRIMARY KEY,
    proveedor_id INTEGER NOT NULL REFERENCES proveedores(id),
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id),
    
    -- Detalles de Factura (Cabecera)
    numero_factura VARCHAR(100),
    fecha_factura DATE,
    uuid_sat VARCHAR(100), -- Folio Fiscal UUID
    moneda VARCHAR(10),
    tipo_cambio DECIMAL(10, 4) DEFAULT 1.0,
    subtotal DECIMAL(15, 2),
    total_impuestos DECIMAL(15, 2),
    total DECIMAL(15, 2),
    
    -- Rastreo de Archivos
    nombre_archivo_xml_original VARCHAR(255),
    nombre_archivo_pdf_original VARCHAR(255),
    nombre_archivo_xml_almacenado VARCHAR(255),
    nombre_archivo_pdf_almacenado VARCHAR(255),
    ruta_archivo VARCHAR(500),
    
    -- Estado
    estado VARCHAR(50) DEFAULT 'pendiente', -- 'pendiente', 'procesado', 'fallido'
    fuente_extraccion VARCHAR(20), -- 'xml', 'pdf', 'manual'
    mensaje_error TEXT,
    
    -- Campos de Auditoría
    fecha_creacion TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 5. Tabla Detalles Factura (Partidas)
CREATE TABLE detalles_factura (
    id SERIAL PRIMARY KEY,
    factura_id INTEGER NOT NULL REFERENCES facturas(id) ON DELETE CASCADE,
    
    -- Detalles del Ítem
    clave_producto_sat VARCHAR(50), -- Clave Prod Serv SAT
    descripcion TEXT,
    cantidad DECIMAL(12, 4),
    unidad_medida VARCHAR(50), -- Clave Unidad
    precio_unitario DECIMAL(15, 4),
    importe_total DECIMAL(15, 4),
    
    -- Impuestos por partida (opcional)
    monto_impuesto DECIMAL(15, 4),
    
    -- Auditoría
    fecha_creacion TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Índices
CREATE INDEX idx_usuarios_correo ON usuarios(correo);
CREATE INDEX idx_sesiones_token ON sesiones_usuarios(token_sesion);
CREATE INDEX idx_facturas_proveedor ON facturas(proveedor_id);
CREATE INDEX idx_facturas_uuid ON facturas(uuid_sat);
CREATE INDEX idx_detalles_factura_id ON detalles_factura(factura_id);

-- Comentarios
COMMENT ON TABLE proveedores IS 'Almacena la información de los proveedores identificados por codigo_proveedor';
COMMENT ON TABLE usuarios IS 'Almacena usuarios del sistema. El login usa correo y codigo_proveedor vinculado';
COMMENT ON TABLE sesiones_usuarios IS 'Rastrea inicios de sesión y sesiones activas para auditoría de seguridad';
COMMENT ON TABLE facturas IS 'Almacena la cabecera de las facturas parseadas de XML o PDF';
COMMENT ON TABLE detalles_factura IS 'Almacena las partidas o ítems extraídos de la factura';
