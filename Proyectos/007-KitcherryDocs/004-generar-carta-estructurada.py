import os
import re
import json
import unicodedata
from datetime import datetime

# ==========================================================
# KITCHERRY DOCS - ITERACIÓN 4
# Archivo: 004-generar-carta-estructurada.py
# Objetivo:
# - Leer out/platos_detectados.json
# - Buscar información adicional en los TXT limpios
# - Añadir categoría, precio, descripción e ingredientes
# - Añadir datos de fichas técnicas si existen
# - Generar out/carta_kitcherry.json
# ==========================================================

TXT_LIMPIO_FOLDER = "txt_limpio"
OUT_FOLDER = "out"

PLATOS_JSON = os.path.join(OUT_FOLDER, "platos_detectados.json")
OUT_JSON = os.path.join(OUT_FOLDER, "carta_kitcherry.json")

os.makedirs(OUT_FOLDER, exist_ok=True)


# ==========================================================
# FUNCIONES GENERALES
# ==========================================================

def normalizar_texto(texto):
    """
    Normaliza texto para comparar nombres aunque tengan mayúsculas,
    tildes o espacios diferentes.
    """

    texto = texto.lower().strip()

    # Quitar tildes
    texto = unicodedata.normalize("NFD", texto)
    texto = "".join(
        caracter for caracter in texto
        if unicodedata.category(caracter) != "Mn"
    )

    # Quitar símbolos innecesarios
    texto = re.sub(r"[^a-z0-9ñ\s]", " ", texto)

    # Reducir espacios
    texto = re.sub(r"\s+", " ", texto)

    return texto.strip()


def limpiar_espacios(texto):
    """
    Limpia saltos de línea y espacios repetidos.
    """

    texto = texto.replace("\n", " ")
    texto = re.sub(r"\s+", " ", texto)
    return texto.strip()


def limpiar_ruido_documento(texto):
    """
    Elimina marcas de página, cabeceras y restos del documento de prueba.
    """

    patrones_ruido = [
        r"===== PÁGINA \d+ =====",
        r"KITCHERRY - DOCUMENTO DE PRUEBA",
        r"Página \d+",
        r"Carta Casa Pochi",
        r"Carta ficticia para pruebas de extracción de platos, precios e ingredientes\.",
        r"Carta de prueba - Restaurante Casa Pochi",
        r"Nota: documento de prueba\..*",
    ]

    for patron in patrones_ruido:
        texto = re.sub(patron, "", texto, flags=re.IGNORECASE)

    texto = re.sub(r"\s+", " ", texto)

    return texto.strip(" .")


def construir_patron_nombre(nombre):
    """
    Convierte un nombre de plato en un patrón flexible.
    Permite detectar nombres aunque estén partidos en varias líneas.
    """

    palabras = nombre.split()
    palabras_escapadas = [re.escape(palabra) for palabra in palabras]

    patron = r"\b" + r"\s+".join(palabras_escapadas) + r"\b"

    return patron


def normalizar_categoria(categoria):
    """
    Unifica categorías para que no queden unas en singular y otras en plural.
    """

    categoria_normalizada = normalizar_texto(categoria)

    equivalencias = {
        "entrante": "Entrantes",
        "entrantes": "Entrantes",
        "principal": "Platos principales",
        "plato principal": "Platos principales",
        "platos principales": "Platos principales",
        "postre": "Postres",
        "postres": "Postres",
        "bebida": "Bebidas",
        "bebidas": "Bebidas"
    }

    return equivalencias.get(categoria_normalizada, categoria)


def leer_txt_limpios():
    """
    Lee todos los TXT limpios y devuelve un diccionario:
    {
        "archivo.txt": "contenido..."
    }
    """

    textos = {}

    if not os.path.isdir(TXT_LIMPIO_FOLDER):
        print(f"ERROR: No existe la carpeta {TXT_LIMPIO_FOLDER}/")
        print("Primero ejecuta 002-limpiar-texto.py")
        return textos

    archivos = sorted([
        archivo for archivo in os.listdir(TXT_LIMPIO_FOLDER)
        if archivo.lower().endswith(".txt")
    ])

    for archivo in archivos:
        ruta = os.path.join(TXT_LIMPIO_FOLDER, archivo)

        with open(ruta, "r", encoding="utf-8", errors="ignore") as f:
            textos[archivo] = f.read()

    return textos


