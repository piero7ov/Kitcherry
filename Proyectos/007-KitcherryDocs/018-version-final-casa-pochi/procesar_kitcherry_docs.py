# ==========================================================
# KITCHERRY DOCS - PROCESO FINAL CASA POCHI
# Archivo: procesar_kitcherry_docs.py
# Objetivo:
# - Leer los 3 PDF desde /pdf
# - Convertirlos a TXT
# - Limpiar los TXT
# - Detectar la lista oficial de platos desde la tabla de alérgenos
# - Enriquecer la carta con precios, categorías e ingredientes desde la carta
# - Enriquecer con fichas técnicas desde el documento de fichas
# - Generar JSON final para index.php
# - Generar resúmenes y análisis con Ollama si está activado
# ==========================================================

from __future__ import annotations

import json
import os
import re
import sys
import time
import unicodedata
from datetime import datetime
from pathlib import Path
from typing import Any


# ==========================================================
# CONFIGURACIÓN GENERAL
# ==========================================================

PROYECTO = "Kitcherry Docs"
ITERACION = "018-version-final-casa-pochi"

BASE_DIR = Path(__file__).resolve().parent

PDF_DIR = BASE_DIR / "pdf"
TXT_DIR = BASE_DIR / "txt"
TXT_LIMPIO_DIR = BASE_DIR / "txt_limpio"
OUT_DIR = BASE_DIR / "out"
SUMMARIES_DIR = BASE_DIR / "summaries"

OUT_INFORME_LIMPIEZA = OUT_DIR / "informe_limpieza.json"
OUT_PLATOS_DETECTADOS = OUT_DIR / "platos_detectados.json"
OUT_CARTA = OUT_DIR / "carta_kitcherry.json"
OUT_ANALISIS = OUT_DIR / "analisis_ollama_carta.json"
OUT_ANALISIS_JSONL = OUT_DIR / "analisis_ollama_carta.jsonl"
OUT_PROCESO = OUT_DIR / "proceso_integral_kitcherry.json"
OUT_REVISIONES = OUT_DIR / "revisiones_platos.json"
OUT_SUMMARIES_JSONL = SUMMARIES_DIR / "summaries.jsonl"

OLLAMA_URL = os.environ.get("KITCHERRY_OLLAMA_URL", "http://localhost:11434/api/generate")
OLLAMA_MODEL = os.environ.get("KITCHERRY_OLLAMA_MODEL", "llama3:latest")
EJECUTAR_OLLAMA = os.environ.get("KITCHERRY_EJECUTAR_OLLAMA", "1").strip() != "0"

MAX_CHARS_OLLAMA = 12000
TIMEOUT_OLLAMA = 300
SLEEP_OLLAMA = 0.2

ALERGENOS_OFICIALES = [
    "Gluten",
    "Crustáceos",
    "Huevos",
    "Pescado",
    "Cacahuetes",
    "Soja",
    "Leche",
    "Frutos de cáscara",
    "Apio",
    "Mostaza",
    "Sésamo",
    "Sulfitos",
    "Altramuces",
    "Moluscos",
]

CATEGORIAS_CANONICAS = {
    "entrante": "Entrantes",
    "entrantes": "Entrantes",
    "principal": "Platos principales",
    "principales": "Platos principales",
    "plato principal": "Platos principales",
    "platos principales": "Platos principales",
    "postre": "Postres",
    "postres": "Postres",
    "bebida": "Bebidas",
    "bebidas": "Bebidas",
}


# ==========================================================
# COMPATIBILIDAD CONSOLA WINDOWS
# ==========================================================

try:
    sys.stdout.reconfigure(encoding="utf-8")
except Exception:
    pass


# ==========================================================
# FUNCIONES BÁSICAS
# ==========================================================

def ahora() -> str:
    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def duracion(inicio: float) -> float:
    return round(time.time() - inicio, 2)


def imprimir_bloque(titulo: str) -> None:
    print()
    print("=" * 70)
    print(titulo)
    print("=" * 70)


def asegurar_carpetas() -> None:
    PDF_DIR.mkdir(exist_ok=True)
    TXT_DIR.mkdir(exist_ok=True)
    TXT_LIMPIO_DIR.mkdir(exist_ok=True)
    OUT_DIR.mkdir(exist_ok=True)
    SUMMARIES_DIR.mkdir(exist_ok=True)


def leer_txt(ruta: Path) -> str:
    return ruta.read_text(encoding="utf-8", errors="ignore")


def guardar_txt(ruta: Path, contenido: str) -> None:
    ruta.parent.mkdir(parents=True, exist_ok=True)
    ruta.write_text(contenido, encoding="utf-8")


def cargar_json(ruta: Path, defecto: Any = None) -> Any:
    if defecto is None:
        defecto = {}

    if not ruta.exists():
        return defecto

    contenido = ruta.read_text(encoding="utf-8", errors="ignore").strip()

    if not contenido:
        return defecto

    try:
        return json.loads(contenido)
    except json.JSONDecodeError:
        return defecto


def guardar_json(ruta: Path, datos: Any) -> None:
    ruta.parent.mkdir(parents=True, exist_ok=True)
    ruta.write_text(
        json.dumps(datos, ensure_ascii=False, indent=4),
        encoding="utf-8"
    )


def append_jsonl(ruta: Path, datos: Any) -> None:
    ruta.parent.mkdir(parents=True, exist_ok=True)

    with ruta.open("a", encoding="utf-8") as archivo:
        archivo.write(json.dumps(datos, ensure_ascii=False) + "\n")


def normalizar(texto: str) -> str:
    texto = texto or ""
    texto = texto.lower().strip()
    texto = unicodedata.normalize("NFD", texto)
    texto = "".join(c for c in texto if unicodedata.category(c) != "Mn")
    texto = re.sub(r"\s+", " ", texto)
    return texto


def crear_slug(texto: str) -> str:
    texto = normalizar(texto)
    texto = re.sub(r"[^a-z0-9]+", "-", texto)
    texto = texto.strip("-")
    return texto if texto else "sin-nombre"


def limpiar_lineas(texto: str) -> list[str]:
    lineas = []

    for linea in texto.splitlines():
        linea = linea.strip()

        if not linea:
            continue

        linea = re.sub(r"\s+", " ", linea)
        lineas.append(linea)

    return lineas


