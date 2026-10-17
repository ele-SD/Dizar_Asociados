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
        <img src="../imagenes/isotipo-dorado1.png" alt="Logo Dizar Asociados" class="logo">
        <h1>Crear cuenta</h1>
        <p class="subtitulo">Regístrate para acceder al portal</p>

        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($exito)): ?>
            <div class="exito"><?php echo htmlspecialchars($exito); ?></div>
        <?php endif; ?>

        <form action="registro.php" method="POST" id="formRegistro" novalidate>
            <div class="campo">
                <label for="usuario">Nombre de usuario</label>
                <input type="text" id="usuario" name="usuario" placeholder="Ej. jperez" required
                       value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>">
            </div>

            <div class="campo">
                <label for="correo">Correo electrónico</label>
                <input type="email" id="correo" name="correo" placeholder="correo@ejemplo.com" required
                       value="<?php echo isset($_POST['correo']) ? htmlspecialchars($_POST['correo']) : ''; ?>">
            </div>

            <div class="fila-documento">
                <div class="campo">
                    <label for="tipo_documento">Tipo de documento</label>
                    <select id="tipo_documento" name="tipo_documento" required>
                        <option value="">Selecciona...</option>
                        <option value="CC">Cédula de ciudadanía</option>
                        <option value="CE">Cédula de extranjería</option>
                    </select>
                </div>

                <div class="campo">
                    <label for="numero_documento">Número de documento</label>
                    <input type="text" id="numero_documento" name="numero_documento" placeholder="1234567890" required
                           value="<?php echo isset($_POST['numero_documento']) ? htmlspecialchars($_POST['numero_documento']) : ''; ?>">
                </div>
            </div>

            <div class="campo">
                <label for="contrasena">Contraseña</label>
                <input type="password" id="contrasena" name="contrasena" placeholder="Mínimo 6 caracteres" required>
            </div>

            <div class="campo">
                <label for="confirmar_contrasena">Confirmar contraseña</label>
                <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" placeholder="Repite tu contraseña" required>
            </div>

            <button type="submit" class="btn">Crear cuenta</button>
        </form>

        <p class="pie">¿Ya tienes cuenta? <a href="../Vista/login.php">Iniciar sesión</a></p>
    </div>
</div>

<script>
document.getElementById("formRegistro").addEventListener("submit", function (e) {
    const correo = document.getElementById("correo").value.trim();
    const usuario = document.getElementById("usuario").value.trim();
    const tipoDocumento = document.getElementById("tipo_documento").value;
    const numeroDocumento = document.getElementById("numero_documento").value.trim();
    const contrasena = document.getElementById("contrasena").value;
    const confirmar = document.getElementById("confirmar_contrasena").value;

    const regexCorreo = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const regexDocumento = /^[0-9]{5,15}$/;

    if (usuario === "") {
        alert("Ingresa un nombre de usuario.");
        e.preventDefault();
        return;
    }

    if (!regexCorreo.test(correo)) {
        alert("Ingresa un correo electrónico válido.");
        e.preventDefault();
        return;
    }

    if (tipoDocumento === "") {
        alert("Selecciona un tipo de documento.");
        e.preventDefault();
        return;
    }

    if (!regexDocumento.test(numeroDocumento)) {
        alert("El número de documento debe contener solo dígitos (5 a 15).");
        e.preventDefault();
        return;
    }

    if (contrasena.length < 6) {
        alert("La contraseña debe tener al menos 6 caracteres.");
        e.preventDefault();
        return;
    }

    if (contrasena !== confirmar) {
        alert("Las contraseñas no coinciden.");
        e.preventDefault();
    }
});
</script>

</body>
</html>