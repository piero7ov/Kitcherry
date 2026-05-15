import os
import imaplib
import email
import json
import urllib.request
from email.header import decode_header

# ==========================================================
# CONFIGURACIÓN KITCHERRY
# ==========================================================
# Variables de entorno IMAP
correo = os.environ["MI_CORREO_KITCHERRY"]
password = os.environ["MI_CONTRASENA_CORREO_KITCHERRY"]
imap_server = os.environ["MI_SERVIDORIMAP_CORREO_KITCHERRY"]

# Ollama local
OLLAMA_URL = os.environ.get("OLLAMA_URL", "http://localhost:11434/api/generate")
OLLAMA_MODEL = os.environ.get("OLLAMA_MODEL", "llama3:latest")

# Número de correos a resumir
N_ULTIMOS = int(os.environ.get("N_ULTIMOS", "5"))

# Límite de caracteres del cuerpo enviados al modelo
MAX_BODY_CHARS = int(os.environ.get("MAX_BODY_CHARS", "8000"))


# ==========================================================
# FUNCIONES AUXILIARES
# ==========================================================
def decode_mime_header(value: str) -> str:
    """
    Decodifica cabeceras MIME como asunto, remitente o fecha.
    """
    if not value:
        return ""

    parts = decode_header(value)
    resultado = []

    for part, enc in parts:
        if isinstance(part, bytes):
            resultado.append(part.decode(enc or "utf-8", errors="ignore"))
        else:
            resultado.append(part)

    return "".join(resultado)


def extract_text_from_message(msg: email.message.Message) -> str:
    """
    Devuelve texto plano del email.
    - Si encuentra text/plain, lo usa.
    - Si no, intenta extraer texto desde text/html.
    """
    textos = []

    if msg.is_multipart():
        for part in msg.walk():
            content_type = part.get_content_type()
            disposition = str(part.get("Content-Disposition") or "")

            # Ignorar adjuntos
            if "attachment" in disposition.lower():
                continue

            if content_type in ("text/plain", "text/html"):
                payload = part.get_payload(decode=True)

                if payload is None:
                    continue

                charset = part.get_content_charset() or "utf-8"

                try:
                    contenido = payload.decode(charset, errors="ignore")
                except Exception:
                    contenido = payload.decode("utf-8", errors="ignore")

                if content_type == "text/html":
                    contenido = (
                        contenido.replace("<br>", "\n")
                                 .replace("<br/>", "\n")
                                 .replace("<br />", "\n")
                                 .replace("</p>", "\n")
                    )

                    import re
                    contenido = re.sub(r"<script.*?>.*?</script>", "", contenido, flags=re.S | re.I)
                    contenido = re.sub(r"<style.*?>.*?</style>", "", contenido, flags=re.S | re.I)
                    contenido = re.sub(r"<[^>]+>", " ", contenido)
                    contenido = re.sub(r"\s+", " ", contenido).strip()

                textos.append(contenido)

    else:
        content_type = msg.get_content_type()
        payload = msg.get_payload(decode=True)

        if payload is not None and content_type in ("text/plain", "text/html"):
            charset = msg.get_content_charset() or "utf-8"

            try:
                contenido = payload.decode(charset, errors="ignore")
            except Exception:
                contenido = payload.decode("utf-8", errors="ignore")

            if content_type == "text/html":
                import re
                contenido = re.sub(r"<script.*?>.*?</script>", "", contenido, flags=re.S | re.I)
                contenido = re.sub(r"<style.*?>.*?</style>", "", contenido, flags=re.S | re.I)
                contenido = re.sub(r"<[^>]+>", " ", contenido)
                contenido = re.sub(r"\s+", " ", contenido).strip()

            textos.append(contenido)

    texto_final = "\n\n".join(t for t in textos if t and t.strip())
    return texto_final.strip()


def ollama_summarize_one_paragraph(subject: str, sender: str, date: str, body: str) -> str:
    """
    Llama a Ollama local para resumir el correo en español y en un solo párrafo.
    """
    body = (body or "").strip()

    if len(body) > MAX_BODY_CHARS:
        body = body[:MAX_BODY_CHARS] + "…"

    prompt = f"""
Eres un asistente que resume correos electrónicos de un negocio de hostelería.
Devuelve SOLO un párrafo en español, sin viñetas, sin títulos, sin saltos de línea y sin código.

Contexto del correo:
- De: {sender}
- Fecha: {date}
- Asunto: {subject}

Contenido:
\"\"\"{body}\"\"\"

Instrucciones:
- Resume el propósito principal del correo y los puntos clave.
- Si hay una petición o acción requerida, indícalo claramente en el mismo párrafo.
- Si faltan datos, no inventes información.
- Redacta de forma clara y útil para la gestión de un establecimiento hostelero.
""".strip()

    payload = {
        "model": OLLAMA_MODEL,
        "prompt": prompt,
        "stream": False
        # "options": {"temperature": 0.2}
    }

    req = urllib.request.Request(
        OLLAMA_URL,
        data=json.dumps(payload).encode("utf-8"),
        headers={"Content-Type": "application/json"},
        method="POST",
    )

    with urllib.request.urlopen(req, timeout=120) as resp:
        data = json.loads(resp.read().decode("utf-8", errors="ignore"))

    resumen = (data.get("response") or "").strip()

    # Forzar un solo párrafo
    resumen = " ".join(resumen.split())
    return resumen


# ==========================================================
# CONEXIÓN IMAP Y LECTURA DE CORREOS
# ==========================================================
mail = imaplib.IMAP4_SSL(imap_server)
mail.login(correo, password)
mail.select("INBOX")

status, mensajes = mail.search(None, "ALL")
ids = mensajes[0].split()

ultimos_ids = ids[-N_ULTIMOS:]

print(f"\nÚltimos {N_ULTIMOS} correos resumidos con Ollama ({OLLAMA_MODEL}):\n")

for i in reversed(ultimos_ids):
    status, data = mail.fetch(i, "(RFC822)")
    raw_email = data[0][1]
    msg = email.message_from_bytes(raw_email)

    subject = decode_mime_header(msg.get("Subject", ""))
    sender = decode_mime_header(msg.get("From", ""))
    date = decode_mime_header(msg.get("Date", ""))

    body_text = extract_text_from_message(msg)

    if not body_text:
        body_text = "(Sin cuerpo de texto legible o solo adjuntos)"

    try:
        resumen = ollama_summarize_one_paragraph(subject, sender, date, body_text)
    except Exception as e:
        resumen = f"(Error al resumir con Ollama: {e})"

    print("================================")
    print("De:", sender)
    print("Asunto:", subject)
    print("Fecha:", date)
    print("Resumen:", resumen)
    print()


# ==========================================================
# CIERRE
# ==========================================================
mail.close()
mail.logout()