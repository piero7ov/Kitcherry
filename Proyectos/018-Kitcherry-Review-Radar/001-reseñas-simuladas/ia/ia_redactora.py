# ==========================================================
# IA 2 - REDACTORA Y PROPUESTA DE MEJORAS
# Genera respuesta profesional y acciones internas.
# ==========================================================

import json
import os
import re

from ia.ollama_client import generar_con_ollama


MODELO_REDACTORA = os.getenv("OLLAMA_WRITER_MODEL", "llama3:latest")


def extraer_json(texto):
    """
    Intenta extraer un JSON de la respuesta del modelo.
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


def normalizar_respuesta(datos, modelo, modo):
    """
    Asegura estructura estable para la respuesta de la IA 2.
    """
    acciones = datos.get("acciones_internas", [])

    if isinstance(acciones, str):
        acciones = [acciones]

    return {
        "respuesta_sugerida": datos.get(
            "respuesta_sugerida",
            "Gracias por compartir tu experiencia. Tomamos nota para seguir mejorando."
        ),
        "acciones_internas": acciones,
        "tono": datos.get("tono", "profesional y cercano"),
        "modelo_usado": modelo,
        "modo": modo
    }


def respuesta_fallback(resena, analisis, error=None):
    """
    Respuesta local por reglas para que la demo funcione sin Ollama.
    """
    sentimiento = analisis.get("sentimiento", "mixto")
    problema = analisis.get("problema_principal", "la experiencia")
    area = ", ".join(analisis.get("area_afectada", ["general"]))

    if sentimiento == "positivo":
        respuesta = (
            "Muchas gracias por tu valoración. Nos alegra saber que la experiencia "
            "fue positiva y que disfrutaste de nuestro servicio. Comentarios así nos "
            "ayudan a seguir trabajando con la misma dedicación."
        )
        acciones = [
            "Mantener los puntos fuertes mencionados por el cliente.",
            "Compartir la reseña positiva con el equipo.",
            "Usar este tipo de comentarios como referencia de buenas prácticas."
        ]

    elif sentimiento == "negativo":
        respuesta = (
            "Gracias por compartir tu experiencia. Sentimos que la visita no haya estado "
            "a la altura de lo esperado. Tomamos nota de lo ocurrido para revisar la situación "
            "con el equipo y aplicar mejoras que nos ayuden a evitar que vuelva a suceder."
        )
        acciones = [
            f"Revisar internamente el problema detectado: {problema}.",
            f"Comprobar el área afectada: {area}.",
            "Definir una acción correctiva y hacer seguimiento."
        ]

    else:
        respuesta = (
            "Gracias por tu comentario. Nos alegra que algunos aspectos de la experiencia "
            "hayan sido positivos, aunque también tomamos nota de los puntos mejorables. "
            "Revisaremos lo indicado para seguir mejorando el servicio."
        )
        acciones = [
            f"Analizar el punto mejorable principal: {problema}.",
            f"Revisar el área afectada: {area}.",
            "Valorar si se necesita formación, ajuste de tiempos o mejora de comunicación."
        ]

    if error:
        acciones.append(f"Aviso técnico: se usó respuesta local porque falló Ollama: {error}")

    return {
        "respuesta_sugerida": respuesta,
        "acciones_internas": acciones,
        "tono": "profesional y cercano",
        "modelo_usado": "fallback_local",
        "modo": "fallback"
    }


def generar_respuesta_y_mejoras(resena, analisis):
    """
    Ejecuta la IA 2.

    Recibe:
    - Reseña original
    - Análisis generado por la IA 1

    Devuelve:
    - Respuesta sugerida
    - Acciones internas de mejora
    """
    prompt = f"""
Eres la IA 2 de Kitcherry Review Radar.

Tu función es generar una respuesta profesional para una reseña de restaurante
y proponer acciones internas de mejora para el negocio.

Debes basarte en:
1. La reseña original.
2. El análisis generado por la IA 1.

Devuelve SOLO un JSON válido. No añadas explicación fuera del JSON.

Formato obligatorio:
{{
  "respuesta_sugerida": "respuesta profesional para publicar o usar como base",
  "acciones_internas": [
    "acción interna 1",
    "acción interna 2",
    "acción interna 3"
  ],
  "tono": "profesional y cercano"
}}

Reseña original:
Autor: {resena.get("autor")}
Puntuación: {resena.get("puntuacion")}
Origen: {resena.get("origen")}
Texto: {resena.get("texto")}

Análisis de la IA 1:
Sentimiento: {analisis.get("sentimiento")}
Puntuación estimada: {analisis.get("puntuacion_estimada")}
Área afectada: {analisis.get("area_afectada")}
Problema principal: {analisis.get("problema_principal")}
Gravedad: {analisis.get("gravedad")}
Resumen: {analisis.get("resumen")}

Reglas:
- Si la reseña es positiva, agradecer y reforzar el punto fuerte.
- Si la reseña es mixta, agradecer y reconocer el punto mejorable.
- Si la reseña es negativa, responder con empatía sin discutir con el cliente.
- No prometas compensaciones concretas.
- No inventes datos.
- Las acciones internas deben ser útiles para un restaurante.
"""

    try:
        respuesta = generar_con_ollama(
            modelo=MODELO_REDACTORA,
            prompt=prompt,
            temperatura=0.3
        )

        datos = extraer_json(respuesta)

        return normalizar_respuesta(
            datos=datos,
            modelo=MODELO_REDACTORA,
            modo="ollama"
        )

    except Exception as error:
        return respuesta_fallback(resena, analisis, error=str(error))