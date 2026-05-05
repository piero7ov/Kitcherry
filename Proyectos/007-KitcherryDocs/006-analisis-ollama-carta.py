import os
import re
import json
import time
import requests
from datetime import datetime

# ==========================================================
# Objetivo:
# - Leer TXT limpios
# - Analizar documentos con apoyo determinista + Ollama
# - Generar un JSON de análisis documental útil para Kitcherry Docs
# ==========================================================

# ---------------- CONFIGURACIÓN ----------------

TXT_FOLDER = "txt_limpio"
OUT_FOLDER = "out"

OUT_JSON = os.path.join(OUT_FOLDER, "analisis_ollama_carta.json")
OUT_JSONL = os.path.join(OUT_FOLDER, "analisis_ollama_carta.jsonl")

OLLAMA_URL = "http://localhost:11434/api/generate"
MODEL = "llama3:latest"

MAX_CHARS = 10000
SLEEP_SEC = 0.2
TIMEOUT = 300

# ------------------------------------------------

ALERGENOS_EQUIVALENCIAS = {
    "Gluten": ["gluten", "trigo", "cebada", "centeno", "avena", "harina", "pan", "pasta"],
    "Crustáceos": ["crustáceos", "crustaceos", "gambas", "langostinos", "cangrejo"],
    "Huevos": ["huevo", "huevos", "mayonesa"],
    "Pescado": ["pescado", "atún", "atun", "merluza", "bacalao", "salmón", "salmon"],
    "Cacahuetes": ["cacahuete", "cacahuetes"],
    "Soja": ["soja"],
    "Leche": ["leche", "queso", "nata", "mantequilla", "yogur"],
    "Frutos de cáscara": ["frutos de cáscara", "frutos de cascara", "nueces", "almendras", "avellanas", "pistachos", "piñones", "pinones"],
    "Apio": ["apio"],
    "Mostaza": ["mostaza"],
    "Sésamo": ["sésamo", "sesamo"],
    "Sulfitos": ["sulfitos", "sulfito"],
    "Altramuces": ["altramuces", "altramuz"],
    "Moluscos": ["moluscos", "mejillones", "calamar", "almejas"]
}


# ==========================================================
# UTILIDADES
# ==========================================================

def cargar_texto(ruta):
    with open(ruta, "r", encoding="utf-8", errors="ignore") as archivo:
        return archivo.read()


def guardar_json(ruta, datos):
    with open(ruta, "w", encoding="utf-8") as archivo:
        json.dump(datos, archivo, ensure_ascii=False, indent=4)


def guardar_jsonl(ruta, registro):
    with open(ruta, "a", encoding="utf-8") as archivo:
        archivo.write(json.dumps(registro, ensure_ascii=False) + "\n")


def normalizar_texto(texto):
    texto = texto.lower()
    texto = texto.replace("á", "a")
    texto = texto.replace("é", "e")
    texto = texto.replace("í", "i")
    texto = texto.replace("ó", "o")
    texto = texto.replace("ú", "u")
    texto = texto.replace("ü", "u")
    texto = texto.replace("ñ", "n")
    texto = re.sub(r"\s+", " ", texto)
    return texto.strip()


def limpiar_espacios(texto):
    texto = texto.replace("\n", " ")
    texto = re.sub(r"\s+", " ", texto)
    return texto.strip()


def eliminar_duplicados(lista):
    resultado = []
    vistos = set()

    for item in lista:
        if not isinstance(item, str):
            continue

        item_limpio = item.strip()

        if not item_limpio:
            continue

        clave = normalizar_texto(item_limpio)

        if clave not in vistos:
            vistos.add(clave)
            resultado.append(item_limpio)

    return resultado


# ==========================================================
# ANÁLISIS DETERMINISTA
# ==========================================================

def detectar_tipo_por_nombre(nombre_archivo):
    nombre = normalizar_texto(nombre_archivo)

    if "carta" in nombre:
        return "carta"

    if "ficha" in nombre:
        return "fichas_tecnicas"

    if "alergeno" in nombre or "alergenos" in nombre:
        return "tabla_alergenos"

    return "otro"


def detectar_alergenos_determinista(texto):
    texto_normalizado = normalizar_texto(texto)
    encontrados = []

    for alergeno, palabras in ALERGENOS_EQUIVALENCIAS.items():
        for palabra in palabras:
            if normalizar_texto(palabra) in texto_normalizado:
                encontrados.append(alergeno)
                break

    return encontrados


