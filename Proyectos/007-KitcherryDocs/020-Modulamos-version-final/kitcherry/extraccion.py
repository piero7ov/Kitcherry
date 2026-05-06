from __future__ import annotations

import re
from pathlib import Path
from typing import Any, Dict, List, Optional

from .config import (
    ALERGENOS_ORDEN, ALERGENOS_KEYWORDS, CATEGORIAS_VALIDAS,
    LINEAS_IGNORABLES_EXACTAS, TXT_LIMPIO_DIR,
)

# ==========================================================
# NORMALIZACIÓN DE TEXTO
# ==========================================================

def normalizar(texto: str) -> str:
    texto = texto.lower()
    reemplazos = {"á": "a", "é": "e", "í": "i", "ó": "o", "ú": "u", "ü": "u", "ñ": "n"}
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
        r"^kitcherry docs\s*-\s*documentos de prueba realistas\s*", "",
        nombre, flags=re.IGNORECASE,
    )
    nombre = re.sub(r"^p[aá]gina\s+\d+\s*", "", nombre, flags=re.IGNORECASE)
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
        "servido", "servida", "cocinado", "cocinada", "frito", "frita",
        "horneado", "horneada", "mezcla de", "crema de", "seleccion de",
        "selección de", "contiene", "puede contener",
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
# LIMPIEZA DE TEXTO
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
# EXTRACCIÓN DESDE LA CARTA
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
    vistos: set = set()
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
            posiciones.append({"nombre": nombre, "posicion": posicion})
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
        resultado[nombre] = detectar_alergenos_en_texto(segmento)
    return resultado


# ==========================================================
# APOYO DESDE FICHAS TÉCNICAS
# ==========================================================

def extraer_campo_ficha(segmento: str, inicios: List[str], finales: List[str]) -> str:
    posicion_inicio = -1
    etiqueta_inicio = ""
    for inicio in inicios:
        inicio_norm = normalizar(inicio)
        pos = segmento.find(inicio_norm)
        if pos >= 0 and (posicion_inicio == -1 or pos < posicion_inicio):
            posicion_inicio = pos
            etiqueta_inicio = inicio_norm
    if posicion_inicio == -1:
        return ""
    inicio_contenido = posicion_inicio + len(etiqueta_inicio)
    posicion_fin = len(segmento)
    for final in finales:
        final_norm = normalizar(final)
        pos = segmento.find(final_norm, inicio_contenido)
        if pos >= 0 and pos < posicion_fin:
            posicion_fin = pos
    contenido = segmento[inicio_contenido:posicion_fin]
    contenido = contenido.strip(" :.-\n\t")
    return contenido[:900].strip()


def extraer_fichas_tecnicas(texto_fichas: str, platos: List[Dict[str, Any]]) -> Dict[str, Dict[str, str]]:
    fichas: Dict[str, Dict[str, str]] = {}
    if not texto_fichas.strip():
        return fichas
    nombres = [str(plato.get("nombre", "")) for plato in platos]
    segmentos = encontrar_segmentos_por_plato(texto_fichas, nombres)
    for nombre in nombres:
        segmento = segmentos.get(nombre, "")
        if not segmento:
            fichas[nombre] = {}
            continue
        fichas[nombre] = {
            "raciones": extraer_campo_ficha(segmento, ["raciones", "racion"], ["ingredientes", "elaboracion", "conservacion", "alergenos"]),
            "ingredientes": extraer_campo_ficha(segmento, ["ingredientes"], ["elaboracion", "conservacion", "alergenos"]),
            "elaboracion": extraer_campo_ficha(segmento, ["elaboracion", "elaboración", "preparacion", "preparación"], ["conservacion", "conservación", "alergenos"]),
            "conservacion": extraer_campo_ficha(segmento, ["conservacion", "conservación"], ["alergenos", "alérgenos"]),
            "alergenos_texto": extraer_campo_ficha(segmento, ["alergenos", "alérgenos"], ["trazas", "observaciones"]),
        }
    return fichas


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
    if len(alergenos_tabla) > 8 and len(alergenos_inferidos) <= 6:
        return alergenos_inferidos
    return combinar_alergenos(alergenos_tabla, alergenos_inferidos)
