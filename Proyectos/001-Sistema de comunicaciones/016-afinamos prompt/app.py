from flask import Flask, render_template, request, jsonify, send_file
import os
import re
import io
import imaplib
import email
import json
import urllib.request
import smtplib
from datetime import datetime
from email.header import decode_header
from email.utils import parseaddr
from email.message import EmailMessage

app = Flask(__name__)

# ==========================================================
# CONFIGURACIÓN
# ==========================================================
correo = os.environ["MI_CORREO_KITCHERRY"]
password = os.environ["MI_CONTRASENA_CORREO_KITCHERRY"]
imap_server = os.environ["MI_SERVIDORIMAP_CORREO_KITCHERRY"]

smtp_server = os.environ["MI_SERVIDORSMTP_CORREO_KITCHERRY"]
smtp_port = int(os.environ["MI_PUERTOSMTP_CORREO_KITCHERRY"])

OLLAMA_URL = os.environ.get("OLLAMA_URL", "http://localhost:11434/api/generate")
OLLAMA_MODEL = os.environ.get("OLLAMA_MODEL", "llama3:latest")
OLLAMA_OPTIONS = {"temperature": 0.1}

N_ULTIMOS_POR_DEFECTO = 5
MAX_BODY_CHARS = int(os.environ.get("MAX_BODY_CHARS", "8000"))

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATA_DIR = os.path.join(BASE_DIR, "data")
TRAZABILIDAD_PATH = os.path.join(DATA_DIR, "trazabilidad.json")

os.makedirs(DATA_DIR, exist_ok=True)
if not os.path.exists(TRAZABILIDAD_PATH):
    with open(TRAZABILIDAD_PATH, "w", encoding="utf-8") as f:
        json.dump({}, f, ensure_ascii=False, indent=2)

# ==========================================================
# CONSTANTES
# ==========================================================
AUTO_EMAIL_PATTERNS = [
    r"no-?reply", r"noreply", r"do-?not-?reply",
    r"mailer-daemon", r"postmaster",
    r"newsletter", r"notifications?", r"alertas?",
    r"billing", r"invoice", r"receipt",
    r"promotions?", r"marketing", r"mailing",
    r"comunicaciones", r"bolet[ií]n"
]

AUTO_SUBJECT_PATTERNS = [
    r"unsubscribe|darse de baja|baja",
    r"oferta|promoci[oó]n|descuento|rebaja|saldo|tarifa",
    r"newsletter|bolet[ií]n|resumen semanal",
    r"alerta|notificaci[oó]n|aviso autom[aá]tico",
]

PERSONAL_SUBJECT_HINTS = [
    r"^re:", r"^fw:", r"^fwd:",
    r"urgente|importante|por favor|consulta|pregunta|reserva|al[eé]rgeno|horario|mesa",
]

PERSONAL_BODY_HINTS = [
    r"un saludo", r"gracias", r"por favor", r"cuando puedas",
    r"necesito", r"podemos", r"quer[ií]a", r"quisiera",
    r"reservar", r"mesa", r"personas", r"horario",
    r"carta", r"men[uú]", r"al[eé]rgeno", r"gluten", r"lactosa",
    r"direcci[oó]n", r"ubicaci[oó]n", r"terraza", r"evento",
    r"confirmarme", r"confirmar", r"disponibilidad"
]

HOSTELERIA_USEFUL_HINTS = [
    r"reserva", r"reservar", r"mesa", r"personas",
    r"horario", r"carta", r"men[uú]",
    r"al[eé]rgeno", r"al[eé]rgenos", r"gluten", r"lactosa",
    r"direcci[oó]n", r"ubicaci[oó]n",
    r"terraza", r"evento", r"celebraci[oó]n",
    r"disponibilidad", r"cancelar reserva", r"modificar reserva",
    r"grupo", r"cumplea[nñ]os", r"vegetariano", r"vegano", r"wok"
]

ALERGENOS_KNOWN = [
    "gluten", "lactosa", "frutos secos", "cacahuete", "cacahuetes",
    "marisco", "huevo", "huevos", "soja", "mostaza", "apio",
    "sésamo", "sesamo", "pescado", "moluscos", "crustáceos", "crustaceos"
]

RESTRICCIONES_KNOWN = [
    "vegano", "vegana", "vegetariano", "vegetariana", "celiaco",
    "celíaco", "intolerancia", "sin gluten", "sin lactosa"
]

SERVICIOS_KNOWN = [
    "terraza", "delivery", "take away", "para llevar", "evento",
    "eventos", "cumpleaños", "celebración", "grupo", "grupos",
    "menú concertado", "menús concertados", "menú cerrado", "menús cerrados",
    "fianza"
]

