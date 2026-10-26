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

// Notificaciones sin leer (el número de la campana)
$noLeidas = 0;
if ($sesionActiva) {
    require_once __DIR__ . '/../Modelo/conexion.php';
    require_once __DIR__ . '/../Controlador/notificaciones_util.php';
    $noLeidas = contar_notificaciones($conexion, (int) $_SESSION["usuario_id"]);
}
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
            <a href="<?= $base ?>Vista/notificaciones.php" class="notif_campana"
               aria-label="Notificaciones<?= $noLeidas > 0 ? " ($noLeidas sin leer)" : "" ?>">
                <svg viewBox="0 0 24 24" class="notif_icono" aria-hidden="true">
                    <path d="M6 9a6 6 0 0 1 12 0c0 6 2 7 2 7H4s2-1 2-7"></path>
                    <path d="M10 19a2 2 0 0 0 4 0"></path>
                </svg>
                <?php if ($noLeidas > 0): ?>
                    <span class="notif_contador"><?= $noLeidas > 99 ? "99+" : $noLeidas ?></span>
                <?php endif; ?>
            </a>

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
                    <a class="perfil_enlace" href="<?= $base ?>Vista/notificaciones.php">
                        Notificaciones<?= $noLeidas > 0 ? " ($noLeidas)" : "" ?>
                    </a>
                    <?php if ($rolActual === "cliente"): ?>
                        <a class="perfil_enlace" href="<?= $base ?>Vista/mis_casos.php">Mis casos</a>
                    <?php endif; ?>
                    <?php if ($esPersonal): ?>
                        <a class="perfil_enlace" href="<?= $base ?>Vista/casos.php">Casos</a>
                        <a class="perfil_enlace" href="<?= $base ?>Vista/consultas.php">Consultas</a>
                    <?php endif; ?>
                    <?php if ($rolActual === "admin"): ?>
                        <a class="perfil_enlace" href="<?= $base ?>Vista/abogados.php">Abogados</a>
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