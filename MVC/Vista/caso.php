<?php
session_start();
require_once __DIR__ . '/../Controlador/auth.php';
require_once __DIR__ . '/../Controlador/casos_util.php';
require_once __DIR__ . '/../Controlador/notificaciones_util.php';
require_once __DIR__ . '/../Controlador/documentos_util.php';
require_once __DIR__ . '/../Modelo/conexion.php';

// Pueden entrar el cliente dueño del caso, el abogado asignado y el administrador
requerir_login();

$base = '../';
$miId = (int) $_SESSION["usuario_id"];
$rol  = $_SESSION["rol"] ?? "";
$casoId = (int) ($_GET["id"] ?? 0);

if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

$stmt = $conexion->prepare(
    "SELECT c.id, c.cliente_id, c.abogado_id, c.tipo, c.titulo, c.descripcion, c.estado,
            c.creado_en, c.actualizado_en,
            s.nombre AS servicio, a.nombre AS abogado, u.usuario AS cliente
     FROM casos c
     LEFT JOIN servicios s ON s.id = c.servicio_id
     LEFT JOIN abogados a ON a.id = c.abogado_id
     INNER JOIN usuarios u ON u.id = c.cliente_id
     WHERE c.id = ?"
);
$stmt->bind_param("i", $casoId);
$stmt->execute();
$caso = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Quién puede ver este caso
$permitido = false;
if ($caso) {
    if ($rol === "admin") {
        $permitido = true;
    } elseif ($rol === "cliente") {
        $permitido = (int) $caso["cliente_id"] === $miId;
    } elseif ($rol === "abogado") {
        $miAbogadoId = abogado_id_de_usuario($conexion, $miId);
        $permitido = $miAbogadoId !== null && (int) $caso["abogado_id"] === $miAbogadoId;
    }
}

// Mismo mensaje si no existe o si no es suyo, para no revelar qué casos hay
if (!$permitido) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="es"><head><meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caso no encontrado | Dizar Asociados</title>
    <link rel="stylesheet" href="../Vista/style.css"></head>
    <body>
    <?php include __DIR__ . '/header.php'; ?>
    <main class="usuarios">
        <h1>Caso no encontrado</h1>
        <p class="consultas_vacio">No existe o no tienes permiso para verlo.</p>
        <a href="<?= $rol === "cliente" ? "mis_casos.php" : "casos.php" ?>" class="btn-volver" aria-label="Volver a los casos" title="Volver a los casos">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M19 12H5M11 6l-6 6 6 6"/>
            </svg>
        </a>
    </main>
    </body></html>
    <?php
    exit();
}

// El chat se cierra cuando el caso está cerrado (el historial se sigue viendo)
$chatAbierto = $caso["estado"] !== "cerrado";

// Enviar un mensaje (POST) -> se guarda y se redirige para evitar reenvíos
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) {
        die("Solicitud no válida.");
    }

    $texto = trim($_POST["mensaje"] ?? "");

    if (!$chatAbierto) {
        $_SESSION["flash_chat"] = ["tipo" => "error", "mensaje" => "Este caso está cerrado, ya no se pueden enviar mensajes."];
    } elseif ($texto === "") {
        $_SESSION["flash_chat"] = ["tipo" => "error", "mensaje" => "Escribe un mensaje antes de enviar."];
    } elseif (mb_strlen($texto) > 1000) {
        $_SESSION["flash_chat"] = ["tipo" => "error", "mensaje" => "El mensaje es demasiado largo (máximo 1000 caracteres)."];
    } else {
        try {
            $stmt = $conexion->prepare("INSERT INTO mensajes (caso_id, autor_id, contenido) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $casoId, $miId, $texto);
            $stmt->execute();
            $stmt->close();

            // Avisar a la otra parte (cliente <-> abogado/administrador)
            notificar_mensaje_caso($conexion, $caso, $rol, $_SESSION["usuario"] ?? "Alguien");
        } catch (Throwable $e) {
            $_SESSION["flash_chat"] = ["tipo" => "error", "mensaje" => "No se pudo enviar el mensaje. Intenta de nuevo."];
        }
    }

    header("Location: caso.php?id=" . $casoId . "#chat");
    exit();
}

$flashChat = $_SESSION["flash_chat"] ?? null;
unset($_SESSION["flash_chat"]);

// Al abrir el caso, los mensajes de la otra parte quedan como leídos
$stmt = $conexion->prepare("UPDATE mensajes SET leido = 1 WHERE caso_id = ? AND autor_id <> ? AND leido = 0");
$stmt->bind_param("ii", $casoId, $miId);
$stmt->execute();
$stmt->close();

