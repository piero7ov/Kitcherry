# ==========================================================
# KITCHERRY WEB ANALYTICS
# Archivo: 002-generar-log-demo.py
# Genera un log simulado parecido a un access.log de Apache
# ==========================================================

import os
import random
from datetime import datetime, timedelta
from urllib.parse import urlparse

from config import BASE_URL, LOG_FILE


PAGINAS = [
    "",
    "index.php",
    "nosotros.php",
    "chatbot.php",
    "aviso-legal.php",
    "privacidad.php",
    "cookies.php",
]

PAGINAS_ERROR = [
    "pagina-no-existe.php",
    "servicio-antiguo.php",
    "assets/img/imagen-inexistente.png",
]

IPS = [
    "127.0.0.1",
    "192.168.1.12",
    "192.168.1.25",
    "192.168.1.40",
    "10.0.0.8",
]

USER_AGENTS = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X)",
    "Mozilla/5.0 (X11; Linux x86_64)",
]


def construir_ruta(relativa: str) -> str:
    """Construye una ruta tipo Apache a partir de BASE_URL y una página relativa."""
    parsed = urlparse(BASE_URL)
    base_path = parsed.path

    if not base_path.endswith("/"):
        base_path += "/"

    return base_path + relativa


def generar_linea(ip: str, fecha: datetime, metodo: str, ruta: str, codigo: int, bytes_respuesta: int) -> str:
    fecha_apache = fecha.strftime("%d/%b/%Y:%H:%M:%S +0200")
    user_agent = random.choice(USER_AGENTS)

    return (
        f'{ip} - - [{fecha_apache}] "{metodo} {ruta} HTTP/1.1" '
        f'{codigo} {bytes_respuesta} "-" "{user_agent}"'
    )


def generar_log():
    os.makedirs(os.path.dirname(LOG_FILE), exist_ok=True)

    random.seed(26)
    lineas = []
    inicio = datetime(2026, 5, 14, 9, 0, 0)

    # Visitas normales distribuidas durante varios días.
    for i in range(180):
        fecha = inicio + timedelta(
            days=random.randint(0, 6),
            hours=random.randint(0, 10),
            minutes=random.randint(0, 59),
            seconds=random.randint(0, 59),
        )

        pagina = random.choices(
            PAGINAS,
            weights=[36, 35, 18, 22, 5, 7, 5],
            k=1
        )[0]

        ruta = construir_ruta(pagina)
        ip = random.choice(IPS)
        lineas.append(generar_linea(ip, fecha, "GET", ruta, 200, random.randint(2500, 9000)))

    # Simulación de formularios enviados desde la página principal.
    for i in range(16):
        fecha = inicio + timedelta(
            days=random.randint(0, 6),
            hours=random.randint(1, 9),
            minutes=random.randint(0, 59),
            seconds=random.randint(0, 59),
        )

        ruta = construir_ruta("index.php")
        ip = random.choice(IPS)
        lineas.append(generar_linea(ip, fecha, "POST", ruta, 200, random.randint(1200, 3000)))

    # Errores 404 y 500 para demostrar el seguimiento de fallos.
    for i in range(12):
        fecha = inicio + timedelta(
            days=random.randint(0, 6),
            hours=random.randint(0, 10),
            minutes=random.randint(0, 59),
            seconds=random.randint(0, 59),
        )

        pagina_error = random.choice(PAGINAS_ERROR)
        ruta = construir_ruta(pagina_error)
        ip = random.choice(IPS)
        codigo = random.choices([404, 500], weights=[10, 2], k=1)[0]
        lineas.append(generar_linea(ip, fecha, "GET", ruta, codigo, random.randint(300, 1200)))

    lineas.sort()

    with open(LOG_FILE, "w", encoding="utf-8") as archivo:
        archivo.write("\n".join(lineas))

    print("Log demo generado correctamente:", LOG_FILE)
    print("Líneas generadas:", len(lineas))


if __name__ == "__main__":
    generar_log()
