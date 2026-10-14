<?php
session_start();
$base = '../';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servicios | Dizar Asociados </title>
    <link rel="stylesheet" href="../Vista/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

<main>
    <section class="servicios">
        <p class="servicios_antetitulo">Especialidades</p>
        <h1 class="servicios_titulo">Áreas de practica</h1>
        <p class="servicios_intro">
            Ofrecemos asesoría legal especializada, eficiente y ética con enfoque en consultoria, negociación litigios con resultados altamente exitosos.
        </p>

        <div class="servicios_grid">
            <div class="servicio_card">
                <h2 class="servicio_card_titulo">Derecho Civil</h2>
                <p class="servicio_card_texto">
                    Asesoría y representación en procesos civiles, protegiendo los derechos e intereses de nuestros clientes con soluciones efectivas.
                </p>
            </div>

        <div class="servicio_card">
            <h2 class="servicio_card_titulo">Derecho de Familia</h2>
            <p class="servicio_card_texto">
                acompañamiento cercano y confidencial en asuntos familiares buscando siempre soluciones justas y de mutuo acuerdo cuando es posible.
            </p>
        </div>

        <div class="servicio_card">
            <h2 class="servicio_card_titulo"> Derecho Laboral </h2>
            <p class="servicio_card_texto">
                Representacion en negociaciones y litigios laborales garantizando el respeto de los derechos de trabajadores y empleadores.
            </p>
        </div>
        </div>
    </section>
</main>
<footer class="footer">
    <p>&copy; 2026 Dizar Asociados - Firma de abogados </p>
</footer>

<script src="../Controlador/script.js"></script>
</body>
</html>