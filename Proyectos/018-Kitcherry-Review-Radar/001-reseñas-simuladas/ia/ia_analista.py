# ==========================================================
# IA 1 - ANALISTA DE RESEÑAS
# Analiza sentimiento, problema, área afectada y gravedad.
# ==========================================================

import json
import os
import re

from ia.ollama_client import generar_con_ollama


MODELO_ANALISTA = os.getenv("OLLAMA_ANALYZER_MODEL", "mistral:latest")


def extraer_json(texto):
    """
    Intenta extraer un JSON aunque el modelo añada texto alrededor.
    """
    if not texto:
        raise ValueError("Respuesta vacía del modelo.")

    texto = texto.strip()

    try:
        return json.loads(texto)
    except json.JSONDecodeError:
        pass

    coincidencia = re.search(r"\{.*\}", texto, re.DOTALL)

    if not coincidencia:
        raise ValueError("No se encontró un bloque JSON en la respuesta del modelo.")

    return json.loads(coincidencia.group(0))


def normalizar_analisis(datos, modelo, modo):
    """
    Asegura que el análisis tenga siempre las mismas claves.
    """
    area = datos.get("area_afectada", [])

    if isinstance(area, str):
        area = [area]

    return {
        "sentimiento": datos.get("sentimiento", "mixto"),
        "puntuacion_estimada": datos.get("puntuacion_estimada", 3),
        "area_afectada": area,
        "problema_principal": datos.get("problema_principal", "Sin clasificar"),
        "gravedad": datos.get("gravedad", "media"),
        "resumen": datos.get("resumen", "No se ha podido generar un resumen detallado."),
        "modelo_usado": modelo,
        "modo": modo
    }


def analisis_fallback(resena, error=None):
    """
    Análisis simple por reglas.
    Sirve para que la demo funcione aunque Ollama no esté activo.
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

    area_afectada = []
    problema = "Experiencia general"

    if any(palabra in texto for palabra in ["tardar", "tardaron", "espera", "lento", "demasiado"]):
        area_afectada.append("sala")
        problema = "Tiempo de espera"

    if any(palabra in texto for palabra in ["alérgeno", "alergenos", "gluten", "sin gluten", "celíaco", "celiaco"]):
        area_afectada.append("alérgenos")
        problema = "Información sobre alérgenos"

    if any(palabra in texto for palabra in ["reserva", "mesa"]):
        area_afectada.append("reservas")
        problema = "Gestión de reservas"

    if any(palabra in texto for palabra in ["frío", "fria", "frio", "plato", "comida"]):
        area_afectada.append("cocina")

        if problema == "Experiencia general":
            problema = "Calidad o temperatura del plato"

    if any(palabra in texto for palabra in ["trato", "personal", "atención", "atencion"]):
        area_afectada.append("atención al cliente")

        if problema == "Experiencia general":
            problema = "Atención al cliente"

    if not area_afectada:
        area_afectada = ["general"]

    resumen = (
        "Análisis local generado por reglas simples. "
        "La reseña se ha clasificado según puntuación y palabras clave detectadas."
    )

    if error:
        resumen += f" Error IA: {error}"

    return {
        "sentimiento": sentimiento,
        "puntuacion_estimada": puntuacion,
        "area_afectada": list(set(area_afectada)),
        "problema_principal": problema,
        "gravedad": gravedad,
        "resumen": resumen,
        "modelo_usado": "fallback_local",
        "modo": "fallback"
    }


def analizar_resena(resena):
    """
    Ejecuta la IA 1.

    Recibe una reseña y devuelve un análisis estructurado.
    """
    prompt = f"""
Eres la IA 1 de Kitcherry Review Radar.

Tu función es analizar reseñas de clientes de un restaurante.
Debes detectar sentimiento, puntuación estimada, área afectada, problema principal,
gravedad y un resumen breve.

Devuelve SOLO un JSON válido. No añadas explicación fuera del JSON.

Formato obligatorio:
{{
  "sentimiento": "positivo | mixto | negativo",
  "puntuacion_estimada": 1,
  "area_afectada": ["sala", "cocina", "reservas", "alérgenos", "limpieza", "precio", "general"],
  "problema_principal": "texto breve",
  "gravedad": "baja | media | alta",
  "resumen": "resumen breve de la reseña"
}}

Datos de la reseña:
Autor: {resena.get("autor")}
Puntuación original: {resena.get("puntuacion")}
Origen: {resena.get("origen")}
Fecha: {resena.get("fecha")}
Texto: {resena.get("texto")}
"""

    try:
        respuesta = generar_con_ollama(
            modelo=MODELO_ANALISTA,
            prompt=prompt,
            temperatura=0.1
        )

        datos = extraer_json(respuesta)

        return normalizar_analisis(
            datos=datos,
            modelo=MODELO_ANALISTA,
            modo="ollama"
        )

    except Exception as error:
        return analisis_fallback(resena, error=str(error))