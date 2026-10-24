<?php
session_start();
require_once __DIR__ . '/../Modelo/conexion.php';
$base = '../';

$sesionActiva = isset($_SESSION["usuario_id"]);

if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

// Mensaje y datos que deja procesar_opinion.php (se muestran una sola vez)
$flash  = $_SESSION["flash_opiniones"] ?? null;
$viejos = $_SESSION["viejos_opiniones"] ?? [];
unset($_SESSION["flash_opiniones"], $_SESSION["viejos_opiniones"]);

// Abogados activos para el selector del formulario
$abogados = $conexion->query("SELECT id, nombre, especialidad FROM abogados WHERE activo = 1 ORDER BY nombre")
                     ->fetch_all(MYSQLI_ASSOC);

// Opiniones publicadas (más recientes primero)
$opiniones = $conexion->query(
    "SELECT o.calificacion, o.comentario, o.creado_en, u.usuario, a.nombre AS abogado
     FROM opiniones o
     INNER JOIN usuarios u ON u.id = o.usuario_id
     LEFT JOIN abogados a ON a.id = o.abogado_id
     ORDER BY o.creado_en DESC
     LIMIT 50"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opiniones | Dizar Asociados</title>
    <link rel="stylesheet" href="../Vista/style.css">
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>

    <main>
        <section class="opiniones">
            <p class="opiniones_antetitulo">Experiencias de nuestros clientes</p>
            <h1 class="opiniones_titulo">Opiniones</h1>
            <p class="opiniones_intro">
                Conoce lo que dicen quienes ya trabajaron con nuestros abogados y comparte tu propia experiencia.
            </p>

            <?php if ($flash): ?>
                <div class="<?= $flash["tipo"] === "exito" ? "exito" : "error" ?>" role="alert">
                    <?= htmlspecialchars($flash["mensaje"]) ?>
                </div>
            <?php endif; ?>

            <!-- Formulario para opinar -->
            <div class="opiniones_formulario">
                <h2 class="opiniones_subtitulo">Cuéntanos tu experiencia</h2>

                <?php if ($sesionActiva): ?>
                    <form class="opiniones_form" method="POST" action="../Controlador/procesar_opinion.php">
                        <input type="hidden" name="csrf" value="<?= $_SESSION["csrf"] ?>">

                        <!-- Honeypot anti-spam: oculto para personas -->
                        <div style="position:absolute; left:-9999px;" aria-hidden="true">
                            <label for="sitio_web">No llenar este campo</label>
                            <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
                        </div>

                        <label for="abogado_id">Abogado</label>
                        <select id="abogado_id" name="abogado_id">
                            <option value="0">-- Opinión general del despacho --</option>
                            <?php foreach ($abogados as $a): ?>
                                <option value="<?= (int) $a["id"] ?>" <?= (int) ($viejos["abogado_id"] ?? 0) === (int) $a["id"] ? "selected" : "" ?>>
                                    <?= htmlspecialchars($a["nombre"]) ?> (<?= htmlspecialchars($a["especialidad"]) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="calificacion">Calificación</label>
                        <select id="calificacion" name="calificacion" required>
                            <option value="">-- Selecciona --</option>
                            <?php foreach ([5 => "Excelente", 4 => "Muy bueno", 3 => "Bueno", 2 => "Regular", 1 => "Malo"] as $valor => $texto): ?>
                                <option value="<?= $valor ?>" <?= (int) ($viejos["calificacion"] ?? 0) === $valor ? "selected" : "" ?>>
                                    <?= $valor ?> - <?= $texto ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="comentario">Tu opinión</label>
                        <textarea id="comentario" name="comentario" rows="5" maxlength="1000" required><?= htmlspecialchars($viejos["comentario"] ?? "") ?></textarea>

                        <button type="submit" class="btn btn--dorado">Publicar opinión</button>
                    </form>
                <?php else: ?>
                    <p class="opiniones_aviso">
                        Para dejar tu opinión necesitas <a href="login.php">iniciar sesión</a>
                        o <a href="registro.php">crear una cuenta</a>.
                    </p>
                <?php endif; ?>
            </div>

            <!-- Listado de opiniones -->
            <div class="opiniones_lista">
                <h2 class="opiniones_subtitulo">Lo que dicen nuestros clientes</h2>

                <?php if (empty($opiniones)): ?>
                    <p class="opiniones_vacio">Aún no hay opiniones. ¡Sé el primero en compartir tu experiencia!</p>
                <?php else: ?>
                    <?php foreach ($opiniones as $o): ?>
                        <article class="opinion_card">
                            <?php $n = max(0, min(5, (int) $o["calificacion"])); ?>
                            <p class="opinion_calificacion" aria-label="Calificación: <?= $n ?> de 5">
                                <span class="estrellas" aria-hidden="true"><?= str_repeat("★", $n) ?><span class="estrellas_vacias"><?= str_repeat("★", 5 - $n) ?></span></span>
                                <span class="opinion_numero"><?= $n ?>/5</span>
                            </p>
                            <p class="opinion_comentario"><?= nl2br(htmlspecialchars($o["comentario"])) ?></p>
                            <p class="opinion_autor">
                                <?= htmlspecialchars($o["usuario"]) ?>
                                · <?= $o["abogado"] ? "Sobre " . htmlspecialchars($o["abogado"]) : "Opinión general del despacho" ?>
                                · <?= date("d/m/Y", strtotime($o["creado_en"])) ?>
                            </p>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer class="footer">
        <p>&copy; 2026 Dizar Asociados - Firma de abogados</p>
    </footer>

    <script src="../Controlador/script.js"></script>
</body>
</html>