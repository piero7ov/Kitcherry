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


def si_no(valor):
    return "Sí" if valor else "No"


def tabla_html(cabeceras, filas):
    if not filas:
        return "<p class='empty'>No hay datos disponibles.</p>"

    thead = "".join(f"<th>{html.escape(str(c))}</th>" for c in cabeceras)
    body = ""

    for fila in filas:
        body += "<tr>"

        for celda in fila:
            valor = celda if celda is not None else ""
            body += f"<td>{html.escape(str(valor))}</td>"

        body += "</tr>"

    return f"""
    <div class='table-wrap'>
        <table>
            <thead>
                <tr>{thead}</tr>
            </thead>
            <tbody>
                {body}
            </tbody>
        </table>
    </div>
    """


def imagen_si_existe(nombre, alt):
    ruta = os.path.join(GRAFICAS_DIR, nombre)
    titulo = html.escape(alt)
    nombre_seguro = html.escape(nombre)

    if os.path.exists(ruta):
        return f"""
        <article class='chart-card'>
            <button
                class='chart-open'
                type='button'
                data-src='../graficas/{nombre_seguro}'
                data-title='{titulo}'
                aria-label='Abrir gráfica: {titulo}'
            >
                <img src='../graficas/{nombre_seguro}' alt='{titulo}'>
                <span class='chart-action'>Ver en grande</span>
            </button>
        </article>
        """

    return f"<p class='empty'>Gráfica no disponible: {nombre_seguro}</p>"


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

    paginas_correctas = valor_unico("SELECT COUNT(*) FROM revision_minibot WHERE estado_tecnico = 'Correcto'")
    paginas_mejorables = valor_unico("SELECT COUNT(*) FROM revision_minibot WHERE estado_tecnico = 'Mejorable'")
    paginas_a_revisar = valor_unico("SELECT COUNT(*) FROM revision_minibot WHERE estado_tecnico = 'Revisar'")
    enlaces_rotos = valor_unico("SELECT COUNT(*) FROM enlaces_minibot WHERE es_roto = 1")

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

    revision_minibot_raw = consultar("""
        SELECT
            url,
            codigo_estado,
            tiempo_respuesta,
            tiene_title,
            tiene_meta_description,
            tiene_h1,
            tiene_lang,
            enlaces_rotos,
            estado_tecnico,
            COALESCE(error, '')
        FROM revision_minibot
        ORDER BY id ASC
        LIMIT 30
    """)

    revision_minibot = []

    for fila in revision_minibot_raw:
        url = fila[0]
        codigo = fila[1]
        tiempo = "" if fila[2] is None else f"{fila[2]} s"
        title = si_no(fila[3])
        description = si_no(fila[4])
        h1 = si_no(fila[5])
        lang = si_no(fila[6])
        rotos = fila[7]
        estado = fila[8]
        error = fila[9]

        revision_minibot.append((
            url,
            codigo,
            tiempo,
            title,
            description,
            h1,
            lang,
            rotos,
            estado,
            error
        ))

    enlaces_rotos_tabla = consultar("""
        SELECT
            origen,
            destino,
            codigo_estado,
            observacion
        FROM enlaces_minibot
        WHERE es_roto = 1
        ORDER BY id ASC
        LIMIT 20
    """)

    historial_ejecuciones = consultar("""
        SELECT
            id,
            fecha_ejecucion,
            total_visitas,
            total_formularios,
            total_errores,
            paginas_revisadas,
            enlaces_detectados
        FROM ejecuciones_analisis
        ORDER BY id DESC
        LIMIT 10
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
                    <h1 class="brand-name">
                        <span class="brand-kit">KIT</span><span class="brand-cherry">CHERRY</span>
                        <span class="brand-analytics">Web Analytics</span>
                    </h1>

                    <p class="subtitle">Informe de seguimiento de actividad y revisión técnica de la web corporativa.</p>
                </div>
            </div>
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
            <strong>Interpretación:</strong> este informe combina dos tipos de seguimiento. Por una parte, analiza logs simulados para medir actividad de usuarios, como visitas, páginas más consultadas, formularios enviados y errores. Por otra parte, utiliza un minibot para revisar técnicamente la web, detectando enlaces rotos, elementos SEO básicos y el estado general de cada página.

            <span class="note-url">Web analizada: {html.escape(BASE_URL)}</span>
        </section>

        <section>
            <div class="section-heading">
                <div>
                    <h2>Semáforo técnico del minibot</h2>
                    <p>Resumen del estado técnico de la web según la revisión automática.</p>
                </div>
            </div>

            <div class="status-grid">
                <article class="status-card status-ok">
                    <span class="status-label">Correcto</span>
                    <strong>{paginas_correctas}</strong>
                    <p>Páginas que cargan bien y tienen los elementos básicos.</p>
                </article>

                <article class="status-card status-warning">
                    <span class="status-label">Mejorable</span>
                    <strong>{paginas_mejorables}</strong>
                    <p>Páginas que cargan, pero necesitan ajustar algún elemento.</p>
                </article>

                <article class="status-card status-error">
                    <span class="status-label">Revisar</span>
                    <strong>{paginas_a_revisar}</strong>
                    <p>Páginas con errores, enlaces rotos o fallos importantes.</p>
                </article>

                <article class="status-card status-links">
                    <span class="status-label">Enlaces rotos</span>
                    <strong>{enlaces_rotos}</strong>
                    <p>Enlaces internos que no responden correctamente.</p>
                </article>
            </div>
        </section>

        <section>
            <div class="section-heading">
                <div>
                    <h2>Historial de seguimiento</h2>
                    <p>Registro de las últimas ejecuciones para comparar la evolución del análisis.</p>
                </div>
            </div>

            <div class="chart-grid chart-grid-single">
                {imagen_si_existe('historial_ejecuciones.png', 'Historial de seguimiento por ejecución')}
            </div>

            {tabla_html(
                ['Ejecución', 'Fecha', 'Visitas', 'Formularios', 'Errores', 'Páginas revisadas', 'Enlaces detectados'],
                historial_ejecuciones
            )}
        </section>

        <section>
            <div class="section-heading">
                <div>
                    <h2>Gráficas de actividad</h2>
                    <p>Haz clic sobre cualquier gráfica para verla ampliada en una ventana modal.</p>
                </div>
            </div>

            <div class="chart-grid">
                {imagen_si_existe('distribucion_actividad_tarta.png', 'Distribución de actividad en gráfica de tarta')}
                {imagen_si_existe('paginas_mas_visitadas.png', 'Páginas más visitadas')}
                {imagen_si_existe('visitas_por_dia.png', 'Visitas por día')}
                {imagen_si_existe('formularios_por_dia.png', 'Formularios por día')}
                {imagen_si_existe('errores_http.png', 'Errores HTTP')}
                {imagen_si_existe('actividad_por_hora.png', 'Actividad por hora')}
                {imagen_si_existe('revision_minibot.png', 'Semáforo técnico del minibot')}
                {imagen_si_existe('seo_basico_minibot.png', 'SEO básico detectado por el minibot')}
                {imagen_si_existe('enlaces_rotos_minibot.png', 'Estado de enlaces internos')}
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
            {tabla_html(
                ['URL', 'Código', 'Tiempo', 'Title', 'Description', 'H1', 'Lang ES', 'Enlaces rotos', 'Estado', 'Aviso/Error'],
                revision_minibot
            )}
        </section>

        <section>
            <h2>Enlaces rotos detectados</h2>
            {tabla_html(['Origen', 'Destino', 'Código', 'Observación'], enlaces_rotos_tabla)}
        </section>
    </main>

    <div class="modal" id="chartModal" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>

        <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="modalChartTitle">
            <button class="modal-close" type="button" data-close-modal aria-label="Cerrar gráfica">×</button>
            <h2 id="modalChartTitle">Gráfica</h2>
            <img id="modalChartImg" src="" alt="">
        </div>
    </div>

    <footer class="container">
        Informe generado el {fecha}.
    </footer>

    <script src="../assets/js/modal-graficas.js"></script>
</body>
</html>"""

    ruta_informe = os.path.join(INFORMES_DIR, "informe_analytics.html")

    with open(ruta_informe, "w", encoding="utf-8") as archivo:
        archivo.write(contenido)

    print("Informe generado correctamente:", ruta_informe)


if __name__ == "__main__":
    generar_informe()