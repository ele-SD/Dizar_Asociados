<?php
// ============================================
// Conexión a la base de datos (MariaDB / XAMPP)
// ============================================

$servidor = "localhost";
$usuario = "root";
$contrasena = ""; // En XAMPP por defecto no tiene contraseña
$basedatos = "despacho_abogados";

$conexion = new mysqli($servidor, $usuario, $contrasena, $basedatos);

// Verificar conexión
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$conexion->set_charset("utf8mb4");
?>
