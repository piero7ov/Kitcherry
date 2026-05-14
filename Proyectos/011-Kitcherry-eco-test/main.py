"""
Kitcherry EcoTest
-----------------
Mini proyecto para comparar el consumo aproximado de recursos en distintos
procesos digitales relacionados con Kitcherry.

Autor: Jose Piero Olivares Velásquez
Proyecto: Kitcherry - Herramientas para hostelería
"""

import csv
from pathlib import Path
from datetime import datetime

from config import POTENCIA_ESTIMADA, UMBRAL_BAJO_WH, UMBRAL_MEDIO_WH
from utilidades.medicion import medir_proceso

from pruebas.clasificacion_reglas import ejecutar_prueba as prueba_clasificacion_reglas
from pruebas.clasificacion_ia_local import ejecutar_prueba as prueba_clasificacion_ia_local
from pruebas.consulta_json import ejecutar_prueba as prueba_consulta_json
from pruebas.consulta_sqlite import ejecutar_prueba as prueba_consulta_sqlite

RUTA_RESULTADOS = Path("resultados")
RUTA_CSV = RUTA_RESULTADOS / "resultados.csv"
RUTA_INFORME = RUTA_RESULTADOS / "informe_resumen.md"


def clasificar_consumo(consumo_wh: float) -> str:
    """
    Clasifica el consumo estimado con una escala sencilla.
    """
    if consumo_wh <= UMBRAL_BAJO_WH:
        return "Bajo"
    elif consumo_wh <= UMBRAL_MEDIO_WH:
        return "Medio"
    return "Alto"


def generar_recomendacion(proceso: str, consumo: str) -> str:
    """
    Genera una recomendación breve según el proceso y el consumo.
    """
    if "IA" in proceso:
        return "Usar solo cuando aporte valor real; para casos simples conviene una solución más ligera."

    if consumo == "Bajo":
        return "Recomendado para tareas frecuentes por su bajo consumo estimado."

    return "Usar de forma controlada y optimizar si el volumen de datos aumenta."


def guardar_csv(resultados):
    RUTA_RESULTADOS.mkdir(exist_ok=True)

    campos = [
        "proceso",
        "tiempo_segundos",
        "potencia_estimada_w",
        "consumo_estimado_wh",
        "consumo_clasificado",
        "elementos_procesados",
        "detalle",
        "recomendacion"
    ]

    with open(RUTA_CSV, "w", encoding="utf-8", newline="") as archivo:
        escritor = csv.DictWriter(archivo, fieldnames=campos)
        escritor.writeheader()

        for resultado in resultados:
            escritor.writerow({campo: resultado.get(campo, "") for campo in campos})


def guardar_informe(resultados):
    RUTA_RESULTADOS.mkdir(exist_ok=True)

    fecha = datetime.now().strftime("%d/%m/%Y %H:%M")

    lineas = []
    lineas.append("# Informe resumen - Kitcherry EcoTest\n")
    lineas.append(f"Fecha de ejecución: {fecha}\n")
    lineas.append("## Objetivo\n")
    lineas.append(
        "El objetivo de esta prueba es comparar distintas formas de resolver procesos digitales "
        "dentro de Kitcherry y comprobar que no todas consumen los mismos recursos. "
        "La prueba permite justificar que la inteligencia artificial debe utilizarse de forma responsable, "
        "reservándola para tareas donde realmente aporte valor.\n"
    )

    lineas.append("## Resultados obtenidos\n")
    lineas.append("| Proceso | Tiempo (s) | Potencia estimada (W) | Consumo estimado (Wh) | Consumo | Recomendación |")
    lineas.append("|---|---:|---:|---:|---|---|")

    for resultado in resultados:
        lineas.append(
            f"| {resultado['proceso']} "
            f"| {resultado['tiempo_segundos']} "
            f"| {resultado['potencia_estimada_w']} "
            f"| {resultado['consumo_estimado_wh']} "
            f"| {resultado['consumo_clasificado']} "
            f"| {resultado['recomendacion']} |"
        )

    lineas.append("\n## Conclusión\n")
    lineas.append(
        "Los resultados muestran que las soluciones simples, como la clasificación por reglas "
        "o la consulta de datos estructurados, suelen ser más ligeras que una solución basada "
        "en inteligencia artificial local o en procesos más pesados. Por este motivo, en Kitcherry "
        "se plantea un uso responsable de la tecnología: aplicar IA cuando sea útil y mantener "
        "soluciones sencillas cuando sean suficientes. Esta decisión mejora el rendimiento, "
        "reduce carga en el servidor y contribuye a una implantación más sostenible.\n"
    )

    with open(RUTA_INFORME, "w", encoding="utf-8") as archivo:
        archivo.write("\n".join(lineas))


def mostrar_resultados(resultados):
    print("\nKITCHERRY ECOTEST - RESULTADOS")
    print("=" * 80)

    for resultado in resultados:
        print(f"\nProceso: {resultado['proceso']}")
        print(f"Tiempo: {resultado['tiempo_segundos']} segundos")
        print(f"Potencia estimada: {resultado['potencia_estimada_w']} W")
        print(f"Consumo estimado: {resultado['consumo_estimado_wh']} Wh")
        print(f"Consumo clasificado: {resultado['consumo_clasificado']}")
        print(f"Elementos procesados: {resultado['elementos_procesados']}")
        print(f"Recomendación: {resultado['recomendacion']}")

    print("\n" + "=" * 80)
    print(f"CSV generado en: {RUTA_CSV}")
    print(f"Informe generado en: {RUTA_INFORME}")


def main():
    pruebas = [
        (
            "Clasificación con reglas",
            prueba_clasificacion_reglas,
            POTENCIA_ESTIMADA["clasificacion_reglas"]
        ),
        (
            "Clasificación con IA local",
            prueba_clasificacion_ia_local,
            POTENCIA_ESTIMADA["clasificacion_ia_local"]
        ),
        (
            "Consulta de stock en JSON",
            prueba_consulta_json,
            POTENCIA_ESTIMADA["consulta_json"]
        ),
        (
            "Consulta de stock en SQLite",
            prueba_consulta_sqlite,
            POTENCIA_ESTIMADA["consulta_sqlite"]
        )
    ]

    resultados = []

    for nombre, funcion, potencia in pruebas:
        resultado = medir_proceso(nombre, funcion, potencia)
        resultado["consumo_clasificado"] = clasificar_consumo(resultado["consumo_estimado_wh"])
        resultado["recomendacion"] = generar_recomendacion(nombre, resultado["consumo_clasificado"])
        resultados.append(resultado)

    guardar_csv(resultados)
    guardar_informe(resultados)
    mostrar_resultados(resultados)


if __name__ == "__main__":
    main()