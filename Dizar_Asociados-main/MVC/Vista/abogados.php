<?php
session_start();
require_once __DIR__ . '/../Controlador/auth.php';
require_once __DIR__ . '/../Modelo/conexion.php';

// Solo el administrador gestiona las fichas de abogado
requerir_rol(['admin']);

$base = '../';
$error = "";
$exito = "";

if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

// Áreas válidas: son los nombres de la tabla servicios (casos.php compara contra ellos)
$servicios = $conexion->query("SELECT nombre FROM servicios WHERE activo = 1 ORDER BY nombre")
                      ->fetch_all(MYSQLI_ASSOC);
$areasValidas = array_column($servicios, "nombre");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) {
        die("Solicitud no válida.");
    }

    $accion = $_POST["accion"] ?? "";

    // ---- Crear la ficha de un usuario que ya tiene rol abogado ----
    if ($accion === "crear") {
        $usuarioId = (int) ($_POST["usuario_id"] ?? 0);
        $nombre    = trim($_POST["nombre"] ?? "");
        $area      = $_POST["especialidad"] ?? "";
        $tarjeta   = trim($_POST["tarjeta_profesional"] ?? "");

        if ($usuarioId <= 0 || $nombre === "" || $area === "") {
            $error = "Elige el usuario, escribe el nombre y elige el área.";
        } elseif (mb_strlen($nombre) > 120 || mb_strlen($tarjeta) > 50) {
            $error = "El nombre o la tarjeta profesional es demasiado largo.";
        } elseif (!in_array($area, $areasValidas, true)) {
            $error = "Elige un área válida.";
        } else {
            // Debe ser un usuario con rol abogado
            $stmt = $conexion->prepare("SELECT correo FROM usuarios WHERE id = ? AND rol = 'abogado'");
            $stmt->bind_param("i", $usuarioId);
            $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Y no tener ya una ficha
            $stmt = $conexion->prepare("SELECT id FROM abogados WHERE usuario_id = ?");
            $stmt->bind_param("i", $usuarioId);
            $stmt->execute();
            $yaTiene = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if (!$u) {
                $error = "Ese usuario no tiene el rol de abogado.";
            } elseif ($yaTiene) {
                $error = "Ese usuario ya tiene una ficha de abogado.";
            } else {
                $tarjetaDb = $tarjeta === "" ? null : $tarjeta;
                try {
                    $stmt = $conexion->prepare(
                        "INSERT INTO abogados (usuario_id, nombre, especialidad, tarjeta_profesional, correo, activo)
                         VALUES (?, ?, ?, ?, ?, 1)"
                    );
                    $stmt->bind_param("issss", $usuarioId, $nombre, $area, $tarjetaDb, $u["correo"]);
                    $stmt->execute();
                    $stmt->close();
                    $exito = "Ficha creada. Ya se le pueden asignar casos de " . $area . ".";
                } catch (Throwable $e) {
                    $error = "No se pudo crear la ficha (revisa que la tarjeta profesional no esté repetida).";
                }
            }
        }
    }

    // ---- Cambiar el área o activar/desactivar una ficha existente ----
    if ($accion === "actualizar") {
        $abogadoId = (int) ($_POST["abogado_id"] ?? 0);
        $area      = $_POST["especialidad"] ?? "";
        $activo    = isset($_POST["activo"]) ? 1 : 0;

        if ($abogadoId <= 0 || !in_array($area, $areasValidas, true)) {
            $error = "Datos no válidos.";
        } else {
            try {
                $stmt = $conexion->prepare("UPDATE abogados SET especialidad = ?, activo = ? WHERE id = ?");
                $stmt->bind_param("sii", $area, $activo, $abogadoId);
                $stmt->execute();
                $stmt->close();
                $exito = "Abogado actualizado.";
            } catch (Throwable $e) {
                $error = "No se pudo actualizar. Intenta de nuevo.";
            }
        }
    }
}

// Usuarios con rol abogado que todavía no tienen ficha
$sinFicha = $conexion->query(
    "SELECT u.id, u.usuario, u.correo
     FROM usuarios u
     LEFT JOIN abogados a ON a.usuario_id = u.id
     WHERE u.rol = 'abogado' AND a.id IS NULL
     ORDER BY u.usuario"
)->fetch_all(MYSQLI_ASSOC);

