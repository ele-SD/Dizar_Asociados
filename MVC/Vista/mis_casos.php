<?php
session_start();
require_once __DIR__ . '/../Controlador/auth.php';
require_once __DIR__ . '/../Controlador/casos_util.php';
require_once __DIR__ . '/../Modelo/conexion.php';

// Cada cliente ve solo sus propios casos
requerir_rol(['cliente']);

$base = '../';
$clienteId = (int) $_SESSION["usuario_id"];

// Aviso que deja procesar_caso.php (se muestra una sola vez)
$flash = $_SESSION["flash_casos"] ?? null;
unset($_SESSION["flash_casos"], $_SESSION["viejos_caso"]);

$stmt = $conexion->prepare(
    "SELECT c.id, c.titulo, c.tipo, c.estado, c.creado_en, c.actualizado_en,
            s.nombre AS servicio, a.nombre AS abogado
     FROM casos c
     LEFT JOIN servicios s ON s.id = c.servicio_id
     LEFT JOIN abogados a ON a.id = c.abogado_id
     WHERE c.cliente_id = ?
     ORDER BY c.actualizado_en DESC, c.id DESC"
);
$stmt->bind_param("i", $clienteId);
$stmt->execute();
$casos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mis casos | Dizar Asociados</title>
<link rel="stylesheet" href="../Vista/style.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="usuarios">
    <div class="caso_cabecera">
        <h1>Mis casos</h1>
        <a href="nuevo_caso.php" class="btn btn--dorado">Registrar caso</a>
    </div>

    <?php if ($flash): ?>
        <div class="<?= $flash["tipo"] === "exito" ? "exito" : "error" ?>" role="alert">
            <?= htmlspecialchars($flash["mensaje"]) ?>
        </div>
    <?php endif; ?>

    <?php if (!$casos): ?>
        <p class="consultas_vacio">Todavía no has registrado ningún caso. Usa el botón «Registrar caso» para empezar.</p>
    <?php else: ?>
    <div class="tabla-scroll">
        <table class="tabla-usuarios">
            <thead>
                <tr>
                    <th>Registrado</th>
                    <th>Caso</th>
                    <th>Tipo</th>
                    <th>Área</th>
                    <th>Abogado</th>
                    <th>Estado</th>
                    <th>Última novedad</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($casos as $c): ?>
                <tr>
                    <td data-label="Registrado"><?= htmlspecialchars(substr($c["creado_en"], 0, 10)) ?></td>
                    <td data-label="Caso"><a href="caso.php?id=<?= (int) $c["id"] ?>"><?= htmlspecialchars($c["titulo"]) ?></a></td>
                    <td data-label="Tipo"><?= htmlspecialchars(CASO_TIPOS[$c["tipo"]] ?? $c["tipo"]) ?></td>
                    <td data-label="Área"><?= htmlspecialchars($c["servicio"] ?? "Sin área") ?></td>
                    <td data-label="Abogado"><?= htmlspecialchars($c["abogado"] ?? "Por asignar") ?></td>
                    <td data-label="Estado">
                        <span class="caso_estado caso_estado--<?= htmlspecialchars($c["estado"]) ?>">
                            <?= htmlspecialchars(CASO_ESTADOS[$c["estado"]] ?? $c["estado"]) ?>
                        </span>
                    </td>
                    <td data-label="Última novedad"><?= htmlspecialchars(substr($c["actualizado_en"], 0, 16)) ?></td>
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