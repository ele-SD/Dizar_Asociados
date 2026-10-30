import logging
import re
from functools import wraps

import bcrypt
from flask import abort, redirect, session, url_for

from Vista.notificaciones import contar_notificaciones
from Modelo.conexion import consultar_uno, ejecutar

# Configuración del bloqueo por intentos fallidos
MAX_INTENTOS = 5
MINUTOS_BLOQUEO = 15

EMAIL_RE = re.compile(r"[^@\s]+@[^@\s]+\.[^@\s]+")
ETIQUETAS_ROL = {"admin": "Administrador", "abogado": "Abogado", "cliente": "Cliente"}


# ---------- Contraseñas ----------

def verificar_contrasena(contrasena, hash_guardado):
    """Equivale a password_verify() de PHP (los hashes $2y$ de PHP funcionan igual)."""
    try:
        # bcrypt solo mira los primeros 72 bytes (PHP también los corta ahí)
        return bcrypt.checkpw(contrasena.encode("utf-8")[:72], hash_guardado.encode("utf-8"))
    except ValueError:
        return False


# ---------- Login con bloqueo temporal ----------

def intentar_login(correo, contrasena):
    """
    Revisa las credenciales.
    Devuelve (usuario, error): si todo sale bien, usuario es un diccionario y error está vacío.
    """
    correo = correo.strip()
    if not correo or not contrasena:
        return None, "Por favor completa todos los campos."
    if not EMAIL_RE.fullmatch(correo):
        return None, "El correo ingresado no es válido."

    try:
        usuario = consultar_uno(
            """SELECT id, usuario, correo, rol, contrasena_hash, activo, intentos_fallidos,
                      (bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()) AS bloqueado,
                      CEIL(TIMESTAMPDIFF(SECOND, NOW(), bloqueado_hasta) / 60) AS minutos_restantes
               FROM usuarios WHERE correo = %s""",
            (correo,),
        )
        if not usuario:
            return None, "Correo o contraseña incorrectos."

        if not usuario["activo"]:
            return None, "Esta cuenta está desactivada. Contacta al despacho."

        if usuario["bloqueado"]:
            # Mientras esté bloqueada ni siquiera se revisa la contraseña
            minutos = max(1, int(usuario["minutos_restantes"] or 1))
            plural = "" if minutos == 1 else "s"
            return None, f"Demasiados intentos fallidos. Intenta de nuevo en {minutos} minuto{plural}."

        if verificar_contrasena(contrasena, usuario["contrasena_hash"]):
            # Acierto: se reinicia el contador
            ejecutar(
                "UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = %s",
                (usuario["id"],),
            )
            return {k: usuario[k] for k in ("id", "usuario", "correo", "rol")}, ""

        # Fallo: se suma un intento y, al llegar al máximo, se bloquea
        intentos = int(usuario["intentos_fallidos"]) + 1
        if intentos >= MAX_INTENTOS:
            ejecutar(
                "UPDATE usuarios SET intentos_fallidos = 0, "
                "bloqueado_hasta = DATE_ADD(NOW(), INTERVAL %s MINUTE) WHERE id = %s",
                (MINUTOS_BLOQUEO, usuario["id"]),
            )
            return None, f"Demasiados intentos fallidos. Tu cuenta quedó bloqueada por {MINUTOS_BLOQUEO} minutos."

        ejecutar("UPDATE usuarios SET intentos_fallidos = %s WHERE id = %s", (intentos, usuario["id"]))
        return None, "Correo o contraseña incorrectos."

    except Exception:
        logging.exception("Error al iniciar sesión")
        return None, "No pudimos conectar con el sistema. Intenta de nuevo en unos minutos."


# ---------- Protección de páginas (equivale a requerir_login / requerir_rol de auth.php) ----------

def requerir_login(vista):
    """Exige haber iniciado sesión."""
    @wraps(vista)
    def envoltura(*args, **kwargs):
        if "usuario_id" not in session:
            return redirect(url_for("login"))
        return vista(*args, **kwargs)
    return envoltura


def requerir_rol(*roles):
    """Exige haber iniciado sesión y tener uno de los roles indicados."""
    def decorador(vista):
        @wraps(vista)
        @requerir_login
        def envoltura(*args, **kwargs):
            if session.get("rol", "") not in roles:
                abort(403, "No tienes permiso para ver esta página.")
            return vista(*args, **kwargs)
        return envoltura
    return decorador


# ---------- Datos de la sesión disponibles en TODAS las plantillas (para el menú) ----------

def contexto_sesion():
    activa = "usuario_id" in session
    rol = session.get("rol", "")
    return {
        "sesion_activa": activa,
        "rol_actual": rol,
        "es_personal": rol in ("admin", "abogado"),
        "nombre_usuario": session.get("usuario", ""),
        "rol_texto": ETIQUETAS_ROL.get(rol, ""),
        "no_leidas": contar_notificaciones(session["usuario_id"]) if activa else 0,
    }