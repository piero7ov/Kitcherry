# ==========================================================
# CLIENTE OLLAMA
# Permite llamar a modelos locales desde Python.
# ==========================================================

import json
import os
import urllib.request
import urllib.error


OLLAMA_URL = os.getenv("OLLAMA_URL", "http://localhost:11434/api/generate")


def generar_con_ollama(modelo, prompt, temperatura=0.2):
    """
    Envía un prompt a Ollama y devuelve la respuesta del modelo.

    Parámetros:
    - modelo: nombre del modelo instalado en Ollama.
    - prompt: texto que se envía al modelo.
    - temperatura: creatividad de la respuesta.

    Devuelve:
    - Texto generado por Ollama.
    """
    payload = {
        "model": modelo,
        "prompt": prompt,
        "stream": False,
        "options": {
            "temperature": temperatura
        }
    }

    datos = json.dumps(payload).encode("utf-8")

    request = urllib.request.Request(
        OLLAMA_URL,
        data=datos,
        headers={"Content-Type": "application/json"},
        method="POST"
    )

    try:
        with urllib.request.urlopen(request, timeout=120) as response:
            respuesta = response.read().decode("utf-8")
            data = json.loads(respuesta)
            return data.get("response", "").strip()

    except urllib.error.URLError as error:
        raise RuntimeError(f"No se pudo conectar con Ollama: {error}")

    except TimeoutError:
        raise RuntimeError("La petición a Ollama ha tardado demasiado.")

    except json.JSONDecodeError:
        raise RuntimeError("Ollama devolvió una respuesta que no es JSON válido.")