def limpiar_texto_general(texto: str) -> str:
    texto = texto.replace("\r\n", "\n").replace("\r", "\n")

    lineas_limpias = []

    for linea in texto.splitlines():
        linea = linea.strip()

        if not linea:
            continue

        if re.match(r"^=+\s*PÁGINA\s+\d+\s*=+$", linea, re.IGNORECASE):
            continue

        if re.match(r"^Página\s+\d+$", linea, re.IGNORECASE):
            continue

        if normalizar(linea) == normalizar("KITCHERRY - DOCUMENTO DE PRUEBA"):
            continue

        linea = re.sub(r"\s+", " ", linea)
        lineas_limpias.append(linea)

    return "\n".join(lineas_limpias).strip() + "\n"


def detectar_tipo_documento_por_nombre(nombre: str) -> str:
    nombre_normalizado = normalizar(nombre)

    if "carta" in nombre_normalizado or "menu" in nombre_normalizado:
        return "carta"

    if "ficha" in nombre_normalizado or "tecnica" in nombre_normalizado:
        return "fichas_tecnicas"

    if "alergeno" in nombre_normalizado or "alergenos" in nombre_normalizado:
        return "tabla_alergenos"

    return "otro"


def extraer_precio(texto: str) -> str:
    patron = re.search(r"(\d{1,3}(?:[.,]\d{2})\s*€)", texto)

    if not patron:
        return ""

    precio = patron.group(1)
    precio = precio.replace(".", ",")
    precio = re.sub(r"\s+", " ", precio).strip()

    return precio


def quitar_precio(texto: str) -> str:
    return re.sub(r"\d{1,3}(?:[.,]\d{2})\s*€", "", texto).strip()


def es_linea_con_precio(texto: str) -> bool:
    return bool(re.search(r"\d{1,3}(?:[.,]\d{2})\s*€", texto))


def normalizar_categoria(texto: str) -> str:
    clave = normalizar(texto)
    return CATEGORIAS_CANONICAS.get(clave, "")


def es_categoria(texto: str) -> bool:
    return normalizar_categoria(texto) != ""


def dividir_ingredientes(descripcion: str) -> list[str]:
    descripcion = descripcion.strip()

    if not descripcion:
        return []

    descripcion = re.sub(r"\.$", "", descripcion)

    partes = []

    for fragmento in descripcion.split(","):
        fragmento = fragmento.strip()

        if not fragmento:
            continue

        subpartes = re.split(r"\s+y\s+", fragmento)

        for subparte in subpartes:
            subparte = subparte.strip(" .")

            if subparte:
                partes.append(subparte)

    ingredientes = []

    for parte in partes:
        if parte and parte not in ingredientes:
            ingredientes.append(parte)

    return ingredientes


def contiene_nombre_plato(linea: str, nombre_plato: str) -> bool:
    linea_n = normalizar(linea)
    plato_n = normalizar(nombre_plato)

    return plato_n in linea_n


def limpiar_descripcion_carta(texto: str) -> str:
    texto = texto.strip()

    cortes = [
        "Nota: documento de prueba",
        "Documento de prueba",
        "La información de alérgenos debe ser revisada",
    ]

    for corte in cortes:
        pos = normalizar(texto).find(normalizar(corte))

        if pos != -1:
            texto = texto[:pos].strip()

    texto = re.sub(r"\s+", " ", texto).strip(" .")

    return texto


def lista_unica(lista: list[str]) -> list[str]:
    resultado = []

    for item in lista:
        item = str(item).strip()

        if item and item not in resultado:
            resultado.append(item)

    return resultado


# ==========================================================
# LECTURA DE PDF
# ==========================================================

def extraer_texto_pdf(ruta_pdf: Path) -> str:
    try:
        from pypdf import PdfReader
    except ImportError:
        try:
            from PyPDF2 import PdfReader
        except ImportError as error:
            raise RuntimeError(
                "No se encontró pypdf ni PyPDF2. Instala dependencias con: "
                "py -m pip install -r requirements.txt"
            ) from error

    reader = PdfReader(str(ruta_pdf))

    partes = []

    for indice, pagina in enumerate(reader.pages, 1):
        texto_pagina = pagina.extract_text() or ""
        partes.append(f"===== PÁGINA {indice} =====\n\n{texto_pagina.strip()}\n")

    return "\n".join(partes).strip() + "\n"


# ==========================================================
# PASO 001 - PDF A TXT
# ==========================================================

def paso_001_leer_documentos() -> dict[str, Any]:
    imprimir_bloque("[001] Lectura de documentos PDF")
    inicio = time.time()

    resultados = []

    pdfs = sorted(PDF_DIR.glob("*.pdf"))

    if not pdfs:
        print(f"No se encontraron PDF en: {PDF_DIR}")

    for pdf in pdfs:
        try:
            texto = extraer_texto_pdf(pdf)

            salida = TXT_DIR / f"{pdf.stem}.txt"
            guardar_txt(salida, texto)

            resultados.append({
                "archivo": pdf.name,
                "salida": salida.name,
                "estado": "ok"
            })

            print(f"OK -> {pdf.name} convertido a {salida.name}")

        except Exception as error:
            resultados.append({
                "archivo": pdf.name,
                "estado": "error",
                "error": str(error)
            })

            print(f"ERROR -> {pdf.name}: {error}")

    estado = "ok" if resultados and all(r["estado"] == "ok" for r in resultados) else "error"
    tiempo = duracion(inicio)

    print()
    print(f"Estado del paso 001: {estado}")
    print(f"Duración: {tiempo} segundos")

    return {
        "numero": "001",
        "nombre": "Lectura de documentos PDF",
        "archivo": "procesar_kitcherry_docs.py",
        "estado": estado,
        "duracion_segundos": tiempo,
        "resultados": resultados
    }


# ==========================================================
# PASO 002 - LIMPIEZA DE TEXTO
# ==========================================================

