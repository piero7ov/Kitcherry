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
    "modelo-kamado-restaurante-v1"
)


# ==========================================================
# PROMPT DEL SISTEMA
# ==========================================================

SYSTEM_PROMPT = (
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
        "ñ": "n",
        "ü": "u"
    }

    for original, reemplazo in reemplazos.items():
        texto = texto.replace(original, reemplazo)

    return texto


def contiene_alguno(texto, patrones):
    return any(patron in texto for patron in patrones)


def respuesta_segura(pregunta):
    pregunta_normalizada = normalizar_texto(pregunta)

    # ------------------------------------------------------
    # Bloque anti-invención: platos que NO aparecen
    # ------------------------------------------------------

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

    # ------------------------------------------------------
    # Separación Kamado / Kitcherry
    # ------------------------------------------------------

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

    # ------------------------------------------------------
    # Identidad básica de Kamado
    # ------------------------------------------------------

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

    # ------------------------------------------------------
    # Baos y rolls
    # ------------------------------------------------------

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

    # ------------------------------------------------------
    # Carta, platos y precios concretos
    # ------------------------------------------------------

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

    # ------------------------------------------------------
    # Cócteles y bebidas
    # ------------------------------------------------------

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

    # ------------------------------------------------------
    # Postres
    # ------------------------------------------------------

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

    # ------------------------------------------------------
    # Vegetarianos, veganos, alérgenos
    # ------------------------------------------------------

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

    # ------------------------------------------------------
    # Reservas, delivery, take away y app
    # ------------------------------------------------------

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

    # ------------------------------------------------------
    # Seguridad: datos no confirmados
    # ------------------------------------------------------

    temas_horario = [
        "horario",
        "a que hora abre",
        "hora abre",
        "a que hora cierra",
        "hora cierra",
        "esta abierto",
        "abren hoy",
        "cierra hoy"
    ]

    temas_contacto = [
        "telefono",
        "numero de telefono",
        "correo",
        "email",
        "contacto oficial"
    ]

    temas_direccion = [
        "direccion exacta",
        "calle",
        "ubicacion exacta",
        "donde esta exactamente",
        "domicilio"
    ]

    if contiene_alguno(pregunta_normalizada, temas_horario):
        return (
            "No dispongo de información concreta sobre horarios actualizados de Kamado. "
            "Para confirmarlo, consulta la web oficial o contacta con el restaurante correspondiente."
        )

    if contiene_alguno(pregunta_normalizada, temas_contacto):
        return (
            "No dispongo de información concreta sobre teléfono, correo o contacto oficial actualizado de Kamado. "
            "Lo recomendable es consultarlo en la web oficial o en la ficha del restaurante correspondiente."
        )

    if contiene_alguno(pregunta_normalizada, temas_direccion):
        return (
            "No dispongo de la dirección exacta actualizada de cada restaurante Kamado. "
            "Para confirmarla, consulta la web oficial o la ficha actualizada del restaurante."
        )

    return None


# ==========================================================
# MODELO
# ==========================================================

def cargar_modelo():
    if not os.path.isdir(MODEL_PATH):
        raise FileNotFoundError("No se encontró el modelo Kamado restaurante v1 fusionado.")

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