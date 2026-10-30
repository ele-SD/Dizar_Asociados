import logging

import pymysql

from Modelo.conexion import consultar_uno, ejecutar
from Vista.auth import verificar_contrasena
from Vista.cuentas import EMAIL_RE, hashear_contrasena


def obtener_datos(usuario_id):
    """Datos que se muestran en el formulario de ajustes."""
    return consultar_uno(
        "SELECT usuario, correo, tipo_documento, numero_documento FROM usuarios WHERE id = %s",
        (usuario_id,),
    ) or {}


def actualizar_perfil(usuario_id, form):
    """Cambia usuario y correo. Devuelve (error, usuario_nuevo, correo_nuevo)."""
    usuario = form.get("usuario", "").strip()
    correo = form.get("correo", "").strip()

    if not usuario or not correo:
        return "Completa el usuario y el correo.", None, None
    if len(usuario) > 50:
        return "El nombre de usuario es demasiado largo (máximo 50 caracteres).", None, None
    if len(correo) > 120 or not EMAIL_RE.fullmatch(correo):
        return "El correo ingresado no es válido.", None, None

    try:
        repetido = consultar_uno(
            "SELECT id FROM usuarios WHERE (correo = %s OR usuario = %s) AND id <> %s",
            (correo, usuario, usuario_id),
        )
        if repetido:
            return "Ya existe una cuenta con ese correo o usuario.", None, None
        ejecutar(
            "UPDATE usuarios SET usuario = %s, correo = %s WHERE id = %s",
            (usuario, correo, usuario_id),
        )
    except pymysql.err.IntegrityError:
        return "Ya existe una cuenta con ese correo o usuario.", None, None
    except Exception:
        logging.exception("Error al actualizar el perfil")
        return "No pudimos guardar los cambios. Intenta de nuevo.", None, None

    return "", usuario, correo


def cambiar_contrasena(usuario_id, form):
    """Cambia la contraseña. Devuelve el mensaje de error, o '' si todo salió bien."""
    actual = form.get("actual", "")
    nueva = form.get("nueva", "")
    confirmar = form.get("confirmar", "")

    try:
        fila = consultar_uno("SELECT contrasena_hash FROM usuarios WHERE id = %s", (usuario_id,))
        if not fila or not verificar_contrasena(actual, fila["contrasena_hash"]):
            return "La contraseña actual no es correcta."
        if len(nueva) < 6:
            return "La nueva contraseña debe tener al menos 6 caracteres."
        if nueva != confirmar:
            return "Las contraseñas nuevas no coinciden."
        ejecutar(
            "UPDATE usuarios SET contrasena_hash = %s WHERE id = %s",
            (hashear_contrasena(nueva), usuario_id),
        )
    except Exception:
        logging.exception("Error al cambiar la contraseña")
        return "No pudimos actualizar la contraseña. Intenta de nuevo."
    return ""