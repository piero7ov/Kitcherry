#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import os
import sys
import threading

import torch
from flask import Flask, request, jsonify
from transformers import AutoTokenizer, AutoModelForCausalLM


# ==========================================================
# UTF-8
# ==========================================================

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")


# ==========================================================
# CONFIGURACIÓN GENERAL
# ==========================================================

app = Flask(__name__)

torch.set_num_threads(4)

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))

# servidor_flask está dentro de:
# 004-entrenamiento IA/105-optimizacion-con-flask/servidor_flask
#
# PROJECT_DIR apunta a:
# 004-entrenamiento IA

PROJECT_DIR = os.path.abspath(
    os.path.join(SCRIPT_DIR, "..", "..")
)

KITCHERRY_MODEL_PATH = os.path.join(
    PROJECT_DIR,
    "102-reentrenamiento",
    "outputs",
    "modelo-kitcherry-corporativo-v2"
)

KAMADO_MODEL_PATH = os.path.join(
    PROJECT_DIR,
    "103-modo-restaurante-kamado",
    "outputs",
    "modelo-kamado-restaurante-v1"
)

MODELS = {}
MODEL_LOCK = threading.Lock()


# ==========================================================
# PROMPTS
# ==========================================================

SYSTEM_PROMPT_KITCHERRY = (
    "Eres el asistente corporativo de Kitcherry, una empresa de herramientas "
    "de software para hostelería desarrollada por PieroDev. Respondes en español "
    "de forma clara, profesional y cercana. Debes explicar qué es Kitcherry, "
    "sus productos actuales, su producto estrella, el sistema inteligente de "
    "consultas y comunicaciones, el chatbot para restaurantes y su enfoque "
    "modular para pequeños negocios hosteleros. "
    "Solo debes responder con información confirmada. Si no conoces un dato "
    "concreto, debes decir que no dispones de información concreta y no inventar."
)

SYSTEM_PROMPT_KAMADO = (
    "Eres un asistente de atención al cliente para un restaurante llamado Kamado. "
    "Kamado es un restaurante de comida asiática inspirado en sabores de países "
    "como Tailandia, Malasia, Vietnam, Corea, China y Japón. "
    "Respondes en español de forma clara, cercana y útil. "
    "Puedes responder sobre carta, platos, precios de referencia, cócteles, postres, "
    "alérgenos, reservas, take away, delivery, app y dudas frecuentes de Kamado. "
    "Muy importante: Kamado no es Kitcherry. Kamado no es un modo de comida de Kitcherry. "
    "Kamado es el restaurante usado como caso práctico para demostrar el sistema de Kitcherry. "
    "Kitcherry es la empresa o proyecto de software que crea el chatbot, pero el usuario en este modo "
    "pregunta sobre Kamado como restaurante. "
    "No debes inventar platos que no estén en la información de referencia. "
    "No debes mencionar burritos, tacos, hamburguesas, pizzas, moretones ni platos que no formen parte de la carta de referencia. "
    "Cuando hables de precios, indica que son precios de referencia y que pueden cambiar. "
    "No debes inventar horarios, teléfonos, direcciones exactas ni datos no confirmados. "
    "Si el usuario pregunta por alergias o intolerancias, debes recomendar confirmar siempre "
    "con el personal del restaurante."
)


# ==========================================================
# UTILIDADES
# ==========================================================

def normalizar_texto(texto):
    texto = texto.lower()

    reemplazos = {
        "á": "a",
        "é": "e",
        "í": "i",
        "ó": "o",
        "ú": "u",
        "ñ": "n",
        "ü": "u"
    }

    for original, reemplazo in reemplazos.items():
        texto = texto.replace(original, reemplazo)

    return texto


def contiene_alguno(texto, patrones):
    return any(patron in texto for patron in patrones)


