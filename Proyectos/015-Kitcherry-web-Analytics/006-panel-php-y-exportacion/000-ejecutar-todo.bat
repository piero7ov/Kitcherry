@echo off
chcp 65001 >nul

echo ====================================
echo KITCHERRY WEB ANALYTICS
echo ====================================
echo.

echo [1/6] Creando/actualizando base de datos...
python 001-crear-bbdd.py

echo.
echo [2/6] Generando logs ficticios...
python 002-generar-log-demo.py

echo.
echo [3/6] Analizando logs...
python 003-analizar-logs.py

echo.
echo [4/6] Ejecutando minibot...
python 004-minibot-kitcherry.py

echo.
echo [5/6] Generando graficas...
python 005-generar-graficas.py

echo.
echo [6/6] Generando informe puente...
python 006-generar-informe.py

echo.
echo ====================================
echo PROCESO FINALIZADO
echo ====================================
echo.
echo Abre el panel PHP desde XAMPP:
echo informes/index.php
echo.
echo Si tienes el proyecto dentro de htdocs, abre la ruta desde localhost.
echo.

pause