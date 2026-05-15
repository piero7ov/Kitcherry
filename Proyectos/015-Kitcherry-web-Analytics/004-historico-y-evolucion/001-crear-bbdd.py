# ==========================================================
# KITCHERRY WEB ANALYTICS
# Archivo: 001-crear-bbdd.py
# Crea la base de datos SQLite del sistema de seguimiento
# ==========================================================

import sqlite3
from config import DB_FILE


def crear_bbdd():
    conexion = sqlite3.connect(DB_FILE)
    cursor = conexion.cursor()

    # Tabla principal para guardar las visitas detectadas en los logs.
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS visitas_web (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fecha_iso TEXT NOT NULL,
            ip TEXT NOT NULL,
            metodo TEXT NOT NULL,
            pagina TEXT NOT NULL,
            pagina_limpia TEXT NOT NULL,
            protocolo TEXT,
            codigo_estado INTEGER NOT NULL,
            es_error INTEGER NOT NULL DEFAULT 0,
            es_formulario INTEGER NOT NULL DEFAULT 0,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        )
    """)

    # Tabla para guardar el resumen técnico de cada página revisada por el minibot.
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS revision_minibot (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fecha_revision TEXT DEFAULT CURRENT_TIMESTAMP,
            url TEXT NOT NULL,
            titulo TEXT,
            codigo_estado INTEGER,
            correos TEXT,
            num_enlaces_internos INTEGER DEFAULT 0,
            num_enlaces_externos INTEGER DEFAULT 0,
            error TEXT
        )
    """)

    # Tabla para guardar los enlaces encontrados por el minibot.
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS enlaces_minibot (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fecha_revision TEXT DEFAULT CURRENT_TIMESTAMP,
            origen TEXT NOT NULL,
            destino TEXT NOT NULL,
            tipo TEXT NOT NULL,
            codigo_estado INTEGER,
            observacion TEXT
        )
    """)

    # Tabla nueva para guardar el historial de ejecuciones del análisis.
    # Esta tabla NO se borra en cada ejecución, porque sirve para comparar la evolución.
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

    conexion.commit()
    conexion.close()

    print("Base de datos creada correctamente:", DB_FILE)


if __name__ == "__main__":
    crear_bbdd()