def cargar_platos_detectados():
    """
    Carga el JSON generado en la iteración 3.
    """

    if not os.path.exists(PLATOS_JSON):
        print(f"ERROR: No existe {PLATOS_JSON}")
        print("Primero ejecuta 003-detectar-platos-alergenos.py")
        return []

    with open(PLATOS_JSON, "r", encoding="utf-8") as f:
        datos = json.load(f)

    return datos.get("platos", [])


# ==========================================================
# EXTRACCIÓN DESDE CARTA
# ==========================================================

def detectar_categoria(texto, posicion):
    """
    Busca la última categoría aparecida antes del plato.
    """

    categorias = [
        "Entrantes",
        "Platos principales",
        "Postres",
        "Bebidas"
    ]

    categoria_detectada = ""

    for categoria in categorias:
        for coincidencia in re.finditer(re.escape(categoria), texto, re.IGNORECASE):
            if coincidencia.start() < posicion:
                categoria_detectada = categoria

    return categoria_detectada


def buscar_posiciones_platos(texto, nombres_platos):
    """
    Busca las posiciones de todos los platos dentro de un texto.
    Sirve para saber dónde termina la descripción de un plato.
    """

    posiciones = []

    for nombre in nombres_platos:
        patron = construir_patron_nombre(nombre)

        for coincidencia in re.finditer(patron, texto, re.IGNORECASE):
            posiciones.append({
                "nombre": nombre,
                "inicio": coincidencia.start(),
                "fin": coincidencia.end()
            })

    posiciones.sort(key=lambda item: item["inicio"])

    return posiciones


def linea_valida_como_producto(linea):
    """
    Comprueba si una línea puede ser el nombre de un producto o plato.
    Sirve para evitar cortar por cabeceras, páginas o texto basura.
    """

    linea = linea.strip()
    linea_min = linea.lower()

    if not linea:
        return False

    if len(linea) < 3:
        return False

    bloqueadas = [
        "kitcherry - documento de prueba",
        "carta casa pochi",
        "carta ficticia",
        "carta de prueba",
        "página",
        "nota:",
        "plato",
        "precio",
        "descripción",
        "descripcion",
        "ingredientes"
    ]

    for palabra in bloqueadas:
        if linea_min.startswith(palabra):
            return False

    if linea.startswith("====="):
        return False

    # Evitar líneas que ya sean precios
    if re.search(r"\d+[,\.]\d{2}\s*€", linea):
        return False

    return True


def buscar_inicio_siguiente_producto_por_precio(texto, desde):
    """
    Busca el siguiente producto de la carta aunque no esté en platos_detectados.json.

    Caso típico del PDF convertido a TXT:

    Cerveza artesanal
    3,80 €
    Contiene cebada y puede contener trazas de gluten.
    Refresco de cola
    2,50 €
    Bebida refrescante sin alérgenos declarados.

    Antes el corte se hacía en la línea del precio 2,50 €,
    por eso se quedaba dentro 'Refresco de cola'.

    Ahora se detecta:
    Refresco de cola
    2,50 €

    Y se corta desde 'Refresco de cola'.
    """

    segmento = texto[desde:]

    # Caso 1: nombre del producto en una línea y precio en la siguiente
    patron_linea_separada = re.compile(
        r"\n(?P<nombre>[^\n€]{3,100})\n(?P<precio>\d+[,\.]\d{2}\s*€)",
        re.IGNORECASE
    )

    for coincidencia in patron_linea_separada.finditer(segmento):
        nombre_producto = coincidencia.group("nombre").strip()

        if linea_valida_como_producto(nombre_producto):
            return desde + coincidencia.start("nombre")

    # Caso 2: nombre del producto y precio en la misma línea
    patron_misma_linea = re.compile(
        r"\n(?P<nombre>[^\n€]{3,100}?)\s+(?P<precio>\d+[,\.]\d{2}\s*€)",
        re.IGNORECASE
    )

    for coincidencia in patron_misma_linea.finditer(segmento):
        nombre_producto = coincidencia.group("nombre").strip()

        if linea_valida_como_producto(nombre_producto):
            return desde + coincidencia.start("nombre")

    return None


