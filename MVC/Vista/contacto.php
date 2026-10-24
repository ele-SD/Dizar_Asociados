<?php
session_start();
date_default_timezone_set('America/Bogota');
$base = '../';

// Primera fecha que se puede pedir: dentro de una hora
$fechaMinima = date('Y-m-d\TH:i', strtotime('+1 hour'));

if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

// Mensaje y datos que deja procesar_contacto.php (se muestran una sola vez)
$flash  = $_SESSION["flash_contacto"] ?? null;
$viejos = $_SESSION["viejos_contacto"] ?? [];
unset($_SESSION["flash_contacto"], $_SESSION["viejos_contacto"]);

function viejo(array $viejos, string $campo): string {
    return htmlspecialchars($viejos[$campo] ?? "");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto | Dizar Asociados</title>
    <link rel="stylesheet" href="../Vista/style.css">
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>

    <main>
        <section class="contacto">
            <p class="contacto_antetitulo">Hablemos</p>
            <h1 class="contacto_titulo">Agenda tu consulta</h1>
            <p class="contacto_intro">
                Escríbenos y uno de nuestros abogados se pondrá en contacto contigo a la brevedad.
            </p>

            <?php if ($flash): ?>
                <div class="<?= $flash["tipo"] === "exito" ? "exito" : "error" ?>" role="alert">
                    <?= htmlspecialchars($flash["mensaje"]) ?>
                </div>
            <?php endif; ?>

            <div class="contacto_grid">
                <form class="contacto_form" method="POST" action="../Controlador/procesar_contacto.php">
                    <input type="hidden" name="csrf" value="<?= $_SESSION["csrf"] ?>">

                    <!-- Honeypot anti-spam: oculto para personas -->
                    <div style="position:absolute; left:-9999px;" aria-hidden="true">
                        <label for="sitio_web">No llenar este campo</label>
                        <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
                    </div>

                    <label for="nombre">Nombre completo</label>
                    <input type="text" id="nombre" name="nombre" maxlength="60" required
                           value="<?= viejo($viejos, "nombre") ?>">

                    <label for="correo">Correo electrónico</label>
                    <input type="email" id="correo" name="correo" maxlength="60" required
                           value="<?= viejo($viejos, "correo") ?>">

                    <label for="telefono">Teléfono</label>
                    <input type="text" id="telefono" name="telefono" maxlength="15" required
                           value="<?= viejo($viejos, "telefono") ?>">

                    <label for="area">Área de interés</label>
                    <select id="area" name="area" required>
                        <option value="">-- Selecciona un área --</option>
                        <?php foreach (["Derecho Civil", "Derecho de Familia", "Derecho Laboral"] as $a): ?>
                            <option value="<?= $a ?>" <?= ($viejos["area"] ?? "") === $a ? "selected" : "" ?>><?= $a ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="fecha_preferida">Fecha y hora preferida (lunes a viernes, 8:00 a.m. a 5:00 p.m.)</label>
                    <input type="datetime-local" id="fecha_preferida" name="fecha_preferida" required
                           min="<?= $fechaMinima ?>" step="900"
                           value="<?= viejo($viejos, "fecha_preferida") ?>">

                    <label for="mensaje">Mensaje</label>
                    <textarea id="mensaje" name="mensaje" rows="5" maxlength="2000" required><?= viejo($viejos, "mensaje") ?></textarea>

                    <button type="submit" class="btn btn--dorado">Enviar mensaje</button>
                </form>

                <div class="contacto_info">
                    <h2 class="contacto_info_titulo">Información de contacto</h2>
                    <p><strong>Dirección:</strong> Montelíbano, Córdoba, Colombia</p>
                    <p><strong>Teléfono:</strong> +57 300 000 0000</p>
                    <p><strong>Correo:</strong> contacto@dizarasociados.com</p>
                    <p><strong>Horario:</strong> Lunes a viernes, 8:00 a.m. – 5:00 p.m.</p>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <p>&copy; 2026 Dizar Asociados - Firma de abogados</p>
    </footer>

    <script src="../Controlador/script.js"></script>
</body>
</html>