<?php


require_once __DIR__ . '/casos_util.php';

const DOC_TAMANO_MAXIMO = 10 * 1024 * 1024;   // 10 MB

// Extensiones permitidas y el tipo con el que se descargan
const DOC_EXTENSIONES = [
    "pdf"  => "application/pdf",
    "doc"  => "application/msword",
    "docx" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    "xls"  => "application/vnd.ms-excel",
    "xlsx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    "pptx" => "application/vnd.openxmlformats-officedocument.presentationml.presentation",
    "jpg"  => "image/jpeg",
    "jpeg" => "image/jpeg",
    "png"  => "image/png",
];


function carpeta_documentos(): string {
    $raiz    = __DIR__ . '/../Archivos';
    $carpeta = $raiz . '/casos';

    if (!is_dir($carpeta) && !mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
        throw new RuntimeException("No se pudo crear la carpeta de documentos.");
    }
    if (!is_file($raiz . '/.htaccess')) {
        file_put_contents($raiz . '/.htaccess', "Require all denied\n");
    }
    return $carpeta;
}


function formatear_tamano(int $bytes): string {
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 1) . " MB";
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024) . " KB";
    }
    return $bytes . " B";
}

// Comprueba que el contenido del archivo corresponde a su extensión, para que no
// pasen por Word o PDF archivos renombrados.
function firma_valida(string $ruta, string $extension): bool {
    $zip = "PK\x03\x04";          
    $ole = "\xD0\xCF\x11\xE0";    
    $firmas = [
        "pdf"  => "%PDF",
        "doc"  => $ole,
        "xls"  => $ole,
        "docx" => $zip,
        "xlsx" => $zip,
        "pptx" => $zip,
        "jpg"  => "\xFF\xD8\xFF",
        "jpeg" => "\xFF\xD8\xFF",
        "png"  => "\x89PNG",
    ];

    if (!isset($firmas[$extension])) {
        return false;
    }
    $inicio = file_get_contents($ruta, false, null, 0, 8);
    return $inicio !== false && strncmp($inicio, $firmas[$extension], strlen($firmas[$extension])) === 0;
}

// Devuelve el caso si este usuario puede verlo (cliente dueño, abogado asignado o admin);
// si no existe o no le corresponde, devuelve null.
function cargar_caso_con_permiso(mysqli $conexion, int $casoId, int $usuarioId, string $rol): ?array {
    $stmt = $conexion->prepare("SELECT id, cliente_id, abogado_id, titulo, estado FROM casos WHERE id = ?");
    $stmt->bind_param("i", $casoId);
    $stmt->execute();
    $caso = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$caso) {
        return null;
    }
    if ($rol === "admin") {
        return $caso;
    }
    if ($rol === "cliente") {
        return (int) $caso["cliente_id"] === $usuarioId ? $caso : null;
    }
    if ($rol === "abogado") {
        $miAbogadoId = abogado_id_de_usuario($conexion, $usuarioId);
        return ($miAbogadoId !== null && (int) $caso["abogado_id"] === $miAbogadoId) ? $caso : null;
    }
    return null;
}