def buscar_inicio_nota(texto, desde):
    """
    Busca si aparece una nota final del documento.
    """

    coincidencia = re.search(r"\bNota\s*:", texto[desde:], re.IGNORECASE)

    if not coincidencia:
        return None

    return desde + coincidencia.start()


def limpiar_descripcion(descripcion):
    """
    Limpia la descripción extraída de la carta.
    """

    descripcion = limpiar_ruido_documento(descripcion)
    descripcion = limpiar_espacios(descripcion)

    return descripcion.strip(" .")


def extraer_ingredientes_desde_descripcion(descripcion):
    """
    Extrae una lista simple de ingredientes desde la descripción.
    Es una extracción inicial, no perfecta.
    """

    descripcion_limpia = descripcion.strip(" .")

    # Quitar frases poco útiles
    descripcion_limpia = re.sub(
        r"\bcontiene\b",
        "",
        descripcion_limpia,
        flags=re.IGNORECASE
    )

    descripcion_limpia = re.sub(
        r"\bpuede contener trazas de\b",
        "",
        descripcion_limpia,
        flags=re.IGNORECASE
    )

    descripcion_limpia = re.sub(
        r"\bpuede contener\b",
        "",
        descripcion_limpia,
        flags=re.IGNORECASE
    )

    descripcion_limpia = re.sub(
        r"\bsin alérgenos declarados\b",
        "",
        descripcion_limpia,
        flags=re.IGNORECASE
    )

    # Separar por comas y por " y "
    partes = re.split(r",|\s+y\s+", descripcion_limpia)

    ingredientes = []

    for parte in partes:
        ingrediente = limpiar_espacios(parte)
        ingrediente = ingrediente.strip(" .")

        # Evitar restos que sean claramente precios o texto basura
        if re.search(r"\d+[,\.]\d{2}\s*€", ingrediente):
            continue

        if ingrediente.lower().startswith("nota:"):
            continue

        if len(ingrediente) >= 3:
            ingredientes.append(ingrediente)

    # Eliminar duplicados manteniendo orden
    resultado = []
    vistos = set()

    for ingrediente in ingredientes:
        clave = normalizar_texto(ingrediente)

        if clave not in vistos:
            vistos.add(clave)
            resultado.append(ingrediente)

    return resultado


def extraer_datos_carta(textos, nombres_platos):
    """
    Busca los platos dentro del documento de carta y extrae:
    - categoría
    - precio
    - descripción
    - ingredientes
    """

    datos_carta = {}

    # Buscar preferentemente archivos que parezcan una carta
    textos_carta = {
        archivo: contenido
        for archivo, contenido in textos.items()
        if "carta" in archivo.lower()
    }

    for archivo, texto in textos_carta.items():

        posiciones = buscar_posiciones_platos(texto, nombres_platos)

        for posicion_plato in posiciones:
            nombre = posicion_plato["nombre"]
            inicio = posicion_plato["inicio"]
            fin_nombre = posicion_plato["fin"]

            clave_nombre = normalizar_texto(nombre)

            # Buscar precio después del nombre
            texto_despues_nombre = texto[fin_nombre:]
            precio_match = re.search(r"\d+[,\.]\d{2}\s*€", texto_despues_nombre)

            if not precio_match:
                continue

            precio = precio_match.group(0).strip()
            fin_precio_real = fin_nombre + precio_match.end()

            # Punto de corte inicial: final del documento
            siguiente_inicio = len(texto)

            # 1. Cortar por siguiente plato conocido
            for otra_posicion in posiciones:
                if otra_posicion["inicio"] > inicio:
                    siguiente_inicio = min(siguiente_inicio, otra_posicion["inicio"])

            # 2. Cortar por siguiente producto con precio,
            # aunque no esté en platos_detectados.json
            siguiente_producto_precio = buscar_inicio_siguiente_producto_por_precio(
                texto,
                fin_precio_real
            )

            if siguiente_producto_precio is not None and siguiente_producto_precio > fin_precio_real:
                siguiente_inicio = min(siguiente_inicio, siguiente_producto_precio)

            # 3. Cortar por siguiente categoría
            categorias = [
                "Entrantes",
                "Platos principales",
                "Postres",
                "Bebidas"
            ]

            for categoria in categorias:
                for coincidencia_categoria in re.finditer(
                    re.escape(categoria),
                    texto[fin_precio_real:],
                    re.IGNORECASE
                ):
                    posicion_categoria = fin_precio_real + coincidencia_categoria.start()

                    if posicion_categoria > inicio:
                        siguiente_inicio = min(siguiente_inicio, posicion_categoria)

            # 4. Cortar por nota final
            inicio_nota = buscar_inicio_nota(texto, fin_precio_real)

            if inicio_nota is not None:
                siguiente_inicio = min(siguiente_inicio, inicio_nota)

            descripcion = texto[fin_precio_real:siguiente_inicio]
            descripcion = limpiar_descripcion(descripcion)

            datos_carta[clave_nombre] = {
                "categoria": normalizar_categoria(detectar_categoria(texto, inicio)),
                "precio": precio,
                "descripcion": descripcion,
                "ingredientes_detectados": extraer_ingredientes_desde_descripcion(descripcion),
                "fuente_carta": archivo
            }

    return datos_carta


