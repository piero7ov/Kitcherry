# ==========================================================
# KITCHERRY DOCS - PROCESO FINAL
# Archivo: procesar_kitcherry_docs.py
# Objetivo:
# - Leer 3 PDFs de un restaurante
# - Extraer texto
# - Limpiar texto
# - Usar la carta como fuente oficial de platos, precios y categorías
# - Usar la tabla de alérgenos como apoyo
# - Usar las fichas técnicas como apoyo interno
# - Generar los JSON necesarios para index.php
# ==========================================================

from __future__ import annotations

import json
import os
import re
import sys
import time
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple


# ==========================================================
# CONFIGURACIÓN GENERAL
# ==========================================================

BASE_DIR = Path(__file__).resolve().parent

PDF_DIR = BASE_DIR / "pdf"
TXT_DIR = BASE_DIR / "txt"
TXT_LIMPIO_DIR = BASE_DIR / "txt_limpio"
OUT_DIR = BASE_DIR / "out"
SUMMARIES_DIR = BASE_DIR / "summaries"

CONFIG_PATH = BASE_DIR / "config_kitcherry_docs.json"

OLLAMA_URL = os.environ.get("OLLAMA_URL", "http://localhost:11434/api/generate")
OLLAMA_MODEL = os.environ.get("OLLAMA_MODEL", "llama3:latest")

EJECUTAR_OLLAMA = os.environ.get("KITCHERRY_EJECUTAR_OLLAMA", "1") != "0"


# ==========================================================
# ALÉRGENOS Y PALABRAS CLAVE
# ==========================================================

ALERGENOS_ORDEN = [
    "Gluten",
    "Crustáceos",
    "Huevo",
    "Pescado",
    "Cacahuetes",
    "Soja",
    "Leche",
    "Frutos secos",
    "Apio",
    "Mostaza",
    "Sésamo",
    "Sulfitos",
    "Altramuces",
    "Moluscos",
]

ALERGENOS_KEYWORDS = {
    "Gluten": [
        "gluten",
        "trigo",
        "harina de trigo",
        "harina",
        "pan ",
        "pan,",
        "pan.",
        "pan brioche",
        "brioche",
        "pan rallado",
        "pasta",
        "galleta",
        "cebada",
        "cerveza",
        "pita",
    ],
    "Crustáceos": [
        "crustaceo",
        "crustaceos",
        "gamba",
        "gambas",
        "langostino",
        "langostinos",
        "cigala",
        "cigalas",
        "bogavante",
        "cangrejo",
    ],
    "Huevo": [
        "huevo",
        "huevos",
        "yema",
        "mayonesa",
        "mahonesa",
        "alioli",
        "pasta fresca de huevo",
    ],
    "Pescado": [
        "pescado",
        "caldo de pescado",
        "lubina",
        "atun",
        "atún",
        "bonito",
        "merluza",
        "bacalao",
        "salmón",
        "salmon",
        "anchoa",
    ],
    "Cacahuetes": [
        "cacahuete",
        "cacahuetes",
        "mani",
        "maní",
    ],
    "Soja": [
        "soja",
        "salsa de soja",
    ],
    "Leche": [
        "leche",
        "lacteo",
        "lácteo",
        "lacteos",
        "lácteos",
        "queso",
        "nata",
        "mantequilla",
        "parmesano",
        "bechamel",
        "crema de leche",
        "queso crema",
    ],
    "Frutos secos": [
        "frutos secos",
        "nuez",
        "nueces",
        "almendra",
        "almendras",
        "piñon",
        "piñón",
        "piñones",
        "avellana",
        "avellanas",
        "pistacho",
        "pistachos",
    ],
    "Apio": [
        "apio",
    ],
    "Mostaza": [
        "mostaza",
    ],
    "Sésamo": [
        "sesamo",
        "sésamo",
        "tahini",
    ],
    "Sulfitos": [
        "sulfito",
        "sulfitos",
        "vino",
        "vino blanco",
        "vino tinto",
        "cava",
        "vinagre",
    ],
    "Altramuces": [
        "altramuz",
        "altramuces",
    ],
    "Moluscos": [
        "molusco",
        "moluscos",
        "mejillon",
        "mejillón",
        "mejillones",
        "calamar",
        "calamares",
        "sepia",
        "almeja",
        "almejas",
        "pulpo",
    ],
}


CATEGORIAS_VALIDAS = [
    "Entrantes",
    "Platos principales",
    "Postres",
    "Bebidas",
    "Primeros",
    "Segundos",
    "Ensaladas",
    "Carnes",
    "Pescados",
    "Arroces",
    "Tapas",
]


LINEAS_IGNORABLES_EXACTAS = {
    "plato",
    "precio",
    "descripcion",
    "descripción",
    "ingredientes",
    "descripcion / ingredientes principales",
    "descripción / ingredientes principales",
}


# ==========================================================
# UTILIDADES DE CONSOLA
# ==========================================================

def imprimir_bloque(titulo: str) -> None:
    print()
    print("=" * 70)
    print(titulo)
    print("=" * 70)


def imprimir_ok(mensaje: str) -> None:
    print(f"OK -> {mensaje}")


def imprimir_omitido(mensaje: str) -> None:
    print(f"OMITIDO -> {mensaje}")


def imprimir_error(mensaje: str) -> None:
    print(f"ERROR -> {mensaje}")


def ahora_legible() -> str:
    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def medir_tiempo(funcion):
    def wrapper(*args, **kwargs):
        inicio = time.time()
        resultado = funcion(*args, **kwargs)
        duracion = round(time.time() - inicio, 2)
        return resultado, duracion

    return wrapper


# ==========================================================
# UTILIDADES DE ARCHIVOS
# ==========================================================

def asegurar_carpetas() -> None:
    for carpeta in [PDF_DIR, TXT_DIR, TXT_LIMPIO_DIR, OUT_DIR, SUMMARIES_DIR]:
        carpeta.mkdir(parents=True, exist_ok=True)


def cargar_json(ruta: Path, defecto: Any) -> Any:
    if not ruta.exists():
        return defecto

    try:
        contenido = ruta.read_text(encoding="utf-8")
        if not contenido.strip():
            return defecto
        return json.loads(contenido)
    except Exception:
        return defecto


