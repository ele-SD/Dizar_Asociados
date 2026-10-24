<?php
session_start();
require_once __DIR__ . '/../Controlador/auth.php';
require_once __DIR__ . '/../Modelo/conexion.php';

// Solo el administrador puede entrar aquí -.-
requerir_rol(['admin']);

$base = '../';
$miId = (int) $_SESSION["usuario_id"];
$error = "";
$exito = "";
$rolesValidos = ['admin', 'abogado', 'cliente'];

if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) {
        die("Solicitud no válida.");
    }

    $idObjetivo = (int) ($_POST["usuario_id"] ?? 0);
    $nuevoRol = $_POST["rol"] ?? "";

    if ($idObjetivo <= 0 || !in_array($nuevoRol, $rolesValidos, true)) {
        $error = "Datos no válidos.";
    } elseif ($idObjetivo === $miId && $nuevoRol !== 'admin') {
        $error = "No puedes quitarte a ti mismo el rol de administrador.";
    } else {
        $stmt = $conexion->prepare("UPDATE usuarios SET rol = ? WHERE id = ?");
        $stmt->bind_param("si", $nuevoRol, $idObjetivo);
        $stmt->execute();
        $stmt->close();
        $exito = "Rol actualizado.";
    }
}

$resultado = $conexion->query("SELECT id, usuario, correo, rol, activo, creado_en FROM usuarios ORDER BY id");
$usuarios = $resultado->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Usuarios | Dizar Asociados</title>
<link rel="stylesheet" href="../Vista/style.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="usuarios">
    <div class="panel_cabecera">
        <h1>Gestión de usuarios</h1>
        <p class="panel_sub"><?= count($usuarios) ?> usuario<?= count($usuarios) === 1 ? "" : "s" ?> registrado<?= count($usuarios) === 1 ? "" : "s" ?></p>
    </div>

    <?php if ($error): ?><p class="nota nota--error" role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($exito): ?><p class="nota" role="status"><?= htmlspecialchars($exito) ?></p><?php endif; ?>

    <div class="tabla-scroll">
        <table class="tabla-usuarios">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Registro</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td data-label="Usuario">
                        <div>
                            <?= htmlspecialchars($u["usuario"]) ?>
                            <?php if ((int) $u["id"] === $miId): ?><span class="etiqueta-tu">tú</span><?php endif; ?>
                        </div>
                    </td>
                    <td data-label="Correo"><?= htmlspecialchars($u["correo"]) ?></td>
                    <td data-label="Rol">
                        <form method="POST" action="usuarios.php" class="fila-rol">
                            <input type="hidden" name="csrf" value="<?= $_SESSION["csrf"] ?>">
                            <input type="hidden" name="usuario_id" value="<?= (int) $u["id"] ?>">
                            <select name="rol" aria-label="Rol de <?= htmlspecialchars($u["usuario"]) ?>">
                                <?php foreach ($rolesValidos as $rol): ?>
                                    <option value="<?= $rol ?>" <?= $u["rol"] === $rol ? 'selected' : '' ?>>
                                        <?= ucfirst($rol) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn--mini">Guardar</button>
                        </form>
                    </td>
                    <td data-label="Registro" class="celda-fecha"><?= htmlspecialchars(date("d/m/Y", strtotime($u["creado_en"]))) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<script src="../Controlador/script.js"></script>
</body>
</html>
