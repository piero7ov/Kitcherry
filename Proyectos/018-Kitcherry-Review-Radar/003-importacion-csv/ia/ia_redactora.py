# ==========================================================
# GENERADOR DE RESPUESTAS Y RECOMENDACIONES
# Crea una respuesta profesional y acciones de mejora.
# ==========================================================

import os

from ia.ollama_client import generar_json_con_reintentos


MODELO_REDACTORA = os.getenv("OLLAMA_WRITER_MODEL", "llama3:latest")


def normalizar_respuesta(datos):
    """
    Garantiza una estructura estable para la respuesta.
    """
    acciones = datos.get("acciones_internas", [])

    if isinstance(acciones, str):
        acciones = [acciones]

    if not isinstance(acciones, list):
        acciones = []

    if not acciones:
        acciones = [
            "Revisar la situación comentada por el cliente.",
            "Compartir la información con el equipo responsable.",
            "Definir una pequeña mejora para evitar que se repita."
        ]

    return {
        "respuesta_sugerida": datos.get(
            "respuesta_sugerida",
            "Gracias por compartir tu experiencia. Tomamos nota para seguir mejorando."
        ),
        "acciones_internas": acciones,
        "tono": datos.get("tono", "profesional y cercano")
    }


def respuesta_local_seguridad(resena, analisis):
    """
    Último recurso para mantener la aplicación operativa.
    """
    sentimiento = analisis.get("sentimiento", "mixto")
    problema = analisis.get("problema_principal", "la experiencia del cliente")

    if sentimiento == "positivo":
        respuesta = (
            "Muchas gracias por tu valoración. Nos alegra saber que la experiencia "
            "fue positiva y que disfrutaste de nuestro servicio. Comentarios así nos "
            "ayudan a seguir trabajando con la misma dedicación."
        )
        acciones = [
            "Mantener los aspectos positivos mencionados por el cliente.",
            "Compartir la reseña con el equipo.",
            "Usar la opinión como referencia de buenas prácticas."
        ]

    elif sentimiento == "negativo":
        respuesta = (
            "Gracias por compartir tu experiencia. Sentimos que la visita no haya estado "
            "a la altura de lo esperado. Tomamos nota de lo ocurrido para revisar la situación "
            "con el equipo y aplicar mejoras."
        )
        acciones = [
            f"Revisar internamente el problema detectado: {problema}.",
            "Comprobar qué parte del servicio pudo fallar.",
            "Definir una acción correctiva y hacer seguimiento."
        ]

    else:
        respuesta = (
            "Gracias por tu comentario. Nos alegra que algunos aspectos hayan sido positivos, "
            "aunque también tomamos nota de los puntos mejorables. Revisaremos lo indicado "
            "para seguir mejorando la experiencia."
        )
        acciones = [
            f"Analizar el punto mejorable principal: {problema}.",
            "Revisar si se necesita ajustar la organización del servicio.",
            "Comentar el caso con el equipo para mejorar la atención."
        ]

    return {
        "respuesta_sugerida": respuesta,
        "acciones_internas": acciones,
        "tono": "profesional y cercano"
    }


def generar_respuesta_y_mejoras(resena, analisis):
    """
    Genera una respuesta pública y recomendaciones internas.
    """
    prompt = f"""
Eres un asistente especializado en reputación digital para restaurantes.

Tu tarea es generar:
1. Una respuesta profesional para una reseña de cliente.
2. Acciones internas útiles para mejorar el negocio.

Devuelve únicamente JSON válido.
No uses markdown.
No añadas explicaciones fuera del JSON.
No prometas descuentos, regalos ni compensaciones concretas.
No discutas con el cliente.
No inventes información.
Usa un tono cercano, profesional y humano.

El JSON debe tener exactamente esta estructura:

{{
  "respuesta_sugerida": "Texto de respuesta para el cliente.",
  "acciones_internas": [
    "Acción interna 1",
    "Acción interna 2",
    "Acción interna 3"
  ],
  "tono": "profesional y cercano"
}}

Reseña original:
Autor: {resena.get("autor")}
Puntuación: {resena.get("puntuacion")}
Origen: {resena.get("origen")}
Texto: {resena.get("texto")}

Resumen de la opinión:
Sentimiento: {analisis.get("sentimiento")}
Puntuación estimada: {analisis.get("puntuacion_estimada")}
Área afectada: {analisis.get("area_afectada")}
Problema principal: {analisis.get("problema_principal")}
Gravedad: {analisis.get("gravedad")}
Resumen: {analisis.get("resumen")}
"""

    try:
        datos = generar_json_con_reintentos(
            modelo=MODELO_REDACTORA,
            prompt=prompt,
            temperatura=0.15,
            intentos=3
        )

        return normalizar_respuesta(datos)

    except Exception:
        return respuesta_local_seguridad(resena, analisis)