def linea_bloqueada(linea):
    linea_min = normalizar_texto(linea)

    bloqueadas = [
        "kitcherry",
        "documento de prueba",
        "pagina",
        "tabla",
        "matriz",
        "plato",
        "precio",
        "descripcion",
        "ingredientes",
        "campo",
        "informacion",
        "categoria",
        "raciones",
        "elaboracion",
        "conservacion",
        "alergenos declarados",
        "gluten",
        "crustaceos",
        "huevos",
        "pescado",
        "cacahuetes",
        "soja",
        "leche",
        "frutos de cascara",
        "apio",
        "mostaza",
        "sesamo",
        "sulfitos",
        "altramuces",
        "moluscos"
    ]

    if not linea_min:
        return True

    if linea.startswith("====="):
        return True

    if len(linea_min) < 4:
        return True

    for palabra in bloqueadas:
        if linea_min.startswith(palabra):
            return True

    return False


def unir_nombre_partido(lineas, indice_linea_nombre):
    """
    Reconstruye nombres partidos antes de un precio.

    Ejemplo:
    Croquetas caseras de
    jamón
    8,50 €

    Devuelve:
    Croquetas caseras de jamón
    """

    linea_actual = lineas[indice_linea_nombre].strip()

    if indice_linea_nombre - 1 >= 0:
        linea_anterior = lineas[indice_linea_nombre - 1].strip()

        if not linea_bloqueada(linea_anterior):
            # Si la línea actual empieza en minúscula, normalmente es continuación
            if linea_actual and linea_actual[0].islower():
                return limpiar_espacios(linea_anterior + " " + linea_actual)

            # Caso habitual: "con queso", "de la casa", etc.
            comienzos_continuacion = ["con ", "de ", "del ", "la ", "el ", "y "]

            if normalizar_texto(linea_actual).startswith(tuple(comienzos_continuacion)):
                return limpiar_espacios(linea_anterior + " " + linea_actual)

    return linea_actual


def detectar_platos_carta(lineas):
    platos = []

    for i, linea in enumerate(lineas):
        if re.search(r"\d+[,\.]\d{2}\s*€", linea):
            if i - 1 >= 0:
                posible_nombre = unir_nombre_partido(lineas, i - 1)

                if not linea_bloqueada(posible_nombre):
                    platos.append(posible_nombre)

    return eliminar_duplicados(platos)


def detectar_platos_fichas(texto):
    platos = []

    for coincidencia in re.finditer(r"Ficha\s+\d+\s*:\s*(.+)", texto, re.IGNORECASE):
        nombre = coincidencia.group(1).strip()

        if nombre and not linea_bloqueada(nombre):
            platos.append(nombre)

    return eliminar_duplicados(platos)


def detectar_platos_tabla(lineas):
    platos = []

    for i, linea in enumerate(lineas):
        siguiente = lineas[i + 1].strip() if i + 1 < len(lineas) else ""

        if siguiente.lower().startswith("alérgenos declarados") or siguiente.lower().startswith("alergenos declarados"):
            if not linea_bloqueada(linea):
                platos.append(linea)

    return eliminar_duplicados(platos)


def detectar_posibles_platos_determinista(nombre_archivo, texto):
    tipo = detectar_tipo_por_nombre(nombre_archivo)
    lineas = [linea.strip() for linea in texto.splitlines() if linea.strip()]

    if tipo == "carta":
        return detectar_platos_carta(lineas)

    if tipo == "fichas_tecnicas":
        return detectar_platos_fichas(texto)

    if tipo == "tabla_alergenos":
        return detectar_platos_tabla(lineas)

    return []


def analisis_determinista(nombre_archivo, texto):
    return {
        "tipo_detectado_por_nombre": detectar_tipo_por_nombre(nombre_archivo),
        "alergenos_detectados_por_palabras": detectar_alergenos_determinista(texto),
        "posibles_platos_detectados": detectar_posibles_platos_determinista(nombre_archivo, texto)
    }


# ==========================================================
# OLLAMA
# ==========================================================

def comprobar_ollama():
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


