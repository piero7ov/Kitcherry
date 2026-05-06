from __future__ import annotations

import json
import time
from datetime import datetime
from pathlib import Path
from typing import Any, Dict

from .config import (
    BASE_DIR, PDF_DIR, TXT_DIR, TXT_LIMPIO_DIR, OUT_DIR, SUMMARIES_DIR,
    CONFIG_PATH, OLLAMA_MODEL, EJECUTAR_OLLAMA,
    inferir_negocio_desde_carpeta,
)

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

def cargar_configuracion() -> Dict[str, Any]:
    config = cargar_json(CONFIG_PATH, {})

    if not isinstance(config, dict):
        config = {}

    negocio = config.get("negocio") or inferir_negocio_desde_carpeta()
    iteracion = config.get("iteracion") or BASE_DIR.name
    modelo = config.get("modelo_ollama") or OLLAMA_MODEL
    nombre_proceso = config.get("nombre_proceso") or f"KITCHERRY DOCS - PROCESO FINAL {negocio.upper()}"
    ejecutar_ollama = config.get("ejecutar_ollama", EJECUTAR_OLLAMA)

    return {
        "proyecto": config.get("proyecto", "Kitcherry Docs"),
        "negocio": negocio,
        "iteracion": iteracion,
        "nombre_proceso": nombre_proceso,
        "modelo_ollama": modelo,
        "ejecutar_ollama": bool(ejecutar_ollama),
    }
