<?php
session_start();
require_once __DIR__ . '/../Controlador/auth.php';
require_once __DIR__ . '/../Controlador/casos_util.php';
require_once __DIR__ . '/../Modelo/conexion.php';

// Pueden entrar el cliente dueño del caso, el abogado asignado y el administrador
requerir_login();

$base = '../';
$miId = (int) $_SESSION["usuario_id"];
$rol  = $_SESSION["rol"] ?? "";
$casoId = (int) ($_GET["id"] ?? 0);

$stmt = $conexion->prepare(
    "SELECT c.id, c.cliente_id, c.abogado_id, c.tipo, c.titulo, c.descripcion, c.estado,
            c.creado_en, c.actualizado_en,
            s.nombre AS servicio, a.nombre AS abogado, u.usuario AS cliente
     FROM casos c
     LEFT JOIN servicios s ON s.id = c.servicio_id
     LEFT JOIN abogados a ON a.id = c.abogado_id
     INNER JOIN usuarios u ON u.id = c.cliente_id
     WHERE c.id = ?"
);
$stmt->bind_param("i", $casoId);
$stmt->execute();
$caso = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Quién puede ver este caso
$permitido = false;
if ($caso) {
    if ($rol === "admin") {
        $permitido = true;
    } elseif ($rol === "cliente") {
        $permitido = (int) $caso["cliente_id"] === $miId;
    } elseif ($rol === "abogado") {
        $miAbogadoId = abogado_id_de_usuario($conexion, $miId);
        $permitido = $miAbogadoId !== null && (int) $caso["abogado_id"] === $miAbogadoId;
    }
}

// Mismo mensaje si no existe o si no es suyo, para no revelar qué casos hay
if (!$permitido) {
    http_response_code(404);
    die("Caso no encontrado.");
}

$stmt = $conexion->prepare(
    "SELECT s.estado, s.nota, s.creado_en, u.usuario, u.rol
     FROM caso_seguimiento s
     INNER JOIN usuarios u ON u.id = s.usuario_id
     WHERE s.caso_id = ?
     ORDER BY s.creado_en ASC, s.id ASC"
);
$stmt->bind_param("i", $casoId);
$stmt->execute();
$seguimiento = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$volverA = $rol === "cliente" ? "mis_casos.php" : "casos.php";
$etiquetasRol = ["admin" => "Administrador", "abogado" => "Abogado", "cliente" => "Cliente"];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($caso["titulo"]) ?> | Dizar Asociados</title>
<link rel="stylesheet" href="../Vista/style.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="usuarios">
    <a href="<?= $volverA ?>" class="btn-volver" aria-label="Volver a los casos" title="Volver a los casos">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M19 12H5M11 6l-6 6 6 6"/>
        </svg>
    </a>

    <div class="caso_cabecera">
        <h1><?= htmlspecialchars($caso["titulo"]) ?></h1>
        <span class="caso_estado caso_estado--<?= htmlspecialchars($caso["estado"]) ?>">
            <?= htmlspecialchars(CASO_ESTADOS[$caso["estado"]] ?? $caso["estado"]) ?>
        </span>
    </div>

    <dl class="caso_datos">
        <div>
            <dt>Tipo</dt>
            <dd><?= htmlspecialchars(CASO_TIPOS[$caso["tipo"]] ?? $caso["tipo"]) ?></dd>
        </div>
        <div>
            <dt>Área</dt>
            <dd><?= htmlspecialchars($caso["servicio"] ?? "Sin área") ?></dd>
        </div>
        <div>
            <dt>Cliente</dt>
            <dd><?= htmlspecialchars($caso["cliente"]) ?></dd>
        </div>
        <div>
            <dt>Abogado</dt>
            <dd><?= htmlspecialchars($caso["abogado"] ?? "Por asignar") ?></dd>
        </div>
        <div>
            <dt>Registrado</dt>
            <dd><?= htmlspecialchars(substr($caso["creado_en"], 0, 16)) ?></dd>
        </div>
    </dl>

    <h2 class="caso_subtitulo">Descripción</h2>
    <p class="caso_descripcion"><?= nl2br(htmlspecialchars($caso["descripcion"])) ?></p>

    <h2 class="caso_subtitulo">Seguimiento</h2>
    <ol class="caso_linea">
        <?php foreach ($seguimiento as $p): ?>
            <li>
                <span class="caso_estado caso_estado--<?= htmlspecialchars($p["estado"]) ?>">
                    <?= htmlspecialchars(CASO_ESTADOS[$p["estado"]] ?? $p["estado"]) ?>
                </span>
                <?php if (trim((string) $p["nota"]) !== ""): ?>
                    <p class="caso_linea_nota"><?= nl2br(htmlspecialchars($p["nota"])) ?></p>
                <?php endif; ?>
                <p class="caso_linea_meta">
                    <?= htmlspecialchars($p["usuario"]) ?> (<?= htmlspecialchars($etiquetasRol[$p["rol"]] ?? $p["rol"]) ?>),
                    <?= htmlspecialchars(substr($p["creado_en"], 0, 16)) ?>
                </p>
            </li>
        <?php endforeach; ?>
    </ol>
</main>

<script src="../Controlador/script.js"></script>
</body>
</html>