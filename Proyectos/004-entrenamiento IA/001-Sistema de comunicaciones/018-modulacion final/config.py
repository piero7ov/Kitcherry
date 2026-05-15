import os
import json

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
MAX_CORREOS_CARGA = int(os.environ.get("MAX_CORREOS_CARGA", "200"))
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
    r"no-?reply",
    r"noreply",
    r"do-?not-?reply",
    r"mailer-daemon",
    r"postmaster",
    r"newsletter",
    r"notifications?",
    r"alertas?",
    r"billing",
    r"invoice",
    r"receipt",
    r"promotions?",
    r"marketing",
    r"mailing",
    r"comunicaciones",
    r"bolet[ií]n"
]

AUTO_SUBJECT_PATTERNS = [
    r"unsubscribe|darse de baja|baja",
    r"oferta|promoci[oó]n|descuento|rebaja|saldo|tarifa",
    r"newsletter|bolet[ií]n|resumen semanal",
    r"alerta|notificaci[oó]n|aviso autom[aá]tico",
]

PERSONAL_SUBJECT_HINTS = [
    r"^re:",
    r"^fw:",
    r"^fwd:",
    r"urgente|importante|por favor|consulta|pregunta|reserva|al[eé]rgeno|horario|mesa",
]

PERSONAL_BODY_HINTS = [
    r"un saludo",
    r"gracias",
    r"por favor",
    r"cuando puedas",
    r"necesito",
    r"podemos",
    r"quer[ií]a",
    r"quisiera",
    r"reservar",
    r"mesa",
    r"personas",
    r"horario",
    r"carta",
    r"men[uú]",
    r"al[eé]rgeno",
    r"gluten",
    r"lactosa",
    r"direcci[oó]n",
    r"ubicaci[oó]n",
    r"terraza",
    r"evento",
    r"confirmarme",
    r"confirmar",
    r"disponibilidad"
]

HOSTELERIA_USEFUL_HINTS = [
    r"reserva",
    r"reservar",
    r"mesa",
    r"personas",
    r"horario",
    r"carta",
    r"men[uú]",
    r"al[eé]rgeno",
    r"al[eé]rgenos",
    r"gluten",
    r"lactosa",
    r"direcci[oó]n",
    r"ubicaci[oó]n",
    r"terraza",
    r"evento",
    r"celebraci[oó]n",
    r"disponibilidad",
    r"cancelar reserva",
    r"modificar reserva",
    r"grupo",
    r"cumplea[nñ]os",
    r"vegetariano",
    r"vegano",
    r"wok"
]

ALERGENOS_KNOWN = [
    "gluten",
    "lactosa",
    "frutos secos",
    "cacahuete",
    "cacahuetes",
    "marisco",
    "huevo",
    "huevos",
    "soja",
    "mostaza",
    "apio",
    "sésamo",
    "sesamo",
    "pescado",
    "moluscos",
    "crustáceos",
    "crustaceos"
]

RESTRICCIONES_KNOWN = [
    "vegano",
    "vegana",
    "vegetariano",
    "vegetariana",
    "celiaco",
    "celíaco",
    "intolerancia",
    "sin gluten",
    "sin lactosa"
]

SERVICIOS_KNOWN = [
    "terraza",
    "delivery",
    "take away",
    "para llevar",
    "evento",
    "eventos",
    "cumpleaños",
    "celebración",
    "grupo",
    "grupos",
    "menú concertado",
    "menús concertados",
    "menú cerrado",
    "menús cerrados",
    "fianza"
]
