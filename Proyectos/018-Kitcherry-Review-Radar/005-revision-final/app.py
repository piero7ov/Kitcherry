# ==========================================================
# KITCHERRY REVIEW RADAR
# 005 - Filtros + edición de respuesta + notas + revisión
# ==========================================================

from pathlib import Path
from datetime import datetime, date
import json
import sqlite3
import csv
import io

from flask import Flask, render_template, request, redirect, url_for, flash, send_file

from ia.ia_analista import analizar_resena
from ia.ia_redactora import generar_respuesta_y_mejoras


# ==========================================================
# CONFIGURACIÓN GENERAL
# ==========================================================

BASE_DIR = Path(__file__).resolve().parent
DATA_DIR = BASE_DIR / "data"
DB_FILE = DATA_DIR / "reviews.db"
CSV_EJEMPLO_FILE = DATA_DIR / "ejemplo_importacion_resenas.csv"

app = Flask(__name__)
app.secret_key = "kitcherry-review-radar"


# ==========================================================
# BASE DE DATOS
# ==========================================================

def conectar_db():
    """
    Crea una conexión SQLite.
    row_factory permite acceder a los campos por nombre.
    """
    DATA_DIR.mkdir(parents=True, exist_ok=True)

    conexion = sqlite3.connect(DB_FILE)
    conexion.row_factory = sqlite3.Row

    return conexion


def columna_existe(conexion, tabla, columna):
    """
    Comprueba si una columna existe en una tabla SQLite.
    """
    columnas = conexion.execute(f"PRAGMA table_info({tabla})").fetchall()

    for item in columnas:
        if item["name"] == columna:
            return True

    return False


def asegurar_columnas_revision(conexion):
    """
    Añade columnas nuevas de la versión 005 si la base de datos
    viene de una versión anterior.
    """
    columnas_nuevas = {
        "respuesta_final": "TEXT",
        "nota_interna": "TEXT",
        "estado_revision": "TEXT",
        "fecha_revision": "TEXT"
    }

    for columna, tipo in columnas_nuevas.items():
        if not columna_existe(conexion, "analysis", columna):
            conexion.execute(f"ALTER TABLE analysis ADD COLUMN {columna} {tipo}")

    conexion.execute("""
        UPDATE analysis
        SET estado_revision = 'analizada'
        WHERE estado_revision IS NULL OR estado_revision = ''
    """)

    conexion.execute("""
        UPDATE analysis
        SET respuesta_final = respuesta_sugerida
        WHERE respuesta_final IS NULL OR respuesta_final = ''
    """)

    conexion.execute("""
        UPDATE analysis
        SET nota_interna = ''
        WHERE nota_interna IS NULL
    """)


def iniciar_db():
    """
    Crea las tablas necesarias y añade reseñas de ejemplo
    si la base de datos está vacía.
    """
    DATA_DIR.mkdir(parents=True, exist_ok=True)
    crear_csv_ejemplo_si_no_existe()

    conexion = conectar_db()

    conexion.execute("""
        CREATE TABLE IF NOT EXISTS reviews (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            autor TEXT NOT NULL,
            puntuacion INTEGER NOT NULL,
            origen TEXT NOT NULL,
            fecha TEXT NOT NULL,
            texto TEXT NOT NULL,
            creado_en TEXT NOT NULL
        )
    """)

    conexion.execute("""
        CREATE TABLE IF NOT EXISTS analysis (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            review_id INTEGER NOT NULL UNIQUE,
            fecha_analisis TEXT NOT NULL,

            sentimiento TEXT NOT NULL,
            puntuacion_estimada INTEGER NOT NULL,
            area_afectada TEXT NOT NULL,
            problema_principal TEXT NOT NULL,
            gravedad TEXT NOT NULL,
            resumen TEXT NOT NULL,

            respuesta_sugerida TEXT NOT NULL,
            acciones_internas TEXT NOT NULL,
            tono TEXT NOT NULL,

            respuesta_final TEXT,
            nota_interna TEXT,
            estado_revision TEXT,
            fecha_revision TEXT,

            FOREIGN KEY (review_id) REFERENCES reviews(id)
        )
    """)

    asegurar_columnas_revision(conexion)

    total = conexion.execute("SELECT COUNT(*) AS total FROM reviews").fetchone()["total"]

    if total == 0:
        insertar_resenas_iniciales(conexion)

    conexion.commit()
    conexion.close()


