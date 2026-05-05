import os
import sys
import json
import time
import subprocess
from datetime import datetime

# ==========================================================
# Objetivo:
# - Ejecutar de forma ordenada todo el flujo de Kitcherry Docs
# - Registrar qué pasos se han ejecutado correctamente
# - Generar un informe final del proceso integral
# ==========================================================

# ==========================================================
# CONFIGURACIÓN
# ==========================================================

OUT_FOLDER = "out"
SUMMARY_FOLDER = "summaries"

INFORME_PROCESO = os.path.join(OUT_FOLDER, "proceso_integral_kitcherry.json")

# Poner en False si quieres ejecutar solo la parte sin IA/Ollama
EJECUTAR_OLLAMA = True

# Si está en True, el proceso se detiene cuando falla un paso de Ollama.
# Si está en False, registra el error pero permite continuar.
DETENER_SI_FALLA_OLLAMA = False

# Lista de pasos del proceso
PASOS = [
    {
        "numero": "001",
        "nombre": "Lectura de documentos PDF",
        "archivo": "001-leer-documentos.py",
        "tipo": "obligatorio",
        "descripcion": "Convierte los documentos PDF de la carpeta pdf/ en archivos TXT dentro de txt/."
    },
    {
        "numero": "002",
        "nombre": "Limpieza de texto",
        "archivo": "002-limpiar-texto.py",
        "tipo": "obligatorio",
        "descripcion": "Limpia los TXT originales y genera versiones normalizadas en txt_limpio/."
    },
    {
        "numero": "003",
        "nombre": "Detección de platos y alérgenos",
        "archivo": "003-detectar-platos-alergenos.py",
        "tipo": "obligatorio",
        "descripcion": "Detecta platos y alérgenos declarados para generar out/platos_detectados.json."
    },
    {
        "numero": "004",
        "nombre": "Generación de carta estructurada",
        "archivo": "004-generar-carta-estructurada.py",
        "tipo": "obligatorio",
        "descripcion": "Une carta, fichas técnicas y alérgenos para generar out/carta_kitcherry.json."
    },
    {
        "numero": "005",
        "nombre": "Resumen de documentos con Ollama",
        "archivo": "005-resumir-documentos-ollama.py",
        "tipo": "ollama",
        "descripcion": "Genera resúmenes automáticos de los TXT limpios en la carpeta summaries/."
    },
    {
        "numero": "006",
        "nombre": "Análisis documental con Ollama",
        "archivo": "006-analisis-ollama-carta.py",
        "tipo": "ollama",
        "descripcion": "Genera out/analisis_ollama_carta.json con análisis inteligente de los documentos."
    }
]

# Archivos esperados para comprobar que el proceso generó resultados
SALIDAS_ESPERADAS = [
    {
        "paso": "001",
        "ruta": "txt",
        "tipo": "carpeta"
    },
    {
        "paso": "002",
        "ruta": "txt_limpio",
        "tipo": "carpeta"
    },
    {
        "paso": "003",
        "ruta": os.path.join("out", "platos_detectados.json"),
        "tipo": "archivo"
    },
    {
        "paso": "004",
        "ruta": os.path.join("out", "carta_kitcherry.json"),
        "tipo": "archivo"
    },
    {
        "paso": "005",
        "ruta": "summaries",
        "tipo": "carpeta"
    },
    {
        "paso": "006",
        "ruta": os.path.join("out", "analisis_ollama_carta.json"),
        "tipo": "archivo"
    }
]


# ==========================================================
# FUNCIONES AUXILIARES
# ==========================================================

def crear_carpetas_base():
    """
    Crea carpetas necesarias para evitar errores de escritura.
    """

    os.makedirs(OUT_FOLDER, exist_ok=True)
    os.makedirs(SUMMARY_FOLDER, exist_ok=True)


def ahora():
    """
    Devuelve fecha y hora actual en formato legible.
    """

    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def existe_archivo_script(nombre_archivo):
    """
    Comprueba que el script exista antes de ejecutarlo.
    """

    return os.path.isfile(nombre_archivo)