def paso_002_limpiar_texto() -> dict[str, Any]:
    imprimir_bloque("[002] Limpieza de texto")
    inicio = time.time()

    resultados = []

    txts = sorted(TXT_DIR.glob("*.txt"))

    if not txts:
        print(f"No se encontraron TXT en: {TXT_DIR}")

    for txt in txts:
        try:
            texto = leer_txt(txt)
            texto_limpio = limpiar_texto_general(texto)

            salida = TXT_LIMPIO_DIR / f"{txt.stem}_limpio.txt"
            guardar_txt(salida, texto_limpio)

            resultados.append({
                "archivo": txt.name,
                "salida": salida.name,
                "estado": "ok",
                "caracteres_originales": len(texto),
                "caracteres_limpios": len(texto_limpio)
            })

            print(f"OK -> {txt.name} limpiado como {salida.name}")

        except Exception as error:
            resultados.append({
                "archivo": txt.name,
                "estado": "error",
                "error": str(error)
            })

            print(f"ERROR -> {txt.name}: {error}")

    informe = {
        "proyecto": PROYECTO,
        "iteracion": ITERACION,
        "descripcion": "Informe de limpieza de textos extraídos desde PDF.",
        "fecha_generacion": ahora(),
        "total_documentos": len(resultados),
        "resultados": resultados
    }

    guardar_json(OUT_INFORME_LIMPIEZA, informe)

    estado = "ok" if resultados and all(r["estado"] == "ok" for r in resultados) else "error"
    tiempo = duracion(inicio)

    print()
    print("Proceso finalizado.")
    print(f"Informe generado en: {OUT_INFORME_LIMPIEZA.relative_to(BASE_DIR)}")
    print()
    print(f"Estado del paso 002: {estado}")
    print(f"Duración: {tiempo} segundos")

    return {
        "numero": "002",
        "nombre": "Limpieza de texto",
        "archivo": "procesar_kitcherry_docs.py",
        "estado": estado,
        "duracion_segundos": tiempo,
        "resultados": resultados
    }


# ==========================================================
# PASO 003 - DETECCIÓN OFICIAL DESDE TABLA DE ALÉRGENOS
# ==========================================================

def parsear_alergenos_de_linea(linea: str) -> list[str]:
    linea_n = normalizar(linea)

    if "sin alergenos" in linea_n or "sin alergenos declarados" in linea_n:
        return []

    encontrados = []

    for alergeno in ALERGENOS_OFICIALES:
        if normalizar(alergeno) in linea_n:
            encontrados.append(alergeno)

    return encontrados


def parsear_platos_alergenos_declarados(texto: str, fuente: str) -> list[dict[str, Any]]:
    lineas = limpiar_lineas(texto)
    platos = []

    for indice, linea in enumerate(lineas):
        if "alérgenos declarados:" not in linea.lower() and "alergenos declarados:" not in linea.lower():
            continue

        if indice == 0:
            continue

        nombre = lineas[indice - 1].strip()

        if not nombre:
            continue

        if normalizar(nombre).startswith("tabla de"):
            continue

        if normalizar(nombre).startswith("documento"):
            continue

        partes = re.split(r"al[eé]rgenos declarados:", linea, flags=re.IGNORECASE)
        texto_alergenos = partes[1].strip() if len(partes) > 1 else ""

        alergenos = parsear_alergenos_de_linea(texto_alergenos)

        platos.append({
            "nombre": nombre,
            "alergenos_declarados": alergenos,
            "estado_revision": "pendiente",
            "fuente": fuente
        })

    return platos


def unificar_platos_detectados(platos: list[dict[str, Any]]) -> list[dict[str, Any]]:
    por_clave = {}

    for plato in platos:
        nombre = str(plato.get("nombre", "")).strip()

        if not nombre:
            continue

        clave = crear_slug(nombre)

        if clave not in por_clave:
            por_clave[clave] = {
                "nombre": nombre,
                "alergenos_declarados": [],
                "estado_revision": "pendiente",
                "fuente": plato.get("fuente", "")
            }

        alergenos = plato.get("alergenos_declarados", [])

        if isinstance(alergenos, list):
            por_clave[clave]["alergenos_declarados"] = lista_unica(
                por_clave[clave]["alergenos_declarados"] + alergenos
            )

    return list(por_clave.values())


def paso_003_detectar_platos_alergenos() -> dict[str, Any]:
    imprimir_bloque("[003] Detección de platos y alérgenos")
    inicio = time.time()

    txts = sorted(TXT_LIMPIO_DIR.glob("*.txt"))
    platos_totales = []
    resultados = []

    if not txts:
        print(f"No hay TXT limpios en la carpeta: {TXT_LIMPIO_DIR}")

    for txt in txts:
        try:
            tipo_documento = detectar_tipo_documento_por_nombre(txt.name)

            # IMPORTANTE:
            # La lista oficial de platos sale SOLO de la tabla de alérgenos.
            # La carta y las fichas técnicas NO crean platos nuevos.
            # Solo se usan después para completar información.
            if tipo_documento != "tabla_alergenos":
                resultados.append({
                    "archivo": txt.name,
                    "estado": "omitido",
                    "motivo": "No se usa para detectar la lista oficial de platos.",
                    "platos_detectados": 0
                })

                print(f"OMITIDO -> {txt.name}: no se usa para detectar platos")
                continue

            texto = leer_txt(txt)
            platos = parsear_platos_alergenos_declarados(texto, txt.name)
            platos_totales.extend(platos)

            resultados.append({
                "archivo": txt.name,
                "estado": "ok",
                "platos_detectados": len(platos)
            })

            print(f"OK -> {txt.name}: {len(platos)} platos detectados")

        except Exception as error:
            resultados.append({
                "archivo": txt.name,
                "estado": "error",
                "error": str(error),
                "platos_detectados": 0
            })

            print(f"ERROR -> {txt.name}: {error}")

    platos_unificados = unificar_platos_detectados(platos_totales)

    salida = {
        "proyecto": PROYECTO,
        "iteracion": ITERACION,
        "descripcion": "Detección oficial de platos y alérgenos desde la tabla de alérgenos.",
        "fecha_generacion": ahora(),
        "total_platos": len(platos_unificados),
        "platos": platos_unificados
    }

    guardar_json(OUT_PLATOS_DETECTADOS, salida)

    documentos_ok = sum(1 for r in resultados if r["estado"] == "ok")
    documentos_error = sum(1 for r in resultados if r["estado"] == "error")

    estado = "ok" if documentos_ok > 0 and documentos_error == 0 else "error"
    tiempo = duracion(inicio)

    print()
    print("Proceso finalizado.")
    print(f"Total de platos detectados: {len(platos_unificados)}")
    print(f"JSON generado en: {OUT_PLATOS_DETECTADOS.relative_to(BASE_DIR)}")
    print()
    print(f"Estado del paso 003: {estado}")
    print(f"Duración: {tiempo} segundos")

    return {
        "numero": "003",
        "nombre": "Detección de platos y alérgenos",
        "archivo": "procesar_kitcherry_docs.py",
        "estado": estado,
        "duracion_segundos": tiempo,
        "resultados": resultados,
        "total_platos": len(platos_unificados)
    }