def insertar_resenas_iniciales(conexion):
    """
    Inserta reseñas iniciales para que el proyecto no arranque vacío.
    """
    resenas = [
        {
            "autor": "Cliente 1",
            "puntuacion": 5,
            "origen": "Google simulado",
            "fecha": "2026-05-16",
            "texto": "La comida estaba excelente, el trato fue muy amable y el ambiente del restaurante era muy agradable. Volveremos seguro."
        },
        {
            "autor": "Cliente 2",
            "puntuacion": 3,
            "origen": "Google simulado",
            "fecha": "2026-05-16",
            "texto": "La comida estaba buena, pero tardaron mucho en atendernos y nadie nos explicó bien qué platos eran sin gluten."
        },
        {
            "autor": "Cliente 3",
            "puntuacion": 2,
            "origen": "TripAdvisor simulado",
            "fecha": "2026-05-15",
            "texto": "La reserva no aparecía cuando llegamos y tuvimos que esperar bastante. El personal intentó solucionarlo, pero fue una mala experiencia."
        },
        {
            "autor": "Cliente 4",
            "puntuacion": 4,
            "origen": "Formulario interno",
            "fecha": "2026-05-14",
            "texto": "Buen menú del día y precio correcto. El servicio fue rápido, aunque la mesa estaba un poco pegada a la entrada."
        },
        {
            "autor": "Cliente 5",
            "puntuacion": 1,
            "origen": "Google simulado",
            "fecha": "2026-05-13",
            "texto": "El plato llegó frío y tardaron demasiado en cambiarlo. No recibimos una explicación clara por parte del equipo."
        },
        {
            "autor": "Cliente 6",
            "puntuacion": 5,
            "origen": "Instagram simulado",
            "fecha": "2026-05-12",
            "texto": "Nos atendieron muy bien, explicaron los alérgenos con claridad y la presentación de los platos fue excelente."
        }
    ]

    for resena in resenas:
        conexion.execute("""
            INSERT INTO reviews (autor, puntuacion, origen, fecha, texto, creado_en)
            VALUES (?, ?, ?, ?, ?, ?)
        """, (
            resena["autor"],
            resena["puntuacion"],
            resena["origen"],
            resena["fecha"],
            resena["texto"],
            datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        ))


def crear_csv_ejemplo_si_no_existe():
    """
    Crea un CSV de ejemplo para probar la importación.
    """
    if CSV_EJEMPLO_FILE.exists():
        return

    contenido = """autor,puntuacion,origen,fecha,texto
Cliente Google 1,4,Google,2026-05-16,"La comida estaba muy buena, aunque el servicio fue un poco lento."
Cliente Google 2,2,Google,2026-05-15,"Tardaron demasiado en atendernos y la mesa no estaba preparada."
Cliente TripAdvisor 1,5,TripAdvisor,2026-05-14,"Excelente atención, platos bien presentados y ambiente muy agradable."
Cliente Instagram 1,3,Instagram,2026-05-13,"El local es bonito y la comida estaba bien, pero faltaba información sobre alérgenos."
Cliente Formulario 1,1,Formulario web,2026-05-12,"El plato llegó frío y no recibimos una explicación clara."
"""

    CSV_EJEMPLO_FILE.write_text(contenido, encoding="utf-8")


# ==========================================================
# FUNCIONES DE CONSULTA
# ==========================================================

def fila_a_diccionario(fila):
    """
    Convierte una fila SQLite en diccionario.
    """
    if fila is None:
        return None

    return dict(fila)


def construir_filtros_desde_request():
    """
    Lee los filtros del formulario GET.
    """
    return {
        "buscar": request.args.get("buscar", "").strip(),
        "estado": request.args.get("estado", "").strip(),
        "origen": request.args.get("origen", "").strip(),
        "puntuacion": request.args.get("puntuacion", "").strip()
    }


def filtros_tienen_valor(filtros):
    """
    Comprueba si hay algún filtro activo.
    """
    return any(valor for valor in filtros.values())


