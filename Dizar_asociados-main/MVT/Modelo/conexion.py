import pymysql

# Mismos datos que tenías en conexion.php (XAMPP: usuario root, sin contraseña)
CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "despacho_abogados",
    "charset": "utf8mb4",
    "cursorclass": pymysql.cursors.DictCursor,  # las filas llegan como diccionarios: fila["nombre"]
}


def conectar():
    """Abre una conexión nueva a MariaDB."""
    return pymysql.connect(**CONFIG)


def consultar_todos(sql, params=()):
    """SELECT que devuelve varias filas (lista de diccionarios)."""
    conexion = conectar()
    try:
        with conexion.cursor() as cursor:
            cursor.execute(sql, params)
            return cursor.fetchall()
    finally:
        conexion.close()


def consultar_uno(sql, params=()):
    """SELECT que devuelve una sola fila (diccionario) o None si no hay."""
    conexion = conectar()
    try:
        with conexion.cursor() as cursor:
            cursor.execute(sql, params)
            return cursor.fetchone()
    finally:
        conexion.close()


def ejecutar(sql, params=()):
    """INSERT / UPDATE / DELETE. Devuelve el id de la fila creada (si fue INSERT)."""
    conexion = conectar()
    try:
        with conexion.cursor() as cursor:
            cursor.execute(sql, params)
            nuevo_id = cursor.lastrowid
        conexion.commit()
        return nuevo_id
    except Exception:
        conexion.rollback()
        raise
    finally:
        conexion.close()


# Prueba rápida: python Modelo/conexion.py
if __name__ == "__main__":
    try:
        servicios = consultar_todos("SELECT id, nombre FROM servicios")
        print("Conexión OK. Servicios en la base de datos:")
        for s in servicios:
            print(f"  {s['id']}. {s['nombre']}")
    except Exception as error:
        print("No se pudo conectar:", error)