def crear_prompt_analisis(nombre_archivo, texto, apoyo_determinista):
    tipo_sugerido = apoyo_determinista.get("tipo_detectado_por_nombre", "otro")

    prompt = f"""
Devuelve únicamente un JSON válido. No escribas explicación. No uses Markdown.

Analiza este documento para Kitcherry Docs, una herramienta de hostelería para organizar carta, platos, ingredientes, fichas técnicas y alérgenos.

El nombre del archivo sugiere este tipo de documento: {tipo_sugerido}.

Estructura obligatoria del JSON:
{{
  "archivo": "{nombre_archivo}",
  "tipo_documento": "{tipo_sugerido}",
  "resumen_utilidad": "",
  "platos_mencionados": [],
  "alergenos_mencionados": [],
  "ingredientes_relevantes": [],
  "advertencias": [],
  "uso_en_kitcherry": "",
  "nivel_revision_recomendado": "medio",
  "motivo_revision": ""
}}

Reglas:
- El campo tipo_documento solo puede ser: carta, fichas_tecnicas, tabla_alergenos u otro.
- No inventes platos, ingredientes ni alérgenos.
- Usa el análisis determinista como apoyo, pero corrige si ves algo claramente incorrecto.
- Si el documento contiene información de alérgenos, el nivel de revisión debe ser alto.
- Si el documento es ficticio o de prueba, añade una advertencia.
- En uso_en_kitcherry explica de forma concreta cómo sirve para el módulo de carta, platos y alérgenos.
- En motivo_revision explica de forma concreta por qué necesita revisión.
- No dejes frases genéricas como "Explica cómo..." o "Resumen breve...".

Análisis determinista de apoyo:
{json.dumps(apoyo_determinista, ensure_ascii=False, indent=2)}

Contenido:
{texto[:MAX_CHARS]}
"""

    return prompt.strip()


def extraer_json_desde_respuesta(respuesta_texto):
    respuesta_texto = respuesta_texto.strip()

    try:
        return json.loads(respuesta_texto)
    except Exception:
        pass

    inicio = respuesta_texto.find("{")
    fin = respuesta_texto.rfind("}")

    if inicio != -1 and fin != -1 and fin > inicio:
        posible_json = respuesta_texto[inicio:fin + 1]

        try:
            return json.loads(posible_json)
        except Exception:
            return None

    return None


def analizar_con_ollama(nombre_archivo, texto, apoyo_determinista):
    prompt = crear_prompt_analisis(nombre_archivo, texto, apoyo_determinista)

    payload = {
        "model": MODEL,
        "prompt": prompt,
        "stream": False,

        # Esto fuerza a Ollama a intentar devolver JSON válido
        "format": "json",

        "options": {
            "temperature": 0.1,
            "num_predict": 1600
        }
    }

    respuesta = requests.post(
        OLLAMA_URL,
        json=payload,
        timeout=TIMEOUT
    )

    respuesta.raise_for_status()

    datos = respuesta.json()
    texto_respuesta = datos.get("response", "").strip()

    json_extraido = extraer_json_desde_respuesta(texto_respuesta)

    if json_extraido is None:
        return crear_fallback_ia(nombre_archivo, apoyo_determinista, texto_respuesta)

    return normalizar_salida_ia(nombre_archivo, json_extraido, apoyo_determinista)


# ==========================================================
# NORMALIZACIÓN Y FALLBACK
# ==========================================================

def crear_resumen_utilidad(tipo):
    if tipo == "carta":
        return "Documento de carta que permite identificar platos, categorías, precios, descripciones e ingredientes para estructurar la información del establecimiento."

    if tipo == "fichas_tecnicas":
        return "Documento de fichas técnicas que permite consultar ingredientes, elaboración, conservación, raciones y alérgenos asociados a varios platos."

    if tipo == "tabla_alergenos":
        return "Documento de tabla de alérgenos que permite relacionar platos con los alérgenos declarados y apoyar la revisión de seguridad alimentaria."

    return "Documento de apoyo para organizar información interna del establecimiento."


def crear_uso_en_kitcherry(tipo):
    if tipo == "carta":
        return "Puede utilizarse para construir una carta estructurada con platos, precios, ingredientes y descripciones consultables desde el módulo de carta y alérgenos."

    if tipo == "fichas_tecnicas":
        return "Puede utilizarse para ampliar la información de cada plato con ingredientes, procesos de elaboración, conservación y alérgenos declarados."

    if tipo == "tabla_alergenos":
        return "Puede utilizarse como documento de referencia para revisar qué alérgenos están asociados a cada plato antes de publicar la información al cliente."

    return "Puede utilizarse como documento auxiliar dentro del sistema de gestión documental de Kitcherry Docs."


