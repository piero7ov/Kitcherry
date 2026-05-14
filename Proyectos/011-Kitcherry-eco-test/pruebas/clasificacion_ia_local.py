"""
Clasificación con IA local usando Ollama.

Esta prueba llama a un modelo local de Ollama para clasificar consultas reales
relacionadas con Kitcherry. Sirve para comparar una solución ligera basada en
reglas frente a una solución más flexible, pero también más pesada.
"""

import json
import unicodedata
import urllib.error
import urllib.request
from pathlib import Path

from config import CATEGORIAS_VALIDAS, OLLAMA_MODEL, OLLAMA_TIMEOUT, OLLAMA_URL, REPETICIONES

RUTA_CONSULTAS = Path("datos/consultas.json")


def cargar_consultas():
    """
    Carga las consultas de prueba desde un archivo JSON.
    """
    with open(RUTA_CONSULTAS, "r", encoding="utf-8") as archivo:
        return json.load(archivo)


def normalizar_texto(texto: str) -> str:
    """
    Convierte el texto a minúsculas y elimina tildes para comparar mejor.
    """
    texto = texto.strip().lower()
    texto = unicodedata.normalize("NFD", texto)
    texto = "".join(caracter for caracter in texto if unicodedata.category(caracter) != "Mn")
    return texto


def limpiar_categoria(respuesta_ia: str) -> str:
    """
    Limpia la respuesta de la IA y la convierte en una categoría válida.
    """
    respuesta = normalizar_texto(respuesta_ia)

    # Por si el modelo responde con una frase en vez de una sola palabra.
    for categoria in CATEGORIAS_VALIDAS:
        if categoria in respuesta:
            return categoria

    return "otros"


def crear_prompt(mensaje: str) -> str:
    """
    Crea el prompt que se enviará a Ollama.
    """
    categorias = ", ".join(CATEGORIAS_VALIDAS)

    return f"""
Eres un clasificador de consultas para Kitcherry, una herramienta de software para hostelería.

Tu tarea es clasificar el mensaje del cliente en una única categoría.

Categorías disponibles:
{categorias}

Reglas:
- Responde solo con una categoría.
- No expliques nada.
- No añadas frases.
- Si no estás seguro, responde otros.

Mensaje del cliente:
\"{mensaje}\"

Categoría:
""".strip()


def llamar_ollama(prompt: str) -> str:
    """
    Envía el prompt a Ollama y devuelve la respuesta del modelo.
    """
    datos = {
        "model": OLLAMA_MODEL,
        "prompt": prompt,
        "stream": False,
        "options": {
            "temperature": 0,
            "num_predict": 10,
        },
    }

    peticion = urllib.request.Request(
        OLLAMA_URL,
        data=json.dumps(datos).encode("utf-8"),
        headers={"Content-Type": "application/json"},
        method="POST",
    )

    with urllib.request.urlopen(peticion, timeout=OLLAMA_TIMEOUT) as respuesta:
        contenido = respuesta.read().decode("utf-8")
        resultado = json.loads(contenido)
        return resultado.get("response", "")


def clasificar_mensaje_ia(mensaje: str) -> str:
    """
    Clasifica un mensaje usando IA local con Ollama.
    """
    prompt = crear_prompt(mensaje)
    respuesta_ia = llamar_ollama(prompt)
    return limpiar_categoria(respuesta_ia)


def ejecutar_prueba():
    """
    Ejecuta la prueba de clasificación con IA local.

    Si Ollama no está abierto o el modelo no existe, el programa no se rompe:
    registra el error en el detalle para que sea fácil detectar el problema.
    """
    consultas = cargar_consultas()
    resultados = []
    total_procesado = 0
    errores = []

    for _ in range(REPETICIONES["clasificacion_ia_local"]):
        for consulta in consultas:
            try:
                categoria = clasificar_mensaje_ia(consulta["mensaje"])
            except urllib.error.URLError as error:
                categoria = "error_ia"
                errores.append(f"No se pudo conectar con Ollama: {error}")
            except TimeoutError as error:
                categoria = "error_ia"
                errores.append(f"Tiempo de espera agotado al llamar a Ollama: {error}")
            except Exception as error:
                categoria = "error_ia"
                errores.append(f"Error al clasificar con IA local: {error}")

            resultados.append({
                "id": consulta["id"],
                "cliente": consulta["cliente"],
                "categoria": categoria,
            })
            total_procesado += 1

    detalle = f"Clasificación real usando IA local con Ollama y el modelo {OLLAMA_MODEL}"

    if errores:
        detalle += f". Aviso: {errores[0]}"

    return {
        "elementos_procesados": total_procesado,
        "detalle": detalle,
        "resultado_ejemplo": resultados[:3],
    }