$stmt = $conexion->prepare(
    "SELECT s.estado, s.nota, s.creado_en, u.usuario, u.rol
     FROM caso_seguimiento s
     INNER JOIN usuarios u ON u.id = s.usuario_id
     WHERE s.caso_id = ?
     ORDER BY s.creado_en ASC, s.id ASC"
);
$stmt->bind_param("i", $casoId);
$stmt->execute();
$seguimiento = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conexion->prepare(
    "SELECT m.autor_id, m.contenido, m.creado_en, u.usuario, u.rol
     FROM mensajes m
     INNER JOIN usuarios u ON u.id = m.autor_id
     WHERE m.caso_id = ?
     ORDER BY m.creado_en ASC, m.id ASC"
);
$stmt->bind_param("i", $casoId);
$stmt->execute();
$mensajes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Documentos de avance del caso
$stmt = $conexion->prepare(
    "SELECT d.id, d.usuario_id, d.nombre_original, d.tamano, d.descripcion, d.creado_en, u.usuario, u.rol
     FROM caso_documentos d
     INNER JOIN usuarios u ON u.id = d.usuario_id
     WHERE d.caso_id = ?
     ORDER BY d.creado_en DESC, d.id DESC"
);
$stmt->bind_param("i", $casoId);
$stmt->execute();
$documentos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$flashDocs = $_SESSION["flash_docs"] ?? null;
unset($_SESSION["flash_docs"]);

// Solo el personal sube documentos (aquí ya se comprobó que es admin o el abogado del caso)
$puedeSubir = in_array($rol, ["admin", "abogado"], true);