def crear_motivo_revision(tipo, tiene_alergenos):
    if tiene_alergenos:
        return "Requiere revisión alta porque contiene información sobre alérgenos que debe ser verificada por el responsable del establecimiento antes de publicarse o utilizarse con clientes."

    if tipo in ["carta", "fichas_tecnicas"]:
        return "Requiere revisión media porque la información extraída debe comprobarse antes de integrarse en la carta estructurada."

    return "Requiere revisión media para comprobar que la información se ha interpretado correctamente."


def contiene_placeholder(texto):
    texto_min = normalizar_texto(str(texto))

    placeholders = [
        "explica como",
        "explica por que",
        "resumen breve",
        "para que sirve este documento"
    ]

    return any(placeholder in texto_min for placeholder in placeholders)


def crear_fallback_ia(nombre_archivo, apoyo_determinista, respuesta_original):
    tipo = apoyo_determinista.get("tipo_detectado_por_nombre", "otro")
    alergenos = apoyo_determinista.get("alergenos_detectados_por_palabras", [])
    platos = apoyo_determinista.get("posibles_platos_detectados", [])

    tiene_alergenos = len(alergenos) > 0

    return {
        "archivo": nombre_archivo,
        "tipo_documento": tipo,
        "resumen_utilidad": crear_resumen_utilidad(tipo),
        "platos_mencionados": platos,
        "alergenos_mencionados": alergenos,
        "ingredientes_relevantes": [],
        "advertencias": [
            "No se pudo interpretar correctamente la respuesta de Ollama como JSON válido. Se generó una salida de apoyo con análisis determinista."
        ],
        "uso_en_kitcherry": crear_uso_en_kitcherry(tipo),
        "nivel_revision_recomendado": "alto" if tiene_alergenos else "medio",
        "motivo_revision": crear_motivo_revision(tipo, tiene_alergenos),
        "respuesta_original_modelo": respuesta_original
    }


def normalizar_salida_ia(nombre_archivo, datos, apoyo_determinista):
    tipo_determinista = apoyo_determinista.get("tipo_detectado_por_nombre", "otro")
    alergenos_deterministas = apoyo_determinista.get("alergenos_detectados_por_palabras", [])
    platos_deterministas = apoyo_determinista.get("posibles_platos_detectados", [])

    tipo = datos.get("tipo_documento", tipo_determinista)

    tipos_validos = ["carta", "fichas_tecnicas", "tabla_alergenos", "otro"]

    if tipo not in tipos_validos or tipo == "otro":
        tipo = tipo_determinista

    platos_ia = datos.get("platos_mencionados", [])
    alergenos_ia = datos.get("alergenos_mencionados", [])
    ingredientes_ia = datos.get("ingredientes_relevantes", [])
    advertencias_ia = datos.get("advertencias", [])

    if not isinstance(platos_ia, list):
        platos_ia = []

    if not isinstance(alergenos_ia, list):
        alergenos_ia = []

    if not isinstance(ingredientes_ia, list):
        ingredientes_ia = []

    if not isinstance(advertencias_ia, list):
        advertencias_ia = []

    platos_finales = eliminar_duplicados(platos_ia or platos_deterministas)
    alergenos_finales = eliminar_duplicados(alergenos_ia or alergenos_deterministas)
    ingredientes_finales = eliminar_duplicados(ingredientes_ia)

    resumen_utilidad = datos.get("resumen_utilidad", "")
    uso_en_kitcherry = datos.get("uso_en_kitcherry", "")
    motivo_revision = datos.get("motivo_revision", "")

    if not resumen_utilidad or contiene_placeholder(resumen_utilidad):
        resumen_utilidad = crear_resumen_utilidad(tipo)

    if not uso_en_kitcherry or contiene_placeholder(uso_en_kitcherry):
        uso_en_kitcherry = crear_uso_en_kitcherry(tipo)

    tiene_alergenos = len(alergenos_finales) > 0

    if not motivo_revision or contiene_placeholder(motivo_revision):
        motivo_revision = crear_motivo_revision(tipo, tiene_alergenos)

    nivel = datos.get("nivel_revision_recomendado", "medio")
    niveles_validos = ["bajo", "medio", "alto"]

    if nivel not in niveles_validos:
        nivel = "medio"

    if tiene_alergenos:
        nivel = "alto"

    texto_completo = json.dumps(datos, ensure_ascii=False).lower()

    if "ficticio" in texto_completo or "prueba" in texto_completo:
        advertencias_ia.append("Documento ficticio o de prueba. La información debe tratarse como material didáctico.")

    advertencias_finales = eliminar_duplicados(advertencias_ia)

    return {
        "archivo": datos.get("archivo", nombre_archivo),
        "tipo_documento": tipo,
        "resumen_utilidad": resumen_utilidad,
        "platos_mencionados": platos_finales,
        "alergenos_mencionados": alergenos_finales,
        "ingredientes_relevantes": ingredientes_finales,
        "advertencias": advertencias_finales,
        "uso_en_kitcherry": uso_en_kitcherry,
        "nivel_revision_recomendado": nivel,
        "motivo_revision": motivo_revision
    }


