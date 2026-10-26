<?php
session_start();
require_once __DIR__ . '/../Controlador/auth.php';
require_once __DIR__ . '/../Modelo/conexion.php';

// Cualquier usuario con sesión ve solo sus propias notificaciones
requerir_login();

$base = '../';
$miId = (int) $_SESSION["usuario_id"];

if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

// Abrir una notificación: se marca como leída y se lleva a la página que corresponde
if (isset($_GET["ir"])) {
    $idNotif = (int) $_GET["ir"];

    $stmt = $conexion->prepare("SELECT enlace FROM notificaciones WHERE id = ? AND usuario_id = ?");
    $stmt->bind_param("ii", $idNotif, $miId);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($fila) {
        $stmt = $conexion->prepare("UPDATE notificaciones SET leida = 1 WHERE id = ? AND usuario_id = ?");
        $stmt->bind_param("ii", $idNotif, $miId);
        $stmt->execute();
        $stmt->close();

        // Solo se permiten enlaces internos tipo "caso.php?id=3"
        $destino = $fila["enlace"] ?? "";
        if (!preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9=&_%-]*)?$/', $destino)) {
            $destino = "notificaciones.php";
        }
        header("Location: " . $destino);
        exit();
    }

    header("Location: notificaciones.php");
    exit();
}

// Marcar todas como leídas (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) {
        die("Solicitud no válida.");
    }

    if (($_POST["accion"] ?? "") === "marcar_todas") {
        $stmt = $conexion->prepare("UPDATE notificaciones SET leida = 1 WHERE usuario_id = ? AND leida = 0");
        $stmt->bind_param("i", $miId);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: notificaciones.php");
    exit();
}

$stmt = $conexion->prepare(
    "SELECT id, mensaje, enlace, leida, creado_en
     FROM notificaciones
     WHERE usuario_id = ?
     ORDER BY creado_en DESC, id DESC
     LIMIT 50"
);
$stmt->bind_param("i", $miId);
$stmt->execute();
$notificaciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$sinLeer = count(array_filter($notificaciones, function ($n) { return !$n["leida"]; }));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notificaciones | Dizar Asociados</title>
<link rel="stylesheet" href="../Vista/style.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="usuarios">
    <div class="caso_cabecera">
        <h1>Notificaciones</h1>
        <?php if ($sinLeer > 0): ?>
            <form method="POST" action="notificaciones.php">
                <input type="hidden" name="csrf" value="<?= $_SESSION["csrf"] ?>">
                <input type="hidden" name="accion" value="marcar_todas">
                <button type="submit" class="btn">Marcar todas como leídas</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (!$notificaciones): ?>
        <p class="consultas_vacio">No tienes notificaciones por ahora.</p>
    <?php else: ?>
        <ul class="notif_lista">
            <?php foreach ($notificaciones as $n): ?>
                <li>
                    <a href="notificaciones.php?ir=<?= (int) $n["id"] ?>"
                       class="notif_item<?= $n["leida"] ? "" : " notif_item--nueva" ?>">
                        <span class="notif_texto"><?= htmlspecialchars($n["mensaje"]) ?></span>
                        <span class="notif_fecha"><?= htmlspecialchars(substr($n["creado_en"], 0, 16)) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</main>

<script src="../Controlador/script.js"></script>
</body>
</html>