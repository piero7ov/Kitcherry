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
    textos = []

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

                textos.append(content)

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

            textos.append(content)

    texto_final = "\n\n".join(t for t in textos if t and t.strip())
    return texto_final.strip()


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
    return " ".join(resumen.split())


# ==========================================================
# CLASIFICACIÓN GENERAL (HEURÍSTICA + LLM)
# ==========================================================
AUTO_EMAIL_PATTERNS = [
    r"no-?reply", r"noreply", r"do-?not-?reply",
    r"mailer-daemon", r"postmaster",
    r"newsletter", r"notifications?", r"alertas?",
    r"billing", r"invoice", r"receipt",
    r"promotions?", r"marketing", r"mailing",
]

AUTO_SUBJECT_PATTERNS = [
    r"unsubscribe|darse de baja|baja",
    r"oferta|promoci[oó]n|descuento|rebaja",
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
]

HOSTELERIA_URGENT_HINTS = [
    r"reserva", r"reservar", r"mesa", r"personas",
    r"horario", r"carta", r"men[uú]",
    r"al[eé]rgeno", r"gluten", r"lactosa",
    r"direcci[oó]n", r"ubicaci[oó]n",
    r"terraza", r"evento", r"celebraci[oó]n",
    r"disponibilidad", r"cancelar reserva", r"modificar reserva",
]

def heuristic_general_classification(sender: str, subject: str, body: str) -> str | None:
    """
    Devuelve:
      - 'automatico_o_publicidad'
      - 'persona_con_motivacion'
      - None si no está claro
    """
    _, addr = parseaddr(sender or "")
    addr_l = (addr or "").lower()
    subj_l = (subject or "").lower()
    body_l = (body or "").lower()
    texto_total = f"{subj_l} {body_l}"

    # 1) Prioridad máxima:
    # Si el asunto o el cuerpo tienen señales claras de consulta hostelera real,
    # debe entrar como persona_con_motivacion antes de revisar patrones automáticos.
    for p in HOSTELERIA_URGENT_HINTS:
        if re.search(p, texto_total):
            return "persona_con_motivacion"

    # 2) Remitentes claramente automáticos
    for p in AUTO_EMAIL_PATTERNS:
        if re.search(p, addr_l):
            return "automatico_o_publicidad"

    # 3) Asuntos claramente automáticos
    for p in AUTO_SUBJECT_PATTERNS:
        if re.search(p, subj_l):
            return "automatico_o_publicidad"

    # 4) Señales personales por asunto
    for p in PERSONAL_SUBJECT_HINTS:
        if re.search(p, subj_l):
            return "persona_con_motivacion"

    # 5) Señales personales por contenido
    hits = 0
    for p in PERSONAL_BODY_HINTS:
        if re.search(p, body_l):
            hits += 1

    if hits >= 1:
        return "persona_con_motivacion"

    return None


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

    try:
        obj = json.loads(raw)
    except Exception:
        m = re.search(r"\{.*\}", raw, flags=re.S)
        if m:
            try:
                obj = json.loads(m.group(0))
            except Exception:
                obj = {
                    "label": "automatico_o_publicidad",
                    "reason": "No se pudo parsear JSON; fallback."
                }
        else:
            obj = {
                "label": "automatico_o_publicidad",
                "reason": "No se pudo parsear JSON; fallback."
            }

    label = (obj.get("label") or "").strip()
    reason = (obj.get("reason") or "").strip()

    if label not in ("automatico_o_publicidad", "persona_con_motivacion"):
        l = label.lower()
        if "persona" in l or "motiv" in l:
            label = "persona_con_motivacion"
        else:
            label = "automatico_o_publicidad"

    return {"label": label, "reason": reason}