def construir_where_filtros(filtros):
    """
    Construye condiciones SQL para aplicar filtros en el listado.
    """
    condiciones = []
    parametros = []

    buscar = filtros.get("buscar", "")
    estado = filtros.get("estado", "")
    origen = filtros.get("origen", "")
    puntuacion = filtros.get("puntuacion", "")

    if buscar:
        condiciones.append("""
            (
                LOWER(r.autor) LIKE LOWER(?)
                OR LOWER(r.texto) LIKE LOWER(?)
                OR LOWER(COALESCE(a.problema_principal, '')) LIKE LOWER(?)
                OR LOWER(COALESCE(a.respuesta_final, '')) LIKE LOWER(?)
                OR LOWER(COALESCE(a.nota_interna, '')) LIKE LOWER(?)
            )
        """)

        patron = f"%{buscar}%"
        parametros.extend([patron, patron, patron, patron, patron])

    if estado == "pendiente":
        condiciones.append("a.review_id IS NULL")

    elif estado == "analizada":
        condiciones.append("""
            a.review_id IS NOT NULL
            AND COALESCE(a.estado_revision, 'analizada') != 'revisada'
        """)

    elif estado == "revisada":
        condiciones.append("a.estado_revision = 'revisada'")

    if origen:
        condiciones.append("r.origen = ?")
        parametros.append(origen)

    if puntuacion:
        try:
            puntuacion_int = int(puntuacion)
            if 1 <= puntuacion_int <= 5:
                condiciones.append("r.puntuacion = ?")
                parametros.append(puntuacion_int)
        except ValueError:
            pass

    if condiciones:
        where_sql = "WHERE " + " AND ".join(condiciones)
    else:
        where_sql = ""

    return where_sql, parametros


def cargar_resenas(filtros=None):
    """
    Obtiene todas las reseñas junto con su análisis, si existe.
    También permite aplicar filtros básicos.
    """
    if filtros is None:
        filtros = {}

    where_sql, parametros = construir_where_filtros(filtros)

    conexion = conectar_db()

    consulta = f"""
        SELECT
            r.id,
            r.autor,
            r.puntuacion,
            r.origen,
            r.fecha,
            r.texto,
            a.sentimiento,
            a.problema_principal,
            a.gravedad,
            a.estado_revision,
            a.fecha_revision
        FROM reviews r
        LEFT JOIN analysis a ON r.id = a.review_id
        {where_sql}
        ORDER BY r.id DESC
    """

    filas = conexion.execute(consulta, parametros).fetchall()

    conexion.close()

    resenas = []

    for fila in filas:
        item = dict(fila)

        if item.get("sentimiento") is None:
            item["estado_visual"] = "pendiente"
            item["estado_texto"] = "Pendiente"
        elif item.get("estado_revision") == "revisada":
            item["estado_visual"] = "revisada"
            item["estado_texto"] = "Revisada"
        else:
            item["estado_visual"] = "analizada"
            item["estado_texto"] = "Analizada"

        resenas.append(item)

    return resenas


def obtener_origenes():
    """
    Obtiene los orígenes existentes para el selector de filtros.
    """
    conexion = conectar_db()

    filas = conexion.execute("""
        SELECT DISTINCT origen
        FROM reviews
        ORDER BY origen ASC
    """).fetchall()

    conexion.close()

    return [fila["origen"] for fila in filas]


def obtener_resena(review_id):
    """
    Obtiene una reseña por ID.
    """
    conexion = conectar_db()

    fila = conexion.execute("""
        SELECT *
        FROM reviews
        WHERE id = ?
    """, (review_id,)).fetchone()

    conexion.close()

    return fila_a_diccionario(fila)


def obtener_analisis(review_id):
    """
    Obtiene el análisis de una reseña.
    """
    conexion = conectar_db()

    fila = conexion.execute("""
        SELECT *
        FROM analysis
        WHERE review_id = ?
    """, (review_id,)).fetchone()

    conexion.close()

    if not fila:
        return None

    datos = dict(fila)

    try:
        datos["area_afectada"] = json.loads(datos["area_afectada"])
    except json.JSONDecodeError:
        datos["area_afectada"] = []

    try:
        datos["acciones_internas"] = json.loads(datos["acciones_internas"])
    except json.JSONDecodeError:
        datos["acciones_internas"] = []

    if not datos.get("respuesta_final"):
        datos["respuesta_final"] = datos.get("respuesta_sugerida", "")

    if not datos.get("nota_interna"):
        datos["nota_interna"] = ""

    if not datos.get("estado_revision"):
        datos["estado_revision"] = "analizada"

    return datos


