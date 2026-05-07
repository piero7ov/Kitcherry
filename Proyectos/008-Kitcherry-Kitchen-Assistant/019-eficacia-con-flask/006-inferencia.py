#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
==========================================================
KITCHERRY KITCHEN ASSISTANT
Archivo: 006-inferencia.py
==========================================================

Inferencia del asistente Kitcherry Kitchen Assistant.

Versión corregida:
- Usa los JSONL 001-015 como conocimiento interno real.
- No usa los JSONL 016-020 como conocimiento local porque eran de comportamiento.
- Evita que el modelo invente o reescriba mal recetas.
- Si encuentra una ficha clara, responde usando el texto interno del manual.
- Si no encuentra información suficiente, bloquea la respuesta.
- Si detecta alérgenos, intolerancias o trazas, responde con plantilla segura.
- Detecta intención de la pregunta:
    ingredientes/gramajes, preparación, presentación, errores o incidencias.
- Permite preguntas naturales:
    "medidas arroz jazmín"
    "como hago maki salmon"
    "como se hace arroz sushi"
    "errores rollitos vegetales"
    "presentacion yakisoba pollo"
- Responde mensajes conversacionales básicos:
    "hola"
    "quien eres"
    "que puedes hacer"
    "ayudame"
    "dame ejemplos"
    "gracias"
