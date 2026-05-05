import os
import json
import time
import requests
from datetime import datetime

# ==========================================================
# Objetivo:
# - Leer los TXT limpios generados en la iteración 2
# - Enviar cada documento a Ollama
# - Generar un resumen en español adaptado al contexto hostelero
# - Guardar resúmenes individuales
# - Guardar un archivo summaries.jsonl útil como registro/dataset
# ==========================================================

# ---------------- CONFIGURACIÓN ----------------

TXT_FOLDER = "txt_limpio"
OUT_FOLDER = "summaries"
OUT_JSONL = os.path.join(OUT_FOLDER, "summaries.jsonl")

OLLAMA_URL = "http://localhost:11434/api/generate"

# Cambia el modelo según el que tengas instalado en Ollama
MODEL = "llama3:latest"

MAX_CHARS = 12000      # Recorte de seguridad para evitar prompts enormes
SLEEP_SEC = 0.2        # Pequeña pausa entre documentos
TIMEOUT = 300          # Tiempo máximo de espera por documento

# ------------------------------------------------


def cargar_texto(ruta):
    """
    Lee un archivo TXT con tolerancia a errores de codificación.
    """

    with open(ruta, "r", encoding="utf-8", errors="ignore") as archivo:
        return archivo.read()


def crear_prompt_resumen(nombre_archivo, texto):
    """
    Crea el prompt que se enviará a Ollama.

    El objetivo no es inventar información, sino resumir fielmente
    el documento desde el enfoque de Kitcherry Docs.
    """

    prompt = f"""
Eres un asistente de apoyo documental para Kitcherry Docs, una herramienta orientada a organizar información de carta, platos, ingredientes, fichas técnicas y alérgenos en negocios de hostelería.

Resume el siguiente documento en UN SOLO PÁRRAFO en español.

Instrucciones:
- Sé fiel al contenido del documento.
- No inventes información.
- Indica el tipo de documento si se puede deducir: carta, ficha técnica, tabla de alérgenos u otro.
- Menciona los puntos clave relacionados con platos, ingredientes, precios, alérgenos o uso interno.
- Si el documento contiene datos de prueba o material ficticio, indícalo de forma natural.
- No hagas listas.
- No uses formato Markdown.

Archivo: {nombre_archivo}

Contenido del documento:
{texto[:MAX_CHARS]}
"""

    return prompt.strip()


def resumir_con_ollama(nombre_archivo, texto):
    """
    Envía el texto a Ollama y devuelve el resumen generado.
    """

    texto = (texto or "").strip()

    if not texto:
        return ""

    prompt = crear_prompt_resumen(nombre_archivo, texto)

    payload = {
        "model": MODEL,
        "prompt": prompt,
        "stream": False,
        "options": {
            "temperature": 0.2
        }
    }

    respuesta = requests.post(
        OLLAMA_URL,
        json=payload,
        timeout=TIMEOUT
    )

    respuesta.raise_for_status()

    datos = respuesta.json()

    return datos.get("response", "").strip()


def guardar_resumen_individual(nombre_archivo, resumen):
    """
    Guarda un archivo .summary.txt por cada documento procesado.
    """

    nombre_base = os.path.splitext(nombre_archivo)[0]
    ruta_salida = os.path.join(OUT_FOLDER, nombre_base + ".summary.txt")

    with open(ruta_salida, "w", encoding="utf-8") as archivo:
        archivo.write(resumen + "\n")

    return ruta_salida


def guardar_en_jsonl(registro):
    """
    Guarda cada resumen como una línea JSON.
    Esto permite tener un historial procesable o reutilizable.
    """

    with open(OUT_JSONL, "a", encoding="utf-8") as archivo:
        archivo.write(json.dumps(registro, ensure_ascii=False) + "\n")


def comprobar_ollama():
    """
    Comprueba de forma sencilla si Ollama responde.
    """

    try:
        payload = {
            "model": MODEL,
            "prompt": "Responde únicamente con la palabra OK.",
            "stream": False,
            "options": {
                "temperature": 0
            }
        }

        respuesta = requests.post(
            OLLAMA_URL,
            json=payload,
            timeout=30
        )

        respuesta.raise_for_status()

        return True

    except Exception as error:
        print("ERROR: No se pudo conectar con Ollama.")
        print("Comprueba que Ollama esté abierto y que el modelo exista.")
        print(f"Modelo configurado: {MODEL}")
        print(f"URL configurada: {OLLAMA_URL}")
        print(f"Detalle técnico: {error}")

        return False


def main():
    """
    Proceso principal:
    - Comprueba carpetas
    - Comprueba Ollama
    - Lee TXT limpios
    - Genera resúmenes
    - Guarda salidas
    """

    if not os.path.isdir(TXT_FOLDER):
        print(f"ERROR: No existe la carpeta '{TXT_FOLDER}'")
        print("Primero ejecuta 002-limpiar-texto.py")
        return

    os.makedirs(OUT_FOLDER, exist_ok=True)

    if not comprobar_ollama():
        return

    # Reiniciar summaries.jsonl en cada ejecución
    if os.path.exists(OUT_JSONL):
        os.remove(OUT_JSONL)

    archivos = sorted([
        archivo for archivo in os.listdir(TXT_FOLDER)
        if archivo.lower().endswith(".txt")
    ])

    if not archivos:
        print(f"No hay archivos .txt en '{TXT_FOLDER}'")
        return

    print("Iniciando resumen de documentos con Ollama...")
    print(f"Modelo: {MODEL}")
    print(f"Carpeta de entrada: {TXT_FOLDER}")
    print(f"Carpeta de salida: {OUT_FOLDER}")
    print("")

    total_ok = 0
    total_error = 0

    for indice, archivo in enumerate(archivos, start=1):
        ruta_txt = os.path.join(TXT_FOLDER, archivo)

        try:
            texto = cargar_texto(ruta_txt)

            resumen = resumir_con_ollama(archivo, texto)

            ruta_resumen = guardar_resumen_individual(archivo, resumen)

            registro = {
                "proyecto": "Kitcherry Docs",
                "iteracion": "005-resumir-documentos-ollama",
                "file": archivo,
                "model": MODEL,
                "summary": resumen,
                "generated_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            }

            guardar_en_jsonl(registro)

            total_ok += 1

            print(f"[{indice}/{len(archivos)}] OK -> {archivo}")
            print(f"Resumen guardado en: {ruta_resumen}")

        except Exception as error:
            total_error += 1

            print(f"[{indice}/{len(archivos)}] ERROR -> {archivo}: {error}")

        time.sleep(SLEEP_SEC)

    print("")
    print("Proceso finalizado.")
    print(f"Documentos procesados correctamente: {total_ok}")
    print(f"Documentos con error: {total_error}")
    print(f"JSONL generado en: {OUT_JSONL}")


if __name__ == "__main__":
    main()