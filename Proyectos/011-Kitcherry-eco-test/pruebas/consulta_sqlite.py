"""
Consulta de stock desde SQLite.

Representa una solución más estructurada que JSON, adecuada cuando el proyecto crece.
"""

import json
import sqlite3
from pathlib import Path
from config import REPETICIONES

RUTA_PRODUCTOS = Path("datos/productos.json")
RUTA_DB = Path("datos/kitcherry_stock.db")


def cargar_productos_json():
    with open(RUTA_PRODUCTOS, "r", encoding="utf-8") as archivo:
        return json.load(archivo)


def preparar_base_datos():
    productos = cargar_productos_json()

    conexion = sqlite3.connect(RUTA_DB)
    cursor = conexion.cursor()

    cursor.execute("""
        CREATE TABLE IF NOT EXISTS productos (
            id INTEGER PRIMARY KEY,
            nombre TEXT NOT NULL,
            categoria TEXT NOT NULL,
            stock INTEGER NOT NULL,
            stock_minimo INTEGER NOT NULL,
            proveedor TEXT NOT NULL
        )
    """)

    cursor.execute("DELETE FROM productos")

    for producto in productos:
        cursor.execute("""
            INSERT INTO productos (id, nombre, categoria, stock, stock_minimo, proveedor)
            VALUES (?, ?, ?, ?, ?, ?)
        """, (
            producto["id"],
            producto["nombre"],
            producto["categoria"],
            producto["stock"],
            producto["stock_minimo"],
            producto["proveedor"]
        ))

    conexion.commit()
    conexion.close()


def consultar_stock_bajo():
    conexion = sqlite3.connect(RUTA_DB)
    cursor = conexion.cursor()

    cursor.execute("""
        SELECT nombre, categoria, stock, stock_minimo, proveedor
        FROM productos
        WHERE stock <= stock_minimo
        ORDER BY categoria, nombre
    """)

    productos = cursor.fetchall()
    conexion.close()

    return productos


def ejecutar_prueba():
    preparar_base_datos()

    total_procesado = 0
    ultimo_resultado = []

    for _ in range(REPETICIONES["consulta_sqlite"]):
        ultimo_resultado = consultar_stock_bajo()
        total_procesado += len(ultimo_resultado)

    return {
        "elementos_procesados": total_procesado,
        "detalle": "Consulta de productos con stock bajo desde SQLite",
        "resultado_ejemplo": [producto[0] for producto in ultimo_resultado]
    }
