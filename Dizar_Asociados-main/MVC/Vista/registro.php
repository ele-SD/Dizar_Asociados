<?php
require_once __DIR__ . "/../Modelo/conexion.php";

$error = "";
$exito = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuarioNombre = trim($_POST["usuario"] ?? "");
    $correo = trim($_POST["correo"] ?? "");
    $contrasena = $_POST["contrasena"] ?? "";
    $confirmar = $_POST["confirmar_contrasena"] ?? "";
    $tipo_documento = $_POST["tipo_documento"] ?? "";
    $numero_documento = trim($_POST["numero_documento"] ?? "");

    if (empty($usuarioNombre) || empty($correo) || empty($contrasena) || empty($confirmar) || empty($tipo_documento) || empty($numero_documento)) {
        $error = "Por favor completa todos los campos.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "El correo ingresado no es válido.";
    } elseif (!in_array($tipo_documento, ["CC", "CE"])) {
        $error = "Selecciona un tipo de documento válido.";
    } elseif (!preg_match('/^[0-9]{5,15}$/', $numero_documento)) {
        $error = "El número de documento solo debe contener dígitos (5 a 15).";
    } elseif (strlen($contrasena) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } elseif ($contrasena !== $confirmar) {
        $error = "Las contraseñas no coinciden.";
    } else {
        $sql = "SELECT id FROM usuarios WHERE correo = ? OR numero_documento = ? OR usuario = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("sss", $correo, $numero_documento, $usuarioNombre);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            $error = "Ya existe una cuenta con ese correo, usuario o número de documento.";
        } else {
            $hash = password_hash($contrasena, PASSWORD_DEFAULT);

            $sql = "INSERT INTO usuarios (usuario, correo, contrasena_hash, tipo_documento, numero_documento) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("sssss", $usuarioNombre, $correo, $hash, $tipo_documento, $numero_documento);

            if ($stmt->execute()) {
                $exito = "Cuenta creada con éxito. Ya puedes iniciar sesión.";
            } else {
                $error = "Ocurrió un error al crear la cuenta. Intenta de nuevo.";
            }
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
<title>Crear cuenta | Dizar Asociados</title>
<link rel="stylesheet" href="../Vista/style.css">
</head>
<body>

<div class="pagina-login">
    <div class="contenedor-login">
         <a href="index.php" class="btn-volver">Volver al inicio</a>
       <img src="imagenes/isotipo-dorado1.png" alt="Logo Dizar Asociados" class="logo">
        <h1>Crear cuenta</h1>
        <p class="subtitulo">Regístrate para acceder al portal</p>

        <?php if (!empty($exito)): ?>
            <div class="registro_ok" role="status">
                <h2>Cuenta creada</h2>
                <p>Tu registro quedó guardado. Ya puedes iniciar sesión.</p>
                <a href="login.php" class="btn">Ir a iniciar sesión</a>
            </div>
        <?php else: ?>

        <form action="registro.php" method="POST" id="formRegistro" novalidate>
            <div class="campo">
                <label for="usuario">Nombre de usuario</label>
                <input type="text" id="usuario" name="usuario" placeholder="Ej. jperez" required
                       value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>">
                <small class="campo_msg"></small>
            </div>

            <div class="campo">
                <label for="correo">Correo electrónico</label>
                <input type="email" id="correo" name="correo" placeholder="correo@ejemplo.com" required
                       value="<?php echo isset($_POST['correo']) ? htmlspecialchars($_POST['correo']) : ''; ?>">
                <small class="campo_msg"></small>
            </div>

            <div class="fila-documento">
                <div class="campo">
                    <label for="tipo_documento">Tipo de documento</label>
                    <select id="tipo_documento" name="tipo_documento" required>
                        <option value="">Selecciona...</option>
                        <option value="CC">Cédula de ciudadanía</option>
                        <option value="CE">Cédula de extranjería</option>
                    </select>
                    <small class="campo_msg"></small>
                </div>

                <div class="campo">
                    <label for="numero_documento">Número de documento</label>
                    <input type="text" id="numero_documento" name="numero_documento" placeholder="1234567890" required
                           value="<?php echo isset($_POST['numero_documento']) ? htmlspecialchars($_POST['numero_documento']) : ''; ?>">
                    <small class="campo_msg"></small>
                </div>
            </div>

            <div class="campo">
                <label for="contrasena">Contraseña</label>
                <input type="password" id="contrasena" name="contrasena" placeholder="Mínimo 6 caracteres" required>
                <small class="campo_msg"></small>
            </div>

            <div class="campo">
                <label for="confirmar_contrasena">Confirmar contraseña</label>
                <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" placeholder="Repite tu contraseña" required>
                <small class="campo_msg"></small>
            </div>

            <?php if (!empty($error)): ?>
                <p class="form_error" id="errorServidor" role="alert"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <button type="submit" class="btn">Crear cuenta</button>
        </form>

        <p class="pie">¿Ya tienes cuenta? <a href="login.php">Iniciar sesión</a></p>

        <?php endif; ?>
    </div>
</div>

<script>
const formRegistro = document.getElementById("formRegistro");

if (formRegistro) {
    function limpiarErrores() {
        document.querySelectorAll(".campo--error").forEach(function (c) {
            c.classList.remove("campo--error");
        });
        const srv = document.getElementById("errorServidor");
        if (srv) srv.remove();
    }

    function mostrarError(input, mensaje) {
        limpiarErrores();
        const campo = input.closest(".campo");
        campo.querySelector(".campo_msg").textContent = mensaje;
        campo.classList.add("campo--error");
        input.focus();
    }

    formRegistro.querySelectorAll("input, select").forEach(function (el) {
        el.addEventListener("input", limpiarErrores);
        el.addEventListener("change", limpiarErrores);
    });

    formRegistro.addEventListener("submit", function (e) {
        const usuario = document.getElementById("usuario");
        const correo = document.getElementById("correo");
        const tipoDocumento = document.getElementById("tipo_documento");
        const numeroDocumento = document.getElementById("numero_documento");
        const contrasena = document.getElementById("contrasena");
        const confirmar = document.getElementById("confirmar_contrasena");

        const regexCorreo = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const regexDocumento = /^[0-9]{5,15}$/;

        let fallo = null;

        if (usuario.value.trim() === "") {
            fallo = [usuario, "Escribe un nombre de usuario."];
        } else if (!regexCorreo.test(correo.value.trim())) {
            fallo = [correo, "Escribe un correo válido, por ejemplo nombre@correo.com"];
        } else if (tipoDocumento.value === "") {
            fallo = [tipoDocumento, "Elige un tipo de documento."];
        } else if (!regexDocumento.test(numeroDocumento.value.trim())) {
            fallo = [numeroDocumento, "Solo números, entre 5 y 15 dígitos."];
        } else if (contrasena.value.length < 6) {
            fallo = [contrasena, "Mínimo 6 caracteres."];
        } else if (contrasena.value !== confirmar.value) {
            fallo = [confirmar, "Las contraseñas no coinciden."];
        }

        if (fallo) {
            mostrarError(fallo[0], fallo[1]);
            e.preventDefault();
        }
    });
}
</script>

</body>
</html>