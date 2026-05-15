import urllib.request
import json
import re
from email.utils import parseaddr

from config import (
    OLLAMA_MODEL,
    OLLAMA_URL,
    OLLAMA_OPTIONS,
    MAX_BODY_CHARS,
    AUTO_EMAIL_PATTERNS,
    AUTO_SUBJECT_PATTERNS,
    PERSONAL_SUBJECT_HINTS,
    PERSONAL_BODY_HINTS,
    ALERGENOS_KNOWN,
    RESTRICCIONES_KNOWN,
    SERVICIOS_KNOWN
)
from utils import (
    normalize_single_paragraph,
    parse_json_with_fallback,
    unique_list
)

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
# CLASIFICACIÓN
# ==========================================================
def is_kitcherry_corporate_contact(sender: str, subject: str, body: str) -> bool:
    text = f"{sender} {subject} {body}".lower()

    corporate_signals = [
        r"formulario de contacto de kitcherry",
        r"desde la web de kitcherry",
        r"nueva consulta recibida desde la web",
        r"nueva consulta desde la web de kitcherry",
        r"sistema inteligente de comunicaciones",
        r"herramientas de software",
        r"software para hosteler[ií]a",
        r"presupuesto para el sistema",
        r"informaci[oó]n y presupuesto",
        r"quiero informaci[oó]n sobre kitcherry",
        r"quisiera informaci[oó]n",
        r"servicios de kitcherry"
    ]

    operational_signals = [
        r"reserva",
        r"reservar",
        r"mesa",
        r"personas",
        r"comensales",
        r"horario",
        r"abr[ií]s",
        r"cerr[aá]is",
        r"direcci[oó]n",
        r"ubicaci[oó]n",
        r"carta",
        r"men[uú]",
        r"platos",
        r"al[eé]rgeno",
        r"al[eé]rgenos",
        r"gluten",
        r"lactosa",
        r"frutos secos"
    ]

    has_corporate = any(re.search(p, text) for p in corporate_signals)
    has_operational = any(re.search(p, text) for p in operational_signals)

    return has_corporate and not has_operational


def has_real_hosteleria_intent(subject: str, body: str) -> bool:
    text = f"{subject} {body}".lower()

    patterns = [
        r"reserva",
        r"reservar",
        r"mesa",
        r"\b\d{1,3}\s*(personas|persona|comensales|comensal|pax)\b",
        r"horario",
        r"abr[ií]s",
        r"abierto",
        r"cerr[aá]is",
        r"cierre",
        r"direcci[oó]n",
        r"ubicaci[oó]n",
        r"c[oó]mo llegar",
        r"carta",
        r"men[uú]",
        r"platos",
        r"bebidas",
        r"al[eé]rgeno",
        r"al[eé]rgenos",
        r"gluten",
        r"lactosa",
        r"frutos secos",
        r"vegetariano",
        r"vegano",
        r"terraza",
        r"delivery",
        r"para llevar",
        r"grupo",
        r"cena de empresa",
        r"comida de empresa",
        r"fianza"
    ]

    return any(re.search(p, text) for p in patterns)


def heuristic_general_classification(sender: str, subject: str, body: str):
    _, addr = parseaddr(sender or "")
    addr_l = (addr or "").lower()
    subj_l = (subject or "").lower()
    body_l = (body or "").lower()

    if is_kitcherry_corporate_contact(sender, subject, body):
        return (
            "automatico_o_publicidad",
            "Heurística: consulta corporativa o comercial dirigida a Kitcherry, no operativa hostelera del restaurante."
        )

    for p in AUTO_EMAIL_PATTERNS:
        if re.search(p, addr_l):
            return "automatico_o_publicidad", "Heurística: remitente típico de correo automático o comercial."

    for p in AUTO_SUBJECT_PATTERNS:
        if re.search(p, subj_l):
            return "automatico_o_publicidad", "Heurística: asunto típico de publicidad o notificación automática."

    if has_real_hosteleria_intent(subject, body):
        return "persona_con_motivacion", "Heurística: detectada consulta operativa relacionada con hostelería."

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

