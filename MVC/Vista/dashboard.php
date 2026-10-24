<?php
session_start();

// Si no hay sesión activa, regresar al login
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

$rol = $_SESSION["rol"] ?? "";
$esPersonal = in_array($rol, ["admin", "abogado"], true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bienvenido | Dizar Asociados</title>
<link rel="stylesheet" href="../Vista/style.css">
</head>
<body>

<div class="pagina-login">
    <div class="contenedor-login">
        <img src="imagenes/isotipo-dorado1.png" alt="Logo Dizar Asociados" class="logo">
        <h1>¡Bienvenido!</h1>
        <p class="subtitulo">
            <?php echo htmlspecialchars($_SESSION["usuario"] ?? ""); ?>
            · Rol: <?php echo htmlspecialchars($rol !== "" ? $rol : "sin rol"); ?>
        </p>

        <?php if ($rol === "cliente"): ?>
        <a href="nuevo_caso.php" class="btn btn--dorado btn--bloque">Registrar caso</a>
        <a href="mis_casos.php" class="btn btn--dorado btn--bloque">Mis casos</a>
        <?php endif; ?>

        <?php if ($esPersonal): ?>
        <a href="casos.php" class="btn btn--dorado btn--bloque">Casos</a>
        <a href="consultas.php" class="btn btn--dorado" style="display: block; text-align: center; margin-top: 15px;">Consultas recibidas</a>
        <?php endif; ?>

        <?php if ($rol === "admin"): ?>
        <a href="abogados.php" class="btn btn--dorado" style="display: block; text-align: center; margin-top: 15px;">Gestión de abogados</a>
        <a href="usuarios.php" class="btn btn--dorado" style="display: block; text-align: center; margin-top: 15px;">Gestión de usuarios</a>
        <?php endif; ?>

        <a href="ajustes.php" class="btn btn--dorado" style="display: block; text-align: center; margin-top: 15px;">Ajustes de la cuenta</a>

        <a href="index.php" class="btn btn--dorado" style="display: block; text-align: center; margin-top: 15px;">Volver a inicio</a>

        <p class="pie" style="margin-top: 30px;">
            <a href="../Controlador/logout.php">Cerrar sesión</a>
        </p>
    </div>
</div>

</body>
</html>