$volverA = $rol === "cliente" ? "mis_casos.php" : "casos.php";
$etiquetasRol = ["admin" => "Administrador", "abogado" => "Abogado", "cliente" => "Cliente"];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($caso["titulo"]) ?> | Dizar Asociados</title>
<link rel="stylesheet" href="../Vista/style.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="usuarios">
    <a href="<?= $volverA ?>" class="btn-volver" aria-label="Volver a los casos" title="Volver a los casos">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M19 12H5M11 6l-6 6 6 6"/>
        </svg>
    </a>

    <div class="caso_cabecera">
        <h1><?= htmlspecialchars($caso["titulo"]) ?></h1>
        <span class="caso_estado caso_estado--<?= htmlspecialchars($caso["estado"]) ?>">
            <?= htmlspecialchars(CASO_ESTADOS[$caso["estado"]] ?? $caso["estado"]) ?>
        </span>
    </div>

    <dl class="caso_datos">
        <div>
            <dt>Tipo</dt>
            <dd><?= htmlspecialchars(CASO_TIPOS[$caso["tipo"]] ?? $caso["tipo"]) ?></dd>
        </div>
        <div>
            <dt>Área</dt>
            <dd><?= htmlspecialchars($caso["servicio"] ?? "Sin área") ?></dd>
        </div>
        <div>
            <dt>Cliente</dt>
            <dd><?= htmlspecialchars($caso["cliente"]) ?></dd>
        </div>
        <div>
            <dt>Abogado</dt>
            <dd><?= htmlspecialchars($caso["abogado"] ?? "Por asignar") ?></dd>
        </div>
        <div>
            <dt>Registrado</dt>
            <dd><?= htmlspecialchars(substr($caso["creado_en"], 0, 16)) ?></dd>
        </div>
    </dl>

    <h2 class="caso_subtitulo">Descripción</h2>
    <p class="caso_descripcion"><?= nl2br(htmlspecialchars($caso["descripcion"])) ?></p>

    <h2 class="caso_subtitulo">Seguimiento</h2>
    <ol class="caso_linea">
        <?php foreach ($seguimiento as $p): ?>
            <li>
                <span class="caso_estado caso_estado--<?= htmlspecialchars($p["estado"]) ?>">
                    <?= htmlspecialchars(CASO_ESTADOS[$p["estado"]] ?? $p["estado"]) ?>
                </span>
                <?php if (trim((string) $p["nota"]) !== ""): ?>
                    <p class="caso_linea_nota"><?= nl2br(htmlspecialchars($p["nota"])) ?></p>
                <?php endif; ?>
                <p class="caso_linea_meta">
                    <?= htmlspecialchars($p["usuario"]) ?> (<?= htmlspecialchars($etiquetasRol[$p["rol"]] ?? $p["rol"]) ?>),
                    <?= htmlspecialchars(substr($p["creado_en"], 0, 16)) ?>
                </p>
            </li>
        <?php endforeach; ?>
    </ol>

        <h2 class="caso_subtitulo" id="documentos">Documentos y avances</h2>

    <?php if ($flashDocs): ?>
        <div class="<?= $flashDocs["tipo"] === "exito" ? "exito" : "error" ?>" role="alert">
            <?= htmlspecialchars($flashDocs["mensaje"]) ?>
        </div>
    <?php endif; ?>

    <?php if (!$documentos): ?>
        <p class="consultas_vacio">Todavía no se han subido documentos a este caso.</p>
    <?php else: ?>
        <ul class="doc_lista">
            <?php foreach ($documentos as $d): ?>
                <li class="doc_item">
                    <div class="doc_info">
                        <a class="doc_nombre" href="../Controlador/descargar_documento.php?id=<?= (int) $d["id"] ?>">
                            <?= htmlspecialchars($d["nombre_original"]) ?>
                        </a>
                        <?php if (trim((string) $d["descripcion"]) !== ""): ?>
                            <p class="doc_descripcion"><?= htmlspecialchars($d["descripcion"]) ?></p>
                        <?php endif; ?>
                        <p class="doc_meta">
                            <?= htmlspecialchars($d["usuario"]) ?> (<?= htmlspecialchars($etiquetasRol[$d["rol"]] ?? $d["rol"]) ?>)
                            · <?= htmlspecialchars(substr($d["creado_en"], 0, 16)) ?>
                            · <?= htmlspecialchars(formatear_tamano((int) $d["tamano"])) ?>
                        </p>
                    </div>

                    <?php if ($puedeSubir && ($rol === "admin" || (int) $d["usuario_id"] === $miId)): ?>
                        <form method="POST" action="../Controlador/subir_documento.php"
                              onsubmit="return confirm('¿Eliminar este documento?');">
                            <input type="hidden" name="csrf" value="<?= $_SESSION["csrf"] ?>">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="caso_id" value="<?= (int) $caso["id"] ?>">
                            <input type="hidden" name="documento_id" value="<?= (int) $d["id"] ?>">
                            <button type="submit" class="btn btn--contorno">Eliminar</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($puedeSubir): ?>
        <form method="POST" action="../Controlador/subir_documento.php" enctype="multipart/form-data" class="doc_form">
            <input type="hidden" name="csrf" value="<?= $_SESSION["csrf"] ?>">
            <input type="hidden" name="accion" value="subir">
            <input type="hidden" name="caso_id" value="<?= (int) $caso["id"] ?>">

            <label for="documento">Subir un avance (Word, PDF, Excel, PowerPoint o imagen, máximo 10 MB)</label>
            <input type="file" id="documento" name="documento" required
                   accept=".pdf,.doc,.docx,.xls,.xlsx,.pptx,.jpg,.jpeg,.png">

            <label for="doc_descripcion">Descripción del avance (opcional)</label>
            <input type="text" id="doc_descripcion" name="descripcion" maxlength="255"
                   placeholder="Ej. Borrador de la demanda para revisión">

            <button type="submit" class="btn btn--dorado">Subir documento</button>
            <p class="doc_meta">Al subirlo, el cliente recibe una notificación.</p>
        </form>
    <?php endif; ?>
    
    <h2 class="caso_subtitulo" id="chat">Mensajes</h2>

    <?php if ($flashChat): ?>
        <div class="<?= $flashChat["tipo"] === "exito" ? "exito" : "error" ?>" role="alert">
            <?= htmlspecialchars($flashChat["mensaje"]) ?>
        </div>
    <?php endif; ?>

    <div class="chat_lista" id="chatLista">
        <?php if (!$mensajes): ?>
            <p class="consultas_vacio">Todavía no hay mensajes en este caso.</p>
        <?php else: ?>
            <?php foreach ($mensajes as $m): ?>
                <div class="chat_msg<?= (int) $m["autor_id"] === $miId ? " chat_msg--propio" : "" ?>">
                    <p class="chat_msg_meta">
                        <?= htmlspecialchars($m["usuario"]) ?> (<?= htmlspecialchars($etiquetasRol[$m["rol"]] ?? $m["rol"]) ?>)
                        · <?= htmlspecialchars(substr($m["creado_en"], 0, 16)) ?>
                    </p>
                    <p class="chat_msg_texto"><?= nl2br(htmlspecialchars($m["contenido"])) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($chatAbierto): ?>
        <form method="POST" action="caso.php?id=<?= (int) $caso["id"] ?>" class="chat_form">
            <input type="hidden" name="csrf" value="<?= $_SESSION["csrf"] ?>">
            <label for="mensaje" class="chat_form_etiqueta">Escribe un mensaje</label>
            <textarea id="mensaje" name="mensaje" rows="3" maxlength="1000" required></textarea>
            <button type="submit" class="btn btn--dorado">Enviar</button>
        </form>
    <?php else: ?>
        <p class="consultas_vacio">Este caso está cerrado, así que ya no se pueden enviar mensajes.</p>
    <?php endif; ?>
</main>

<script>
// Deja la conversación vista desde el último mensaje
const chatLista = document.getElementById("chatLista");
if (chatLista) chatLista.scrollTop = chatLista.scrollHeight;
</script>

<script src="../Controlador/script.js"></script>
</body>
</html>