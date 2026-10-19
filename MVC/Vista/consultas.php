<?php
session_start();
require_once __DIR__ . '/../Controlador/auth.php';
require_once __DIR__ . '/../Modelo/conexion.php';

// Solo el personal. Cuando agregues el rol recepcionista, súmalo aquí.
requerir_rol(['admin', 'abogado']);

$base = '../';
$estadosValidos = ['pendiente', 'contactado', 'agendada', 'cerrada'];

if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

// Filtro por estado (viene por GET)
$filtro = $_GET["estado"] ?? "";
if (!in_array($filtro, $estadosValidos, true)) {
    $filtro = "";
}

// Cambio de estado (POST) -> se procesa y se redirige para evitar reenvíos
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) {
        die("Solicitud no válida.");
    }

    $idConsulta  = (int) ($_POST["consulta_id"] ?? 0);
    $nuevoEstado = $_POST["estado"] ?? "";

    if ($idConsulta > 0 && in_array($nuevoEstado, $estadosValidos, true)) {
        $stmt = $conexion->prepare("UPDATE consultas SET estado = ? WHERE id = ?");
        $stmt->bind_param("si", $nuevoEstado, $idConsulta);
        $stmt->execute();
        $stmt->close();
        $_SESSION["flash_consultas"] = ["tipo" => "exito", "mensaje" => "Estado actualizado."];
    } else {
        $_SESSION["flash_consultas"] = ["tipo" => "error", "mensaje" => "Datos no válidos."];
    }

    $destino = "consultas.php" . ($filtro !== "" ? "?estado=" . urlencode($filtro) : "");
    header("Location: $destino");
    exit();
}

$flash = $_SESSION["flash_consultas"] ?? null;
unset($_SESSION["flash_consultas"]);

// Contadores por estado
$conteos = array_fill_keys($estadosValidos, 0);
$res = $conexion->query("SELECT estado, COUNT(*) AS total FROM consultas GROUP BY estado");
while ($f = $res->fetch_assoc()) {
    $conteos[$f["estado"]] = (int) $f["total"];
}
$totalGeneral = array_sum($conteos);

// Listado (con el nombre del servicio)
$sql = "SELECT c.id, c.nombre, c.correo, c.telefono, c.mensaje, c.estado, c.creado_en,
               s.nombre AS servicio
        FROM consultas c
        LEFT JOIN servicios s ON s.id = c.servicio_id";

if ($filtro !== "") {
    $stmt = $conexion->prepare($sql . " WHERE c.estado = ? ORDER BY c.creado_en DESC");
    $stmt->bind_param("s", $filtro);
    $stmt->execute();
    $consultas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $consultas = $conexion->query($sql . " ORDER BY c.creado_en DESC")->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Consultas | Dizar Asociados</title>
<link rel="stylesheet" href="../Vista/style.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="usuarios consultas">
    <h1>Consultas recibidas</h1>

    <?php if ($flash): ?>
        <div class="<?= $flash["tipo"] === "exito" ? "exito" : "error" ?>" role="alert">
            <?= htmlspecialchars($flash["mensaje"]) ?>
        </div>
    <?php endif; ?>

    <nav class="filtros-estado" aria-label="Filtrar por estado">
        <a href="consultas.php" class="filtro<?= $filtro === "" ? " filtro--activo" : "" ?>">
            Todas (<?= $totalGeneral ?>)
        </a>
        <?php foreach ($estadosValidos as $e): ?>
            <a href="consultas.php?estado=<?= $e ?>" class="filtro<?= $filtro === $e ? " filtro--activo" : "" ?>">
                <?= ucfirst($e) ?> (<?= $conteos[$e] ?>)
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$consultas): ?>
        <p class="consultas_vacio">No hay consultas<?= $filtro !== "" ? " con estado «" . htmlspecialchars($filtro) . "»" : "" ?>.</p>
    <?php else: ?>
    <div class="tabla-scroll">
        <table class="tabla-usuarios">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Contacto</th>
                    <th>Área</th>
                    <th>Mensaje</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($consultas as $c): ?>
                <tr>
                    <td><?= htmlspecialchars(substr($c["creado_en"], 0, 16)) ?></td>
                    <td><?= htmlspecialchars($c["nombre"]) ?></td>
                    <td>
                        <a href="mailto:<?= htmlspecialchars($c["correo"]) ?>"><?= htmlspecialchars($c["correo"]) ?></a><br>
                        <?= htmlspecialchars($c["telefono"] ?? "—") ?>
                    </td>
                    <td><?= htmlspecialchars($c["servicio"] ?? "Sin área") ?></td>
                    <td>
                        <details class="consulta_mensaje">
                            <summary><?= htmlspecialchars(mb_strimwidth($c["mensaje"], 0, 50, "…")) ?></summary>
                            <p><?= nl2br(htmlspecialchars($c["mensaje"])) ?></p>
                        </details>
                    </td>
                    <td>
                        <form method="POST" action="consultas.php<?= $filtro !== "" ? "?estado=" . urlencode($filtro) : "" ?>" class="fila-rol">
                            <input type="hidden" name="csrf" value="<?= $_SESSION["csrf"] ?>">
                            <input type="hidden" name="consulta_id" value="<?= (int) $c["id"] ?>">
                            <select name="estado" aria-label="Estado de la consulta de <?= htmlspecialchars($c["nombre"]) ?>">
                                <?php foreach ($estadosValidos as $e): ?>
                                    <option value="<?= $e ?>" <?= $c["estado"] === $e ? "selected" : "" ?>><?= ucfirst($e) ?></option>
                                <?php endforeach; ?>
                            </select>
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
