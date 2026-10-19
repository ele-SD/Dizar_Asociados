<?php
session_start();
require_once __DIR__ . '/../Modelo/conexion.php';

// Solo se acepta por POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../Vista/contacto.php");
    exit();
}

// Token CSRF
if (empty($_SESSION["csrf"]) || !hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) {
    die("Solicitud no válida.");
}

// Honeypot anti-spam: un humano nunca llena este campo (está oculto)
if (!empty($_POST["sitio_web"] ?? "")) {
    header("Location: ../Vista/contacto.php");
    exit();
}

// Redirige a contacto.php dejando un mensaje y, si hubo error, lo que el usuario escribió
function volver(string $tipo, string $mensaje, array $viejos = []): void {
    $_SESSION["flash_contacto"] = ["tipo" => $tipo, "mensaje" => $mensaje];
    $_SESSION["viejos_contacto"] = $viejos;
    header("Location: ../Vista/contacto.php");
    exit();
}

$nombre   = trim($_POST["nombre"] ?? "");
$correo   = trim($_POST["correo"] ?? "");
$telefono = trim($_POST["telefono"] ?? "");
$area     = trim($_POST["area"] ?? "");
$mensaje  = trim($_POST["mensaje"] ?? "");

$viejos = [
    "nombre" => $nombre, "correo" => $correo, "telefono" => $telefono,
    "area" => $area, "mensaje" => $mensaje,
];

// Validaciones
if ($nombre === "" || $correo === "" || $telefono === "" || $area === "" || $mensaje === "") {
    volver("error", "Por favor completa todos los campos.", $viejos);
}
if (mb_strlen($nombre) > 120 || mb_strlen($correo) > 120) {
    volver("error", "El nombre o el correo es demasiado largo.", $viejos);
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    volver("error", "El correo ingresado no es válido.", $viejos);
}
if (!preg_match('/^\+?[0-9\s\-]{7,20}$/', $telefono)) {
    volver("error", "El teléfono solo puede tener números, espacios o guiones (7 a 20 caracteres).", $viejos);
}
if (mb_strlen($mensaje) < 10) {
    volver("error", "Cuéntanos un poco más en tu mensaje (mínimo 10 caracteres).", $viejos);
}
if (mb_strlen($mensaje) > 2000) {
    volver("error", "El mensaje es demasiado largo (máximo 2000 caracteres).", $viejos);
}

// Buscar el servicio por nombre (solo los activos)
$stmt = $conexion->prepare("SELECT id FROM servicios WHERE nombre = ? AND activo = 1");
$stmt->bind_param("s", $area);
$stmt->execute();
$servicio = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$servicio) {
    volver("error", "Selecciona un área de interés válida.", $viejos);
}
$servicioId = (int) $servicio["id"];

// Guardar la consulta (estado 'pendiente' por defecto en la BD)
$stmt = $conexion->prepare(
    "INSERT INTO consultas (nombre, correo, telefono, servicio_id, mensaje) VALUES (?, ?, ?, ?, ?)"
);
$stmt->bind_param("sssis", $nombre, $correo, $telefono, $servicioId, $mensaje);

if ($stmt->execute()) {
    $stmt->close();
    volver("exito", "¡Gracias! Recibimos tu mensaje y uno de nuestros abogados te contactará pronto.");
}

$stmt->close();
volver("error", "No pudimos guardar tu mensaje. Intenta de nuevo en unos minutos.", $viejos);