# ==========================================================
# HELPERS GENERALES
# ==========================================================
def now_str() -> str:
    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def unique_list(values):
    out = []
    seen = set()
    for v in values:
        if v is None:
            continue
        txt = str(v).strip()
        if not txt:
            continue
        low = txt.lower()
        if low not in seen:
            seen.add(low)
            out.append(txt)
    return out


def normalize_single_paragraph(text: str) -> str:
    return " ".join((text or "").split()).strip()


def parse_json_with_fallback(raw: str, default: dict) -> dict:
    try:
        return json.loads(raw)
    except Exception:
        m = re.search(r"\{.*\}", raw, flags=re.S)
        if m:
            try:
                return json.loads(m.group(0))
            except Exception:
                return default
        return default


def decode_mime_header(value: str) -> str:
    if not value:
        return ""
    parts = decode_header(value)
    out = []
    for part, enc in parts:
        if isinstance(part, bytes):
            out.append(part.decode(enc or "utf-8", errors="ignore"))
        else:
            out.append(part)
    return "".join(out)


def normalize_reply_subject(subject: str) -> str:
    subject = (subject or "").strip()
    if not subject:
        return "Re: Consulta"
    if subject.lower().startswith("re:"):
        return subject
    return f"Re: {subject}"

# ==========================================================
# TRAZABILIDAD
# ==========================================================
def load_trazabilidad() -> dict:
    try:
        with open(TRAZABILIDAD_PATH, "r", encoding="utf-8") as f:
            return json.load(f)
    except Exception:
        return {}


def save_trazabilidad(data: dict) -> None:
    with open(TRAZABILIDAD_PATH, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)


def ensure_tracking(uid: str) -> dict:
    data = load_trazabilidad()
    if uid not in data:
        created = now_str()
        data[uid] = {
            "estado": "pendiente",
            "creado_en": created,
            "borrador_generado_en": None,
            "respondido_en": None,
            "archivado_en": None,
            "enviado_a_papelera_en": None,
            "respuesta_enviada": "",
            "historial": [
                {"accion": "correo_detectado", "fecha": created}
            ]
        }
        save_trazabilidad(data)
    return data[uid]


def update_tracking(uid: str, updates: dict, action_name: str | None = None, action_extra: dict | None = None) -> dict:
    data = load_trazabilidad()
    if uid not in data:
        ensure_tracking(uid)
        data = load_trazabilidad()

    data[uid].update(updates)

    if action_name:
        event = {"accion": action_name, "fecha": now_str()}
        if action_extra:
            event.update(action_extra)
        data[uid]["historial"].append(event)

    save_trazabilidad(data)
    return data[uid]


def dashboard_stats(items: list) -> dict:
    stats = {
        "total": 0,
        "pendiente": 0,
        "respondido": 0,
        "archivado": 0,
        "papelera": 0
    }
    for item in items:
        stats["total"] += 1
        estado = item.get("tracking", {}).get("estado", "pendiente")
        if estado in stats:
            stats[estado] += 1
    return stats

# ==========================================================
# OLLAMA
# ==========================================================
def ollama_call(prompt: str) -> str:
    payload = {
        "model": OLLAMA_MODEL,
        "prompt": prompt,
        "stream": False,
        "options": OLLAMA_OPTIONS
    }

    req = urllib.request.Request(
        OLLAMA_URL,
        data=json.dumps(payload).encode("utf-8"),
        headers={"Content-Type": "application/json"},
        method="POST",
    )

    with urllib.request.urlopen(req, timeout=180) as resp:
        data = json.loads(resp.read().decode("utf-8", errors="ignore"))

    return (data.get("response") or "").strip()

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
        return {"body": "\n\n".join(t for t in plain_texts if t.strip()).strip(), "body_source": "text/plain"}
    if html_texts:
        return {"body": "\n\n".join(t for t in html_texts if t.strip()).strip(), "body_source": "text/html"}

    return {"body": "", "body_source": "desconocido"}


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

# ==========================================================
# CLASIFICACIÓN
# ==========================================================
def heuristic_general_classification(sender: str, subject: str, body: str):
    _, addr = parseaddr(sender or "")
    addr_l = (addr or "").lower()
    subj_l = (subject or "").lower()
    body_l = (body or "").lower()
    text_total = f"{subj_l} {body_l}"

    for p in HOSTELERIA_USEFUL_HINTS:
        if re.search(p, text_total):
            return "persona_con_motivacion", "Heurística: detectadas señales claras de consulta hostelera."

    for p in AUTO_EMAIL_PATTERNS:
        if re.search(p, addr_l):
            return "automatico_o_publicidad", "Heurística: remitente típico de correo automático o comercial."

    for p in AUTO_SUBJECT_PATTERNS:
        if re.search(p, subj_l):
            return "automatico_o_publicidad", "Heurística: asunto típico de publicidad o notificación automática."

    for p in PERSONAL_SUBJECT_HINTS:
        if re.search(p, subj_l):
            return "persona_con_motivacion", "Heurística: asunto con señales de petición o comunicación humana."

    hits = sum(1 for p in PERSONAL_BODY_HINTS if re.search(p, body_l))
    if hits >= 1:
        return "persona_con_motivacion", "Heurística: el cuerpo contiene señales de comunicación personal."

    return None, None


