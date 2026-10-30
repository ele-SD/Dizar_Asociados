import logging
import re

import bcrypt
import pymysql

from Modelo.conexion import consultar_uno, ejecutar

EMAIL_RE = re.compile(r"[^@\s]+@[^@\s]+\.[^@\s]+")


def hashear_contrasena(contrasena):
    """Equivale a password_hash() de PHP. El resultado ($2b$...) también lo lee password_verify()."""
    return bcrypt.hashpw(contrasena.encode("utf-8")[:72], bcrypt.gensalt(rounds=10)).decode("utf-8")


def crear_cuenta(form):
    """
    Valida el formulario de registro y crea el usuario (rol 'cliente' por defecto).
    Devuelve (creada, error).
    """
    usuario = form.get("usuario", "").strip()
    correo = form.get("correo", "").strip()
    contrasena = form.get("contrasena", "")
    confirmar = form.get("confirmar_contrasena", "")
    tipo_documento = form.get("tipo_documento", "")
    numero_documento = form.get("numero_documento", "").strip()

    if not (usuario and correo and contrasena and confirmar and tipo_documento and numero_documento):
        return False, "Por favor completa todos los campos."
    if len(usuario) > 50:
        return False, "El nombre de usuario es demasiado largo (máximo 50 caracteres)."
    if len(correo) > 120 or not EMAIL_RE.fullmatch(correo):
        return False, "El correo ingresado no es válido."
    if tipo_documento not in ("CC", "CE"):
        return False, "Selecciona un tipo de documento válido."
    if not re.fullmatch(r"[0-9]{5,15}", numero_documento):
        return False, "El número de documento solo debe contener dígitos (5 a 15)."
    if len(contrasena) < 6:
        return False, "La contraseña debe tener al menos 6 caracteres."
    if contrasena != confirmar:
        return False, "Las contraseñas no coinciden."

    try:
        existente = consultar_uno(
            "SELECT id FROM usuarios WHERE correo = %s OR numero_documento = %s OR usuario = %s",
            (correo, numero_documento, usuario),
        )
        if existente:
            return False, "Ya existe una cuenta con ese correo, usuario o número de documento."

        ejecutar(
            "INSERT INTO usuarios (usuario, correo, contrasena_hash, tipo_documento, numero_documento) "
            "VALUES (%s, %s, %s, %s, %s)",
            (usuario, correo, hashear_contrasena(contrasena), tipo_documento, numero_documento),
        )
    except pymysql.err.IntegrityError:
        # Dos personas registrándose a la vez con los mismos datos: la BD lo impide (UNIQUE)
        return False, "Ya existe una cuenta con ese correo, usuario o número de documento."
    except Exception:
        logging.exception("Error al crear la cuenta")
        return False, "Ocurrió un error al crear la cuenta. Intenta de nuevo."

    return True, ""


def solicitar_recuperacion(correo):

    correo = correo.strip()
    if not correo:
        return "", "Por favor ingresa tu correo electrónico."
    if not EMAIL_RE.fullmatch(correo):
        return "", "El correo ingresado no es válido."
    return "Si el correo está registrado, recibirás instrucciones para restablecer tu contraseña.", ""