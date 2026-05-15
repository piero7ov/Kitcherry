# ==========================================================
# KITCHERRY WEB ANALYTICS
# Archivo: 007-generar-historial-demo.py
# Genera un historial ficticio de ejecuciones para pruebas
# ==========================================================

import sqlite3
from datetime import datetime, timedelta

from config import DB_FILE


# Si está en True, borra el historial anterior y genera uno nuevo.
# Para pruebas queda mejor porque evita duplicados.
REEMPLAZAR_HISTORIAL = True


def crear_tabla_si_no_existe(cursor):
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS ejecuciones_analisis (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fecha_ejecucion TEXT NOT NULL,
            total_visitas INTEGER NOT NULL DEFAULT 0,
            total_formularios INTEGER NOT NULL DEFAULT 0,
            total_errores INTEGER NOT NULL DEFAULT 0,
            paginas_revisadas INTEGER NOT NULL DEFAULT 0,
            enlaces_detectados INTEGER NOT NULL DEFAULT 0,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        )
    """)


def generar_historial_demo():
    conexion = sqlite3.connect(DB_FILE)
    cursor = conexion.cursor()

    crear_tabla_si_no_existe(cursor)

    if REEMPLAZAR_HISTORIAL:
        cursor.execute("DELETE FROM ejecuciones_analisis")
        cursor.execute("DELETE FROM sqlite_sequence WHERE name='ejecuciones_analisis'")

    hoy = datetime.now()

    datos_demo = [
        # dias_atras, visitas, formularios, errores, paginas_revisadas, enlaces_detectados
        (6, 85, 3, 9, 5, 18),
        (5, 110, 4, 7, 5, 20),
        (4, 135, 6, 6, 6, 24),
        (3, 160, 7, 5, 6, 26),
        (2, 190, 9, 3, 6, 28),
        (1, 225, 11, 2, 6, 30),
        (0, 260, 14, 1, 6, 32),
    ]

    for dias_atras, visitas, formularios, errores, paginas_revisadas, enlaces_detectados in datos_demo:
        fecha = hoy - timedelta(days=dias_atras)
        fecha_iso = fecha.replace(hour=18, minute=30, second=0, microsecond=0).isoformat()

        cursor.execute("""
            INSERT INTO ejecuciones_analisis
            (
                fecha_ejecucion,
                total_visitas,
                total_formularios,
                total_errores,
                paginas_revisadas,
                enlaces_detectados
            )
            VALUES (?, ?, ?, ?, ?, ?)
        """, (
            fecha_iso,
            visitas,
            formularios,
            errores,
            paginas_revisadas,
            enlaces_detectados
        ))

    conexion.commit()
    conexion.close()

    print("Historial ficticio generado correctamente.")
    print("Registros insertados:", len(datos_demo))


if __name__ == "__main__":
    generar_historial_demo()