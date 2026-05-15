# ==========================================================
# KITCHERRY WEB ANALYTICS
# Archivo: 006-generar-informe.py
# Genera un informe HTML final con estadísticas y gráficas
# ==========================================================

import html
import os
import sqlite3
from datetime import datetime

from config import DB_FILE, GRAFICAS_DIR, INFORMES_DIR, BASE_URL


def consultar(query, params=()):
    conexion = sqlite3.connect(DB_FILE)
    cursor = conexion.cursor()
    cursor.execute(query, params)
    datos = cursor.fetchall()
    conexion.close()
    return datos


def valor_unico(query, defecto=0):
    datos = consultar(query)
    if not datos or datos[0][0] is None:
        return defecto
    return datos[0][0]


def tabla_html(cabeceras, filas):
    if not filas:
        return "<p class='empty'>No hay datos disponibles.</p>"

    thead = "".join(f"<th>{html.escape(str(c))}</th>" for c in cabeceras)
    body = ""

    for fila in filas:
        body += "<tr>"
        for celda in fila:
            body += f"<td>{html.escape(str(celda if celda is not None else ''))}</td>"
        body += "</tr>"

    return f"<table><thead><tr>{thead}</tr></thead><tbody>{body}</tbody></table>"


def imagen_si_existe(nombre, alt):
    ruta = os.path.join(GRAFICAS_DIR, nombre)
    if os.path.exists(ruta):
        return f"<img src='../graficas/{nombre}' alt='{html.escape(alt)}'>"
    return f"<p class='empty'>Gráfica no disponible: {html.escape(nombre)}</p>"


def generar_informe():
    os.makedirs(INFORMES_DIR, exist_ok=True)

    if not os.path.exists(DB_FILE):
        print("No existe la base de datos:", DB_FILE)
        print("Ejecuta primero los scripts anteriores.")
        return

    total_visitas = valor_unico("SELECT COUNT(*) FROM visitas_web WHERE es_error = 0")
    total_formularios = valor_unico("SELECT COUNT(*) FROM visitas_web WHERE es_formulario = 1")
    total_errores = valor_unico("SELECT COUNT(*) FROM visitas_web WHERE es_error = 1")
    paginas_revisadas = valor_unico("SELECT COUNT(*) FROM revision_minibot")
    enlaces_detectados = valor_unico("SELECT COUNT(*) FROM enlaces_minibot")

    paginas_mas_visitadas = consultar("""
        SELECT pagina_limpia, COUNT(*) AS total
        FROM visitas_web
        WHERE es_error = 0
        GROUP BY pagina_limpia
        ORDER BY total DESC
        LIMIT 10
    """)

    errores = consultar("""
        SELECT pagina_limpia, codigo_estado, COUNT(*) AS total
        FROM visitas_web
        WHERE es_error = 1
        GROUP BY pagina_limpia, codigo_estado
        ORDER BY total DESC
        LIMIT 10
    """)

    revision_minibot = consultar("""
        SELECT url, titulo, codigo_estado, num_enlaces_internos, num_enlaces_externos, correos, COALESCE(error, '')
        FROM revision_minibot
        ORDER BY id ASC
        LIMIT 20
    """)

    fecha = datetime.now().strftime("%d/%m/%Y %H:%M")

    contenido = f"""<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitcherry Web Analytics</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="brand">
                <img class="brand-logo" src="../assets/img/logo.png" alt="Logo de Kitcherry">
                <div class="brand-text">
                    <h1 class="brand-name">Kitcherry Web Analytics</h1>
                    <p class="subtitle">Informe de seguimiento de actividad y revisión técnica de la web corporativa.</p>
                </div>
            </div>
            <p class="subtitle">Web analizada: {html.escape(BASE_URL)}</p>
        </div>
    </header>

    <main class="container">
        <section class="grid">
            <div class="card">
                <p class="metric">{total_visitas}</p>
                <p class="label">Visitas registradas</p>
            </div>
            <div class="card">
                <p class="metric">{total_formularios}</p>
                <p class="label">Formularios enviados</p>
            </div>
            <div class="card">
                <p class="metric">{total_errores}</p>
                <p class="label">Errores detectados</p>
            </div>
            <div class="card">
                <p class="metric">{paginas_revisadas}</p>
                <p class="label">Páginas revisadas por el minibot</p>
            </div>
            <div class="card">
                <p class="metric">{enlaces_detectados}</p>
                <p class="label">Enlaces detectados</p>
            </div>
        </section>

        <section class="note">
            <strong>Interpretación:</strong> este informe combina dos tipos de seguimiento. Por una parte, analiza logs simulados para medir actividad de usuarios, como visitas, páginas más consultadas, formularios enviados y errores. Por otra parte, utiliza un minibot para revisar técnicamente la web, detectando páginas, títulos, correos y enlaces internos o externos.
        </section>

        <section>
            <h2>Gráficas de actividad</h2>
            <div class="chart-grid">
                {imagen_si_existe('paginas_mas_visitadas.png', 'Páginas más visitadas')}
                {imagen_si_existe('visitas_por_dia.png', 'Visitas por día')}
                {imagen_si_existe('formularios_por_dia.png', 'Formularios por día')}
                {imagen_si_existe('errores_http.png', 'Errores HTTP')}
                {imagen_si_existe('actividad_por_hora.png', 'Actividad por hora')}
                {imagen_si_existe('revision_minibot.png', 'Revisión minibot')}
            </div>
        </section>

        <section>
            <h2>Páginas más visitadas</h2>
            {tabla_html(['Página', 'Visitas'], paginas_mas_visitadas)}
        </section>

        <section>
            <h2>Errores detectados</h2>
            {tabla_html(['Página', 'Código HTTP', 'Cantidad'], errores)}
        </section>

        <section>
            <h2>Revisión técnica del minibot</h2>
            {tabla_html(['URL', 'Título', 'Código', 'Enlaces internos', 'Enlaces externos', 'Correos', 'Aviso/Error'], revision_minibot)}
        </section>
    </main>

    <footer class="container">
        Informe generado el {fecha}.
    </footer>
</body>
</html>"""

    ruta_informe = os.path.join(INFORMES_DIR, "informe_analytics.html")
    with open(ruta_informe, "w", encoding="utf-8") as archivo:
        archivo.write(contenido)

    print("Informe generado correctamente:", ruta_informe)


if __name__ == "__main__":
    generar_informe()
