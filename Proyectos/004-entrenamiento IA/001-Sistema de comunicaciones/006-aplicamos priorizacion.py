import os
import re
import imaplib
import email
import json
import urllib.request
from email.header import decode_header
from email.utils import parseaddr

# ==========================================================
# CONFIGURACIÓN KITCHERRY
# ==========================================================
correo = os.environ["MI_CORREO_KITCHERRY"]
password = os.environ["MI_CONTRASENA_CORREO_KITCHERRY"]
imap_server = os.environ["MI_SERVIDORIMAP_CORREO_KITCHERRY"]

OLLAMA_URL = os.environ.get("OLLAMA_URL", "http://localhost:11434/api/generate")
OLLAMA_MODEL = os.environ.get("OLLAMA_MODEL", "llama3:latest")

N_ULTIMOS = int(os.environ.get("N_ULTIMOS", "5"))
MAX_BODY_CHARS = int(os.environ.get("MAX_BODY_CHARS", "8000"))

OLLAMA_OPTIONS = {"temperature": 0.1}

# ==========================================================
# HELPERS
# ==========================================================
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


def extract_text_from_message(msg: email.message.Message) -> str:
    texts = []

    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            disp = str(part.get("Content-Disposition") or "")

            if "attachment" in disp.lower():
                continue

            if ctype in ("text/plain", "text/html"):
                payload = part.get_payload(decode=True)
                if payload is None:
                    continue

                charset = part.get_content_charset() or "utf-8"

                try:
                    content = payload.decode(charset, errors="ignore")
                except Exception:
                    content = payload.decode("utf-8", errors="ignore")

                if ctype == "text/html":
                    content = (
                        content.replace("<br>", "\n")
                               .replace("<br/>", "\n")
                               .replace("<br />", "\n")
                               .replace("</p>", "\n")
                    )
                    content = re.sub(r"<script.*?>.*?</script>", "", content, flags=re.S | re.I)
                    content = re.sub(r"<style.*?>.*?</style>", "", content, flags=re.S | re.I)
                    content = re.sub(r"<[^>]+>", " ", content)
                    content = re.sub(r"\s+", " ", content).strip()

                texts.append(content)
    else:
        ctype = msg.get_content_type()
        payload = msg.get_payload(decode=True)

        if payload is not None and ctype in ("text/plain", "text/html"):
            charset = msg.get_content_charset() or "utf-8"

            try:
                content = payload.decode(charset, errors="ignore")
            except Exception:
                content = payload.decode("utf-8", errors="ignore")

            if ctype == "text/html":
                content = re.sub(r"<script.*?>.*?</script>", "", content, flags=re.S | re.I)
                content = re.sub(r"<style.*?>.*?</style>", "", content, flags=re.S | re.I)
                content = re.sub(r"<[^>]+>", " ", content)
                content = re.sub(r"\s+", " ", content).strip()

            texts.append(content)

    text = "\n\n".join(t for t in texts if t and t.strip())
    return text.strip()


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

    with urllib.request.urlopen(req, timeout=120) as resp:
        data = json.loads(resp.read().decode("utf-8", errors="ignore"))

    return (data.get("response") or "").strip()


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


def normalize_single_paragraph(text: str) -> str:
    return " ".join((text or "").split()).strip()


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

    resumen = ollama_call(prompt)
    return normalize_single_paragraph(resumen)

# ==========================================================
# CLASIFICACIÓN GENERAL
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
    r"grupo", r"cumplea[nñ]os", r"vegetariano", r"vegano"
]

def heuristic_general_classification(sender: str, subject: str, body: str) -> tuple[str | None, str | None]:
    _, addr = parseaddr(sender or "")
    addr_l = (addr or "").lower()
    subj_l = (subject or "").lower()
    body_l = (body or "").lower()
    text_total = f"{subj_l} {body_l}"

    # Prioridad a señales hosteleras útiles para no mandar reservas a automáticos
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


def llm_general_classify(sender: str, subject: str, date: str, summary: str, body: str) -> dict:
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
        if "persona" in l or "motiv" in l or l == "b":
            label = "persona_con_motivacion"
        else:
            label = "automatico_o_publicidad"

    # Guardarraíl como en la idea del profe
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
        reason = reason + " (Corrección: el motivo sugiere una comunicación personal.)"

    return {"label": label, "reason": reason}

