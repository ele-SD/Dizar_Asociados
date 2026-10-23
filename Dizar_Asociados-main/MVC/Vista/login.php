<?php
session_start();
require_once __DIR__ . "/../Modelo/conexion.php";

// Configuración del bloqueo
const MAX_INTENTOS = 5;      
const MINUTOS_BLOQUEO = 15;  
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $correo = trim($_POST["correo"] ?? "");
    $contrasena = $_POST["contrasena"] ?? "";

    if (empty($correo) || empty($contrasena)) {
        $error = "Por favor completa todos los campos.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "El correo ingresado no es válido.";
    } else {
       
        $sql = "SELECT id, usuario, correo, rol, contrasena_hash, activo, intentos_fallidos,
                       (bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()) AS bloqueado,
                       CEIL(TIMESTAMPDIFF(SECOND, NOW(), bloqueado_hasta) / 60) AS minutos_restantes
                FROM usuarios WHERE correo = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $stmt->close();

        if ($resultado->num_rows === 1) {
            $usuario = $resultado->fetch_assoc();

            if (!$usuario["activo"]) {
                $error = "Esta cuenta está desactivada. Contacta al despacho.";

            } elseif ($usuario["bloqueado"]) {
                // Mientras esté bloqueada ni siquiera se revisa la contraseña
                $min = max(1, (int) $usuario["minutos_restantes"]);
                $error = "Demasiados intentos fallidos. Intenta de nuevo en $min minuto" . ($min === 1 ? "" : "s") . ".";

            } elseif (password_verify($contrasena, $usuario["contrasena_hash"])) {
                // Acierto: se reinicia el contador
                $stmt = $conexion->prepare("UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?");
                $stmt->bind_param("i", $usuario["id"]);
                $stmt->execute();
                $stmt->close();

                session_regenerate_id(true);
                $_SESSION["usuario_id"] = $usuario["id"];
                $_SESSION["usuario"] = $usuario["usuario"];
                $_SESSION["correo"] = $usuario["correo"];
                $_SESSION["rol"] = $usuario["rol"];
                header("Location: dashboard.php");
                exit();

            } else {
                // Fallo: se suma un intento y, al llegar al máximo, se bloquea
                $intentos = (int) $usuario["intentos_fallidos"] + 1;

                if ($intentos >= MAX_INTENTOS) {
                    $minutos = MINUTOS_BLOQUEO;
                    $stmt = $conexion->prepare("UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?");
                    $stmt->bind_param("ii", $minutos, $usuario["id"]);
                    $error = "Demasiados intentos fallidos. Tu cuenta quedó bloqueada por " . MINUTOS_BLOQUEO . " minutos.";
                } else {
                    $stmt = $conexion->prepare("UPDATE usuarios SET intentos_fallidos = ? WHERE id = ?");
                    $stmt->bind_param("ii", $intentos, $usuario["id"]);
                    $error = "Correo o contraseña incorrectos.";
                }
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $error = "Correo o contraseña incorrectos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesión | Dizar Asociados</title>
<link rel="stylesheet" href="../Vista/style.css">
</head>
<body>

<div class="pagina-login">
    <div class="contenedor-login">
       <a href="index.php" class="btn-volver" aria-label="Volver al inicio" title="Volver al inicio">
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 11l9-8 9 8"/>
        <path d="M5 10v10h5v-6h4v6h5V10"/>
    </svg>
</a>

        <img src="../imagenes/isotipo-dorado1.png" alt="Logo Dizar Asociados" class="logo">
        <h1>Bienvenido</h1>
        <p class="subtitulo">Inicia sesión para continuar</p>

        <form action="login.php" method="POST" id="formLogin" novalidate>
            <div class="campo">
                <label for="correo">Correo electrónico</label>
                <input type="email" id="correo" name="correo" placeholder="correo@ejemplo.com" required
                       value="<?php echo isset($_POST['correo']) ? htmlspecialchars($_POST['correo']) : ''; ?>">
                <small class="campo_msg" id="msgCorreo"></small>
            </div>

            <div class="campo<?php echo !empty($error) ? ' campo--error' : ''; ?>">
                <label for="contrasena">Contraseña</label>
                <input type="password" id="contrasena" name="contrasena" placeholder="••••••••" required>
                <small class="campo_msg" id="msgContrasena" role="alert"><?php echo htmlspecialchars($error); ?></small>
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
const campoCorreo = document.getElementById("correo");
const campoContrasena = document.getElementById("contrasena");

function limpiarErrores() {
    document.querySelectorAll(".campo--error").forEach(function (c) {
        c.classList.remove("campo--error");
    });
}

function mostrarError(input, mensaje) {
    limpiarErrores();
    const campo = input.closest(".campo");
    campo.querySelector(".campo_msg").textContent = mensaje;
    campo.classList.add("campo--error");
    input.focus();
}

campoCorreo.addEventListener("input", limpiarErrores);
campoContrasena.addEventListener("input", limpiarErrores);

document.getElementById("formLogin").addEventListener("submit", function (e) {
    const correo = campoCorreo.value.trim();
    const regexCorreo = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!regexCorreo.test(correo)) {
        mostrarError(campoCorreo, "Escribe un correo válido, por ejemplo nombre@correo.com");
        e.preventDefault();
        return;
    }

    if (campoContrasena.value.length < 6) {
        mostrarError(campoContrasena, "Mínimo 6 caracteres.");
        e.preventDefault();
    }
});
</script>

</body>
</html>