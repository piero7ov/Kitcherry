#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
==========================================================
KITCHERRY KITCHEN ASSISTANT
Archivo: 019-api_flask.py
==========================================================

API Flask independiente para usar el modelo entrenado de Kitcherry.

Esta versión funciona dentro de la carpeta:
019_eficacia-con-flask/

Usa archivos locales:
- 006-inferencia.py
- modelo-kitcherry-cocina-fusionado/
- entrenamiento/
- venv/
"""

import os
import sys
import importlib.util
from datetime import datetime

from flask import Flask, request, jsonify
from transformers import AutoTokenizer, AutoModelForCausalLM


# ==========================================================
# FORZAR UTF-8 EN WINDOWS
# ==========================================================

try:
    sys.stdin.reconfigure(encoding="utf-8", errors="replace")
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass


# ==========================================================
# RUTAS LOCALES
# ==========================================================

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))

INFERENCIA_PATH = os.path.join(SCRIPT_DIR, "006-inferencia.py")


# ==========================================================
# CARGAR MÓDULO 006-INFERENCIA.PY
# ==========================================================

def cargar_modulo_inferencia():
    """
    Carga 006-inferencia.py como módulo aunque tenga guion en el nombre.
    """

    if not os.path.isfile(INFERENCIA_PATH):
        raise FileNotFoundError(
            f"No se encontró 006-inferencia.py en: {INFERENCIA_PATH}"
        )

    spec = importlib.util.spec_from_file_location(
        "kitcherry_inferencia",
        INFERENCIA_PATH,
    )

    modulo = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(modulo)

    return modulo


# ==========================================================
# INICIALIZAR FLASK
# ==========================================================

app = Flask(__name__)


# ==========================================================
# VARIABLES GLOBALES
# ==========================================================

modulo_inferencia = None
model = None
tokenizer = None
conocimiento = []
modelo_cargado = False
info_dispositivo = ""


# ==========================================================
# CARGA ÚNICA DEL MODELO
# ==========================================================

def cargar_modelo_una_vez():
    """
    Carga el modelo, tokenizer y conocimiento una sola vez.
    """

    global modulo_inferencia
    global model
    global tokenizer
    global conocimiento
    global modelo_cargado
    global info_dispositivo

    if modelo_cargado:
        return

    inicio = datetime.now()

    print("=" * 70)
    print("KITCHERRY KITCHEN ASSISTANT - API FLASK INDEPENDIENTE")
    print("=" * 70)
    print(f"Carpeta actual: {SCRIPT_DIR}")
    print(f"Inferencia principal: {INFERENCIA_PATH}")

    modulo_inferencia = cargar_modulo_inferencia()

    modulo_inferencia.comprobar_modelo_fusionado()

    device_map, dtype, dispositivo = modulo_inferencia.obtener_configuracion_dispositivo()
    info_dispositivo = f"{dispositivo} | {dtype}"

    print("\nDispositivo utilizado:")
    print(f" - {dispositivo}")
    print(f" - Precisión: {dtype}")

    print("\nCargando conocimiento local...")

    if modulo_inferencia.USE_LOCAL_KNOWLEDGE:
        conocimiento = modulo_inferencia.cargar_conocimiento_local()

    print(f"Registros de conocimiento cargados: {len(conocimiento)}")

    print("\nCargando tokenizer...")

    tokenizer = AutoTokenizer.from_pretrained(
        modulo_inferencia.MODEL_DIR,
        use_fast=True,
    )

    if tokenizer.pad_token is None:
        tokenizer.pad_token = tokenizer.eos_token

    print("\nCargando modelo fusionado...")

    model = AutoModelForCausalLM.from_pretrained(
        modulo_inferencia.MODEL_DIR,
        dtype=dtype,
        device_map=device_map,
    )

    model.eval()

    modelo_cargado = True

    fin = datetime.now()

    print("\nModelo cargado correctamente en Flask.")
    print(f"Tiempo de carga: {fin - inicio}")
    print("=" * 70)


# ==========================================================
# CORS
# ==========================================================

@app.after_request
def aplicar_cors(respuesta):
    respuesta.headers["Access-Control-Allow-Origin"] = "*"
    respuesta.headers["Access-Control-Allow-Headers"] = "Content-Type"
    respuesta.headers["Access-Control-Allow-Methods"] = "GET, POST, OPTIONS"
    return respuesta


# ==========================================================
# RUTAS
# ==========================================================

@app.route("/", methods=["GET"])
def home():
    return jsonify({
        "ok": True,
        "mensaje": "Kitcherry Kitchen Assistant API Flask activa.",
        "modelo_cargado": modelo_cargado,
        "dispositivo": info_dispositivo,
    })


@app.route("/estado", methods=["GET"])
def estado():
    return jsonify({
        "ok": True,
        "modelo_cargado": modelo_cargado,
        "registros_conocimiento": len(conocimiento),
        "dispositivo": info_dispositivo,
    })


@app.route("/chat", methods=["POST", "OPTIONS"])
def chat():
    if request.method == "OPTIONS":
        return jsonify({"ok": True})

    try:
        cargar_modelo_una_vez()

        datos = request.get_json(silent=True) or {}
        mensaje = str(datos.get("mensaje", "")).strip()

        if mensaje == "":
            return jsonify({
                "ok": False,
                "respuesta": "Escribe una consulta válida."
            })

        respuesta = modulo_inferencia.responder(
            model=model,
            tokenizer=tokenizer,
            pregunta=mensaje,
            conocimiento=conocimiento,
        )

        return jsonify({
            "ok": True,
            "respuesta": respuesta,
        })

    except Exception as error:
        return jsonify({
            "ok": False,
            "respuesta": (
                "No se ha podido procesar la respuesta del asistente. "
                "Revisa que la API Flask, el modelo fusionado y los archivos JSONL estén disponibles."
            ),
            "error": str(error),
        })


# ==========================================================
# PROGRAMA PRINCIPAL
# ==========================================================

if __name__ == "__main__":
    cargar_modelo_una_vez()

    app.run(
        host="127.0.0.1",
        port=5000,
        debug=False,
    )