import hmac
import secrets

from flask import Flask, abort, redirect, render_template, request, session, url_for

from Vista.ajustes import actualizar_perfil, cambiar_contrasena, obtener_datos
from Vista.auth import contexto_sesion, intentar_login, requerir_login
from Vista.cuentas import crear_cuenta, solicitar_recuperacion
from Vista.contacto import AREAS, fecha_minima, procesar_contacto
from Vista.notificaciones import abrir_notificacion, listar_notificaciones, marcar_todas_leidas

app = Flask(
    __name__,
    template_folder="Plantilla/templates",  
    static_folder="Plantilla/static",       
)

app.secret_key = "dizar-asociados-cambiar-esta-clave-en-produccion"


#Token CSRF (lo usan todos los formularios que modifican datos)

def asegurar_csrf():
    if not session.get("csrf"):
        session["csrf"] = secrets.token_hex(32)
    return session["csrf"]


def csrf_valido():
    token = session.get("csrf", "")
    enviado = request.form.get("csrf", "")
    return bool(token) and hmac.compare_digest(token.encode(), enviado.encode())


@app.context_processor
def datos_de_sesion():
    # Hace que el menú (base.html) sepa quién está conectado, en todas las páginas
    return contexto_sesion()


@app.route("/")
def inicio():
    return render_template("index.html")


@app.route("/nosotros")
def nosotros():
    return render_template("nosotros.html")


@app.route("/servicios")
def servicios():
    return render_template("servicios.html")


# ---------- Contacto ----------

@app.route("/contacto")
def contacto():
    # Token CSRF: protege el formulario contra envíos desde otras páginas
    asegurar_csrf()

    # Mensaje y datos que deja contacto_enviar (se muestran una sola vez)
    flash = session.pop("flash_contacto", None)
    viejos = session.pop("viejos_contacto", {})

    return render_template(
        "contacto.html",
        csrf=session["csrf"],
        flash=flash,
        viejos=viejos,
        areas=AREAS,
        fecha_minima=fecha_minima(),
    )


@app.route("/contacto/enviar", methods=["POST"])
def contacto_enviar():
    if not csrf_valido():
        abort(400, "Solicitud no válida.")

    # Honeypot anti-spam: un humano nunca llena este campo (está oculto)
    if request.form.get("sitio_web"):
        return redirect(url_for("contacto"))

    tipo, mensaje, viejos = procesar_contacto(request.form)
    session["flash_contacto"] = {"tipo": tipo, "mensaje": mensaje}
    session["viejos_contacto"] = viejos
    return redirect(url_for("contacto"))


# ---------- Login, logout y tablero ----------

@app.route("/login", methods=["GET", "POST"])
def login():
    error = ""
    correo = request.form.get("correo", "")
    if request.method == "POST":
        usuario, error = intentar_login(correo, request.form.get("contrasena", ""))
        if usuario:
            session.clear()   # sesión nueva (equivale a session_regenerate_id)
            session["usuario_id"] = usuario["id"]
            session["usuario"] = usuario["usuario"]
            session["correo"] = usuario["correo"]
            session["rol"] = usuario["rol"]
            return redirect(url_for("dashboard"))
    return render_template("login.html", error=error, correo=correo)


@app.route("/registro", methods=["GET", "POST"])
def registro():
    error, creada = "", False
    if request.method == "POST":
        creada, error = crear_cuenta(request.form)
    return render_template("registro.html", error=error, exito=creada, datos=request.form)


@app.route("/recuperar", methods=["GET", "POST"])
def recuperar():
    exito, error = "", ""
    if request.method == "POST":
        exito, error = solicitar_recuperacion(request.form.get("correo", ""))
    return render_template("recuperar.html", exito=exito, error=error)


@app.route("/logout")
def logout():
    session.clear()
    return redirect(url_for("login"))


@app.route("/dashboard")
@requerir_login
def dashboard():
    return render_template("dashboard.html")


# ---------- Ajustes de la cuenta ----------

@app.route("/ajustes", methods=["GET", "POST"])
@requerir_login
def ajustes():
    csrf = asegurar_csrf()
    usuario_id = session["usuario_id"]
    error, exito, abrir_pass = "", "", False

    if request.method == "POST":
        if not csrf_valido():
            abort(400, "Solicitud no válida.")

        accion = request.form.get("accion", "")
        if accion == "perfil":
            error, nuevo_usuario, nuevo_correo = actualizar_perfil(usuario_id, request.form)
            if not error:
                session["usuario"] = nuevo_usuario
                session["correo"] = nuevo_correo
                exito = "Datos actualizados."
        elif accion == "contrasena":
            error = cambiar_contrasena(usuario_id, request.form)
            if not error:
                exito = "Contraseña actualizada."
            abrir_pass = bool(error)   # si hubo error, el panel de contraseña queda abierto

    return render_template(
        "ajustes.html",
        csrf=csrf, error=error, exito=exito, abrir_pass=abrir_pass,
        datos=obtener_datos(usuario_id),
    )


# ---------- Notificaciones ----------

@app.route("/notificaciones", methods=["GET", "POST"])
@requerir_login
def notificaciones():
    csrf = asegurar_csrf()
    mi_id = session["usuario_id"]

    # Marcar todas como leídas (POST)
    if request.method == "POST":
        if not csrf_valido():
            abort(400, "Solicitud no válida.")
        if request.form.get("accion") == "marcar_todas":
            marcar_todas_leidas(mi_id)
        return redirect(url_for("notificaciones"))

    # Abrir una notificación: se marca como leída y se lleva a la página que corresponde
    if "ir" in request.args:
        try:
            notificacion_id = int(request.args["ir"])
        except ValueError:
            notificacion_id = 0
        destino = abrir_notificacion(mi_id, notificacion_id)
        return redirect(destino or url_for("notificaciones"))

    lista = listar_notificaciones(mi_id)
    sin_leer = sum(1 for n in lista if not n["leida"])
    return render_template("notificaciones.html", notificaciones=lista, sin_leer=sin_leer, csrf=csrf)


if __name__ == "__main__":
    app.run(debug=True)