#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import os
import torch
from transformers import AutoTokenizer, AutoModelForCausalLM
from peft import PeftModel


# ==========================================================
# RUTAS DEL PROYECTO
# ==========================================================

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))

# Debe coincidir con el modelo usado en el entrenamiento
BASE_MODEL = "Qwen/Qwen2.5-0.5B-Instruct"

# Carpeta generada por el entrenamiento LoRA
ADAPTER_PATH = os.path.join(
    SCRIPT_DIR,
    "outputs",
    "lora-kitcherry"
)

# Carpeta donde se guardará el modelo fusionado
OUT_PATH = os.path.join(
    SCRIPT_DIR,
    "outputs",
    "modelo-kitcherry-fusionado"
)


def main():
    print("=" * 60)
    print("🔗 FUSIÓN LoRA - KITCHERRY")
    print("=" * 60)

    if not os.path.isdir(ADAPTER_PATH):
        raise FileNotFoundError(
            f"No se encontró la carpeta del adaptador LoRA:\n{ADAPTER_PATH}\n\n"
            "Primero debes terminar el entrenamiento con 003-entrenamiento inicial.py"
        )

    os.makedirs(OUT_PATH, exist_ok=True)

    use_cuda = torch.cuda.is_available()

    if use_cuda:
        dtype = torch.float16
        device_map = "auto"
    else:
        dtype = torch.float32
        device_map = {"": "cpu"}

    print(f"🧠 Modelo base: {BASE_MODEL}")
    print(f"📁 Adaptador LoRA: {ADAPTER_PATH}")
    print(f"📁 Salida fusionada: {OUT_PATH}")
    print(f"⚙️ CUDA disponible: {use_cuda}")
    print(f"⚙️ dtype: {dtype}")
    print("=" * 60)

    # ======================================================
    # CARGAR MODELO BASE
    # ======================================================

    print("🧠 Cargando modelo base...")

    base_model = AutoModelForCausalLM.from_pretrained(
        BASE_MODEL,
        torch_dtype=dtype,
        device_map=device_map,
        low_cpu_mem_usage=True,
    )

    # ======================================================
    # CARGAR TOKENIZER
    # ======================================================

    print("🔤 Cargando tokenizer...")

    try:
        tokenizer = AutoTokenizer.from_pretrained(
            ADAPTER_PATH,
            use_fast=True,
        )
    except Exception:
        tokenizer = AutoTokenizer.from_pretrained(
            BASE_MODEL,
            use_fast=True,
        )

    if tokenizer.pad_token is None:
        tokenizer.pad_token = tokenizer.eos_token

    base_model.config.pad_token_id = tokenizer.pad_token_id

    # ======================================================
    # CARGAR ADAPTADOR LoRA
    # ======================================================

    print("📦 Cargando adaptador LoRA...")

    model = PeftModel.from_pretrained(
        base_model,
        ADAPTER_PATH,
    )

    # ======================================================
    # FUSIONAR
    # ======================================================

    print("🔗 Fusionando adaptador con modelo base...")

    merged_model = model.merge_and_unload()

    try:
        merged_model.config.use_cache = True
    except Exception:
        pass

    # ======================================================
    # GUARDAR MODELO FUSIONADO
    # ======================================================

    print("💾 Guardando modelo fusionado...")

    merged_model.save_pretrained(
        OUT_PATH,
        safe_serialization=True,
    )

    tokenizer.save_pretrained(OUT_PATH)

    print("=" * 60)
    print("✅ Modelo fusionado correctamente.")
    print(f"📁 Guardado en: {OUT_PATH}")
    print("=" * 60)


if __name__ == "__main__":
    main()