# ==========================================================
# PROCESO PRINCIPAL
# ==========================================================

def main():
    if not os.path.isdir(TXT_FOLDER):
        print(f"ERROR: No existe la carpeta '{TXT_FOLDER}'")
        print("Primero ejecuta 002-limpiar-texto.py")
        return

    os.makedirs(OUT_FOLDER, exist_ok=True)

    if not comprobar_ollama():
        return

    if os.path.exists(OUT_JSONL):
        os.remove(OUT_JSONL)

    archivos = sorted([
        archivo for archivo in os.listdir(TXT_FOLDER)
        if archivo.lower().endswith(".txt")
    ])

    if not archivos:
        print(f"No hay archivos .txt en '{TXT_FOLDER}'")
        return

    print("Iniciando análisis documental con Ollama...")
    print(f"Modelo: {MODEL}")
    print(f"Carpeta de entrada: {TXT_FOLDER}")
    print(f"Salida JSON: {OUT_JSON}")
    print("")

    resultados = []
    total_ok = 0
    total_error = 0

    for indice, archivo in enumerate(archivos, start=1):
        ruta_txt = os.path.join(TXT_FOLDER, archivo)

        try:
            texto = cargar_texto(ruta_txt)

            apoyo = analisis_determinista(archivo, texto)
            analisis_ia = analizar_con_ollama(archivo, texto, apoyo)

            registro = {
                "proyecto": "Kitcherry Docs",
                "iteracion": "006-analisis-ollama-carta",
                "archivo": archivo,
                "modelo": MODEL,
                "fecha_generacion": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                "analisis_determinista": apoyo,
                "analisis_ia": analisis_ia
            }

            resultados.append(registro)
            guardar_jsonl(OUT_JSONL, registro)

            total_ok += 1

            print(f"[{indice}/{len(archivos)}] OK -> {archivo}")
            print(f"Tipo IA: {analisis_ia.get('tipo_documento')}")
            print(f"Revisión: {analisis_ia.get('nivel_revision_recomendado')}")
            print("")

        except Exception as error:
            total_error += 1

            registro_error = {
                "proyecto": "Kitcherry Docs",
                "iteracion": "006-analisis-ollama-carta",
                "archivo": archivo,
                "modelo": MODEL,
                "fecha_generacion": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                "error": str(error)
            }

            resultados.append(registro_error)
            guardar_jsonl(OUT_JSONL, registro_error)

            print(f"[{indice}/{len(archivos)}] ERROR -> {archivo}: {error}")

        time.sleep(SLEEP_SEC)

    salida_global = {
        "proyecto": "Kitcherry Docs",
        "iteracion": "006-analisis-ollama-carta",
        "descripcion": "Análisis documental con Ollama sobre cartas, fichas técnicas y tablas de alérgenos.",
        "modelo": MODEL,
        "fecha_generacion": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "total_documentos": len(archivos),
        "documentos_ok": total_ok,
        "documentos_error": total_error,
        "resultados": resultados
    }

    guardar_json(OUT_JSON, salida_global)

    print("Proceso finalizado.")
    print(f"Documentos procesados correctamente: {total_ok}")
    print(f"Documentos con error: {total_error}")
    print(f"JSON generado en: {OUT_JSON}")
    print(f"JSONL generado en: {OUT_JSONL}")


if __name__ == "__main__":
    main()