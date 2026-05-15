# ==========================================================
# KITCHERRY WEB ANALYTICS
# Archivo: 002-generar-log-demo.py
# Genera un archivo de logs ficticios para pruebas
# ==========================================================

import os
import random
from datetime import datetime, timedelta
from urllib.parse import urlparse

from config import BASE_URL_LOCAL, LOG_FILE_DEMO, LOGS_DIR


IPS_DEMO = [
    "127.0.0.1",
    "192.168.1.10",
    "192.168.1.24",
    "88.14.25.10",
    "81.35.90.201",
    "46.24.18.44",
    "90.168.10.51",
]


USER_AGENTS = [
    "Mozilla/5.0 Chrome/Windows",
    "Mozilla/5.0 Firefox/Windows",
    "Mozilla/5.0 Safari/iPhone",
    "Mozilla/5.0 Edge/Windows",
]


def obtener_base_path():
    path = urlparse(BASE_URL_LOCAL).path

    if not path.endswith("/"):
        path += "/"

    return path


def formato_fecha_apache(fecha):
    return fecha.strftime("%d/%b/%Y:%H:%M:%S +0200")


def crear_linea_log(ip, fecha, metodo, ruta, codigo):
    user_agent = random.choice(USER_AGENTS)

    return (
        f'{ip} - - [{formato_fecha_apache(fecha)}] '
        f'"{metodo} {ruta} HTTP/1.1" {codigo} '
        f'1024 "-" "{user_agent}"'
    )


def generar_log_demo():
    os.makedirs(LOGS_DIR, exist_ok=True)

    base_path = obtener_base_path()

    paginas_ok = [
        base_path,
        base_path + "index.php",
        base_path + "nosotros.php",
        base_path + "chatbot.php",
        base_path + "aviso-legal.php",
        base_path + "privacidad.php",
        base_path + "cookies.php",
    ]

    paginas_error = [
        base_path + "pagina-no-existe.php",
        base_path + "servicio-antiguo.php",
        base_path + "assets/img/imagen-rota.png",
    ]

    lineas = []
    hoy = datetime.now()

    # Visitas normales repartidas durante varios días.
    for _ in range(260):
        dias_atras = random.randint(0, 6)
        hora = random.randint(9, 23)
        minuto = random.randint(0, 59)
        segundo = random.randint(0, 59)

        fecha = hoy - timedelta(days=dias_atras)
        fecha = fecha.replace(hour=hora, minute=minuto, second=segundo, microsecond=0)

        ip = random.choice(IPS_DEMO)
        ruta = random.choices(
            paginas_ok,
            weights=[35, 40, 18, 22, 5, 5, 5],
            k=1
        )[0]

        lineas.append(crear_linea_log(ip, fecha, "GET", ruta, 200))

    # Formularios enviados mediante POST.
    for _ in range(14):
        dias_atras = random.randint(0, 6)
        hora = random.randint(10, 21)
        minuto = random.randint(0, 59)

        fecha = hoy - timedelta(days=dias_atras)
        fecha = fecha.replace(hour=hora, minute=minuto, second=0, microsecond=0)

        ip = random.choice(IPS_DEMO)
        ruta = base_path + "index.php"

        lineas.append(crear_linea_log(ip, fecha, "POST", ruta, 200))

    # Errores 404 simulados.
    for _ in range(7):
        dias_atras = random.randint(0, 6)
        hora = random.randint(9, 23)
        minuto = random.randint(0, 59)

        fecha = hoy - timedelta(days=dias_atras)
        fecha = fecha.replace(hour=hora, minute=minuto, second=0, microsecond=0)

        ip = random.choice(IPS_DEMO)
        ruta = random.choice(paginas_error)

        lineas.append(crear_linea_log(ip, fecha, "GET", ruta, 404))

    # Un error 500 simulado.
    fecha = hoy.replace(hour=18, minute=45, second=0, microsecond=0)
    lineas.append(crear_linea_log("127.0.0.1", fecha, "GET", base_path + "chatbot.php", 500))

    random.shuffle(lineas)

    with open(LOG_FILE_DEMO, "w", encoding="utf-8") as archivo:
        archivo.write("\n".join(lineas))

    print("Log ficticio generado correctamente:")
    print(LOG_FILE_DEMO)
    print("Líneas generadas:", len(lineas))


if __name__ == "__main__":
    generar_log_demo()