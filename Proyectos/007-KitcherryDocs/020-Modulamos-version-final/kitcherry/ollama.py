from __future__ import annotations

import json
import re
from typing import Any, Dict, List, Optional

from .config import OLLAMA_URL, OLLAMA_MODEL, EJECUTAR_OLLAMA, OUT_DIR
from .utils import cargar_json
from .extraccion import (
    normalizar, obtener_lineas_limpias, detectar_alergenos_en_texto, extraer_items_carta,
)

# ==========================================================
# LLAMADA A OLLAMA
# ==========================================================

def llamar_ollama(prompt: str, modelo: str) -> str:
    try:
        import requests
        respuesta = requests.post(
            OLLAMA_URL,
            json={"model": modelo, "prompt": prompt, "stream": False},
            timeout=90,
        )
        if respuesta.status_code != 200:
            return ""
        datos = respuesta.json()
        return str(datos.get("response", "")).strip()
    except Exception:
        return ""


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


# ==========================================================
# RESÚMENES SIN OLLAMA
# ==========================================================

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


# ==========================================================
# ANÁLISIS SIN OLLAMA
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
