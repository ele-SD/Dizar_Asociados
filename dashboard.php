<?php
session_start();

// Si no hay sesión activa, regresar al login
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}
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
        <img src="../imagenes/isotipo-dorado1.png" alt="Logo Dizar Asociados" class="logo">
        <h1>¡Bienvenido!</h1>
        <p class="subtitulo">Sesión iniciada como <?php echo htmlspecialchars($_SESSION["correo"]); ?></p>

        <a href="../apartado/ajustes.php" class="btn btn--dorado" style="display: block; text-align: center; margin-top: 15px;">Ajustes de la cuenta</a>

        <a href="index.php" class="btn btn--dorado" style="display: block; text-align: center; margin-top: 15px;">Volver a inicio</a>

        <p class="pie" style="margin-top: 30px;">
            <a href="logout.php">Cerrar sesión</a>
        </p>
    </div>
</div>

</body>
</html>