def detectar_intencion_contacto(pregunta):
    pregunta_normalizada = normalizar_texto(pregunta)

    patrones = [
        "quiero contratar",
        "contratar kitcherry",
        "me interesa",
        "quiero una demo",
        "solicitar demo",
        "pedir demo",
        "quiero probar",
        "como puedo contactar",
        "contactar con kitcherry",
        "quiero mas informacion",
        "tengo un restaurante",
        "para mi restaurante",
        "para mi negocio",
        "podemos hablar",
        "presupuesto",
        "solicitar informacion"
    ]

    return contiene_alguno(pregunta_normalizada, patrones)


# ==========================================================
# CAPA SEGURA KITCHERRY
# ==========================================================

def respuesta_segura_kitcherry(pregunta):
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

    if contiene_alguno(pregunta_normalizada, temas_precio):
        return "No dispongo de información concreta sobre los precios de Kitcherry."

    if contiene_alguno(pregunta_normalizada, temas_ubicacion):
        return "No dispongo de información concreta sobre oficinas físicas o dirección oficial de Kitcherry."

    if contiene_alguno(pregunta_normalizada, temas_app):
        return "No dispongo de información concreta sobre una app móvil oficial de Kitcherry."

    if contiene_alguno(pregunta_normalizada, temas_contacto):
        return "No dispongo de información concreta sobre teléfono, correo o contacto oficial de Kitcherry."

    if contiene_alguno(pregunta_normalizada, temas_clientes):
        return "No dispongo de información concreta sobre clientes reales o casos reales de Kitcherry."

    if contiene_alguno(pregunta_normalizada, temas_restaurante):
        return (
            "No. Kitcherry no es un restaurante ni vende comida. "
            "Es una empresa de herramientas de software para hostelería desarrollada por PieroDev."
        )

    if contiene_alguno(pregunta_normalizada, temas_servicios):
        return (
            "Actualmente Kitcherry se centra en dos líneas principales: "
            "el sistema inteligente de consultas y comunicaciones para hostelería, "
            "que es su producto estrella, y el chatbot de atención al cliente para restaurantes. "
            "En el futuro se podrán añadir más herramientas modulares."
        )

    return None


# ==========================================================
# CAPA SEGURA KAMADO
# ==========================================================