def llm_general_classify(sender: str, subject: str, date: str, summary: str, body: str):
    body = (body or "").strip()
    if len(body) > 2500:
        body = body[:2500] + "…"

    prompt = f"""
Clasifica el correo en una de estas dos etiquetas EXACTAS:
- "automatico_o_publicidad"
- "persona_con_motivacion"

Devuelve SOLO JSON válido:
{{"label":"automatico_o_publicidad|persona_con_motivacion","reason":"..."}}

Datos:
De: {sender}
Fecha: {date}
Asunto: {subject}
Resumen: {summary}
Contenido: \"\"\"{body}\"\"\"

Criterios:
- "automatico_o_publicidad": no-reply, newsletters, marketing, alertas, notificaciones, recibos o mensajes sin atención humana real.
- "persona_con_motivacion": una persona escribe con una intención concreta hacia el negocio, como preguntar, reservar, coordinar o solicitar información.
Si dudas, elige "automatico_o_publicidad".
""".strip()

    raw = ollama_call(prompt).strip()
    obj = parse_json_with_fallback(
        raw,
        {"label": "automatico_o_publicidad", "reason": "No se pudo parsear JSON; fallback."}
    )

    label = (obj.get("label") or "").strip()
    reason = normalize_single_paragraph(str(obj.get("reason", "")))

    if label not in ("automatico_o_publicidad", "persona_con_motivacion"):
        l = label.lower()
        label = "persona_con_motivacion" if ("persona" in l or "motiv" in l or l == "b") else "automatico_o_publicidad"

    reason_l = reason.lower()
    if label == "automatico_o_publicidad" and (
        "tono personal" in reason_l
        or "requiere atención" in reason_l
        or "solicita" in reason_l
        or "coordin" in reason_l
        or "petición" in reason_l
        or "reserva" in reason_l
    ):
        label = "persona_con_motivacion"
        reason += " (Corrección: el motivo sugiere una comunicación personal.)"

    return {"label": label, "reason": reason}


def heuristic_hosteleria_classification(subject: str, body: str):
    text = f"{subject} {body}".lower()

    # ======================================================
    # IMPORTANTE:
    # Reservas va primero para evitar que correos de grupos
    # con "menús", "fianza" o "precios" caigan en servicios.
    # ======================================================

    patrones_reserva = [
        r"reserva",
        r"reservar",
        r"mesa",
        r"disponibilidad",
        r"cancelar reserva",
        r"modificar reserva",
        r"confirmar reserva",

        # Grupos y eventos que suelen implicar reserva
        r"grupo",
        r"grupos",
        r"cena de empresa",
        r"comida de empresa",
        r"evento",
        r"celebraci[oó]n",
        r"cumplea[nñ]os",

        # Personas / comensales
        r"\b\d{1,3}\s*(personas|persona|comensales|comensal|pax)\b",

        # Condiciones típicas de reservas grandes
        r"men[uú]s?\s+cerrad",
        r"men[uú]s?\s+concertad",
        r"fianza",
        r"precios?\s+aproximad",
        r"presupuesto"
    ]

    patrones_horario = [
        r"horario", r"abr[ií]s", r"abierto", r"cerr[aá]is", r"cierre",
        r"direcci[oó]n", r"ubicaci[oó]n", r"c[oó]mo llegar", r"d[oó]nde est[aá]is",
    ]

    patrones_carta = [
        r"al[eé]rgeno", r"al[eé]rgenos", r"gluten", r"lactosa",
        r"vegano", r"vegetariano", r"cel[ií]aco", r"intolerancia",
        r"carta", r"men[uú]", r"platos", r"bebidas",
        r"ingredientes", r"wok", r"terraza", r"delivery",
        r"servicio", r"servicios", r"eventos"
    ]

    # 1. Primero reservas
    for p in patrones_reserva:
        if re.search(p, text):
            return "reserva", "Heurística hostelera: detectada consulta de reserva o reserva de grupo."

    # 2. Luego horario / ubicación
    for p in patrones_horario:
        if re.search(p, text):
            return "horario_ubicacion", "Heurística hostelera: detectada consulta sobre horario o ubicación."

    # 3. Al final carta / alérgenos / servicios
    for p in patrones_carta:
        if re.search(p, text):
            return "carta_alergenos_servicios", "Heurística hostelera: detectada consulta sobre carta, alérgenos o servicios."

    return None, None


