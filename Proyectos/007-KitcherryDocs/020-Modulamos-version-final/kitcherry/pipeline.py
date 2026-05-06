from __future__ import annotations

import json
import sys
from pathlib import Path
from typing import Any, Dict, List

from .config import (
    BASE_DIR, PDF_DIR, TXT_DIR, TXT_LIMPIO_DIR, OUT_DIR, SUMMARIES_DIR,
    EJECUTAR_OLLAMA,
)
from .utils import (
    asegurar_carpetas, cargar_json, guardar_json, guardar_texto, leer_texto,
    ahora_legible, medir_tiempo, imprimir_bloque, imprimir_ok,
    imprimir_omitido, imprimir_error, cargar_configuracion,
)
from .extraccion import (
    extraer_texto_pdf, limpiar_texto_documento, buscar_txt_limpio_por_tipo,
    extraer_items_carta, detectar_alergenos_desde_tabla, extraer_fichas_tecnicas,
    extraer_ingredientes_desde_descripcion, detectar_alergenos_plato,
    obtener_lineas_limpias,
)
from .ollama import (
    llamar_ollama, resumen_sin_ollama, analisis_sin_ollama, extraer_json_desde_respuesta,
)

# ==========================================================
# PASO 001 - LECTURA DE PDF
# ==========================================================

@medir_tiempo
def paso_001_leer_pdfs() -> Dict[str, Any]:
    imprimir_bloque("[001] Lectura de documentos PDF")

    pdfs = sorted(PDF_DIR.glob("*.pdf"))
    generados = []

    if not pdfs:
        imprimir_error("No se encontraron PDFs en la carpeta pdf.")
        return {"estado": "error", "generados": generados, "mensaje": "No se encontraron PDFs."}

    for pdf in pdfs:
        try:
            texto = extraer_texto_pdf(pdf)
            salida = TXT_DIR / f"{pdf.stem}.txt"
            guardar_texto(salida, texto)
            generados.append(str(salida.relative_to(BASE_DIR)))
            imprimir_ok(f"{pdf.name} convertido a {salida.name}")
        except Exception as error:
            imprimir_error(f"{pdf.name}: {error}")

    estado = "ok" if generados else "error"
    return {"estado": estado, "generados": generados, "total": len(generados)}


# ==========================================================
# PASO 002 - LIMPIEZA
# ==========================================================

@medir_tiempo
def paso_002_limpiar_textos() -> Dict[str, Any]:
    imprimir_bloque("[002] Limpieza de texto")

    txts = sorted(TXT_DIR.glob("*.txt"))
    informe: Dict[str, Any] = {"fecha": ahora_legible(), "documentos": []}

    if not txts:
        imprimir_error("No hay TXT generados para limpiar.")
        return {"estado": "error", "generados": []}

    generados = []

    for txt in txts:
        texto = leer_texto(txt)
        texto_limpio = limpiar_texto_documento(texto)

        salida = TXT_LIMPIO_DIR / f"{txt.stem}_limpio.txt"
        guardar_texto(salida, texto_limpio)
        generados.append(str(salida.relative_to(BASE_DIR)))

        informe["documentos"].append({
            "archivo_origen": txt.name,
            "archivo_limpio": salida.name,
            "caracteres_originales": len(texto),
            "caracteres_limpios": len(texto_limpio),
        })
        imprimir_ok(f"{txt.name} limpiado como {salida.name}")

    guardar_json(OUT_DIR / "informe_limpieza.json", informe)

    print()
    print("Proceso finalizado.")
    print("Informe generado en: out\\informe_limpieza.json")

    return {"estado": "ok", "generados": generados, "total": len(generados)}


# ==========================================================
# PASO 003 - DETECCIÓN OFICIAL DE PLATOS
# ==========================================================

