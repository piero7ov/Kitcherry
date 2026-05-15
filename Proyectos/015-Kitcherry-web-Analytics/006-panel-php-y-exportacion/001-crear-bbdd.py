# ==========================================================
# KITCHERRY WEB ANALYTICS
# Archivo: 001-crear-bbdd.py
# Crea la base de datos SQLite del sistema de seguimiento
# ==========================================================

import sqlite3
from config import DB_FILE


def asegurar_columna(cursor, tabla, columna, definicion):
    """
    Añade una columna a una tabla si todavía no existe.

    Esto permite actualizar la base de datos sin tener que borrar
    el archivo kitcherry_analytics.sqlite anterior.
    """
    cursor.execute(f"PRAGMA table_info({tabla})")
    columnas_actuales = [fila[1] for fila in cursor.fetchall()]

    if columna not in columnas_actuales:
        cursor.execute(f"ALTER TABLE {tabla} ADD COLUMN {columna} {definicion}")


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

    # Tabla para guardar el historial de ejecuciones.
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

    # Nuevas columnas de auditoría técnica para el minibot.
    asegurar_columna(cursor, "revision_minibot", "meta_description", "TEXT")
    asegurar_columna(cursor, "revision_minibot", "h1", "TEXT")
    asegurar_columna(cursor, "revision_minibot", "html_lang", "TEXT")
    asegurar_columna(cursor, "revision_minibot", "tiene_title", "INTEGER NOT NULL DEFAULT 0")
    asegurar_columna(cursor, "revision_minibot", "tiene_meta_description", "INTEGER NOT NULL DEFAULT 0")
    asegurar_columna(cursor, "revision_minibot", "tiene_h1", "INTEGER NOT NULL DEFAULT 0")
    asegurar_columna(cursor, "revision_minibot", "tiene_lang", "INTEGER NOT NULL DEFAULT 0")
    asegurar_columna(cursor, "revision_minibot", "tiempo_respuesta", "REAL")
    asegurar_columna(cursor, "revision_minibot", "enlaces_rotos", "INTEGER NOT NULL DEFAULT 0")
    asegurar_columna(cursor, "revision_minibot", "estado_tecnico", "TEXT DEFAULT 'Sin revisar'")

    # Nueva columna para marcar enlaces rotos.
    asegurar_columna(cursor, "enlaces_minibot", "es_roto", "INTEGER NOT NULL DEFAULT 0")

    conexion.commit()
    conexion.close()

    print("Base de datos creada/actualizada correctamente:", DB_FILE)


if __name__ == "__main__":
    crear_bbdd()