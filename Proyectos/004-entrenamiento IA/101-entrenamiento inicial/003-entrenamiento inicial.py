#!/usr/bin/env python3
import os
from datetime import datetime

# ==========================================================
# FORZAR CPU EN WINDOWS
# ==========================================================
# Esto evita que intente usar CUDA si no queremos entrenar con GPU.
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
# CONFIGURACIÓN DEL PROYECTO
# ==========================================================

# Ruta del dataset JSONL
DATA_FILE = "data/002-kitcherry_chatbot.jsonl"

# Modelo base de Hugging Face.
# IMPORTANTE:
# No usamos "llama3:latest" aquí porque ese nombre es de Ollama.
# Para entrenar con Transformers usamos un modelo de Hugging Face.
BASE_MODEL = "Qwen/Qwen2.5-0.5B-Instruct"

# Carpeta donde se guardará el adaptador LoRA
OUTPUT_DIR = "outputs/lora-kitcherry"

# Parámetros del entrenamiento
MAX_LENGTH = 384
NUM_EPOCHS = 12
LEARNING_RATE = 1e-4
BATCH_SIZE = 1
GRAD_ACCUM = 8

# Número de hilos de CPU
# Puedes subirlo o bajarlo según tu ordenador.
torch.set_num_threads(4)


# ==========================================================
# PROMPT DEL SISTEMA
# ==========================================================

SYSTEM_PROMPT = (
    "Eres el asistente virtual de Kitcherry, una empresa de herramientas "
    "de software para hostelería desarrollada por PieroDev. Respondes en "
    "español de forma clara, profesional y cercana. Debes explicar qué es "
    "Kitcherry, sus servicios, su enfoque y sus beneficios para pequeños "
    "negocios hosteleros. Si no conoces un dato concreto, debes decirlo "
    "claramente y no inventar información."
)


# ==========================================================
# FUNCIONES
# ==========================================================

def comprobar_dataset():
    """
    Comprueba que el archivo JSONL existe.
    """
    if not os.path.isfile(DATA_FILE):
        raise FileNotFoundError(
            f"No se encontró el dataset: {DATA_FILE}\n"
            "Revisa que el archivo exista y que el nombre sea correcto."
        )


def cargar_tokenizer():
    """
    Carga el tokenizer del modelo base.
    """
    tokenizer = AutoTokenizer.from_pretrained(BASE_MODEL, use_fast=True)

    # Algunos modelos no tienen token de padding definido.
    # En ese caso usamos el token de fin de texto.
    if tokenizer.pad_token is None:
        tokenizer.pad_token = tokenizer.eos_token

    return tokenizer


def cargar_modelo():
    """
    Carga el modelo base en CPU.
    """
    print(f"🧠 Cargando modelo base: {BASE_MODEL}")

    model = AutoModelForCausalLM.from_pretrained(
        BASE_MODEL,
        torch_dtype=torch.float32,
        low_cpu_mem_usage=True,
    )

    model.to("cpu")

    # Ahorro de memoria durante entrenamiento.
    model.config.use_cache = False

    try:
        model.gradient_checkpointing_enable()
    except Exception:
        print("⚠️ No se pudo activar gradient checkpointing. Continuamos igualmente.")

    return model


def aplicar_lora(model):
    """
    Aplica configuración LoRA al modelo base.
    """
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
    """
    Convierte cada línea JSONL con question/answer en formato de conversación.
    """
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

    # Si el tokenizer tiene plantilla de chat, la usamos.
    # Si no, usamos un formato manual.
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
    print("🚀 ENTRENAMIENTO LoRA - KITCHERRY")
    print("=" * 60)
    print(f"📄 Dataset: {DATA_FILE}")
    print(f"🧠 Modelo base: {BASE_MODEL}")
    print(f"📁 Salida: {OUTPUT_DIR}")
    print("=" * 60)

    comprobar_dataset()

    if torch.cuda.is_available():
        raise RuntimeError(
            "CUDA sigue visible. Este script está configurado para CPU. "
            "Revisa CUDA_VISIBLE_DEVICES."
        )

    # ======================================================
    # CARGAR DATASET
    # ======================================================

    raw_dataset = load_dataset("json", data_files=DATA_FILE, split="train")

    print(f"✅ Dataset cargado con {len(raw_dataset)} ejemplos.")

    if len(raw_dataset) < 10:
        print("⚠️ El dataset tiene pocos ejemplos. Entrenará, pero el resultado puede ser limitado.")

    # División train/test para evaluación básica
    if len(raw_dataset) >= 10:
        dataset_split = raw_dataset.train_test_split(test_size=0.15, seed=42)
        train_dataset_raw = dataset_split["train"]
        eval_dataset_raw = dataset_split["test"]
    else:
        train_dataset_raw = raw_dataset
        eval_dataset_raw = None

    print(f"📚 Ejemplos de entrenamiento: {len(train_dataset_raw)}")
    if eval_dataset_raw is not None:
        print(f"🧪 Ejemplos de prueba: {len(eval_dataset_raw)}")

    # ======================================================
    # CARGAR TOKENIZER Y MODELO
    # ======================================================

    tokenizer = cargar_tokenizer()
    model = cargar_modelo()

    if tokenizer.pad_token_id is not None:
        model.config.pad_token_id = tokenizer.pad_token_id

    model = aplicar_lora(model)

    # ======================================================
    # PREPARAR TEXTO
    # ======================================================

    def map_qa(example):
        return convertir_qa_a_texto(example, tokenizer)

    train_text_dataset = train_dataset_raw.map(map_qa)

    if eval_dataset_raw is not None:
        eval_text_dataset = eval_dataset_raw.map(map_qa)
    else:
        eval_text_dataset = None

    # ======================================================
    # TOKENIZAR
    # ======================================================

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

    # ======================================================
    # CONFIGURACIÓN DEL ENTRENAMIENTO
    # ======================================================

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

    # ======================================================
    # ENTRENAR
    # ======================================================

    print("=" * 60)
    print("🚂 Comenzando entrenamiento en CPU...")
    print("=" * 60)

    train_output = trainer.train()

    print("=" * 60)
    print("🏁 Entrenamiento terminado.")
    print("=" * 60)

    # ======================================================
    # EVALUACIÓN BÁSICA
    # ======================================================

    if eval_tokenized is not None:
        print("🧪 Evaluando modelo...")
        eval_metrics = trainer.evaluate()
        print("📊 Métricas de evaluación:")
        print(eval_metrics)

    # ======================================================
    # GUARDAR ADAPTADOR LoRA
    # ======================================================

    print(f"💾 Guardando adaptador LoRA en: {OUTPUT_DIR}")

    os.makedirs(OUTPUT_DIR, exist_ok=True)

    trainer.save_model(OUTPUT_DIR)
    tokenizer.save_pretrained(OUTPUT_DIR)

    fin = datetime.now()

    print("=" * 60)
    print("✅ Adaptador LoRA guardado correctamente.")
    print(f"⏱️ Duración total: {fin - inicio}")
    print("📊 Métricas de entrenamiento:")
    print(train_output.metrics)
    print("=" * 60)


if __name__ == "__main__":
    main()