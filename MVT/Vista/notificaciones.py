import logging
import re

from Modelo.conexion import consultar_todos, consultar_uno, ejecutar

# Enlaces internos permitidos: "/caso?id=3" y también los antiguos "caso.php?id=3"
ENLACE_RE = re.compile(r"/?([a-z_]+)(?:\.php)?(\?[A-Za-z0-9=&_%-]*)?")


# ---------- Leer ----------

def contar_notificaciones(usuario_id):
    """Cantidad de notificaciones sin leer (el número rojo de la campana)."""
    try:
        fila = consultar_uno(
            "SELECT COUNT(*) AS total FROM notificaciones WHERE usuario_id = %s AND leida = 0",
            (usuario_id,),
        )
        return int(fila["total"]) if fila else 0
    except Exception:
        return 0   # si algo falla, el menú simplemente no muestra el número


def listar_notificaciones(usuario_id, limite=50):

    filas = consultar_todos(
        "SELECT id, mensaje, enlace, leida, creado_en FROM notificaciones "
        "WHERE usuario_id = %s ORDER BY creado_en DESC, id DESC LIMIT %s",
        (usuario_id, limite),
    )
    for f in filas:
        f["fecha"] = str(f["creado_en"])[:16]   # 2026-10-30 14:05
    return filas


def normalizar_destino(enlace):

    m = ENLACE_RE.fullmatch(enlace or "")
    if not m:
        return "/notificaciones"
    return "/" + m.group(1) + (m.group(2) or "")


def abrir_notificacion(usuario_id, notificacion_id):

    fila = consultar_uno(
        "SELECT enlace FROM notificaciones WHERE id = %s AND usuario_id = %s",
        (notificacion_id, usuario_id),
    )
    if not fila:
        return None
    ejecutar(
        "UPDATE notificaciones SET leida = 1 WHERE id = %s AND usuario_id = %s",
        (notificacion_id, usuario_id),
    )
    return normalizar_destino(fila["enlace"])


def marcar_todas_leidas(usuario_id):
    ejecutar(
        "UPDATE notificaciones SET leida = 1 WHERE usuario_id = %s AND leida = 0",
        (usuario_id,),
    )


# ---------- Crear (equivale a notificaciones_util.php) ----------

def crear_notificacion(usuario_id, mensaje, enlace=""):
    """Guarda una notificación. Si falla, solo lo anota en el registro: nunca rompe la página."""
    try:
        if len(mensaje) > 255:
            mensaje = mensaje[:254] + "…"
        ejecutar(
            "INSERT INTO notificaciones (usuario_id, mensaje, enlace) VALUES (%s, %s, %s)",
            (usuario_id, mensaje, enlace or None),
        )
    except Exception:
        logging.exception("No se pudo crear la notificación")


def notificar_admins(mensaje, enlace=""):
    """Avisa a todos los administradores activos."""
    try:
        for admin in consultar_todos("SELECT id FROM usuarios WHERE rol = 'admin' AND activo = 1"):
            crear_notificacion(admin["id"], mensaje, enlace)
    except Exception:
        logging.exception("No se pudo avisar a los administradores")


def usuario_id_de_abogado(abogado_id):
    """Cuenta de usuario ligada a una ficha de abogado (o None si no tiene)."""
    try:
        fila = consultar_uno("SELECT usuario_id FROM abogados WHERE id = %s", (abogado_id,))
        return fila["usuario_id"] if fila and fila["usuario_id"] is not None else None
    except Exception:
        return None


# ---------- Avisos concretos del módulo de casos (los usaremos en el paso de casos) ----------

def notificar_nuevo_caso(caso_id, titulo):
    """Un cliente registró un caso nuevo -> se avisa a los administradores."""
    notificar_admins(f"Nuevo caso registrado: «{titulo}».", f"/caso?id={caso_id}")


def notificar_asignacion(caso_id, cliente_id, titulo, abogado_id, abogado_nombre):
    """Se asignó un abogado -> se avisa al cliente y al abogado."""
    enlace = f"/caso?id={caso_id}"
    crear_notificacion(cliente_id, f"Tu caso «{titulo}» fue asignado a {abogado_nombre}.", enlace)
    usuario_abogado = usuario_id_de_abogado(abogado_id)
    if usuario_abogado is not None:
        crear_notificacion(usuario_abogado, f"Se te asignó el caso «{titulo}».", enlace)


def notificar_estado(caso_id, cliente_id, titulo, estado_texto, cambio_estado):
    """Cambió el estado o se agregó una nota -> se avisa al cliente."""
    enlace = f"/caso?id={caso_id}"
    if cambio_estado:
        crear_notificacion(cliente_id, f"Tu caso «{titulo}» ahora está en estado «{estado_texto}».", enlace)
    else:
        crear_notificacion(cliente_id, f"Hay una nueva nota en tu caso «{titulo}».", enlace)


def notificar_mensaje_caso(caso, rol_remitente, nombre):
    """Mensaje nuevo en el chat de un caso."""
    enlace = f"/caso?id={caso['id']}"
    titulo = caso["titulo"]
    if rol_remitente == "cliente":
        texto = f"{nombre} escribió en el caso «{titulo}»."
        destino = usuario_id_de_abogado(caso["abogado_id"]) if caso.get("abogado_id") else None
        if destino is not None:
            crear_notificacion(destino, texto, enlace)
        else:
            notificar_admins(texto, enlace)
    else:
        crear_notificacion(caso["cliente_id"], f"Tienes un mensaje nuevo en tu caso «{titulo}».", enlace)


def notificar_documento(caso, nombre_archivo):
    """Se subió un documento de avance -> se avisa al cliente."""
    crear_notificacion(
        caso["cliente_id"],
        f"Hay un documento nuevo en tu caso «{caso['titulo']}»: {nombre_archivo}.",
        f"/caso?id={caso['id']}",
    )