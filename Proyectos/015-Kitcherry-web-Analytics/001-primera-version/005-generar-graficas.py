# ==========================================================
# KITCHERRY WEB ANALYTICS
# Archivo: 005-generar-graficas.py
# Genera gráficas PNG con matplotlib a partir de SQLite
# ==========================================================

import os
import sqlite3
from collections import Counter
from datetime import datetime

import matplotlib.pyplot as plt

from config import DB_FILE, GRAFICAS_DIR


def asegurar_carpeta():
    os.makedirs(GRAFICAS_DIR, exist_ok=True)


def consultar(query, params=()):
    conexion = sqlite3.connect(DB_FILE)
    cursor = conexion.cursor()
    cursor.execute(query, params)
    datos = cursor.fetchall()
    conexion.close()
    return datos


def guardar_figura(nombre_archivo: str):
    ruta = os.path.join(GRAFICAS_DIR, nombre_archivo)
    plt.tight_layout()
    plt.savefig(ruta, dpi=140)
    plt.close()
    print("Gráfica generada:", ruta)


def grafica_paginas_mas_visitadas():
    datos = consultar("""
        SELECT pagina_limpia, COUNT(*) AS total
        FROM visitas_web
        WHERE es_error = 0
        GROUP BY pagina_limpia
        ORDER BY total DESC
        LIMIT 8
    """)

    if not datos:
        return

    paginas = [fila[0] for fila in datos]
    totales = [fila[1] for fila in datos]

    plt.figure(figsize=(9, 5))
    plt.bar(paginas, totales)
    plt.title("Páginas más visitadas")
    plt.xlabel("Página")
    plt.ylabel("Visitas")
    plt.xticks(rotation=35, ha="right")
    guardar_figura("paginas_mas_visitadas.png")


def grafica_visitas_por_dia():
    datos = consultar("""
        SELECT substr(fecha_iso, 1, 10) AS dia, COUNT(*) AS total
        FROM visitas_web
        WHERE es_error = 0
        GROUP BY dia
        ORDER BY dia ASC
    """)

    if not datos:
        return

    dias = [fila[0] for fila in datos]
    totales = [fila[1] for fila in datos]

    plt.figure(figsize=(9, 5))
    plt.plot(dias, totales, marker="o")
    plt.title("Visitas por día")
    plt.xlabel("Día")
    plt.ylabel("Visitas")
    plt.xticks(rotation=35, ha="right")
    guardar_figura("visitas_por_dia.png")


def grafica_formularios_por_dia():
    datos = consultar("""
        SELECT substr(fecha_iso, 1, 10) AS dia, COUNT(*) AS total
        FROM visitas_web
        WHERE es_formulario = 1
        GROUP BY dia
        ORDER BY dia ASC
    """)

    if not datos:
        return

    dias = [fila[0] for fila in datos]
    totales = [fila[1] for fila in datos]

    plt.figure(figsize=(9, 5))
    plt.bar(dias, totales)
    plt.title("Formularios enviados por día")
    plt.xlabel("Día")
    plt.ylabel("Formularios enviados")
    plt.xticks(rotation=35, ha="right")
    guardar_figura("formularios_por_dia.png")


def grafica_errores_http():
    datos = consultar("""
        SELECT codigo_estado, COUNT(*) AS total
        FROM visitas_web
        WHERE es_error = 1
        GROUP BY codigo_estado
        ORDER BY codigo_estado ASC
    """)

    if not datos:
        return

    codigos = [str(fila[0]) for fila in datos]
    totales = [fila[1] for fila in datos]

    plt.figure(figsize=(7, 5))
    plt.bar(codigos, totales)
    plt.title("Errores HTTP detectados")
    plt.xlabel("Código HTTP")
    plt.ylabel("Cantidad")
    guardar_figura("errores_http.png")


def grafica_actividad_por_hora():
    datos = consultar("""
        SELECT fecha_iso
        FROM visitas_web
        WHERE es_error = 0
    """)

    if not datos:
        return

    contador = Counter()

    for (fecha_iso,) in datos:
        try:
            hora = datetime.fromisoformat(fecha_iso).hour
            contador[hora] += 1
        except ValueError:
            pass

    horas = list(range(24))
    totales = [contador.get(hora, 0) for hora in horas]

    plt.figure(figsize=(9, 5))
    plt.bar([str(hora) for hora in horas], totales)
    plt.title("Actividad por hora")
    plt.xlabel("Hora")
    plt.ylabel("Visitas")
    guardar_figura("actividad_por_hora.png")


def grafica_revision_minibot():
    datos = consultar("""
        SELECT
            SUM(CASE WHEN error IS NULL OR error = '' THEN 1 ELSE 0 END) AS correctas,
            SUM(CASE WHEN error IS NOT NULL AND error <> '' THEN 1 ELSE 0 END) AS con_error
        FROM revision_minibot
    """)

    if not datos or datos[0][0] is None:
        return

    correctas = datos[0][0] or 0
    con_error = datos[0][1] or 0

    plt.figure(figsize=(7, 5))
    plt.bar(["Correctas", "Con aviso/error"], [correctas, con_error])
    plt.title("Resultado de la revisión técnica del minibot")
    plt.xlabel("Estado")
    plt.ylabel("Páginas")
    guardar_figura("revision_minibot.png")


def generar_graficas():
    asegurar_carpeta()

    if not os.path.exists(DB_FILE):
        print("No existe la base de datos:", DB_FILE)
        print("Ejecuta primero: python 001-crear-bbdd.py")
        return

    grafica_paginas_mas_visitadas()
    grafica_visitas_por_dia()
    grafica_formularios_por_dia()
    grafica_errores_http()
    grafica_actividad_por_hora()
    grafica_revision_minibot()

    print("\nProceso de gráficas finalizado.")


if __name__ == "__main__":
    generar_graficas()
