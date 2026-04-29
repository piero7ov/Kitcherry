#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import os
from datetime import datetime

# ==========================================================
# FORZAR CPU EN WINDOWS
# ==========================================================

os.environ["CUDA_VISIBLE_DEVICES"] = ""
os.environ["ACCELERATE_USE_CPU"] = "true"

import torch
from datasets import load_dataset
from transformers import (
    AutoTokenizer,
    AutoModelForCausalLM,
    Trainer,
    TrainingArguments,
    DataCollatorForLanguageModeling,
)
from peft import LoraConfig, get_peft_model


# ==========================================================
# RUTAS
# ==========================================================

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))

DATA_FILE = os.path.join(
    SCRIPT_DIR,
    "data",
    "kitcherry_corporativo_v2.jsonl"
)

OUTPUT_DIR = os.path.join(
    SCRIPT_DIR,
    "outputs",
    "lora-kitcherry-corporativo-v2"
)


# ==========================================================
# CONFIGURACIÓN
# ==========================================================

BASE_MODEL = "Qwen/Qwen2.5-0.5B-Instruct"

MAX_LENGTH = 384
NUM_EPOCHS = 12
LEARNING_RATE = 1e-4
BATCH_SIZE = 1
GRAD_ACCUM = 8

torch.set_num_threads(4)


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
# FUNCIONES
# ==========================================================

def comprobar_dataset():
    if not os.path.isfile(DATA_FILE):
        raise FileNotFoundError(
            f"No se encontró el dataset:\n{DATA_FILE}"
        )


def cargar_tokenizer():
    tokenizer = AutoTokenizer.from_pretrained(
        BASE_MODEL,
        use_fast=True
    )

    if tokenizer.pad_token is None:
        tokenizer.pad_token = tokenizer.eos_token

    return tokenizer


def cargar_modelo():
    print(f"🧠 Cargando modelo base: {BASE_MODEL}")

    model = AutoModelForCausalLM.from_pretrained(
        BASE_MODEL,
        dtype=torch.float32,
        low_cpu_mem_usage=True,
    )

    model.to("cpu")
    model.config.use_cache = False

    try:
        model.gradient_checkpointing_enable()
    except Exception:
        print("⚠️ No se pudo activar gradient checkpointing.")

    try:
        model.enable_input_require_grads()
    except Exception:
        pass

    return model


def aplicar_lora(model):
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

    print("✅ LoRA aplicado correctamente.")
    model.print_trainable_parameters()

    return model


def convertir_qa_a_texto(example, tokenizer):
    question = example.get("question", "")
    answer = example.get("answer", "")

    if not isinstance(question, str):
        question = str(question)

    if not isinstance(answer, str):
        answer = str(answer)

    conversation = [
        {"role": "system", "content": SYSTEM_PROMPT},
        {"role": "user", "content": question},
        {"role": "assistant", "content": answer},
    ]

    try:
        text = tokenizer.apply_chat_template(
            conversation,
            tokenize=False,
            add_generation_prompt=False,
        )
    except Exception:
        text = (
            f"SYSTEM: {SYSTEM_PROMPT}\n"
            f"USER: {question}\n"
            f"ASSISTANT: {answer}\n"
        )

    return {"text": text}


def main():
    inicio = datetime.now()

    print("=" * 60)
    print("🚀 ENTRENAMIENTO CORPORATIVO V2 - KITCHERRY")
    print("=" * 60)
    print(f"📄 Dataset: {DATA_FILE}")
    print(f"🧠 Modelo base: {BASE_MODEL}")
    print(f"📁 Salida LoRA: {OUTPUT_DIR}")
    print("=" * 60)

    comprobar_dataset()

    if torch.cuda.is_available():
        raise RuntimeError(
            "CUDA sigue visible. Este script está preparado para CPU."
        )

    raw_dataset = load_dataset(
        "json",
        data_files=DATA_FILE,
        split="train"
    )

    print(f"✅ Dataset cargado con {len(raw_dataset)} ejemplos.")

    if len(raw_dataset) >= 10:
        dataset_split = raw_dataset.train_test_split(
            test_size=0.15,
            seed=42
        )
        train_dataset_raw = dataset_split["train"]
        eval_dataset_raw = dataset_split["test"]
    else:
        train_dataset_raw = raw_dataset
        eval_dataset_raw = None

    print(f"📚 Entrenamiento: {len(train_dataset_raw)} ejemplos")

    if eval_dataset_raw is not None:
        print(f"🧪 Evaluación: {len(eval_dataset_raw)} ejemplos")

    tokenizer = cargar_tokenizer()
    model = cargar_modelo()

    if tokenizer.pad_token_id is not None:
        model.config.pad_token_id = tokenizer.pad_token_id

    model = aplicar_lora(model)

    def map_qa(example):
        return convertir_qa_a_texto(example, tokenizer)

    train_text_dataset = train_dataset_raw.map(map_qa)

    if eval_dataset_raw is not None:
        eval_text_dataset = eval_dataset_raw.map(map_qa)
    else:
        eval_text_dataset = None

    def tokenize_fn(batch):
        return tokenizer(
            batch["text"],
            truncation=True,
            max_length=MAX_LENGTH,
            padding=False,
        )

    train_tokenized = train_text_dataset.map(
        tokenize_fn,
        batched=True,
        remove_columns=train_text_dataset.column_names,
    )

    if eval_text_dataset is not None:
        eval_tokenized = eval_text_dataset.map(
            tokenize_fn,
            batched=True,
            remove_columns=eval_text_dataset.column_names,
        )
    else:
        eval_tokenized = None

    data_collator = DataCollatorForLanguageModeling(
        tokenizer=tokenizer,
        mlm=False,
    )

    training_args = TrainingArguments(
        output_dir=OUTPUT_DIR,
        num_train_epochs=NUM_EPOCHS,
        per_device_train_batch_size=BATCH_SIZE,
        gradient_accumulation_steps=GRAD_ACCUM,
        learning_rate=LEARNING_RATE,
        weight_decay=0.01,
        warmup_ratio=0.05,
        logging_steps=5,
        save_steps=50,
        save_total_limit=2,
        report_to="none",
        gradient_checkpointing=True,
        dataloader_pin_memory=False,
        optim="adamw_torch",
    )

    trainer = Trainer(
        model=model,
        args=training_args,
        train_dataset=train_tokenized,
        eval_dataset=eval_tokenized,
        data_collator=data_collator,
    )

    print("=" * 60)
    print("🚂 Comenzando entrenamiento corporativo v2...")
    print("=" * 60)

    train_output = trainer.train()

    print("=" * 60)
    print("🏁 Entrenamiento terminado.")
    print("=" * 60)

    if eval_tokenized is not None:
        print("🧪 Evaluando modelo...")
        eval_metrics = trainer.evaluate()
        print("📊 Métricas de evaluación:")
        print(eval_metrics)

    print(f"💾 Guardando adaptador LoRA en: {OUTPUT_DIR}")

    os.makedirs(OUTPUT_DIR, exist_ok=True)

    trainer.save_model(OUTPUT_DIR)
    tokenizer.save_pretrained(OUTPUT_DIR)

    fin = datetime.now()

    print("=" * 60)
    print("✅ Adaptador LoRA corporativo v2 guardado correctamente.")
    print(f"⏱️ Duración total: {fin - inicio}")
    print("📊 Métricas de entrenamiento:")
    print(train_output.metrics)
    print("=" * 60)


if __name__ == "__main__":
    main()