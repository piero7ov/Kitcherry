# ==========================================================
# KITCHERRY WEB ANALYTICS
# Archivo: config.py
# Configuración general del proyecto
# ==========================================================

import os

# Carpeta raíz del proyecto.
BASE_DIR = os.path.dirname(os.path.abspath(__file__))

# Base de datos SQLite.
DB_FILE = os.path.join(BASE_DIR, "kitcherry_analytics.sqlite")

# Carpetas del proyecto.
LOGS_DIR = os.path.join(BASE_DIR, "logs")
GRAFICAS_DIR = os.path.join(BASE_DIR, "graficas")
INFORMES_DIR = os.path.join(BASE_DIR, "informes")

# ==========================================================
# CONFIGURACIÓN DE URL
# ==========================================================
# Ahora se usa la URL local.
# Cuando la web esté publicada, cambia USAR_URL_REAL a True
# y escribe la URL real en BASE_URL_REAL.

USAR_URL_REAL = False

BASE_URL_LOCAL = "http://localhost/DAMPieroOlivares/Primero/Proyecto%20intermodular/203-Proyectos%20de%20Kitcherry/017-web-Kitcherry-actualizada"

BASE_URL_REAL = "https://tudominio.com/"

BASE_URL = BASE_URL_REAL if USAR_URL_REAL else BASE_URL_LOCAL

# ==========================================================
# CONFIGURACIÓN DE LOGS
# ==========================================================
# Ahora se usan logs ficticios/demo.
# Cuando tengas logs reales del servidor, cambia USAR_LOG_REAL a True
# y coloca el archivo real en logs/access_real.log.

USAR_LOG_REAL = False

LOG_FILE_DEMO = os.path.join(LOGS_DIR, "access_demo.log")
LOG_FILE_REAL = os.path.join(LOGS_DIR, "access_real.log")

LOG_FILE = LOG_FILE_REAL if USAR_LOG_REAL else LOG_FILE_DEMO

# ==========================================================
# CONFIGURACIÓN DEL MINIBOT
# ==========================================================

MAX_PAGINAS_MINIBOT = 30

EXTENSIONES_ESTATICAS = (
    ".css",
    ".js",
    ".png",
    ".jpg",
    ".jpeg",
    ".gif",
    ".svg",
    ".webp",
    ".ico",
    ".woff",
    ".woff2",
    ".ttf",
    ".otf",
    ".mp4",
    ".mp3",
    ".pdf",
    ".zip",
)

ARCHIVOS_TECNICOS_IGNORADOS = (
    "api_chat.php",
)

# ==========================================================
# CONFIGURACIÓN DEL FORMULARIO
# ==========================================================
# Se usa para detectar formularios enviados en los logs.
# En tu caso el formulario está en la página principal/index.

FORMULARIO_KEYWORDS = (
    "contacto",
    "index.php",
)

# ==========================================================
# CREACIÓN DE CARPETAS
# ==========================================================

os.makedirs(LOGS_DIR, exist_ok=True)
os.makedirs(GRAFICAS_DIR, exist_ok=True)
os.makedirs(INFORMES_DIR, exist_ok=True)