def existe_resena_duplicada(conexion, autor, fecha, texto):
    """
    Comprueba duplicado simple por autor + fecha + texto.
    """
    fila = conexion.execute("""
        SELECT id
        FROM reviews
        WHERE LOWER(TRIM(autor)) = LOWER(TRIM(?))
          AND fecha = ?
          AND LOWER(TRIM(texto)) = LOWER(TRIM(?))
        LIMIT 1
    """, (autor, fecha, texto)).fetchone()

    return fila is not None


def insertar_resena(conexion, autor, puntuacion, origen, fecha, texto):
    """
    Inserta una reseña en SQLite.
    """
    cursor = conexion.execute("""
        INSERT INTO reviews (autor, puntuacion, origen, fecha, texto, creado_en)
        VALUES (?, ?, ?, ?, ?, ?)
    """, (
        autor,
        puntuacion,
        origen,
        fecha,
        texto,
        datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    ))

    return cursor.lastrowid


def guardar_analisis(review_id, resultado_analisis, resultado_respuesta):
    """
    Guarda o actualiza el análisis de una reseña.
    Si ya existía una respuesta final editada, se actualiza también
    con la nueva respuesta sugerida para que el reanálisis sea coherente.
    """
    conexion = conectar_db()

    respuesta_sugerida = resultado_respuesta.get(
        "respuesta_sugerida",
        "Gracias por compartir tu experiencia."
    )

    conexion.execute("""
        INSERT INTO analysis (
            review_id,
            fecha_analisis,
            sentimiento,
            puntuacion_estimada,
            area_afectada,
            problema_principal,
            gravedad,
            resumen,
            respuesta_sugerida,
            acciones_internas,
            tono,
            respuesta_final,
            nota_interna,
            estado_revision,
            fecha_revision
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)

        ON CONFLICT(review_id) DO UPDATE SET
            fecha_analisis = excluded.fecha_analisis,
            sentimiento = excluded.sentimiento,
            puntuacion_estimada = excluded.puntuacion_estimada,
            area_afectada = excluded.area_afectada,
            problema_principal = excluded.problema_principal,
            gravedad = excluded.gravedad,
            resumen = excluded.resumen,
            respuesta_sugerida = excluded.respuesta_sugerida,
            acciones_internas = excluded.acciones_internas,
            tono = excluded.tono,
            respuesta_final = excluded.respuesta_final,
            estado_revision = 'analizada',
            fecha_revision = NULL
    """, (
        review_id,
        datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        resultado_analisis.get("sentimiento", "mixto"),
        int(resultado_analisis.get("puntuacion_estimada", 3)),
        json.dumps(resultado_analisis.get("area_afectada", []), ensure_ascii=False),
        resultado_analisis.get("problema_principal", "Sin clasificar"),
        resultado_analisis.get("gravedad", "media"),
        resultado_analisis.get("resumen", "Resumen no disponible."),
        respuesta_sugerida,
        json.dumps(resultado_respuesta.get("acciones_internas", []), ensure_ascii=False),
        resultado_respuesta.get("tono", "profesional y cercano"),
        respuesta_sugerida,
        "",
        "analizada",
        None
    ))

    conexion.commit()
    conexion.close()


def guardar_revision(review_id, respuesta_final, nota_interna, marcar_revisada=False):
    """
    Guarda la respuesta final editada y la nota interna.
    Si marcar_revisada es True, cierra la reseña como revisada.
    """
    conexion = conectar_db()

    analisis = conexion.execute("""
        SELECT id
        FROM analysis
        WHERE review_id = ?
    """, (review_id,)).fetchone()

    if not analisis:
        conexion.close()
        return False

    if marcar_revisada:
        estado_revision = "revisada"
        fecha_revision = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    else:
        estado_actual = conexion.execute("""
            SELECT estado_revision
            FROM analysis
            WHERE review_id = ?
        """, (review_id,)).fetchone()

        estado_revision = estado_actual["estado_revision"] or "analizada"
        fecha_actual = conexion.execute("""
            SELECT fecha_revision
            FROM analysis
            WHERE review_id = ?
        """, (review_id,)).fetchone()

        fecha_revision = fecha_actual["fecha_revision"]

    conexion.execute("""
        UPDATE analysis
        SET respuesta_final = ?,
            nota_interna = ?,
            estado_revision = ?,
            fecha_revision = ?
        WHERE review_id = ?
    """, (
        respuesta_final,
        nota_interna,
        estado_revision,
        fecha_revision,
        review_id
    ))

    conexion.commit()
    conexion.close()

    return True