def llm_hosteleria_classify(sender: str, subject: str, date: str, summary: str, body: str):
    body = (body or "").strip()
    if len(body) > 2500:
        body = body[:2500] + "…"

    prompt = f"""
Clasifica este correo de un negocio de hostelería en UNA de estas categorías EXACTAS:

- "reserva"
- "horario_ubicacion"
- "carta_alergenos_servicios"
- "otro_cliente"

Devuelve SOLO JSON válido:
{{"label":"reserva|horario_ubicacion|carta_alergenos_servicios|otro_cliente","reason":"..."}}

Datos:
De: {sender}
Fecha: {date}
Asunto: {subject}
Resumen: {summary}
Contenido: \"\"\"{body}\"\"\"

Criterios:
- "reserva": consultas o peticiones sobre reservar, modificar, cancelar, disponibilidad de mesa, grupos, eventos, número de personas o cenas de empresa.
- Si el correo habla de reserva o grupo y además menciona menús cerrados, menús concertados, precios o fianza, sigue siendo "reserva".
- "horario_ubicacion": preguntas sobre horario, apertura, cierre, dirección, ubicación o cómo llegar.
- "carta_alergenos_servicios": preguntas sobre menú, carta, platos, bebidas, ingredientes, alérgenos, opciones alimentarias o servicios del local cuando NO hay intención principal de reserva.
- "otro_cliente": correo real de un cliente que no encaja claramente en las categorías anteriores.

Si dudas entre "reserva" y "carta_alergenos_servicios", elige "reserva" si hay fecha, hora, mesa, disponibilidad, grupo o número de personas.
""".strip()

    raw = ollama_call(prompt).strip()
    obj = parse_json_with_fallback(
        raw,
        {"label": "otro_cliente", "reason": "No se pudo parsear JSON; fallback."}
    )

    label = (obj.get("label") or "").strip()
    reason = normalize_single_paragraph(str(obj.get("reason", "")))

    if label not in ("reserva", "horario_ubicacion", "carta_alergenos_servicios", "otro_cliente"):
        l = label.lower()
        if "reserva" in l or "mesa" in l or "disponibilidad" in l or "grupo" in l:
            label = "reserva"
        elif "horario" in l or "ubic" in l or "direcci" in l:
            label = "horario_ubicacion"
        elif "alergen" in l or "carta" in l or "servicio" in l or "menu" in l or "wok" in l:
            label = "carta_alergenos_servicios"
        else:
            label = "otro_cliente"

    return {"label": label, "reason": reason}

# ==========================================================
# PRIORIZACIÓN
# ==========================================================
def heuristic_priority(host_label: str, subject: str, body: str, summary: str):
    text = f"{subject} {body} {summary}".lower()

    if host_label == "reserva":
        priority = 7
        reasons = ["La categoría reserva suele requerir atención operativa."]
    elif host_label == "horario_ubicacion":
        priority = 4
        reasons = ["Las consultas de horario o ubicación suelen ser informativas."]
    elif host_label == "carta_alergenos_servicios":
        priority = 5
        reasons = ["Puede influir en la atención y experiencia del cliente."]
    else:
        priority = 4
        reasons = ["Correo real de cliente sin categoría específica."]

    if re.search(r"\bhoy\b|\besta noche\b|\besta tarde\b|\bahora\b|\bcuanto antes\b|\burgente\b", text):
        priority += 2
        reasons.append("Contiene señales de urgencia temporal inmediata.")

    if re.search(r"\bmañana\b|\bpasado mañana\b|\beste fin de semana\b|\beste viernes\b|\beste sábado\b|\beste domingo\b", text):
        priority += 1
        reasons.append("Hace referencia a una necesidad próxima en el tiempo.")

    if re.search(r"\bgrupo\b|\bgrupos\b|\bevento\b|\bcelebraci[oó]n\b|\bcumplea[nñ]os\b|\bcena de empresa\b|\bcomida de empresa\b", text):
        priority += 2
        reasons.append("Puede implicar coordinación especial del establecimiento.")

    if re.search(r"\bal[eé]rgeno\b|\bal[eé]rgenos\b|\bgluten\b|\blactosa\b|\bcel[ií]aco\b|\bintolerancia\b", text):
        priority += 3
        reasons.append("Los alérgenos o intolerancias tienen impacto importante en el servicio.")

    if re.search(r"\bcancelar reserva\b|\bmodificar reserva\b|\bdisponibilidad\b", text):
        priority += 1
        reasons.append("Afecta directamente a la organización de mesas o reservas.")

    if re.search(r"\bsolo informaci[oó]n\b|\bcuando pod[aá]is\b|\bsin prisa\b|\bconsulta general\b", text):
        priority -= 2
        reasons.append("El mensaje sugiere baja urgencia.")

    return max(0, min(10, priority)), " ".join(reasons)