"""

import os
import re
import json
import glob
import unicodedata
from datetime import datetime
from typing import Any, Dict, List, Tuple

import torch
from transformers import AutoTokenizer, AutoModelForCausalLM


# ==========================================================
# CONFIGURACIÓN GENERAL
# ==========================================================

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))

MODEL_DIR = os.path.join(SCRIPT_DIR, "modelo-kitcherry-cocina-fusionado")
DATA_FOLDER = os.path.join(SCRIPT_DIR, "entrenamiento")

KNOWLEDGE_FILE_PREFIXES = [
    "001",
    "002",
    "003",
    "004",
    "005",
    "006",
    "007",
    "008",
    "009",
    "010",
    "011",
    "012",
    "013",
    "014",
    "015",
]

USE_LOCAL_KNOWLEDGE = True

# Modo seguro para demo:
# True = responde pegado al manual interno.
# False = permitiría usar generación del modelo con contexto.
STRICT_MANUAL_MODE = True

MAX_CONTEXT_ITEMS = 5
MAX_CONTEXT_CHARS = 4500

MAX_NEW_TOKENS = 260
REPETITION_PENALTY = 1.12
NO_REPEAT_NGRAM_SIZE = 4


SYSTEM_PROMPT = (
    "Eres Kitcherry Kitchen Assistant, un asistente educativo y operativo en español "
    "para apoyar al personal de cocina y producción interna. Respondes de forma clara, "
    "precisa, concisa y práctica. No sustituyes al jefe de cocina ni al criterio profesional "
    "del equipo. No inventes gramajes, ingredientes, tiempos, alérgenos ni procedimientos. "
    "Si no hay información suficiente en el contexto interno, debes decirlo claramente. "
    "En temas de alérgenos, intolerancias o seguridad alimentaria, nunca confirmes que "
    "un plato es apto sin revisar la ficha técnica oficial y el riesgo de contaminación cruzada."
)


# ==========================================================
# ALIAS DE CONSULTAS NATURALES
# ==========================================================

ALIASES_CONOCIMIENTO = {
    "arroz sushi": "arroz sushi avinagrado",
    "arroz para sushi": "arroz sushi avinagrado",
    "arroz de sushi": "arroz sushi avinagrado",
    "mayo sriracha": "mayonesa sriracha",
    "maki salmon": "maki de salmon",
    "maki salmón": "maki de salmon",
    "yakisoba pollo": "yakisoba de pollo",
    "rollitos": "rollitos vegetales",
    "rollitos vegetal": "rollitos vegetales",
    "uramaki pollo": "uramaki de pollo teriyaki",
    "uramaki teriyaki": "uramaki de pollo teriyaki",
    "arroz jazmin": "arroz jazmin de servicio",
    "arroz jazmín": "arroz jazmin de servicio",
}


# ==========================================================
# PALABRAS CLAVE
# ==========================================================

ALLERGEN_KEYWORDS = [
    "alergia",
    "alergias",
    "alergico",
    "alergica",
    "alérgico",
    "alérgica",
    "alergeno",
    "alergenos",
    "alérgeno",
    "alérgenos",
    "intolerancia",
    "intolerante",
    "celiaco",
    "celíaco",
    "celiaca",
    "celíaca",
    "gluten",
    "lactosa",
    "leche",
    "huevo",
    "huevos",
    "soja",
    "sesamo",
    "sésamo",
    "marisco",
    "mariscos",
    "pescado",
    "pescados",
    "frutos secos",
    "cacahuete",
    "cacahuetes",
    "almendra",
    "almendras",
    "nueces",
    "mostaza",
    "apio",
    "sulfitos",
    "crustaceos",
    "crustáceos",
    "moluscos",
    "traza",
    "trazas",
    "contaminacion cruzada",
    "contaminación cruzada",
    "apto",
    "apta",
]

INTENT_QUANTITY_KEYWORDS = [
    "ingredientes",
    "ingrediente",
    "gramajes",
    "gramaje",
    "gramos",
    "gramo",
    "gr",
    "kg",
    "kilo",
    "kilos",
    "mililitros",
    "mililitro",
    "ml",
    "litros",
    "litro",
    "cantidad",
    "cantidades",
    "cuánto",
    "cuanto",
    "cuánta",
    "cuanta",
    "lleva",
    "llevan",
    "pesa",
    "peso",
    "medida",
    "medidas",
    "dosificación",
    "dosificacion",
    "proporcion",
    "proporción",
]

INTENT_PREPARATION_KEYWORDS = [
    "preparo",
    "preparar",
    "prepara",
    "hacer",
    "hace",
    "hacemos",
    "hago",
    "haz",
    "elaborar",
    "elaboro",
    "elabora",
    "elaboración",
    "elaboracion",
    "receta",
    "pasos",
    "paso",
    "paso a paso",
    "montar",
    "montaje",
    "cocer",
    "cocción",
    "coccion",
    "saltear",
    "freir",
    "fríe",
    "frie",
    "regenerar",
    "conservar",
    "guardar",
]

INTENT_PRESENTATION_KEYWORDS = [
    "emplatar",
    "emplatado",
    "presenta",
    "presentar",
    "presentacion",
    "presentación",
    "servir",
    "sirve",
    "sale",
    "montaje final",
    "decoracion",
    "decoración",
]

INTENT_ERRORS_KEYWORDS = [
    "errores",
    "error",
    "evitar",
    "fallos",
    "fallo",
    "cuidado",
    "mal",
    "problemas",
]

INTENT_INCIDENT_KEYWORDS = [
    "comandas",
    "pase",
    "organizo",
    "organizar",
    "acumulado",
    "acumuladas",
    "atasco",
    "retraso",
    "retrasos",
    "servicio",
    "incidencia",
    "incidencias",
    "frio",
    "frío",
    "seco",
    "seca",
    "liquida",
    "líquida",
    "espesa",
    "humedo",
    "húmedo",
    "quemado",
    "quemada",
    "falta",
]


# ==========================================================
# FUNCIONES DE TEXTO
# ==========================================================

def normalizar_texto(texto: str) -> str:
    """
    Normaliza texto:
    - minúsculas
    - sin tildes
    - sin signos raros
    - espacios limpios
    """

    texto = str(texto).lower().strip()

    texto = unicodedata.normalize("NFD", texto)
    texto = "".join(
        caracter for caracter in texto
        if unicodedata.category(caracter) != "Mn"
    )

    texto = re.sub(r"[^a-z0-9ñ\s]", " ", texto)
    texto = re.sub(r"\s+", " ", texto).strip()

    return texto


def aplicar_aliases(pregunta: str) -> str:
    """
    Añade términos equivalentes a la pregunta para mejorar la búsqueda.

    Ejemplo:
    "como se hace arroz sushi"
    se convierte en:
    "como se hace arroz sushi arroz sushi avinagrado"
    """

    pregunta_expandida = pregunta
    pregunta_norm = normalizar_texto(pregunta)

    for alias, termino_real in ALIASES_CONOCIMIENTO.items():
        alias_norm = normalizar_texto(alias)
        termino_norm = normalizar_texto(termino_real)

        if alias_norm in pregunta_norm and termino_norm not in pregunta_norm:
            pregunta_expandida += f" {termino_real}"

    return pregunta_expandida


def contiene_alguna(texto: str, palabras: List[str]) -> bool:
    """
    Comprueba si un texto contiene alguna palabra clave.

    Para palabras cortas como gr, kg o ml exige palabra completa.
    """

    texto_norm = normalizar_texto(texto)
    palabras_texto = set(texto_norm.split())

    for palabra in palabras:
        palabra_norm = normalizar_texto(palabra)

        if not palabra_norm:
            continue

        if len(palabra_norm) <= 3:
            if palabra_norm in palabras_texto:
                return True
        else:
            if palabra_norm in texto_norm:
                return True

    return False


def detectar_intencion(pregunta: str) -> str:
    """
    Detecta la intención principal de la pregunta.

    Devuelve:
    - cantidad
    - preparacion
    - presentacion
    - errores
    - incidencia
    - general
    """

    pregunta_norm = normalizar_texto(pregunta)

    if contiene_alguna(pregunta_norm, INTENT_ERRORS_KEYWORDS):
        return "errores"

    if contiene_alguna(pregunta_norm, INTENT_PRESENTATION_KEYWORDS):
        return "presentacion"

    if contiene_alguna(pregunta_norm, INTENT_PREPARATION_KEYWORDS):
        return "preparacion"

    if contiene_alguna(pregunta_norm, INTENT_QUANTITY_KEYWORDS):
        return "cantidad"

    if contiene_alguna(pregunta_norm, INTENT_INCIDENT_KEYWORDS):
        return "incidencia"

    return "general"


def detectar_intencion_ficha(question: str, answer: str) -> str:
    """
    Detecta qué tipo de ficha es un registro del manual.
    """

    texto = f"{question} {answer}"
    texto_norm = normalizar_texto(texto)

    if "como se emplata" in texto_norm or "presentacion recomendada" in texto_norm or "presenta" in texto_norm:
        return "presentacion"

    if "errores" in texto_norm or "debes evitar" in texto_norm:
        return "errores"

    if "comandas" in texto_norm or "pase" in texto_norm or "incidencia" in texto_norm:
        return "incidencia"

    if "como preparo" in texto_norm or "para preparar" in texto_norm or "paso a paso" in texto_norm:
        return "preparacion"

    if "ingredientes" in texto_norm or "gramajes" in texto_norm or "segun el manual" in texto_norm or "lleva" in texto_norm:
        return "cantidad"

    return "general"


def extraer_palabras_utiles(texto: str) -> List[str]:
    """
    Extrae palabras útiles para comparar la pregunta con las fichas.
    """

    texto_norm = normalizar_texto(texto)

    stopwords = {
        "que", "como", "para", "debo", "debe", "de", "del", "la", "el", "los",
        "las", "un", "una", "unos", "unas", "y", "o", "en", "con", "sin",
        "por", "al", "lo", "me", "mi", "tu", "su", "se", "es", "son",
        "esta", "este", "estos", "estas", "hacer", "hace", "hacemos", "hago",
        "preparar", "preparo", "pasos", "paso", "seguir", "correctamente",
        "servicio", "plato", "cliente", "puede", "puedo", "cuanto", "cuantos",
        "cuanta", "cuantas", "lleva", "llevan", "tengo", "hay", "si", "no",
        "mas", "menos", "segun", "según", "manual", "interno", "oficial",
        "dame", "dime", "saber", "quiero", "necesito", "medidas", "medida",
        "ingredientes", "gramajes", "gramaje", "cantidad", "cantidades",
        "errores", "evitar", "presentacion", "presentación", "emplatado",
    }

    palabras = texto_norm.split()
    palabras_utiles = []

    for palabra in palabras:
        if len(palabra) < 3:
            continue

        if palabra in stopwords:
            continue

        palabras_utiles.append(palabra)

    return palabras_utiles


def limpiar_para_mostrar(texto: str) -> str:
    """
    Pequeña limpieza visual de respuestas del JSONL.
    No cambia el contenido técnico.
    """

    texto = texto.strip()

    reemplazos = {
        "Segun": "Según",
        "segun": "según",
        " jazmin": " jazmín",
        " produccion": " producción",
        " laminas": " láminas",
        " pasalo": " pásalo",
        " Pasalo": " Pásalo",
        " anade": " añade",
        " Anade": " Añade",
        " tecnicas": " técnicas",
        " tecnica": " técnica",
        " alergenos": " alérgenos",
        " coccion": " cocción",
        " elaboracion": " elaboración",
        " elaboraciónes": " elaboraciones",
        " presentacion": " presentación",
        " decoracion": " decoración",
        " sesamo": " sésamo",
        " salmon": " salmón",
        " frio": " frío",
        " humedo": " húmedo",
        " enfria": " enfría",
        " azucar": " azúcar",
        " frie": " fríe",
        " usalo": " úsalo",
        " esta ": " está ",
    }

    for original, corregido in reemplazos.items():
        texto = texto.replace(original, corregido)

    texto = texto.replace(" Trabaja con la mise en place", ". Trabaja con la mise en place")
    texto = texto.replace(" El plato debe salir", ". El plato debe salir")
    texto = texto.replace(" Si hay duda", ". Si hay duda")
    texto = texto.replace(" Estos gramajes", ". Estos gramajes")

    texto = re.sub(r"\.\.+", ".", texto)
    texto = re.sub(r"\s+", " ", texto).strip()

    return texto


# ==========================================================
# CONVERSACIÓN BÁSICA
# ==========================================================

def es_mensaje_conversacional(pregunta: str) -> bool:
    """
    Detecta mensajes simples que no deben buscarse en el manual.
    """

    pregunta_norm = normalizar_texto(pregunta)

    patrones = [
        "hola",
        "holaa",
        "buenas",
        "buenos dias",
        "buenas tardes",
        "buenas noches",
        "hey",
        "hola como estas",
        "hola que tal",
        "que tal",
        "como estas",
        "todo bien",
        "estas ahi",
        "estas funcionando",
        "funcionas",
        "quien eres",
        "quién eres",
        "que eres",
        "qué eres",
        "que puedes hacer",
        "qué puedes hacer",
        "para que sirves",
        "para qué sirves",
        "ayuda",
        "ayudame",
        "ayúdame",
        "ayudame con una receta",
        "ayúdame con una receta",
        "necesito ayuda",
        "necesito ayuda con una receta",
        "no entiendo",
        "no se que preguntar",
        "no sé qué preguntar",
        "dame ejemplos",
        "ponme ejemplos",
        "tienes recetas",
        "sabes recetas",
        "puedes inventar recetas",
        "puedes inventarte recetas",
        "buen trabajo",
        "muy bien",
        "perfecto",
        "gracias",
        "muchas gracias",
        "vale gracias",
        "ok gracias",
        "perfecto gracias",
        "adios",
        "adiós",
        "chao",
        "hasta luego",
    ]

    if pregunta_norm in patrones:
        return True

    if pregunta_norm.startswith("hola"):
        return True

    if pregunta_norm.startswith("ayudame") or pregunta_norm.startswith("ayudame"):
        return True

    if pregunta_norm.startswith("necesito ayuda"):
        return True

    if pregunta_norm.startswith("que puedes") or pregunta_norm.startswith("para que sirves"):
        return True

    if pregunta_norm.startswith("dame ejemplos") or pregunta_norm.startswith("ponme ejemplos"):
        return True

    return False


def responder_mensaje_conversacional(pregunta: str) -> str:
    """
    Responde mensajes básicos sin consultar el manual.
    """

    pregunta_norm = normalizar_texto(pregunta)

    if pregunta_norm in [
        "gracias",
        "muchas gracias",
        "vale gracias",
        "ok gracias",
        "perfecto gracias",
    ]:
        return (
            "De nada. Cuando necesites consultar una elaboración, gramaje, "
            "presentación o incidencia de cocina, puedo ayudarte."
        )

    if pregunta_norm in [
        "adios",
        "adiós",
        "chao",
        "hasta luego",
    ]:
        return (
            "De acuerdo. Cuando necesites consultar algo de cocina, aquí estaré."
        )

    if pregunta_norm in [
        "quien eres",
        "quién eres",
        "que eres",
        "qué eres",
    ]:
        return (
            "Soy Kitcherry Kitchen Assistant, un asistente de cocina pensado para "
            "apoyar al personal en elaboraciones, gramajes, mise en place, "
            "presentación, incidencias y consultas del manual interno. "
            "No sustituyo al responsable de cocina ni a la ficha técnica oficial."
        )

    if pregunta_norm in [
        "que puedes hacer",
        "qué puedes hacer",
        "para que sirves",
        "para qué sirves",
    ]:
        return (
            "Puedo ayudarte a consultar información del manual interno de cocina: "
            "ingredientes, gramajes, pasos de preparación, presentación de platos, "
            "errores a evitar, incidencias del servicio y pautas de seguridad. "
            "Si una consulta no aparece en el manual, te avisaré para evitar inventar datos."
        )

    if pregunta_norm in [
        "ayuda",
        "ayudame",
        "ayúdame",
        "ayudame con una receta",
        "ayúdame con una receta",
        "necesito ayuda",
        "necesito ayuda con una receta",
    ] or pregunta_norm.startswith("ayudame") or pregunta_norm.startswith("necesito ayuda"):
        return (
            "Claro. Puedes preguntarme de forma directa, por ejemplo: "
            "'medidas arroz jazmín', 'cómo hago maki salmón', "
            "'presentación yakisoba pollo' o 'errores rollitos vegetales'."
        )

    if pregunta_norm in [
        "no entiendo",
        "no se que preguntar",
        "no sé qué preguntar",
    ]:
        return (
            "No pasa nada. Puedes hacerme preguntas cortas y naturales, por ejemplo: "
            "'gramos maki salmón', 'cómo hago arroz sushi', "
            "'errores uramaki teriyaki' o 'medidas mayo sriracha'."
        )

    if pregunta_norm in [
        "dame ejemplos",
        "ponme ejemplos",
    ] or pregunta_norm.startswith("dame ejemplos") or pregunta_norm.startswith("ponme ejemplos"):
        return (
            "Ejemplos de consultas que puedes hacerme: "
            "'medidas arroz jazmín', 'cómo hago rollitos vegetales', "
            "'presentación yakisoba pollo', 'errores maki salmón' o "
            "'qué lleva mayonesa sriracha'."
        )

    if pregunta_norm in [
        "tienes recetas",
        "sabes recetas",
    ]:
        return (
            "Puedo ayudarte con las elaboraciones que estén registradas en el manual interno. "
            "Si una receta no aparece en el manual, te diré que no tengo información suficiente."
        )

    if pregunta_norm in [
        "puedes inventar recetas",
        "puedes inventarte recetas",
    ]:
        return (
            "No. Para evitar errores en cocina, no debo inventar recetas, gramajes ni procedimientos. "
            "Solo puedo apoyarme en el manual interno o recomendar revisar la ficha técnica oficial."
        )

    if pregunta_norm in [
        "estas ahi",
        "estas funcionando",
        "funcionas",
    ]:
        return (
            "Sí, estoy funcionando. Puedes consultarme elaboraciones, gramajes, presentaciones, "
            "errores a evitar o incidencias de cocina."
        )

    if pregunta_norm in [
        "buen trabajo",
        "muy bien",
        "perfecto",
    ]:
        return (
            "Gracias. Me alegra que la ayuda sea útil. Puedes seguir preguntándome sobre "
            "elaboraciones, medidas o incidencias del manual interno."
        )

    if pregunta_norm.startswith("hola") or pregunta_norm in [
        "buenas",
        "buenos dias",
        "buenas tardes",
        "buenas noches",
        "hey",
        "que tal",
        "como estas",
        "hola como estas",
        "hola que tal",
        "todo bien",
    ]:
        return (
            "Hola, estoy listo para ayudarte con consultas de cocina: elaboraciones, "
            "gramajes, mise en place, presentación, incidencias o dudas del manual interno."
        )

    return (
        "Estoy aquí para ayudarte con consultas de cocina del manual interno. "
        "Puedes preguntarme por gramajes, elaboraciones, presentación o incidencias."
    )


# ==========================================================
# CARGA DE MODELO Y CONOCIMIENTO
# ==========================================================

def comprobar_modelo_fusionado():
    """
    Comprueba que existe el modelo fusionado.
    """

    if not os.path.isdir(MODEL_DIR):
        raise FileNotFoundError(
            "No se ha encontrado el modelo fusionado.\n\n"
            f"Carpeta esperada:\n - {MODEL_DIR}\n\n"
            "Antes de ejecutar inferencia debes fusionar el modelo:\n"
            "python 005-fusion.py"
        )


def obtener_configuracion_dispositivo():
    """
    Decide dispositivo y precisión.
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