def guardar_json(ruta: Path, datos: Any) -> None:
    ruta.parent.mkdir(parents=True, exist_ok=True)
    ruta.write_text(
        json.dumps(datos, ensure_ascii=False, indent=4),
        encoding="utf-8"
    )


def guardar_texto(ruta: Path, texto: str) -> None:
    ruta.parent.mkdir(parents=True, exist_ok=True)
    ruta.write_text(texto, encoding="utf-8")


def leer_texto(ruta: Path) -> str:
    if not ruta.exists():
        return ""
    return ruta.read_text(encoding="utf-8", errors="ignore")


# ==========================================================
# CONFIGURACIÓN DEL PROYECTO
# ==========================================================

def inferir_negocio_desde_carpeta() -> str:
    nombre = BASE_DIR.name.lower()

    if "huerta" in nombre or "mar" in nombre:
        return "Restaurante La Huerta del Mar"

    if "pochi" in nombre:
        return "Casa Pochi"

    return "Restaurante de prueba"


def cargar_configuracion() -> Dict[str, Any]:
    config = cargar_json(CONFIG_PATH, {})

    if not isinstance(config, dict):
        config = {}

    negocio = config.get("negocio") or inferir_negocio_desde_carpeta()
    iteracion = config.get("iteracion") or BASE_DIR.name
    modelo = config.get("modelo_ollama") or OLLAMA_MODEL

    return {
        "negocio": negocio,
        "iteracion": iteracion,
        "modelo_ollama": modelo,
    }


# ==========================================================
# NORMALIZACIÓN DE TEXTO
# ==========================================================

def normalizar(texto: str) -> str:
    texto = texto.lower()

    reemplazos = {
        "á": "a",
        "é": "e",
        "í": "i",
        "ó": "o",
        "ú": "u",
        "ü": "u",
        "ñ": "n",
    }

    for origen, destino in reemplazos.items():
        texto = texto.replace(origen, destino)

    texto = re.sub(r"\s+", " ", texto)
    return texto.strip()


def limpiar_espacios(texto: str) -> str:
    texto = texto.replace("\r\n", "\n").replace("\r", "\n")
    texto = re.sub(r"[ \t]+", " ", texto)
    texto = re.sub(r"\n{3,}", "\n\n", texto)
    return texto.strip()


def es_precio(linea: str) -> bool:
    return re.fullmatch(r"\d{1,3}[,.]\d{2}\s*€", linea.strip()) is not None


def extraer_precio(linea: str) -> str:
    coincidencia = re.search(r"\d{1,3}[,.]\d{2}\s*€", linea)
    if not coincidencia:
        return ""
    return coincidencia.group(0).replace(".", ",")


def es_categoria(linea: str) -> bool:
    linea_norm = normalizar(linea)

    for categoria in CATEGORIAS_VALIDAS:
        if linea_norm == normalizar(categoria):
            return True

    return False


def normalizar_categoria(linea: str) -> str:
    linea_norm = normalizar(linea)

    for categoria in CATEGORIAS_VALIDAS:
        if linea_norm == normalizar(categoria):
            return categoria

    return ""


def es_linea_ignorable(linea: str) -> bool:
    linea_limpia = linea.strip()
    linea_norm = normalizar(linea_limpia)

    if not linea_limpia:
        return True

    if linea_norm in LINEAS_IGNORABLES_EXACTAS:
        return True

    if linea_norm.startswith("kitcherry docs"):
        return True

    if re.fullmatch(r"pagina\s+\d+", linea_norm):
        return True

    if linea_norm.startswith("documento ficticio"):
        return True

    if linea_norm.startswith("documento de prueba"):
        return True

    if linea_norm.startswith("carta ficticia"):
        return True

    if linea_norm.startswith("carta de prueba"):
        return True

    if linea_norm.startswith("nota:"):
        return True

    if "informacion de alergenos debe ser revisada" in linea_norm:
        return True

    if "información de alérgenos debe ser revisada" in linea_norm:
        return True

    if "antes de publicarse al cliente" in linea_norm:
        return True

    return False


def limpiar_nombre_plato(nombre: str) -> str:
    nombre = nombre.strip()
    nombre = re.sub(r"\s+", " ", nombre)

    nombre = re.sub(
        r"^kitcherry docs\s*-\s*documentos de prueba realistas\s*",
        "",
        nombre,
        flags=re.IGNORECASE,
    )

    nombre = re.sub(
        r"^p[aá]gina\s+\d+\s*",
        "",
        nombre,
        flags=re.IGNORECASE,
    )

    if ". " in nombre:
        partes = [parte.strip() for parte in nombre.split(".") if parte.strip()]
        if partes:
            nombre = partes[-1]

    nombre = re.sub(r"^(plato|precio)\s+", "", nombre, flags=re.IGNORECASE)
    nombre = nombre.strip(" -–—·")

    return nombre


def parece_linea_descripcion(linea: str) -> bool:
    linea = linea.strip()

    if not linea:
        return True

    if es_linea_ignorable(linea):
        return True

    if es_categoria(linea):
        return True

    if es_precio(linea):
        return True

    if linea.endswith("."):
        return True

    if "," in linea and len(linea) > 28:
        return True

    if len(linea) > 62:
        return True

    palabras_descriptivas = [
        "servido",
        "servida",
        "cocinado",
        "cocinada",
        "frito",
        "frita",
        "horneado",
        "horneada",
        "mezcla de",
        "crema de",
        "seleccion de",
        "selección de",
        "contiene",
        "puede contener",
    ]

    linea_norm = normalizar(linea)

    for palabra in palabras_descriptivas:
        if palabra in linea_norm:
            return True

    return False


def obtener_lineas_limpias(texto: str) -> List[str]:
    lineas = []

    for linea in texto.splitlines():
        linea = linea.strip()
        linea = re.sub(r"\s+", " ", linea)

        if not linea:
            continue

        lineas.append(linea)

    return lineas


# ==========================================================
# LECTURA DE PDF
# ==========================================================

def extraer_texto_pdf(ruta_pdf: Path) -> str:
    texto = ""

    try:
        import fitz

        documento = fitz.open(str(ruta_pdf))

        for pagina in documento:
            texto += pagina.get_text("text") + "\n"

        documento.close()

        if texto.strip():
            return limpiar_espacios(texto)
    except Exception:
        pass

    try:
        from pypdf import PdfReader

        lector = PdfReader(str(ruta_pdf))

        for pagina in lector.pages:
            texto_pagina = pagina.extract_text() or ""
            texto += texto_pagina + "\n"

        return limpiar_espacios(texto)
    except Exception as error:
        raise RuntimeError(f"No se pudo leer el PDF {ruta_pdf.name}: {error}")


