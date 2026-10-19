<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Exige haber iniciado sesión
function requerir_login(string $base = '../'): void {
    if (!isset($_SESSION["usuario_id"])) {
        header("Location: {$base}Vista/login.php");
        exit();
    }
}

// Exige haber iniciado sesión y tener uno de los roles indicados
function requerir_rol(array $roles, string $base = '../'): void {
    requerir_login($base);
    if (!in_array($_SESSION["rol"] ?? "", $roles, true)) {
        http_response_code(403);
        die("No tienes permiso para ver esta página.");
    }
}

// Para mostrar u ocultar cosas en el menú
function tiene_rol(string ...$roles): bool {
    return in_array($_SESSION["rol"] ?? "", $roles, true);
}