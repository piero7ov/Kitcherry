#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import os
import sys
import torch
from transformers import AutoTokenizer, AutoModelForCausalLM


# ==========================================================
# RUTAS
# ==========================================================

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))

MODEL_PATH = os.path.join(
    SCRIPT_DIR,
    "outputs",
    "modelo-kitcherry-fusionado"
)


# ==========================================================
# PROMPT DEL SISTEMA
# ==========================================================

SYSTEM_PROMPT = (
    "Eres el asistente virtual de Kitcherry, una empresa de herramientas "
    "de software para hostelería desarrollada por PieroDev. Respondes en "
    "español de forma clara, profesional y cercana. "
    "Solo debes responder con información confirmada sobre Kitcherry. "
    "No debes inventar ubicaciones, precios, funcionalidades oficiales, "
    "clientes reales, teléfonos, correos ni datos empresariales no confirmados. "
    "Si no dispones de información concreta, debes decirlo claramente. "
    "Kitcherry no es un restaurante ni vende comida; es un proyecto de software "
    "para hostelería orientado a pequeños negocios."
)


# ==========================================================
# CAPA DE SEGURIDAD
# ==========================================================

def normalizar_texto(texto):
    texto = texto.lower()

    reemplazos = {
        "á": "a",
        "é": "e",
        "í": "i",
        "ó": "o",
        "ú": "u",
        "ñ": "n"
    }

    for original, reemplazo in reemplazos.items():
        texto = texto.replace(original, reemplazo)

    return texto


def respuesta_segura(pregunta):
    pregunta_normalizada = normalizar_texto(pregunta)

    temas_precio = [
        "cuanto cuesta",
        "cuanto vale",
        "precio",
        "precios",
        "tarifa",
        "tarifas",
        "plan de pago",
        "planes de pago",
        "suscripcion",
        "coste",
        "costo"
    ]

    temas_ubicacion = [
        "oficina",
        "oficinas",
        "direccion",
        "donde queda",
        "donde esta",
        "ubicacion",
        "localizacion",
        "sede",
        "domicilio fiscal"
    ]

    temas_app = [
        "app movil",
        "aplicacion movil",
        "app oficial",
        "aplicacion oficial"
    ]

    temas_contacto = [
        "telefono",
        "numero de telefono",
        "correo",
        "email",
        "contacto oficial"
    ]

    temas_clientes = [
        "clientes reales",
        "clientes actuales",
        "casos reales",
        "casos de exito",
        "empresas que usan",
        "empresas usan",
        "restaurantes que usan",
        "restaurantes usan",
        "que restaurantes usan",
        "que empresas usan",
        "quien usa",
        "quienes usan",
        "negocios que usan",
        "negocios usan",
        "clientes tiene",
        "que clientes tiene",
        "lo usan",
        "usan kitcherry"
    ]

    temas_servicios = [
        "que servicios ofrece",
        "servicios ofrece",
        "que herramientas ofrece",
        "herramientas ofrece",
        "que productos ofrece",
        "productos ofrece",
        "que hace kitcherry",
        "que puede hacer kitcherry"
    ]

    temas_restaurante = [
        "kitcherry es un restaurante",
        "es un restaurante",
        "vende comida",
        "kitcherry vende comida",
        "es una cadena de restaurantes",
        "es un local de comida"
    ]

    if any(t in pregunta_normalizada for t in temas_precio):
        return "No dispongo de información concreta sobre los precios de Kitcherry."

    if any(t in pregunta_normalizada for t in temas_ubicacion):
        return "No dispongo de información concreta sobre oficinas físicas o dirección oficial de Kitcherry."

    if any(t in pregunta_normalizada for t in temas_app):
        return "No dispongo de información concreta sobre una app móvil oficial de Kitcherry."

    if any(t in pregunta_normalizada for t in temas_contacto):
        return "No dispongo de información concreta sobre teléfono, correo o contacto oficial de Kitcherry."

    if any(t in pregunta_normalizada for t in temas_clientes):
        return "No dispongo de información concreta sobre clientes reales o casos reales de Kitcherry."

    if any(t in pregunta_normalizada for t in temas_restaurante):
        return (
            "No. Kitcherry no es un restaurante ni vende comida. "
            "Es una empresa de herramientas de software para hostelería desarrollada por PieroDev."
        )

    if any(t in pregunta_normalizada for t in temas_servicios):
        return (
            "Kitcherry ofrece herramientas de software para hostelería, como chatbot "
            "de atención al cliente, gestión de reservas, automatización de consultas, "
            "información de carta y alérgenos, apoyo interno para cocina, consulta rápida "
            "para personal de sala y organización de tareas del negocio."
        )

    return None