def respuesta_segura_kamado(pregunta):
    pregunta_normalizada = normalizar_texto(pregunta)

    if contiene_alguno(pregunta_normalizada, [
        "tacos",
        "taco",
        "hamburguesa",
        "hamburguesas",
        "pizza",
        "pizzas",
        "burrito",
        "burritos",
        "moretones",
        "menu infantil",
        "menú infantil"
    ]):
        return (
            "No me consta que esos platos aparezcan en la carta de referencia de Kamado. "
            "Para evitar darte información incorrecta, lo recomendable es consultar la carta actualizada "
            "del restaurante."
        )

    if contiene_alguno(pregunta_normalizada, [
        "kamado forma parte de kitcherry",
        "kamado es parte de kitcherry",
        "kamado es kitcherry",
        "kamado pertenece a kitcherry",
        "kamado es un modo de kitcherry",
        "kamado es una herramienta de kitcherry",
        "kitcherry vende comida a traves de kamado",
        "kitcherry vende comida por kamado"
    ]):
        return (
            "No. Kamado no forma parte de Kitcherry. Kamado es un restaurante usado como caso práctico "
            "para demostrar cómo Kitcherry puede adaptar un chatbot a un negocio hostelero concreto. "
            "Kitcherry es el proyecto de software; Kamado es el ejemplo de restaurante."
        )

    if contiene_alguno(pregunta_normalizada, [
        "eres el chatbot oficial",
        "chatbot oficial de kamado",
        "perteneces a kamado",
        "trabajas para kamado",
        "sois kamado",
        "eres de kamado"
    ]):
        return (
            "No. Este chatbot no es el chatbot oficial de Kamado. Es una demostración del sistema "
            "Kitcherry utilizando información pública y material de referencia de Kamado como caso práctico."
        )

    if contiene_alguno(pregunta_normalizada, [
        "que es kamado",
        "explicame kamado",
        "hablame de kamado",
        "kamado que es"
    ]):
        return (
            "Kamado es un restaurante de comida asiática inspirado en sabores de países "
            "como Tailandia, Malasia, Vietnam, Corea, China y Japón. Su propuesta combina "
            "platos asiáticos, ambiente moderno y opciones para comer en local, pedir para llevar "
            "o delivery según disponibilidad."
        )

    if contiene_alguno(pregunta_normalizada, [
        "que significa kamado",
        "que es un horno kamado",
        "horno kamado"
    ]):
        return (
            "Un kamado es un horno japonés utilizado para cocinar lentamente y a baja temperatura. "
            "En el caso del restaurante Kamado, el nombre se relaciona con esa inspiración asiática "
            "y con una propuesta gastronómica moderna."
        )

    if contiene_alguno(pregunta_normalizada, [
        "que tipo de comida ofrece kamado",
        "que comida ofrece kamado",
        "comida de kamado",
        "kamado es asiatico",
        "restaurante asiatico",
        "estilo de comida tiene kamado"
    ]):
        return (
            "Kamado ofrece comida asiática con platos inspirados en Tailandia, Malasia, "
            "Vietnam, Corea, China y Japón. En su carta de referencia aparecen entrantes, "
            "platos al vapor, baos, rolls, woks, bowls, ramen, currys, postres y cócteles."
        )

    if contiene_alguno(pregunta_normalizada, [
        "que platos puedo encontrar",
        "que platos hay",
        "que tiene la carta",
        "platos de kamado",
        "carta de kamado",
        "que hay en la carta",
        "que opciones principales tiene",
        "platos destacados"
    ]):
        return (
            "Según la carta de referencia, en Kamado puedes encontrar entrantes como Thai Salad, "
            "Berenjenas Tian, Malaysian Roll, Karaage o Takoyaki de Pulpo; platos al vapor como "
            "Gyozas Veggie, Hakao y baos; rolls como Tiger Roll, Singapore Roll o Dragon Roll; "
            "platos principales como Udon Style, Tom Kha Kai, Ramen Curry, Ribu Ramen, Pad Thai, "
            "Kamado Rice, Curry Massaman y Curry Amarillo; además de postres y cócteles."
        )

    if contiene_alguno(pregunta_normalizada, [
        "kamado tiene baos",
        "tiene baos",
        "baos de kamado",
        "que baos tiene",
        "que baos hay",
        "me apetecen baos",
        "baos"
    ]):
        return (
            "Sí. Según la carta de referencia, Kamado tiene baos como Tsin Bao Blanco, "
            "Pokku Bao, Arab Bao y Veggie Bao. Los precios de referencia son: "
            "Tsin Bao Blanco 5,40 €, Pokku Bao 6,40 €, Arab Bao 6,90 € y Veggie Bao 6,40 €. "
            "Los precios pueden cambiar, por lo que conviene confirmarlos en la carta actualizada."
        )

    if contiene_alguno(pregunta_normalizada, [
        "que rolls tiene",
        "rolls de kamado",
        "que rolls hay",
        "kamado tiene rolls",
        "tiene rolls",
        "quiero rolls",
        "rolls"
    ]):
        return (
            "Según la carta de referencia, Kamado tiene rolls como Tiger Roll, "
            "Singapore Roll, Secret Roll, Fresh Roll, Dragon Roll y Snow Roll. "
            "Los precios de referencia son: Tiger Roll 11,90 €, Singapore Roll 13,40 €, "
            "Secret Roll 12,90 €, Fresh Roll 11,90 €, Dragon Roll 12,90 € y Snow Roll 13,40 €. "
            "Los precios pueden cambiar, por lo que conviene confirmarlos en la carta actualizada."
        )

    if "pad thai" in pregunta_normalizada:
        if contiene_alguno(pregunta_normalizada, ["cuanto", "precio", "cuesta", "vale"]):
            return (
                "Según la carta de referencia, Pad Thai cuesta 15,40 € con pollo, "
                "14,90 € con verduras, 15,90 € con gambas y 14,90 € con tofu. "
                "Los precios pueden cambiar, por lo que conviene confirmarlos en la carta actualizada."
            )

        return (
            "Pad Thai lleva fideos de arroz salteados con huevo, zanahoria, brotes de soja, "
            "cebolla, cilantro, cacahuetes, lima y thai chili. Puede pedirse con pollo, "
            "verduras, gambas o tofu."
        )

    if "kamado rice" in pregunta_normalizada:
        if contiene_alguno(pregunta_normalizada, ["cuanto", "precio", "cuesta", "vale"]):
            return (
                "Según la carta de referencia, Kamado Rice cuesta 15,40 € con pollo, "
                "14,90 € con verduras, 15,90 € con gambas y 14,90 € con tofu. "
                "Los precios pueden cambiar."
            )

        return (
            "Kamado Rice lleva arroz jazmín salteado con huevo, zanahoria, cebolla, "
            "jengibre y salsa Kamado. Puede pedirse con pollo, verduras, gambas o tofu."
        )

    if "curry massaman" in pregunta_normalizada:
        if contiene_alguno(pregunta_normalizada, ["cuanto", "precio", "cuesta", "vale"]):
            return (
                "Según la carta de referencia, Curry Massaman cuesta 15,40 € con pollo, "
                "14,90 € con verduras, 15,90 € con gambas y 14,90 € con tofu. "
                "Los precios pueden cambiar."
            )

        return (
            "Curry Massaman es un curry tailandés con leche de coco, cebolla y laurel, "
            "acompañado de arroz rojo. Puede pedirse con pollo, verduras, gambas o tofu."
        )

    if "curry amarillo" in pregunta_normalizada:
        if contiene_alguno(pregunta_normalizada, ["cuanto", "precio", "cuesta", "vale"]):
            return (
                "Según la carta de referencia, Curry Amarillo cuesta 15,40 € con pollo, "
                "14,90 € con verduras, 15,90 € con gambas y 14,90 € con tofu. "
                "Los precios pueden cambiar."
            )

        return (
            "Curry Amarillo es un curry tailandés con leche de coco, aromáticos, cebolla morada "
            "y cebolleta blanca, acompañado de arroz jazmín. Puede pedirse con pollo, verduras, "
            "gambas o tofu."
        )

    if "ramen curry" in pregunta_normalizada:
        if contiene_alguno(pregunta_normalizada, ["cuanto", "precio", "cuesta", "vale"]):
            return (
                "Según la carta de referencia, Ramen Curry cuesta 14,90 €. "
                "Los precios pueden cambiar, por lo que conviene confirmarlo en la carta actualizada."
            )

        return (
            "Ramen Curry es una sopa ramen fusionada con curry de origen indonesio estilo Laksa Singapur, "
            "con verduras, noodles, base con curry y gambas."
        )

    if "ribu ramen" in pregunta_normalizada:
        if contiene_alguno(pregunta_normalizada, ["cuanto", "precio", "cuesta", "vale"]):
            return (
                "Según la carta de referencia, Ribu Ramen cuesta 14,90 €. "
                "Los precios pueden cambiar, por lo que conviene confirmarlo en la carta actualizada."
            )

        return (
            "Ribu Ramen es una sopa ramen de soja y miso con costillas a baja temperatura, "
            "shiitake encurtido, huevo en soja, cebolleta japonesa y germinados de soja."
        )

    if contiene_alguno(pregunta_normalizada, [
        "no quiero alcohol",
        "sin alcohol",
        "no bebo alcohol",
        "algo sin alcohol",
        "bebida sin alcohol",
        "bebidas sin alcohol",
        "coctel sin alcohol",
        "cocteles sin alcohol",
        "cocktail sin alcohol",
        "cocktails sin alcohol"
    ]):
        return (
            "Sí. Según la carta de referencia, Kamado ofrece cócteles sin alcohol como "
            "Long Dan y Monster Buu. Long Dan lleva granadina, chili, yuzu, zumo de piña "
            "y naranja. Monster Buu lleva té verde, manzana y jengibre."
        )

    if contiene_alguno(pregunta_normalizada, [
        "que cocteles puedo pedir",
        "que cocteles tiene",
        "cocteles de kamado",
        "cocktails de kamado",
        "kamado tiene cocteles",
        "algun coctel",
        "carta de cocteles",
        "cocteles puedo pedir",
        "cocteles tiene"
    ]):
        return (
            "Según la carta de referencia, Kamado ofrece cócteles como Geisha en Moscow, "
            "Sumono, Kamado Passion, Smoke Dragon, Asian Kraken y Bruce Lee. "
            "También tiene opciones sin alcohol como Long Dan y Monster Buu. "
            "Los precios pueden cambiar, por lo que conviene confirmarlos en la carta actualizada."
        )

    if "geisha en moscow" in pregunta_normalizada:
        if contiene_alguno(pregunta_normalizada, ["cuanto", "precio", "cuesta", "vale"]):
            return (
                "Según la carta de referencia, Geisha en Moscow cuesta 8,00 €. "
                "Los precios pueden cambiar, por lo que conviene confirmarlo en la carta actualizada."
            )

        return (
            "Geisha en Moscow lleva macerado de fresas casero, jengibre, sirope chili pepper, "
            "agave, sake y triple seco. Es un cóctel afrutado y refrescante con un ligero toque picante."
        )

    if "long dan" in pregunta_normalizada:
        if contiene_alguno(pregunta_normalizada, ["cuanto", "precio", "cuesta", "vale"]):
            return (
                "Según la carta de referencia, Long Dan cuesta 6,50 € y es una opción sin alcohol. "
                "Los precios pueden cambiar."
            )

        return (
            "Long Dan es un cóctel sin alcohol con granadina, chili, yuzu, zumo de piña y naranja."
        )

    if "monster buu" in pregunta_normalizada:
        if contiene_alguno(pregunta_normalizada, ["cuanto", "precio", "cuesta", "vale"]):
            return (
                "Según la carta de referencia, Monster Buu cuesta 6,50 € y es una opción sin alcohol. "
                "Los precios pueden cambiar."
            )

        return (
            "Monster Buu es un cóctel sin alcohol con té verde, manzana y jengibre."
        )

    if "death by chocolate" in pregunta_normalizada:
        if contiene_alguno(pregunta_normalizada, ["cuanto", "precio", "cuesta", "vale"]):
            return (
                "Según la carta de referencia, Death by Chocolate cuesta 5,90 €. "
                "Los precios pueden cambiar, por lo que conviene confirmarlo en la carta actualizada."
            )

        return (
            "Death by Chocolate es un postre de la carta de referencia de Kamado. "
            "Se describe como una tarta de chocolate esponjoso, con bizcocho de chocolate "
            "relleno de mousse y cubierto de ganache."
        )

    if contiene_alguno(pregunta_normalizada, [
        "que postres tiene",
        "postres de kamado",
        "tiene postres",
        "algo dulce"
    ]):
        return (
            "Según la carta de referencia, Kamado tiene postres como Tian Bao Zi, Tian Pan, "
            "Mochi, Death by Chocolate y Cheesecake."
        )

    if "mochi" in pregunta_normalizada:
        if contiene_alguno(pregunta_normalizada, ["cuanto", "precio", "cuesta", "vale"]):
            return (
                "Según la carta de referencia, los mochis cuestan 5,90 €. "
                "Los precios pueden cambiar."
            )

        return (
            "Según la carta de referencia, los mochis pueden elegirse entre sésamo negro, "
            "litchi, yuzu o té verde."
        )

    if "cheesecake" in pregunta_normalizada:
        if contiene_alguno(pregunta_normalizada, ["cuanto", "precio", "cuesta", "vale"]):
            return (
                "Según la carta de referencia, Cheesecake cuesta 5,90 €. "
                "Los precios pueden cambiar, por lo que conviene confirmarlo en la carta actualizada."
            )

        return (
            "Cheesecake es una tarta de queso horneada y fundente con puré de frutos del bosque."
        )

    if contiene_alguno(pregunta_normalizada, [
        "opciones vegetarianas",
        "vegetariano",
        "vegetariana"
    ]):
        return (
            "Sí. La carta de referencia marca opciones vegetarianas y también permite algunas "
            "alternativas con verduras o tofu. Para confirmar cada plato, conviene revisar la carta "
            "actualizada o consultarlo con el personal."
        )

    if contiene_alguno(pregunta_normalizada, [
        "opciones veganas",
        "vegano",
        "vegana",
        "tofu"
    ]):
        return (
            "Sí. La carta de referencia indica opciones veganas mediante iconos y también aparecen "
            "alternativas con tofu. Para confirmar si un plato concreto es vegano, conviene revisar "
            "la carta actualizada o preguntarlo al personal."
        )

    if contiene_alguno(pregunta_normalizada, [
        "alergia",
        "alergias",
        "alergeno",
        "alergenos",
        "intolerancia",
        "intolerancias",
        "cacahuetes",
        "frutos secos"
    ]):
        return (
            "Kamado dispone de carta de alérgenos, pero si tienes una alergia o intolerancia importante, "
            "debes confirmarlo siempre con el personal del restaurante antes de pedir. La información del chatbot "
            "es orientativa y puede contener errores."
        )

    if contiene_alguno(pregunta_normalizada, [
        "reservar",
        "reserva",
        "reservas"
    ]):
        return (
            "Sí. La web de Kamado incluye una sección de reservas. Según la información de referencia, "
            "aparecen opciones como León, Valencia CC Saler, X-Madrid y Zaragoza."
        )

    if contiene_alguno(pregunta_normalizada, [
        "delivery",
        "domicilio",
        "a domicilio",
        "reparto"
    ]):
        return (
            "Sí. Kamado ofrece opciones de delivery o reparto a domicilio desde su sección Take Away & Delivery, "
            "según disponibilidad del servicio en tu zona."
        )

    if contiene_alguno(pregunta_normalizada, [
        "take away",
        "recoger",
        "recogida",
        "para llevar"
    ]):
        return (
            "Sí. Kamado ofrece opción de take away, permitiendo recoger el pedido en el local según disponibilidad."
        )

    if contiene_alguno(pregunta_normalizada, [
        "app",
        "aplicacion"
    ]):
        return (
            "Sí. La web de Kamado indica que se puede descargar su app desde App Store y Play Store."
        )

    if contiene_alguno(pregunta_normalizada, [
        "horario",
        "a que hora abre",
        "hora abre",
        "a que hora cierra",
        "hora cierra",
        "esta abierto",
        "abren hoy",
        "cierra hoy"
    ]):
        return (
            "No dispongo de información concreta sobre horarios actualizados de Kamado. "
            "Para confirmarlo, consulta la web oficial o contacta con el restaurante correspondiente."
        )

    if contiene_alguno(pregunta_normalizada, [
        "telefono",
        "numero de telefono",
        "correo",
        "email",
        "contacto oficial"
    ]):
        return (
            "No dispongo de información concreta sobre teléfono, correo o contacto oficial actualizado de Kamado. "
            "Lo recomendable es consultarlo en la web oficial o en la ficha del restaurante correspondiente."
        )

    if contiene_alguno(pregunta_normalizada, [
        "direccion exacta",
        "calle",
        "ubicacion exacta",
        "donde esta exactamente",
        "domicilio"
    ]):
        return (
            "No dispongo de la dirección exacta actualizada de cada restaurante Kamado. "
            "Para confirmarla, consulta la web oficial o la ficha actualizada del restaurante."
        )

    return None