# ==========================================================
# PASO 001 - LECTURA DE PDF
# ==========================================================

@medir_tiempo
def paso_001_leer_pdfs() -> Dict[str, Any]:
    imprimir_bloque("[001] Lectura de documentos PDF")

    pdfs = sorted(PDF_DIR.glob("*.pdf"))
    generados = []

    if not pdfs:
        imprimir_error("No se encontraron PDFs en la carpeta pdf.")
        return {
            "estado": "error",
            "generados": generados,
            "mensaje": "No se encontraron PDFs.",
        }

    for pdf in pdfs:
        try:
            texto = extraer_texto_pdf(pdf)
            salida = TXT_DIR / f"{pdf.stem}.txt"
            guardar_texto(salida, texto)

            generados.append(str(salida.relative_to(BASE_DIR)))
            imprimir_ok(f"{pdf.name} convertido a {salida.name}")
        except Exception as error:
            imprimir_error(f"{pdf.name}: {error}")

    estado = "ok" if generados else "error"

    return {
        "estado": estado,
        "generados": generados,
        "total": len(generados),
    }


# ==========================================================
# PASO 002 - LIMPIEZA
# ==========================================================

def limpiar_texto_documento(texto: str) -> str:
    texto = limpiar_espacios(texto)

    lineas_limpias = []

    for linea in texto.splitlines():
        linea = linea.strip()
        linea = re.sub(r"\s+", " ", linea)

        if not linea:
            continue

        linea_norm = normalizar(linea)

        if linea_norm.startswith("kitcherry docs - documentos de prueba realistas"):
            continue

        if re.fullmatch(r"pagina\s+\d+", linea_norm):
            continue

        lineas_limpias.append(linea)

    return "\n".join(lineas_limpias).strip()


@medir_tiempo
def paso_002_limpiar_textos() -> Dict[str, Any]:
    imprimir_bloque("[002] Limpieza de texto")

    txts = sorted(TXT_DIR.glob("*.txt"))
    informe = {
        "fecha": ahora_legible(),
        "documentos": [],
    }

    if not txts:
        imprimir_error("No hay TXT generados para limpiar.")
        return {
            "estado": "error",
            "generados": [],
        }

    generados = []

    for txt in txts:
        texto = leer_texto(txt)
        texto_limpio = limpiar_texto_documento(texto)

        salida = TXT_LIMPIO_DIR / f"{txt.stem}_limpio.txt"
        guardar_texto(salida, texto_limpio)

        generados.append(str(salida.relative_to(BASE_DIR)))

        informe["documentos"].append({
            "archivo_origen": txt.name,
            "archivo_limpio": salida.name,
            "caracteres_originales": len(texto),
            "caracteres_limpios": len(texto_limpio),
        })

        imprimir_ok(f"{txt.name} limpiado como {salida.name}")

    guardar_json(OUT_DIR / "informe_limpieza.json", informe)

    print()
    print("Proceso finalizado.")
    print("Informe generado en: out\\informe_limpieza.json")

    return {
        "estado": "ok",
        "generados": generados,
        "total": len(generados),
    }


# ==========================================================
# IDENTIFICACIÓN DE DOCUMENTOS
# ==========================================================

def buscar_txt_limpio_por_tipo(tipo: str) -> Optional[Path]:
    archivos = sorted(TXT_LIMPIO_DIR.glob("*.txt"))

    patrones = {
        "carta": ["carta", "menu", "menú"],
        "fichas": ["ficha", "fichas", "tecnica", "técnica", "tecnicas", "técnicas"],
        "tabla": ["tabla", "alergeno", "alérgeno", "alergenos", "alérgenos"],
    }

    for archivo in archivos:
        nombre_norm = normalizar(archivo.name)

        for patron in patrones.get(tipo, []):
            if normalizar(patron) in nombre_norm:
                return archivo

    return None


# ==========================================================
# EXTRACCIÓN OFICIAL DESDE LA CARTA
# ==========================================================

def detectar_inicio_nombre(lineas: List[str], indice_precio: int) -> int:
    recogidas = []
    j = indice_precio - 1

    while j >= 0:
        linea = lineas[j].strip()

        if not linea:
            break

        if es_linea_ignorable(linea):
            j -= 1
            continue

        if es_categoria(linea):
            break

        if es_precio(linea):
            break

        if parece_linea_descripcion(linea) and recogidas:
            break

        if parece_linea_descripcion(linea) and not recogidas:
            break

        recogidas.append(linea)

        if len(recogidas) >= 3:
            break

        j -= 1

    if not recogidas:
        return max(0, indice_precio - 1)

    return indice_precio - len(recogidas)


def obtener_categoria_para_indice(lineas: List[str], indice: int) -> str:
    categoria_actual = ""

    for i in range(0, indice + 1):
        linea = lineas[i]

        if es_categoria(linea):
            categoria_actual = normalizar_categoria(linea)

    return categoria_actual


def es_nombre_plato_valido(nombre: str) -> bool:
    nombre_limpio = limpiar_nombre_plato(nombre)
    nombre_norm = normalizar(nombre_limpio)

    if len(nombre_limpio) < 3:
        return False

    if nombre_norm in LINEAS_IGNORABLES_EXACTAS:
        return False

    if nombre_norm.startswith("nota"):
        return False

    if "informacion de alergenos" in nombre_norm:
        return False

    if "antes de publicarse" in nombre_norm:
        return False

    if len(nombre_limpio) > 90:
        return False

    if nombre_limpio.endswith("."):
        return False

    return True