@medir_tiempo
def paso_003_detectar_platos() -> Dict[str, Any]:
    imprimir_bloque("[003] Detección de platos y alérgenos")

    carta_txt = buscar_txt_limpio_por_tipo("carta")
    fichas_txt = buscar_txt_limpio_por_tipo("fichas")
    tabla_txt = buscar_txt_limpio_por_tipo("tabla")

    if not carta_txt:
        imprimir_error("No se encontró un TXT limpio de carta.")
        return {"estado": "error", "platos": []}

    if fichas_txt:
        imprimir_omitido(f"{fichas_txt.name}: no se usa como fuente principal de detección oficial")

    if tabla_txt:
        imprimir_omitido(f"{tabla_txt.name}: se usará como apoyo de alérgenos, no como fuente oficial de platos")

    texto_carta = leer_texto(carta_txt)
    platos_carta = extraer_items_carta(texto_carta)

    platos_detectados = []
    for indice, plato in enumerate(platos_carta, start=1):
        platos_detectados.append({
            "id": indice,
            "nombre": plato.get("nombre", ""),
            "categoria": plato.get("categoria", "Sin categoría"),
            "precio": plato.get("precio", ""),
            "descripcion": plato.get("descripcion", ""),
            "fuente": "carta",
        })

    salida = OUT_DIR / "platos_detectados.json"
    guardar_json(salida, {
        "fecha": ahora_legible(),
        "fuente_oficial": "carta",
        "total": len(platos_detectados),
        "platos": platos_detectados,
    })

    imprimir_ok(f"{carta_txt.name}: {len(platos_detectados)} platos detectados como fuente oficial")

    print()
    print("Proceso finalizado.")
    print(f"Total de platos detectados: {len(platos_detectados)}")
    print("JSON generado en: out\\platos_detectados.json")

    return {"estado": "ok", "platos": platos_detectados, "total": len(platos_detectados)}


# ==========================================================
# PASO 004 - CARTA ESTRUCTURADA
# ==========================================================

def crear_revisiones_base_si_no_existe(config: Dict[str, Any]) -> None:
    ruta = OUT_DIR / "revisiones_platos.json"
    if ruta.exists():
        return
    guardar_json(ruta, {
        "proyecto": "Kitcherry Docs",
        "iteracion": config["iteracion"],
        "descripcion": "Archivo independiente para guardar estados de revisión de platos sin modificar la carta generada automáticamente.",
        "ultima_actualizacion": "",
        "total_revisiones": 0,
        "revisiones": {},
    })


@medir_tiempo
def paso_004_generar_carta_estructurada(config: Dict[str, Any]) -> Dict[str, Any]:
    imprimir_bloque("[004] Generación de carta estructurada")

    datos_detectados = cargar_json(OUT_DIR / "platos_detectados.json", {})
    platos_detectados = datos_detectados.get("platos", [])

    if not isinstance(platos_detectados, list) or not platos_detectados:
        imprimir_error("No hay platos detectados para estructurar.")
        return {"estado": "error", "total": 0}

    tabla_txt = buscar_txt_limpio_por_tipo("tabla")
    fichas_txt = buscar_txt_limpio_por_tipo("fichas")

    texto_tabla = leer_texto(tabla_txt) if tabla_txt else ""
    texto_fichas = leer_texto(fichas_txt) if fichas_txt else ""

    alergenos_por_tabla = detectar_alergenos_desde_tabla(texto_tabla, platos_detectados)
    fichas_por_plato = extraer_fichas_tecnicas(texto_fichas, platos_detectados)

    platos_estructurados = []

    for indice, plato in enumerate(platos_detectados, start=1):
        nombre = str(plato.get("nombre", "")).strip()
        descripcion = str(plato.get("descripcion", "")).strip()
        ficha = fichas_por_plato.get(nombre, {})

        alergenos_tabla = alergenos_por_tabla.get(nombre, [])
        alergenos_finales = detectar_alergenos_plato(plato, alergenos_tabla, ficha)
        ingredientes = extraer_ingredientes_desde_descripcion(descripcion)

        fuentes = ["carta"]
        if texto_tabla:
            fuentes.append("tabla_alergenos")
        if texto_fichas:
            fuentes.append("fichas_tecnicas")

        platos_estructurados.append({
            "id": indice,
            "nombre": nombre,
            "categoria": plato.get("categoria", "Sin categoría"),
            "precio": plato.get("precio", ""),
            "descripcion": descripcion,
            "ingredientes_detectados": ingredientes,
            "alergenos_declarados": alergenos_finales,
            "estado_revision": "pendiente",
            "ficha_tecnica": ficha,
            "fuentes": fuentes,
        })

    carta = {
        "proyecto": "Kitcherry Docs",
        "iteracion": config["iteracion"],
        "negocio": config["negocio"],
        "fecha_generacion": ahora_legible(),
        "fuente_oficial_platos": "carta",
        "fuente_apoyo_alergenos": "tabla_alergenos + inferencia por ingredientes",
        "total_platos": len(platos_estructurados),
        "platos": platos_estructurados,
    }

    guardar_json(OUT_DIR / "carta_kitcherry.json", carta)
    crear_revisiones_base_si_no_existe(config)

    print()
    print("Proceso finalizado.")
    print("JSON generado en: out\\carta_kitcherry.json")
    print(f"Total de platos estructurados: {len(platos_estructurados)}")

    for plato in platos_estructurados:
        precio = plato["precio"] if plato["precio"] else "Sin precio"
        categoria = plato["categoria"] if plato["categoria"] else "Sin categoría"
        total_alergenos = len(plato["alergenos_declarados"])
        print(f"- {plato['nombre']} | {categoria} | {precio} | {total_alergenos} alérgenos")

    return {"estado": "ok", "total": len(platos_estructurados)}


