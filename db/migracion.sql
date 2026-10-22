-- ============================================================
-- Dizar Asociados - MIGRACIÓN para bases ya creadas
-- Úsalo si tu tabla `usuarios` viene de la versión anterior
-- (roles solo 'admin' y 'abogado', sin columnas de bloqueo).
-- Se puede ejecutar más de una vez sin problema (MariaDB / XAMPP).
-- No borra ningún dato.
-- ============================================================

USE despacho_abogados;

-- 1) Agregar el rol 'cliente' y dejarlo como valor por defecto.
--    Sin esto, cada persona que se registra quedaría como 'abogado'
--    y vería las consultas de los clientes.
ALTER TABLE usuarios
  MODIFY rol ENUM('admin','abogado','cliente') NOT NULL DEFAULT 'cliente';

-- 2) Columnas para el bloqueo temporal por intentos fallidos
ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER activo,
  ADD COLUMN IF NOT EXISTS bloqueado_hasta DATETIME NULL DEFAULT NULL AFTER intentos_fallidos;

-- 3) Longitud del número de documento igual a la de la base principal
ALTER TABLE usuarios
  MODIFY numero_documento VARCHAR(20) DEFAULT NULL;

-- ------------------------------------------------------------
-- REVISIÓN MANUAL (no se ejecuta sola)
-- Las cuentas creadas antes de esta migración por el formulario
-- de registro quedaron como 'abogado'. Mira quiénes son:
--     SELECT id, usuario, correo, rol FROM usuarios;
-- y pasa a 'cliente' a los que no sean abogados reales:
--     UPDATE usuarios SET rol = 'cliente' WHERE id IN (2, 3);
-- Para volver admin a alguien:
--     UPDATE usuarios SET rol = 'admin' WHERE correo = 'tu_correo@ejemplo.com';
-- Para desbloquear una cuenta:
--     UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE correo = 'correo@ejemplo.com';
-- ------------------------------------------------------------