# ==========================================================
# EXTRACCIÓN DESDE FICHAS TÉCNICAS
# ==========================================================

def es_linea_corte_ficha(linea):
    """
    Detecta líneas donde se debe dejar de capturar un campo de ficha técnica.
    """

    linea_limpia = linea.strip()
    linea_min = linea_limpia.lower()

    if linea_limpia.startswith("====="):
        return True

    if linea_min.startswith("kitcherry - documento de prueba"):
        return True

    if re.match(r"^página\s+\d+$", linea_min):
        return True

    if re.match(r"^ficha\s+\d+\s*:", linea_min):
        return True

    return False


def limpiar_valor_ficha(texto):
    """
    Limpia valores extraídos desde fichas técnicas.
    """

    texto = limpiar_ruido_documento(texto)
    texto = limpiar_espacios(texto)

    return texto.strip(" .")


def extraer_valor_campo(lineas, campo):
    """
    Extrae el valor de un campo dentro de una ficha.

    Ejemplo:
    Categoría
    Entrante

    Ingredientes
    Leche, harina...
    """

    campos_posibles = [
        "Campo",
        "Información",
        "Categoría",
        "Raciones",
        "Ingredientes",
        "Elaboración",
        "Conservación",
        "Alérgenos declarados"
    ]

    valor = []
    capturando = False

    for linea in lineas:
        linea_limpia = linea.strip()

        if normalizar_texto(linea_limpia) == normalizar_texto(campo):
            capturando = True
            continue

        if capturando:

            # Cortar si aparece ruido de página o nueva ficha
            if es_linea_corte_ficha(linea_limpia):
                break

            # Si aparece otro campo, dejamos de capturar
            if any(
                normalizar_texto(linea_limpia) == normalizar_texto(campo_posible)
                for campo_posible in campos_posibles
            ):
                break

            if linea_limpia:
                valor.append(linea_limpia)

    return limpiar_valor_ficha(" ".join(valor))


def extraer_fichas_tecnicas(textos):
    """
    Extrae fichas técnicas desde documentos que contengan bloques tipo:
    Ficha 1: Croquetas caseras de jamón
    """

    fichas = {}

    textos_fichas = {
        archivo: contenido
        for archivo, contenido in textos.items()
        if "ficha" in archivo.lower()
    }

    for archivo, texto in textos_fichas.items():

        # Localizar encabezados "Ficha X:"
        encabezados = list(re.finditer(r"Ficha\s+\d+\s*:\s*(.+)", texto, re.IGNORECASE))

        for indice, encabezado in enumerate(encabezados):
            nombre_plato = limpiar_espacios(encabezado.group(1))

            inicio_bloque = encabezado.end()

            if indice + 1 < len(encabezados):
                fin_bloque = encabezados[indice + 1].start()
            else:
                fin_bloque = len(texto)

            bloque = texto[inicio_bloque:fin_bloque]
            lineas = [linea.strip() for linea in bloque.splitlines() if linea.strip()]

            clave_nombre = normalizar_texto(nombre_plato)

            fichas[clave_nombre] = {
                "nombre_ficha": nombre_plato,
                "categoria_ficha": normalizar_categoria(extraer_valor_campo(lineas, "Categoría")),
                "raciones": extraer_valor_campo(lineas, "Raciones"),
                "ingredientes_ficha": extraer_valor_campo(lineas, "Ingredientes"),
                "elaboracion": extraer_valor_campo(lineas, "Elaboración"),
                "conservacion": extraer_valor_campo(lineas, "Conservación"),
                "alergenos_texto_ficha": extraer_valor_campo(lineas, "Alérgenos declarados"),
                "fuente_ficha": archivo
            }

    return fichas


