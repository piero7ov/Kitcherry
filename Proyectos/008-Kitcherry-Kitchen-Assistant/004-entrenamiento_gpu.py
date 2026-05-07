#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
==========================================================
KITCHERRY KITCHEN ASSISTANT
==========================================================

Entrenamiento LoRA usando GPU.

Versión recomendada para el nuevo planteamiento:
- Modelo base más potente: Qwen/Qwen2.5-1.5B-Instruct
- Entrenamiento suave: 2 épocas y learning rate bajo
- Entrena SOLO comportamiento del asistente con los JSONL 016-020
- Los JSONL 001-015 se conservan como conocimiento local para inferencia

Funcionamiento:
1. Busca los archivos .jsonl dentro de entrenamiento/
2. Usa solo los archivos configurados en TRAIN_FILE_PREFIXES
3. Carga pares question/answer
4. Crea prompts en formato chat
5. Entrena un adaptador LoRA en GPU
6. Guarda el resultado en lora-gpu/
"""

import os
import glob
from dataclasses import dataclass
from datetime import datetime
from typing import Any, Dict, List, Tuple

import torch
from datasets import Dataset, load_dataset
from transformers import (
    AutoTokenizer,
    AutoModelForCausalLM,
    Trainer,
    TrainingArguments,
)
from peft import LoraConfig, get_peft_model


# ==========================================================
# CONFIGURACIÓN GENERAL
# ==========================================================

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
DATA_FOLDER = os.path.join(SCRIPT_DIR, "entrenamiento")

# Modelo recomendado para mejorar calidad sin hacerlo inviable para portátil.
BASE_MODEL = "Qwen/Qwen2.5-1.5B-Instruct"

OUTPUT_DIR = os.path.join(SCRIPT_DIR, "lora-gpu")
HF_CACHE = os.path.join(SCRIPT_DIR, ".hf-cache")
os.environ["HF_HOME"] = HF_CACHE
os.makedirs(HF_CACHE, exist_ok=True)

# IMPORTANTE:
# Entrenamos comportamiento, no recetas.
# Los archivos 001-015 quedan para conocimiento local en 006-inferencia.py.
TRAIN_FILE_PREFIXES = ("016", "017", "018", "019", "020")

SYSTEM_PROMPT = (
    "Eres Kitcherry Kitchen Assistant, un asistente de IA educativo y operativo en español "
    "para apoyar al personal de cocina y producción interna. Respondes de forma clara, "
    "precisa, concisa y práctica. Tu función es ayudar a consultar elaboraciones, pasos "
    "de preparación, mise en place, organización del servicio, conservación básica, "
    "regeneración, emplatado, pase, incidencias operativas y procedimientos internos de cocina. "
    "No sustituyes al jefe de cocina ni al criterio profesional del equipo. No inventes "
    "gramajes, ingredientes, tiempos, alérgenos ni procedimientos si no están claros. Si una "
    "pregunta afecta a alérgenos, seguridad alimentaria, producto sensible, conservación o "
    "decisiones delicadas, debes recomendar comprobar siempre la ficha técnica oficial del "
    "restaurante y consultar con una persona responsable. Si falta información, dilo claramente."
)

MAX_LENGTH = 768
NUM_EPOCHS = 2
LEARNING_RATE = 2e-5
BATCH_SIZE = 1
GRADIENT_ACCUMULATION_STEPS = 8
VALIDATION_RATIO = 0.10
RANDOM_SEED = 42


# ==========================================================
# COLLATOR PERSONALIZADO
# ==========================================================

@dataclass
class DataCollatorForCausalLMWithLabels:
    tokenizer: Any

    def __call__(self, features: List[Dict[str, Any]]) -> Dict[str, torch.Tensor]:
        labels = [feature["labels"] for feature in features]

        features_sin_labels = []
        for feature in features:
            feature_copia = dict(feature)
            feature_copia.pop("labels")
            features_sin_labels.append(feature_copia)

        batch = self.tokenizer.pad(
            features_sin_labels,
            padding=True,
            return_tensors="pt",
        )

        max_length = batch["input_ids"].shape[1]
        labels_rellenados = []

        for label in labels:
            if len(label) < max_length:
                label = label + [-100] * (max_length - len(label))
            else:
                label = label[:max_length]

            labels_rellenados.append(label)

        batch["labels"] = torch.tensor(labels_rellenados, dtype=torch.long)
        return batch


# ==========================================================
# FUNCIONES AUXILIARES
# ==========================================================

def buscar_archivos_jsonl() -> List[str]:
    """
    Busca los JSONL de entrenamiento de comportamiento.
    Por defecto solo usa 016-020.
    """

    todos = sorted(glob.glob(os.path.join(DATA_FOLDER, "*.jsonl")))

    archivos = []
    for ruta in todos:
        nombre = os.path.basename(ruta)
        if nombre.startswith(TRAIN_FILE_PREFIXES):
            archivos.append(ruta)

    if not archivos:
        raise FileNotFoundError(
            "No se encontraron archivos JSONL de comportamiento en la carpeta entrenamiento/.\n"
            f"Carpeta revisada: {DATA_FOLDER}\n"
            f"Prefijos esperados: {', '.join(TRAIN_FILE_PREFIXES)}"
        )

    return archivos


def obtener_question_answer(example: Dict[str, Any]) -> Tuple[str, str]:
    """
    Obtiene pregunta y respuesta del dataset.
    """

    question = (
        example.get("question")
        or example.get("pregunta")
        or example.get("instruction")
        or example.get("prompt")
        or ""
    )

    answer = (
        example.get("answer")
        or example.get("respuesta")
        or example.get("output")
        or example.get("response")
        or ""
    )

    return str(question).strip(), str(answer).strip()


def crear_prompt_y_texto_completo(tokenizer: AutoTokenizer, question: str, answer: str) -> Tuple[str, str]:
    """
    Crea el prompt en formato chat y después añade la respuesta esperada.
    """

    mensajes_prompt = [
        {"role": "system", "content": SYSTEM_PROMPT},
        {"role": "user", "content": question},
    ]

    try:
        prompt = tokenizer.apply_chat_template(
            mensajes_prompt,
            tokenize=False,
            add_generation_prompt=True,
        )
    except Exception:
        prompt = f"SYSTEM: {SYSTEM_PROMPT}\nUSER: {question}\nASSISTANT:"

    eos_token = tokenizer.eos_token or ""
    texto_completo = prompt + answer + eos_token

    return prompt, texto_completo


def dividir_dataset(dataset: Dataset) -> Tuple[Dataset, Dataset | None]:
    """
    Divide en train/eval si hay suficientes ejemplos.
    """

    if len(dataset) < 20:
        return dataset, None

    split = dataset.train_test_split(
        test_size=VALIDATION_RATIO,
        seed=RANDOM_SEED,
    )

    return split["train"], split["test"]


def cargar_modelo_base(dtype: torch.dtype):
    """
    Carga el modelo base en GPU.
    Se deja fallback con torch_dtype por compatibilidad entre versiones de Transformers.
    """

    try:
        return AutoModelForCausalLM.from_pretrained(
            BASE_MODEL,
            dtype=dtype,
            device_map="auto",
        )
    except TypeError:
        return AutoModelForCausalLM.from_pretrained(
            BASE_MODEL,
            torch_dtype=dtype,
            device_map="auto",
        )


# ==========================================================
# PROGRAMA PRINCIPAL
# ==========================================================

def main() -> None:
    inicio = datetime.now()

    print("=" * 70)
    print("KITCHERRY KITCHEN ASSISTANT - ENTRENAMIENTO GPU")
    print("=" * 70)
    print(f"Carpeta del proyecto: {SCRIPT_DIR}")
    print(f"Carpeta de entrenamiento: {DATA_FOLDER}")
    print(f"Modelo base: {BASE_MODEL}")
    print(f"Salida LoRA: {OUTPUT_DIR}")
    print(f"Archivos usados para entrenar: {', '.join(TRAIN_FILE_PREFIXES)}")
    print("=" * 70)

    # ------------------------------------------------------
    # 0. Comprobar GPU
    # ------------------------------------------------------

    if not torch.cuda.is_available():
        raise RuntimeError(
            "No se ha detectado una GPU CUDA. "
            "Para entrenar con GPU necesitas una gráfica NVIDIA compatible. "
            "Si quieres entrenar por CPU, usa 003-entrenamiento_cpu.py."
        )

    print(f"\nGPU detectada: {torch.cuda.get_device_name(0)}")

    # Para RTX 2060 es mejor forzar float16 en vez de bfloat16.
    dtype = torch.float16
    usar_fp16 = True
    usar_bf16 = False

    print(f"Precisión usada: {dtype}")

    # ------------------------------------------------------
    # 1. Buscar archivos JSONL
    # ------------------------------------------------------

    archivos_jsonl = buscar_archivos_jsonl()

    print("\nArchivos JSONL usados para este entrenamiento:")
    for archivo in archivos_jsonl:
        print(f" - {archivo}")

    # ------------------------------------------------------
    # 2. Cargar dataset
    # ------------------------------------------------------

    print("\nCargando dataset...")

    raw_dataset = load_dataset(
        "json",
        data_files=archivos_jsonl,
        split="train",
    )

    print(f"Ejemplos cargados: {len(raw_dataset)}")

    # ------------------------------------------------------
    # 3. Cargar tokenizer
    # ------------------------------------------------------

    print("\nCargando tokenizer...")

    tokenizer = AutoTokenizer.from_pretrained(
        BASE_MODEL,
        use_fast=True,
    )

    if tokenizer.pad_token is None:
        tokenizer.pad_token = tokenizer.eos_token

    # ------------------------------------------------------
    # 4. Cargar modelo en GPU
    # ------------------------------------------------------

    print("\nCargando modelo base en GPU...")

    model = cargar_modelo_base(dtype=dtype)
    model.config.use_cache = False

    try:
        model.gradient_checkpointing_enable()
    except Exception:
        pass

    try:
        model.enable_input_require_grads()
    except Exception:
        pass

    # ------------------------------------------------------
    # 5. Configurar LoRA
    # ------------------------------------------------------

    print("\nConfigurando LoRA...")

    lora_config = LoraConfig(
        r=8,
        lora_alpha=16,
        target_modules=[
            "q_proj",
            "k_proj",
            "v_proj",
            "o_proj",
            "gate_proj",
            "up_proj",
            "down_proj",
        ],
        lora_dropout=0.05,
        bias="none",
        task_type="CAUSAL_LM",
    )

    model = get_peft_model(model, lora_config)

    print("\nParámetros entrenables:")
    model.print_trainable_parameters()

    # ------------------------------------------------------
    # 6. Tokenizar dataset
    # ------------------------------------------------------

    print("\nTokenizando dataset...")

    def tokenize_with_labels(example: Dict[str, Any]) -> Dict[str, Any]:
        question, answer = obtener_question_answer(example)

        if not question or not answer:
            return {"input_ids": [], "attention_mask": [], "labels": []}

        prompt, texto_completo = crear_prompt_y_texto_completo(
            tokenizer,
            question,
            answer,
        )

        prompt_ids = tokenizer(
            prompt,
            add_special_tokens=False,
        )["input_ids"]

        full_encoding = tokenizer(
            texto_completo,
            add_special_tokens=False,
            truncation=True,
            max_length=MAX_LENGTH,
        )

        input_ids = full_encoding["input_ids"]
        attention_mask = full_encoding["attention_mask"]

        if len(prompt_ids) >= len(input_ids):
            return {"input_ids": [], "attention_mask": [], "labels": []}

        labels = [-100] * len(prompt_ids) + input_ids[len(prompt_ids):]
        labels = labels[:len(input_ids)]

        if all(valor == -100 for valor in labels):
            return {"input_ids": [], "attention_mask": [], "labels": []}

        return {
            "input_ids": input_ids,
            "attention_mask": attention_mask,
            "labels": labels,
        }

    tokenized_dataset = raw_dataset.map(
        tokenize_with_labels,
        remove_columns=raw_dataset.column_names,
    )

    def tiene_tokens(example: Dict[str, Any]) -> bool:
        return isinstance(example["input_ids"], list) and len(example["input_ids"]) > 0

    tokenized_dataset = tokenized_dataset.filter(tiene_tokens)

    print(f"Ejemplos válidos después de tokenizar: {len(tokenized_dataset)}")

    if len(tokenized_dataset) == 0:
        raise RuntimeError("No quedaron ejemplos válidos después de tokenizar.")

    train_dataset, eval_dataset = dividir_dataset(tokenized_dataset)

    print(f"Ejemplos de entrenamiento: {len(train_dataset)}")
    if eval_dataset is not None:
        print(f"Ejemplos de validación: {len(eval_dataset)}")
    else:
        print("Validación desactivada por tener pocos ejemplos.")

    data_collator = DataCollatorForCausalLMWithLabels(tokenizer=tokenizer)

    # ------------------------------------------------------
    # 7. Argumentos de entrenamiento
    # ------------------------------------------------------

    training_args = TrainingArguments(
        output_dir=OUTPUT_DIR,
        num_train_epochs=NUM_EPOCHS,
        per_device_train_batch_size=BATCH_SIZE,
        gradient_accumulation_steps=GRADIENT_ACCUMULATION_STEPS,
        learning_rate=LEARNING_RATE,
        weight_decay=0.01,
        warmup_ratio=0.03,
        lr_scheduler_type="cosine",
        logging_steps=5,
        save_steps=100,
        save_total_limit=1,
        report_to="none",
        gradient_checkpointing=True,
        optim="adamw_torch",
        dataloader_num_workers=0,
        fp16=usar_fp16,
        bf16=usar_bf16,
        seed=RANDOM_SEED,
    )

    trainer = Trainer(
        model=model,
        args=training_args,
        train_dataset=train_dataset,
        eval_dataset=eval_dataset,
        data_collator=data_collator,
    )

    # ------------------------------------------------------
    # 8. Entrenar
    # ------------------------------------------------------

    print("\nIniciando entrenamiento LoRA en GPU...")
    train_output = trainer.train()
    print("\nEntrenamiento finalizado.")

    # ------------------------------------------------------
    # 9. Guardar adaptador LoRA
    # ------------------------------------------------------

    print(f"\nGuardando adaptador LoRA en: {OUTPUT_DIR}")

    os.makedirs(OUTPUT_DIR, exist_ok=True)
    trainer.save_model(OUTPUT_DIR)
    tokenizer.save_pretrained(OUTPUT_DIR)

    fin = datetime.now()

    print("\nProceso completado correctamente.")
    print(f"Duración total: {fin - inicio}")

    metrics = getattr(train_output, "metrics", None)
    if metrics:
        print("\nMétricas del entrenamiento:")
        print(metrics)


if __name__ == "__main__":
    main()
