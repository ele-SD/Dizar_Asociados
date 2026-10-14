<?php
session_start();
require_once "conexion.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $correo = trim($_POST["correo"] ?? "");
    $contrasena = $_POST["contrasena"] ?? "";

    if (empty($correo) || empty($contrasena)) {
        $error = "Por favor completa todos los campos.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "El correo ingresado no es válido.";
    } else {
        $sql = "SELECT id, correo, contrasena_hash, activo FROM usuarios WHERE correo = ?";
        $sql = "SELECT id, usuario, correo, contrasena_hash, activo FROM usuarios WHERE correo = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {
            $usuario = $resultado->fetch_assoc();

            if (!$usuario["activo"]) {
                $error = "Esta cuenta está desactivada. Contacta al despacho.";
            } elseif (password_verify($contrasena, $usuario["contrasena_hash"])) {
                $_SESSION["usuario_id"] = $usuario["id"];
                $_SESSION["usuario"] = $usuario["usuario"];
                $_SESSION["correo"] = $usuario["correo"];
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Correo o contraseña incorrectos.";
            }
        } else {
            $error = "Correo o contraseña incorrectos.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesión | Dizar Asociados</title>
<link rel="stylesheet" href="estilos/style.css">
</head>
<body>

<div class="pagina-login">
    <div class="contenedor-login">
        <img src="imagenes/isotipo-dorado1.png" alt="Logo Dizar Asociados" class="logo">
        <h1>Bienvenido</h1>
        <p class="subtitulo">Inicia sesión para continuar</p>

        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST" id="formLogin" novalidate>
            <div class="campo">
                <label for="correo">Correo electrónico</label>
                <input type="email" id="correo" name="correo" placeholder="correo@ejemplo.com" required
                       value="<?php echo isset($_POST['correo']) ? htmlspecialchars($_POST['correo']) : ''; ?>">
            </div>

            <div class="campo">
                <label for="contrasena">Contraseña</label>
                <input type="password" id="contrasena" name="contrasena" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn">Iniciar sesión</button>

            <div class="enlaces">
                <a href="recuperar.php">¿Olvidaste tu contraseña?</a>
            </div>
        </form>

        <p class="pie">¿No tienes cuenta? <a href="registro.php">Crear cuenta</a></p>
    </div>
</div>

<script>
document.getElementById("formLogin").addEventListener("submit", function (e) {
    const correo = document.getElementById("correo").value.trim();
    const contrasena = document.getElementById("contrasena").value;
    const regexCorreo = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!regexCorreo.test(correo)) {
        alert("Ingresa un correo electrónico válido.");
        e.preventDefault();
        return;
    }

    if (contrasena.length < 6) {
        alert("La contraseña debe tener al menos 6 caracteres.");
        e.preventDefault();
    }
});
</script>

</body>
</html>