# ==========================================================
# PASO 005 - RESUMEN DE DOCUMENTOS
# ==========================================================

@medir_tiempo
def paso_005_resumir_documentos(config: Dict[str, Any]) -> Dict[str, Any]:
    imprimir_bloque("[005] Resumen de documentos")

    print("Iniciando resumen de documentos...")
    print(f"Modelo: {config['modelo_ollama']}")
    print("Carpeta de entrada: txt_limpio")
    print("Carpeta de salida: summaries")
    print(f"Ollama activo: {'Sí' if EJECUTAR_OLLAMA else 'No'}")
    print()

    txts = sorted(TXT_LIMPIO_DIR.glob("*.txt"))
    registros_jsonl = []
    procesados = 0
    errores = 0

    for indice, txt in enumerate(txts, start=1):
        texto = leer_texto(txt)
        resumen = ""

        if EJECUTAR_OLLAMA:
            prompt = (
                "Resume este documento en español en un único párrafo breve. "
                "El resumen debe explicar para qué sirve dentro de un sistema de carta, platos y alérgenos.\n\n"
                f"Archivo: {txt.name}\n\n"
                f"Contenido:\n{texto[:7000]}"
            )
            resumen = llamar_ollama(prompt, config["modelo_ollama"])

        if not resumen:
            resumen = resumen_sin_ollama(txt.name, texto)

        salida = SUMMARIES_DIR / f"{txt.stem}.summary.txt"
        guardar_texto(salida, resumen)

        registros_jsonl.append({"archivo": txt.name, "summary": resumen})
        procesados += 1

        print(f"[{indice}/{len(txts)}] OK -> {txt.name}")
        print(f"Resumen guardado en: {salida.relative_to(BASE_DIR)}")

    jsonl_path = SUMMARIES_DIR / "summaries.jsonl"
    with jsonl_path.open("w", encoding="utf-8") as archivo:
        for registro in registros_jsonl:
            archivo.write(json.dumps(registro, ensure_ascii=False) + "\n")

    print()
    print("Proceso finalizado.")
    print(f"Documentos procesados correctamente: {procesados}")
    print(f"Documentos con error: {errores}")
    print("JSONL generado en: summaries\\summaries.jsonl")

    return {"estado": "ok", "procesados": procesados, "errores": errores}


# ==========================================================
# PASO 006 - ANÁLISIS DOCUMENTAL
# ==========================================================