def extraer_items_carta(texto_carta: str) -> List[Dict[str, Any]]:
    lineas = obtener_lineas_limpias(texto_carta)

    precios_detectados = []

    for indice, linea in enumerate(lineas):
        if es_precio(linea):
            inicio_nombre = detectar_inicio_nombre(lineas, indice)
            nombre = " ".join(lineas[inicio_nombre:indice])
            nombre = limpiar_nombre_plato(nombre)

            if not es_nombre_plato_valido(nombre):
                continue

            categoria = obtener_categoria_para_indice(lineas, inicio_nombre)
            precio = extraer_precio(linea)

            precios_detectados.append({
                "indice_precio": indice,
                "indice_inicio_nombre": inicio_nombre,
                "nombre": nombre,
                "categoria": categoria if categoria else "Sin categoría",
                "precio": precio,
            })

    items = []

    for posicion, item in enumerate(precios_detectados):
        inicio_descripcion = item["indice_precio"] + 1

        if posicion + 1 < len(precios_detectados):
            fin_descripcion = precios_detectados[posicion + 1]["indice_inicio_nombre"]
        else:
            fin_descripcion = len(lineas)

        descripcion_lineas = []

        for linea in lineas[inicio_descripcion:fin_descripcion]:
            if es_linea_ignorable(linea):
                continue

            if es_categoria(linea):
                continue

            if es_precio(linea):
                continue

            descripcion_lineas.append(linea)

        descripcion = " ".join(descripcion_lineas)
        descripcion = re.sub(r"\s+", " ", descripcion).strip()

        items.append({
            "nombre": item["nombre"],
            "categoria": item["categoria"],
            "precio": item["precio"],
            "descripcion": descripcion,
            "fuente": "carta",
        })

    return depurar_items_carta(items)