def calcular_estadisticas(filtros=None):
    """
    Calcula los datos del panel principal sobre el resultado filtrado.
    """
    resenas_filtradas = cargar_resenas(filtros)

    total = len(resenas_filtradas)

    if total == 0:
        return {
            "total": 0,
            "media": 0,
            "pendientes": 0,
            "analizadas": 0,
            "revisadas": 0,
            "positivas": 0,
            "mixtas": 0,
            "negativas": 0,
            "problemas": []
        }

    suma = sum(int(resena["puntuacion"]) for resena in resenas_filtradas)
    media = round(suma / total, 2)

    pendientes = 0
    analizadas = 0
    revisadas = 0

    positivas = 0
    mixtas = 0
    negativas = 0

    contador_problemas = {}

    for resena in resenas_filtradas:
        estado = resena.get("estado_visual")
        sentimiento = resena.get("sentimiento")
        problema = resena.get("problema_principal")

        if estado == "pendiente":
            pendientes += 1
        elif estado == "revisada":
            revisadas += 1
        else:
            analizadas += 1

        if sentimiento == "positivo":
            positivas += 1
        elif sentimiento == "mixto":
            mixtas += 1
        elif sentimiento == "negativo":
            negativas += 1

        if problema:
            contador_problemas[problema] = contador_problemas.get(problema, 0) + 1

    problemas_ordenados = sorted(
        contador_problemas.items(),
        key=lambda item: item[1],
        reverse=True
    )

    problemas = [
        {
            "problema_principal": problema,
            "total": total_problema
        }
        for problema, total_problema in problemas_ordenados[:5]
    ]

    return {
        "total": total,
        "media": media,
        "pendientes": pendientes,
        "analizadas": analizadas,
        "revisadas": revisadas,
        "positivas": positivas,
        "mixtas": mixtas,
        "negativas": negativas,
        "problemas": problemas
    }


# ==========================================================
# FUNCIONES DE IMPORTACIÓN
# ==========================================================

def validar_resena_importada(item, numero_fila):
    """
    Valida una reseña importada desde CSV o JSON.
    """
    errores = []

    autor = str(item.get("autor", "")).strip()
    puntuacion = str(item.get("puntuacion", "")).strip()
    origen = str(item.get("origen", "")).strip()
    fecha = str(item.get("fecha", "")).strip()
    texto = str(item.get("texto", "")).strip()

    if not autor:
        errores.append(f"Fila {numero_fila}: falta el autor.")

    if not puntuacion:
        errores.append(f"Fila {numero_fila}: falta la puntuación.")
    else:
        try:
            puntuacion = int(puntuacion)
            if puntuacion < 1 or puntuacion > 5:
                errores.append(f"Fila {numero_fila}: la puntuación debe estar entre 1 y 5.")
        except ValueError:
            errores.append(f"Fila {numero_fila}: la puntuación debe ser un número.")
            puntuacion = 0

    if not origen:
        errores.append(f"Fila {numero_fila}: falta el origen.")

    if not fecha:
        errores.append(f"Fila {numero_fila}: falta la fecha.")

    if not texto:
        errores.append(f"Fila {numero_fila}: falta el texto.")

    resena_limpia = {
        "autor": autor,
        "puntuacion": puntuacion,
        "origen": origen,
        "fecha": fecha,
        "texto": texto
    }

    return resena_limpia, errores


def leer_csv_importado(archivo):
    """
    Lee un CSV subido desde el formulario.
    """
    contenido = archivo.read().decode("utf-8-sig")
    buffer = io.StringIO(contenido)

    lector = csv.DictReader(buffer)

    if not lector.fieldnames:
        raise ValueError("El CSV no contiene cabeceras.")

    campos_necesarios = {"autor", "puntuacion", "origen", "fecha", "texto"}
    campos_archivo = set(campo.strip() for campo in lector.fieldnames)

    faltantes = campos_necesarios - campos_archivo

    if faltantes:
        raise ValueError(f"Faltan columnas obligatorias: {', '.join(faltantes)}")

    return list(lector)