DATOS DEL CORREO:
Asunto / título del correo: {subject}
De: {sender}
Fecha: {date}
Resumen: {summary}
Contenido:
\"\"\"{body}\"\"\"

REGLAS IMPORTANTES:
- Lee primero el asunto/título del correo, porque suele indicar la intención principal.
- Después usa el contenido para confirmar o matizar esa intención.
- Si el asunto dice claramente "reserva", "consulta sobre alérgenos", "horario", "ubicación", "carta", "mesa", "grupo" o algo similar, úsalo como señal principal.
- "persona_con_motivacion": una persona escribe con una intención operativa relacionada con un negocio hostelero: reservar, modificar una reserva, preguntar por horario, ubicación, carta, platos, alérgenos, disponibilidad, servicios del local o atención al cliente del restaurante.
- "automatico_o_publicidad": no-reply, newsletters, marketing, alertas, notificaciones, recibos, mensajes automáticos, formularios corporativos, presupuestos sobre software, consultas comerciales sobre Kitcherry o mensajes que no tratan una operación real del restaurante.
- Si el correo viene de un formulario web corporativo de Kitcherry y pide información o presupuesto sobre herramientas/software, elige "automatico_o_publicidad".
- Si el correo menciona Kitcherry como empresa de software y no habla de una reserva, carta, horario, ubicación o alérgenos de un restaurante, elige "automatico_o_publicidad".
- Si dudas, elige "automatico_o_publicidad".