def llm_priority(host_label: str, sender: str, subject: str, date: str, summary: str, body: str):
    body = (body or "").strip()
    if len(body) > 2500:
        body = body[:2500] + "…"

    prompt = f"""
Evalúa la PRIORIDAD de este correo para la operativa de un negocio de hostelería.
Escala: 0 (poco prioritario) a 10 (muy prioritario).

Devuelve SOLO JSON válido:
{{"priority":0,"reason":"..."}}

Criterios orientativos:
- 9-10: reserva para hoy o muy próxima, incidencias sensibles, alérgenos urgentes, grupos/eventos con impacto alto, necesidad inmediata de respuesta.
- 6-8: acciones necesarias con plazo cercano, reservas normales, cambios o coordinación importante.
- 3-5: petición razonable sin urgencia clara, seguimiento normal.
- 0-2: informativo, opcional o de baja relevancia operativa.

Datos:
Categoría hostelera: {host_label}
De: {sender}
Fecha: {date}
Asunto: {subject}
Resumen: {summary}
Contenido: \"\"\"{body}\"\"\"

No inventes. Si falta información, usa un valor conservador.
""".strip()

    raw = ollama_call(prompt).strip()
    obj = parse_json_with_fallback(
        raw,
        {"priority": 5, "reason": "No se pudo parsear JSON; fallback a 5."}
    )

    try:
        priority = int(obj.get("priority", 5))
    except Exception:
        priority = 5

    priority = max(0, min(10, priority))
    reason = normalize_single_paragraph(str(obj.get("reason", ""))) or "Sin motivo proporcionado."
    return {"priority": priority, "reason": reason}

# ==========================================================
# EXTRACCIÓN DE DATOS
# ==========================================================
def empty_extracted_data():
    return {
        "fecha": "",
        "hora": "",
        "personas": "",
        "nombre": "",
        "telefono": "",
        "alergenos": [],
        "restricciones": [],
        "servicios": [],
        "notas": []
    }


def sanitize_extracted_data(host_label: str, data: dict) -> dict:
    cleaned = empty_extracted_data()

    for key in cleaned.keys():
        if isinstance(cleaned[key], list):
            cleaned[key] = unique_list(data.get(key, []) or [])
        else:
            cleaned[key] = str(data.get(key, "") or "").strip()

    if "@" in cleaned["telefono"]:
        cleaned["telefono"] = ""

    if cleaned["personas"] and not re.fullmatch(r"\d{1,3}", cleaned["personas"]):
        cleaned["personas"] = ""

    if host_label != "reserva":
        cleaned["fecha"] = ""
        cleaned["hora"] = ""
        cleaned["personas"] = ""
        cleaned["nombre"] = ""
        cleaned["telefono"] = ""

    return cleaned


def detect_phone(text: str) -> str:
    matches = re.findall(r'(?:(?:\+34[\s-]?)?[6789]\d{2}[\s-]?\d{3}[\s-]?\d{3})', text)
    return re.sub(r"\s+", " ", matches[0]).strip() if matches else ""


def detect_people(text: str) -> str:
    patterns = [
        r"\b(\d{1,3})\s*(personas|persona|comensales|comensal|pax)\b",
        r"\bpara\s+(\d{1,3})\s*(personas|persona|comensales|comensal|pax)?\b"
    ]
    for p in patterns:
        m = re.search(p, text, flags=re.I)
        if m:
            return m.group(1)
    return ""


def detect_time(text: str) -> str:
    patterns = [
        r"\b(?:a las|sobre las|hacia las)\s*(\d{1,2}[:.]\d{2})\b",
        r"\b(\d{1,2}[:.]\d{2})\b",
        r"\b(?:a las|sobre las|hacia las)\s*(\d{1,2})\b"
    ]
    for p in patterns:
        m = re.search(p, text, flags=re.I)
        if m:
            value = m.group(1).replace(".", ":")
            return f"{value}:00" if re.fullmatch(r"\d{1,2}", value) else value
    return ""


def detect_date(text: str) -> str:
    patterns = [
        r"\b(hoy|mañana|pasado mañana|esta noche|esta tarde|este fin de semana)\b",
        r"\b(\d{1,2}[/-]\d{1,2}[/-]\d{2,4})\b",
        r"\b(\d{1,2}\s+de\s+[a-záéíóúñ]+)\b",
        r"\b(lunes|martes|miércoles|miercoles|jueves|viernes|sábado|sabado|domingo)\b"
    ]
    for p in patterns:
        m = re.search(p, text, flags=re.I)
        if m:
            return m.group(1)
    return ""


def detect_name_from_body(text: str) -> str:
    patterns = [
        r"(?:me llamo|soy|mi nombre es)\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+){0,2})",
        r"(?:nombre|a nombre de)\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+){0,2})"
    ]
    for p in patterns:
        m = re.search(p, text)
        if m:
            return m.group(1).strip()
    return ""


