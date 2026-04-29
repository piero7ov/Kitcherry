#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import os
import torch
from transformers import AutoTokenizer, AutoModelForCausalLM
from peft import PeftModel


# ==========================================================
# RUTAS
# ==========================================================

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))

BASE_MODEL = "Qwen/Qwen2.5-0.5B-Instruct"

ADAPTER_PATH = os.path.join(
    SCRIPT_DIR,
    "outputs",
    "lora-kitcherry-corporativo-v2"
)

OUT_PATH = os.path.join(
    SCRIPT_DIR,
    "outputs",
    "modelo-kitcherry-corporativo-v2"
)


def main():
    print("=" * 60)
    print("🔗 FUSIÓN CORPORATIVO V2 - KITCHERRY")
    print("=" * 60)

    if not os.path.isdir(ADAPTER_PATH):
        raise FileNotFoundError(
            f"No se encontró el adaptador LoRA:\n{ADAPTER_PATH}\n\n"
            "Primero ejecuta 008-entrenamiento-corporativo-v2.py"
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

    print("🧠 Cargando modelo base...")

    base_model = AutoModelForCausalLM.from_pretrained(
        BASE_MODEL,
        dtype=dtype,
        device_map=device_map,
        low_cpu_mem_usage=True,
    )

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

    print("📦 Cargando adaptador LoRA...")

    model = PeftModel.from_pretrained(
        base_model,
        ADAPTER_PATH,
    )

    print("🔗 Fusionando adaptador con modelo base...")

    merged_model = model.merge_and_unload()

    try:
        merged_model.config.use_cache = True
    except Exception:
        pass

    print("💾 Guardando modelo fusionado...")

    merged_model.save_pretrained(
        OUT_PATH,
        safe_serialization=True,
    )

    tokenizer.save_pretrained(OUT_PATH)

    print("=" * 60)
    print("✅ Modelo corporativo v2 fusionado correctamente.")
    print(f"📁 Guardado en: {OUT_PATH}")
    print("=" * 60)


if __name__ == "__main__":
    main()