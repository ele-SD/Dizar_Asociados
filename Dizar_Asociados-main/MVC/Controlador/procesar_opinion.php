<?php
session_start();
require_once __DIR__ . '/../Modelo/conexion.php';

// Solo se acepta por POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../Vista/opiniones.php");
    exit();
}

// Hay que haber iniciado sesión para opinar
if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../Vista/login.php");
    exit();
}

// Token CSRF
if (empty($_SESSION["csrf"]) || !hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) {
    die("Solicitud no válida.");
}

// Honeypot anti-spam
if (!empty($_POST["sitio_web"] ?? "")) {
    header("Location: ../Vista/opiniones.php");
    exit();
}

// Redirige a opiniones.php dejando un mensaje y, si hubo error, lo que el usuario escribió
function volver(string $tipo, string $mensaje, array $viejos = []): void {
    $_SESSION["flash_opiniones"] = ["tipo" => $tipo, "mensaje" => $mensaje];
    $_SESSION["viejos_opiniones"] = $viejos;
    header("Location: ../Vista/opiniones.php");
    exit();
}

$abogadoId    = (int) ($_POST["abogado_id"] ?? 0);   // 0 = opinión general
$calificacion = (int) ($_POST["calificacion"] ?? 0);
$comentario   = trim($_POST["comentario"] ?? "");

$viejos = [
    "abogado_id" => $abogadoId, "calificacion" => $calificacion, "comentario" => $comentario,
];

// Validaciones
if ($calificacion < 1 || $calificacion > 5) {
    volver("error", "Selecciona una calificación de 1 a 5.", $viejos);
}
if (mb_strlen($comentario) < 10) {
    volver("error", "Cuéntanos un poco más sobre tu experiencia (mínimo 10 caracteres).", $viejos);
}
if (mb_strlen($comentario) > 1000) {
    volver("error", "El comentario es demasiado largo (máximo 1000 caracteres).", $viejos);
}

// Si eligió un abogado, debe existir y estar activo
if ($abogadoId > 0) {
    $stmt = $conexion->prepare("SELECT id FROM abogados WHERE id = ? AND activo = 1");
    $stmt->bind_param("i", $abogadoId);
    $stmt->execute();
    $existe = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$existe) {
        volver("error", "Selecciona un abogado válido.", $viejos);
    }
} else {
    $abogadoId = null;
}

$usuarioId = (int) $_SESSION["usuario_id"];

$stmt = $conexion->prepare(
    "INSERT INTO opiniones (usuario_id, abogado_id, calificacion, comentario) VALUES (?, ?, ?, ?)"
);
$stmt->bind_param("iiis", $usuarioId, $abogadoId, $calificacion, $comentario);

if ($stmt->execute()) {
    $stmt->close();
    volver("exito", "¡Gracias por compartir tu experiencia!");
}

$stmt->close();
volver("error", "No pudimos guardar tu opinión. Intenta de nuevo en unos minutos.", $viejos);
