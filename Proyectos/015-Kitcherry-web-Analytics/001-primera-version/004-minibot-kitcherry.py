# ==========================================================
# KITCHERRY WEB ANALYTICS
# Archivo: 004-minibot-kitcherry.py
# Revisa técnicamente la web corporativa de Kitcherry en local
# ==========================================================

import os
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


def guardar_revision(cursor, url, titulo, codigo_estado, correos, enlaces_internos, enlaces_externos, error=None):
    cursor.execute("""
        INSERT INTO revision_minibot
        (url, titulo, codigo_estado, correos, num_enlaces_internos, num_enlaces_externos, error)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    """, (
        url,
        titulo,
        codigo_estado,
        ", ".join(sorted(set(correos))) if correos else "",
        enlaces_internos,
        enlaces_externos,
        error,
    ))


def guardar_enlace(cursor, origen, destino, tipo, codigo_estado=None, observacion=""):
    cursor.execute("""
        INSERT INTO enlaces_minibot
        (origen, destino, tipo, codigo_estado, observacion)
        VALUES (?, ?, ?, ?, ?)
    """, (origen, destino, tipo, codigo_estado, observacion))


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

    print("\n====================================")
    print("KITCHERRY WEB ANALYTICS - MINIBOT")
    print("====================================")
    print("URL inicial:", BASE_URL)

    while pendientes and len(visitadas) < MAX_PAGINAS_MINIBOT:
        url_actual = pendientes.popleft()
        url_actual = normalizar_url(url_actual)

        if url_actual in visitadas:
            continue

        if not es_rastreable(url_actual):
            continue

        visitadas.add(url_actual)
        print("Revisando:", url_actual)

        try:
            respuesta = requests.get(url_actual, timeout=8)
            codigo_estado = respuesta.status_code
            content_type = respuesta.headers.get("Content-Type", "")

            if "text/html" not in content_type and respuesta.text.strip().startswith("<") is False:
                guardar_revision(cursor, url_actual, "", codigo_estado, [], 0, 0, "No parece HTML")
                continue

            soup = BeautifulSoup(respuesta.text, "html.parser")
            titulo = soup.title.get_text(strip=True) if soup.title else "Sin título"
            correos = PATRON_CORREO.findall(respuesta.text)

            enlaces_internos = 0
            enlaces_externos = 0

            for etiqueta in soup.find_all("a", href=True):
                href = etiqueta.get("href", "").strip()

                if not href or href.startswith("#") or href.startswith("javascript:"):
                    continue

                if href.startswith("mailto:") or href.startswith("tel:"):
                    tipo = obtener_tipo_enlace(href)
                    guardar_enlace(cursor, url_actual, href, tipo, None, "Enlace especial")
                    continue

                destino = normalizar_url(urljoin(url_actual, href))
                tipo = obtener_tipo_enlace(destino)

                if tipo == "interno":
                    enlaces_internos += 1
                    guardar_enlace(cursor, url_actual, destino, tipo, None, "Enlace interno detectado")

                    if destino not in visitadas and es_rastreable(destino):
                        pendientes.append(destino)
                else:
                    enlaces_externos += 1
                    guardar_enlace(cursor, url_actual, destino, tipo, None, "Enlace externo detectado")

            guardar_revision(
                cursor,
                url_actual,
                titulo,
                codigo_estado,
                correos,
                enlaces_internos,
                enlaces_externos,
                None if codigo_estado < 400 else f"Código HTTP {codigo_estado}",
            )

        except requests.RequestException as error:
            guardar_revision(cursor, url_actual, "", None, [], 0, 0, str(error))

        conexion.commit()

    conexion.close()

    print("\nRevisión finalizada.")
    print("Páginas revisadas:", len(visitadas))
    print("Resultados guardados en:", DB_FILE)


if __name__ == "__main__":
    revisar_web()