Ejemplos:
- Asunto: "Consulta sobre alérgenos en la carta" => persona_con_motivacion.
- Asunto: "Reserva grupo" => persona_con_motivacion.
- Asunto: "Nueva consulta desde la web de Kitcherry" y contenido sobre presupuesto de software => automatico_o_publicidad.
- Asunto: "Newsletter", "Promoción", "Factura", "Aviso automático" => automatico_o_publicidad.
""".strip()

    raw = ollama_call(prompt).strip()

    obj = parse_json_with_fallback(
        raw,
        {
            "label": "automatico_o_publicidad",
            "reason": "No se pudo parsear JSON; fallback."
        }
    )

    label = (obj.get("label") or "").strip()
    reason = normalize_single_paragraph(str(obj.get("reason", "")))

    if label not in ("automatico_o_publicidad", "persona_con_motivacion"):
        l = label.lower()
        label = "persona_con_motivacion" if ("persona" in l or "motiv" in l or l == "b") else "automatico_o_publicidad"

    reason_l = reason.lower()

    if label == "automatico_o_publicidad" and (
        "reserva" in reason_l
        or "alérgeno" in reason_l
        or "alergeno" in reason_l
        or "horario" in reason_l
        or "ubicación" in reason_l
        or "ubicacion" in reason_l
        or "carta" in reason_l
        or "mesa" in reason_l
    ):
        label = "persona_con_motivacion"
        reason += " (Corrección: el motivo sugiere una consulta operativa de hostelería.)"

    return {
        "label": label,
        "reason": reason
    }


def heuristic_hosteleria_classification(subject: str, body: str):
    text = f"{subject} {body}".lower()

    reserva_strong_patterns = [
        r"\breserva\b",
        r"\breservar\b",
        r"\bmesa\b",
        r"\bdisponibilidad\b",
        r"cancelar reserva",
        r"modificar reserva",
        r"confirmar reserva",
        r"\b\d{1,3}\s*(personas|persona|comensales|comensal|pax)\b",
        r"cena de empresa",
        r"comida de empresa",
        r"grupo",
        r"grupos",
        r"evento",
        r"celebraci[oó]n",
        r"cumplea[nñ]os",
        r"men[uú]s?\s+cerrad",
        r"men[uú]s?\s+concertad",
        r"fianza",
        r"precios?\s+aproximad",
        r"presupuesto"
    ]

    horario_patterns = [
        r"horario",
        r"abr[ií]s",
        r"abierto",
        r"cerr[aá]is",
        r"cierre",
        r"direcci[oó]n",
        r"ubicaci[oó]n",
        r"c[oó]mo llegar",
        r"d[oó]nde est[aá]is",
        r"parking",
        r"metro",
        r"parada"
    ]

    carta_alergenos_patterns = [
        r"al[eé]rgeno",
        r"al[eé]rgenos",
        r"gluten",
        r"lactosa",
        r"frutos secos",
        r"cacahuete",
        r"cacahuetes",
        r"marisco",
        r"huevo",
        r"soja",
        r"mostaza",
        r"apio",
        r"s[eé]samo",
        r"pescado",
        r"moluscos",
        r"crust[aá]ceos",
        r"vegano",
        r"vegetariano",
        r"cel[ií]aco",
        r"intolerancia",
        r"carta",
        r"men[uú]",
        r"platos",
        r"bebidas",
        r"ingredientes",
        r"wok",
        r"servicio",
        r"servicios",
        r"delivery",
        r"terraza"
    ]

    reserva_score = sum(1 for p in reserva_strong_patterns if re.search(p, text))
    horario_score = sum(1 for p in horario_patterns if re.search(p, text))
    carta_score = sum(1 for p in carta_alergenos_patterns if re.search(p, text))

    has_people = bool(re.search(r"\b\d{1,3}\s*(personas|persona|comensales|comensal|pax)\b", text))
    has_time = bool(re.search(r"\b\d{1,2}[:.]\d{2}\b|\ba las\s+\d{1,2}\b", text))
    has_date = bool(re.search(
        r"\bhoy\b|\bmañana\b|\bpasado mañana\b|\beste viernes\b|\beste sábado\b|\beste domingo\b|\b\d{1,2}[/-]\d{1,2}[/-]\d{2,4}\b|\b\d{1,2}\s+de\s+[a-záéíóúñ]+",
        text
    ))

    strong_reservation_context = has_people or (has_date and has_time)

    if carta_score > 0 and not strong_reservation_context:
        return (
            "carta_alergenos_servicios",
            "Heurística hostelera: detectada consulta principal sobre carta, alérgenos, ingredientes o servicios."
        )

    if reserva_score > 0:
        return (
            "reserva",
            "Heurística hostelera: detectada consulta de reserva, grupo, evento o disponibilidad."
        )

    if horario_score > 0:
        return (
            "horario_ubicacion",
            "Heurística hostelera: detectada consulta sobre horario, ubicación o cómo llegar."
        )

    if carta_score > 0:
        return (
            "carta_alergenos_servicios",
            "Heurística hostelera: detectada consulta sobre carta, alérgenos o servicios."
        )

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

DATOS DEL CORREO:
Asunto / título del correo: {subject}
De: {sender}
Fecha: {date}
Resumen: {summary}
Contenido:
\"\"\"{body}\"\"\"

REGLAS IMPORTANTES:
- Lee primero el asunto/título del correo. El asunto suele indicar la intención principal.
- Usa el contenido para confirmar o matizar la intención del asunto.
- Si el asunto dice "Consulta sobre alérgenos", "alérgenos en la carta", "sin gluten", "frutos secos", "platos seguros" o similar, clasifica como "carta_alergenos_servicios", aunque el cuerpo mencione "reservar".
- Si el asunto dice "Reserva", "Reserva grupo", "Mesa para X personas", "Cena de empresa", "Comida de empresa", "Disponibilidad" o similar, clasifica como "reserva".
- Si el asunto dice "Horario", "Ubicación", "Dirección", "Cómo llegar", "Apertura" o "Cierre", clasifica como "horario_ubicacion".

CRITERIOS:
- "reserva": consultas o peticiones sobre reservar, modificar, cancelar, disponibilidad de mesa, grupos, eventos, número de personas o cenas de empresa.
- Si el correo habla de reserva o grupo y además menciona menús cerrados, menús concertados, precios o fianza, sigue siendo "reserva".
- "horario_ubicacion": preguntas sobre horario, apertura, cierre, dirección, ubicación, parking, metro o cómo llegar.
- "carta_alergenos_servicios": preguntas sobre menú, carta, platos, bebidas, ingredientes, alérgenos, opciones alimentarias, intolerancias o servicios del local cuando la intención principal no es confirmar una reserva.
- "otro_cliente": correo real de un cliente que no encaja claramente en las categorías anteriores.

REGLA DE DESEMPATE:
- Si aparece "reservar" pero el asunto o el contenido principal trata sobre alérgenos, carta, ingredientes o platos seguros, elige "carta_alergenos_servicios".
- Si aparece "menú" pero el asunto o contenido habla de grupo, personas, disponibilidad, fianza o cena de empresa, elige "reserva".
- Si dudas entre "reserva" y "carta_alergenos_servicios", elige "reserva" solo si hay señales claras de reserva: personas, fecha, hora, mesa, grupo o disponibilidad.
""".strip()

    raw = ollama_call(prompt).strip()

    obj = parse_json_with_fallback(
        raw,
        {
            "label": "otro_cliente",
            "reason": "No se pudo parsear JSON; fallback."
        }
    )

    label = (obj.get("label") or "").strip()
    reason = normalize_single_paragraph(str(obj.get("reason", "")))

    if label not in ("reserva", "horario_ubicacion", "carta_alergenos_servicios", "otro_cliente"):
        l = label.lower()

        if "reserva" in l or "mesa" in l or "disponibilidad" in l or "grupo" in l:
            label = "reserva"
        elif "horario" in l or "ubic" in l or "direcci" in l or "parking" in l:
            label = "horario_ubicacion"
        elif "alergen" in l or "alérgen" in l or "carta" in l or "servicio" in l or "menu" in l or "menú" in l or "wok" in l:
            label = "carta_alergenos_servicios"
        else:
            label = "otro_cliente"

    return {
        "label": label,
        "reason": reason
    }


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
Asunto / título: {subject}
Resumen: {summary}
Contenido:
\"\"\"{body}\"\"\"