def heuristic_extract_useful_data(host_label: str, sender: str, subject: str, body: str, summary: str):
    text = f"{subject}\n{body}\n{summary}"
    data = empty_extracted_data()

    if host_label == "reserva":
        data["telefono"] = detect_phone(text)
        data["personas"] = detect_people(text)
        data["hora"] = detect_time(text)
        data["fecha"] = detect_date(text)
        data["nombre"] = detect_name_from_body(text)

    low = text.lower()
    data["alergenos"] = unique_list([a for a in ALERGENOS_KNOWN if a in low])
    data["restricciones"] = unique_list([r for r in RESTRICCIONES_KNOWN if r in low])
    data["servicios"] = unique_list([s for s in SERVICIOS_KNOWN if s in low])

    notes = []
    if "cancelar reserva" in low:
        notes.append("El cliente quiere cancelar la reserva.")
    if "modificar reserva" in low:
        notes.append("El cliente quiere modificar la reserva.")
    if "disponibilidad" in low and host_label == "reserva":
        notes.append("El cliente pregunta por disponibilidad.")
    if "cumpleaños" in low or "cumpleanos" in low or "celebración" in low or "celebracion" in low:
        notes.append("La consulta parece relacionada con una celebración.")
    if "grupo" in low or "grupos" in low:
        notes.append("La consulta puede implicar un grupo.")
    if "cena de empresa" in low or "comida de empresa" in low:
        notes.append("La consulta parece relacionada con una empresa.")
    if "menú concertado" in low or "menús concertados" in low or "menu concertado" in low or "menus concertados" in low:
        notes.append("El cliente pregunta por menús concertados.")
    if "menú cerrado" in low or "menús cerrados" in low or "menu cerrado" in low or "menus cerrados" in low:
        notes.append("El cliente pregunta por menús cerrados.")
    if "fianza" in low:
        notes.append("El cliente pregunta por fianza o condiciones de reserva.")
    if "precio" in low or "precios" in low or "presupuesto" in low:
        notes.append("El cliente pregunta por precios o presupuesto.")

    data["notas"] = unique_list(notes)

    missing = []
    if host_label == "reserva":
        for field in ("fecha", "hora", "personas", "nombre", "telefono"):
            if not str(data[field]).strip():
                missing.append(field)
    elif host_label == "carta_alergenos_servicios":
        if not data["alergenos"] and not data["restricciones"] and not data["servicios"]:
            missing.append("detalle_consulta")
    elif host_label == "horario_ubicacion":
        if "horario" not in low and "ubic" not in low and "direcci" not in low and "cómo llegar" not in low:
            missing.append("tipo_consulta")

    return {
        "detected": data,
        "missing": unique_list(missing),
        "notes": "Extracción heurística realizada."
    }


def merge_extracted_data(host_label: str, base: dict, extra: dict) -> dict:
    merged = empty_extracted_data()
    base_detected = base.get("detected", {}) or {}
    extra_detected = extra.get("detected", {}) or {}

    for key in merged.keys():
        if isinstance(merged[key], list):
            merged[key] = unique_list(list(base_detected.get(key, []) or []) + list(extra_detected.get(key, []) or []))
        else:
            merged[key] = str(extra_detected.get(key) or base_detected.get(key) or "").strip()

    return {
        "detected": sanitize_extracted_data(host_label, merged),
        "missing": unique_list(list(base.get("missing", []) or []) + list(extra.get("missing", []) or [])),
        "notes": normalize_single_paragraph(f"{base.get('notes', '')} {extra.get('notes', '')}")
    }


def llm_extract_useful_data(host_label: str, sender: str, subject: str, date: str, summary: str, body: str, heuristic_data: dict):
    body = (body or "").strip()
    if len(body) > 2500:
        body = body[:2500] + "…"

    prompt = f"""
Extrae datos útiles de este correo de hostelería.

Devuelve SOLO JSON válido con esta estructura:
{{
  "detected": {{
    "fecha": "",
    "hora": "",
    "personas": "",
    "nombre": "",
    "telefono": "",
    "alergenos": [],
    "restricciones": [],
    "servicios": [],
    "notas": []
  }},
  "missing": [],
  "notes": ""
}}

Datos del correo:
- Categoría: {host_label}
- De: {sender}
- Fecha email: {date}
- Asunto: {subject}
- Resumen: {summary}
- Contenido: \"\"\"{body}\"\"\"

Extracción heurística previa:
{json.dumps(heuristic_data, ensure_ascii=False)}

Reglas:
- No inventes datos.
- Si un dato no aparece claro, déjalo vacío.
- En una consulta de alérgenos o carta, no rellenes fecha, hora, personas, nombre o teléfono si no aparecen de forma explícita.
- "missing" debe incluir datos faltantes importantes para tramitar la solicitud.
- En reservas intenta extraer fecha, hora, personas, nombre y teléfono.
- En alérgenos o servicios intenta detectar restricciones o necesidades importantes.
- "notes" debe ser una explicación breve y útil.
""".strip()

    raw = ollama_call(prompt).strip()
    obj = parse_json_with_fallback(
        raw,
        {"detected": empty_extracted_data(), "missing": [], "notes": "No se pudo extraer JSON; fallback."}
    )

    detected = obj.get("detected", {}) or {}
    normalized = empty_extracted_data()

    for key in normalized.keys():
        if isinstance(normalized[key], list):
            normalized[key] = unique_list(detected.get(key, []) or [])
        else:
            normalized[key] = str(detected.get(key, "") or "").strip()

    return {
        "detected": sanitize_extracted_data(host_label, normalized),
        "missing": unique_list(obj.get("missing", []) or []),
        "notes": normalize_single_paragraph(str(obj.get("notes", "") or ""))
    }