# ==========================================================
# PASO 004 - GENERACIÓN DE CARTA ESTRUCTURADA
# ==========================================================

def obtener_txt_limpio_por_tipo(tipo: str) -> tuple[Path | None, str]:
    for txt in sorted(TXT_LIMPIO_DIR.glob("*.txt")):
        if detectar_tipo_documento_por_nombre(txt.name) == tipo:
            return txt, leer_txt(txt)

    return None, ""


def es_cabecera_carta(linea: str) -> bool:
    linea_n = normalizar(linea)

    if linea_n in ["plato", "precio"]:
        return True

    if "descripcion" in linea_n and "ingredientes" in linea_n:
        return True

    if linea_n.startswith("nota"):
        return True

    if "documento ficticio" in linea_n:
        return True

    if "carta ficticia" in linea_n:
        return True

    if "carta de prueba" in linea_n:
        return True

    if "restaurante casa pochi" in linea_n:
        return True

    if "extraccion de texto" in linea_n:
        return True

    return False


def buscar_plato_oficial_desde_indice(
    lineas: list[str],
    indice: int,
    nombres_oficiales: list[str]
) -> tuple[str, int] | None:
    """
    Busca si desde una línea empieza un plato oficial.
    Soporta nombres partidos en varias líneas:
    Croquetas caseras de
    jamón

    También soporta nombres ampliados en carta:
    Pan con tomate y aceite de oliva
    cuando el plato oficial es:
    Pan con tomate
    """

    ventana = []

    for desplazamiento in range(0, 4):
        indice_actual = indice + desplazamiento

        if indice_actual >= len(lineas):
            break

        linea = lineas[indice_actual]

        if es_categoria(linea):
            break

        if es_cabecera_carta(linea):
            break

        if es_linea_con_precio(linea):
            break

        ventana.append(linea)

        texto_ventana = normalizar(" ".join(ventana))

        for nombre in nombres_oficiales:
            nombre_n = normalizar(nombre)

            if nombre_n and nombre_n in texto_ventana:
                return nombre, desplazamiento + 1

    return None


def buscar_precio_desde_indice(
    lineas: list[str],
    indice_inicio: int,
    limite: int = 5
) -> tuple[str, int | None]:
    """
    Busca el primer precio cercano después del nombre del plato.
    """

    for indice in range(indice_inicio, min(len(lineas), indice_inicio + limite)):
        precio = extraer_precio(lineas[indice])

        if precio:
            return precio, indice

    return "", None


def parece_item_no_oficial_con_precio(lineas: list[str], indice: int) -> bool:
    """
    Detecta elementos de carta que no están en la tabla oficial,
    como Refresco de cola o Café con leche.

    Esto evita que se metan dentro de la descripción de Cerveza artesanal.
    """

    if indice + 1 >= len(lineas):
        return False

    linea = lineas[indice]

    if es_categoria(linea):
        return False

    if es_cabecera_carta(linea):
        return False

    if es_linea_con_precio(linea):
        return False

    return es_linea_con_precio(lineas[indice + 1])


def construir_descripcion_desde_precio(
    lineas: list[str],
    indice_precio: int,
    nombres_oficiales: list[str]
) -> str:
    descripcion = []

    for indice in range(indice_precio + 1, len(lineas)):
        linea = lineas[indice]

        if es_categoria(linea):
            break

        if es_cabecera_carta(linea):
            break

        if es_linea_con_precio(linea):
            break

        if buscar_plato_oficial_desde_indice(lineas, indice, nombres_oficiales):
            break

        if parece_item_no_oficial_con_precio(lineas, indice):
            break

        descripcion.append(linea)

        if len(descripcion) >= 4:
            break

    return limpiar_descripcion_carta(" ".join(descripcion))


def parsear_info_carta(texto_carta: str, nombres_oficiales: list[str]) -> dict[str, dict[str, Any]]:
    lineas = limpiar_lineas(texto_carta)
    info = {}

    categoria_actual = ""
    indice = 0

    while indice < len(lineas):
        linea = lineas[indice]

        categoria_detectada = normalizar_categoria(linea)

        if categoria_detectada:
            categoria_actual = categoria_detectada
            indice += 1
            continue

        if es_cabecera_carta(linea):
            indice += 1
            continue

        plato_detectado = buscar_plato_oficial_desde_indice(lineas, indice, nombres_oficiales)

        if not plato_detectado:
            indice += 1
            continue

        nombre_oficial, lineas_nombre = plato_detectado
        clave = crear_slug(nombre_oficial)

        precio, indice_precio = buscar_precio_desde_indice(
            lineas,
            indice + lineas_nombre
        )

        descripcion = ""

        if indice_precio is not None:
            descripcion = construir_descripcion_desde_precio(
                lineas,
                indice_precio,
                nombres_oficiales
            )

        ingredientes = dividir_ingredientes(descripcion)

        info[clave] = {
            "categoria": categoria_actual,
            "precio": precio,
            "descripcion": descripcion,
            "ingredientes_detectados": ingredientes,
            "fuente": "carta"
        }

        if indice_precio is not None:
            indice = indice_precio + 1
        else:
            indice += lineas_nombre

    return info


def obtener_bloque_ficha(
    lineas: list[str],
    indice_inicio: int,
    nombres_oficiales: list[str]
) -> list[str]:
    bloque = [lineas[indice_inicio]]

    for i in range(indice_inicio + 1, len(lineas)):
        linea = lineas[i]

        for nombre in nombres_oficiales:
            if contiene_nombre_plato(linea, nombre):
                return bloque

        bloque.append(linea)

    return bloque


def extraer_campo_ficha(bloque_texto: str, etiqueta: str, siguientes: list[str]) -> str:
    etiqueta_regex = re.escape(etiqueta)

    posibles_siguientes = "|".join(re.escape(s) for s in siguientes)

    patron = rf"{etiqueta_regex}\s*:\s*(.*?)(?=(?:{posibles_siguientes})\s*:|$)"

    coincidencia = re.search(
        patron,
        bloque_texto,
        flags=re.IGNORECASE | re.DOTALL
    )

    if not coincidencia:
        return ""

    valor = coincidencia.group(1)
    valor = re.sub(r"\s+", " ", valor).strip(" .")

    return valor


