<?php
session_start();
date_default_timezone_set('America/Bogota');
require_once __DIR__ . '/../Controlador/auth.php';
require_once __DIR__ . '/../Modelo/conexion.php';

// Solo el personal. Cuando agregues el rol recepcionista, súmalo aquí.
requerir_rol(['admin', 'abogado']);

$base = '../';
// El correo es opcional
$correoActivo = false;
if (is_file(__DIR__ . '/../Controlador/correo_util.php')) {
    require_once __DIR__ . '/../Controlador/correo_util.php';
    $correoActivo = defined('CORREO_ACTIVO') && CORREO_ACTIVO === true;
}
$estadosValidos = ['pendiente', 'contactado', 'agendada', 'cerrada'];
const DURACION_CITA_MIN = 30; // minutos que ocupa cada cita en la agenda del abogado

// El correo automático solo sale si en config_correo.php existe: const CORREO_ACTIVO = true;


if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

// Filtro por estado (viene por GET)
$filtro = $_GET["estado"] ?? "";
if (!in_array($filtro, $estadosValidos, true)) {
    $filtro = "";
}

// Cambio de estado / agendado de cita (POST) -> se procesa y se redirige para evitar reenvíos
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) {
        die("Solicitud no válida.");
    }

    $idConsulta  = (int) ($_POST["consulta_id"] ?? 0);
    $nuevoEstado = $_POST["estado"] ?? "";
    $aviso = ["tipo" => "error", "mensaje" => "Datos no válidos."];

    $stmt = $conexion->prepare(
        "SELECT c.id, c.nombre, c.correo, c.estado, c.abogado_id, c.fecha_cita, s.nombre AS servicio
         FROM consultas c LEFT JOIN servicios s ON s.id = c.servicio_id
         WHERE c.id = ?"
    );
    $stmt->bind_param("i", $idConsulta);
    $stmt->execute();
    $consulta = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($consulta && in_array($nuevoEstado, $estadosValidos, true)) {

        if ($nuevoEstado === "agendada") {
            // ---- Agendar: abogado + fecha, sin choques, y correo al cliente ----
            $abogadoId = (int) ($_POST["abogado_id"] ?? 0);
            $fechaTxt  = trim($_POST["fecha_cita"] ?? "");

            $stmt = $conexion->prepare("SELECT id, nombre, especialidad FROM abogados WHERE id = ? AND activo = 1");
            $stmt->bind_param("i", $abogadoId);
            $stmt->execute();
            $abogado = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $fecha = DateTime::createFromFormat('Y-m-d\TH:i', $fechaTxt);
            $errores = DateTime::getLastErrors();

            if (!$abogado) {
                $aviso["mensaje"] = "Elige el abogado que atenderá la cita.";
            } elseif ($consulta["servicio"] !== null && $abogado["especialidad"] !== $consulta["servicio"]) {
                $aviso["mensaje"] = "Ese abogado no atiende el área de esta consulta.";
            } elseif (!$fecha || ($errores && ($errores['warning_count'] > 0 || $errores['error_count'] > 0))) {
                $aviso["mensaje"] = "La fecha de la cita no es válida.";
            } elseif ($fecha <= new DateTime('now')) {
                $aviso["mensaje"] = "Elige una fecha y hora que todavía no haya pasado.";
            } elseif ((int) $fecha->format('N') > 5 || (int) $fecha->format('G') < 8 || (int) $fecha->format('G') >= 17) {
                $aviso["mensaje"] = "Atendemos de lunes a viernes, de 8:00 a.m. a 5:00 p.m.";
            } else {
                $fechaDb = $fecha->format('Y-m-d H:i:s');

                // ¿El abogado ya tiene otra cita que se cruza con esta?
                $min = DURACION_CITA_MIN;
                $stmt = $conexion->prepare(
                    "SELECT id FROM consultas
                     WHERE abogado_id = ? AND estado = 'agendada' AND id <> ?
                       AND fecha_cita > DATE_SUB(?, INTERVAL ? MINUTE)
                       AND fecha_cita < DATE_ADD(?, INTERVAL ? MINUTE)"
                );
                $stmt->bind_param("iisisi", $abogadoId, $idConsulta, $fechaDb, $min, $fechaDb, $min);
                $stmt->execute();
                $choque = $stmt->get_result()->num_rows > 0;
                $stmt->close();

                if ($choque) {
                    $aviso["mensaje"] = $abogado["nombre"] . " ya tiene una cita cerca de esa hora. Elige otro horario.";
                } else {
                    $yaEstabaIgual = $consulta["estado"] === "agendada"
                        && (int) $consulta["abogado_id"] === $abogadoId
                        && $consulta["fecha_cita"] === $fechaDb;

                    $stmt = $conexion->prepare("UPDATE consultas SET estado = 'agendada', abogado_id = ?, fecha_cita = ? WHERE id = ?");
                    $stmt->bind_param("isi", $abogadoId, $fechaDb, $idConsulta);
                    $stmt->execute();
                    $stmt->close();

                    if ($yaEstabaIgual) {
                        $aviso = ["tipo" => "exito", "mensaje" => "La cita ya estaba agendada con esos datos."];
                    } elseif (!$correoActivo) {
                        $aviso = ["tipo" => "exito", "mensaje" => "Cita agendada con " . $abogado["nombre"] . " el " . $fecha->format('d/m/Y H:i') . ". El correo automático está desactivado: avísale al cliente."];
                    } else {
                        $reprogramada = $consulta["estado"] === "agendada";
                        $enviado = correo_confirmacion_cita(
                            $consulta["correo"], $consulta["nombre"], $fecha,
                            $abogado["nombre"], $consulta["servicio"], $reprogramada
                        );
                        if ($enviado) {
                            $aviso = ["tipo" => "exito", "mensaje" => "Cita agendada y correo enviado a " . $consulta["correo"] . "."];
                        } else {
                            $aviso = ["tipo" => "error", "mensaje" => "La cita quedó agendada, pero no se pudo enviar el correo. Revisa la configuración o avísale al cliente."];
                        }
                    }
                }
            }

        } else {
            // ---- Otros estados: si deja de estar agendada, se libera la agenda ----
            if (in_array($nuevoEstado, ["pendiente", "contactado"], true)) {
                $stmt = $conexion->prepare("UPDATE consultas SET estado = ?, abogado_id = NULL, fecha_cita = NULL WHERE id = ?");
            } else {
                $stmt = $conexion->prepare("UPDATE consultas SET estado = ? WHERE id = ?");
            }
            $stmt->bind_param("si", $nuevoEstado, $idConsulta);
            $stmt->execute();
            $stmt->close();
            $aviso = ["tipo" => "exito", "mensaje" => "Estado actualizado."];
        }
    }

    $_SESSION["flash_consultas"] = $aviso;
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