# ==========================================================
# RESUMEN Y RESPUESTA
# ==========================================================
def summarize_one_paragraph(subject: str, sender: str, date: str, body: str) -> str:
    body = (body or "").strip()
    if len(body) > MAX_BODY_CHARS:
        body = body[:MAX_BODY_CHARS] + "…"

    prompt = f"""
Eres un asistente que resume correos electrónicos de un negocio de hostelería.
Devuelve SOLO un párrafo en español, sin viñetas, sin títulos, sin saltos de línea y sin código.

Contexto:
- De: {sender}
- Fecha: {date}
- Asunto: {subject}

Contenido:
\"\"\"{body}\"\"\"

Reglas:
- Resume el propósito y los puntos clave.
- Si hay una acción solicitada, indícala.
- No inventes.
- Redacta de forma útil para la gestión del establecimiento.
""".strip()

    return normalize_single_paragraph(ollama_call(prompt))


def generar_borrador_respuesta(email_data: dict) -> str:
    subject = email_data.get("subject", "")
    sender = email_data.get("from", "")
    summary = email_data.get("summary", "")
    category = email_data.get("category", "")
    priority = email_data.get("priority", "")
    original_body = email_data.get("body", "")
    extracted = email_data.get("extracted", {}) or {}
    missing = email_data.get("missing", []) or []

    if len(original_body) > 3000:
        original_body = original_body[:3000] + "…"

    prompt = f"""
Eres un asistente de un negocio de hostelería llamado Kitcherry.
Redacta un borrador de respuesta en español, amable, profesional, claro y breve.

Contexto:
- Remitente: {sender}
- Asunto recibido: {subject}
- Categoría: {category}
- Prioridad: {priority}
- Resumen del correo: {summary}

Datos útiles detectados:
{json.dumps(extracted, ensure_ascii=False)}

Datos faltantes:
{json.dumps(missing, ensure_ascii=False)}

Contenido original del correo:
\"\"\"{original_body}\"\"\"

REGLAS IMPORTANTES:
- NO inventes ningún dato del negocio.
- NO inventes horarios, direcciones, teléfonos, disponibilidad, precios, platos, ingredientes ni políticas.
- Si hace falta incluir un dato del negocio que no aparece confirmado, usa un placeholder entre corchetes.
- Ejemplos de placeholders:
  [HORARIO_APERTURA]
  [HORARIO_CIERRE]
  [DIRECCIÓN]
  [TELÉFONO]
  [EMAIL]
  [DISPONIBILIDAD]
  [DETALLE_CARTA]
  [DETALLE_ALÉRGENOS_CONFIRMADOS]
  [NOMBRE_CLIENTE]
  [FECHA_RESERVA]
  [HORA_RESERVA]

INSTRUCCIONES DE REDACCIÓN:
- Devuelve solo el texto del correo.
- No pongas asunto.
- No expliques lo que estás haciendo.
- Si faltan datos del cliente para tramitar una reserva, pídelos de forma educada.
- Si preguntan por información del negocio no confirmada, responde con placeholders en lugar de inventar.
- Si es una consulta de alérgenos, responde con prudencia y usa placeholders si no hay confirmación exacta.
- El resultado debe ser un borrador editable y rápido de completar por una persona del negocio.
""".strip()

    return ollama_call(prompt).strip()


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
        "dashboard": {"total": 0, "pendiente": 0, "respondido": 0, "archivado": 0, "papelera": 0}
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
                host_label, sender, subject, date, summary, body_text, heuristic_data
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
        tracking = ensure_tracking(uid_str)

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

        try:
            summary = summarize_one_paragraph(subject, sender, date, body_text)
        except Exception as e:
            summary = f"(Error al resumir con Ollama: {e})"

        general_label, general_reason = clasificar_general(sender, subject, date, summary, body_text)
        _, sender_email = parseaddr(sender)

        item = {
            "uid": uid_str,
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

    for lista in (categoria_reserva, categoria_horario_ubicacion, categoria_carta, categoria_otro):
        lista.sort(key=lambda x: x["priority"], reverse=True)

    categoria_papelera.sort(key=lambda x: x.get("tracking", {}).get("enviado_a_papelera_en") or "", reverse=True)

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

# ==========================================================
# RUTAS
# ==========================================================
@app.route("/")
def index():
    return render_template(
        "index.html",
        modelo=OLLAMA_MODEL,
        total_por_defecto=N_ULTIMOS_POR_DEFECTO
    )


@app.route("/api/correos")
def api_correos():
    try:
        n = int(request.args.get("n", N_ULTIMOS_POR_DEFECTO))
    except Exception:
        n = N_ULTIMOS_POR_DEFECTO

    n = max(1, min(50, n))

    return jsonify({
        "ok": True,
        "n": n,
        "modelo": OLLAMA_MODEL,
        "datos": obtener_correos_procesados(n)
    })


@app.route("/api/generar_respuesta", methods=["POST"])
def api_generar_respuesta():
    payload = request.get_json(silent=True) or {}
    uid = str(payload.get("uid") or "").strip()

    try:
        tracking = None
        if uid:
            tracking = update_tracking(
                uid,
                {"borrador_generado_en": now_str()},
                action_name="borrador_generado"
            )

        return jsonify({
            "ok": True,
            "respuesta": generar_borrador_respuesta(payload),
            "tracking": tracking
        })
    except Exception as e:
        return jsonify({"ok": False, "error": str(e)}), 500


@app.route("/api/enviar_respuesta", methods=["POST"])
def api_enviar_respuesta():
    payload = request.get_json(silent=True) or {}

    uid = (payload.get("uid") or "").strip()
    destinatario = (payload.get("to") or "").strip()
    asunto = (payload.get("subject") or "").strip()
    cuerpo = (payload.get("body") or "").strip()

    try:
        enviar_respuesta_email(destinatario, asunto, cuerpo)

        tracking = None
        if uid:
            tracking = update_tracking(
                uid,
                {
                    "estado": "respondido",
                    "respondido_en": now_str(),
                    "respuesta_enviada": cuerpo
                },
                action_name="correo_enviado"
            )

        return jsonify({
            "ok": True,
            "message": "Correo enviado correctamente.",
            "tracking": tracking,
            "respuesta_enviada": cuerpo
        })
    except Exception as e:
        return jsonify({"ok": False, "error": str(e)}), 500


@app.route("/api/cambiar_estado", methods=["POST"])
def api_cambiar_estado():
    payload = request.get_json(silent=True) or {}
    uid = (payload.get("uid") or "").strip()
    estado = (payload.get("estado") or "").strip()

    if not uid:
        return jsonify({"ok": False, "error": "Falta el UID del correo."}), 400

    if estado not in {"pendiente", "respondido", "archivado", "papelera"}:
        return jsonify({"ok": False, "error": "Estado no válido."}), 400

    tracking_actual = ensure_tracking(uid)
    estado_anterior = tracking_actual.get("estado", "pendiente")

    updates = {"estado": estado}

    if estado == "archivado":
        updates["archivado_en"] = now_str()
        accion = "correo_archivado"
    elif estado == "papelera":
        updates["enviado_a_papelera_en"] = now_str()
        accion = "enviado_a_papelera"
    elif estado == "pendiente":
        accion = "recuperado_de_papelera" if estado_anterior == "papelera" else "marcado_como_pendiente"
    else:
        accion = "marcado_como_respondido"

    try:
        tracking = update_tracking(uid, updates, action_name=accion)
        return jsonify({"ok": True, "tracking": tracking})
    except Exception as e:
        return jsonify({"ok": False, "error": str(e)}), 500


@app.route("/api/vaciar_papelera", methods=["POST"])
def api_vaciar_papelera():
    try:
        data = load_trazabilidad()
        keys_to_delete = [uid for uid, item in data.items() if item.get("estado") == "papelera"]
        for uid in keys_to_delete:
            del data[uid]
        save_trazabilidad(data)
        return jsonify({"ok": True, "deleted": len(keys_to_delete)})
    except Exception as e:
        return jsonify({"ok": False, "error": str(e)}), 500


@app.route("/api/adjunto")
def api_adjunto():
    uid = (request.args.get("uid") or "").strip()
    index_raw = (request.args.get("index") or "").strip()

    if not uid:
        return jsonify({"ok": False, "error": "Falta el UID del correo."}), 400

    try:
        attachment_index = int(index_raw)
    except Exception:
        return jsonify({"ok": False, "error": "Índice de adjunto inválido."}), 400

    try:
        attachment = fetch_attachment_by_uid(uid, attachment_index)
        return send_file(
            io.BytesIO(attachment["data"]),
            mimetype=attachment["content_type"],
            as_attachment=True,
            download_name=attachment["filename"]
        )
    except Exception as e:
        return jsonify({"ok": False, "error": str(e)}), 404


if __name__ == "__main__":
    app.run(debug=True)