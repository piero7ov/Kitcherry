# ==========================================================
# KITCHERRY WEB ANALYTICS
# Archivo: 003-analizar-logs.py
# Analiza logs tipo Apache y guarda visitas en SQLite
# ==========================================================

import os
import re
import sqlite3
from datetime import datetime
from urllib.parse import urlparse, unquote

from config import BASE_URL, DB_FILE, FORMULARIO_KEYWORDS, LOG_FILE


PATRON_LOG = re.compile(
    r'(?P<ip>\S+)\s+\S+\s+\S+\s+'
    r'\[(?P<fecha>[^\]]+)\]\s+'
    r'"(?P<metodo>\S+)\s+(?P<pagina>\S+)\s+(?P<protocolo>[^"]+)"\s+'
    r'(?P<codigo>\d{3})'
)


def crear_tablas_si_no_existen():
    import importlib.util

    spec = importlib.util.spec_from_file_location("crear_bbdd", "001-crear-bbdd.py")
    modulo = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(modulo)
    modulo.crear_bbdd()


def convertir_fecha_apache(fecha_texto):
    """
    Convierte una fecha de Apache:
    14/May/2026:18:20:00 +0200

    A formato ISO:
    2026-05-14T18:20:00+02:00
    """
    try:
        fecha = datetime.strptime(fecha_texto, "%d/%b/%Y:%H:%M:%S %z")
        return fecha.isoformat()
    except ValueError:
        return datetime.now().isoformat(timespec="seconds")


def obtener_base_path():
    path = urlparse(BASE_URL).path

    if not path.endswith("/"):
        path += "/"

    return path


def limpiar_pagina(pagina):
    """
    Limpia la ruta para que en el informe se vea más clara.
    Por ejemplo:
    /DAMPiero.../006-redes%20sociales/index.php -> /index.php
    """
    pagina_parseada = urlparse(pagina)
    path = unquote(pagina_parseada.path)
    base_path = unquote(obtener_base_path())

    if path.startswith(base_path):
        path = path.replace(base_path, "/", 1)

    if path == "":
        path = "/"

    if path != "/" and path.endswith("/"):
        path = path[:-1]

    return path


def detectar_formulario(metodo, pagina_limpia, pagina_original):
    if metodo.upper() != "POST":
        return 0

    ruta = (pagina_limpia + " " + pagina_original).lower()

    # Evitamos contar endpoints técnicos como formulario.
    if "api_chat.php" in ruta:
        return 0

    for palabra in FORMULARIO_KEYWORDS:
        if palabra.lower() in ruta:
            return 1

    # En este proyecto, un POST a la raíz o a index se interpreta como contacto.
    if pagina_limpia in ("/", "/index.php"):
        return 1

    return 0


def analizar_logs():
    crear_tablas_si_no_existen()

    if not os.path.exists(LOG_FILE):
        print("No existe el archivo de logs:")
        print(LOG_FILE)
        print("Si estás usando logs ficticios, ejecuta primero:")
        print("python 002-generar-log-demo.py")
        return

    conexion = sqlite3.connect(DB_FILE)
    cursor = conexion.cursor()

    # Limpiamos visitas anteriores para que el informe no duplique datos.
    # El historial de ejecuciones NO se borra.
    cursor.execute("DELETE FROM visitas_web")
    conexion.commit()

    total_lineas = 0
    total_insertadas = 0
    total_errores = 0
    total_formularios = 0

    with open(LOG_FILE, "r", encoding="utf-8", errors="ignore") as archivo:
        for linea in archivo:
            total_lineas += 1
            linea = linea.strip()

            if not linea:
                continue

            coincidencia = PATRON_LOG.search(linea)

            if not coincidencia:
                continue

            ip = coincidencia.group("ip")
            fecha_original = coincidencia.group("fecha")
            metodo = coincidencia.group("metodo")
            pagina = coincidencia.group("pagina")
            protocolo = coincidencia.group("protocolo")
            codigo_estado = int(coincidencia.group("codigo"))

            fecha_iso = convertir_fecha_apache(fecha_original)
            pagina_limpia = limpiar_pagina(pagina)

            es_error = 1 if codigo_estado >= 400 else 0
            es_formulario = detectar_formulario(metodo, pagina_limpia, pagina)

            if es_error:
                total_errores += 1

            if es_formulario:
                total_formularios += 1

            cursor.execute("""
                INSERT INTO visitas_web
                (
                    fecha_iso,
                    ip,
                    metodo,
                    pagina,
                    pagina_limpia,
                    protocolo,
                    codigo_estado,
                    es_error,
                    es_formulario
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            """, (
                fecha_iso,
                ip,
                metodo,
                pagina,
                pagina_limpia,
                protocolo,
                codigo_estado,
                es_error,
                es_formulario
            ))

            total_insertadas += 1

    conexion.commit()
    conexion.close()

    print("\n====================================")
    print("KITCHERRY WEB ANALYTICS - LOGS")
    print("====================================")
    print("Archivo analizado:", LOG_FILE)
    print("Líneas leídas:", total_lineas)
    print("Visitas insertadas:", total_insertadas)
    print("Formularios detectados:", total_formularios)
    print("Errores detectados:", total_errores)
    print("Resultados guardados en:", DB_FILE)


if __name__ == "__main__":
    analizar_logs()