import os
import re
import json
from datetime import datetime

# ==========================================================
# KITCHERRY DOCS
# Objetivo:
# - Leer los TXT limpios generados
# - Detectar platos que tengan una línea posterior de alérgenos declarados
# - Generar un JSON inicial con platos y alérgenos
# ==========================================================

TXT_LIMPIO_FOLDER = "txt_limpio"
OUT_FOLDER = "out"
OUT_JSON = os.path.join(OUT_FOLDER, "platos_detectados.json")

os.makedirs(OUT_FOLDER, exist_ok=True)


def limpiar_nombre_plato(nombre):
    """
    Limpia el nombre del plato eliminando espacios raros
    y puntos innecesarios.
    """

    nombre = nombre.strip()
    nombre = re.sub(r"\s+", " ", nombre)
    nombre = nombre.strip(" .:-")

    return nombre


def limpiar_alergeno(alergeno):
    """
    Limpia cada alérgeno detectado.
    """

    alergeno = alergeno.strip()
    alergeno = alergeno.strip(" .:-")
    alergeno = re.sub(r"\s+", " ", alergeno)

    return alergeno


def extraer_alergenos_desde_linea(linea):
    """
    Recibe una línea tipo:
    Alérgenos declarados: Gluten, Huevos, Leche, Soja.

    Devuelve:
    ["Gluten", "Huevos", "Leche", "Soja"]
    """

    linea = linea.strip()

    # Quitar el inicio de la frase
    linea = re.sub(
        r"^al[eé]rgenos\s+declarados\s*:\s*",
        "",
        linea,
        flags=re.IGNORECASE
    )

    linea = linea.strip()

    # Caso especial: sin alérgenos declarados
    if "sin alérgenos declarados" in linea.lower():
        return []

    # Separar por comas
    partes = linea.split(",")

    alergenos = []

    for parte in partes:
        alergeno = limpiar_alergeno(parte)

        if alergeno:
            alergenos.append(alergeno)

    return alergenos


def es_linea_alergenos(linea):
    """
    Detecta si una línea contiene alérgenos declarados.
    """

    return bool(
        re.search(
            r"^al[eé]rgenos\s+declarados\s*:",
            linea.strip(),
            re.IGNORECASE
        )
    )


def linea_no_valida_como_plato(linea):
    """
    Evita que se tomen como platos líneas que son títulos,
    encabezados o texto explicativo.
    """

    linea_min = linea.lower().strip()

    palabras_bloqueadas = [
        "kitcherry",
        "documento de prueba",
        "página",
        "tabla de alérgenos",
        "matriz simplificada",
        "documento ficticio",
        "tabla ficticia",
        "los datos",
        "son de prueba",
        "plato",
        "gluten",
        "crustáceos",
        "huevos",
        "pescado",
        "cacahuetes",
        "soja",
        "leche",
        "frutos de cáscara",
        "apio",
        "mostaza",
        "sésamo",
        "sulfitos",
        "altramuces",
        "moluscos",
        "x"
    ]

    if linea_min == "":
        return True

    if len(linea_min) < 3:
        return True

    if linea_min in palabras_bloqueadas:
        return True

    for palabra in palabras_bloqueadas:
        if linea_min.startswith(palabra):
            return True

    if linea_min.startswith("====="):
        return True

    return False


def buscar_plato_anterior(lineas, indice_actual):
    """
    Busca hacia arriba desde la línea de alérgenos para encontrar
    el nombre del plato más cercano.
    """

    for i in range(indice_actual - 1, -1, -1):
        posible_plato = limpiar_nombre_plato(lineas[i])

        if not linea_no_valida_como_plato(posible_plato):
            return posible_plato

    return ""


def detectar_platos_en_texto(texto, archivo_origen):
    """
    Detecta platos usando la estructura:
    Nombre del plato
    Alérgenos declarados: ...
    """

    lineas = texto.splitlines()
    platos = []

    for i, linea in enumerate(lineas):

        if es_linea_alergenos(linea):
            nombre_plato = buscar_plato_anterior(lineas, i)
            alergenos = extraer_alergenos_desde_linea(linea)

            if nombre_plato:
                plato = {
                    "nombre": nombre_plato,
                    "alergenos_declarados": alergenos,
                    "estado_revision": "pendiente",
                    "fuente": archivo_origen
                }

                platos.append(plato)

    return platos


def eliminar_duplicados(platos):
    """
    Evita platos repetidos si aparecen en varios documentos.
    La clave será nombre + fuente.
    """

    vistos = set()
    resultado = []

    for plato in platos:
        clave = (
            plato["nombre"].lower(),
            plato["fuente"].lower()
        )

        if clave not in vistos:
            vistos.add(clave)
            resultado.append(plato)

    return resultado


def main():
    if not os.path.isdir(TXT_LIMPIO_FOLDER):
        print(f"ERROR: No existe la carpeta {TXT_LIMPIO_FOLDER}/")
        print("Primero ejecuta 002-limpiar-texto.py")
        return

    archivos_txt = sorted([
        archivo for archivo in os.listdir(TXT_LIMPIO_FOLDER)
        if archivo.lower().endswith(".txt")
    ])

    if not archivos_txt:
        print(f"No se encontraron archivos TXT en {TXT_LIMPIO_FOLDER}/")
        return

    todos_los_platos = []

    for archivo in archivos_txt:
        ruta = os.path.join(TXT_LIMPIO_FOLDER, archivo)

        try:
            with open(ruta, "r", encoding="utf-8", errors="ignore") as f:
                texto = f.read()

            platos = detectar_platos_en_texto(texto, archivo)

            todos_los_platos.extend(platos)

            print(f"OK -> {archivo}: {len(platos)} platos detectados")

        except Exception as e:
            print(f"ERROR -> {archivo}: {e}")

    todos_los_platos = eliminar_duplicados(todos_los_platos)

    salida = {
        "proyecto": "Kitcherry Docs",
        "iteracion": "003-detectar-platos-alergenos",
        "descripcion": "Detección inicial de platos y alérgenos declarados desde documentos TXT limpios.",
        "fecha_generacion": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "total_platos": len(todos_los_platos),
        "platos": todos_los_platos
    }

    with open(OUT_JSON, "w", encoding="utf-8") as f:
        json.dump(salida, f, ensure_ascii=False, indent=4)

    print("\nProceso finalizado.")
    print(f"Total de platos detectados: {len(todos_los_platos)}")
    print(f"JSON generado en: {OUT_JSON}")


if __name__ == "__main__":
    main()