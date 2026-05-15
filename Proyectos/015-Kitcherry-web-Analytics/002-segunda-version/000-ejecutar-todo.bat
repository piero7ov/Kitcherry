@echo off
chcp 65001 >nul
cls

echo ====================================
echo KITCHERRY WEB ANALYTICS
echo ====================================
echo.

echo [1/6] Creando base de datos...
python 001-crear-bbdd.py
if errorlevel 1 goto error

echo.
echo [2/6] Generando log demo...
python 002-generar-log-demo.py
if errorlevel 1 goto error

echo.
echo [3/6] Analizando logs...
python 003-analizar-logs.py
if errorlevel 1 goto error

echo.
echo [4/6] Ejecutando minibot...
echo IMPORTANTE: XAMPP debe estar encendido y la web local debe abrir correctamente.
python 004-minibot-kitcherry.py

echo.
echo [5/6] Generando graficas...
python 005-generar-graficas.py
if errorlevel 1 goto error

echo.
echo [6/6] Generando informe HTML...
python 006-generar-informe.py
if errorlevel 1 goto error

echo.
echo ====================================
echo PROCESO FINALIZADO
echo Abre el archivo: informes\informe_analytics.html
echo ====================================
pause
exit /b

:error
echo.
echo Ha ocurrido un error. Revisa el mensaje anterior.
pause
