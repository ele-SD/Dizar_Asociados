<?php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/casos_util.php';
require_once __DIR__ . '/../Modelo/conexion.php';

// Solo los clientes registran casos
requerir_rol(['cliente']);

// Solo se acepta por POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../Vista/nuevo_caso.php");
    exit();
}

// Token CSRF
if (empty($_SESSION["csrf"]) || !hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) {
    die("Solicitud no válida.");
}

// Redirige dejando un mensaje y, si hubo error, lo que el usuario escribió
function volver(string $tipo, string $mensaje, array $viejos = [], string $destino = "nuevo_caso.php"): void {
    $_SESSION["flash_casos"] = ["tipo" => $tipo, "mensaje" => $mensaje];
    $_SESSION["viejos_caso"] = $viejos;
    header("Location: ../Vista/" . $destino);
    exit();
}

$clienteId   = (int) $_SESSION["usuario_id"];
$tipo        = $_POST["tipo"] ?? "";
$servicioId  = (int) ($_POST["servicio_id"] ?? 0);
$titulo      = trim($_POST["titulo"] ?? "");
$descripcion = trim($_POST["descripcion"] ?? "");

$viejos = [
    "tipo" => $tipo, "servicio_id" => $servicioId,
    "titulo" => $titulo, "descripcion" => $descripcion,
];

// Validaciones
if (!array_key_exists($tipo, CASO_TIPOS)) {
    volver("error", "Elige si necesitas una asesoría o una demanda.", $viejos);
}
if ($titulo === "" || $descripcion === "" || $servicioId <= 0) {
    volver("error", "Por favor completa todos los campos.", $viejos);
}
if (mb_strlen($titulo) < 5 || mb_strlen($titulo) > 150) {
    volver("error", "El título debe tener entre 5 y 150 caracteres.", $viejos);
}
if (mb_strlen($descripcion) < 20) {
    volver("error", "Cuéntanos un poco más sobre tu caso (mínimo 20 caracteres).", $viejos);
}
if (mb_strlen($descripcion) > 2000) {
    volver("error", "La descripción es demasiado larga (máximo 2000 caracteres).", $viejos);
}

// El área debe existir y estar activa
$stmt = $conexion->prepare("SELECT id FROM servicios WHERE id = ? AND activo = 1");
$stmt->bind_param("i", $servicioId);
$stmt->execute();
$servicio = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$servicio) {
    volver("error", "Selecciona un área válida.", $viejos);
}

// Se guarda el caso y su primera línea de seguimiento, las dos o ninguna
try {
    $conexion->begin_transaction();

    $stmt = $conexion->prepare(
        "INSERT INTO casos (cliente_id, servicio_id, tipo, titulo, descripcion) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("iisss", $clienteId, $servicioId, $tipo, $titulo, $descripcion);
    $stmt->execute();
    $casoId = (int) $conexion->insert_id;
    $stmt->close();

    registrar_seguimiento($conexion, $casoId, $clienteId, "recibido", "Caso registrado por el cliente.");

    $conexion->commit();
} catch (Throwable $e) {
    $conexion->rollback();
    volver("error", "No pudimos registrar tu caso. Intenta de nuevo en unos minutos.", $viejos);
}

volver("exito", "Recibimos tu caso. Te avisaremos cuando un abogado lo tome.", [], "mis_casos.php");