# ==========================================================
# CARGA DE MODELOS
# ==========================================================

def get_model_config(mode):
    if mode == "restaurante":
        return {
            "model_path": KAMADO_MODEL_PATH,
            "system_prompt": SYSTEM_PROMPT_KAMADO,
            "safe_function": respuesta_segura_kamado,
        }

    return {
        "model_path": KITCHERRY_MODEL_PATH,
        "system_prompt": SYSTEM_PROMPT_KITCHERRY,
        "safe_function": respuesta_segura_kitcherry,
    }


def cargar_modelo(mode):
    with MODEL_LOCK:
        if mode in MODELS:
            return MODELS[mode]["tokenizer"], MODELS[mode]["model"]

        config = get_model_config(mode)
        model_path = config["model_path"]

        if not os.path.isdir(model_path):
            raise FileNotFoundError(
                f"No se encontró el modelo para el modo '{mode}': {model_path}"
            )

        use_cuda = torch.cuda.is_available()

        if use_cuda:
            dtype = torch.float16
            device_map = "auto"
        else:
            dtype = torch.float32
            device_map = {"": "cpu"}

        print("=" * 60)
        print(f"🧠 Cargando modelo para modo: {mode}")
        print(f"📁 Ruta: {model_path}")
        print(f"⚙️ CUDA disponible: {use_cuda}")
        print("=" * 60)

        tokenizer = AutoTokenizer.from_pretrained(
            model_path,
            local_files_only=True,
            use_fast=True,
        )

        if tokenizer.pad_token is None:
            tokenizer.pad_token = tokenizer.eos_token

        model = AutoModelForCausalLM.from_pretrained(
            model_path,
            local_files_only=True,
            dtype=dtype,
            device_map=device_map,
        )

        model.eval()

        model.generation_config.do_sample = False
        model.generation_config.temperature = 1.0
        model.generation_config.top_p = 1.0
        model.generation_config.top_k = 50

        MODELS[mode] = {
            "tokenizer": tokenizer,
            "model": model,
        }

        return tokenizer, model


