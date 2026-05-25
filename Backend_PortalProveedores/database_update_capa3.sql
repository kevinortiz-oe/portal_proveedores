-- Capa 3: Diseño de Base de Datos para Control de Flujo (Human-in-the-loop)

-- 1. Agregar la columna para almacenar el JSON de errores (compatible con PostgreSQL)
ALTER TABLE facturas ADD COLUMN errores_validacion JSONB NULL;
-- Si usas MySQL en vez de PostgreSQL, sería:
-- ALTER TABLE facturas ADD COLUMN errores_validacion JSON NULL;

-- 2. Asegurarse que la columna 'estado' permite los nuevos valores más largos
ALTER TABLE facturas ALTER COLUMN estado TYPE VARCHAR(50);
-- MySQL equivalente: ALTER TABLE facturas MODIFY COLUMN estado VARCHAR(50);

-- Nota: Los nuevos estados que se insertarán serán:
-- 'AUTO-APROBADO'
-- 'PENDIENTE DE REVISION'
