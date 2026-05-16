# ==========================================================
# KITCHERRY REVIEW RADAR - VERSIÓN 001
# Flask + JSON + doble IA con Ollama
# ==========================================================

from pathlib import Path
from datetime import datetime
import json

from flask import Flask, render_template, redirect, url_for, flash

from ia.ia_analista import analizar_resena
from ia.ia_redactora import generar_respuesta_y_mejoras


# ==========================================================
# CONFIGURACIÓN GENERAL
# ==========================================================

BASE_DIR = Path(__file__).resolve().parent
DATA_DIR = BASE_DIR / "data"

REVIEWS_FILE = DATA_DIR / "reviews.json"
ANALISIS_FILE = DATA_DIR / "analisis_001.json"

app = Flask(__name__)
app.secret_key = "kitcherry-review-radar-001"


# ==========================================================
# FUNCIONES AUXILIARES
# ==========================================================

def cargar_json(ruta, valor_por_defecto):
    """
    Carga un archivo JSON.
    Si no existe o está vacío, devuelve un valor por defecto.
    """
    try:
        if not ruta.exists():
            return valor_por_defecto

        contenido = ruta.read_text(encoding="utf-8").strip()

        if not contenido:
            return valor_por_defecto

        return json.loads(contenido)

    except json.JSONDecodeError:
        return valor_por_defecto


def guardar_json(ruta, datos):
    """
    Guarda datos en un archivo JSON con formato legible.
    """
    ruta.parent.mkdir(parents=True, exist_ok=True)

    ruta.write_text(
        json.dumps(datos, ensure_ascii=False, indent=4),
        encoding="utf-8"
    )


def cargar_resenas():
    """
    Carga las reseñas simuladas desde reviews.json.
    """
    return cargar_json(REVIEWS_FILE, [])


def cargar_analisis():
    """
    Carga los análisis ya generados.
    La clave será el ID de la reseña.
    """
    return cargar_json(ANALISIS_FILE, {})


def buscar_resena_por_id(review_id):
    """
    Busca una reseña concreta por su ID.
    """
    resenas = cargar_resenas()

    for resena in resenas:
        if int(resena.get("id", 0)) == int(review_id):
            return resena

    return None


def calcular_estadisticas(resenas, analisis):
    """
    Calcula métricas básicas para el panel principal.
    """
    total = len(resenas)

    if total == 0:
        return {
            "total": 0,
            "media": 0,
            "analizadas": 0,
            "pendientes": 0,
            "positivas": 0,
            "mixtas": 0,
            "negativas": 0,
            "problemas": []
        }

    suma_puntuaciones = sum(float(r.get("puntuacion", 0)) for r in resenas)
    media = round(suma_puntuaciones / total, 2)

    analizadas = len(analisis)
    pendientes = total - analizadas

    positivas = 0
    mixtas = 0
    negativas = 0

    contador_problemas = {}

    for item in analisis.values():
        datos_ia1 = item.get("ia_analista", {})
        sentimiento = datos_ia1.get("sentimiento", "").lower()
        problema = datos_ia1.get("problema_principal", "Sin clasificar")

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
        key=lambda x: x[1],
        reverse=True
    )

    return {
        "total": total,
        "media": media,
        "analizadas": analizadas,
        "pendientes": pendientes,
        "positivas": positivas,
        "mixtas": mixtas,
        "negativas": negativas,
        "problemas": problemas_ordenados[:5]
    }


# ==========================================================
# RUTAS
# ==========================================================

@app.route("/")
def index():
    """
    Página principal.
    Muestra listado de reseñas y resumen.
    """
    resenas = cargar_resenas()
    analisis = cargar_analisis()
    estadisticas = calcular_estadisticas(resenas, analisis)

    return render_template(
        "index.html",
        resenas=resenas,
        analisis=analisis,
        estadisticas=estadisticas
    )


@app.route("/review/<int:review_id>")
def detalle(review_id):
    """
    Detalle de una reseña concreta.
    """
    resena = buscar_resena_por_id(review_id)

    if not resena:
        flash("No se ha encontrado la reseña solicitada.", "error")
        return redirect(url_for("index"))

    analisis = cargar_analisis()
    resultado = analisis.get(str(review_id))

    return render_template(
        "detalle.html",
        resena=resena,
        resultado=resultado
    )


@app.route("/analizar/<int:review_id>", methods=["POST"])
def analizar(review_id):
    """
    Ejecuta el flujo completo de doble IA.

    1. IA Analista analiza la reseña.
    2. IA Redactora genera respuesta y mejoras.
    3. Se guarda el resultado en analisis_001.json.
    """
    resena = buscar_resena_por_id(review_id)

    if not resena:
        flash("No se ha encontrado la reseña para analizar.", "error")
        return redirect(url_for("index"))

    # IA 1: análisis de la reseña
    resultado_ia1 = analizar_resena(resena)

    # IA 2: respuesta sugerida y acciones de mejora
    resultado_ia2 = generar_respuesta_y_mejoras(resena, resultado_ia1)

    analisis = cargar_analisis()

    analisis[str(review_id)] = {
        "id_resena": review_id,
        "fecha_analisis": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "ia_analista": resultado_ia1,
        "ia_redactora": resultado_ia2
    }

    guardar_json(ANALISIS_FILE, analisis)

    flash("Reseña analizada correctamente con IA 1 e IA 2.", "success")

    return redirect(url_for("detalle", review_id=review_id))


@app.route("/reiniciar-analisis")
def reiniciar_analisis():
    """
    Borra todos los análisis generados.
    Útil para repetir la demo desde cero.
    """
    guardar_json(ANALISIS_FILE, {})
    flash("Se han reiniciado los análisis de la versión 001.", "success")
    return redirect(url_for("index"))


# ==========================================================
# EJECUCIÓN
# ==========================================================

if __name__ == "__main__":
    DATA_DIR.mkdir(parents=True, exist_ok=True)

    if not ANALISIS_FILE.exists():
        guardar_json(ANALISIS_FILE, {})

    app.run(debug=True)