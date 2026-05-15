# ==========================================================
# KITCHERRY WEB ANALYTICS
# Archivo: 006-generar-informe.py
# Genera un HTML puente hacia el nuevo panel PHP
# ==========================================================

import os
from datetime import datetime

from config import INFORMES_DIR


def generar_informe():
    os.makedirs(INFORMES_DIR, exist_ok=True)

    fecha = datetime.now().strftime("%d/%m/%Y %H:%M")

    contenido = f"""<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry Web Analytics</title>
    <meta http-equiv="refresh" content="0; url=index.php">
    <style>
        body {{
            font-family: Arial, Helvetica, sans-serif;
            background: #f7f1f2;
            color: #171717;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }}

        .box {{
            background: white;
            border-radius: 18px;
            padding: 28px;
            max-width: 620px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.10);
            border-left: 6px solid #C2182B;
        }}

        h1 {{
            margin-top: 0;
            color: #C2182B;
        }}

        a {{
            color: #C2182B;
            font-weight: bold;
        }}
    </style>
</head>
<body>
    <div class="box">
        <h1>Kitcherry Web Analytics</h1>
        <p>El informe visual ahora se muestra desde el panel PHP.</p>
        <p><a href="index.php">Abrir panel PHP</a></p>
        <p>Archivo puente generado el {fecha}.</p>
    </div>
</body>
</html>"""

    ruta_informe = os.path.join(INFORMES_DIR, "informe_analytics.html")

    with open(ruta_informe, "w", encoding="utf-8") as archivo:
        archivo.write(contenido)

    print("Informe puente generado correctamente:", ruta_informe)
    print("Panel principal: informes/index.php")


if __name__ == "__main__":
    generar_informe()