import re
from datetime import datetime, timedelta, timezone

from Modelo.conexion import consultar_uno, ejecutar

# Hora de Colombia (UTC-5, sin horario de verano)
BOGOTA = timezone(timedelta(hours=-5))

AREAS = ["Derecho Civil", "Derecho de Familia", "Derecho Laboral"]


def ahora():
    """Fecha y hora actual en Colombia."""
    return datetime.now(BOGOTA).replace(tzinfo=None)


def fecha_minima():
    """Primera fecha que se puede pedir: dentro de una hora (formato del campo datetime-local)."""
    return (ahora() + timedelta(hours=1)).strftime("%Y-%m-%dT%H:%M")


def procesar_contacto(form):

    nombre = form.get("nombre", "").strip()
    correo = form.get("correo", "").strip()
    telefono = form.get("telefono", "").strip()
    area = form.get("area", "").strip()
    mensaje = form.get("mensaje", "").strip()
    fecha_txt = form.get("fecha_preferida", "").strip()

    viejos = {
        "nombre": nombre, "correo": correo, "telefono": telefono,
        "area": area, "mensaje": mensaje, "fecha_preferida": fecha_txt,
    }
    viejos = {k: v[:2000] for k, v in viejos.items()}  # por si llega algo enorme

    # Validaciones
    if not (nombre and correo and telefono and area and mensaje and fecha_txt):
        return "error", "Por favor completa todos los campos.", viejos
    if len(nombre) > 120 or len(correo) > 120:
        return "error", "El nombre o el correo es demasiado largo.", viejos
    if not re.fullmatch(r"[^@\s]+@[^@\s]+\.[^@\s]+", correo):
        return "error", "El correo ingresado no es válido.", viejos
    if not re.fullmatch(r"\+?[0-9\s\-]{7,20}", telefono):
        return "error", "El teléfono solo puede tener números, espacios o guiones (7 a 20 caracteres).", viejos
    if len(mensaje) < 10:
        return "error", "Cuéntanos un poco más en tu mensaje (mínimo 10 caracteres).", viejos
    if len(mensaje) > 2000:
        return "error", "El mensaje es demasiado largo (máximo 2000 caracteres).", viejos

    # Fecha de la cita: formato válido, a futuro y dentro del horario de atención
    try:
        fecha = datetime.strptime(fecha_txt, "%Y-%m-%dT%H:%M")
    except ValueError:
        return "error", "La fecha de la cita no es válida.", viejos
    if fecha <= ahora():
        return "error", "Elige una fecha y hora que todavía no haya pasado.", viejos
    if fecha.isoweekday() > 5 or fecha.hour < 8 or fecha.hour >= 17:   # 1 = lunes ... 7 = domingo
        return "error", "Atendemos de lunes a viernes, de 8:00 a.m. a 5:00 p.m. Elige otro horario.", viejos
    fecha_preferida = fecha.strftime("%Y-%m-%d %H:%M:%S")

    # Buscar el servicio por nombre y guardar la consulta
    try:
        servicio = consultar_uno(
            "SELECT id FROM servicios WHERE nombre = %s AND activo = 1", (area,)
        )
        if not servicio:
            return "error", "Selecciona un área de interés válida.", viejos

        ejecutar(
            "INSERT INTO consultas (nombre, correo, telefono, servicio_id, mensaje, fecha_preferida) "
            "VALUES (%s, %s, %s, %s, %s, %s)",
            (nombre, correo, telefono, servicio["id"], mensaje, fecha_preferida),
        )
    except Exception:
        return "error", "No pudimos guardar tu mensaje. Intenta de nuevo en unos minutos.", viejos

    return "exito", "¡Gracias! Recibimos tu solicitud de cita y uno de nuestros abogados te contactará para confirmarla.", {}