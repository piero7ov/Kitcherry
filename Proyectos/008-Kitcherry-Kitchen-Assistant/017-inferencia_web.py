#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
==========================================================
KITCHERRY KITCHEN ASSISTANT
==========================================================

Puente para usar 006-inferencia.py desde PHP.

Funcionamiento:
1. Recibe un mensaje desde PHP por STDIN en formato JSON.
2. Importa la lógica de 006-inferencia.py.
3. Carga el modelo fusionado y el conocimiento local.
4. Genera una respuesta usando responder().
5. Devuelve JSON limpio en UTF-8 para que PHP lo muestre en la interfaz web.
"""

import os
import sys
import json
import importlib.util
import contextlib

from transformers import AutoTokenizer, AutoModelForCausalLM


# ==========================================================
# FORZAR UTF-8 EN WINDOWS / APACHE
# ==========================================================

try:
    sys.stdin.reconfigure(encoding="utf-8", errors="replace")
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass


# ==========================================================
# CONFIGURACIÓN
# ==========================================================

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
INFERENCIA_PATH = os.path.join(SCRIPT_DIR, "006-inferencia.py")


# ==========================================================
# FUNCIONES AUXILIARES
# ==========================================================

def cargar_modulo_inferencia():
    """
    Carga 006-inferencia.py como módulo aunque el nombre tenga guion.
    """

    if not os.path.isfile(INFERENCIA_PATH):
        raise FileNotFoundError(
            f"No se encontró el archivo de inferencia principal: {INFERENCIA_PATH}"
        )

    spec = importlib.util.spec_from_file_location(
        "kitcherry_inferencia",
        INFERENCIA_PATH,
    )

    modulo = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(modulo)

    return modulo


def leer_mensaje_entrada() -> str:
    """
    Lee el mensaje enviado desde PHP.

    PHP enviará un JSON por STDIN:
    {
        "mensaje": "texto del usuario"
    }
    """

    entrada = sys.stdin.read().strip()

    if not entrada:
        return ""

    try:
        datos = json.loads(entrada)
        return str(datos.get("mensaje", "")).strip()
    except Exception:
        return entrada.strip()


def responder_json(ok: bool, respuesta: str, error: str = ""):
    """
    Devuelve una respuesta JSON limpia en UTF-8.
    """

    salida = {
        "ok": ok,
        "respuesta": respuesta,
    }

    if error:
        salida["error"] = error

    print(
        json.dumps(
            salida,
            ensure_ascii=False,
        ),
        flush=True
    )


# ==========================================================
# PROGRAMA PRINCIPAL
# ==========================================================

def main():
    try:
        pregunta = leer_mensaje_entrada()

        if not pregunta:
            responder_json(
                ok=False,
                respuesta="Escribe una consulta válida.",
            )
            return

        modulo = cargar_modulo_inferencia()

        # Redirigimos prints internos a STDERR para que STDOUT sea solo JSON.
        with contextlib.redirect_stdout(sys.stderr):

            modulo.comprobar_modelo_fusionado()

            device_map, dtype, dispositivo = modulo.obtener_configuracion_dispositivo()

            conocimiento = []

            if modulo.USE_LOCAL_KNOWLEDGE:
                conocimiento = modulo.cargar_conocimiento_local()

            tokenizer = AutoTokenizer.from_pretrained(
                modulo.MODEL_DIR,
                use_fast=True,
            )

            if tokenizer.pad_token is None:
                tokenizer.pad_token = tokenizer.eos_token

            model = AutoModelForCausalLM.from_pretrained(
                modulo.MODEL_DIR,
                dtype=dtype,
                device_map=device_map,
            )

            model.eval()

            respuesta = modulo.responder(
                model=model,
                tokenizer=tokenizer,
                pregunta=pregunta,
                conocimiento=conocimiento,
            )

        responder_json(
            ok=True,
            respuesta=respuesta,
        )

    except Exception as error:
        responder_json(
            ok=False,
            respuesta=(
                "No se ha podido procesar la respuesta del asistente. "
                "Revisa que el modelo fusionado, el entorno virtual y los archivos JSONL estén disponibles."
            ),
            error=str(error),
        )


if __name__ == "__main__":
    main()