<?php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/casos_util.php';
require_once __DIR__ . '/documentos_util.php';
require_once __DIR__ . '/../Modelo/conexion.php';

// Cualquier usuario con sesión, pero solo recibe el archivo si el caso es suyo
requerir_login();

$miId  = (int) $_SESSION["usuario_id"];
$rol   = $_SESSION["rol"] ?? "";
$docId = (int) ($_GET["id"] ?? 0);

$stmt = $conexion->prepare("SELECT caso_id, nombre_original, nombre_guardado FROM caso_documentos WHERE id = ?");
$stmt->bind_param("i", $docId);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Mismo mensaje si no existe o si no es suyo, para no revelar qué documentos hay
$caso = $doc ? cargar_caso_con_permiso($conexion, (int) $doc["caso_id"], $miId, $rol) : null;
if (!$doc || !$caso) {
    http_response_code(404);
    die("Documento no encontrado.");
}

$ruta = carpeta_documentos() . "/" . basename($doc["nombre_guardado"]);
if (!is_file($ruta)) {
    http_response_code(404);
    die("El archivo ya no está disponible en el servidor.");
}

$extension = strtolower(pathinfo($doc["nombre_guardado"], PATHINFO_EXTENSION));
$tipo      = DOC_EXTENSIONES[$extension] ?? "application/octet-stream";

// Nombre de descarga: versión simple para navegadores viejos y versión con tildes
$nombre       = $doc["nombre_original"];
$nombreSimple = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre);

header("Content-Type: " . $tipo);
header("Content-Disposition: attachment; filename=\"" . $nombreSimple . "\"; filename*=UTF-8''" . rawurlencode($nombre));
header("Content-Length: " . filesize($ruta));
header("X-Content-Type-Options: nosniff");

readfile($ruta);
exit();