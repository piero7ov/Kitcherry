# ==========================================================
# KITCHERRY WEB ANALYTICS
# Archivo: 004-minibot-kitcherry.py
# Revisa técnicamente la web corporativa de Kitcherry en local
# ==========================================================

import re
import sqlite3
from collections import deque
from urllib.parse import urljoin, urlparse, urldefrag

from config import (
    BASE_URL,
    DB_FILE,
    MAX_PAGINAS_MINIBOT,
    EXTENSIONES_ESTATICAS,
    ARCHIVOS_TECNICOS_IGNORADOS,
)

try:
    import requests
    from bs4 import BeautifulSoup
except ImportError:
    print("Faltan librerías. Instala dependencias con:")
    print("pip install -r requirements.txt")
    raise SystemExit


PATRON_CORREO = re.compile(r"[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+")


def crear_tablas_si_no_existen():
    import importlib.util

    spec = importlib.util.spec_from_file_location("crear_bbdd", "001-crear-bbdd.py")
    modulo = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(modulo)
    modulo.crear_bbdd()


def normalizar_url(url: str) -> str:
    """Quita anclas internas tipo #contacto para evitar duplicados."""
    url_sin_ancla, _ = urldefrag(url)
    return url_sin_ancla


def es_misma_web(url: str) -> bool:
    """Comprueba que el enlace pertenece al mismo localhost y a la misma carpeta base."""
    base = urlparse(BASE_URL)
    actual = urlparse(url)

    if actual.netloc != base.netloc:
        return False

    base_path = base.path
    if not base_path.endswith("/"):
        base_path += "/"

    return actual.path.startswith(base_path)


def es_rastreable(url: str) -> bool:
    """Evita rastrear imágenes, CSS, vídeos, fuentes o endpoints técnicos."""
    path = urlparse(url).path.lower()

    if path.endswith(EXTENSIONES_ESTATICAS):
        return False

    for archivo in ARCHIVOS_TECNICOS_IGNORADOS:
        if path.endswith(archivo.lower()):
            return False

    return True


def obtener_tipo_enlace(url: str) -> str:
    if url.startswith("mailto:"):
        return "correo"

    if url.startswith("tel:"):
        return "telefono"

    if es_misma_web(url):
        return "interno"

    return "externo"


def texto_limpio(valor):
    if not valor:
        return ""

    return " ".join(valor.strip().split())


def obtener_meta_description(soup):
    meta = soup.find("meta", attrs={"name": re.compile("^description$", re.I)})

    if not meta:
        return ""

    return texto_limpio(meta.get("content", ""))


def obtener_h1(soup):
    h1 = soup.find("h1")

    if not h1:
        return ""

    return texto_limpio(h1.get_text(" ", strip=True))


def obtener_lang(soup):
    html_tag = soup.find("html")

    if not html_tag:
        return ""

    return texto_limpio(html_tag.get("lang", ""))


def calcular_estado_tecnico(codigo_estado, tiene_title, tiene_description, tiene_h1, tiene_lang, enlaces_rotos, error):
    """
    Devuelve un estado tipo semáforo:
    - Correcto
    - Mejorable
    - Revisar
    """
    if error:
        return "Revisar"

    if codigo_estado is None or codigo_estado >= 400:
        return "Revisar"

    if enlaces_rotos > 0:
        return "Revisar"

    if not tiene_title or not tiene_description or not tiene_h1 or not tiene_lang:
        return "Mejorable"

    return "Correcto"


def comprobar_enlace_interno(url, cache_codigos):
    """
    Comprueba si un enlace interno responde correctamente.
    Usa una pequeña caché para no consultar el mismo enlace varias veces.
    """
    if url in cache_codigos:
        return cache_codigos[url]

    if not es_rastreable(url):
        resultado = {
            "codigo_estado": None,
            "es_roto": 0,
            "observacion": "Enlace interno ignorado por ser recurso técnico o estático"
        }
        cache_codigos[url] = resultado
        return resultado

    try:
        respuesta = requests.get(url, timeout=8, allow_redirects=True)
        codigo_estado = respuesta.status_code
        es_roto = 1 if codigo_estado >= 400 else 0

        if es_roto:
            observacion = f"Enlace interno con error HTTP {codigo_estado}"
        else:
            observacion = "Enlace interno correcto"

        resultado = {
            "codigo_estado": codigo_estado,
            "es_roto": es_roto,
            "observacion": observacion
        }

    except requests.RequestException as error:
        resultado = {
            "codigo_estado": None,
            "es_roto": 1,
            "observacion": f"Error al comprobar enlace interno: {error}"
        }

    cache_codigos[url] = resultado
    return resultado


