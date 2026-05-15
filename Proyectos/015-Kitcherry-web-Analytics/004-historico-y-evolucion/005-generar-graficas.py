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


# Paleta visual basada en la identidad de Kitcherry.
COLOR_ROJO = "#C2182B"
COLOR_NARANJA = "#F97316"
COLOR_AMARILLO = "#FFB703"
COLOR_NEGRO = "#171717"
COLOR_GRIS = "#6B7280"
COLOR_ROSA = "#F43F5E"
COLOR_VERDE = "#22C55E"
COLOR_AZUL = "#2563EB"


def asegurar_carpeta():
    os.makedirs(GRAFICAS_DIR, exist_ok=True)


def consultar(query, params=()):
    conexion = sqlite3.connect(DB_FILE)
    cursor = conexion.cursor()
    cursor.execute(query, params)
    datos = cursor.fetchall()
    conexion.close()
    return datos


def ejecutar(query, params=()):
    conexion = sqlite3.connect(DB_FILE)
    cursor = conexion.cursor()
    cursor.execute(query, params)
    conexion.commit()
    conexion.close()


def valor_unico(query, defecto=0):
    datos = consultar(query)

    if not datos or datos[0][0] is None:
        return defecto

    return datos[0][0]


def guardar_figura(nombre_archivo: str):
    ruta = os.path.join(GRAFICAS_DIR, nombre_archivo)
    plt.tight_layout()
    plt.savefig(ruta, dpi=150, bbox_inches="tight")
    plt.close()
    print("Gráfica generada:", ruta)


def preparar_estilo_grafica():
    """Ajustes comunes para que las gráficas se vean más limpias."""
    plt.rcParams.update({
        "figure.facecolor": "white",
        "axes.facecolor": "white",
        "axes.edgecolor": "#D4D4D4",
        "axes.labelcolor": "#171717",
        "xtick.color": "#333333",
        "ytick.color": "#333333",
        "font.size": 10,
    })


def registrar_ejecucion_actual():
    """
    Guarda una fotografía del análisis actual.

    Esta función convierte cada ejecución del proyecto en un registro histórico.
    Así se puede comparar la evolución de visitas, formularios, errores y revisión técnica.
    """

    total_visitas = valor_unico("SELECT COUNT(*) FROM visitas_web WHERE es_error = 0")
    total_formularios = valor_unico("SELECT COUNT(*) FROM visitas_web WHERE es_formulario = 1")
    total_errores = valor_unico("SELECT COUNT(*) FROM visitas_web WHERE es_error = 1")
    paginas_revisadas = valor_unico("SELECT COUNT(*) FROM revision_minibot")
    enlaces_detectados = valor_unico("SELECT COUNT(*) FROM enlaces_minibot")

    fecha_ejecucion = datetime.now().isoformat(timespec="seconds")

    ejecutar("""
        INSERT INTO ejecuciones_analisis
        (
            fecha_ejecucion,
            total_visitas,
            total_formularios,
            total_errores,
            paginas_revisadas,
            enlaces_detectados
        )
        VALUES (?, ?, ?, ?, ?, ?)
    """, (
        fecha_ejecucion,
        total_visitas,
        total_formularios,
        total_errores,
        paginas_revisadas,
        enlaces_detectados,
    ))

    print("Ejecución registrada en el historial:", fecha_ejecucion)


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

    colores = [
        COLOR_ROJO if i == 0 else COLOR_NARANJA if i == 1 else COLOR_GRIS
        for i in range(len(paginas))
    ]

    plt.figure(figsize=(9, 5))
    plt.bar(paginas, totales, color=colores)
    plt.title("Páginas más visitadas", fontsize=16, pad=14)
    plt.xlabel("Página")
    plt.ylabel("Visitas")
    plt.xticks(rotation=35, ha="right")
    plt.grid(axis="y", alpha=0.25)
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
    plt.plot(dias, totales, marker="o", color=COLOR_ROJO, linewidth=2.5)
    plt.fill_between(dias, totales, alpha=0.12, color=COLOR_ROJO)
    plt.title("Visitas por día", fontsize=16, pad=14)
    plt.xlabel("Día")
    plt.ylabel("Visitas")
    plt.xticks(rotation=35, ha="right")
    plt.grid(axis="y", alpha=0.25)
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
    plt.bar(dias, totales, color=COLOR_NARANJA)
    plt.title("Formularios enviados por día", fontsize=16, pad=14)
    plt.xlabel("Día")
    plt.ylabel("Formularios enviados")
    plt.xticks(rotation=35, ha="right")
    plt.grid(axis="y", alpha=0.25)
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
    plt.bar(codigos, totales, color=COLOR_NEGRO)
    plt.title("Errores HTTP detectados", fontsize=16, pad=14)
    plt.xlabel("Código HTTP")
    plt.ylabel("Cantidad")
    plt.grid(axis="y", alpha=0.25)
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
    plt.bar([str(hora) for hora in horas], totales, color=COLOR_GRIS)
    plt.title("Actividad por hora", fontsize=16, pad=14)
    plt.xlabel("Hora")
    plt.ylabel("Visitas")
    plt.grid(axis="y", alpha=0.25)
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
    plt.bar(
        ["Correctas", "Con aviso/error"],
        [correctas, con_error],
        color=[COLOR_VERDE, COLOR_ROJO]
    )
    plt.title("Resultado de la revisión técnica del minibot", fontsize=16, pad=14)
    plt.xlabel("Estado")
    plt.ylabel("Páginas")
    plt.grid(axis="y", alpha=0.25)
    guardar_figura("revision_minibot.png")


