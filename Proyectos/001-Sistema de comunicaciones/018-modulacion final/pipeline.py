import imaplib
import email
from email.utils import parseaddr

from config import imap_server, correo, password
from utils import decode_mime_header
from email_service import extract_message_content, extract_attachments_info
from db import build_tracking_id, ensure_tracking, dashboard_stats
from ai_service import (
    heuristic_general_classification,
    llm_general_classify,
    heuristic_hosteleria_classification,
    llm_hosteleria_classify,
    heuristic_priority,
    llm_priority,
    heuristic_extract_useful_data,
    llm_extract_useful_data,
    merge_extracted_data,
    summarize_one_paragraph,
    empty_extracted_data
)

# ==========================================================
# PIPELINE DE PROCESADO
# ==========================================================
def empty_processed_result():
    return {
        "automaticos": [],
        "importantes": [],
        "reservas": [],
        "horario_ubicacion": [],
        "carta_alergenos_servicios": [],
        "otro_cliente": [],
        "papelera": [],
        "dashboard": {
            "total": 0,
            "pendiente": 0,
            "respondido": 0,
            "archivado": 0,
            "papelera": 0
        }
    }


def clasificar_general(sender, subject, date, summary, body_text):
    label, reason = heuristic_general_classification(sender, subject, body_text)

    if label is None:
        cls = llm_general_classify(sender, subject, date, summary, body_text)
        return cls["label"], cls["reason"]

    return label, reason


def clasificar_hosteleria(sender, subject, date, summary, body_text):
    label, reason = heuristic_hosteleria_classification(subject, body_text)

    if label is None:
        cls = llm_hosteleria_classify(sender, subject, date, summary, body_text)
        return cls["label"], cls["reason"]

    return label, reason


def calcular_prioridad(host_label, sender, subject, date, summary, body_text):
    priority, reason = heuristic_priority(host_label, subject, body_text, summary)

    if host_label == "otro_cliente" or priority in (4, 5):
        try:
            pr = llm_priority(host_label, sender, subject, date, summary, body_text)
            return pr["priority"], pr["reason"]
        except Exception:
            pass

    return priority, reason


def extraer_datos(host_label, sender, subject, date, summary, body_text):
    heuristic_data = heuristic_extract_useful_data(host_label, sender, subject, body_text, summary)

    if host_label in ("reserva", "carta_alergenos_servicios", "otro_cliente"):
        try:
            llm_data = llm_extract_useful_data(
                host_label,
                sender,
                subject,
                date,
                summary,
                body_text,
                heuristic_data
            )

            return merge_extracted_data(host_label, heuristic_data, llm_data)

        except Exception:
            return heuristic_data

    return heuristic_data


def obtener_correos_procesados(n_ultimos: int):
    mail = imaplib.IMAP4_SSL(imap_server)
    mail.login(correo, password)
    mail.select("INBOX")

    status, mensajes = mail.uid("search", None, "ALL")

    if status != "OK":
        mail.close()
        mail.logout()
        return empty_processed_result()

    ids = mensajes[0].split()
    ultimos_ids = ids[-n_ultimos:]

    categoria_automatico = []
    categoria_reserva = []
    categoria_horario_ubicacion = []
    categoria_carta = []
    categoria_otro = []
    categoria_papelera = []
    visibles = []

    for uid in reversed(ultimos_ids):
        uid_str = uid.decode() if isinstance(uid, bytes) else str(uid)

        status, data = mail.uid("fetch", uid, "(RFC822)")

        if status != "OK" or not data or not data[0]:
            continue

        msg = email.message_from_bytes(data[0][1])

        subject = decode_mime_header(msg.get("Subject", ""))
        sender = decode_mime_header(msg.get("From", ""))
        date = decode_mime_header(msg.get("Date", ""))

        content = extract_message_content(msg)
        body_text = content["body"] or "(Sin cuerpo de texto legible o solo adjuntos)"
        body_source = content["body_source"]
        attachments = extract_attachments_info(msg)

        tracking_id = build_tracking_id(msg, uid_str, body_text)
        tracking = ensure_tracking(tracking_id, uid_actual=uid_str)

        try:
            summary = summarize_one_paragraph(subject, sender, date, body_text)
        except Exception as e:
            summary = f"(Error al resumir con Ollama: {e})"

        general_label, general_reason = clasificar_general(sender, subject, date, summary, body_text)
        _, sender_email = parseaddr(sender)

        item = {
            "uid": uid_str,
            "tracking_id": tracking.get("tracking_id", tracking_id),
            "from": sender,
            "from_email": sender_email,
            "subject": subject,
            "date": date,
            "summary": summary,
            "body": body_text,
            "body_source": body_source,
            "general_reason": general_reason,
            "priority": None,
            "priority_reason": None,
            "host_reason": None,
            "category": None,
            "extracted": empty_extracted_data(),
            "missing": [],
            "extraction_notes": "",
            "attachments": attachments,
            "tracking": tracking
        }

        if general_label == "automatico_o_publicidad":
            item["category"] = "automatico_o_publicidad"

            if tracking["estado"] == "papelera":
                categoria_papelera.append(item)
            else:
                categoria_automatico.append(item)
                visibles.append(item)

            continue

        host_label, host_reason = clasificar_hosteleria(sender, subject, date, summary, body_text)
        priority, priority_reason = calcular_prioridad(host_label, sender, subject, date, summary, body_text)
        extraction = extraer_datos(host_label, sender, subject, date, summary, body_text)

        item["host_reason"] = host_reason
        item["category"] = host_label
        item["priority"] = priority
        item["priority_reason"] = priority_reason
        item["extracted"] = extraction["detected"]
        item["missing"] = extraction["missing"]
        item["extraction_notes"] = extraction["notes"]

        if tracking["estado"] == "papelera":
            categoria_papelera.append(item)
            continue

        visibles.append(item)

        if host_label == "reserva":
            categoria_reserva.append(item)

        elif host_label == "horario_ubicacion":
            categoria_horario_ubicacion.append(item)

        elif host_label == "carta_alergenos_servicios":
            categoria_carta.append(item)

        else:
            categoria_otro.append(item)

    mail.close()
    mail.logout()

    for lista in (
        categoria_reserva,
        categoria_horario_ubicacion,
        categoria_carta,
        categoria_otro
    ):
        lista.sort(key=lambda x: x["priority"], reverse=True)

    categoria_papelera.sort(
        key=lambda x: x.get("tracking", {}).get("enviado_a_papelera_en") or "",
        reverse=True
    )

    importantes = categoria_reserva + categoria_horario_ubicacion + categoria_carta + categoria_otro
    importantes.sort(key=lambda x: x["priority"], reverse=True)

    return {
        "automaticos": categoria_automatico,
        "importantes": importantes,
        "reservas": categoria_reserva,
        "horario_ubicacion": categoria_horario_ubicacion,
        "carta_alergenos_servicios": categoria_carta,
        "otro_cliente": categoria_otro,
        "papelera": categoria_papelera,
        "dashboard": dashboard_stats(visibles + categoria_papelera)
    }
