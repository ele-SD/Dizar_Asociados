<?php
session_start();
$base = '../';
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
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main>
        <section class="contacto">
            <p class="contacto_antetitulo">Hablemos</p>
            <h1 class="contacto_titulo">Agenda tu consulta</h1>
            <p class="contacto_intro">
                Escríbenos y uno de nuestros abogados se pondrá en contacto contigo a la brevedad.
            </p>

            <div class="contacto_grid">
                <form class="contacto_form" method="POST" action="procesar_contacto.php">
                    <label for="nombre">Nombre completo</label>
                    <input type="text" id="nombre" name="nombre" maxlength="60" required>

                    <label for="correo">Correo electrónico</label>
                    <input type="email" id="correo" name="correo" maxlength="60" required>

                    <label for="telefono">Teléfono</label>
                    <input type="text" id="telefono" name="telefono" maxlength="15" required>

                    <label for="area">Área de interés</label>
                    <select id="area" name="area" required>
                        <option value="">-- Selecciona un área --</option>
                        <option value="Derecho Civil">Derecho Civil</option>
                        <option value="Derecho de Familia">Derecho de Familia</option>
                        <option value="Derecho Laboral">Derecho Laboral</option>
                    </select>

                    <label for="mensaje">Mensaje</label>
                    <textarea id="mensaje" name="mensaje" rows="5" required></textarea>

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