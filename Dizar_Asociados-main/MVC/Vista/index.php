<?php
session_start();
$base = '../';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dizar Asociados | Firma de Abogados</title>
    <link rel="stylesheet" href="../Vista/style.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main>
    <section class="hero">
        <div class="hero_contenido">
            <p class="hero_antetitulo">Montelibano-Cordoba, Colombia</p>
            <h1 class="hero_titulo"> Justicia con <em>precisión</em> y convicción </h1>
        <p class="hero_texto">
            Asesoramiento legal especializado, eficiente y ético en derecho civil, de familia y laboral, reconocida por su enfoque en consultoría, negociación y litigios con resultados altamente exitosos. Protegemos sus derechos con soluciones innovadoras y efectivas.
        </p>

        <div class="hero_botones">
    <a href="../Vista/contacto.php" class="btn btn--dorado">Agendar consulta</a>
    <a href="../Vista/nosotros.php" class="btn btn--contorno">Conocer el Despacho</a>
    <a href="../Vista/opiniones.php" class="btn btn--contorno">Opiniones</a>
</div>
        </div>
    </section>
<section class="equipo_previo">
    <p class="equipo_antetitulado"> Nuestros Socios</p>
    <h2 class="equipo_titulado"> El equipo </h2>

    <div class="equpo_grid">
        <div class="equpo_card">
            <div class="equipo_avatar">
                <svg viewBox="0 0 24 24" class="equipo_avatar_icono">
                    <circle cx="12" cy="8" r="4"></circle>
                    <path d="M4 20c0-4.4 3.6-8 8-8s8 3.6 8 8"></path>
                </svg>
            </div>
            <h3 class="equpo_nombre"> Nombre Apellido </h3>
            <p class="equpo_area"> Derecho Civil </p>
        </div>
        <div class="equpo_card">
            <div class="equipo_avatar">
                <svg viewBox="0 0 24 24" class="equipo_avatar_icono">
                    <circle cx="12" cy="8" r="4"></circle>
                    <path d="M4 20c0-4.4 3.6-8 8-8s8 3.6 8 8"></path>
                </svg>
            </div>
            <h3 class="equpo_nombre">Nombre Apellido</h3>
            <p class="equpo_area"> Derecho de Familia </p>
        </div>

        <div class="equpo_card">
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