def grafica_tarta_distribucion_actividad():
    """Crea una gráfica de tarta para ver el reparto general de la actividad registrada."""
    datos = consultar("""
        SELECT
            SUM(CASE WHEN es_error = 0 AND es_formulario = 0 THEN 1 ELSE 0 END) AS visitas_normales,
            SUM(CASE WHEN es_error = 0 AND es_formulario = 1 THEN 1 ELSE 0 END) AS formularios,
            SUM(CASE WHEN es_error = 1 THEN 1 ELSE 0 END) AS errores
        FROM visitas_web
    """)

    if not datos:
        return

    visitas_normales = datos[0][0] or 0
    formularios = datos[0][1] or 0
    errores = datos[0][2] or 0

    valores = [visitas_normales, formularios, errores]
    etiquetas = ["Visitas normales", "Formularios", "Errores"]

    datos_limpios = [
        (etiqueta, valor)
        for etiqueta, valor in zip(etiquetas, valores)
        if valor > 0
    ]

    if not datos_limpios:
        return

    etiquetas = [item[0] for item in datos_limpios]
    valores = [item[1] for item in datos_limpios]
    colores = [COLOR_ROJO, COLOR_NARANJA, COLOR_NEGRO][:len(valores)]

    plt.figure(figsize=(7, 6))
    plt.pie(
        valores,
        labels=etiquetas,
        autopct="%1.1f%%",
        startangle=90,
        colors=colores,
        wedgeprops={"edgecolor": "white", "linewidth": 2},
        textprops={"color": "#171717"},
    )
    plt.title("Distribución de la actividad web", fontsize=16, pad=14)
    plt.axis("equal")
    guardar_figura("distribucion_actividad_tarta.png")


def grafica_historial_ejecuciones():
    """
    Genera una gráfica de evolución a partir de las últimas ejecuciones guardadas.
    """

    datos = consultar("""
        SELECT
            id,
            fecha_ejecucion,
            total_visitas,
            total_formularios,
            total_errores,
            paginas_revisadas
        FROM ejecuciones_analisis
        ORDER BY id ASC
        LIMIT 20
    """)

    if not datos or len(datos) < 1:
        return

    etiquetas = [f"#{fila[0]}" for fila in datos]
    visitas = [fila[2] for fila in datos]
    formularios = [fila[3] for fila in datos]
    errores = [fila[4] for fila in datos]
    paginas_revisadas = [fila[5] for fila in datos]

    plt.figure(figsize=(10, 5.5))
    plt.plot(etiquetas, visitas, marker="o", linewidth=2.4, color=COLOR_ROJO, label="Visitas")
    plt.plot(etiquetas, formularios, marker="o", linewidth=2.4, color=COLOR_NARANJA, label="Formularios")
    plt.plot(etiquetas, errores, marker="o", linewidth=2.4, color=COLOR_NEGRO, label="Errores")
    plt.plot(etiquetas, paginas_revisadas, marker="o", linewidth=2.4, color=COLOR_AZUL, label="Páginas revisadas")

    plt.title("Historial de seguimiento por ejecución", fontsize=16, pad=14)
    plt.xlabel("Ejecución")
    plt.ylabel("Cantidad")
    plt.grid(axis="y", alpha=0.25)
    plt.legend()
    guardar_figura("historial_ejecuciones.png")


def generar_graficas():
    asegurar_carpeta()
    preparar_estilo_grafica()

    if not os.path.exists(DB_FILE):
        print("No existe la base de datos:", DB_FILE)
        print("Ejecuta primero: python 001-crear-bbdd.py")
        return

    # Primero se registra la ejecución actual y después se generan las gráficas,
    # para que el historial incluya también este análisis.
    registrar_ejecucion_actual()

    grafica_paginas_mas_visitadas()
    grafica_visitas_por_dia()
    grafica_formularios_por_dia()
    grafica_errores_http()
    grafica_actividad_por_hora()
    grafica_revision_minibot()
    grafica_tarta_distribucion_actividad()
    grafica_historial_ejecuciones()

    print("\nProceso de gráficas finalizado.")


if __name__ == "__main__":
    generar_graficas()