def generar_respuesta(mode, pregunta):
    config = get_model_config(mode)
    safe_function = config["safe_function"]
    system_prompt = config["system_prompt"]

    respuesta_bloqueada = safe_function(pregunta)

    if respuesta_bloqueada is not None:
        return respuesta_bloqueada

    tokenizer, model = cargar_modelo(mode)

    conversation = [
        {"role": "system", "content": system_prompt},
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
            f"SYSTEM: {system_prompt}\n"
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
            max_new_tokens=260,
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
# RUTAS FLASK
# ==========================================================

@app.route("/", methods=["GET"])
def index():
    return jsonify({
        "ok": True,
        "message": "Servidor Flask IA Kitcherry activo.",
        "routes": ["/health", "/chat"]
    })


@app.route("/health", methods=["GET"])
def health():
    return jsonify({
        "ok": True,
        "status": "running",
        "project_dir": PROJECT_DIR,
        "kitcherry_model_exists": os.path.isdir(KITCHERRY_MODEL_PATH),
        "kamado_model_exists": os.path.isdir(KAMADO_MODEL_PATH),
        "loaded_models": list(MODELS.keys())
    })


@app.route("/chat", methods=["POST"])
def chat():
    data = request.get_json(silent=True)

    if not isinstance(data, dict):
        return jsonify({
            "ok": False,
            "answer": "No se recibió un JSON válido."
        }), 400

    mode = str(data.get("mode", "kitcherry")).strip().lower()
    question = str(data.get("question", "")).strip()

    if mode not in ["kitcherry", "restaurante"]:
        mode = "kitcherry"

    if question == "":
        return jsonify({
            "ok": False,
            "answer": "Escribe una pregunta para continuar.",
            "mode": mode,
            "contact_intent": False
        }), 400

    try:
        answer = generar_respuesta(mode, question)

        return jsonify({
            "ok": True,
            "answer": answer,
            "mode": mode,
            "contact_intent": detectar_intencion_contacto(question)
        })

    except Exception as e:
        return jsonify({
            "ok": False,
            "answer": f"Error al consultar el modelo: {str(e)}",
            "mode": mode,
            "contact_intent": False
        }), 500


# ==========================================================
# EJECUCIÓN
# ==========================================================

if __name__ == "__main__":
    print("=" * 60)
    print("🚀 Servidor Flask IA Kitcherry")
    print("=" * 60)
    print(f"📁 PROJECT_DIR: {PROJECT_DIR}")
    print(f"📁 Modelo Kitcherry: {KITCHERRY_MODEL_PATH}")
    print(f"📁 Modelo Kamado: {KAMADO_MODEL_PATH}")
    print("🌐 URL: http://127.0.0.1:5005")
    print("=" * 60)

    app.run(
        host="127.0.0.1",
        port=5005,
        debug=False
    )