@medir_tiempo
def paso_006_analisis_documental(config: Dict[str, Any]) -> Dict[str, Any]:
    imprimir_bloque("[006] Análisis documental")

    print("Iniciando análisis documental...")
    print(f"Modelo: {config['modelo_ollama']}")
    print("Carpeta de entrada: txt_limpio")
    print("Salida JSON: out\\analisis_ollama_carta.json")
    print(f"Ollama activo: {'Sí' if EJECUTAR_OLLAMA else 'No'}")
    print()

    txts = sorted(TXT_LIMPIO_DIR.glob("*.txt"))
    resultados = []
    jsonl_registros = []
    procesados = 0
    errores = 0

    for indice, txt in enumerate(txts, start=1):
        texto = leer_texto(txt)
        analisis = None

        if EJECUTAR_OLLAMA:
            prompt = f"""
Analiza el siguiente documento de un restaurante para un sistema llamado Kitcherry Docs.

Devuelve SOLO un JSON válido con esta estructura:
{{
  "tipo_documento": "carta | fichas_tecnicas | tabla_alergenos | otro",
  "nivel_revision_recomendado": "bajo | medio | alto",
  "resumen_utilidad": "resumen breve",
  "uso_en_kitcherry": "cómo se usa dentro del sistema",
  "platos_mencionados": [],
  "alergenos_mencionados": [],
  "ingredientes_relevantes": [],
  "advertencias": []
}}

Archivo: {txt.name}

Contenido:
{texto[:7000]}
"""
            respuesta = llamar_ollama(prompt, config["modelo_ollama"])
            analisis = extraer_json_desde_respuesta(respuesta)

        if not analisis:
            analisis = analisis_sin_ollama(txt.name, texto)

        tipo = analisis.get("tipo_documento", "otro")
        nivel = analisis.get("nivel_revision_recomendado", "alto")

        resultados.append({"archivo": txt.name, "analisis_ia": analisis})
        jsonl_registros.append({"archivo": txt.name, "analisis": analisis})
        procesados += 1

        print(f"[{indice}/{len(txts)}] OK -> {txt.name}")
        print(f"Tipo IA: {tipo}")
        print(f"Revisión: {nivel}")
        print()

    salida_json = OUT_DIR / "analisis_ollama_carta.json"
    salida_jsonl = OUT_DIR / "analisis_ollama_carta.jsonl"

    guardar_json(salida_json, {
        "fecha": ahora_legible(),
        "modelo": config["modelo_ollama"],
        "ollama_activo": EJECUTAR_OLLAMA,
        "total_documentos": len(resultados),
        "resultados": resultados,
    })

    with salida_jsonl.open("w", encoding="utf-8") as archivo:
        for registro in jsonl_registros:
            archivo.write(json.dumps(registro, ensure_ascii=False) + "\n")

    print("Proceso finalizado.")
    print(f"Documentos procesados correctamente: {procesados}")
    print(f"Documentos con error: {errores}")
    print("JSON generado en: out\\analisis_ollama_carta.json")
    print("JSONL generado en: out\\analisis_ollama_carta.jsonl")

    return {"estado": "ok", "procesados": procesados, "errores": errores}


# ==========================================================
# PROCESO INTEGRAL
# ==========================================================

def existe_ruta_relativa(ruta: str) -> bool:
    return (BASE_DIR / ruta).exists()


def contar_archivos(carpeta: Path, patron: str) -> int:
    if not carpeta.exists():
        return 0
    return len(list(carpeta.glob(patron)))


def registrar_paso(
    numero: str, nombre: str, estado: str, duracion: float, archivo: str = "",
) -> Dict[str, Any]:
    return {
        "numero": numero,
        "nombre": nombre,
        "estado": estado,
        "duracion_segundos": duracion,
        "archivo": archivo,
    }


def guardar_informe_final(config: Dict[str, Any], pasos: List[Dict[str, Any]]) -> Dict[str, Any]:
    carta = cargar_json(OUT_DIR / "carta_kitcherry.json", {})
    revisiones = cargar_json(OUT_DIR / "revisiones_platos.json", {})

    total_revisiones = 0
    if isinstance(revisiones, dict):
        revisiones_dict = revisiones.get("revisiones", {})
        if isinstance(revisiones_dict, dict):
            total_revisiones = len(revisiones_dict)

    informe = {
        "proyecto": "Kitcherry Docs",
        "iteracion": config["iteracion"],
        "negocio": config["negocio"],
        "fecha": ahora_legible(),
        "estado_general": "completado",
        "python": sys.executable,
        "base_dir": str(BASE_DIR),
        "pdf_dir": str(PDF_DIR.relative_to(BASE_DIR)),
        "modelo_ollama": config["modelo_ollama"],
        "ollama_activo": EJECUTAR_OLLAMA,
        "pasos": pasos,
        "resumen": {
            "txt_generados": contar_archivos(TXT_DIR, "*.txt"),
            "txt_limpios_generados": contar_archivos(TXT_LIMPIO_DIR, "*.txt"),
            "platos_en_carta": int(carta.get("total_platos", 0)) if isinstance(carta, dict) else 0,
            "resumenes_generados": contar_archivos(SUMMARIES_DIR, "*.summary.txt"),
            "documentos_analizados": len(cargar_json(OUT_DIR / "analisis_ollama_carta.json", {}).get("resultados", [])),
            "revisiones_guardadas": total_revisiones,
        },
        "comprobacion_salidas": {
            "pdf": PDF_DIR.exists(),
            "txt": TXT_DIR.exists(),
            "txt_limpio": TXT_LIMPIO_DIR.exists(),
            "out/platos_detectados.json": existe_ruta_relativa("out/platos_detectados.json"),
            "out/carta_kitcherry.json": existe_ruta_relativa("out/carta_kitcherry.json"),
            "out/revisiones_platos.json": existe_ruta_relativa("out/revisiones_platos.json"),
            "summaries": SUMMARIES_DIR.exists(),
            "out/analisis_ollama_carta.json": existe_ruta_relativa("out/analisis_ollama_carta.json"),
        },
    }

    guardar_json(OUT_DIR / "proceso_integral_kitcherry.json", informe)
    return informe


