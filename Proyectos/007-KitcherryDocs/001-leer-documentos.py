import os

try:
    from pypdf import PdfReader
except ImportError:
    from PyPDF2 import PdfReader

# ==========================================================
# KITCHERRY DOCS
# Objetivo: leer documentos PDF de la carpeta pdf/ y generar TXT
# ==========================================================

PDF_FOLDER = "pdf"
TXT_FOLDER = "txt"

# Crear carpeta txt si no existe
os.makedirs(TXT_FOLDER, exist_ok=True)

# Comprobar que existe la carpeta de PDFs
if not os.path.isdir(PDF_FOLDER):
    print(f"ERROR: No existe la carpeta {PDF_FOLDER}/")
    print("Crea una carpeta llamada pdf y coloca dentro los documentos PDF.")
    raise SystemExit

# Recorrer todos los PDFs de la carpeta
for archivo in os.listdir(PDF_FOLDER):

    if archivo.lower().endswith(".pdf"):

        ruta_pdf = os.path.join(PDF_FOLDER, archivo)
        nombre_txt = os.path.splitext(archivo)[0] + ".txt"
        ruta_txt = os.path.join(TXT_FOLDER, nombre_txt)

        try:
            reader = PdfReader(ruta_pdf)
            texto = ""

            for numero_pagina, pagina in enumerate(reader.pages, start=1):
                contenido = pagina.extract_text()

                if contenido:
                    texto += f"\n\n===== PÁGINA {numero_pagina} =====\n\n"
                    texto += contenido + "\n"

            with open(ruta_txt, "w", encoding="utf-8") as f:
                f.write(texto.strip())

            print(f"OK -> {archivo} convertido a {nombre_txt}")

        except Exception as e:
            print(f"ERROR -> {archivo}: {e}")

print("\nProceso finalizado.")