def ejecutar_script(nombre_archivo):
    """
    Ejecuta un script Python usando el mismo intérprete con el que se lanza este archivo.
    """

    inicio = time.time()

    resultado = subprocess.run(
        [sys.executable, nombre_archivo],
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="replace"
    )

    fin = time.time()
    duracion = round(fin - inicio, 2)

    return {
        "returncode": resultado.returncode,
        "stdout": resultado.stdout,
        "stderr": resultado.stderr,
        "duracion_segundos": duracion
    }


def comprobar_salida(ruta, tipo):
    """
    Comprueba si una salida esperada existe.
    """

    if tipo == "archivo":
        return os.path.isfile(ruta)

    if tipo == "carpeta":
        return os.path.isdir(ruta)

    return False


def contar_archivos_txt(carpeta):
    """
    Cuenta archivos TXT dentro de una carpeta.
    """

    if not os.path.isdir(carpeta):
        return 0

    return len([
        archivo for archivo in os.listdir(carpeta)
        if archivo.lower().endswith(".txt")
    ])


def contar_archivos_summary(carpeta):
    """
    Cuenta archivos .summary.txt dentro de summaries/.
    """

    if not os.path.isdir(carpeta):
        return 0

    return len([
        archivo for archivo in os.listdir(carpeta)
        if archivo.lower().endswith(".summary.txt")
    ])


def cargar_json_seguro(ruta):
    """
    Carga un JSON si existe. Si hay error, devuelve None.
    """

    if not os.path.isfile(ruta):
        return None

    try:
        with open(ruta, "r", encoding="utf-8") as archivo:
            return json.load(archivo)
    except Exception:
        return None


def generar_resumen_salidas():
    """
    Genera un resumen de las principales salidas del proceso.
    """

    carta_json = cargar_json_seguro(os.path.join(OUT_FOLDER, "carta_kitcherry.json"))
    analisis_json = cargar_json_seguro(os.path.join(OUT_FOLDER, "analisis_ollama_carta.json"))

    total_platos = 0
    total_documentos_analizados = 0

    if carta_json:
        total_platos = carta_json.get("total_platos", 0)

    if analisis_json:
        total_documentos_analizados = analisis_json.get("documentos_ok", 0)

    resumen = {
        "txt_generados": contar_archivos_txt("txt"),
        "txt_limpios_generados": contar_archivos_txt("txt_limpio"),
        "resumenes_generados": contar_archivos_summary("summaries"),
        "total_platos_carta": total_platos,
        "documentos_analizados_ollama": total_documentos_analizados
    }

    return resumen


def comprobar_salidas_esperadas():
    """
    Comprueba las salidas principales del proceso.
    """

    comprobaciones = []

    for salida in SALIDAS_ESPERADAS:
        existe = comprobar_salida(salida["ruta"], salida["tipo"])

        comprobaciones.append({
            "paso": salida["paso"],
            "ruta": salida["ruta"],
            "tipo": salida["tipo"],
            "existe": existe
        })

    return comprobaciones


def guardar_informe(informe):
    """
    Guarda el informe final del proceso integral.
    """

    with open(INFORME_PROCESO, "w", encoding="utf-8") as archivo:
        json.dump(informe, archivo, ensure_ascii=False, indent=4)


def imprimir_bloque(titulo):
    """
    Imprime un bloque visual por consola.
    """

    print("")
    print("=" * 70)
    print(titulo)
    print("=" * 70)


# ==========================================================
# PROCESO PRINCIPAL
# ==========================================================

