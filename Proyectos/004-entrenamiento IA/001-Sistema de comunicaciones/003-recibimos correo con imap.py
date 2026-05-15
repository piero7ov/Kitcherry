import os
import imaplib
import email
from email.header import decode_header

# ==============================
# VARIABLES DE ENTORNO
# ==============================

correo = os.environ["MI_CORREO_KITCHERRY"]
password = os.environ["MI_CONTRASENA_CORREO_KITCHERRY"]
imap_server = os.environ["MI_SERVIDORIMAP_CORREO_KITCHERRY"]

# ==============================
# CONEXIÓN IMAP
# ==============================

mail = imaplib.IMAP4_SSL(imap_server)

mail.login(correo, password)

# seleccionar bandeja de entrada
mail.select("INBOX")

# ==============================
# OBTENER IDS DE MENSAJES
# ==============================

status, mensajes = mail.search(None, "ALL")

ids = mensajes[0].split()

# últimos 5 correos
ultimos_ids = ids[-5:]

print("\nÚltimos 5 correos:\n")

# ==============================
# LEER MENSAJES
# ==============================

for i in reversed(ultimos_ids):

    status, data = mail.fetch(i, "(RFC822)")

    raw_email = data[0][1]

    mensaje = email.message_from_bytes(raw_email)

    # ---- Asunto ----
    asunto, encoding = decode_header(
        mensaje["Subject"]
    )[0]

    if isinstance(asunto, bytes):
        asunto = asunto.decode(
            encoding or "utf-8",
            errors="ignore"
        )

    # ---- Remitente ----
    remitente = mensaje.get("From")

    # ---- Fecha ----
    fecha = mensaje.get("Date")

    print("================================")
    print("De:", remitente)
    print("Asunto:", asunto)
    print("Fecha:", fecha)

# ==============================
# CERRAR
# ==============================

mail.close()
mail.logout()
