from Modelo.conexion import consultar_uno


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