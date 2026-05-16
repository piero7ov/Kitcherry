# ==========================================================
# CLIENTE OLLAMA
# Prioriza respuestas reales de modelos locales.
# Usa JSON mode cuando se solicita estructura.
# ==========================================================

import json
import os
import re
import urllib.request
import urllib.error


OLLAMA_URL = os.getenv("OLLAMA_URL", "http://localhost:11434/api/generate")


def extraer_json(texto):
    """
    Extrae un JSON válido desde la respuesta del modelo.

    Algunos modelos pueden devolver texto adicional.
    Esta función intenta recuperar el primer bloque JSON encontrado.
    """
    if not texto:
        raise ValueError("La respuesta del modelo está vacía.")

    texto = texto.strip()

    try:
        return json.loads(texto)
    except json.JSONDecodeError:
        pass

    coincidencia = re.search(r"\{.*\}", texto, re.DOTALL)

    if not coincidencia:
        raise ValueError("No se encontró JSON válido en la respuesta del modelo.")

    return json.loads(coincidencia.group(0))


def generar_texto_ollama(modelo, prompt, temperatura=0.1, usar_json=True):
    """
    Envía un prompt a Ollama.

    Si usar_json es True, se usa el modo JSON de Ollama
    para mejorar la estabilidad de las respuestas estructuradas.
    """
    payload = {
        "model": modelo,
        "prompt": prompt,
        "stream": False,
        "options": {
            "temperature": temperatura,
            "top_p": 0.8
        }
    }

    if usar_json:
        payload["format"] = "json"

    datos = json.dumps(payload).encode("utf-8")

    request = urllib.request.Request(
        OLLAMA_URL,
        data=datos,
        headers={"Content-Type": "application/json"},
        method="POST"
    )

    try:
        with urllib.request.urlopen(request, timeout=180) as response:
            respuesta = response.read().decode("utf-8")
            data = json.loads(respuesta)
            return data.get("response", "").strip()

    except urllib.error.URLError as error:
        raise RuntimeError(f"No se pudo conectar con Ollama: {error}")

    except TimeoutError:
        raise RuntimeError("La petición a Ollama tardó demasiado.")

    except json.JSONDecodeError:
        raise RuntimeError("Ollama devolvió una respuesta no válida.")


def generar_json_con_reintentos(modelo, prompt, temperatura=0.1, intentos=3):
    """
    Intenta obtener JSON válido varias veces antes de fallar.
    """
    ultimo_error = None

    for intento in range(1, intentos + 1):
        if intento == 1:
            prompt_final = prompt
        else:
            prompt_final = f"""
Tu respuesta anterior no cumplió el formato solicitado.

Responde de nuevo usando únicamente JSON válido.
No añadas texto antes ni después del JSON.
No uses markdown.
No expliques nada.

Instrucción original:
{prompt}
"""

        try:
            respuesta = generar_texto_ollama(
                modelo=modelo,
                prompt=prompt_final,
                temperatura=temperatura,
                usar_json=True
            )

            return extraer_json(respuesta)

        except Exception as error:
            ultimo_error = error

    raise RuntimeError(f"No se pudo obtener JSON válido después de {intentos} intentos: {ultimo_error}")