def parsear_fichas_tecnicas(texto_fichas: str, nombres_oficiales: list[str]) -> dict[str, dict[str, str]]:
    lineas = limpiar_lineas(texto_fichas)
    info = {}

    etiquetas = [
        "Raciones",
        "Ingredientes",
        "Elaboración",
        "Elaboracion",
        "Conservación",
        "Conservacion",
        "Alérgenos",
        "Alergenos",
        "Alérgenos texto",
        "Alergenos texto",
    ]

    usados = set()

    for indice, linea in enumerate(lineas):
        for nombre in nombres_oficiales:
            clave = crear_slug(nombre)

            if clave in usados:
                continue

            if not contiene_nombre_plato(linea, nombre):
                continue

            bloque = obtener_bloque_ficha(lineas, indice, nombres_oficiales)
            bloque_texto = "\n".join(bloque)

            raciones = extraer_campo_ficha(bloque_texto, "Raciones", etiquetas)
            ingredientes = extraer_campo_ficha(bloque_texto, "Ingredientes", etiquetas)

            elaboracion = extraer_campo_ficha(bloque_texto, "Elaboración", etiquetas)

            if not elaboracion:
                elaboracion = extraer_campo_ficha(bloque_texto, "Elaboracion", etiquetas)

            conservacion = extraer_campo_ficha(bloque_texto, "Conservación", etiquetas)

            if not conservacion:
                conservacion = extraer_campo_ficha(bloque_texto, "Conservacion", etiquetas)

            alergenos_texto = extraer_campo_ficha(bloque_texto, "Alérgenos", etiquetas)

            if not alergenos_texto:
                alergenos_texto = extraer_campo_ficha(bloque_texto, "Alergenos", etiquetas)

            ficha = {
                "raciones": raciones,
                "ingredientes": ingredientes,
                "elaboracion": elaboracion,
                "conservacion": conservacion,
                "alergenos_texto": alergenos_texto
            }

            if any(valor.strip() for valor in ficha.values()):
                info[clave] = ficha
                usados.add(clave)

            break

    return info


def crear_revisiones_si_no_existen() -> None:
    if OUT_REVISIONES.exists():
        return

    revisiones = {
        "proyecto": PROYECTO,
        "iteracion": ITERACION,
        "descripcion": "Archivo independiente para guardar revisiones manuales sin modificar carta_kitcherry.json.",
        "ultima_actualizacion": "",
        "total_revisiones": 0,
        "revisiones": {}
    }

    guardar_json(OUT_REVISIONES, revisiones)


def paso_004_generar_carta_estructurada() -> dict[str, Any]:
    imprimir_bloque("[004] Generación de carta estructurada")
    inicio = time.time()

    datos_platos = cargar_json(OUT_PLATOS_DETECTADOS, {"platos": []})
    platos_base = datos_platos.get("platos", [])

    if not isinstance(platos_base, list):
        platos_base = []

    nombres_oficiales = [str(p.get("nombre", "")).strip() for p in platos_base if p.get("nombre")]

    carta_path, texto_carta = obtener_txt_limpio_por_tipo("carta")
    fichas_path, texto_fichas = obtener_txt_limpio_por_tipo("fichas_tecnicas")

    info_carta = parsear_info_carta(texto_carta, nombres_oficiales) if texto_carta else {}
    info_fichas = parsear_fichas_tecnicas(texto_fichas, nombres_oficiales) if texto_fichas else {}

    platos_estructurados = []

    for indice, plato in enumerate(platos_base, 1):
        nombre = str(plato.get("nombre", "")).strip()
        clave = crear_slug(nombre)

        carta_plato = info_carta.get(clave, {})
        ficha_plato = info_fichas.get(clave, {})

        fuentes = []

        fuente_tabla = plato.get("fuente", "")

        if fuente_tabla:
            fuentes.append(fuente_tabla)

        if carta_plato:
            fuentes.append(carta_path.name if carta_path else "carta")

        if ficha_plato:
            fuentes.append(fichas_path.name if fichas_path else "fichas_tecnicas")

        ficha_final = {
            "raciones": ficha_plato.get("raciones", ""),
            "ingredientes": ficha_plato.get("ingredientes", ""),
            "elaboracion": ficha_plato.get("elaboracion", ""),
            "conservacion": ficha_plato.get("conservacion", ""),
            "alergenos_texto": ficha_plato.get("alergenos_texto", "")
        }

        plato_final = {
            "id": indice,
            "nombre": nombre,
            "categoria": carta_plato.get("categoria", ""),
            "precio": carta_plato.get("precio", ""),
            "descripcion": carta_plato.get("descripcion", ""),
            "ingredientes_detectados": carta_plato.get("ingredientes_detectados", []),
            "alergenos_declarados": plato.get("alergenos_declarados", []),
            "estado_revision": "pendiente",
            "ficha_tecnica": ficha_final,
            "fuentes": lista_unica(fuentes)
        }

        platos_estructurados.append(plato_final)

    salida = {
        "proyecto": PROYECTO,
        "iteracion": ITERACION,
        "descripcion": "Carta estructurada con platos oficiales, precios, categorías, ingredientes, alérgenos y fichas técnicas.",
        "fecha_generacion": ahora(),
        "negocio": "Casa Pochi",
        "estado_general": "borrador_pendiente_revision",
        "total_platos": len(platos_estructurados),
        "platos": platos_estructurados
    }

    guardar_json(OUT_CARTA, salida)
    crear_revisiones_si_no_existen()

    tiempo = duracion(inicio)
    estado = "ok" if len(platos_estructurados) > 0 else "error"

    print()
    print("Proceso finalizado.")
    print(f"JSON generado en: {OUT_CARTA.relative_to(BASE_DIR)}")
    print(f"Total de platos estructurados: {len(platos_estructurados)}")

    for plato in platos_estructurados:
        categoria = plato["categoria"] if plato["categoria"] else "Sin categoría"
        precio = plato["precio"] if plato["precio"] else "Sin precio"
        total_alergenos = len(plato.get("alergenos_declarados", []))

        print(f"- {plato['nombre']} | {categoria} | {precio} | {total_alergenos} alérgenos")

    print()
    print(f"Estado del paso 004: {estado}")
    print(f"Duración: {tiempo} segundos")

    return {
        "numero": "004",
        "nombre": "Generación de carta estructurada",
        "archivo": "procesar_kitcherry_docs.py",
        "estado": estado,
        "duracion_segundos": tiempo,
        "total_platos": len(platos_estructurados)
    }


