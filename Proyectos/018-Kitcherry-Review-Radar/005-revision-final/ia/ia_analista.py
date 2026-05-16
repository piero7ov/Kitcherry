# ==========================================================
# ANALIZADOR DE RESEÑAS
# Clasifica la opinión del cliente.
# ==========================================================

import os

from ia.ollama_client import generar_json_con_reintentos


MODELO_ANALISTA = os.getenv("OLLAMA_ANALYZER_MODEL", "mistral:latest")


def normalizar_analisis(datos):
    """
    Garantiza que siempre se devuelvan las mismas claves.
    """
    area = datos.get("area_afectada", [])

    if isinstance(area, str):
        area = [area]

    if not isinstance(area, list):
        area = ["general"]

    sentimiento = str(datos.get("sentimiento", "mixto")).lower()
    gravedad = str(datos.get("gravedad", "media")).lower()

    if sentimiento not in ["positivo", "mixto", "negativo"]:
        sentimiento = "mixto"

    if gravedad not in ["baja", "media", "alta"]:
        gravedad = "media"

    try:
        puntuacion_estimada = int(datos.get("puntuacion_estimada", 3))
    except ValueError:
        puntuacion_estimada = 3

    if puntuacion_estimada < 1:
        puntuacion_estimada = 1

    if puntuacion_estimada > 5:
        puntuacion_estimada = 5

    return {
        "sentimiento": sentimiento,
        "puntuacion_estimada": puntuacion_estimada,
        "area_afectada": area,
        "problema_principal": datos.get("problema_principal", "Sin clasificar"),
        "gravedad": gravedad,
        "resumen": datos.get("resumen", "No se ha podido generar un resumen claro.")
    }


def analisis_local_seguridad(resena):
    """
    Último recurso para evitar que la aplicación se rompa.
    No se muestra como información técnica al usuario.
    """
    texto = resena.get("texto", "").lower()
    puntuacion = int(resena.get("puntuacion", 3))

    if puntuacion >= 4:
        sentimiento = "positivo"
        gravedad = "baja"
    elif puntuacion == 3:
        sentimiento = "mixto"
        gravedad = "media"
    else:
        sentimiento = "negativo"
        gravedad = "alta"

    area_afectada = ["general"]
    problema = "Experiencia general"

    if any(palabra in texto for palabra in ["tard", "espera", "lento", "demasiado"]):
        area_afectada = ["sala"]
        problema = "Tiempo de espera"

    if any(palabra in texto for palabra in ["gluten", "celíaco", "celiaco", "alérgeno", "alergenos", "alérgenos"]):
        area_afectada = ["alérgenos", "sala"]
        problema = "Información sobre alérgenos"

    if any(palabra in texto for palabra in ["reserva", "mesa"]):
        area_afectada = ["reservas"]
        problema = "Gestión de reservas"

    if any(palabra in texto for palabra in ["frío", "frio", "plato", "comida"]):
        if "general" in area_afectada:
            area_afectada = ["cocina"]
            problema = "Calidad o temperatura del plato"

    return {
        "sentimiento": sentimiento,
        "puntuacion_estimada": puntuacion,
        "area_afectada": area_afectada,
        "problema_principal": problema,
        "gravedad": gravedad,
        "resumen": "La reseña se ha clasificado según su puntuación y el contenido principal detectado."
    }


def analizar_resena(resena):
    """
    Analiza una reseña de cliente y devuelve datos estructurados.
    """
    prompt = f"""
Eres un asistente especializado en analizar opiniones de clientes de restaurantes.

Analiza la siguiente reseña y devuelve únicamente un JSON válido.

No uses markdown.
No añadas explicaciones fuera del JSON.
No inventes datos.
Usa un lenguaje claro y profesional.

El JSON debe tener exactamente esta estructura:

{{
  "sentimiento": "positivo",
  "puntuacion_estimada": 5,
  "area_afectada": ["sala"],
  "problema_principal": "Tiempo de espera",
  "gravedad": "baja",
  "resumen": "Resumen breve de la opinión del cliente."
}}

Valores permitidos:
- sentimiento: "positivo", "mixto", "negativo"
- gravedad: "baja", "media", "alta"
- area_afectada puede incluir: "sala", "cocina", "reservas", "alérgenos", "limpieza", "precio", "ambiente", "general"

Datos de la reseña:
Autor: {resena.get("autor")}
Puntuación original: {resena.get("puntuacion")}
Origen: {resena.get("origen")}
Fecha: {resena.get("fecha")}
Texto: {resena.get("texto")}
"""

    try:
        datos = generar_json_con_reintentos(
            modelo=MODELO_ANALISTA,
            prompt=prompt,
            temperatura=0.05,
            intentos=3
        )

        return normalizar_analisis(datos)

    except Exception:
        return analisis_local_seguridad(resena)