def leer_json_importado(archivo):
    """
    Lee un JSON subido desde el formulario.
    """
    contenido = archivo.read().decode("utf-8-sig")
    datos = json.loads(contenido)

    if not isinstance(datos, list):
        raise ValueError("El JSON debe contener una lista de reseñas.")

    return datos


def procesar_importacion(lista_resenas):
    """
    Valida e inserta reseñas importadas.
    """
    conexion = conectar_db()

    resultado = {
        "insertadas": 0,
        "duplicadas": 0,
        "invalidas": 0,
        "errores": []
    }

    for indice, item in enumerate(lista_resenas, start=1):
        resena, errores = validar_resena_importada(item, indice)

        if errores:
            resultado["invalidas"] += 1
            resultado["errores"].extend(errores)
            continue

        if existe_resena_duplicada(
            conexion,
            resena["autor"],
            resena["fecha"],
            resena["texto"]
        ):
            resultado["duplicadas"] += 1
            continue

        insertar_resena(
            conexion,
            resena["autor"],
            resena["puntuacion"],
            resena["origen"],
            resena["fecha"],
            resena["texto"]
        )

        resultado["insertadas"] += 1

    conexion.commit()
    conexion.close()

    return resultado


# ==========================================================
# RUTAS
# ==========================================================

@app.route("/")
def index():
    """
    Panel principal con filtros básicos.
    """
    filtros = construir_filtros_desde_request()
    resenas = cargar_resenas(filtros)
    estadisticas = calcular_estadisticas(filtros)
    origenes = obtener_origenes()

    return render_template(
        "index.html",
        resenas=resenas,
        estadisticas=estadisticas,
        filtros=filtros,
        filtros_activos=filtros_tienen_valor(filtros),
        origenes=origenes
    )


@app.route("/nueva-resena", methods=["GET", "POST"])
def nueva_resena():
    """
    Alta manual de reseñas.
    """
    if request.method == "POST":
        autor = request.form.get("autor", "").strip()
        puntuacion = request.form.get("puntuacion", "").strip()
        origen = request.form.get("origen", "").strip()
        fecha = request.form.get("fecha", "").strip()
        texto = request.form.get("texto", "").strip()

        errores = []

        if not autor:
            errores.append("El autor es obligatorio.")

        if not puntuacion:
            errores.append("La puntuación es obligatoria.")
        else:
            try:
                puntuacion = int(puntuacion)
                if puntuacion < 1 or puntuacion > 5:
                    errores.append("La puntuación debe estar entre 1 y 5.")
            except ValueError:
                errores.append("La puntuación debe ser un número.")

        if not origen:
            errores.append("El origen es obligatorio.")

        if not fecha:
            errores.append("La fecha es obligatoria.")

        if not texto:
            errores.append("El texto de la reseña es obligatorio.")

        if errores:
            for error in errores:
                flash(error, "error")

            return render_template(
                "nueva_resena.html",
                hoy=date.today().isoformat(),
                datos=request.form
            )

        conexion = conectar_db()

        if existe_resena_duplicada(conexion, autor, fecha, texto):
            conexion.close()
            flash("Esta reseña ya existe y no se ha vuelto a guardar.", "error")
            return render_template(
                "nueva_resena.html",
                hoy=date.today().isoformat(),
                datos=request.form
            )

        nuevo_id = insertar_resena(
            conexion,
            autor,
            puntuacion,
            origen,
            fecha,
            texto
        )

        conexion.commit()
        conexion.close()

        flash("Reseña añadida correctamente.", "success")

        return redirect(url_for("detalle", review_id=nuevo_id))

    return render_template(
        "nueva_resena.html",
        hoy=date.today().isoformat(),
        datos={}
    )