# ==========================================================
# CARGAR MODELO
# ==========================================================

def cargar_modelo():
    if not os.path.isdir(MODEL_PATH):
        raise FileNotFoundError(
            f"No se encontró el modelo fusionado:\n{MODEL_PATH}\n\n"
            "Primero ejecuta 004-fusionar.py"
        )

    use_cuda = torch.cuda.is_available()

    if use_cuda:
        dtype = torch.float16
        device_map = "auto"
    else:
        dtype = torch.float32
        device_map = {"": "cpu"}

    print("🧠 Cargando modelo fusionado Kitcherry...")
    print(f"📁 Modelo: {MODEL_PATH}")
    print(f"⚙️ CUDA disponible: {use_cuda}")

    tokenizer = AutoTokenizer.from_pretrained(
        MODEL_PATH,
        local_files_only=True,
        use_fast=True,
    )

    if tokenizer.pad_token is None:
        tokenizer.pad_token = tokenizer.eos_token

    model = AutoModelForCausalLM.from_pretrained(
        MODEL_PATH,
        local_files_only=True,
        dtype=dtype,
        device_map=device_map,
    )

    model.eval()

    # Configuración para inferencia estable y sin avisos de temperature/top_p/top_k
    model.generation_config.do_sample = False
    model.generation_config.temperature = 1.0
    model.generation_config.top_p = 1.0
    model.generation_config.top_k = 50

    return tokenizer, model


# ==========================================================
# GENERAR RESPUESTA
# ==========================================================

def generar_respuesta(tokenizer, model, pregunta):
    respuesta_bloqueada = respuesta_segura(pregunta)

    if respuesta_bloqueada is not None:
        return respuesta_bloqueada

    conversation = [
        {"role": "system", "content": SYSTEM_PROMPT},
        {"role": "user", "content": pregunta},
    ]

    try:
        chat_text = tokenizer.apply_chat_template(
            conversation,
            tokenize=False,
            add_generation_prompt=True,
        )
    except Exception:
        chat_text = (
            f"SYSTEM: {SYSTEM_PROMPT}\n"
            f"USER: {pregunta}\n"
            f"ASSISTANT:"
        )

    inputs = tokenizer(
        chat_text,
        return_tensors="pt",
    )

    inputs = {
        key: value.to(model.device)
        for key, value in inputs.items()
    }

    input_len = inputs["input_ids"].shape[-1]

    with torch.no_grad():
        output_ids = model.generate(
            **inputs,
            max_new_tokens=180,
            do_sample=False,
            pad_token_id=tokenizer.eos_token_id,
            eos_token_id=tokenizer.eos_token_id,
        )

    generated_ids = output_ids[0, input_len:]

    respuesta = tokenizer.decode(
        generated_ids,
        skip_special_tokens=True,
    ).strip()

    return respuesta


# ==========================================================
# PROGRAMA PRINCIPAL
# ==========================================================

def main():
    tokenizer, model = cargar_modelo()

    # Modo 1: pregunta desde terminal
    if len(sys.argv) >= 2:
        pregunta = " ".join(sys.argv[1:])
        respuesta = generar_respuesta(tokenizer, model, pregunta)
        print("\nRespuesta:")
        print(respuesta)
        return

    # Modo 2: chat interactivo
    print("\n✅ Chat Kitcherry iniciado.")
    print("Escribe 'salir' para terminar.")

    while True:
        pregunta = input("\nTú: ").strip()

        if pregunta.lower() in ["salir", "exit", "quit"]:
            print("👋 Chat finalizado.")
            break

        if not pregunta:
            continue

        respuesta = generar_respuesta(tokenizer, model, pregunta)

        print("\nKitcherry:")
        print(respuesta)


if __name__ == "__main__":
    main()