def depurar_items_carta(items: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    depurados = []
    vistos = set()

    for item in items:
        nombre = limpiar_nombre_plato(str(item.get("nombre", "")))
        clave = normalizar(nombre)

        if not nombre or clave in vistos:
            continue

        if not es_nombre_plato_valido(nombre):
            continue

        item["nombre"] = nombre
        vistos.add(clave)
        depurados.append(item)

    return depurados


# ==========================================================
# APOYO DESDE TABLA DE ALÉRGENOS
# ==========================================================

def encontrar_segmentos_por_plato(texto: str, nombres_platos: List[str]) -> Dict[str, str]:
    texto_norm = normalizar(texto)
    posiciones = []

    for nombre in nombres_platos:
        nombre_norm = normalizar(nombre)

        posicion = texto_norm.find(nombre_norm)

        if posicion >= 0:
            posiciones.append({
                "nombre": nombre,
                "posicion": posicion,
            })

    posiciones.sort(key=lambda item: item["posicion"])

    segmentos = {}

    for indice, item in enumerate(posiciones):
        inicio = item["posicion"]

        if indice + 1 < len(posiciones):
            fin = posiciones[indice + 1]["posicion"]
        else:
            fin = min(len(texto_norm), inicio + 800)

        segmento = texto_norm[inicio:fin]
        segmentos[item["nombre"]] = segmento

    return segmentos


def detectar_alergenos_en_texto(texto: str) -> List[str]:
    texto_norm = normalizar(texto)

    if "sin alergenos declarados" in texto_norm or "sin alergenos" in texto_norm:
        return []

    detectados = []

    for alergeno in ALERGENOS_ORDEN:
        palabras = ALERGENOS_KEYWORDS.get(alergeno, [])

        for palabra in palabras:
            palabra_norm = normalizar(palabra)

            if palabra_norm in texto_norm:
                detectados.append(alergeno)
                break

    return detectados


def detectar_alergenos_desde_tabla(texto_tabla: str, platos: List[Dict[str, Any]]) -> Dict[str, List[str]]:
    nombres = [str(plato.get("nombre", "")) for plato in platos]
    segmentos = encontrar_segmentos_por_plato(texto_tabla, nombres)

    resultado = {}

    for plato in platos:
        nombre = str(plato.get("nombre", ""))
        segmento = segmentos.get(nombre, "")

        if not segmento:
            resultado[nombre] = []
            continue

        alergenos = detectar_alergenos_en_texto(segmento)

        resultado[nombre] = alergenos

    return resultado


# ==========================================================
# APOYO DESDE FICHAS TÉCNICAS
# ==========================================================

def extraer_fichas_tecnicas(texto_fichas: str, platos: List[Dict[str, Any]]) -> Dict[str, Dict[str, str]]:
    fichas = {}

    if not texto_fichas.strip():
        return fichas

    nombres = [str(plato.get("nombre", "")) for plato in platos]
    segmentos = encontrar_segmentos_por_plato(texto_fichas, nombres)

    for nombre in nombres:
        segmento = segmentos.get(nombre, "")

        if not segmento:
            fichas[nombre] = {}
            continue

        ficha = {
            "raciones": extraer_campo_ficha(segmento, ["raciones", "racion"], ["ingredientes", "elaboracion", "conservacion", "alergenos"]),
            "ingredientes": extraer_campo_ficha(segmento, ["ingredientes"], ["elaboracion", "conservacion", "alergenos"]),
            "elaboracion": extraer_campo_ficha(segmento, ["elaboracion", "elaboración", "preparacion", "preparación"], ["conservacion", "conservación", "alergenos"]),
            "conservacion": extraer_campo_ficha(segmento, ["conservacion", "conservación"], ["alergenos", "alérgenos"]),
            "alergenos_texto": extraer_campo_ficha(segmento, ["alergenos", "alérgenos"], ["trazas", "observaciones"]),
        }

        fichas[nombre] = ficha

    return fichas


def extraer_campo_ficha(segmento: str, inicios: List[str], finales: List[str]) -> str:
    segmento_norm = segmento

    posicion_inicio = -1
    etiqueta_inicio = ""

    for inicio in inicios:
        inicio_norm = normalizar(inicio)
        pos = segmento_norm.find(inicio_norm)

        if pos >= 0 and (posicion_inicio == -1 or pos < posicion_inicio):
            posicion_inicio = pos
            etiqueta_inicio = inicio_norm

    if posicion_inicio == -1:
        return ""

    inicio_contenido = posicion_inicio + len(etiqueta_inicio)

    posicion_fin = len(segmento_norm)

    for final in finales:
        final_norm = normalizar(final)
        pos = segmento_norm.find(final_norm, inicio_contenido)

        if pos >= 0 and pos < posicion_fin:
            posicion_fin = pos

    contenido = segmento_norm[inicio_contenido:posicion_fin]
    contenido = contenido.strip(" :.-\n\t")

    return contenido[:900].strip()


# ==========================================================
# INGREDIENTES Y ALÉRGENOS
# ==========================================================

def extraer_ingredientes_desde_descripcion(descripcion: str) -> List[str]:
    descripcion = descripcion.strip()

    if not descripcion:
        return []

    texto = descripcion

    texto = re.sub(r"^(contiene|incluye)\s+", "", texto, flags=re.IGNORECASE)

    partes = re.split(r",|\sy\s", texto)

    ingredientes = []

    for parte in partes:
        parte = parte.strip(" .;:")

        if not parte:
            continue

        if len(parte) > 45:
            continue

        if normalizar(parte).startswith("servid"):
            continue

        if parte not in ingredientes:
            ingredientes.append(parte)

    return ingredientes[:12]


def combinar_alergenos(*listas: List[str]) -> List[str]:
    resultado = []

    for lista in listas:
        for alergeno in lista:
            if alergeno in ALERGENOS_ORDEN and alergeno not in resultado:
                resultado.append(alergeno)

    resultado.sort(key=lambda item: ALERGENOS_ORDEN.index(item))
    return resultado


def obtener_texto_ficha(ficha: Dict[str, str]) -> str:
    partes = []

    for valor in ficha.values():
        if isinstance(valor, str) and valor.strip():
            partes.append(valor.strip())

    return " ".join(partes)


def detectar_alergenos_plato(
    plato: Dict[str, Any],
    alergenos_tabla: List[str],
    ficha: Dict[str, str],
) -> List[str]:
    texto_base = " ".join([
        str(plato.get("nombre", "")),
        str(plato.get("descripcion", "")),
        obtener_texto_ficha(ficha),
    ])

    alergenos_inferidos = detectar_alergenos_en_texto(texto_base)

    # Protección contra segmentos de tabla contaminados:
    # si la tabla devuelve demasiados alérgenos para casi todo,
    # priorizamos la inferencia desde carta/ficha.
    if len(alergenos_tabla) > 8 and len(alergenos_inferidos) <= 6:
        return alergenos_inferidos

    return combinar_alergenos(alergenos_tabla, alergenos_inferidos)


# ==========================================================
# PASO 003 - DETECCIÓN OFICIAL DE PLATOS
# ==========================================================

@medir_tiempo
def paso_003_detectar_platos() -> Dict[str, Any]:
    imprimir_bloque("[003] Detección de platos y alérgenos")

    carta_txt = buscar_txt_limpio_por_tipo("carta")
    fichas_txt = buscar_txt_limpio_por_tipo("fichas")
    tabla_txt = buscar_txt_limpio_por_tipo("tabla")

    if not carta_txt:
        imprimir_error("No se encontró un TXT limpio de carta.")
        return {
            "estado": "error",
            "platos": [],
        }

    if fichas_txt:
        imprimir_omitido(f"{fichas_txt.name}: no se usa como fuente principal de detección oficial")

    if tabla_txt:
        imprimir_omitido(f"{tabla_txt.name}: se usará como apoyo de alérgenos, no como fuente oficial de platos")

    texto_carta = leer_texto(carta_txt)
    platos_carta = extraer_items_carta(texto_carta)

    platos_detectados = []

    for indice, plato in enumerate(platos_carta, start=1):
        platos_detectados.append({
            "id": indice,
            "nombre": plato.get("nombre", ""),
            "categoria": plato.get("categoria", "Sin categoría"),
            "precio": plato.get("precio", ""),
            "descripcion": plato.get("descripcion", ""),
            "fuente": "carta",
        })

    salida = OUT_DIR / "platos_detectados.json"

    guardar_json(salida, {
        "fecha": ahora_legible(),
        "fuente_oficial": "carta",
        "total": len(platos_detectados),
        "platos": platos_detectados,
    })

    imprimir_ok(f"{carta_txt.name}: {len(platos_detectados)} platos detectados como fuente oficial")

    print()
    print("Proceso finalizado.")
    print(f"Total de platos detectados: {len(platos_detectados)}")
    print("JSON generado en: out\\platos_detectados.json")

    return {
        "estado": "ok",
        "platos": platos_detectados,
        "total": len(platos_detectados),
    }


# ==========================================================
# PASO 004 - CARTA ESTRUCTURADA
# ==========================================================

def crear_revisiones_base_si_no_existe(config: Dict[str, Any]) -> None:
    ruta = OUT_DIR / "revisiones_platos.json"

    if ruta.exists():
        return

    guardar_json(ruta, {
        "proyecto": "Kitcherry Docs",
        "iteracion": config["iteracion"],
        "descripcion": "Archivo independiente para guardar estados de revisión de platos sin modificar la carta generada automáticamente.",
        "ultima_actualizacion": "",
        "total_revisiones": 0,
        "revisiones": {},
    })


@medir_tiempo
def paso_004_generar_carta_estructurada(config: Dict[str, Any]) -> Dict[str, Any]:
    imprimir_bloque("[004] Generación de carta estructurada")

    datos_detectados = cargar_json(OUT_DIR / "platos_detectados.json", {})
    platos_detectados = datos_detectados.get("platos", [])

    if not isinstance(platos_detectados, list) or not platos_detectados:
        imprimir_error("No hay platos detectados para estructurar.")
        return {
            "estado": "error",
            "total": 0,
        }

    tabla_txt = buscar_txt_limpio_por_tipo("tabla")
    fichas_txt = buscar_txt_limpio_por_tipo("fichas")

    texto_tabla = leer_texto(tabla_txt) if tabla_txt else ""
    texto_fichas = leer_texto(fichas_txt) if fichas_txt else ""

    alergenos_por_tabla = detectar_alergenos_desde_tabla(texto_tabla, platos_detectados)
    fichas_por_plato = extraer_fichas_tecnicas(texto_fichas, platos_detectados)

    platos_estructurados = []

    for indice, plato in enumerate(platos_detectados, start=1):
        nombre = str(plato.get("nombre", "")).strip()
        descripcion = str(plato.get("descripcion", "")).strip()
        ficha = fichas_por_plato.get(nombre, {})

        alergenos_tabla = alergenos_por_tabla.get(nombre, [])
        alergenos_finales = detectar_alergenos_plato(plato, alergenos_tabla, ficha)
        ingredientes = extraer_ingredientes_desde_descripcion(descripcion)

        fuentes = ["carta"]

        if texto_tabla:
            fuentes.append("tabla_alergenos")

        if texto_fichas:
            fuentes.append("fichas_tecnicas")

        platos_estructurados.append({
            "id": indice,
            "nombre": nombre,
            "categoria": plato.get("categoria", "Sin categoría"),
            "precio": plato.get("precio", ""),
            "descripcion": descripcion,
            "ingredientes_detectados": ingredientes,
            "alergenos_declarados": alergenos_finales,
            "estado_revision": "pendiente",
            "ficha_tecnica": ficha,
            "fuentes": fuentes,
        })

    carta = {
        "proyecto": "Kitcherry Docs",
        "iteracion": config["iteracion"],
        "negocio": config["negocio"],
        "fecha_generacion": ahora_legible(),
        "fuente_oficial_platos": "carta",
        "fuente_apoyo_alergenos": "tabla_alergenos + inferencia por ingredientes",
        "total_platos": len(platos_estructurados),
        "platos": platos_estructurados,
    }

    guardar_json(OUT_DIR / "carta_kitcherry.json", carta)
    crear_revisiones_base_si_no_existe(config)

    print()
    print("Proceso finalizado.")
    print("JSON generado en: out\\carta_kitcherry.json")
    print(f"Total de platos estructurados: {len(platos_estructurados)}")

    for plato in platos_estructurados:
        precio = plato["precio"] if plato["precio"] else "Sin precio"
        categoria = plato["categoria"] if plato["categoria"] else "Sin categoría"
        total_alergenos = len(plato["alergenos_declarados"])

        print(
            f"- {plato['nombre']} | {categoria} | {precio} | {total_alergenos} alérgenos"
        )

    return {
        "estado": "ok",
        "total": len(platos_estructurados),
    }


# ==========================================================
# OLLAMA / RESÚMENES
# ==========================================================

def llamar_ollama(prompt: str, modelo: str) -> str:
    try:
        import requests

        respuesta = requests.post(
            OLLAMA_URL,
            json={
                "model": modelo,
                "prompt": prompt,
                "stream": False,
            },
            timeout=90,
        )

        if respuesta.status_code != 200:
            return ""

        datos = respuesta.json()
        return str(datos.get("response", "")).strip()
    except Exception:
        return ""


def resumen_sin_ollama(nombre_archivo: str, texto: str) -> str:
    nombre_norm = normalizar(nombre_archivo)

    if "carta" in nombre_norm:
        return (
            "Documento de carta utilizado como fuente principal para extraer platos, "
            "categorías, precios, descripciones e ingredientes principales."
        )

    if "ficha" in nombre_norm:
        return (
            "Documento interno de fichas técnicas utilizado como apoyo para revisar "
            "ingredientes, elaboración, conservación y posibles advertencias alimentarias."
        )

    if "tabla" in nombre_norm or "alergeno" in nombre_norm:
        return (
            "Documento de tabla de alérgenos utilizado como apoyo para contrastar "
            "la información alimentaria de los platos."
        )

    primeras_lineas = " ".join(obtener_lineas_limpias(texto)[:4])
    primeras_lineas = primeras_lineas[:350].strip()

    if primeras_lineas:
        return primeras_lineas

    return "Documento procesado por Kitcherry Docs."


@medir_tiempo
def paso_005_resumir_documentos(config: Dict[str, Any]) -> Dict[str, Any]:
    imprimir_bloque("[005] Resumen de documentos")

    print("Iniciando resumen de documentos...")
    print(f"Modelo: {config['modelo_ollama']}")
    print("Carpeta de entrada: txt_limpio")
    print("Carpeta de salida: summaries")
    print(f"Ollama activo: {'Sí' if EJECUTAR_OLLAMA else 'No'}")
    print()

    txts = sorted(TXT_LIMPIO_DIR.glob("*.txt"))

    registros_jsonl = []
    procesados = 0
    errores = 0

    for indice, txt in enumerate(txts, start=1):
        texto = leer_texto(txt)

        resumen = ""

        if EJECUTAR_OLLAMA:
            prompt = (
                "Resume este documento en español en un único párrafo breve. "
                "El resumen debe explicar para qué sirve dentro de un sistema de carta, platos y alérgenos.\n\n"
                f"Archivo: {txt.name}\n\n"
                f"Contenido:\n{texto[:7000]}"
            )

            resumen = llamar_ollama(prompt, config["modelo_ollama"])

        if not resumen:
            resumen = resumen_sin_ollama(txt.name, texto)

        salida = SUMMARIES_DIR / f"{txt.stem}.summary.txt"
        guardar_texto(salida, resumen)

        registros_jsonl.append({
            "archivo": txt.name,
            "summary": resumen,
        })

        procesados += 1

        print(f"[{indice}/{len(txts)}] OK -> {txt.name}")
        print(f"Resumen guardado en: {salida.relative_to(BASE_DIR)}")

    jsonl_path = SUMMARIES_DIR / "summaries.jsonl"

    with jsonl_path.open("w", encoding="utf-8") as archivo:
        for registro in registros_jsonl:
            archivo.write(json.dumps(registro, ensure_ascii=False) + "\n")

    print()
    print("Proceso finalizado.")
    print(f"Documentos procesados correctamente: {procesados}")
    print(f"Documentos con error: {errores}")
    print("JSONL generado en: summaries\\summaries.jsonl")

    return {
        "estado": "ok",
        "procesados": procesados,
        "errores": errores,
    }


# ==========================================================
# ANÁLISIS DOCUMENTAL
# ==========================================================

def tipo_documento_por_nombre(nombre_archivo: str) -> str:
    nombre_norm = normalizar(nombre_archivo)

    if "carta" in nombre_norm:
        return "carta"

    if "ficha" in nombre_norm:
        return "fichas_tecnicas"

    if "tabla" in nombre_norm or "alergeno" in nombre_norm:
        return "tabla_alergenos"

    return "otro"


def analisis_sin_ollama(nombre_archivo: str, texto: str) -> Dict[str, Any]:
    tipo = tipo_documento_por_nombre(nombre_archivo)

    alergenos_mencionados = detectar_alergenos_en_texto(texto)

    platos_carta = extraer_items_carta(texto) if tipo == "carta" else []

    platos_mencionados = [plato["nombre"] for plato in platos_carta]

    if tipo == "tabla_alergenos":
        datos_platos = cargar_json(OUT_DIR / "platos_detectados.json", {})
        platos_mencionados = [
            plato.get("nombre", "")
            for plato in datos_platos.get("platos", [])
            if plato.get("nombre")
        ]

    if tipo == "fichas_tecnicas":
        datos_platos = cargar_json(OUT_DIR / "platos_detectados.json", {})
        nombres_base = [
            plato.get("nombre", "")
            for plato in datos_platos.get("platos", [])
            if plato.get("nombre")
        ]

        texto_norm = normalizar(texto)

        platos_mencionados = [
            nombre for nombre in nombres_base
            if normalizar(nombre) in texto_norm
        ]

    if tipo == "carta":
        resumen = "Carta del restaurante utilizada para extraer platos, categorías, precios y descripciones."
        uso = "Fuente principal para construir la carta estructurada."
    elif tipo == "fichas_tecnicas":
        resumen = "Fichas técnicas usadas como apoyo interno para ingredientes, elaboraciones y conservación."
        uso = "Apoyo para enriquecer la información interna de cada plato."
    elif tipo == "tabla_alergenos":
        resumen = "Tabla de alérgenos usada como documento de contraste para la información alimentaria."
        uso = "Apoyo para completar y revisar los alérgenos declarados."
    else:
        resumen = "Documento procesado como fuente auxiliar."
        uso = "Apoyo documental dentro de Kitcherry Docs."

    advertencias = [
        "La información de alérgenos debe revisarse por una persona responsable antes de comunicarse al cliente."
    ]

    return {
        "tipo_documento": tipo,
        "nivel_revision_recomendado": "alto",
        "resumen_utilidad": resumen,
        "uso_en_kitcherry": uso,
        "platos_mencionados": platos_mencionados,
        "alergenos_mencionados": alergenos_mencionados,
        "ingredientes_relevantes": [],
        "advertencias": advertencias,
    }


def extraer_json_desde_respuesta(texto: str) -> Optional[Dict[str, Any]]:
    texto = texto.strip()

    if not texto:
        return None

    try:
        datos = json.loads(texto)

        if isinstance(datos, dict):
            return datos
    except Exception:
        pass

    coincidencia = re.search(r"\{.*\}", texto, flags=re.DOTALL)

    if not coincidencia:
        return None

    try:
        datos = json.loads(coincidencia.group(0))

        if isinstance(datos, dict):
            return datos
    except Exception:
        return None

    return None


@medir_tiempo
def paso_006_analisis_documental(config: Dict[str, Any]) -> Dict[str, Any]:
    imprimir_bloque("[006] Análisis documental")

    print("Iniciando análisis documental...")
    print(f"Modelo: {config['modelo_ollama']}")
    print("Carpeta de entrada: txt_limpio")
    print("Salida JSON: out\\analisis_ollama_carta.json")
    print(f"Ollama activo: {'Sí' if EJECUTAR_OLLAMA else 'No'}")
    print()

    txts = sorted(TXT_LIMPIO_DIR.glob("*.txt"))

    resultados = []
    jsonl_registros = []

    procesados = 0
    errores = 0

    for indice, txt in enumerate(txts, start=1):
        texto = leer_texto(txt)

        analisis = None

        if EJECUTAR_OLLAMA:
            prompt = f"""
Analiza el siguiente documento de un restaurante para un sistema llamado Kitcherry Docs.

Devuelve SOLO un JSON válido con esta estructura:
{{
  "tipo_documento": "carta | fichas_tecnicas | tabla_alergenos | otro",
  "nivel_revision_recomendado": "bajo | medio | alto",
  "resumen_utilidad": "resumen breve",
  "uso_en_kitcherry": "cómo se usa dentro del sistema",
  "platos_mencionados": [],
  "alergenos_mencionados": [],
  "ingredientes_relevantes": [],
  "advertencias": []
}}

Archivo: {txt.name}

Contenido:
{texto[:7000]}
"""

            respuesta = llamar_ollama(prompt, config["modelo_ollama"])
            analisis = extraer_json_desde_respuesta(respuesta)

        if not analisis:
            analisis = analisis_sin_ollama(txt.name, texto)

        tipo = analisis.get("tipo_documento", "otro")
        nivel = analisis.get("nivel_revision_recomendado", "alto")

        resultados.append({
            "archivo": txt.name,
            "analisis_ia": analisis,
        })

        jsonl_registros.append({
            "archivo": txt.name,
            "analisis": analisis,
        })

        procesados += 1

        print(f"[{indice}/{len(txts)}] OK -> {txt.name}")
        print(f"Tipo IA: {tipo}")
        print(f"Revisión: {nivel}")
        print()

    salida_json = OUT_DIR / "analisis_ollama_carta.json"
    salida_jsonl = OUT_DIR / "analisis_ollama_carta.jsonl"

    guardar_json(salida_json, {
        "fecha": ahora_legible(),
        "modelo": config["modelo_ollama"],
        "ollama_activo": EJECUTAR_OLLAMA,
        "total_documentos": len(resultados),
        "resultados": resultados,
    })

    with salida_jsonl.open("w", encoding="utf-8") as archivo:
        for registro in jsonl_registros:
            archivo.write(json.dumps(registro, ensure_ascii=False) + "\n")

    print("Proceso finalizado.")
    print(f"Documentos procesados correctamente: {procesados}")
    print(f"Documentos con error: {errores}")
    print("JSON generado en: out\\analisis_ollama_carta.json")
    print("JSONL generado en: out\\analisis_ollama_carta.jsonl")

    return {
        "estado": "ok",
        "procesados": procesados,
        "errores": errores,
    }


# ==========================================================
# PROCESO INTEGRAL
# ==========================================================

def existe_ruta_relativa(ruta: str) -> bool:
    return (BASE_DIR / ruta).exists()


def contar_archivos(carpeta: Path, patron: str) -> int:
    if not carpeta.exists():
        return 0
    return len(list(carpeta.glob(patron)))


def guardar_informe_final(
    config: Dict[str, Any],
    pasos: List[Dict[str, Any]],
) -> Dict[str, Any]:
    carta = cargar_json(OUT_DIR / "carta_kitcherry.json", {})
    revisiones = cargar_json(OUT_DIR / "revisiones_platos.json", {})

    total_revisiones = 0

    if isinstance(revisiones, dict):
        revisiones_dict = revisiones.get("revisiones", {})
        if isinstance(revisiones_dict, dict):
            total_revisiones = len(revisiones_dict)

    informe = {
        "proyecto": "Kitcherry Docs",
        "iteracion": config["iteracion"],
        "negocio": config["negocio"],
        "fecha": ahora_legible(),
        "estado_general": "completado",
        "python": sys.executable,
        "base_dir": str(BASE_DIR),
        "pdf_dir": str(PDF_DIR.relative_to(BASE_DIR)),
        "modelo_ollama": config["modelo_ollama"],
        "ollama_activo": EJECUTAR_OLLAMA,
        "pasos": pasos,
        "resumen": {
            "txt_generados": contar_archivos(TXT_DIR, "*.txt"),
            "txt_limpios_generados": contar_archivos(TXT_LIMPIO_DIR, "*.txt"),
            "platos_en_carta": int(carta.get("total_platos", 0)) if isinstance(carta, dict) else 0,
            "resumenes_generados": contar_archivos(SUMMARIES_DIR, "*.summary.txt"),
            "documentos_analizados": len(cargar_json(OUT_DIR / "analisis_ollama_carta.json", {}).get("resultados", [])),
            "revisiones_guardadas": total_revisiones,
        },
        "comprobacion_salidas": {
            "pdf": PDF_DIR.exists(),
            "txt": TXT_DIR.exists(),
            "txt_limpio": TXT_LIMPIO_DIR.exists(),
            "out/platos_detectados.json": existe_ruta_relativa("out/platos_detectados.json"),
            "out/carta_kitcherry.json": existe_ruta_relativa("out/carta_kitcherry.json"),
            "out/revisiones_platos.json": existe_ruta_relativa("out/revisiones_platos.json"),
            "summaries": SUMMARIES_DIR.exists(),
            "out/analisis_ollama_carta.json": existe_ruta_relativa("out/analisis_ollama_carta.json"),
        },
    }

    guardar_json(OUT_DIR / "proceso_integral_kitcherry.json", informe)

    return informe


def registrar_paso(
    numero: str,
    nombre: str,
    estado: str,
    duracion: float,
    archivo: str = "",
) -> Dict[str, Any]:
    return {
        "numero": numero,
        "nombre": nombre,
        "estado": estado,
        "duracion_segundos": duracion,
        "archivo": archivo,
    }


def mostrar_resumen_final(informe: Dict[str, Any]) -> None:
    imprimir_bloque("RESUMEN FINAL")

    resumen = informe.get("resumen", {})
    comprobacion = informe.get("comprobacion_salidas", {})

    print(f"Estado general: {informe.get('estado_general', 'sin_estado')}")
    print(f"TXT generados: {resumen.get('txt_generados', 0)}")
    print(f"TXT limpios generados: {resumen.get('txt_limpios_generados', 0)}")
    print(f"Platos en carta estructurada: {resumen.get('platos_en_carta', 0)}")
    print(f"Resúmenes generados: {resumen.get('resumenes_generados', 0)}")
    print(f"Documentos analizados: {resumen.get('documentos_analizados', 0)}")
    print(f"Revisiones guardadas: {resumen.get('revisiones_guardadas', 0)}")
    print("Informe guardado en: out\\proceso_integral_kitcherry.json")
    print()
    print("Comprobación de salidas:")

    for ruta, existe in comprobacion.items():
        print(f"- {ruta} -> {'OK' if existe else 'FALTA'}")

    print()
    print("Proceso terminado.")


def main() -> None:
    asegurar_carpetas()

    config = cargar_configuracion()

    imprimir_bloque(f"KITCHERRY DOCS - PROCESO FINAL {config['negocio'].upper()}")

    print(f"Inicio del proceso: {ahora_legible()}")
    print(f"Python usado: {sys.executable}")
    print(f"Carpeta base: {BASE_DIR}")
    print(f"Carpeta PDF: {PDF_DIR.relative_to(BASE_DIR)}")
    print(f"Negocio: {config['negocio']}")
    print(f"Iteración: {config['iteracion']}")
    print(f"Modelo Ollama: {config['modelo_ollama']}")
    print(f"Ejecutar pasos con Ollama: {'Sí' if EJECUTAR_OLLAMA else 'No'}")

    pasos = []

    resultado_001, duracion_001 = paso_001_leer_pdfs()
    pasos.append(registrar_paso(
        "001",
        "Lectura de documentos PDF",
        resultado_001.get("estado", "sin_estado"),
        duracion_001,
        "pdf",
    ))
    print()
    print(f"Estado del paso 001: {resultado_001.get('estado')}")
    print(f"Duración: {duracion_001} segundos")

    resultado_002, duracion_002 = paso_002_limpiar_textos()
    pasos.append(registrar_paso(
        "002",
        "Limpieza de texto",
        resultado_002.get("estado", "sin_estado"),
        duracion_002,
        "txt_limpio",
    ))
    print()
    print(f"Estado del paso 002: {resultado_002.get('estado')}")
    print(f"Duración: {duracion_002} segundos")

    resultado_003, duracion_003 = paso_003_detectar_platos()
    pasos.append(registrar_paso(
        "003",
        "Detección oficial de platos desde carta",
        resultado_003.get("estado", "sin_estado"),
        duracion_003,
        "out/platos_detectados.json",
    ))
    print()
    print(f"Estado del paso 003: {resultado_003.get('estado')}")
    print(f"Duración: {duracion_003} segundos")

    resultado_004, duracion_004 = paso_004_generar_carta_estructurada(config)
    pasos.append(registrar_paso(
        "004",
        "Generación de carta estructurada",
        resultado_004.get("estado", "sin_estado"),
        duracion_004,
        "out/carta_kitcherry.json",
    ))
    print()
    print(f"Estado del paso 004: {resultado_004.get('estado')}")
    print(f"Duración: {duracion_004} segundos")

    resultado_005, duracion_005 = paso_005_resumir_documentos(config)
    pasos.append(registrar_paso(
        "005",
        "Resumen de documentos",
        resultado_005.get("estado", "sin_estado"),
        duracion_005,
        "summaries",
    ))
    print()
    print(f"Estado del paso 005: {resultado_005.get('estado')}")
    print(f"Duración: {duracion_005} segundos")

    resultado_006, duracion_006 = paso_006_analisis_documental(config)
    pasos.append(registrar_paso(
        "006",
        "Análisis documental",
        resultado_006.get("estado", "sin_estado"),
        duracion_006,
        "out/analisis_ollama_carta.json",
    ))
    print()
    print(f"Estado del paso 006: {resultado_006.get('estado')}")
    print(f"Duración: {duracion_006} segundos")

    informe = guardar_informe_final(config, pasos)
    mostrar_resumen_final(informe)


if __name__ == "__main__":
    main()