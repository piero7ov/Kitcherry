import re
import email
import imaplib
import smtplib
from email.message import EmailMessage

from config import (
    imap_server,
    correo,
    password,
    smtp_server,
    smtp_port
)
from utils import decode_mime_header, normalize_reply_subject

# ==========================================================
# EMAIL / IMAP
# ==========================================================
def clean_html_to_text(content: str) -> str:
    content = (
        content.replace("<br>", "\n")
               .replace("<br/>", "\n")
               .replace("<br />", "\n")
               .replace("</p>", "\n")
               .replace("</div>", "\n")
               .replace("</li>", "\n")
    )

    content = re.sub(r"<script.*?>.*?</script>", "", content, flags=re.S | re.I)
    content = re.sub(r"<style.*?>.*?</style>", "", content, flags=re.S | re.I)
    content = re.sub(r"<[^>]+>", " ", content)
    content = re.sub(r"\n\s*\n+", "\n\n", content)
    content = re.sub(r"[ \t]+", " ", content)

    return content.strip()


def extract_message_content(msg: email.message.Message) -> dict:
    plain_texts = []
    html_texts = []

    def decode_part(part):
        payload = part.get_payload(decode=True)

        if payload is None:
            return ""

        charset = part.get_content_charset() or "utf-8"

        try:
            return payload.decode(charset, errors="ignore").strip()
        except Exception:
            return payload.decode("utf-8", errors="ignore").strip()

    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            disp = str(part.get("Content-Disposition") or "")

            if "attachment" in disp.lower():
                continue

            if ctype not in ("text/plain", "text/html"):
                continue

            content = decode_part(part)

            if not content:
                continue

            if ctype == "text/plain":
                plain_texts.append(content)
            else:
                html_texts.append(clean_html_to_text(content))

    else:
        ctype = msg.get_content_type()

        if ctype in ("text/plain", "text/html"):
            content = decode_part(msg)

            if content:
                if ctype == "text/plain":
                    plain_texts.append(content)
                else:
                    html_texts.append(clean_html_to_text(content))

    if plain_texts:
        return {
            "body": "\n\n".join(t for t in plain_texts if t.strip()).strip(),
            "body_source": "text/plain"
        }

    if html_texts:
        return {
            "body": "\n\n".join(t for t in html_texts if t.strip()).strip(),
            "body_source": "text/html"
        }

    return {
        "body": "",
        "body_source": "desconocido"
    }


def decode_filename(filename: str) -> str:
    return decode_mime_header(filename) if filename else "adjunto"


def extract_attachments_info(msg: email.message.Message) -> list:
    attachments = []
    index = 0

    for part in msg.walk():
        filename = part.get_filename()
        disp = str(part.get("Content-Disposition") or "")

        if filename or "attachment" in disp.lower():
            payload = part.get_payload(decode=True)

            attachments.append({
                "index": index,
                "filename": decode_filename(filename),
                "content_type": part.get_content_type(),
                "size": len(payload) if payload else 0
            })

            index += 1

    return attachments


def fetch_attachment_by_uid(uid: str, attachment_index: int):
    mail = imaplib.IMAP4_SSL(imap_server)
    mail.login(correo, password)
    mail.select("INBOX")

    status, data = mail.uid("fetch", uid, "(RFC822)")

    if status != "OK" or not data or not data[0]:
        mail.close()
        mail.logout()
        raise FileNotFoundError("No se pudo recuperar el correo.")

    msg = email.message_from_bytes(data[0][1])
    target_part = None
    found_index = 0

    for part in msg.walk():
        filename = part.get_filename()
        disp = str(part.get("Content-Disposition") or "")

        if filename or "attachment" in disp.lower():
            if found_index == attachment_index:
                target_part = part
                break

            found_index += 1

    mail.close()
    mail.logout()

    if target_part is None:
        raise FileNotFoundError("No se encontró el adjunto solicitado.")

    payload = target_part.get_payload(decode=True)

    if payload is None:
        raise FileNotFoundError("El adjunto no contiene datos descargables.")

    return {
        "filename": decode_filename(target_part.get_filename()),
        "content_type": target_part.get_content_type() or "application/octet-stream",
        "data": payload
    }


def enviar_respuesta_email(destinatario: str, asunto_original: str, cuerpo_respuesta: str):
    destinatario = (destinatario or "").strip()
    cuerpo_respuesta = (cuerpo_respuesta or "").strip()

    if not destinatario:
        raise ValueError("No se ha encontrado un destinatario válido.")

    if not cuerpo_respuesta:
        raise ValueError("La respuesta está vacía.")

    msg = EmailMessage()
    msg["From"] = correo
    msg["To"] = destinatario
    msg["Subject"] = normalize_reply_subject(asunto_original)
    msg.set_content(cuerpo_respuesta)

    with smtplib.SMTP(smtp_server, smtp_port, timeout=60) as server:
        server.starttls()
        server.login(correo, password)
        server.send_message(msg)
