<?php
session_start();
$sesionActiva = isset($_SESSION["usuario_id"]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dizar Asociados | Firma de Abogados</title>
    <link rel="stylesheet" href="estilos/style.css">
</head>
<body>
    <header class="header">
        <nav class="nav">
        <a href="index.php" class="nav_logo">
            <img src="imagenes/isotipo-dorado1.png" alt="isotipo Dizar Asociados" class="nav_logo_img">
            <span class="nav_logo_texto"> Dizar Asociados</span>
        </a>
        <ul class="nav_menu">
            <li><a href="index.php">Inicio</a></li>
            <li><a href="apartado/nosotros.php">Nosotros</a></li>
            <li><a href="apartado/servicios.php">Servicios</a></li>
            <li><a href="apartado/contacto.php">Contacto</a></li>
        </ul>

        <?php if ($sesionActiva): ?>
            <div class="perfil_menu">
                <button class="perfil_boton" id="perfilBoton">
                    <svg viewBox="0 0 24 24" class="perfil_icono">
                        <circle cx="12" cy="8" r="4"></circle>
                        <path d="M4 20c0-4.4 3.6-8 8-8s8 3.6 8 8"></path>
                    </svg>
                </button>
                <div class="perfil_dropdown" id="perfilDropdown">
                    <p class="perfil_correo"><?= htmlspecialchars($_SESSION["correo"]) ?></p>
                    <a href="dashboard.php">Mi cuenta</a>
                    <a href="logout.php">Cerrar sesión</a>
                </div>
            </div>
        <?php else: ?>
            <a href="login.php" class="btn-login">Iniciar sesión</a>
        <?php endif; ?>
    </nav>
</header>

<main>
    <section class="hero">
        <div class="hero_contenido">
            <p class="hero_antetitulo">Montelibano-Cordoba, Colombia</p>
            <h1 class="hero_titulo"> Justicia con <em>precisión</em> y convicción </h1>
        <p class="hero_texto">
            Asesoramiento legal especializado, eficiente y ético en derecho civil, de familia y laboral, reconocida por su enfoque en consultoría, negociación y litigios con resultados altamente exitosos. Protegemos sus derechos con soluciones innovadoras y efectivas.
        </p>

        <div class="hero_botones">
    <a href="apartado/contacto.php" class="btn btn--dorado">Agendar consulta</a>
    <a href="apartado/nosotros.php" class="btn btn--contorno">Conocer el Despacho</a>
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

<script src="js/script.js"></script>
</body>
</html>