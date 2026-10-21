<?php
session_start();
require_once __DIR__ . '/../Modelo/conexion.php';

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

$base = '../';
$id = (int) $_SESSION["usuario_id"];
$error = "";
$exito = "";

if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) {
        die("Solicitud no válida.");
    }

    $accion = $_POST["accion"] ?? "";

    if ($accion === "perfil") {
        $usuarioNombre = trim($_POST["usuario"] ?? "");
        $correo = trim($_POST["correo"] ?? "");

        if ($usuarioNombre === "" || $correo === "") {
            $error = "Completa el usuario y el correo.";
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = "El correo ingresado no es válido.";
        } else {
            $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE (correo = ? OR usuario = ?) AND id <> ?");
            $stmt->bind_param("ssi", $correo, $usuarioNombre, $id);
            $stmt->execute();
            $repetido = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($repetido) {
                $error = "Ya existe una cuenta con ese correo o usuario.";
            } else {
                $stmt = $conexion->prepare("UPDATE usuarios SET usuario = ?, correo = ? WHERE id = ?");
                $stmt->bind_param("ssi", $usuarioNombre, $correo, $id);
                $stmt->execute();
                $stmt->close();
                $_SESSION["usuario"] = $usuarioNombre;
                $_SESSION["correo"] = $correo;
                $exito = "Datos actualizados.";
            }
        }
    }

    if ($accion === "contrasena") {
        $actual = $_POST["actual"] ?? "";
        $nueva = $_POST["nueva"] ?? "";
        $confirmar = $_POST["confirmar"] ?? "";

        $stmt = $conexion->prepare("SELECT contrasena_hash FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$fila || !password_verify($actual, $fila["contrasena_hash"])) {
            $error = "La contraseña actual no es correcta.";
        } elseif (strlen($nueva) < 6) {
            $error = "La nueva contraseña debe tener al menos 6 caracteres.";
        } elseif ($nueva !== $confirmar) {
            $error = "Las contraseñas nuevas no coinciden.";
        } else {
            $hash = password_hash($nueva, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("UPDATE usuarios SET contrasena_hash = ? WHERE id = ?");
            $stmt->bind_param("si", $hash, $id);
            $stmt->execute();
            $stmt->close();
            session_regenerate_id(true);
            $exito = "Contraseña actualizada.";
        }
    }
}

$stmt = $conexion->prepare("SELECT usuario, correo, tipo_documento, numero_documento FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$datos = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ajustes | Dizar Asociados</title>
<link rel="stylesheet" href="../Vista/style.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="ajustes">
    <div class="contenedor-login">
        <h1>Ajustes de la cuenta</h1>

        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($exito): ?><div class="exito"><?= htmlspecialchars($exito) ?></div><?php endif; ?>

        <form method="POST" action="ajustes.php">
            <input type="hidden" name="csrf" value="<?= $_SESSION["csrf"] ?>">
            <input type="hidden" name="accion" value="perfil">
            <div class="campo">
                <label for="usuario">Nombre de usuario</label>
                <input type="text" id="usuario" name="usuario" value="<?= htmlspecialchars($datos["usuario"] ?? "") ?>" required>
            </div>
            <div class="campo">
                <label for="correo">Correo electrónico</label>
                <input type="email" id="correo" name="correo" value="<?= htmlspecialchars($datos["correo"] ?? "") ?>" required>
            </div>
            <div class="campo">
                <label>Documento</label>
                <input type="text" value="<?= htmlspecialchars(trim(($datos["tipo_documento"] ?? "") . " " . ($datos["numero_documento"] ?? ""))) ?>" disabled>
            </div>
            <button type="submit" class="btn">Guardar cambios</button>
        </form>

        <?php $abrirPass = (($_POST["accion"] ?? "") === "contrasena" && $error !== ""); ?>
<button type="button" class="btn-desplegar<?= $abrirPass ? ' abierto' : '' ?>" id="btnPassword" aria-expanded="<?= $abrirPass ? 'true' : 'false' ?>" aria-controls="panelPassword">
    <span>Cambiar contraseña</span>
    <span class="flecha"></span>
</button>

<div class="panel-desplegable<?= $abrirPass ? ' abierto' : '' ?>" id="panelPassword">
    <div class="panel-interior">
        <form method="POST" action="ajustes.php">
            <input type="hidden" name="csrf" value="<?= $_SESSION["csrf"] ?>">
            <input type="hidden" name="accion" value="contrasena">
            <div class="campo">
                <label for="actual">Contraseña actual</label>
                <input type="password" id="actual" name="actual" required>
            </div>
            <div class="campo">
                <label for="nueva">Nueva contraseña</label>
                <input type="password" id="nueva" name="nueva" minlength="6" required>
            </div>
            <div class="campo">
                <label for="confirmar">Confirmar nueva contraseña</label>
                <input type="password" id="confirmar" name="confirmar" minlength="6" required>
            </div>
            <button type="submit" class="btn">Actualizar contraseña</button>
        </form>
    </div>
</div>
    </div>
</main>

<script>
document.getElementById("btnPassword").addEventListener("click", function () {
    const panel = document.getElementById("panelPassword");
    const abierto = panel.classList.toggle("abierto");
    this.classList.toggle("abierto", abierto);
    this.setAttribute("aria-expanded", abierto);
});
</script> 

<script src="../Controlador/script.js"></script>
</body>
</html>