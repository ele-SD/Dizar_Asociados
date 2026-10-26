<?php
session_start();
require_once __DIR__ . '/../Controlador/auth.php';
require_once __DIR__ . '/../Controlador/casos_util.php';
require_once __DIR__ . '/../Controlador/notificaciones_util.php';
require_once __DIR__ . '/../Modelo/conexion.php';

// Solo el personal. 
requerir_rol(['admin', 'abogado']);

$base = '../';
$miId    = (int) $_SESSION["usuario_id"];
$esAdmin = ($_SESSION["rol"] ?? "") === "admin";

// Ficha de abogado ligada a esta cuenta (solo aplica a los abogados)
$miAbogadoId = $esAdmin ? null : abogado_id_de_usuario($conexion, $miId);
$sinFicha    = !$esAdmin && $miAbogadoId === null;

if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

// Filtro por estado (viene por GET)
$filtro = $_GET["estado"] ?? "";
if (!array_key_exists($filtro, CASO_ESTADOS)) {
    $filtro = "";
}

// Acciones (POST) -> se procesan y se redirige para evitar reenvíos
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) {
        die("Solicitud no válida.");
    }

    $accion = $_POST["accion"] ?? "";
    $casoId = (int) ($_POST["caso_id"] ?? 0);
    $aviso  = ["tipo" => "error", "mensaje" => "Datos no válidos."];

    $stmt = $conexion->prepare(
        "SELECT c.id, c.cliente_id, c.titulo, c.abogado_id, c.estado, s.nombre AS servicio
         FROM casos c LEFT JOIN servicios s ON s.id = c.servicio_id
         WHERE c.id = ?"
    );
    $stmt->bind_param("i", $casoId);
    $stmt->execute();
    $caso = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // El abogado solo puede tocar los casos que tiene asignados
    $puedeEditar = $caso && ($esAdmin || ($miAbogadoId !== null && (int) $caso["abogado_id"] === $miAbogadoId));

    if (!$puedeEditar) {
        $aviso = ["tipo" => "error", "mensaje" => "No tienes permiso sobre ese caso."];

    } elseif ($accion === "asignar" && $esAdmin) {
        $abogadoId = (int) ($_POST["abogado_id"] ?? 0);

        $stmt = $conexion->prepare("SELECT id, nombre, especialidad FROM abogados WHERE id = ? AND activo = 1");
        $stmt->bind_param("i", $abogadoId);
        $stmt->execute();
        $abogado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$abogado) {
            $aviso = ["tipo" => "error", "mensaje" => "Elige un abogado de la lista."];
        } elseif ($caso["servicio"] !== null && $abogado["especialidad"] !== $caso["servicio"]) {
            $aviso = ["tipo" => "error", "mensaje" => "Ese abogado no atiende el área de este caso."];
        } else {
            // Si el caso apenas se había recibido, pasa a "asignado"
            $nuevoEstado = $caso["estado"] === "recibido" ? "asignado" : $caso["estado"];

            try {
                $conexion->begin_transaction();
                $stmt = $conexion->prepare("UPDATE casos SET abogado_id = ?, estado = ? WHERE id = ?");
                $stmt->bind_param("isi", $abogadoId, $nuevoEstado, $casoId);
                $stmt->execute();
                $stmt->close();
                registrar_seguimiento($conexion, $casoId, $miId, $nuevoEstado, "Caso asignado a " . $abogado["nombre"] . ".");
                $conexion->commit();

                // Avisar al cliente y al abogado
                notificar_asignacion($conexion, $casoId, (int) $caso["cliente_id"], $caso["titulo"], $abogadoId, $abogado["nombre"]);

                $aviso = ["tipo" => "exito", "mensaje" => "Caso asignado a " . $abogado["nombre"] . "."];
            } catch (Throwable $e) {
                $conexion->rollback();
                $aviso = ["tipo" => "error", "mensaje" => "No se pudo asignar el caso. Intenta de nuevo."];
            }
        }

    } elseif ($accion === "estado") {
        $nuevoEstado = $_POST["estado"] ?? "";
        $nota        = trim($_POST["nota"] ?? "");

        if (!array_key_exists($nuevoEstado, CASO_ESTADOS)) {
            $aviso = ["tipo" => "error", "mensaje" => "Estado no válido."];
        } elseif (mb_strlen($nota) > 500) {
            $aviso = ["tipo" => "error", "mensaje" => "La nota es demasiado larga (máximo 500 caracteres)."];
        } elseif ($nuevoEstado !== "recibido" && $caso["abogado_id"] === null) {
            $aviso = ["tipo" => "error", "mensaje" => "Primero asigna un abogado a este caso."];
        } elseif ($nuevoEstado === $caso["estado"] && $nota === "") {
            $aviso = ["tipo" => "error", "mensaje" => "No hay nada que guardar: cambia el estado o escribe una nota."];
        } else {
            try {
                $conexion->begin_transaction();
                $stmt = $conexion->prepare("UPDATE casos SET estado = ? WHERE id = ?");
                $stmt->bind_param("si", $nuevoEstado, $casoId);
                $stmt->execute();
                $stmt->close();
                registrar_seguimiento($conexion, $casoId, $miId, $nuevoEstado, $nota);
                $conexion->commit();

                // Avisar al cliente del cambio de estado o de la nueva nota
                notificar_estado(
                    $conexion, $casoId, (int) $caso["cliente_id"], $caso["titulo"],
                    CASO_ESTADOS[$nuevoEstado], $nuevoEstado !== $caso["estado"]
                );

                $aviso = ["tipo" => "exito", "mensaje" => "Caso actualizado."];
            } catch (Throwable $e) {
                $conexion->rollback();
                $aviso = ["tipo" => "error", "mensaje" => "No se pudo actualizar el caso. Intenta de nuevo."];
            }
        }
    }

    $_SESSION["flash_casos_personal"] = $aviso;
    header("Location: casos.php" . ($filtro !== "" ? "?estado=" . urlencode($filtro) : ""));
    exit();
}

