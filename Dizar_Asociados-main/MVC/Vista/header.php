<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base = $base ?? '../';
$sesionActiva = isset($_SESSION["usuario_id"]);
$rolActual = $_SESSION["rol"] ?? "";
$esPersonal = in_array($rolActual, ["admin", "abogado"], true);

$nombreUsuario = $_SESSION["usuario"] ?? "";
$etiquetasRol = ["admin" => "Administrador", "abogado" => "Abogado", "cliente" => "Cliente"];
$rolTexto = $etiquetasRol[$rolActual] ?? "";
?>
<header class="header">
    <nav class="nav">
                <a href="<?= $base ?>Vista/index.php" class="nav_logo">
            <img src="<?= $base ?>Vista/imagenes/isotipo-dorado1.png" alt="isotipo Dizar Asociados" class="nav_logo_img">
            <span class="nav_logo_texto"> Dizar Asociados</span>
        </a>
        <ul class="nav_menu">
            <li><a href="<?= $base ?>Vista/index.php">Inicio</a></li>
            <li><a href="<?= $base ?>Vista/nosotros.php">Nosotros</a></li>
            <li><a href="<?= $base ?>Vista/servicios.php">Servicios</a></li>
            <li><a href="<?= $base ?>Vista/contacto.php">Contacto</a></li>
        </ul>

        <?php if ($sesionActiva): ?>
            <div class="perfil_menu">
                <button class="perfil_boton" id="perfilBoton" type="button" aria-label="Menú de la cuenta">
                    <svg viewBox="0 0 24 24" class="perfil_icono">
                        <circle cx="12" cy="8" r="4"></circle>
                        <path d="M4 20c0-4.4 3.6-8 8-8s8 3.6 8 8"></path>
                    </svg>
                </button>
                <span class="perfil_nombre"><?= htmlspecialchars($nombreUsuario) ?></span>
                <div class="perfil_dropdown" id="perfilDropdown">
                    <div class="perfil_cabecera">
                        <span class="perfil_cabecera_nombre"><?= htmlspecialchars($nombreUsuario) ?></span>
                        <?php if ($rolTexto !== ""): ?>
                            <span class="perfil_cabecera_rol"><?= $rolTexto ?></span>
                        <?php endif; ?>
                    </div>

                    <a class="perfil_enlace" href="<?= $base ?>Vista/dashboard.php">Mi cuenta</a>
                    <?php if ($esPersonal): ?>
                        <a class="perfil_enlace" href="<?= $base ?>Vista/consultas.php">Consultas</a>
                    <?php endif; ?>
                    <?php if ($rolActual === "admin"): ?>
                        <a class="perfil_enlace" href="<?= $base ?>Vista/usuarios.php">Usuarios</a>
                    <?php endif; ?>
                    <a class="perfil_enlace" href="<?= $base ?>Vista/ajustes.php">Ajustes</a>
                    <a class="perfil_enlace perfil_enlace--salir" href="<?= $base ?>Controlador/logout.php">Cerrar sesión</a>
                </div>
            </div>
        <?php else: ?>
            <a href="<?= $base ?>Vista/login.php" class="btn-login">Iniciar sesión</a>
        <?php endif; ?>
    </nav>
</header>