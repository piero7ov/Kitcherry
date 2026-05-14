"""
Clasificación ligera de consultas usando reglas y palabras clave.

Esta prueba representa una solución eficiente: para mensajes sencillos,
no siempre hace falta usar inteligencia artificial.
"""

import json
from pathlib import Path
from config import REPETICIONES

RUTA_CONSULTAS = Path("datos/consultas.json")

PALABRAS_CLAVE = {
    "reserva": ["reservar", "reserva", "mesa", "personas", "terraza", "modificar"],
    "alergenos": ["alergia", "alérgica", "alergenos", "alérgenos", "sin gluten", "frutos secos", "marisco"],
    "horarios": ["horario", "apertura", "domingo", "cerráis", "abierto"],
    "carta": ["carta", "menú", "menu", "platos", "precios"],
    "comercial": ["presupuesto", "sistema", "kitcherry", "información", "comunicaciones"]
}


def cargar_consultas():
    with open(RUTA_CONSULTAS, "r", encoding="utf-8") as archivo:
        return json.load(archivo)


def clasificar_mensaje(mensaje: str) -> str:
    texto = mensaje.lower()

    for categoria, palabras in PALABRAS_CLAVE.items():
        for palabra in palabras:
            if palabra in texto:
                return categoria

    return "otros"


def ejecutar_prueba():
    consultas = cargar_consultas()
    resultados = []
    total_procesado = 0

    for _ in range(REPETICIONES["clasificacion_reglas"]):
        for consulta in consultas:
            categoria = clasificar_mensaje(consulta["mensaje"])
            resultados.append({
                "id": consulta["id"],
                "cliente": consulta["cliente"],
                "categoria": categoria
            })
            total_procesado += 1

    return {
        "elementos_procesados": total_procesado,
        "detalle": "Clasificación mediante palabras clave y reglas simples",
        "resultado_ejemplo": resultados[:3]
    }
