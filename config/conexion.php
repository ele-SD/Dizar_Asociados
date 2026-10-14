<?php

$host       = "localhost";
$usuario    = "root";
$contraseña = "";
$baseDatos  = "dizar_asociados";   

require_once __DIR__ . '/../conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $usuario, $contraseña, $baseDatos);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    error_log("Error de conexión: " . $e->getMessage());
    http_response_code(500);
    die("No se pudo conectar con el servicio. Intenta más tarde.");
}
