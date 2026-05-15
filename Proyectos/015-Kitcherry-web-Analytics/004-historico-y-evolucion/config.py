# ==========================================================
# KITCHERRY WEB ANALYTICS
# Archivo: config.py
# Configuración principal del proyecto
# ==========================================================

from urllib.parse import urlparse, unquote

# URL local actual de la web corporativa de Kitcherry.
# Cuando la web esté publicada, solo tendrás que cambiar esta URL.
BASE_URL = "http://localhost/DAMPieroOlivares/Primero/Proyecto%20intermodular/203-Proyectos%20de%20Kitcherry/006-redes%20sociales/"

# Archivo de base de datos SQLite donde se guardarán las visitas y la revisión del minibot.
DB_FILE = "kitcherry_analytics.sqlite"

# Archivo de log simulado.
LOG_FILE = "logs/access_demo.log"

# Carpeta donde se guardarán las gráficas generadas con matplotlib.
GRAFICAS_DIR = "graficas"

# Carpeta donde se guardará el informe HTML final.
INFORMES_DIR = "informes"

# Número máximo de páginas que revisará el minibot para no hacer un rastreo infinito.
MAX_PAGINAS_MINIBOT = 30

# Extensiones que el analizador de logs y el minibot deben ignorar como páginas normales.
EXTENSIONES_ESTATICAS = (
    ".css", ".js", ".png", ".jpg", ".jpeg", ".gif", ".webp", ".svg",
    ".ico", ".mp4", ".mp3", ".pdf", ".zip", ".otf", ".ttf", ".woff", ".woff2"
)

# Archivos técnicos que el minibot no debería rastrear como páginas normales.
ARCHIVOS_TECNICOS_IGNORADOS = (
    "api_chat.php",
)

# Palabras clave para detectar envíos de formulario dentro del log.
# En tu web actual, el formulario está en index.php y puede aparecer como POST a / o /index.php.
FORM_PATH_KEYWORDS = (
    "/",
    "/index.php",
    "/contacto.php",
)


def obtener_base_path() -> str:
    """Devuelve la ruta base de la URL local para limpiar las rutas del log."""
    parsed = urlparse(BASE_URL)
    path = parsed.path
    if not path.endswith("/"):
        path += "/"
    return path


def limpiar_pagina_desde_path(path: str) -> str:
    """
    Convierte una ruta larga del log en una ruta corta más fácil de leer.

    Ejemplo:
    /DAMPieroOlivares/.../006-redes%20sociales/index.php  ->  /index.php
    /DAMPieroOlivares/.../006-redes%20sociales/           ->  /
    """
    base_path = obtener_base_path()

    if path.startswith(base_path):
        limpia = path[len(base_path):]
    else:
        limpia = path.lstrip("/")

    limpia = unquote(limpia)
    limpia = "/" + limpia.lstrip("/")

    if limpia == "/":
        return "/"

    # Quitamos parámetros de URL si apareciesen.
    limpia = limpia.split("?")[0]
    return limpia