# ==========================================================
# GENERACIÓN DEL JSON FINAL
# ==========================================================

def generar_carta_estructurada():
    textos = leer_txt_limpios()
    platos_detectados = cargar_platos_detectados()

    if not textos:
        return

    if not platos_detectados:
        print("No hay platos detectados para estructurar.")
        return

    nombres_platos = [plato["nombre"] for plato in platos_detectados]

    datos_carta = extraer_datos_carta(textos, nombres_platos)
    fichas_tecnicas = extraer_fichas_tecnicas(textos)

    platos_finales = []

    for indice, plato in enumerate(platos_detectados, start=1):
        nombre = plato.get("nombre", "")
        clave_nombre = normalizar_texto(nombre)

        info_carta = datos_carta.get(clave_nombre, {})
        info_ficha = fichas_tecnicas.get(clave_nombre, {})

        categoria = (
            info_ficha.get("categoria_ficha")
            or info_carta.get("categoria")
            or ""
        )

        categoria = normalizar_categoria(categoria)

        fuentes = []

        if plato.get("fuente"):
            fuentes.append(plato.get("fuente"))

        if info_carta.get("fuente_carta"):
            fuentes.append(info_carta.get("fuente_carta"))

        if info_ficha.get("fuente_ficha"):
            fuentes.append(info_ficha.get("fuente_ficha"))

        # Eliminar fuentes duplicadas
        fuentes_unicas = []

        for fuente in fuentes:
            if fuente not in fuentes_unicas:
                fuentes_unicas.append(fuente)

        plato_final = {
            "id": indice,
            "nombre": nombre,
            "categoria": categoria,
            "precio": info_carta.get("precio", ""),
            "descripcion": info_carta.get("descripcion", ""),
            "ingredientes_detectados": info_carta.get("ingredientes_detectados", []),
            "alergenos_declarados": plato.get("alergenos_declarados", []),
            "estado_revision": plato.get("estado_revision", "pendiente"),
            "ficha_tecnica": {
                "raciones": info_ficha.get("raciones", ""),
                "ingredientes": info_ficha.get("ingredientes_ficha", ""),
                "elaboracion": info_ficha.get("elaboracion", ""),
                "conservacion": info_ficha.get("conservacion", ""),
                "alergenos_texto": info_ficha.get("alergenos_texto_ficha", "")
            },
            "fuentes": fuentes_unicas
        }

        platos_finales.append(plato_final)

    salida = {
        "proyecto": "Kitcherry Docs",
        "iteracion": "004-generar-carta-estructurada",
        "descripcion": "Carta estructurada con platos, precios, categorías, ingredientes, alérgenos y fichas técnicas.",
        "fecha_generacion": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "negocio": "Casa Pochi",
        "estado_general": "borrador_pendiente_revision",
        "total_platos": len(platos_finales),
        "platos": platos_finales
    }

    with open(OUT_JSON, "w", encoding="utf-8") as f:
        json.dump(salida, f, ensure_ascii=False, indent=4)

    print("\nProceso finalizado.")
    print(f"JSON generado en: {OUT_JSON}")
    print(f"Total de platos estructurados: {len(platos_finales)}")

    # Resumen rápido por consola
    for plato in platos_finales:
        print(
            f"- {plato['nombre']} | "
            f"{plato['categoria']} | "
            f"{plato['precio']} | "
            f"{len(plato['alergenos_declarados'])} alérgenos"
        )


if __name__ == "__main__":
    generar_carta_estructurada()