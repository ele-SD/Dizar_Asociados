<?php
session_start();
$base = '../';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nosotros | Dizar Asociados</title>
    <link rel="stylesheet" href="../estilos/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main>
        <section class="nosotros_intro">
            <p class="nosotros_antetitulo">Quiénes somos</p>
            <h1 class="nosotros_titulo">Compromiso, ética y resultados</h1>
            <p class="nosotros_texto">
                Dizar Asociados es una firma de abogados con sede en Montelíbano, Córdoba, dedicada a brindar
                asesoría legal especializada en derecho civil, de familia y laboral. Nuestro enfoque combina
                rigor jurídico con un trato cercano a cada cliente, buscando siempre soluciones justas y efectivas.
            </p>
        </section>

        <section class="valores">
            <h2 class="valores_titulo">Nuestros valores</h2>
            <div class="valores_grid">
                <div class="valor_card">
                    <h3 class="valor_card_titulo">Ética</h3>
                    <p class="valor_card_texto">Actuamos con transparencia y honestidad en cada proceso legal que asumimos.</p>
                </div>
                <div class="valor_card">
                    <h3 class="valor_card_titulo">Compromiso</h3>
                    <p class="valor_card_texto">Nos involucramos de forma cercana con cada caso, priorizando los intereses de nuestros clientes.</p>
                </div>
                <div class="valor_card">
                    <h3 class="valor_card_titulo">Excelencia</h3>
                    <p class="valor_card_texto">Buscamos resultados sólidos mediante preparación rigurosa y atención al detalle.</p>
                </div>
            </div>
        </section>

        <section class="equipo_previo">
            <p class="equipo_antetitulado">Nuestros Socios</p>
            <h2 class="equipo_titulado">El equipo</h2>

            <div class="equpo_grid">
                <div class="equpo_card">
                    <div class="equipo_avatar">
                        <svg viewBox="0 0 24 24" class="equipo_avatar_icono">
                            <circle cx="12" cy="8" r="4"></circle>
                            <path d="M4 20c0-4.4 3.6-8 8-8s8 3.6 8 8"></path>
                        </svg>
                    </div>
                    <h3 class="equpo_nombre">Nombre Apellido</h3>
                    <p class="equpo_area">Derecho Civil</p>
                </div>

                <div class="equpo_card">
                    <div class="equipo_avatar">
                        <svg viewBox="0 0 24 24" class="equipo_avatar_icono">
                            <circle cx="12" cy="8" r="4"></circle>
                            <path d="M4 20c0-4.4 3.6-8 8-8s8 3.6 8 8"></path>
                        </svg>
                    </div>
                    <h3 class="equpo_nombre">Nombre Apellido</h3>
                    <p class="equpo_area">Derecho de Familia</p>
                </div>

                <div class="equpo_card">
                    <div class="equipo_avatar">
                        <svg viewBox="0 0 24 24" class="equipo_avatar_icono">
                            <circle cx="12" cy="8" r="4"></circle>
                            <path d="M4 20c0-4.4 3.6-8 8-8s8 3.6 8 8"></path>
                        </svg>
                    </div>
                    <h3 class="equpo_nombre">Nombre Apellido</h3>
                    <p class="equpo_area">Derecho Laboral</p>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <p>&copy; 2026 Dizar Asociados - Firma de abogados</p>
    </footer>

    <script src="../js/script.js"></script>
</body>
</html>