// Fichas existentes
$abogados = $conexion->query(
    "SELECT a.id, a.nombre, a.especialidad, a.tarjeta_profesional, a.activo, u.usuario, u.correo
     FROM abogados a
     LEFT JOIN usuarios u ON u.id = a.usuario_id
     ORDER BY a.nombre"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Abogados | Dizar Asociados</title>
<link rel="stylesheet" href="../Vista/style.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="usuarios">
    <h1>Gestión de abogados</h1>

    <?php if ($error): ?><div class="error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito): ?><div class="exito" role="alert"><?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <h2 class="caso_subtitulo">Abogados sin ficha</h2>
    <?php if (!$sinFicha): ?>
        <p class="consultas_vacio">
            No hay usuarios con rol abogado pendientes de ficha. Para sumar uno nuevo,
            primero cámbiale el rol en <a href="usuarios.php">Usuarios</a>.
        </p>
    <?php else: ?>
    <div class="tabla-scroll">
        <table class="tabla-usuarios">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Nombre completo</th>
                    <th>Área</th>
                    <th>Tarjeta profesional</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sinFicha as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s["usuario"]) ?><br><?= htmlspecialchars($s["correo"]) ?></td>
                    <td colspan="4">
                        <form method="POST" action="abogados.php" class="fila-rol">
                            <input type="hidden" name="csrf" value="<?= $_SESSION["csrf"] ?>">
                            <input type="hidden" name="accion" value="crear">
                            <input type="hidden" name="usuario_id" value="<?= (int) $s["id"] ?>">
                            <input type="text" name="nombre" maxlength="120" placeholder="Nombre completo" required
                                   value="<?= htmlspecialchars($s["usuario"]) ?>"
                                   aria-label="Nombre completo de <?= htmlspecialchars($s["usuario"]) ?>">
                            <select name="especialidad" required aria-label="Área de <?= htmlspecialchars($s["usuario"]) ?>">
                                <option value="">-- Área --</option>
                                <?php foreach ($areasValidas as $area): ?>
                                    <option value="<?= htmlspecialchars($area) ?>"><?= htmlspecialchars($area) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="tarjeta_profesional" maxlength="50" placeholder="Tarjeta prof. (opcional)"
                                   aria-label="Tarjeta profesional de <?= htmlspecialchars($s["usuario"]) ?>">
                            <button type="submit" class="btn">Crear ficha</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <h2 class="caso_subtitulo">Abogados del despacho</h2>
    <?php if (!$abogados): ?>
        <p class="consultas_vacio">Todavía no hay fichas de abogado.</p>
    <?php else: ?>
    <div class="tabla-scroll">
        <table class="tabla-usuarios">
            <thead>
                <tr>
                    <th>Abogado</th>
                    <th>Cuenta</th>
                    <th>Tarjeta prof.</th>
                    <th>Área y estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($abogados as $a): ?>
                <tr>
                    <td><?= htmlspecialchars($a["nombre"]) ?></td>
                    <td><?= htmlspecialchars($a["usuario"] ?? "—") ?><br><?= htmlspecialchars($a["correo"] ?? "") ?></td>
                    <td><?= htmlspecialchars($a["tarjeta_profesional"] ?? "—") ?></td>
                    <td>
                        <form method="POST" action="abogados.php" class="fila-rol">
                            <input type="hidden" name="csrf" value="<?= $_SESSION["csrf"] ?>">
                            <input type="hidden" name="accion" value="actualizar">
                            <input type="hidden" name="abogado_id" value="<?= (int) $a["id"] ?>">
                            <select name="especialidad" aria-label="Área de <?= htmlspecialchars($a["nombre"]) ?>">
                                <?php if (!in_array($a["especialidad"], $areasValidas, true)): ?>
                                    <option value="" selected disabled><?= htmlspecialchars($a["especialidad"]) ?> (no válida)</option>
                                <?php endif; ?>
                                <?php foreach ($areasValidas as $area): ?>
                                    <option value="<?= htmlspecialchars($area) ?>" <?= $a["especialidad"] === $area ? "selected" : "" ?>>
                                        <?= htmlspecialchars($area) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label>
                                <input type="checkbox" name="activo" value="1" <?= $a["activo"] ? "checked" : "" ?>> Activo
                            </label>
                            <button type="submit" class="btn">Guardar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</main>

<script src="../Controlador/script.js"></script>
</body>
</html>