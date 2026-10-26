<?php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/casos_util.php';
require_once __DIR__ . '/documentos_util.php';
require_once __DIR__ . '/notificaciones_util.php';
require_once __DIR__ . '/../Modelo/conexion.php';

// Solo el personal sube o elimina documentos (el cliente solo los descarga)
requerir_rol(['admin', 'abogado']);

// Solo se acepta por POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../Vista/casos.php");
    exit();
}

// Token CSRF
if (empty($_SESSION["csrf"]) || !hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) {
    die("Solicitud no válida.");
}

$miId   = (int) $_SESSION["usuario_id"];
$rol    = $_SESSION["rol"] ?? "";
$casoId = (int) ($_POST["caso_id"] ?? 0);

// Vuelve al caso dejando un aviso
function volver(int $casoId, string $tipo, string $mensaje): void {
    $_SESSION["flash_docs"] = ["tipo" => $tipo, "mensaje" => $mensaje];
    header("Location: ../Vista/caso.php?id=" . $casoId . "#documentos");
    exit();
}

// El abogado solo trabaja en los casos que tiene asignados
$caso = cargar_caso_con_permiso($conexion, $casoId, $miId, $rol);
if (!$caso) {
    http_response_code(404);
    die("Caso no encontrado.");
}

$accion = $_POST["accion"] ?? "";

// ---------- Eliminar un documento ----------
if ($accion === "eliminar") {
    $docId = (int) ($_POST["documento_id"] ?? 0);

    $stmt = $conexion->prepare("SELECT id, usuario_id, nombre_guardado FROM caso_documentos WHERE id = ? AND caso_id = ?");
    $stmt->bind_param("ii", $docId, $casoId);
    $stmt->execute();
    $doc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$doc) {
        volver($casoId, "error", "Ese documento ya no existe.");
    }
    // El abogado solo borra lo que subió él; el administrador puede borrar cualquiera
    if ($rol !== "admin" && (int) $doc["usuario_id"] !== $miId) {
        volver($casoId, "error", "Solo puedes eliminar los documentos que tú subiste.");
    }

    $stmt = $conexion->prepare("DELETE FROM caso_documentos WHERE id = ?");
    $stmt->bind_param("i", $docId);
    $stmt->execute();
    $stmt->close();

    @unlink(carpeta_documentos() . "/" . basename($doc["nombre_guardado"]));

    volver($casoId, "exito", "Documento eliminado.");
}

if ($accion !== "subir") {
    volver($casoId, "error", "Acción no válida.");
}

// ---------- Subir un documento ----------
$archivo = $_FILES["documento"] ?? null;

if (!$archivo || $archivo["error"] === UPLOAD_ERR_NO_FILE) {
    volver($casoId, "error", "Elige un archivo para subir.");
}
if ($archivo["error"] === UPLOAD_ERR_INI_SIZE || $archivo["error"] === UPLOAD_ERR_FORM_SIZE) {
    volver($casoId, "error", "El archivo es demasiado grande (máximo 10 MB).");
}
if ($archivo["error"] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo["tmp_name"])) {
    volver($casoId, "error", "No se pudo recibir el archivo. Intenta de nuevo.");
}
if ($archivo["size"] <= 0 || $archivo["size"] > DOC_TAMANO_MAXIMO) {
    volver($casoId, "error", "El archivo debe pesar menos de 10 MB y no estar vacío.");
}

// Nombre que ve el cliente (se quitan rutas y caracteres de control)
$nombreOriginal = trim(basename(str_replace("\\", "/", $archivo["name"])));
$nombreOriginal = preg_replace('/[\x00-\x1F\x7F]/', '', $nombreOriginal);

if ($nombreOriginal === "" || mb_strlen($nombreOriginal) > 150) {
    volver($casoId, "error", "El nombre del archivo es muy largo (máximo 150 caracteres).");
}

$extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
if (!array_key_exists($extension, DOC_EXTENSIONES)) {
    volver($casoId, "error", "Tipo de archivo no permitido. Sube Word, PDF, Excel, PowerPoint o una imagen (JPG o PNG).");
}
if (!firma_valida($archivo["tmp_name"], $extension)) {
    volver($casoId, "error", "El contenido del archivo no corresponde a un ." . $extension . " válido.");
}

$descripcion = trim($_POST["descripcion"] ?? "");
if (mb_strlen($descripcion) > 255) {
    volver($casoId, "error", "La descripción es demasiado larga (máximo 255 caracteres).");
}

// Se guarda con un nombre al azar, así nadie puede adivinar la ruta de otro archivo
try {
    $carpeta = carpeta_documentos();
} catch (Throwable $e) {
    error_log($e->getMessage());
    volver($casoId, "error", "No se pudo preparar la carpeta de documentos en el servidor.");
}

$nombreGuardado = bin2hex(random_bytes(16)) . "." . $extension;
if (!move_uploaded_file($archivo["tmp_name"], $carpeta . "/" . $nombreGuardado)) {
    volver($casoId, "error", "No se pudo guardar el archivo en el servidor.");
}

try {
    $tamano  = (int) $archivo["size"];
    $descDb  = $descripcion === "" ? null : $descripcion;

    $stmt = $conexion->prepare(
        "INSERT INTO caso_documentos (caso_id, usuario_id, nombre_original, nombre_guardado, tamano, descripcion)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("iissis", $casoId, $miId, $nombreOriginal, $nombreGuardado, $tamano, $descDb);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    // Si no quedó en la base de datos, no dejamos el archivo huérfano
    @unlink($carpeta . "/" . $nombreGuardado);
    error_log("No se pudo registrar el documento: " . $e->getMessage());
    volver($casoId, "error", "No se pudo registrar el documento. Intenta de nuevo.");
}

// El documento ya está subido: lo que sigue no debe romperlo si falla
try {
    $nota = "Subió el documento «" . $nombreOriginal . "»" . ($descripcion !== "" ? ": " . $descripcion : ".");
    registrar_seguimiento($conexion, $casoId, $miId, $caso["estado"], $nota);
} catch (Throwable $e) {
    error_log("No se pudo registrar el seguimiento del documento: " . $e->getMessage());
}

notificar_documento($conexion, $caso, $nombreOriginal);

volver($casoId, "exito", "Documento subido. El cliente recibió una notificación.");