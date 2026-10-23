<?php
session_start();
require_once __DIR__ . '/../Controlador/auth.php';
require_once __DIR__ . '/../Controlador/casos_util.php';
require_once __DIR__ . '/../Modelo/conexion.php';

// Solo los clientes registran casos
requerir_rol(['cliente']);

$base = '../';

if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

// Mensaje y datos que deja procesar_caso.php (se muestran una sola vez)
$flash  = $_SESSION["flash_casos"] ?? null;
$viejos = $_SESSION["viejos_caso"] ?? [];
unset($_SESSION["flash_casos"], $_SESSION["viejos_caso"]);

function viejo(array $viejos, string $campo): string {
    return htmlspecialchars((string) ($viejos[$campo] ?? ""));
}

$servicios = $conexion->query("SELECT id, nombre FROM servicios WHERE activo = 1 ORDER BY nombre")
                      ->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar caso | Dizar Asociados</title>
    <link rel="stylesheet" href="../Vista/style.css">
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>

    <main>
        <section class="contacto">
            <p class="contacto_antetitulo">Tus casos</p>
            <h1 class="contacto_titulo">Registra tu caso</h1>
            <p class="contacto_intro">
                Cuéntanos qué necesitas. Un abogado de la firma revisará tu caso y te lo asignará según el área.
            </p>

            <?php if ($flash): ?>
                <div class="<?= $flash["tipo"] === "exito" ? "exito" : "error" ?>" role="alert">
                    <?= htmlspecialchars($flash["mensaje"]) ?>
                </div>
            <?php endif; ?>

            <div class="caso_formulario">
                <form class="contacto_form" method="POST" action="../Controlador/procesar_caso.php">
                    <input type="hidden" name="csrf" value="<?= $_SESSION["csrf"] ?>">

                    <label for="tipo">¿Qué necesitas?</label>
                    <select id="tipo" name="tipo" required>
                        <option value="">-- Selecciona --</option>
                        <?php foreach (CASO_TIPOS as $valor => $texto): ?>
                            <option value="<?= $valor ?>" <?= ($viejos["tipo"] ?? "") === $valor ? "selected" : "" ?>><?= $texto ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="servicio_id">Área del derecho</label>
                    <select id="servicio_id" name="servicio_id" required>
                        <option value="">-- Selecciona un área --</option>
                        <?php foreach ($servicios as $s): ?>
                            <option value="<?= (int) $s["id"] ?>" <?= (int) ($viejos["servicio_id"] ?? 0) === (int) $s["id"] ? "selected" : "" ?>>
                                <?= htmlspecialchars($s["nombre"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="titulo">Título del caso</label>
                    <input type="text" id="titulo" name="titulo" maxlength="150" required
                           placeholder="Ej. Despido sin pago de prestaciones"
                           value="<?= viejo($viejos, "titulo") ?>">

                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" rows="7" maxlength="2000" required
                              placeholder="Cuéntanos qué pasó, cuándo y qué esperas lograr."><?= viejo($viejos, "descripcion") ?></textarea>

                    <button type="submit" class="btn btn--dorado">Registrar caso</button>
                </form>
            </div>
        </section>
    </main>

    <footer class="footer">
        <p>&copy; 2026 Dizar Asociados - Firma de abogados</p>
    </footer>

    <script src="../Controlador/script.js"></script>
</body>
</html>