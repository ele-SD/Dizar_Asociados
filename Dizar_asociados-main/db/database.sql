CREATE DATABASE IF NOT EXISTS despacho_abogados CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE despacho_abogados;

-- Servicios 

CREATE TABLE IF NOT EXISTS servicios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Usuarios: login, registro, ajustes y roles
-- Incluye el bloqueo temporal por intentos fallidos

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    correo VARCHAR(120) NOT NULL UNIQUE,
    contrasena_hash VARCHAR(255) NOT NULL,
    tipo_documento ENUM('CC','CE') DEFAULT NULL,
    numero_documento VARCHAR(20) DEFAULT NULL UNIQUE,
    rol ENUM('admin','abogado','cliente') NOT NULL DEFAULT 'cliente',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_hasta DATETIME NULL DEFAULT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- Abogados 
-- usuario_id liga la ficha del abogado con su cuenta 

CREATE TABLE IF NOT EXISTS abogados (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NULL UNIQUE,
    nombre VARCHAR(120) NOT NULL,
    especialidad VARCHAR(100) NOT NULL,
    tarjeta_profesional VARCHAR(30) DEFAULT NULL,
    correo VARCHAR(120) DEFAULT NULL,
    foto VARCHAR(255) DEFAULT NULL,
    biografia TEXT,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_abogado_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;


-- Consultas / solicitudes de cita 

CREATE TABLE IF NOT EXISTS consultas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    correo VARCHAR(120) NOT NULL,
    telefono VARCHAR(20) DEFAULT NULL,
    servicio_id INT UNSIGNED DEFAULT NULL,
    mensaje TEXT NOT NULL,
    fecha_preferida DATETIME DEFAULT NULL,
    estado ENUM('pendiente','contactado','agendada','cerrada') NOT NULL DEFAULT 'pendiente',
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_consulta_servicio
        FOREIGN KEY (servicio_id) REFERENCES servicios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Casos jurídicos 
-- tipo: asesoría o demanda. El historial del cliente son sus casos.

CREATE TABLE IF NOT EXISTS casos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    abogado_id INT UNSIGNED DEFAULT NULL,
    servicio_id INT UNSIGNED DEFAULT NULL,
    tipo ENUM('asesoria','demanda') NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT NOT NULL,
    estado ENUM('recibido','asignado','en_proceso','cerrado') NOT NULL DEFAULT 'recibido',
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_caso_cliente
        FOREIGN KEY (cliente_id) REFERENCES usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_caso_abogado
        FOREIGN KEY (abogado_id) REFERENCES abogados(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_caso_servicio
        FOREIGN KEY (servicio_id) REFERENCES servicios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Seguimiento: cada cambio de estado del caso queda registrado 
CREATE TABLE IF NOT EXISTS caso_seguimiento (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    caso_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    estado ENUM('recibido','asignado','en_proceso','cerrado') NOT NULL,
    nota TEXT,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_seg_caso
        FOREIGN KEY (caso_id) REFERENCES casos(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_seg_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;


-- Mensajes del chat entre cliente y abogado, ligados a un caso 
-- leido = 0 sirve para avisar al personal de mensajes nuevos

CREATE TABLE IF NOT EXISTS mensajes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    caso_id INT UNSIGNED NOT NULL,
    autor_id INT UNSIGNED NOT NULL,
    contenido TEXT NOT NULL,
    leido TINYINT(1) NOT NULL DEFAULT 0,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_msg_caso
        FOREIGN KEY (caso_id) REFERENCES casos(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_msg_autor
        FOREIGN KEY (autor_id) REFERENCES usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;


-- Opiniones de los usuarios sobre su experiencia con los abogados
-- (abogado_id NULL = opinión general sobre el despacho)

CREATE TABLE IF NOT EXISTS opiniones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    abogado_id INT UNSIGNED DEFAULT NULL,
    calificacion TINYINT UNSIGNED NOT NULL,
    comentario TEXT NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_opinion_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_opinion_abogado
        FOREIGN KEY (abogado_id) REFERENCES abogados(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_calificacion CHECK (calificacion BETWEEN 1 AND 5)
) ENGINE=InnoDB;


-- Datos iniciales

INSERT IGNORE INTO servicios (nombre, descripcion) VALUES
('Derecho Civil', 'Contratos, responsabilidad civil, propiedad, sucesiones y procesos judiciales civiles.'),
('Derecho de Familia', 'Divorcios, custodia, alimentos, filiación y liquidación de sociedad conyugal.'),
('Derecho Laboral', 'Contratos de trabajo, despidos, liquidaciones, prestaciones sociales y litigios laborales.');

