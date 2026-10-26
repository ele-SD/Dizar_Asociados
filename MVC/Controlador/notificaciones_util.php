<?php
function crear_notificacion(mysqli $conexion, int $usuarioId, string $mensaje, string $enlace = ""): void {
    try {
        $mensaje = mb_strimwidth($mensaje, 0, 255, "…");
        $enlaceDb = $enlace === "" ? null : $enlace;
        $stmt = $conexion->prepare("INSERT INTO notificaciones (usuario_id, mensaje, enlace) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $usuarioId, $mensaje, $enlaceDb);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log("No se pudo crear la notificación: " . $e->getMessage());
    }
}

// Avisa a todos los administradores activos
function notificar_admins(mysqli $conexion, string $mensaje, string $enlace = ""): void {
    try {
        $res = $conexion->query("SELECT id FROM usuarios WHERE rol = 'admin' AND activo = 1");
        while ($f = $res->fetch_assoc()) {
            crear_notificacion($conexion, (int) $f["id"], $mensaje, $enlace);
        }
    } catch (Throwable $e) {
        error_log("No se pudo avisar a los administradores: " . $e->getMessage());
    }
}

// Cuenta de usuario ligada a una ficha de abogado (o null si no tiene)
function usuario_id_de_abogado(mysqli $conexion, int $abogadoId): ?int {
    try {
        $stmt = $conexion->prepare("SELECT usuario_id FROM abogados WHERE id = ?");
        $stmt->bind_param("i", $abogadoId);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return ($fila && $fila["usuario_id"] !== null) ? (int) $fila["usuario_id"] : null;
    } catch (Throwable $e) {
        return null;
    }
}

// Cantidad de notificaciones sin leer (el número rojo de la campana)
function contar_notificaciones(mysqli $conexion, int $usuarioId): int {
    try {
        $stmt = $conexion->prepare("SELECT COUNT(*) AS total FROM notificaciones WHERE usuario_id = ? AND leida = 0");
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($fila["total"] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

// ---- Avisos concretos del módulo de casos ----

// Un cliente registró un caso nuevo -> se avisa a los administradores
function notificar_nuevo_caso(mysqli $conexion, int $casoId, string $titulo): void {
    notificar_admins($conexion, "Nuevo caso registrado: «" . $titulo . "».", "caso.php?id=" . $casoId);
}

// Se asignó un abogado -> se avisa al cliente y al abogado
function notificar_asignacion(mysqli $conexion, int $casoId, int $clienteId, string $titulo, int $abogadoId, string $abogadoNombre): void {
    $enlace = "caso.php?id=" . $casoId;
    crear_notificacion($conexion, $clienteId, "Tu caso «" . $titulo . "» fue asignado a " . $abogadoNombre . ".", $enlace);

    $usuarioAbogado = usuario_id_de_abogado($conexion, $abogadoId);
    if ($usuarioAbogado !== null) {
        crear_notificacion($conexion, $usuarioAbogado, "Se te asignó el caso «" . $titulo . "».", $enlace);
    }
}

// Cambió el estado o se agregó una nota -> se avisa al cliente
function notificar_estado(mysqli $conexion, int $casoId, int $clienteId, string $titulo, string $estadoTexto, bool $cambioEstado): void {
    $enlace = "caso.php?id=" . $casoId;
    if ($cambioEstado) {
        crear_notificacion($conexion, $clienteId, "Tu caso «" . $titulo . "» ahora está en estado «" . $estadoTexto . "».", $enlace);
    } else {
        crear_notificacion($conexion, $clienteId, "Hay una nueva nota en tu caso «" . $titulo . "».", $enlace);
    }
}


function notificar_mensaje_caso(mysqli $conexion, array $caso, string $rolRemitente, string $nombre): void {
    $enlace = "caso.php?id=" . (int) $caso["id"];
    $titulo = $caso["titulo"];

    if ($rolRemitente === "cliente") {
        $texto = $nombre . " escribió en el caso «" . $titulo . "».";
        $destino = !empty($caso["abogado_id"]) ? usuario_id_de_abogado($conexion, (int) $caso["abogado_id"]) : null;

        if ($destino !== null) {
            crear_notificacion($conexion, $destino, $texto, $enlace);
        } else {
            notificar_admins($conexion, $texto, $enlace);
        }
    } else {
        crear_notificacion($conexion, (int) $caso["cliente_id"], "Tienes un mensaje nuevo en tu caso «" . $titulo . "».", $enlace);
    }
}

// Se subió un documento de avance -> se avisa al cliente
function notificar_documento(mysqli $conexion, array $caso, string $nombreArchivo): void {
    crear_notificacion(
        $conexion,
        (int) $caso["cliente_id"],
        "Hay un documento nuevo en tu caso «" . $caso["titulo"] . "»: " . $nombreArchivo . ".",
        "caso.php?id=" . (int) $caso["id"]
    );
}