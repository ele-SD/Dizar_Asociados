
CREATE DATABASE IF NOT EXISTS despacho_abogados CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE despacho_abogados;

-- Servicios (apartado/servicios.php)
CREATE TABLE IF NOT EXISTS servicios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Abogados (apartado/nosotros.php)
CREATE TABLE IF NOT EXISTS abogados (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    especialidad VARCHAR(100) NOT NULL,
    tarjeta_profesional VARCHAR(30) DEFAULT NULL,
    correo VARCHAR(120) DEFAULT NULL,
    foto VARCHAR(255) DEFAULT NULL,
    biografia TEXT,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Consultas / citas (formulario de apartado/contacto.php y "Agendar consulta")
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

-- Usuarios (login, registro y ajustes)
-- La contraseña se guarda con password_hash() de PHP, nunca en texto plano
CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    correo VARCHAR(120) NOT NULL UNIQUE,
    contrasena_hash VARCHAR(255) NOT NULL,
    tipo_documento ENUM('CC','CE') DEFAULT NULL,
    numero_documento VARCHAR(15) DEFAULT NULL UNIQUE,
    rol ENUM('admin','abogado') NOT NULL DEFAULT 'abogado',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Datos iniciales (INSERT IGNORE evita duplicados si se importa dos veces)
INSERT IGNORE INTO servicios (nombre, descripcion) VALUES
('Derecho Civil', 'Contratos, responsabilidad civil, propiedad, sucesiones y procesos judiciales civiles.'),
('Derecho de Familia', 'Divorcios, custodia, alimentos, filiación y liquidación de sociedad conyugal.'),
('Derecho Laboral', 'Contratos de trabajo, despidos, liquidaciones, prestaciones sociales y litigios laborales.');