$flash = $_SESSION["flash_casos_personal"] ?? null;
unset($_SESSION["flash_casos_personal"]);

$conteos = array_fill_keys(array_keys(CASO_ESTADOS), 0);
$casos = [];
$abogadosActivos = [];

if (!$sinFicha) {
    // Contadores por estado
    if ($esAdmin) {
        $res = $conexion->query("SELECT estado, COUNT(*) AS total FROM casos GROUP BY estado");
    } else {
        $res = $conexion->query("SELECT estado, COUNT(*) AS total FROM casos WHERE abogado_id = " . (int) $miAbogadoId . " GROUP BY estado");
    }
    while ($f = $res->fetch_assoc()) {
        $conteos[$f["estado"]] = (int) $f["total"];
    }

    // Listado
    $sql = "SELECT c.id, c.titulo, c.tipo, c.estado, c.abogado_id, c.creado_en,
                   s.nombre AS servicio, a.nombre AS abogado, u.usuario AS cliente
            FROM casos c
            LEFT JOIN servicios s ON s.id = c.servicio_id
            LEFT JOIN abogados a ON a.id = c.abogado_id
            INNER JOIN usuarios u ON u.id = c.cliente_id";

    $where = [];
    $tipos = "";
    $params = [];
    if (!$esAdmin) {
        $where[] = "c.abogado_id = ?";
        $tipos .= "i";
        $params[] = $miAbogadoId;
    }
    if ($filtro !== "") {
        $where[] = "c.estado = ?";
        $tipos .= "s";
        $params[] = $filtro;
    }
    if ($where) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY c.actualizado_en DESC, c.id DESC";

    $stmt = $conexion->prepare($sql);
    if ($params) {
        $stmt->bind_param($tipos, ...$params);
    }
    $stmt->execute();
    $casos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Abogados disponibles, para ofrecer solo los del área de cada caso
    if ($esAdmin) {
        $abogadosActivos = $conexion->query("SELECT id, nombre, especialidad FROM abogados WHERE activo = 1 ORDER BY nombre")
                                    ->fetch_all(MYSQLI_ASSOC);
    }
}
$totalGeneral = array_sum($conteos);
$accionUrl = "casos.php" . ($filtro !== "" ? "?estado=" . urlencode($filtro) : "");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Casos | Dizar Asociados</title>
<link rel="stylesheet" href="../Vista/style.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="usuarios casos">
    <h1><?= $esAdmin ? "Casos del despacho" : "Mis casos asignados" ?></h1>

    <?php if ($flash): ?>
        <div class="<?= $flash["tipo"] === "exito" ? "exito" : "error" ?>" role="alert">
            <?= htmlspecialchars($flash["mensaje"]) ?>
        </div>
    <?php endif; ?>

    <?php if ($sinFicha): ?>
        <p class="consultas_vacio">
            Tu cuenta todavía no está ligada a una ficha de abogado. Pídele al administrador que la vincule para ver los casos que te asignen.
        </p>
    <?php else: ?>

    <nav class="filtros-estado" aria-label="Filtrar por estado">
        <a href="casos.php" class="filtro<?= $filtro === "" ? " filtro--activo" : "" ?>">
            Todos (<?= $totalGeneral ?>)
        </a>
        <?php foreach (CASO_ESTADOS as $clave => $texto): ?>
            <a href="casos.php?estado=<?= $clave ?>" class="filtro<?= $filtro === $clave ? " filtro--activo" : "" ?>">
                <?= $texto ?> (<?= $conteos[$clave] ?>)
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$casos): ?>
        <p class="consultas_vacio">No hay casos<?= $filtro !== "" ? " con estado «" . htmlspecialchars(CASO_ESTADOS[$filtro]) . "»" : "" ?>.</p>
    <?php else: ?>
    <div class="tabla-scroll">
        <table class="tabla-usuarios">
            <thead>
                <tr>
                    <th>Registrado</th>
                    <th>Cliente</th>
                    <th>Caso</th>
                    <th>Área</th>
                    <th>Abogado</th>
                    <th>Estado y nota</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($casos as $c): ?>
                <tr>
                    <td data-label="Registrado"><?= htmlspecialchars(substr($c["creado_en"], 0, 10)) ?></td>
                    <td data-label="Cliente"><?= htmlspecialchars($c["cliente"]) ?></td>
                    <td data-label="Caso">
                        <div>
                            <a href="caso.php?id=<?= (int) $c["id"] ?>"><?= htmlspecialchars($c["titulo"]) ?></a><br>
                            <?= htmlspecialchars(CASO_TIPOS[$c["tipo"]] ?? $c["tipo"]) ?>
                        </div>
                    </td>
                    <td data-label="Área"><?= htmlspecialchars($c["servicio"] ?? "Sin área") ?></td>
                    <td data-label="Abogado">
                        <?php if ($esAdmin): ?>
                            <?php
                            // Solo los abogados del área del caso (si el caso no tiene área, todos)
                            $candidatos = array_filter($abogadosActivos, function ($a) use ($c) {
                                return $c["servicio"] === null || $a["especialidad"] === $c["servicio"];
                            });
                            ?>
                            <?php if ($candidatos): ?>
                                <form method="POST" action="<?= $accionUrl ?>" class="fila-rol">
                                    <input type="hidden" name="csrf" value="<?= $_SESSION["csrf"] ?>">
                                    <input type="hidden" name="accion" value="asignar">
                                    <input type="hidden" name="caso_id" value="<?= (int) $c["id"] ?>">
                                    <select name="abogado_id" aria-label="Abogado para el caso <?= htmlspecialchars($c["titulo"]) ?>">
                                        <option value="0">-- Elegir --</option>
                                        <?php foreach ($candidatos as $a): ?>
                                            <option value="<?= (int) $a["id"] ?>" <?= (int) $c["abogado_id"] === (int) $a["id"] ? "selected" : "" ?>>
                                                <?= htmlspecialchars($a["nombre"]) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn">Asignar</button>
                                </form>
                            <?php else: ?>
                                <?= htmlspecialchars($c["abogado"] ?? "Sin abogados de esta área") ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <?= htmlspecialchars($c["abogado"] ?? "Por asignar") ?>
                        <?php endif; ?>
                    </td>
                    <td data-label="Estado y nota">
                        <?php if ($esAdmin || (int) $c["abogado_id"] === $miAbogadoId): ?>
                        <form method="POST" action="<?= $accionUrl ?>" class="fila-rol">
                            <input type="hidden" name="csrf" value="<?= $_SESSION["csrf"] ?>">
                            <input type="hidden" name="accion" value="estado">
                            <input type="hidden" name="caso_id" value="<?= (int) $c["id"] ?>">
                            <select name="estado" aria-label="Estado del caso <?= htmlspecialchars($c["titulo"]) ?>">
                                <?php foreach (CASO_ESTADOS as $clave => $texto): ?>
                                    <option value="<?= $clave ?>" <?= $c["estado"] === $clave ? "selected" : "" ?>><?= $texto ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="nota" maxlength="500" placeholder="Nota (opcional)"
                                   aria-label="Nota para el seguimiento del caso <?= htmlspecialchars($c["titulo"]) ?>">
                            <button type="submit" class="btn">Guardar</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</main>

<script src="../Controlador/script.js"></script>
</body>
</html>