No inventes. Si falta información, usa un valor conservador.
""".strip()

    raw = ollama_call(prompt).strip()

    obj = parse_json_with_fallback(
        raw,
        {
            "priority": 5,
            "reason": "No se pudo parsear JSON; fallback a 5."
        }
    )

    try:
        priority = int(obj.get("priority", 5))
    except Exception:
        priority = 5

    priority = max(0, min(10, priority))
    reason = normalize_single_paragraph(str(obj.get("reason", ""))) or "Sin motivo proporcionado."

    return {
        "priority": priority,
        "reason": reason
    }


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
            merged[key] = unique_list(
                list(base_detected.get(key, []) or [])
                + list(extra_detected.get(key, []) or [])
            )
        else:
            merged[key] = str(extra_detected.get(key) or base_detected.get(key) or "").strip()

    return {
        "detected": sanitize_extracted_data(host_label, merged),
        "missing": unique_list(list(base.get("missing", []) or []) + list(extra.get("missing", []) or [])),
        "notes": normalize_single_paragraph(f"{base.get('notes', '')} {extra.get('notes', '')}")
    }


def llm_extract_useful_data(
    host_label: str,
    sender: str,
    subject: str,
    date: str,
    summary: str,
    body: str,
    heuristic_data: dict
):
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

DATOS DEL CORREO:
- Categoría: {host_label}
- Asunto / título: {subject}
- De: {sender}
- Fecha email: {date}
- Resumen: {summary}
- Contenido:
\"\"\"{body}\"\"\"

Extracción heurística previa:
{json.dumps(heuristic_data, ensure_ascii=False)}

Reglas:
- Lee primero el asunto/título del correo.
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
        {
            "detected": empty_extracted_data(),
            "missing": [],
            "notes": "No se pudo extraer JSON; fallback."
        }
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
- Asunto / título: {subject}
- De: {sender}
- Fecha: {date}

Contenido:
\"\"\"{body}\"\"\"

Reglas:
- Lee primero el asunto/título del correo.
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
Tu tarea es redactar un BORRADOR editable de respuesta en español.

MUY IMPORTANTE:
- Esto NO es una respuesta definitiva.
- Es un borrador para que una persona del negocio lo revise antes de enviarlo.
- No debes inventar datos del negocio.
- Si falta cualquier dato real del negocio, debes usar placeholders entre corchetes.
- Es mejor usar placeholders que inventar información.

DATOS DEL CORREO:
Asunto / título del correo: {subject}
Remitente: {sender}
Categoría: {category}
Prioridad: {priority}
Resumen: {summary}

Datos útiles detectados:
{json.dumps(extracted, ensure_ascii=False)}

Datos faltantes:
{json.dumps(missing, ensure_ascii=False)}

Contenido original del correo:
\"\"\"{original_body}\"\"\"

PLACEHOLDERS OBLIGATORIOS CUANDO FALTEN DATOS:
- Horario de apertura: [HORARIO_APERTURA]
- Horario de cierre: [HORARIO_CIERRE]
- Dirección: [DIRECCIÓN]
- Teléfono del negocio: [TELÉFONO]
- Email del negocio: [EMAIL]
- Disponibilidad de reserva: [DISPONIBILIDAD]
- Fecha de reserva no confirmada: [FECHA_RESERVA]
- Hora de reserva no confirmada: [HORA_RESERVA]
- Nombre del cliente si no está claro: [NOMBRE_CLIENTE]
- Número de personas si no está claro: [NÚMERO_PERSONAS]
- Precio o presupuesto: [PRECIO/PRESUPUESTO]
- Menú cerrado o concertado: [DETALLE_MENÚ]
- Fianza o condiciones de reserva: [CONDICIONES_RESERVA]
- Información de carta: [DETALLE_CARTA]
- Información de alérgenos: [DETALLE_ALÉRGENOS_CONFIRMADOS]
- Ingredientes o platos seguros: [PLATOS/INGREDIENTES_CONFIRMADOS]
- Política de alérgenos: [POLÍTICA_ALÉRGENOS]

REGLAS DE REDACCIÓN:
- Lee primero el asunto/título del correo para entender la intención.
- Responde según la intención principal del correo.
- Si preguntan por horario, NO inventes horas. Usa [HORARIO_APERTURA] y/o [HORARIO_CIERRE].
- Si preguntan por ubicación, NO inventes dirección. Usa [DIRECCIÓN].
- Si preguntan por disponibilidad, NO confirmes que hay mesa. Usa [DISPONIBILIDAD].
- Si preguntan por precios, menús concertados, menús cerrados o fianza, NO inventes importes ni condiciones. Usa [PRECIO/PRESUPUESTO], [DETALLE_MENÚ] y/o [CONDICIONES_RESERVA].
- Si preguntan por alérgenos, NO confirmes que un plato es seguro si no hay datos confirmados. Usa [DETALLE_ALÉRGENOS_CONFIRMADOS], [PLATOS/INGREDIENTES_CONFIRMADOS] o [POLÍTICA_ALÉRGENOS].
- Si faltan datos del cliente para tramitar una reserva, pídelos de forma educada.
- No digas frases como "queda confirmada la reserva" salvo que el correo indique claramente que ya está confirmada.
- No digas "tenemos disponibilidad" salvo que aparezca confirmada en los datos.
- No añadas información del negocio que no aparezca en el correo o en los datos detectados.
- Devuelve solo el texto del correo.
- No pongas asunto.
- No expliques que eres una IA.
- Tono: amable, profesional, claro y breve.

OBJETIVO:
Generar un borrador útil, seguro y editable, con placeholders cuando falten datos reales.
""".strip()

    return ollama_call(prompt).strip()