# ==========================================================
# OLLAMA
# ==========================================================

def llamar_ollama(prompt: str, temperatura: float = 0.2) -> str:
    try:
        import requests
    except ImportError as error:
        raise RuntimeError(
            "No se encontró requests. Instala dependencias con: "
            "py -m pip install -r requirements.txt"
        ) from error

    payload = {
        "model": OLLAMA_MODEL,
        "prompt": prompt,
        "stream": False,
        "options": {
            "temperature": temperatura
        }
    }

    respuesta = requests.post(OLLAMA_URL, json=payload, timeout=TIMEOUT_OLLAMA)
    respuesta.raise_for_status()

    return respuesta.json().get("response", "").strip()


def extraer_json_de_respuesta(texto: str) -> dict[str, Any] | None:
    texto = texto.strip()

    try:
        datos = json.loads(texto)

        if isinstance(datos, dict):
            return datos
    except json.JSONDecodeError:
        pass

    inicio = texto.find("{")
    fin = texto.rfind("}")

    if inicio == -1 or fin == -1 or fin <= inicio:
        return None

    posible_json = texto[inicio:fin + 1]

    try:
        datos = json.loads(posible_json)

        if isinstance(datos, dict):
            return datos
    except json.JSONDecodeError:
        return None

    return None


# ==========================================================
# PASO 005 - RESÚMENES CON OLLAMA
# ==========================================================

def resumir_documento_con_ollama(texto: str) -> str:
    prompt = (
        "Resume en UN SOLO PÁRRAFO en español el siguiente documento. "
        "Sé fiel al contenido, no inventes datos y menciona el tema principal y puntos clave.\n\n"
        f"{texto[:MAX_CHARS_OLLAMA]}"
    )

    return llamar_ollama(prompt, temperatura=0.2)


def paso_005_resumir_documentos_ollama() -> dict[str, Any]:
    imprimir_bloque("[005] Resumen de documentos con Ollama")
    inicio = time.time()

    if not EJECUTAR_OLLAMA:
        print("Paso omitido porque KITCHERRY_EJECUTAR_OLLAMA=0")

        return {
            "numero": "005",
            "nombre": "Resumen de documentos con Ollama",
            "archivo": "procesar_kitcherry_docs.py",
            "estado": "omitido",
            "duracion_segundos": duracion(inicio),
            "motivo": "Ollama desactivado"
        }

    if OUT_SUMMARIES_JSONL.exists():
        OUT_SUMMARIES_JSONL.unlink()

    resultados = []
    txts = sorted(TXT_LIMPIO_DIR.glob("*.txt"))

    print("Iniciando resumen de documentos con Ollama...")
    print(f"Modelo: {OLLAMA_MODEL}")
    print(f"Carpeta de entrada: {TXT_LIMPIO_DIR.relative_to(BASE_DIR)}")
    print(f"Carpeta de salida: {SUMMARIES_DIR.relative_to(BASE_DIR)}")
    print()

    for indice, txt in enumerate(txts, 1):
        try:
            texto = leer_txt(txt)
            resumen = resumir_documento_con_ollama(texto)

            salida = SUMMARIES_DIR / f"{txt.stem}.summary.txt"
            guardar_txt(salida, resumen + "\n")

            registro = {
                "proyecto": PROYECTO,
                "iteracion": ITERACION,
                "archivo": txt.name,
                "modelo": OLLAMA_MODEL,
                "fecha_generacion": ahora(),
                "summary": resumen
            }

            append_jsonl(OUT_SUMMARIES_JSONL, registro)

            resultados.append({
                "archivo": txt.name,
                "estado": "ok",
                "salida": salida.name
            })

            print(f"[{indice}/{len(txts)}] OK -> {txt.name}")
            print(f"Resumen guardado en: {salida.relative_to(BASE_DIR)}")

        except Exception as error:
            resultados.append({
                "archivo": txt.name,
                "estado": "error",
                "error": str(error)
            })

            print(f"[{indice}/{len(txts)}] ERROR -> {txt.name}: {error}")

        time.sleep(SLEEP_OLLAMA)

    documentos_ok = sum(1 for r in resultados if r["estado"] == "ok")
    documentos_error = sum(1 for r in resultados if r["estado"] == "error")
    estado = "ok" if documentos_ok > 0 and documentos_error == 0 else "error"
    tiempo = duracion(inicio)

    print()
    print("Proceso finalizado.")
    print(f"Documentos procesados correctamente: {documentos_ok}")
    print(f"Documentos con error: {documentos_error}")
    print(f"JSONL generado en: {OUT_SUMMARIES_JSONL.relative_to(BASE_DIR)}")
    print()
    print(f"Estado del paso 005: {estado}")
    print(f"Duración: {tiempo} segundos")

    return {
        "numero": "005",
        "nombre": "Resumen de documentos con Ollama",
        "archivo": "procesar_kitcherry_docs.py",
        "estado": estado,
        "duracion_segundos": tiempo,
        "documentos_ok": documentos_ok,
        "documentos_error": documentos_error,
        "resultados": resultados
    }


# ==========================================================
# PASO 006 - ANÁLISIS DOCUMENTAL CON OLLAMA
# ==========================================================

def analisis_determinista_documento(nombre_archivo: str, texto: str) -> dict[str, Any]:
    tipo = detectar_tipo_documento_por_nombre(nombre_archivo)
    texto_n = normalizar(texto)

    alergenos = []

    for alergeno in ALERGENOS_OFICIALES:
        if normalizar(alergeno) in texto_n:
            alergenos.append(alergeno)

    platos_detectados = []

    datos_carta = cargar_json(OUT_CARTA, {"platos": []})
    platos = datos_carta.get("platos", [])

    if isinstance(platos, list):
        for plato in platos:
            nombre = str(plato.get("nombre", "")).strip()

            if nombre and normalizar(nombre) in texto_n:
                platos_detectados.append(nombre)

    return {
        "tipo_detectado_por_nombre": tipo,
        "alergenos_detectados_por_palabras": alergenos,
        "posibles_platos_detectados": platos_detectados
    }


