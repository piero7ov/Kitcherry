#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
==========================================================
KITCHERRY KITCHEN ASSISTANT
==========================================================

Fusiona el modelo base con el adaptador LoRA entrenado.

Funcionamiento:
1. Busca el adaptador LoRA entrenado.
   - Primero intenta usar lora-gpu/
   - Si no existe, intenta usar lora-cpu/
2. Carga el modelo base Qwen/Qwen2.5-1.5B-Instruct.
3. Carga el adaptador LoRA encima del modelo base.
4. Fusiona ambos con merge_and_unload().
5. Guarda el modelo final fusionado en modelo-kitcherry-cocina-fusionado/
"""

import os
import shutil
from datetime import datetime
from typing import Tuple

import torch
from transformers import AutoTokenizer, AutoModelForCausalLM
from peft import PeftModel


# ==========================================================
# CONFIGURACIÓN GENERAL
# ==========================================================

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))

BASE_MODEL = "Qwen/Qwen2.5-1.5B-Instruct"

LORA_GPU_DIR = os.path.join(SCRIPT_DIR, "lora-gpu")
LORA_CPU_DIR = os.path.join(SCRIPT_DIR, "lora-cpu")

OUTPUT_DIR = os.path.join(SCRIPT_DIR, "modelo-kitcherry-cocina-fusionado")

HF_CACHE = os.path.join(SCRIPT_DIR, ".hf-cache")
os.environ["HF_HOME"] = HF_CACHE
os.makedirs(HF_CACHE, exist_ok=True)


# ==========================================================
# FUNCIONES AUXILIARES
# ==========================================================

def existe_adaptador_lora(carpeta: str) -> bool:
    """
    Comprueba si una carpeta parece contener un adaptador LoRA válido.
    """

    adapter_config = os.path.join(carpeta, "adapter_config.json")
    adapter_model_safetensors = os.path.join(carpeta, "adapter_model.safetensors")
    adapter_model_bin = os.path.join(carpeta, "adapter_model.bin")

    return (
        os.path.isdir(carpeta)
        and os.path.isfile(adapter_config)
        and (
            os.path.isfile(adapter_model_safetensors)
            or os.path.isfile(adapter_model_bin)
        )
    )


def seleccionar_adaptador_lora() -> str:
    """
    Selecciona automáticamente el adaptador LoRA.
    Da prioridad a lora-gpu y después a lora-cpu.
    """

    if existe_adaptador_lora(LORA_GPU_DIR):
        return LORA_GPU_DIR

    if existe_adaptador_lora(LORA_CPU_DIR):
        return LORA_CPU_DIR

    raise FileNotFoundError(
        "No se ha encontrado ningún adaptador LoRA válido.\n\n"
        "Se esperaba encontrar uno de estos directorios:\n"
        f" - {LORA_GPU_DIR}\n"
        f" - {LORA_CPU_DIR}\n\n"
        "Primero debes ejecutar el entrenamiento:\n"
        " - python 002-entrenamiento_gpu.py\n"
        "o\n"
        " - python 003-entrenamiento_cpu.py"
    )


def preparar_salida() -> None:
    """
    Prepara la carpeta de salida.
    Si existe una versión anterior, se elimina para evitar mezcla de archivos.
    """

    if os.path.isdir(OUTPUT_DIR):
        print("\nYa existe una carpeta de salida anterior:")
        print(f" - {OUTPUT_DIR}")
        print("Eliminando carpeta anterior para guardar una fusión limpia...")
        shutil.rmtree(OUTPUT_DIR)

    os.makedirs(OUTPUT_DIR, exist_ok=True)


def obtener_configuracion_dispositivo() -> Tuple[object, torch.dtype, str]:
    """
    Decide si la fusión se hará con GPU o CPU.
    """

    if torch.cuda.is_available():
        device_map = "auto"
        dtype = torch.float16
        dispositivo = f"GPU CUDA: {torch.cuda.get_device_name(0)}"
    else:
        device_map = {"": "cpu"}
        dtype = torch.float32
        dispositivo = "CPU"

    return device_map, dtype, dispositivo


def cargar_modelo_base(device_map: object, dtype: torch.dtype):
    """
    Carga el modelo base con fallback para distintas versiones de Transformers.
    """

    try:
        return AutoModelForCausalLM.from_pretrained(
            BASE_MODEL,
            dtype=dtype,
            device_map=device_map,
        )
    except TypeError:
        return AutoModelForCausalLM.from_pretrained(
            BASE_MODEL,
            torch_dtype=dtype,
            device_map=device_map,
        )


# ==========================================================
# PROGRAMA PRINCIPAL
# ==========================================================

def main() -> None:
    inicio = datetime.now()

    print("=" * 70)
    print("KITCHERRY KITCHEN ASSISTANT - FUSIÓN DEL MODELO")
    print("=" * 70)
    print(f"Carpeta del proyecto: {SCRIPT_DIR}")
    print(f"Modelo base: {BASE_MODEL}")
    print(f"Salida modelo fusionado: {OUTPUT_DIR}")
    print("=" * 70)

    # ------------------------------------------------------
    # 1. Seleccionar adaptador LoRA
    # ------------------------------------------------------

    lora_dir = seleccionar_adaptador_lora()

    print("\nAdaptador LoRA seleccionado:")
    print(f" - {lora_dir}")

    # ------------------------------------------------------
    # 2. Configurar dispositivo
    # ------------------------------------------------------

    device_map, dtype, dispositivo = obtener_configuracion_dispositivo()

    print("\nDispositivo utilizado:")
    print(f" - {dispositivo}")
    print(f" - Precisión: {dtype}")

    # ------------------------------------------------------
    # 3. Preparar carpeta de salida
    # ------------------------------------------------------

    preparar_salida()

    # ------------------------------------------------------
    # 4. Cargar tokenizer
    # ------------------------------------------------------

    print("\nCargando tokenizer...")

    try:
        tokenizer = AutoTokenizer.from_pretrained(
            lora_dir,
            use_fast=True,
        )
    except Exception:
        tokenizer = AutoTokenizer.from_pretrained(
            BASE_MODEL,
            use_fast=True,
        )

    if tokenizer.pad_token is None:
        tokenizer.pad_token = tokenizer.eos_token

    # ------------------------------------------------------
    # 5. Cargar modelo base
    # ------------------------------------------------------

    print("\nCargando modelo base...")

    base_model = cargar_modelo_base(
        device_map=device_map,
        dtype=dtype,
    )

    # ------------------------------------------------------
    # 6. Cargar adaptador LoRA
    # ------------------------------------------------------

    print("\nCargando adaptador LoRA sobre el modelo base...")

    model_with_lora = PeftModel.from_pretrained(
        base_model,
        lora_dir,
        is_trainable=False,
    )

    # ------------------------------------------------------
    # 7. Fusionar
    # ------------------------------------------------------

    print("\nFusionando modelo base + LoRA...")
    print("Esto puede tardar unos segundos o minutos según el equipo.")

    merged_model = model_with_lora.merge_and_unload()

    # ------------------------------------------------------
    # 8. Guardar modelo final
    # ------------------------------------------------------

    print("\nGuardando modelo fusionado...")

    merged_model.save_pretrained(
        OUTPUT_DIR,
        safe_serialization=True,
    )

    tokenizer.save_pretrained(OUTPUT_DIR)

    fin = datetime.now()

    print("\nFusión completada correctamente.")
    print(f"Modelo final guardado en: {OUTPUT_DIR}")
    print(f"Duración total: {fin - inicio}")
    print("=" * 70)


if __name__ == "__main__":
    main()
