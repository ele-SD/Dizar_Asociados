-- ============================================================
-- Dizar Asociados - Tabla de opiniones
-- Se puede ejecutar más de una vez (IF NOT EXISTS).
-- abogado_id NULL = opinión general sobre el despacho.
-- ============================================================
USE despacho_abogados;

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
