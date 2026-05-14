"""
Consulta de stock desde archivo JSON.

Representa una solución ligera para proyectos pequeños o prototipos.
"""

import json
from pathlib import Path
from config import REPETICIONES

RUTA_PRODUCTOS = Path("datos/productos.json")


def cargar_productos():
    with open(RUTA_PRODUCTOS, "r", encoding="utf-8") as archivo:
        return json.load(archivo)


def obtener_stock_bajo(productos):
    return [producto for producto in productos if producto["stock"] <= producto["stock_minimo"]]


def ejecutar_prueba():
    total_procesado = 0
    ultimo_resultado = []

    for _ in range(REPETICIONES["consulta_json"]):
        productos = cargar_productos()
        ultimo_resultado = obtener_stock_bajo(productos)
        total_procesado += len(productos)

    return {
        "elementos_procesados": total_procesado,
        "detalle": "Consulta de productos con stock bajo desde JSON",
        "resultado_ejemplo": [producto["nombre"] for producto in ultimo_resultado]
    }