def crear_analisis_fallback(
    archivo: str,
    tipo: str,
    analisis_determinista: dict[str, Any],
    motivo: str
) -> dict[str, Any]:
    return {
        "archivo": archivo,
        "tipo_documento": tipo,
        "resumen_utilidad": "Documento utilizado como fuente para estructurar información de carta, platos, ingredientes y alérgenos.",
        "platos_mencionados": analisis_determinista.get("posibles_platos_detectados", []),
        "alergenos_mencionados": analisis_determinista.get("alergenos_detectados_por_palabras", []),
        "ingredientes_relevantes": [],
        "advertencias": [
            motivo
        ],
        "uso_en_kitcherry": "Sirve como documento de apoyo para el módulo de carta, platos y alérgenos.",
        "nivel_revision_recomendado": "alto",
        "motivo_revision": "La información alimentaria y de alérgenos debe ser revisada por una persona responsable antes de usarse con clientes."
    }


def analizar_documento_con_ollama(
    archivo: str,
    texto: str,
    analisis_determinista: dict[str, Any]
) -> dict[str, Any]:
    tipo_detectado = analisis_determinista.get("tipo_detectado_por_nombre", "otro")

    prompt = f"""
Analiza el siguiente documento de un sistema llamado Kitcherry Docs.

Devuelve SOLO un JSON válido, sin explicaciones antes ni después.

El JSON debe tener exactamente estas claves:
{{
  "archivo": "{archivo}",
  "tipo_documento": "carta | fichas_tecnicas | tabla_alergenos | otro",
  "resumen_utilidad": "Resumen breve de para qué sirve este documento dentro de Kitcherry Docs.",
  "platos_mencionados": [],
  "alergenos_mencionados": [],
  "ingredientes_relevantes": [],
  "advertencias": [],
  "uso_en_kitcherry": "Explica cómo puede utilizarse este documento dentro del módulo de carta, platos y alérgenos.",
  "nivel_revision_recomendado": "bajo | medio | alto",
  "motivo_revision": "Explica por qué necesita ese nivel de revisión."
}}

Tipo detectado por nombre de archivo: {tipo_detectado}

Documento:
{texto[:MAX_CHARS_OLLAMA]}
"""

    respuesta = llamar_ollama(prompt, temperatura=0.1)
    datos = extraer_json_de_respuesta(respuesta)

    if datos is None:
        fallback = crear_analisis_fallback(
            archivo,
            tipo_detectado,
            analisis_determinista,
            "No se pudo interpretar la respuesta de Ollama como JSON válido."
        )

        fallback["respuesta_original_modelo"] = respuesta
        return fallback

    if datos.get("tipo_documento", "otro") == "otro" and tipo_detectado != "otro":
        datos["tipo_documento"] = tipo_detectado

    campos_lista = [
        "platos_mencionados",
        "alergenos_mencionados",
        "ingredientes_relevantes",
        "advertencias",
    ]

    for campo in campos_lista:
        if not isinstance(datos.get(campo), list):
            datos[campo] = []

    if not datos.get("nivel_revision_recomendado"):
        datos["nivel_revision_recomendado"] = "alto"

    if not datos.get("motivo_revision"):
        datos["motivo_revision"] = "La información alimentaria y de alérgenos debe revisarse antes de usarse con clientes."

    return datos


def paso_006_analisis_ollama_carta() -> dict[str, Any]:
    imprimir_bloque("[006] Análisis documental con Ollama")
    inicio = time.time()

    if OUT_ANALISIS_JSONL.exists():
        OUT_ANALISIS_JSONL.unlink()

    resultados = []
    txts = sorted(TXT_LIMPIO_DIR.glob("*.txt"))

    if not EJECUTAR_OLLAMA:
        print("Ollama desactivado. Se generará análisis determinista básico.")

    else:
        print("Iniciando análisis documental con Ollama...")
        print(f"Modelo: {OLLAMA_MODEL}")
        print(f"Carpeta de entrada: {TXT_LIMPIO_DIR.relative_to(BASE_DIR)}")
        print(f"Salida JSON: {OUT_ANALISIS.relative_to(BASE_DIR)}")
        print()

    for indice, txt in enumerate(txts, 1):
        try:
            texto = leer_txt(txt)
            determinista = analisis_determinista_documento(txt.name, texto)
            tipo = determinista.get("tipo_detectado_por_nombre", "otro")

            if EJECUTAR_OLLAMA:
                analisis_ia = analizar_documento_con_ollama(txt.name, texto, determinista)
            else:
                analisis_ia = crear_analisis_fallback(
                    txt.name,
                    tipo,
                    determinista,
                    "Análisis generado sin Ollama porque el paso de IA estaba desactivado."
                )

            registro = {
                "proyecto": PROYECTO,
                "iteracion": ITERACION,
                "archivo": txt.name,
                "modelo": OLLAMA_MODEL if EJECUTAR_OLLAMA else "sin_ollama",
                "fecha_generacion": ahora(),
                "analisis_determinista": determinista,
                "analisis_ia": analisis_ia
            }

            resultados.append(registro)
            append_jsonl(OUT_ANALISIS_JSONL, registro)

            print(f"[{indice}/{len(txts)}] OK -> {txt.name}")
            print(f"Tipo IA: {analisis_ia.get('tipo_documento', 'otro')}")
            print(f"Revisión: {analisis_ia.get('nivel_revision_recomendado', 'alto')}")
            print()

        except Exception as error:
            determinista = analisis_determinista_documento(txt.name, leer_txt(txt) if txt.exists() else "")

            analisis_ia = crear_analisis_fallback(
                txt.name,
                determinista.get("tipo_detectado_por_nombre", "otro"),
                determinista,
                f"Error durante el análisis: {error}"
            )

            registro = {
                "proyecto": PROYECTO,
                "iteracion": ITERACION,
                "archivo": txt.name,
                "modelo": OLLAMA_MODEL if EJECUTAR_OLLAMA else "sin_ollama",
                "fecha_generacion": ahora(),
                "analisis_determinista": determinista,
                "analisis_ia": analisis_ia
            }

            resultados.append(registro)
            append_jsonl(OUT_ANALISIS_JSONL, registro)

            print(f"[{indice}/{len(txts)}] ERROR -> {txt.name}: {error}")

        time.sleep(SLEEP_OLLAMA if EJECUTAR_OLLAMA else 0)

    documentos_ok = len(resultados)
    documentos_error = 0

    salida = {
        "proyecto": PROYECTO,
        "iteracion": ITERACION,
        "descripcion": "Análisis documental sobre cartas, fichas técnicas y tablas de alérgenos.",
        "modelo": OLLAMA_MODEL if EJECUTAR_OLLAMA else "sin_ollama",
        "fecha_generacion": ahora(),
        "total_documentos": len(txts),
        "documentos_ok": documentos_ok,
        "documentos_error": documentos_error,
        "resultados": resultados
    }

    guardar_json(OUT_ANALISIS, salida)

    estado = "ok" if documentos_ok > 0 else "error"
    tiempo = duracion(inicio)

    print("Proceso finalizado.")
    print(f"Documentos procesados correctamente: {documentos_ok}")
    print(f"Documentos con error: {documentos_error}")
    print(f"JSON generado en: {OUT_ANALISIS.relative_to(BASE_DIR)}")
    print(f"JSONL generado en: {OUT_ANALISIS_JSONL.relative_to(BASE_DIR)}")
    print()
    print(f"Estado del paso 006: {estado}")
    print(f"Duración: {tiempo} segundos")

    return {
        "numero": "006",
        "nombre": "Análisis documental con Ollama",
        "archivo": "procesar_kitcherry_docs.py",
        "estado": estado,
        "duracion_segundos": tiempo,
        "documentos_ok": documentos_ok,
        "documentos_error": documentos_error
    }


