import os
import re
import json
from datetime import datetime

# ==========================================================
# KITCHERRY DOCS
# Objetivo:
# - Leer los TXT generados
# - Limpiar saltos raros, espacios dobles y caracteres innecesarios
# - Mantener una limpieza conservadora para no romper tablas
# - Guardar los textos limpios en txt_limpio/
# ==========================================================

TXT_ORIGINAL_FOLDER = "txt"
TXT_LIMPIO_FOLDER = "txt_limpio"
OUT_FOLDER = "out"

# Crear carpetas necesarias
os.makedirs(TXT_LIMPIO_FOLDER, exist_ok=True)
os.makedirs(OUT_FOLDER, exist_ok=True)


def limpiar_linea(linea):
    """
    Limpia una línea individual sin destruir demasiado su estructura.
    Esto es importante porque algunos documentos pueden tener tablas.
    """

    # Quitar espacios al principio y al final
    linea = linea.strip()

    # Cambiar tabulaciones por espacios
    linea = linea.replace("\t", " ")

    # Reducir muchos espacios seguidos a uno solo
    linea = re.sub(r" {2,}", " ", linea)

    # Normalizar algunos caracteres frecuentes
    linea = linea.replace("•", "-")
    linea = linea.replace("–", "-")
    linea = linea.replace("—", "-")
    linea = linea.replace("“", '"')
    linea = linea.replace("”", '"')
    linea = linea.replace("’", "'")

    return linea


def es_marcador_pagina(linea):
    """
    Detecta líneas como:
    ===== PÁGINA 1 =====
    """
    return bool(re.match(r"^=+\s*PÁGINA\s+\d+\s*=+$", linea, re.IGNORECASE))


def parece_titulo_o_categoria(linea):
    """
    Detecta líneas que probablemente son títulos o categorías.
    Ejemplos:
    ENTRANTES
    PRINCIPALES
    POSTRES
    TABLA DE ALÉRGENOS
    """

    if len(linea) < 3:
        return False

    # Si está casi todo en mayúsculas, puede ser título
    letras = re.sub(r"[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ]", "", linea)

    if letras and letras.upper() == letras and len(letras) >= 4:
        return True

    return False


def parece_linea_tabla_o_lista(linea):
    """
    Detecta líneas que conviene NO unir con otras porque pueden venir de tablas,
    listas de ingredientes, alérgenos o platos.
    """

    patrones = [
        r"^-",                       # líneas tipo lista
        r"\d+[,\.]\d{2}\s*€",         # precios tipo 8,50 €
        r"\bgluten\b",
        r"\bleche\b",
        r"\bhuevo\b",
        r"\bpescado\b",
        r"\bsoja\b",
        r"\bfrutos\b",
        r"\bsésamo\b",
        r"\bmostaza\b",
        r"\bsulfitos\b",
        r"\bcrustáceos\b",
        r"\bmoluscos\b",
        r"\bcacahuetes\b",
        r"\baltramuces\b",
        r"\bapio\b",
        r"\bingredientes\b",
        r"\balérgenos\b",
        r"\bcontiene\b",
        r"\bpuede contener\b",
    ]

    linea_min = linea.lower()

    for patron in patrones:
        if re.search(patron, linea_min, re.IGNORECASE):
            return True

    return False


def limpiar_texto_conservador(texto):
    """
    Limpieza conservadora:
    - Limpia línea a línea
    - Elimina líneas vacías excesivas
    - No intenta reconstruir tablas
    - No une agresivamente líneas, para no romper documentos de alérgenos
    """

    lineas_originales = texto.splitlines()
    lineas_limpias = []

    for linea in lineas_originales:
        linea_limpia = limpiar_linea(linea)

        # Guardamos líneas no vacías
        if linea_limpia != "":
            lineas_limpias.append(linea_limpia)

    resultado = []
    linea_anterior = ""

    for linea in lineas_limpias:

        # Mantener separación antes de páginas
        if es_marcador_pagina(linea):
            resultado.append("")
            resultado.append(linea)
            resultado.append("")
            linea_anterior = linea
            continue

        # Mantener separación antes de títulos/categorías
        if parece_titulo_o_categoria(linea):
            resultado.append("")
            resultado.append(linea)
            linea_anterior = linea
            continue

        # No unir líneas de tablas, listas o alérgenos
        if parece_linea_tabla_o_lista(linea):
            resultado.append(linea)
            linea_anterior = linea
            continue

        # En esta iteración evitamos unir líneas agresivamente.
        # Solo guardamos la línea limpia.
        resultado.append(linea)
        linea_anterior = linea

    texto_limpio = "\n".join(resultado)

    # Quitar más de 2 saltos de línea seguidos
    texto_limpio = re.sub(r"\n{3,}", "\n\n", texto_limpio)

    return texto_limpio.strip()


def analizar_texto(nombre_archivo, texto_original, texto_limpio):
    """
    Genera información básica para saber cuánto se ha limpiado cada archivo.
    """

    lineas_originales = texto_original.splitlines()
    lineas_limpias = texto_limpio.splitlines()

    return {
        "archivo": nombre_archivo,
        "caracteres_originales": len(texto_original),
        "caracteres_limpios": len(texto_limpio),
        "lineas_originales": len(lineas_originales),
        "lineas_limpias": len(lineas_limpias),
        "procesado_en": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    }


def main():
    if not os.path.isdir(TXT_ORIGINAL_FOLDER):
        print(f"ERROR: No existe la carpeta {TXT_ORIGINAL_FOLDER}/")
        print("Primero ejecuta 001-leer-documentos.py para generar los TXT.")
        return

    informe = []

    archivos_txt = [
        archivo for archivo in os.listdir(TXT_ORIGINAL_FOLDER)
        if archivo.lower().endswith(".txt")
    ]

    if not archivos_txt:
        print(f"No se encontraron archivos TXT en {TXT_ORIGINAL_FOLDER}/")
        return

    for archivo in archivos_txt:
        ruta_original = os.path.join(TXT_ORIGINAL_FOLDER, archivo)

        nombre_sin_extension = os.path.splitext(archivo)[0]
        nombre_limpio = nombre_sin_extension + "_limpio.txt"
        ruta_limpia = os.path.join(TXT_LIMPIO_FOLDER, nombre_limpio)

        try:
            with open(ruta_original, "r", encoding="utf-8") as f:
                texto_original = f.read()

            texto_limpio = limpiar_texto_conservador(texto_original)

            with open(ruta_limpia, "w", encoding="utf-8") as f:
                f.write(texto_limpio)

            datos_archivo = analizar_texto(
                archivo,
                texto_original,
                texto_limpio
            )

            informe.append(datos_archivo)

            print(f"OK -> {archivo} limpiado como {nombre_limpio}")

        except Exception as e:
            print(f"ERROR -> {archivo}: {e}")

    ruta_informe = os.path.join(OUT_FOLDER, "informe_limpieza.json")

    with open(ruta_informe, "w", encoding="utf-8") as f:
        json.dump(informe, f, ensure_ascii=False, indent=4)

    print("\nProceso finalizado.")
    print(f"Informe generado en: {ruta_informe}")


if __name__ == "__main__":
    main()