def main():
    crear_carpetas_base()

    imprimir_bloque("KITCHERRY DOCS - PROCESO INTEGRAL")

    print("Inicio del proceso:", ahora())
    print("Python usado:", sys.executable)
    print("Ejecutar pasos con Ollama:", "Sí" if EJECUTAR_OLLAMA else "No")

    informe = {
        "proyecto": "Kitcherry Docs",
        "iteracion": "007-proceso-integral-kitcherry",
        "descripcion": "Ejecución completa del flujo PDF, TXT, limpieza, detección, carta estructurada, resúmenes y análisis documental.",
        "inicio": ahora(),
        "fin": "",
        "ejecutar_ollama": EJECUTAR_OLLAMA,
        "detener_si_falla_ollama": DETENER_SI_FALLA_OLLAMA,
        "estado_general": "en_proceso",
        "pasos": [],
        "salidas": [],
        "resumen_salidas": {}
    }

    hubo_error = False

    for paso in PASOS:
        numero = paso["numero"]
        nombre = paso["nombre"]
        archivo = paso["archivo"]
        tipo = paso["tipo"]

        if tipo == "ollama" and not EJECUTAR_OLLAMA:
            registro = {
                "numero": numero,
                "nombre": nombre,
                "archivo": archivo,
                "tipo": tipo,
                "estado": "omitido",
                "motivo": "EJECUTAR_OLLAMA está configurado como False.",
                "duracion_segundos": 0,
                "stdout": "",
                "stderr": ""
            }

            informe["pasos"].append(registro)

            print(f"[{numero}] OMITIDO -> {nombre}")
            continue

        imprimir_bloque(f"[{numero}] {nombre}")

        if not existe_archivo_script(archivo):
            hubo_error = True

            registro = {
                "numero": numero,
                "nombre": nombre,
                "archivo": archivo,
                "tipo": tipo,
                "estado": "error",
                "motivo": f"No se encontró el archivo {archivo}.",
                "duracion_segundos": 0,
                "stdout": "",
                "stderr": ""
            }

            informe["pasos"].append(registro)

            print(f"ERROR: No se encontró el archivo {archivo}")

            if tipo == "obligatorio":
                print("El proceso se detiene porque este paso es obligatorio.")
                break

            if tipo == "ollama" and DETENER_SI_FALLA_OLLAMA:
                print("El proceso se detiene porque falló un paso de Ollama.")
                break

            continue

        print(f"Ejecutando: {archivo}")

        resultado = ejecutar_script(archivo)

        estado = "ok" if resultado["returncode"] == 0 else "error"

        if estado == "error":
            hubo_error = True

        registro = {
            "numero": numero,
            "nombre": nombre,
            "archivo": archivo,
            "tipo": tipo,
            "estado": estado,
            "returncode": resultado["returncode"],
            "duracion_segundos": resultado["duracion_segundos"],
            "stdout": resultado["stdout"],
            "stderr": resultado["stderr"]
        }

        informe["pasos"].append(registro)

        print(resultado["stdout"])

        if resultado["stderr"]:
            print("STDERR:")
            print(resultado["stderr"])

        print(f"Estado del paso {numero}: {estado.upper()}")
        print(f"Duración: {resultado['duracion_segundos']} segundos")

        if estado == "error":
            if tipo == "obligatorio":
                print("El proceso se detiene porque este paso es obligatorio.")
                break

            if tipo == "ollama" and DETENER_SI_FALLA_OLLAMA:
                print("El proceso se detiene porque falló un paso de Ollama.")
                break

    informe["fin"] = ahora()
    informe["salidas"] = comprobar_salidas_esperadas()
    informe["resumen_salidas"] = generar_resumen_salidas()

    # Estado general
    errores_obligatorios = [
        paso for paso in informe["pasos"]
        if paso.get("tipo") == "obligatorio" and paso.get("estado") == "error"
    ]

    errores_ollama = [
        paso for paso in informe["pasos"]
        if paso.get("tipo") == "ollama" and paso.get("estado") == "error"
    ]

    if errores_obligatorios:
        informe["estado_general"] = "error"
    elif errores_ollama:
        informe["estado_general"] = "completado_con_avisos"
    elif hubo_error:
        informe["estado_general"] = "completado_con_avisos"
    else:
        informe["estado_general"] = "completado"

    guardar_informe(informe)

    imprimir_bloque("RESUMEN FINAL")

    print("Estado general:", informe["estado_general"])
    print("TXT generados:", informe["resumen_salidas"]["txt_generados"])
    print("TXT limpios generados:", informe["resumen_salidas"]["txt_limpios_generados"])
    print("Platos en carta estructurada:", informe["resumen_salidas"]["total_platos_carta"])
    print("Resúmenes generados:", informe["resumen_salidas"]["resumenes_generados"])
    print("Documentos analizados con Ollama:", informe["resumen_salidas"]["documentos_analizados_ollama"])
    print("Informe guardado en:", INFORME_PROCESO)

    print("")
    print("Comprobación de salidas:")

    for salida in informe["salidas"]:
        estado_salida = "OK" if salida["existe"] else "NO ENCONTRADO"
        print(f"- {salida['ruta']} -> {estado_salida}")


if __name__ == "__main__":
    main()