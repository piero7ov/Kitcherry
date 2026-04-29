#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import os
import sys
import torch
from transformers import AutoTokenizer, AutoModelForCausalLM


# ==========================================================
# UTF-8 PARA PHP / XAMPP / WINDOWS
# ==========================================================

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")


# ==========================================================
# RUTAS
# ==========================================================

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))

MODEL_PATH = os.path.join(
    SCRIPT_DIR,
    "outputs",
    "modelo-kitcherry-corporativo-v2"
)


# ==========================================================
# PROMPT DEL SISTEMA
# ==========================================================

SYSTEM_PROMPT = (
    "Eres el asistente corporativo de Kitcherry, una empresa de herramientas "
    "de software para hostelería desarrollada por PieroDev. Respondes en español "
    "de forma clara, profesional y cercana. Debes explicar qué es Kitcherry, "
    "sus productos actuales, su producto estrella, el sistema inteligente de "
    "consultas y comunicaciones, el chatbot para restaurantes y su enfoque "
    "modular para pequeños negocios hosteleros. "
    "Solo debes responder con información confirmada. Si no conoces un dato "
    "concreto, debes decir que no dispones de información concreta y no inventar."
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
        "que puede hacer kitcherry",
        "productos actuales",
        "lineas principales"
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
            "Actualmente Kitcherry se centra en dos líneas principales: "
            "el sistema inteligente de consultas y comunicaciones para hostelería, "
            "que es su producto estrella, y el chatbot de atención al cliente para restaurantes. "
            "En el futuro se podrán añadir más herramientas modulares."
        )

    return None


# ==========================================================
# MODELO
# ==========================================================

def cargar_modelo():
    if not os.path.isdir(MODEL_PATH):
        raise FileNotFoundError("No se encontró el modelo corporativo v2 fusionado.")

    use_cuda = torch.cuda.is_available()

    if use_cuda:
        dtype = torch.float16
        device_map = "auto"
    else:
        dtype = torch.float32
        device_map = {"": "cpu"}

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

    model.generation_config.do_sample = False
    model.generation_config.temperature = 1.0
    model.generation_config.top_p = 1.0
    model.generation_config.top_k = 50

    return tokenizer, model


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
            max_new_tokens=220,
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


def main():
    if len(sys.argv) < 2:
        print("No se recibió ninguna pregunta.")
        return

    pregunta = " ".join(sys.argv[1:]).strip()

    if not pregunta:
        print("No se recibió ninguna pregunta.")
        return

    tokenizer, model = cargar_modelo()
    respuesta = generar_respuesta(tokenizer, model, pregunta)

    print(respuesta)


if __name__ == "__main__":
    main()