# ==========================================================
# RESUMEN FINAL
# ==========================================================

def contar_archivos(carpeta: Path, patron: str) -> int:
    if not carpeta.exists():
        return 0

    return len(list(carpeta.glob(patron)))


def generar_resumen_final(pasos: list[dict[str, Any]]) -> None:
    imprimir_bloque("RESUMEN FINAL")

    carta = cargar_json(OUT_CARTA, {"platos": []})
    analisis = cargar_json(OUT_ANALISIS, {"resultados": []})
    revisiones = cargar_json(OUT_REVISIONES, {"revisiones": {}})

    platos = carta.get("platos", [])
    resultados_analisis = analisis.get("resultados", [])
    revisiones_guardadas = revisiones.get("revisiones", {})

    estado_general = "completado"

    for paso in pasos:
        if paso.get("estado") == "error":
            estado_general = "completado_con_errores"
            break

    resumen = {
        "proyecto": PROYECTO,
        "iteracion": ITERACION,
        "descripcion": "Proceso final completo de Kitcherry Docs para Casa Pochi.",
        "fecha_generacion": ahora(),
        "estado_general": estado_general,
        "base_dir": str(BASE_DIR),
        "pdf_dir": str(PDF_DIR.relative_to(BASE_DIR)),
        "modelo_ollama": OLLAMA_MODEL,
        "ollama_activado": EJECUTAR_OLLAMA,
        "pasos": pasos,
        "salidas": {
            "txt_generados": contar_archivos(TXT_DIR, "*.txt"),
            "txt_limpios_generados": contar_archivos(TXT_LIMPIO_DIR, "*.txt"),
            "platos_en_carta": len(platos) if isinstance(platos, list) else 0,
            "resumenes_generados": contar_archivos(SUMMARIES_DIR, "*.summary.txt"),
            "documentos_analizados": len(resultados_analisis) if isinstance(resultados_analisis, list) else 0,
            "revisiones_guardadas": len(revisiones_guardadas) if isinstance(revisiones_guardadas, dict) else 0
        },
        "archivos_principales": {
            "platos_detectados": str(OUT_PLATOS_DETECTADOS.relative_to(BASE_DIR)),
            "carta_kitcherry": str(OUT_CARTA.relative_to(BASE_DIR)),
            "revisiones_platos": str(OUT_REVISIONES.relative_to(BASE_DIR)),
            "analisis_ollama_carta": str(OUT_ANALISIS.relative_to(BASE_DIR)),
            "proceso_integral": str(OUT_PROCESO.relative_to(BASE_DIR))
        }
    }

    guardar_json(OUT_PROCESO, resumen)

    print(f"Estado general: {estado_general}")
    print(f"TXT generados: {resumen['salidas']['txt_generados']}")
    print(f"TXT limpios generados: {resumen['salidas']['txt_limpios_generados']}")
    print(f"Platos en carta estructurada: {resumen['salidas']['platos_en_carta']}")
    print(f"Resúmenes generados: {resumen['salidas']['resumenes_generados']}")
    print(f"Documentos analizados con Ollama: {resumen['salidas']['documentos_analizados']}")
    print(f"Revisiones guardadas: {resumen['salidas']['revisiones_guardadas']}")
    print(f"Informe guardado en: {OUT_PROCESO.relative_to(BASE_DIR)}")
    print()
    print("Comprobación de salidas:")

    salidas = [
        PDF_DIR,
        TXT_DIR,
        TXT_LIMPIO_DIR,
        OUT_PLATOS_DETECTADOS,
        OUT_CARTA,
        OUT_REVISIONES,
        SUMMARIES_DIR,
        OUT_ANALISIS,
    ]

    for salida in salidas:
        estado = "OK" if salida.exists() else "FALTA"
        print(f"- {salida.relative_to(BASE_DIR)} -> {estado}")


# ==========================================================
# MAIN
# ==========================================================

def main() -> None:
    asegurar_carpetas()

    print()
    print("=" * 70)
    print("KITCHERRY DOCS - PROCESO FINAL CASA POCHI")
    print("=" * 70)
    print(f"Inicio del proceso: {ahora()}")
    print(f"Python usado: {sys.executable}")
    print(f"Carpeta base: {BASE_DIR}")
    print(f"Carpeta PDF: {PDF_DIR.relative_to(BASE_DIR)}")
    print(f"Modelo Ollama: {OLLAMA_MODEL}")
    print(f"Ejecutar pasos con Ollama: {'Sí' if EJECUTAR_OLLAMA else 'No'}")

    pasos = []

    pasos.append(paso_001_leer_documentos())
    pasos.append(paso_002_limpiar_texto())
    pasos.append(paso_003_detectar_platos_alergenos())
    pasos.append(paso_004_generar_carta_estructurada())
    pasos.append(paso_005_resumir_documentos_ollama())
    pasos.append(paso_006_analisis_ollama_carta())

    generar_resumen_final(pasos)

    print()
    print("Proceso terminado.")


if __name__ == "__main__":
    main()