def mostrar_resumen_final(informe: Dict[str, Any]) -> None:
    imprimir_bloque("RESUMEN FINAL")

    resumen = informe.get("resumen", {})
    comprobacion = informe.get("comprobacion_salidas", {})

    print(f"Estado general: {informe.get('estado_general', 'sin_estado')}")
    print(f"TXT generados: {resumen.get('txt_generados', 0)}")
    print(f"TXT limpios generados: {resumen.get('txt_limpios_generados', 0)}")
    print(f"Platos en carta estructurada: {resumen.get('platos_en_carta', 0)}")
    print(f"Resúmenes generados: {resumen.get('resumenes_generados', 0)}")
    print(f"Documentos analizados: {resumen.get('documentos_analizados', 0)}")
    print(f"Revisiones guardadas: {resumen.get('revisiones_guardadas', 0)}")
    print("Informe guardado en: out\\proceso_integral_kitcherry.json")
    print()
    print("Comprobación de salidas:")

    for ruta, existe in comprobacion.items():
        print(f"- {ruta} -> {'OK' if existe else 'FALTA'}")

    print()
    print("Proceso terminado.")


# ==========================================================
# MAIN
# ==========================================================

def main() -> None:
    asegurar_carpetas()

    config = cargar_configuracion()

    imprimir_bloque(config["nombre_proceso"])

    print(f"Inicio del proceso: {ahora_legible()}")
    print(f"Python usado: {sys.executable}")
    print(f"Carpeta base: {BASE_DIR}")
    print(f"Carpeta PDF: {PDF_DIR.relative_to(BASE_DIR)}")
    print(f"Negocio: {config['negocio']}")
    print(f"Iteración: {config['iteracion']}")
    print(f"Modelo Ollama: {config['modelo_ollama']}")
    print(f"Ejecutar pasos con Ollama: {'Sí' if EJECUTAR_OLLAMA else 'No'}")

    pasos = []

    resultado_001, duracion_001 = paso_001_leer_pdfs()
    pasos.append(registrar_paso("001", "Lectura de documentos PDF", resultado_001.get("estado", "sin_estado"), duracion_001, "pdf"))
    print()
    print(f"Estado del paso 001: {resultado_001.get('estado')}")
    print(f"Duración: {duracion_001} segundos")

    resultado_002, duracion_002 = paso_002_limpiar_textos()
    pasos.append(registrar_paso("002", "Limpieza de texto", resultado_002.get("estado", "sin_estado"), duracion_002, "txt_limpio"))
    print()
    print(f"Estado del paso 002: {resultado_002.get('estado')}")
    print(f"Duración: {duracion_002} segundos")

    resultado_003, duracion_003 = paso_003_detectar_platos()
    pasos.append(registrar_paso("003", "Detección oficial de platos desde carta", resultado_003.get("estado", "sin_estado"), duracion_003, "out/platos_detectados.json"))
    print()
    print(f"Estado del paso 003: {resultado_003.get('estado')}")
    print(f"Duración: {duracion_003} segundos")

    resultado_004, duracion_004 = paso_004_generar_carta_estructurada(config)
    pasos.append(registrar_paso("004", "Generación de carta estructurada", resultado_004.get("estado", "sin_estado"), duracion_004, "out/carta_kitcherry.json"))
    print()
    print(f"Estado del paso 004: {resultado_004.get('estado')}")
    print(f"Duración: {duracion_004} segundos")

    resultado_005, duracion_005 = paso_005_resumir_documentos(config)
    pasos.append(registrar_paso("005", "Resumen de documentos", resultado_005.get("estado", "sin_estado"), duracion_005, "summaries"))
    print()
    print(f"Estado del paso 005: {resultado_005.get('estado')}")
    print(f"Duración: {duracion_005} segundos")

    resultado_006, duracion_006 = paso_006_analisis_documental(config)
    pasos.append(registrar_paso("006", "Análisis documental", resultado_006.get("estado", "sin_estado"), duracion_006, "out/analisis_ollama_carta.json"))
    print()
    print(f"Estado del paso 006: {resultado_006.get('estado')}")
    print(f"Duración: {duracion_006} segundos")

    informe = guardar_informe_final(config, pasos)
    mostrar_resumen_final(informe)
