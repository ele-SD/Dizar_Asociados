<?php
// Datos y funciones compartidos por las pantallas de casos (RF 2.1 a 2.4)

const CASO_ESTADOS = [
    "recibido"   => "Recibido",
    "asignado"   => "Asignado",
    "en_proceso" => "En proceso",
    "cerrado"    => "Cerrado",
];

const CASO_TIPOS = [
    "asesoria" => "Asesoría",
    "demanda"  => "Demanda",
];

// Devuelve el id de la ficha de abogado ligada a un usuario, o null si no tiene
function abogado_id_de_usuario(mysqli $conexion, int $usuarioId): ?int {
    $stmt = $conexion->prepare("SELECT id FROM abogados WHERE usuario_id = ? AND activo = 1");
    $stmt->bind_param("i", $usuarioId);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $fila ? (int) $fila["id"] : null;
}

// Guarda un cambio de estado o una nota en el historial del caso
function registrar_seguimiento(mysqli $conexion, int $casoId, int $usuarioId, string $estado, string $nota): void {
    $stmt = $conexion->prepare(
        "INSERT INTO caso_seguimiento (caso_id, usuario_id, estado, nota) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("iiss", $casoId, $usuarioId, $estado, $nota);
    $stmt->execute();
    $stmt->close();
}