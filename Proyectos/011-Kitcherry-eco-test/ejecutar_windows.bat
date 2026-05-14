@echo off
chcp 65001 > nul
title Kitcherry EcoTest

echo ===============================================
echo          KITCHERRY ECOTEST
echo ===============================================
echo.
echo Ejecutando pruebas de eficiencia...
echo.

python main.py

if errorlevel 1 (
    echo.
    echo No se ha podido ejecutar con python.
    echo Prueba con: py main.py
    echo.
    py main.py
)

echo.
echo Pulsa una tecla para cerrar...
pause > nul
