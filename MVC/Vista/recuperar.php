<?php
require_once __DIR__ . "/../Modelo/conexion.php";

$error = "";
$exito = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $correo = trim($_POST["correo"] ?? "");

    if (empty($correo)) {
        $error = "Por favor ingresa tu correo electrónico.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "El correo ingresado no es válido.";
    } else {
        $sql = "SELECT id FROM usuarios WHERE correo = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $resultado = $stmt->get_result();

        // Por seguridad, siempre se muestra el mismo mensaje,
        // exista o no el correo en la base de datos.
        $exito = "Si el correo está registrado, recibirás instrucciones para restablecer tu contraseña.";
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Recuperar contraseña | Dizar Asociados</title>
<link rel="stylesheet" href="../Vista/style.css">
</head>
<body>

<div class="pagina-login">
    <div class="contenedor-login">
        <img src="imagenes/isotipo-dorado1.png" alt="Logo Dizar Asociados" class="logo">
        <h1>Recuperar contraseña</h1>
        <p class="subtitulo">Ingresa tu correo y te enviaremos instrucciones</p>

        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($exito)): ?>
            <div class="exito"><?php echo htmlspecialchars($exito); ?></div>
        <?php endif; ?>

        <form action="recuperar.php" method="POST" id="formRecuperar" novalidate>
            <div class="campo">
                <label for="correo">Correo electrónico</label>
                <input type="email" id="correo" name="correo" placeholder="correo@ejemplo.com" required>
            </div>

            <button type="submit" class="btn">Enviar instrucciones</button>
        </form>

        <p class="pie"><a href="login.php">← Volver a iniciar sesión</a></p>
    </div>
</div>

<script>
document.getElementById("formRecuperar").addEventListener("submit", function (e) {
    const correo = document.getElementById("correo").value.trim();
    const regexCorreo = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!regexCorreo.test(correo)) {
        alert("Ingresa un correo electrónico válido.");
        e.preventDefault();
    }
});
</script>

</body>
</html>