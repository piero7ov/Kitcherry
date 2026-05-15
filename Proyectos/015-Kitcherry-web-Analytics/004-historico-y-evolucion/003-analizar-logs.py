# ==========================================================
# KITCHERRY WEB ANALYTICS
# Archivo: 003-analizar-logs.py
# Lee el access_demo.log, lo analiza y guarda los datos en SQLite
# ==========================================================

import os
import re
import sqlite3
from collections import Counter
from datetime import datetime

from config import (
    DB_FILE,
    LOG_FILE,
    EXTENSIONES_ESTATICAS,
    FORM_PATH_KEYWORDS,
    limpiar_pagina_desde_path,
)

# Patrón para leer líneas tipo Apache Combined Log.
PATRON_LOG = re.compile(
    r'(?P<ip>\S+) \S+ \S+ \[(?P<fecha>[^\]]+)\] '
    r'"(?P<metodo>\S+) (?P<ruta>\S+) (?P<protocolo>[^"]+)" '
    r'(?P<codigo>\d{3})'
)


def convertir_fecha(fecha_apache: str) -> str:
    """Convierte una fecha Apache a formato ISO para trabajar mejor con SQLite."""
    try:
        fecha = datetime.strptime(fecha_apache, "%d/%b/%Y:%H:%M:%S %z")
        return fecha.isoformat()
    except ValueError:
        return fecha_apache


def es_archivo_estatico(pagina: str) -> bool:
    return pagina.lower().endswith(EXTENSIONES_ESTATICAS)


def es_envio_formulario(metodo: str, pagina_limpia: str) -> int:
    if metodo.upper() != "POST":
        return 0

    if pagina_limpia in FORM_PATH_KEYWORDS:
        return 1

    if "contacto" in pagina_limpia.lower():
        return 1

    return 0


def preparar_bbdd():
    # Importamos y ejecutamos la creación de tablas para evitar errores si el usuario no ejecutó el paso 001.
    import importlib.util

    spec = importlib.util.spec_from_file_location("crear_bbdd", "001-crear-bbdd.py")
    modulo = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(modulo)
    modulo.crear_bbdd()


def analizar_logs():
    if not os.path.exists(LOG_FILE):
        print("No existe el archivo de log:", LOG_FILE)
        print("Ejecuta primero: python 002-generar-log-demo.py")
        return

    preparar_bbdd()

    conexion = sqlite3.connect(DB_FILE)
    cursor = conexion.cursor()

    # Limpiamos visitas anteriores para que cada análisis sea claro y no duplique datos.
    cursor.execute("DELETE FROM visitas_web")

    total_lineas = 0
    total_guardadas = 0
    paginas = Counter()
    errores = Counter()
    formularios = 0

    with open(LOG_FILE, "r", encoding="utf-8") as archivo:
        for linea in archivo:
            total_lineas += 1
            linea = linea.strip()
            if not linea:
                continue

            coincidencia = PATRON_LOG.search(linea)
            if not coincidencia:
                continue

            ip = coincidencia.group("ip")
            fecha_iso = convertir_fecha(coincidencia.group("fecha"))
            metodo = coincidencia.group("metodo")
            pagina = coincidencia.group("ruta").split("?")[0]
            protocolo = coincidencia.group("protocolo")
            codigo_estado = int(coincidencia.group("codigo"))
            pagina_limpia = limpiar_pagina_desde_path(pagina)

            # No contamos archivos estáticos como si fueran páginas normales.
            if es_archivo_estatico(pagina_limpia):
                continue

            es_error = 1 if codigo_estado >= 400 else 0
            es_formulario = es_envio_formulario(metodo, pagina_limpia)

            cursor.execute("""
                INSERT INTO visitas_web
                (fecha_iso, ip, metodo, pagina, pagina_limpia, protocolo, codigo_estado, es_error, es_formulario)
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
                es_formulario,
            ))

            total_guardadas += 1
            paginas[pagina_limpia] += 1

            if es_error:
                errores[codigo_estado] += 1

            if es_formulario:
                formularios += 1

    conexion.commit()
    conexion.close()

    print("\n====================================")
    print("KITCHERRY WEB ANALYTICS - LOGS")
    print("====================================")
    print("Líneas leídas:", total_lineas)
    print("Visitas guardadas:", total_guardadas)
    print("Formularios enviados:", formularios)
    print("Errores detectados:", sum(errores.values()))

    print("\nPáginas más visitadas:")
    for pagina, cantidad in paginas.most_common(10):
        print(f"- {pagina}: {cantidad}")

    print("\nErrores por código:")
    if errores:
        for codigo, cantidad in errores.items():
            print(f"- {codigo}: {cantidad}")
    else:
        print("- No se han detectado errores")


if __name__ == "__main__":
    analizar_logs()
