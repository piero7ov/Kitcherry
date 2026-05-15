KITCHERRY WEB ANALYTICS
=======================

Mini proyecto para el punto 5.1: Procedimiento de seguimiento de las actividades.

Este proyecto combina dos ideas:

1. Analíticas de actividad:
   - visitas totales
   - páginas más visitadas
   - formularios enviados
   - errores HTTP
   - actividad por día y por hora

2. Revisión técnica con minibot:
   - páginas encontradas
   - títulos detectados
   - correos visibles
   - enlaces internos y externos
   - posibles errores de acceso

REQUISITOS
----------

1. Tener Python instalado.
2. Instalar dependencias:

   pip install -r requirements.txt

3. Si quieres usar el minibot, encender XAMPP y comprobar que la web abre en:

   http://localhost/DAMPieroOlivares/Primero/Proyecto%20intermodular/203-Proyectos%20de%20Kitcherry/006-redes%20sociales/

EJECUCIÓN RÁPIDA EN WINDOWS
---------------------------

Doble clic en:

   000-ejecutar-todo.bat

EJECUCIÓN MANUAL
----------------

python 001-crear-bbdd.py
python 002-generar-log-demo.py
python 003-analizar-logs.py
python 004-minibot-kitcherry.py
python 005-generar-graficas.py
python 006-generar-informe.py

RESULTADO
---------

El informe final se crea en:

   informes/informe_analytics.html

Las gráficas se crean en:

   graficas/

La base de datos se guarda en:

   kitcherry_analytics.sqlite

NOTA
----

Ahora se usan logs simulados porque la web está en local. Cuando la web esté publicada en un servidor real, se podrá reemplazar logs/access_demo.log por un log real del servidor manteniendo el mismo procedimiento.

RECURSOS VISUALES
=================
Se ha añadido la carpeta assets/ para que puedas colocar tus recursos de marca:

assets/img/logo.png
- Sustituye este archivo vacío por tu logo real de Kitcherry.

assets/fuente/coolveltica/
- Coloca aquí tu archivo de fuente Coolvetica.
- El CSS ya apunta a: assets/fuente/coolveltica/Coolvetica Rg.otf

assets/css/style.css
- Contiene los estilos del informe HTML, la referencia al logo y la carga de la tipografía.
