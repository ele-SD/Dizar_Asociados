import hmac
import secrets

from flask import Flask, abort, redirect, render_template, request, session, url_for

from Vista.auth import contexto_sesion, intentar_login, requerir_login
from Vista.contacto import AREAS, fecha_minima, procesar_contacto

app = Flask(
    __name__,
    template_folder="Plantilla/templates",  # los .html
    static_folder="Plantilla/static",       # css, js e imágenes
)

# Clave para firmar la sesión (equivale a session_start() de PHP).
# Más adelante la pasaremos a una variable de entorno.
app.secret_key = "dizar-asociados-cambiar-esta-clave-en-produccion"


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
    if not session.get("csrf"):
        session["csrf"] = secrets.token_hex(32)

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
    token = session.get("csrf", "")
    if not token or not hmac.compare_digest(token.encode(), request.form.get("csrf", "").encode()):
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


@app.route("/logout")
def logout():
    session.clear()
    return redirect(url_for("login"))


@app.route("/dashboard")
@requerir_login
def dashboard():
    return render_template("dashboard.html")


if __name__ == "__main__":
    app.run(debug=True)