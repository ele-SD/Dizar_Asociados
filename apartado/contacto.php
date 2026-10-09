<?php
session_start();
$sesionActiva = isset($_SESSION["usuario_id"]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto | Dizar Asociados</title>
    <link rel="stylesheet" href="../estilos/style.css">
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="../index.php" class="nav_logo">
                <img src="../imagenes/isotipo-dorado1.png" alt="isotipo Dizar Asociados" class="nav_logo_img">
                <span class="nav_logo_texto"> Dizar Asociados</span>
            </a>
            <ul class="nav_menu">
                <li><a href="../index.php">Inicio</a></li>
                <li><a href="nosotros.php">Nosotros</a></li>
                <li><a href="servicios.php">Servicios</a></li>
                <li><a href="contacto.php">Contacto</a></li>
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
                        <a href="../dashboard.php">Mi cuenta</a>
                        <a href="../logout.php">Cerrar sesión</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="../login.php" class="btn-login">Iniciar sesión</a>
            <?php endif; ?>
        </nav>
    </header>

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

    <script src="../js/script.js"></script>
</body>
</html>