# ==========================================================
# CLASIFICACIÓN HOSTELERA
# ==========================================================
def heuristic_hosteleria_classification(subject: str, body: str) -> tuple[str | None, str | None]:
    text = f"{subject} {body}".lower()

    patrones_reserva = [
        r"reserva", r"reservar", r"mesa", r"personas", r"comida para", r"cena para",
        r"disponibilidad", r"cancelar reserva", r"modificar reserva", r"grupo",
    ]
    patrones_horario_ubicacion = [
        r"horario", r"abr[ií]s", r"abierto", r"cerr[aá]is", r"cierre",
        r"direcci[oó]n", r"ubicaci[oó]n", r"c[oó]mo llegar", r"d[oó]nde est[aá]is",
    ]
    patrones_carta_alergenos_servicios = [
        r"carta", r"men[uú]", r"platos", r"bebidas", r"al[eé]rgeno", r"al[eé]rgenos",
        r"gluten", r"lactosa", r"vegano", r"vegetariano", r"terraza", r"delivery",
        r"servicio", r"servicios", r"eventos", r"cumplea[nñ]os", r"celebraci[oó]n",
    ]

    for p in patrones_reserva:
        if re.search(p, text):
            return "reserva", "Heurística hostelera: detectada consulta de reserva."

    for p in patrones_horario_ubicacion:
        if re.search(p, text):
            return "horario_ubicacion", "Heurística hostelera: detectada consulta sobre horario o ubicación."

    for p in patrones_carta_alergenos_servicios:
        if re.search(p, text):
            return "carta_alergenos_servicios", "Heurística hostelera: detectada consulta sobre carta, alérgenos o servicios."

    return None, None


def llm_hosteleria_classify(sender: str, subject: str, date: str, summary: str, body: str) -> dict:
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
- "reserva": consultas o peticiones sobre reservar, modificar, cancelar, disponibilidad de mesa, grupos o número de personas.
- "horario_ubicacion": preguntas sobre horario, apertura, cierre, dirección, ubicación o cómo llegar.
- "carta_alergenos_servicios": preguntas sobre menú, carta, platos, bebidas, ingredientes, alérgenos, opciones alimentarias o servicios del local.
- "otro_cliente": correo real de un cliente que no encaja claramente en las categorías anteriores.

Si dudas, elige la categoría más cercana.
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
        if "reserva" in l:
            label = "reserva"
        elif "horario" in l or "ubic" in l or "direcci" in l:
            label = "horario_ubicacion"
        elif "carta" in l or "alergen" in l or "servicio" in l or "menu" in l:
            label = "carta_alergenos_servicios"
        else:
            label = "otro_cliente"

    return {"label": label, "reason": reason}

# ==========================================================
# PRIORIZACIÓN
# ==========================================================
def heuristic_priority(host_label: str, subject: str, body: str, summary: str) -> tuple[int | None, str | None]:
    text = f"{subject} {body} {summary}".lower()

    # Base por categoría
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

    # Señales de urgencia temporal
    if re.search(r"\bhoy\b|\besta noche\b|\besta tarde\b|\bahora\b|\bcuanto antes\b|\burgente\b", text):
        priority += 2
        reasons.append("Contiene señales de urgencia temporal inmediata.")

    if re.search(r"\bmañana\b|\bpasado mañana\b|\beste fin de semana\b|\beste viernes\b|\beste sábado\b|\beste domingo\b", text):
        priority += 1
        reasons.append("Hace referencia a una necesidad próxima en el tiempo.")

    # Impacto operativo
    if re.search(r"\bgrupo\b|\bgrupos\b|\bevento\b|\bcelebraci[oó]n\b|\bcumplea[nñ]os\b", text):
        priority += 2
        reasons.append("Puede implicar coordinación especial del establecimiento.")

    if re.search(r"\bal[eé]rgeno\b|\bal[eé]rgenos\b|\bgluten\b|\blactosa\b|\bcel[ií]aco\b|\bintolerancia\b", text):
        priority += 3
        reasons.append("Los alérgenos o intolerancias tienen impacto importante en el servicio.")

    if re.search(r"\bcancelar reserva\b|\bmodificar reserva\b|\bdisponibilidad\b", text):
        priority += 1
        reasons.append("Afecta directamente a la organización de mesas o reservas.")

    # Señales de baja urgencia
    if re.search(r"\bsolo informaci[oó]n\b|\bcuando pod[aá]is\b|\bsin prisa\b|\bconsulta general\b", text):
        priority -= 2
        reasons.append("El mensaje sugiere baja urgencia.")

    priority = max(0, min(10, priority))
    return priority, " ".join(reasons)


def llm_priority(host_label: str, sender: str, subject: str, date: str, summary: str, body: str) -> dict:
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

    priority = obj.get("priority", 5)
    try:
        priority = int(priority)
    except Exception:
        priority = 5

    priority = max(0, min(10, priority))
    reason = normalize_single_paragraph(str(obj.get("reason", "")))
    if not reason:
        reason = "Sin motivo proporcionado."

    return {"priority": priority, "reason": reason}

# ==========================================================
# MAIN
# ==========================================================
mail = imaplib.IMAP4_SSL(imap_server)
mail.login(correo, password)
mail.select("INBOX")

status, mensajes = mail.search(None, "ALL")
ids = mensajes[0].split()
ultimos_ids = ids[-N_ULTIMOS:]