def buscar_archivos_conocimiento() -> List[str]:
    """
    Busca los JSONL 001-015.
    """

    archivos = []

    for prefix in KNOWLEDGE_FILE_PREFIXES:
        patron = os.path.join(DATA_FOLDER, f"{prefix}_*.jsonl")
        archivos.extend(sorted(glob.glob(patron)))

    return sorted(archivos)


def obtener_question_answer(example: Dict[str, Any]) -> Tuple[str, str]:
    """
    Obtiene pregunta y respuesta del JSONL.
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


def cargar_conocimiento_local() -> List[Dict[str, Any]]:
    """
    Carga los JSONL de conocimiento real.
    """

    conocimiento = []
    archivos = buscar_archivos_conocimiento()

    for archivo in archivos:
        nombre_archivo = os.path.basename(archivo)

        try:
            with open(archivo, "r", encoding="utf-8") as f:
                for numero_linea, linea in enumerate(f, start=1):
                    linea = linea.strip()

                    if not linea:
                        continue

                    try:
                        registro = json.loads(linea)
                    except json.JSONDecodeError:
                        continue

                    question, answer = obtener_question_answer(registro)

                    if not question or not answer:
                        continue

                    texto_completo = f"{question}\n{answer}"
                    intencion_ficha = detectar_intencion_ficha(question, answer)

                    conocimiento.append(
                        {
                            "archivo": nombre_archivo,
                            "linea": numero_linea,
                            "question": question,
                            "answer": answer,
                            "texto": texto_completo,
                            "texto_norm": normalizar_texto(texto_completo),
                            "question_norm": normalizar_texto(question),
                            "answer_norm": normalizar_texto(answer),
                            "palabras_question": set(extraer_palabras_utiles(question)),
                            "palabras_answer": set(extraer_palabras_utiles(answer)),
                            "intencion": intencion_ficha,
                        }
                    )

        except FileNotFoundError:
            continue

    return conocimiento


# ==========================================================
# BÚSQUEDA DE CONTEXTO
# ==========================================================

def calcular_penalizacion_por_palabras_no_encontradas(
    pregunta: str,
    registro: Dict[str, Any],
) -> int:
    """
    Penaliza si hay palabras importantes de la pregunta que no aparecen en la ficha.
    """

    pregunta_expandida = aplicar_aliases(pregunta)
    palabras = extraer_palabras_utiles(pregunta_expandida)

    if not palabras:
        return 0

    texto_norm = registro["texto_norm"]

    no_encontradas = 0

    for palabra in palabras:
        if palabra not in texto_norm:
            no_encontradas += 1

    return no_encontradas * 12


def puntuar_registro(pregunta: str, registro: Dict[str, Any]) -> int:
    """
    Puntúa una ficha según su relación con la pregunta y su intención.
    """

    pregunta_expandida = aplicar_aliases(pregunta)

    pregunta_norm = normalizar_texto(pregunta_expandida)
    palabras = extraer_palabras_utiles(pregunta_expandida)
    intencion_pregunta = detectar_intencion(pregunta)

    intencion_ficha = registro["intencion"]

    texto_norm = registro["texto_norm"]
    question_norm = registro["question_norm"]
    answer_norm = registro["answer_norm"]

    score = 0

    if pregunta_norm == question_norm:
        score += 1000

    if pregunta_norm and pregunta_norm in texto_norm:
        score += 300

    if intencion_pregunta == intencion_ficha:
        score += 90
    elif intencion_pregunta != "general" and intencion_ficha != "general":
        score -= 45

    for palabra in palabras:
        if palabra in registro["palabras_question"]:
            score += 28
        elif palabra in question_norm:
            score += 20
        elif palabra in registro["palabras_answer"]:
            score += 10
        elif palabra in answer_norm:
            score += 6
        elif palabra in texto_norm:
            score += 3

    for i in range(len(palabras) - 1):
        bigrama = f"{palabras[i]} {palabras[i + 1]}"

        if bigrama in question_norm:
            score += 45
        elif bigrama in answer_norm:
            score += 22
        elif bigrama in texto_norm:
            score += 10

    for i in range(len(palabras) - 2):
        trigrama = f"{palabras[i]} {palabras[i + 1]} {palabras[i + 2]}"

        if trigrama in question_norm:
            score += 65
        elif trigrama in answer_norm:
            score += 35
        elif trigrama in texto_norm:
            score += 18

    score -= calcular_penalizacion_por_palabras_no_encontradas(pregunta, registro)

    return score


def buscar_contexto_relevante(
    pregunta: str,
    conocimiento: List[Dict[str, Any]],
) -> List[Dict[str, Any]]:
    """
    Busca fichas relevantes.
    """

    resultados = []

    for registro in conocimiento:
        score = puntuar_registro(pregunta, registro)

        if score > 0:
            resultados.append(
                {
                    **registro,
                    "score": score,
                }
            )

    resultados.sort(key=lambda item: item["score"], reverse=True)

    return resultados[:MAX_CONTEXT_ITEMS]


def hay_contexto_fiable(
    pregunta: str,
    contexto: List[Dict[str, Any]],
) -> bool:
    """
    Decide si la mejor ficha encontrada es fiable.
    """

    if not contexto:
        return False

    mejor_score = contexto[0]["score"]
    intencion_pregunta = detectar_intencion(pregunta)

    if len(contexto) >= 2:
        diferencia = contexto[0]["score"] - contexto[1]["score"]

        if diferencia < 8 and mejor_score < 120:
            return False

    if intencion_pregunta == "cantidad":
        return mejor_score >= 55

    if intencion_pregunta in ["preparacion", "presentacion", "errores"]:
        return mejor_score >= 50

    if intencion_pregunta == "incidencia":
        return mejor_score >= 40

    return mejor_score >= 45


def construir_contexto_textual(contexto: List[Dict[str, Any]]) -> str:
    """
    Construye contexto textual para generación opcional.
    """

    partes = []
    caracteres_usados = 0

    for indice, item in enumerate(contexto, start=1):
        bloque = (
            f"[CONOCIMIENTO {indice}]\n"
            f"Archivo: {item['archivo']}\n"
            f"Pregunta relacionada: {item['question']}\n"
            f"Respuesta interna:\n{item['answer']}\n"
        )

        if caracteres_usados + len(bloque) > MAX_CONTEXT_CHARS:
            break

        partes.append(bloque)
        caracteres_usados += len(bloque)

    return "\n".join(partes).strip()


# ==========================================================
# RESPUESTAS SEGURAS
# ==========================================================

def es_pregunta_alergenos(pregunta: str) -> bool:
    """
    Detecta alergias, intolerancias, trazas o aptitud.
    """

    return contiene_alguna(pregunta, ALLERGEN_KEYWORDS)


def respuesta_segura_alergenos() -> str:
    """
    Respuesta segura para alérgenos.
    """

    return (
        "No puedo confirmar que un plato sea apto para una alergia, intolerancia "
        "o ausencia de trazas sin revisar la ficha técnica oficial del restaurante. "
        "Hay que comprobar ingredientes, salsas, toppings, trazas y posible "
        "contaminación cruzada. Antes de informar al cliente, consulta con una "
        "persona responsable."
    )


def respuesta_sin_informacion() -> str:
    """
    Respuesta cuando no hay ficha suficiente.
    """

    return (
        "No tengo información suficiente sobre esa consulta en el manual interno. "
        "Para evitar errores, revisa la ficha técnica oficial del restaurante o "
        "consulta con una persona responsable antes de usar cantidades, ingredientes "
        "o procedimientos en producción."
    )


def formatear_respuesta_manual(item: Dict[str, Any]) -> str:
    """
    Devuelve una respuesta basada directamente en la ficha interna.
    """

    respuesta = limpiar_para_mostrar(item["answer"])

    return respuesta


# ==========================================================
# GENERACIÓN OPCIONAL CON MODELO
# ==========================================================

def crear_prompt(
    tokenizer,
    pregunta: str,
    contexto_textual: str,
) -> str:
    """
    Crea prompt para Qwen.
    """

    contenido_usuario = (
        "Responde a la pregunta del usuario usando únicamente el CONOCIMIENTO INTERNO.\n\n"
        "REGLAS OBLIGATORIAS:\n"
        "- No añadas ingredientes, decoraciones, toppings, consejos ni variaciones que no aparezcan literalmente en el conocimiento interno.\n"
        "- No inventes ingredientes, gramajes, tiempos ni procedimientos.\n"
        "- Si el conocimiento interno no contiene la respuesta, dilo claramente.\n"
        "- En alérgenos o intolerancias, nunca confirmes que un plato es apto sin ficha técnica oficial.\n"
        "- Responde en español natural, claro y práctico.\n"
        "- No uses palabras en otros idiomas si no aparecen en el conocimiento.\n"
        "- Evita mayúsculas innecesarias.\n\n"
        f"CONOCIMIENTO INTERNO:\n{contexto_textual}\n\n"
        f"PREGUNTA DEL USUARIO:\n{pregunta}"
    )

    mensajes = [
        {
            "role": "system",
            "content": SYSTEM_PROMPT,
        },
        {
            "role": "user",
            "content": contenido_usuario,
        },
    ]

    try:
        prompt = tokenizer.apply_chat_template(
            mensajes,
            tokenize=False,
            add_generation_prompt=True,
        )
    except Exception:
        prompt = (
            f"SYSTEM: {SYSTEM_PROMPT}\n\n"
            f"USER: {contenido_usuario}\n\n"
            f"ASSISTANT:"
        )

    return prompt


def limpiar_respuesta_modelo(respuesta: str) -> str:
    """
    Limpia encabezados del modelo.
    """

    respuesta = respuesta.strip()

    patrones_inicio = [
        "assistant:",
        "kitcherry kitchen assistant:",
        "respuesta:",
    ]

    respuesta_norm = respuesta.lower()

    for patron in patrones_inicio:
        if respuesta_norm.startswith(patron):
            respuesta = respuesta[len(patron):].strip()
            break

    return respuesta


def generar_respuesta_modelo(
    model,
    tokenizer,
    pregunta: str,
    contexto_textual: str,
) -> str:
    """
    Genera respuesta con el modelo.
    En esta versión queda como opción secundaria.
    """

    prompt = crear_prompt(
        tokenizer=tokenizer,
        pregunta=pregunta,
        contexto_textual=contexto_textual,
    )

    inputs = tokenizer(
        prompt,
        return_tensors="pt",
        truncation=True,
        max_length=2048,
    )

    if torch.cuda.is_available():
        inputs = {clave: valor.to(model.device) for clave, valor in inputs.items()}

    with torch.no_grad():
        output_ids = model.generate(
            **inputs,
            max_new_tokens=MAX_NEW_TOKENS,
            do_sample=False,
            repetition_penalty=REPETITION_PENALTY,
            no_repeat_ngram_size=NO_REPEAT_NGRAM_SIZE,
            pad_token_id=tokenizer.eos_token_id,
            eos_token_id=tokenizer.eos_token_id,
        )

    nuevos_tokens = output_ids[0][inputs["input_ids"].shape[-1]:]

    respuesta = tokenizer.decode(
        nuevos_tokens,
        skip_special_tokens=True,
    ).strip()

    return limpiar_respuesta_modelo(respuesta)


# ==========================================================
# CONTROL PRINCIPAL DE RESPUESTA
# ==========================================================

def responder(
    model,
    tokenizer,
    pregunta: str,
    conocimiento: List[Dict[str, Any]],
) -> str:
    """
    Control principal del asistente.
    """

    pregunta = pregunta.strip()

    if not pregunta:
        return "Escribe una pregunta válida."

    pregunta_norm = normalizar_texto(pregunta)

    # 1. Mensajes conversacionales básicos.
    if es_mensaje_conversacional(pregunta):
        return responder_mensaje_conversacional(pregunta)

    # 2. Seguridad de alérgenos/trazas.
    if es_pregunta_alergenos(pregunta):
        return respuesta_segura_alergenos()

    # 3. Buscar ficha interna.
    contexto = []

    if USE_LOCAL_KNOWLEDGE:
        contexto = buscar_contexto_relevante(
            pregunta=pregunta,
            conocimiento=conocimiento,
        )

    if not hay_contexto_fiable(pregunta, contexto):
        return respuesta_sin_informacion()

    mejor_ficha = contexto[0]

    # 4. Modo manual estricto: evita que el modelo deforme recetas.
    if STRICT_MANUAL_MODE:
        return formatear_respuesta_manual(mejor_ficha)

    # 5. Generación opcional.
    contexto_textual = construir_contexto_textual(contexto)

    if not contexto_textual:
        return respuesta_sin_informacion()

    respuesta = generar_respuesta_modelo(
        model=model,
        tokenizer=tokenizer,
        pregunta=pregunta,
        contexto_textual=contexto_textual,
    )

    if not respuesta:
        return respuesta_sin_informacion()

    return respuesta


# ==========================================================
# PROGRAMA PRINCIPAL
# ==========================================================

def main():
    inicio = datetime.now()

    print("=" * 70)
    print("KITCHERRY KITCHEN ASSISTANT - INFERENCIA")
    print("=" * 70)
    print(f"Carpeta del proyecto: {SCRIPT_DIR}")
    print(f"Modelo fusionado: {MODEL_DIR}")
    print("=" * 70)

    comprobar_modelo_fusionado()

    device_map, dtype, dispositivo = obtener_configuracion_dispositivo()

    print("\nDispositivo utilizado:")
    print(f" - {dispositivo}")
    print(f" - Precisión: {dtype}")

    # ------------------------------------------------------
    # 1. Cargar conocimiento local
    # ------------------------------------------------------

    conocimiento = []

    if USE_LOCAL_KNOWLEDGE:
        print("\nCargando conocimiento local desde JSONL 001-015...")

        conocimiento = cargar_conocimiento_local()

        print(f"Registros de conocimiento cargados: {len(conocimiento)}")
    else:
        print("\nConocimiento local desactivado.")

    # ------------------------------------------------------
    # 2. Cargar tokenizer
    # ------------------------------------------------------

    print("\nCargando tokenizer...")

    tokenizer = AutoTokenizer.from_pretrained(
        MODEL_DIR,
        use_fast=True,
    )

    if tokenizer.pad_token is None:
        tokenizer.pad_token = tokenizer.eos_token

    # ------------------------------------------------------
    # 3. Cargar modelo fusionado
    # ------------------------------------------------------

    print("\nCargando modelo fusionado...")

    model = AutoModelForCausalLM.from_pretrained(
        MODEL_DIR,
        dtype=dtype,
        device_map=device_map,
    )

    model.eval()

    fin_carga = datetime.now()

    print("\nModelo cargado correctamente.")
    print(f"Tiempo de carga: {fin_carga - inicio}")

    print("\nPuedes escribir preguntas al asistente.")
    print("Para salir escribe: salir, exit o q")
    print("=" * 70)

    # ------------------------------------------------------
    # 4. Bucle de preguntas
    # ------------------------------------------------------

    while True:
        pregunta = input("\nTú: ").strip()

        if pregunta.lower() in ["salir", "exit", "q"]:
            print("\nCerrando inferencia.")
            break

        print("\nKitcherry Kitchen Assistant:")

        try:
            respuesta = responder(
                model=model,
                tokenizer=tokenizer,
                pregunta=pregunta,
                conocimiento=conocimiento,
            )

            print(respuesta)

        except Exception as error:
            print("Ha ocurrido un error durante la generación:")
            print(error)


if __name__ == "__main__":
    main()