def guardar_revision(
    cursor,
    url,
    titulo,
    meta_description,
    h1,
    html_lang,
    codigo_estado,
    tiempo_respuesta,
    correos,
    enlaces_internos,
    enlaces_externos,
    enlaces_rotos,
    estado_tecnico,
    error=None
):
    tiene_title = 1 if titulo and titulo != "Sin título" else 0
    tiene_meta_description = 1 if meta_description else 0
    tiene_h1 = 1 if h1 else 0
    tiene_lang = 1 if html_lang.lower().startswith("es") else 0

    cursor.execute("""
        INSERT INTO revision_minibot
        (
            url,
            titulo,
            meta_description,
            h1,
            html_lang,
            codigo_estado,
            tiempo_respuesta,
            correos,
            num_enlaces_internos,
            num_enlaces_externos,
            enlaces_rotos,
            tiene_title,
            tiene_meta_description,
            tiene_h1,
            tiene_lang,
            estado_tecnico,
            error
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    """, (
        url,
        titulo,
        meta_description,
        h1,
        html_lang,
        codigo_estado,
        tiempo_respuesta,
        ", ".join(sorted(set(correos))) if correos else "",
        enlaces_internos,
        enlaces_externos,
        enlaces_rotos,
        tiene_title,
        tiene_meta_description,
        tiene_h1,
        tiene_lang,
        estado_tecnico,
        error,
    ))


def guardar_enlace(cursor, origen, destino, tipo, codigo_estado=None, observacion="", es_roto=0):
    cursor.execute("""
        INSERT INTO enlaces_minibot
        (origen, destino, tipo, codigo_estado, observacion, es_roto)
        VALUES (?, ?, ?, ?, ?, ?)
    """, (origen, destino, tipo, codigo_estado, observacion, es_roto))