// Abogados activos, para ofrecer solo los del área de cada consulta
$abogadosActivos = $conexion->query("SELECT id, nombre, especialidad FROM abogados WHERE activo = 1 ORDER BY nombre")
                            ->fetch_all(MYSQLI_ASSOC);

// Listado (con el nombre del servicio y el abogado de la cita)
$sql = "SELECT c.id, c.nombre, c.correo, c.telefono, c.mensaje, c.estado, c.creado_en,
               c.fecha_preferida, c.fecha_cita, c.abogado_id,
               s.nombre AS servicio, a.nombre AS abogado
        FROM consultas c
        LEFT JOIN servicios s ON s.id = c.servicio_id
        LEFT JOIN abogados a ON a.id = c.abogado_id";

if ($filtro !== "") {
    $stmt = $conexion->prepare($sql . " WHERE c.estado = ? ORDER BY c.creado_en DESC");
    $stmt->bind_param("s", $filtro);
    $stmt->execute();
    $consultas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $consultas = $conexion->query($sql . " ORDER BY c.creado_en DESC")->fetch_all(MYSQLI_ASSOC);
}

$accionUrl = "consultas.php" . ($filtro !== "" ? "?estado=" . urlencode($filtro) : "");
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
    <div class="panel_cabecera">
        <h1>Consultas recibidas</h1>
        <p class="panel_sub">
            <?= $totalGeneral ?> en total<?php if ($conteos["pendiente"] > 0): ?>
            · <?= $conteos["pendiente"] ?> por atender<?php endif; ?>
        </p>
    </div>

    <?php if ($flash): ?>
        <p class="nota<?= $flash["tipo"] === "exito" ? "" : " nota--error" ?>" role="status">
            <?= htmlspecialchars($flash["mensaje"]) ?>
        </p>
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
                    <th>Cita</th>
                    <th>Mensaje</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($consultas as $c): ?>
                <?php
                    // Solo los abogados del área de la consulta (si no tiene área, todos)
                    $candidatos = array_filter($abogadosActivos, function ($a) use ($c) {
                        return $c["servicio"] === null || $a["especialidad"] === $c["servicio"];
                    });
                    // Fecha que se propone en el formulario: la confirmada, o la que pidió el cliente
                    $fechaBase  = $c["fecha_cita"] ?: $c["fecha_preferida"];
                    $fechaInput = $fechaBase ? str_replace(" ", "T", substr($fechaBase, 0, 16)) : "";
                ?>
                <tr>
                    <td data-label="Fecha" class="celda-fecha"><?= htmlspecialchars(date("d/m/Y H:i", strtotime($c["creado_en"]))) ?></td>
                    <td data-label="Cliente"><?= htmlspecialchars($c["nombre"]) ?></td>
                    <td data-label="Contacto">
                        <div>
                            <a href="mailto:<?= htmlspecialchars($c["correo"]) ?>"><?= htmlspecialchars($c["correo"]) ?></a>
                            <span class="dato-sec"><?= htmlspecialchars($c["telefono"] ?? "Sin teléfono") ?></span>
                        </div>
                    </td>
                    <td data-label="Área"><?= htmlspecialchars($c["servicio"] ?? "Sin área") ?></td>
                    <td data-label="Cita">
                        <div>
                            Pedida: <?= $c["fecha_preferida"] ? htmlspecialchars(substr($c["fecha_preferida"], 0, 16)) : "—" ?>
                            <?php if ($c["estado"] === "agendada" && $c["fecha_cita"]): ?>
                                <span class="dato-sec"><strong>Confirmada: <?= htmlspecialchars(substr($c["fecha_cita"], 0, 16)) ?></strong></span>
                                <span class="dato-sec"><?= htmlspecialchars($c["abogado"] ?? "") ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td data-label="Mensaje">
                        <details class="consulta_mensaje">
                            <summary><?= htmlspecialchars(mb_strimwidth($c["mensaje"], 0, 50, "…")) ?></summary>
                            <p><?= nl2br(htmlspecialchars($c["mensaje"])) ?></p>
                        </details>
                    </td>
                    <td data-label="Estado">
                        <form method="POST" action="<?= $accionUrl ?>" class="fila-rol fila-rol--estado form-cita">
                            <input type="hidden" name="csrf" value="<?= $_SESSION["csrf"] ?>">
                            <input type="hidden" name="consulta_id" value="<?= (int) $c["id"] ?>">
                            <span class="punto punto--<?= htmlspecialchars($c["estado"]) ?>" aria-hidden="true"></span>
                            <select name="estado" class="sel-estado" aria-label="Estado de la consulta de <?= htmlspecialchars($c["nombre"]) ?>">
                                <?php foreach ($estadosValidos as $e): ?>
                                    <option value="<?= $e ?>" <?= $c["estado"] === $e ? "selected" : "" ?>><?= ucfirst($e) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <div class="campos-cita">
                                <select name="abogado_id" aria-label="Abogado de la cita de <?= htmlspecialchars($c["nombre"]) ?>">
                                    <option value="0">-- Abogado --</option>
                                    <?php foreach ($candidatos as $a): ?>
                                        <option value="<?= (int) $a["id"] ?>" <?= (int) $c["abogado_id"] === (int) $a["id"] ? "selected" : "" ?>>
                                            <?= htmlspecialchars($a["nombre"]) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="datetime-local" name="fecha_cita" step="900" value="<?= $fechaInput ?>"
                                       aria-label="Fecha de la cita de <?= htmlspecialchars($c["nombre"]) ?>">
                            </div>

                            <button type="submit" class="btn btn--mini">Guardar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</main>

<script>
// Abogado y fecha solo se muestran cuando el estado elegido es "agendada"
document.querySelectorAll(".form-cita").forEach(function (form) {
    const estado = form.querySelector(".sel-estado");
    const campos = form.querySelector(".campos-cita");

    function actualizar() {
        campos.style.display = estado.value === "agendada" ? "flex" : "none";
    }

    estado.addEventListener("change", actualizar);
    actualizar();
});
</script>

<script src="../Controlador/script.js"></script>
</body>
</html>