@app.route("/importar-resenas", methods=["GET", "POST"])
def importar_resenas():
    """
    Importación de reseñas mediante CSV o JSON.
    """
    resultado = None

    if request.method == "POST":
        archivo = request.files.get("archivo")

        if not archivo or archivo.filename.strip() == "":
            flash("Debes seleccionar un archivo CSV o JSON.", "error")
            return render_template("importar_resenas.html", resultado=None)

        nombre = archivo.filename.lower()

        try:
            if nombre.endswith(".csv"):
                lista_resenas = leer_csv_importado(archivo)
            elif nombre.endswith(".json"):
                lista_resenas = leer_json_importado(archivo)
            else:
                flash("Formato no permitido. Solo se acepta CSV o JSON.", "error")
                return render_template("importar_resenas.html", resultado=None)

            resultado = procesar_importacion(lista_resenas)

            flash(
                f"Importación finalizada. Añadidas: {resultado['insertadas']}. "
                f"Duplicadas: {resultado['duplicadas']}. "
                f"Inválidas: {resultado['invalidas']}.",
                "success"
            )

        except Exception as error:
            flash(f"No se pudo importar el archivo: {error}", "error")

    return render_template(
        "importar_resenas.html",
        resultado=resultado
    )


@app.route("/descargar-csv-ejemplo")
def descargar_csv_ejemplo():
    """
    Descarga el CSV de ejemplo.
    """
    crear_csv_ejemplo_si_no_existe()

    return send_file(
        CSV_EJEMPLO_FILE,
        as_attachment=True,
        download_name="ejemplo_importacion_resenas.csv"
    )


@app.route("/review/<int:review_id>")
def detalle(review_id):
    """
    Vista de detalle de una reseña.
    """
    resena = obtener_resena(review_id)

    if not resena:
        flash("No se ha encontrado la reseña solicitada.", "error")
        return redirect(url_for("index"))

    analisis = obtener_analisis(review_id)

    return render_template(
        "detalle.html",
        resena=resena,
        analisis=analisis
    )


@app.route("/analizar/<int:review_id>", methods=["POST"])
def analizar(review_id):
    """
    Ejecuta el análisis completo.
    """
    resena = obtener_resena(review_id)

    if not resena:
        flash("No se ha encontrado la reseña para analizar.", "error")
        return redirect(url_for("index"))

    resultado_analisis = analizar_resena(resena)
    resultado_respuesta = generar_respuesta_y_mejoras(resena, resultado_analisis)

    guardar_analisis(
        review_id=review_id,
        resultado_analisis=resultado_analisis,
        resultado_respuesta=resultado_respuesta
    )

    flash("Análisis generado correctamente.", "success")

    return redirect(url_for("detalle", review_id=review_id))


@app.route("/guardar-revision/<int:review_id>", methods=["POST"])
def guardar_revision_ruta(review_id):
    """
    Guarda la respuesta final, nota interna y opcionalmente
    marca la reseña como revisada.
    """
    resena = obtener_resena(review_id)

    if not resena:
        flash("No se ha encontrado la reseña.", "error")
        return redirect(url_for("index"))

    respuesta_final = request.form.get("respuesta_final", "").strip()
    nota_interna = request.form.get("nota_interna", "").strip()
    accion = request.form.get("accion", "guardar")

    if not respuesta_final:
        flash("La respuesta final no puede estar vacía.", "error")
        return redirect(url_for("detalle", review_id=review_id))

    marcar_revisada = accion == "revisar"

    guardado = guardar_revision(
        review_id=review_id,
        respuesta_final=respuesta_final,
        nota_interna=nota_interna,
        marcar_revisada=marcar_revisada
    )

    if not guardado:
        flash("Primero debes analizar la reseña antes de guardar la revisión.", "error")
        return redirect(url_for("detalle", review_id=review_id))

    if marcar_revisada:
        flash("Reseña marcada como revisada.", "success")
    else:
        flash("Cambios guardados correctamente.", "success")

    return redirect(url_for("detalle", review_id=review_id))


@app.route("/limpiar-analisis")
def limpiar_analisis():
    """
    Limpia los análisis para repetir la demo.
    Las reseñas se mantienen.
    """
    conexion = conectar_db()
    conexion.execute("DELETE FROM analysis")
    conexion.commit()
    conexion.close()

    flash("Los análisis se han limpiado correctamente.", "success")

    return redirect(url_for("index"))


# ==========================================================
# EJECUCIÓN
# ==========================================================

if __name__ == "__main__":
    iniciar_db()
    app.run(debug=True)