def revisar_web():
    crear_tablas_si_no_existen()

    conexion = sqlite3.connect(DB_FILE)
    cursor = conexion.cursor()

    # Limpiamos revisiones anteriores para que la salida sea clara.
    cursor.execute("DELETE FROM revision_minibot")
    cursor.execute("DELETE FROM enlaces_minibot")
    conexion.commit()

    pendientes = deque([normalizar_url(BASE_URL)])
    visitadas = set()
    cache_codigos = {}

    print("\n====================================")
    print("KITCHERRY WEB ANALYTICS - MINIBOT")
    print("====================================")
    print("URL inicial:", BASE_URL)

    while pendientes and len(visitadas) < MAX_PAGINAS_MINIBOT:
        url_actual = normalizar_url(pendientes.popleft())

        if url_actual in visitadas:
            continue

        if not es_rastreable(url_actual):
            continue

        visitadas.add(url_actual)
        print("Revisando:", url_actual)

        try:
            respuesta = requests.get(url_actual, timeout=8, allow_redirects=True)
            codigo_estado = respuesta.status_code
            tiempo_respuesta = round(respuesta.elapsed.total_seconds(), 3)
            content_type = respuesta.headers.get("Content-Type", "")

            if respuesta.encoding is None:
                respuesta.encoding = respuesta.apparent_encoding

            parece_html = "text/html" in content_type or respuesta.text.strip().startswith("<")

            if not parece_html:
                error = "No parece una página HTML"
                estado_tecnico = "Revisar"

                guardar_revision(
                    cursor=cursor,
                    url=url_actual,
                    titulo="",
                    meta_description="",
                    h1="",
                    html_lang="",
                    codigo_estado=codigo_estado,
                    tiempo_respuesta=tiempo_respuesta,
                    correos=[],
                    enlaces_internos=0,
                    enlaces_externos=0,
                    enlaces_rotos=0,
                    estado_tecnico=estado_tecnico,
                    error=error
                )

                conexion.commit()
                continue

            soup = BeautifulSoup(respuesta.text, "html.parser")

            titulo = texto_limpio(soup.title.get_text(" ", strip=True)) if soup.title else "Sin título"
            meta_description = obtener_meta_description(soup)
            h1 = obtener_h1(soup)
            html_lang = obtener_lang(soup)
            correos = PATRON_CORREO.findall(respuesta.text)

            tiene_title = 1 if titulo and titulo != "Sin título" else 0
            tiene_description = 1 if meta_description else 0
            tiene_h1 = 1 if h1 else 0
            tiene_lang = 1 if html_lang.lower().startswith("es") else 0

            enlaces_internos = 0
            enlaces_externos = 0
            enlaces_rotos = 0

            for etiqueta in soup.find_all("a", href=True):
                href = etiqueta.get("href", "").strip()

                if not href or href.startswith("#") or href.startswith("javascript:"):
                    continue

                if href.startswith("mailto:") or href.startswith("tel:"):
                    tipo = obtener_tipo_enlace(href)
                    guardar_enlace(
                        cursor=cursor,
                        origen=url_actual,
                        destino=href,
                        tipo=tipo,
                        codigo_estado=None,
                        observacion="Enlace especial",
                        es_roto=0
                    )
                    continue

                destino = normalizar_url(urljoin(url_actual, href))
                tipo = obtener_tipo_enlace(destino)

                if tipo == "interno":
                    enlaces_internos += 1
                    resultado_enlace = comprobar_enlace_interno(destino, cache_codigos)

                    guardar_enlace(
                        cursor=cursor,
                        origen=url_actual,
                        destino=destino,
                        tipo=tipo,
                        codigo_estado=resultado_enlace["codigo_estado"],
                        observacion=resultado_enlace["observacion"],
                        es_roto=resultado_enlace["es_roto"]
                    )

                    if resultado_enlace["es_roto"]:
                        enlaces_rotos += 1

                    if (
                        destino not in visitadas
                        and resultado_enlace["es_roto"] == 0
                        and es_rastreable(destino)
                    ):
                        pendientes.append(destino)

                else:
                    enlaces_externos += 1

                    guardar_enlace(
                        cursor=cursor,
                        origen=url_actual,
                        destino=destino,
                        tipo=tipo,
                        codigo_estado=None,
                        observacion="Enlace externo detectado, no revisado por el minibot",
                        es_roto=0
                    )

            error_revision = None if codigo_estado < 400 else f"Código HTTP {codigo_estado}"

            estado_tecnico = calcular_estado_tecnico(
                codigo_estado=codigo_estado,
                tiene_title=tiene_title,
                tiene_description=tiene_description,
                tiene_h1=tiene_h1,
                tiene_lang=tiene_lang,
                enlaces_rotos=enlaces_rotos,
                error=error_revision
            )

            guardar_revision(
                cursor=cursor,
                url=url_actual,
                titulo=titulo,
                meta_description=meta_description,
                h1=h1,
                html_lang=html_lang,
                codigo_estado=codigo_estado,
                tiempo_respuesta=tiempo_respuesta,
                correos=correos,
                enlaces_internos=enlaces_internos,
                enlaces_externos=enlaces_externos,
                enlaces_rotos=enlaces_rotos,
                estado_tecnico=estado_tecnico,
                error=error_revision
            )

        except requests.RequestException as error:
            guardar_revision(
                cursor=cursor,
                url=url_actual,
                titulo="",
                meta_description="",
                h1="",
                html_lang="",
                codigo_estado=None,
                tiempo_respuesta=None,
                correos=[],
                enlaces_internos=0,
                enlaces_externos=0,
                enlaces_rotos=0,
                estado_tecnico="Revisar",
                error=str(error)
            )

        conexion.commit()

    conexion.close()

    print("\nRevisión finalizada.")
    print("Páginas revisadas:", len(visitadas))
    print("Resultados guardados en:", DB_FILE)


if __name__ == "__main__":
    revisar_web()