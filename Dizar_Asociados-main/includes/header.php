<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// $base = '' en la raíz, '../' dentro de apartado/
$base = $base ?? '';
$sesionActiva = isset($_SESSION["usuario_id"]);
?>
<header class="header">
    <nav class="nav">
        <a href="<?= $base ?>Principal/index.php" class="nav_logo">
            <img src="<?= $base ?>imagenes/isotipo-dorado1.png" alt="isotipo Dizar Asociados" class="nav_logo_img">
            <span class="nav_logo_texto"> Dizar Asociados</span>
        </a>
        <ul class="nav_menu">
            <li><a href="<?= $base ?>Principal/index.php">Inicio</a></li>
            <li><a href="<?= $base ?>apartado/nosotros.php">Nosotros</a></li>
            <li><a href="<?= $base ?>apartado/servicios.php">Servicios</a></li>
            <li><a href="<?= $base ?>apartado/contacto.php">Contacto</a></li>
        </ul>

        <?php if ($sesionActiva): ?>
            <div class="perfil_menu">
                <button class="perfil_boton" id="perfilBoton" type="button">
                    <svg viewBox="0 0 24 24" class="perfil_icono">
                        <circle cx="12" cy="8" r="4"></circle>
                        <path d="M4 20c0-4.4 3.6-8 8-8s8 3.6 8 8"></path>
                    </svg>
                </button>
                <div class="perfil_dropdown" id="perfilDropdown">
                    <p class="perfil_correo"><?= htmlspecialchars($_SESSION["usuario"] ?? $_SESSION["correo"]) ?></p>
                    <a href="<?= $base ?>Principal/dashboard.php">Mi cuenta</a>
                    <a href="<?= $base ?>apartado/ajustes.php">Ajustes</a>
                    <a href="<?= $base ?>Principal/logout.php">Cerrar sesión</a>
                </div>
            </div>
        <?php else: ?>
            <a href="<?= $base ?>Principal/login.php" class="btn-login">Iniciar sesión</a>
        <?php endif; ?>
    </nav>
</header>