# ==========================================================
# CLASIFICACIÓN HOSTELERA (SOLO CORREOS ÚTILES)
# ==========================================================
def heuristic_hosteleria_classification(subject: str, body: str) -> str | None:
    texto = f"{subject} {body}".lower()

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
        r"servicio", r"eventos", r"cumplea[nñ]os", r"celebraci[oó]n",
    ]

    for p in patrones_reserva:
        if re.search(p, texto):
            return "reserva"

    for p in patrones_horario_ubicacion:
        if re.search(p, texto):
            return "horario_ubicacion"

    for p in patrones_carta_alergenos_servicios:
        if re.search(p, texto):
            return "carta_alergenos_servicios"

    return None


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

    try:
        obj = json.loads(raw)
    except Exception:
        m = re.search(r"\{.*\}", raw, flags=re.S)
        if m:
            try:
                obj = json.loads(m.group(0))
            except Exception:
                obj = {
                    "label": "otro_cliente",
                    "reason": "No se pudo parsear JSON; fallback."
                }
        else:
            obj = {
                "label": "otro_cliente",
                "reason": "No se pudo parsear JSON; fallback."
            }

    label = (obj.get("label") or "").strip()
    reason = (obj.get("reason") or "").strip()

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

    try:
        summary = summarize_one_paragraph(subject, sender, date, body_text)
    except Exception as e:
        summary = f"(Error al resumir con Ollama: {e})"

    # Clasificación general
    h_general = heuristic_general_classification(sender, subject, body_text)

    if h_general is not None:
        general_label = h_general
        if general_label == "automatico_o_publicidad":
            general_reason = "Clasificación general por heurística: patrón de automático/publicidad."
        else:
            general_reason = "Clasificación general por heurística: consulta humana o hostelera detectada."
    else:
        cls_general = llm_general_classify(sender, subject, date, summary, body_text)
        general_label = cls_general["label"]
        general_reason = cls_general["reason"]

    item = {
        "from": sender,
        "subject": subject,
        "date": date,
        "summary": summary,
        "general_reason": general_reason,
    }

    # Si es automático/publicidad, se guarda directamente
    if general_label == "automatico_o_publicidad":
        categoria_automatico.append(item)
        continue

    # Si es un correo útil, lo clasificamos por temática hostelera
    h_hosteleria = heuristic_hosteleria_classification(subject, body_text)

    if h_hosteleria is not None:
        host_label = h_hosteleria
        if host_label == "reserva":
            host_reason = "Clasificación hostelera por heurística: detectada consulta de reserva."
        elif host_label == "horario_ubicacion":
            host_reason = "Clasificación hostelera por heurística: detectada consulta sobre horario o ubicación."
        elif host_label == "carta_alergenos_servicios":
            host_reason = "Clasificación hostelera por heurística: detectada consulta sobre carta, alérgenos o servicios."
        else:
            host_reason = "Clasificación hostelera por heurística."
    else:
        cls_host = llm_hosteleria_classify(sender, subject, date, summary, body_text)
        host_label = cls_host["label"]
        host_reason = cls_host["reason"]

    item["host_reason"] = host_reason

    if host_label == "reserva":
        categoria_reserva.append(item)
    elif host_label == "horario_ubicacion":
        categoria_horario_ubicacion.append(item)
    elif host_label == "carta_alergenos_servicios":
        categoria_carta_alergenos_servicios.append(item)
    else:
        categoria_otro_cliente.append(item)

# ==========================================================
# MOSTRAR RESULTADOS
# ==========================================================
def mostrar_categoria(titulo: str, lista: list):
    print("=" * 60)
    print(titulo)
    print("=" * 60)
    print()

    if not lista:
        print("No hay correos en esta categoría.\n")
        return

    for e in lista:
        print("—" * 60)
        print("De:", e["from"])
        print("Asunto:", e["subject"])
        print("Fecha:", e["date"])
        print("Resumen:", e["summary"])
        if "general_reason" in e:
            print("Motivo general:", e["general_reason"])
        if "host_reason" in e:
            print("Motivo hostelería:", e["host_reason"])
        print()


mostrar_categoria("CATEGORÍA 1: Automáticos / publicidad / poco relevantes", categoria_automatico)
mostrar_categoria("CATEGORÍA 2: Reservas", categoria_reserva)
mostrar_categoria("CATEGORÍA 3: Horario y ubicación", categoria_horario_ubicacion)
mostrar_categoria("CATEGORÍA 4: Carta, alérgenos y servicios", categoria_carta_alergenos_servicios)
mostrar_categoria("CATEGORÍA 5: Otros correos de clientes", categoria_otro_cliente)

mail.close()
mail.logout()