categoria_automatico = []
categoria_reserva = []
categoria_horario_ubicacion = []
categoria_carta_alergenos_servicios = []
categoria_otro_cliente = []

print(f"\nProcesando los últimos {N_ULTIMOS} correos con Ollama ({OLLAMA_MODEL})...\n")

for i in reversed(ultimos_ids):
    status, data = mail.fetch(i, "(RFC822)")
    raw_email = data[0][1]
    msg = email.message_from_bytes(raw_email)

    subject = decode_mime_header(msg.get("Subject", ""))
    sender = decode_mime_header(msg.get("From", ""))
    date = decode_mime_header(msg.get("Date", ""))

    body_text = extract_text_from_message(msg) or "(Sin cuerpo de texto legible o solo adjuntos)"

    # Resumen
    try:
        summary = summarize_one_paragraph(subject, sender, date, body_text)
    except Exception as e:
        summary = f"(Error al resumir con Ollama: {e})"

    # Clasificación general
    general_label, general_reason = heuristic_general_classification(sender, subject, body_text)
    if general_label is None:
        cls_general = llm_general_classify(sender, subject, date, summary, body_text)
        general_label = cls_general["label"]
        general_reason = cls_general["reason"]

    item = {
        "from": sender,
        "subject": subject,
        "date": date,
        "summary": summary,
        "general_reason": general_reason,
        "priority": None,
        "priority_reason": None,
        "host_reason": None,
    }

    # Automáticos/publicidad: no pasan a prioridad
    if general_label == "automatico_o_publicidad":
        categoria_automatico.append(item)
        continue

    # Clasificación hostelera
    host_label, host_reason = heuristic_hosteleria_classification(subject, body_text)
    if host_label is None:
        cls_host = llm_hosteleria_classify(sender, subject, date, summary, body_text)
        host_label = cls_host["label"]
        host_reason = cls_host["reason"]

    item["host_reason"] = host_reason

    # Priorización: primero heurística, si quieres puedes forzar LLM siempre,
    # pero así va más rápido y estable
    priority, priority_reason = heuristic_priority(host_label, subject, body_text, summary)

    # Refinado opcional con LLM en casos ambiguos
    # Aquí lo usamos solo para "otro_cliente" o si la prioridad quedó media
    if host_label == "otro_cliente" or priority in (4, 5):
        try:
            pr = llm_priority(host_label, sender, subject, date, summary, body_text)
            priority = pr["priority"]
            priority_reason = pr["reason"]
        except Exception:
            pass

    item["priority"] = priority
    item["priority_reason"] = priority_reason

    if host_label == "reserva":
        categoria_reserva.append(item)
    elif host_label == "horario_ubicacion":
        categoria_horario_ubicacion.append(item)
    elif host_label == "carta_alergenos_servicios":
        categoria_carta_alergenos_servicios.append(item)
    else:
        categoria_otro_cliente.append(item)

# Ordenar por prioridad descendente
categoria_reserva.sort(key=lambda x: x["priority"], reverse=True)
categoria_horario_ubicacion.sort(key=lambda x: x["priority"], reverse=True)
categoria_carta_alergenos_servicios.sort(key=lambda x: x["priority"], reverse=True)
categoria_otro_cliente.sort(key=lambda x: x["priority"], reverse=True)

# ==========================================================
# MOSTRAR RESULTADOS
# ==========================================================
def mostrar_categoria(titulo: str, lista: list, mostrar_prioridad: bool = False):
    print("=" * 60)
    print(titulo)
    print("=" * 60)
    print()

    if not lista:
        print("No hay correos en esta categoría.\n")
        return

    for e in lista:
        print("—" * 60)
        if mostrar_prioridad:
            print("PRIORIDAD:", e["priority"], "/ 10")
            print("Motivo prioridad:", e["priority_reason"])
        print("De:", e["from"])
        print("Asunto:", e["subject"])
        print("Fecha:", e["date"])
        print("Resumen:", e["summary"])
        print("Motivo general:", e["general_reason"])
        if e["host_reason"]:
            print("Motivo hostelería:", e["host_reason"])
        print()


mostrar_categoria("CATEGORÍA 1: Automáticos / publicidad / poco relevantes", categoria_automatico, mostrar_prioridad=False)
mostrar_categoria("CATEGORÍA 2: Reservas", categoria_reserva, mostrar_prioridad=True)
mostrar_categoria("CATEGORÍA 3: Horario y ubicación", categoria_horario_ubicacion, mostrar_prioridad=True)
mostrar_categoria("CATEGORÍA 4: Carta, alérgenos y servicios", categoria_carta_alergenos_servicios, mostrar_prioridad=True)
mostrar_categoria("CATEGORÍA 5: Otros correos de clientes", categoria_otro_cliente, mostrar_prioridad=True)

mail.close()
mail.logout()