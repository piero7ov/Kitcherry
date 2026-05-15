# Uso de logs reales con IONOS en Kitcherry Web Analytics

Este documento explica cómo adaptar el proyecto **Kitcherry Web Analytics** cuando la web corporativa de Kitcherry esté publicada en un servidor de **IONOS**.

Actualmente el proyecto utiliza logs ficticios para poder demostrar el sistema de seguimiento sin depender todavía de tráfico real. Cuando la web esté online, el objetivo será sustituir esos datos simulados por registros reales del servidor o, si el hosting no facilita acceso directo a los logs, por un sistema propio de registro en PHP.

---

## 1. Situación actual del proyecto

En la versión actual se trabaja con logs ficticios generados por:

```text
002-generar-log-demo.py
```

Este archivo crea un log de prueba en:

```text
logs/access_demo.log
```

Ese log simula:

- visitas a la web;
- páginas más consultadas;
- formularios enviados;
- errores 404;
- errores 500;
- actividad por día;
- actividad por hora.

Esto permite demostrar el funcionamiento del panel aunque la web todavía no esté publicada.

---

## 2. Objetivo al publicar la web en IONOS

Cuando la web esté subida a IONOS, Kitcherry Web Analytics podrá trabajar de dos formas:

1. **Usando logs reales del servidor**, si el plan de IONOS permite descargarlos o consultarlos.
2. **Usando un log propio creado desde PHP**, si el hosting no ofrece acceso directo a los logs Apache/Nginx.

La idea es mantener el mismo panel de estadísticas, pero alimentarlo con datos reales.

---

## 3. Uso de logs reales del servidor

Si IONOS permite descargar los logs de acceso de la web, se deberá colocar el archivo dentro de la carpeta:

```text
logs/
```

Con este nombre:

```text
access_real.log
```

La estructura quedaría así:

```text
018-kitcherry-web-analytics/
│
├── logs/
│   ├── access_demo.log
│   └── access_real.log
│
├── config.py
├── 003-analizar-logs.py
└── ...
```

Después habría que abrir el archivo:

```text
config.py
```

Y cambiar:

```python
USAR_LOG_REAL = False
```

por:

```python
USAR_LOG_REAL = True
```

Con ese cambio, el proyecto dejará de leer:

```text
logs/access_demo.log
```

y pasará a leer:

```text
logs/access_real.log
```

---

## 4. Formato esperado del log real

El analizador actual está preparado para un formato parecido al de Apache:

```text
IP - - [fecha] "METODO /pagina HTTP/1.1" CODIGO
```

Ejemplo:

```text
88.14.25.10 - - [14/May/2026:18:20:00 +0200] "GET /index.php HTTP/1.1" 200
88.14.25.10 - - [14/May/2026:18:22:00 +0200] "POST /index.php HTTP/1.1" 200
88.14.25.10 - - [14/May/2026:18:25:00 +0200] "GET /pagina-no-existe.php HTTP/1.1" 404
```

Con este formato el sistema puede calcular:

- visitas totales;
- páginas más visitadas;
- formularios enviados;
- errores HTTP;
- actividad por día;
- actividad por hora.

---

## 5. Si el formato de IONOS es diferente

Puede ocurrir que el archivo de logs descargado desde IONOS tenga un formato distinto al usado actualmente por el proyecto.

En ese caso no sería necesario rehacer todo el sistema. Solo habría que adaptar el archivo:

```text
003-analizar-logs.py
```

Concretamente esta parte:

```python
PATRON_LOG = re.compile(...)
```

Esa expresión regular es la encargada de leer cada línea del log y extraer:

- IP;
- fecha;
- método;
- página;
- protocolo;
- código HTTP.

El resto del proyecto podría seguir funcionando igual.

---

## 6. Configurar la URL real de la web

Cuando Kitcherry esté publicada en IONOS, también se podrá cambiar la URL que revisa el minibot.

En el archivo:

```text
config.py
```

Actualmente se utiliza la URL local:

```python
USAR_URL_REAL = False
```

Cuando la web esté online, se debe cambiar a:

```python
USAR_URL_REAL = True
```

Y después indicar la URL real:

```python
BASE_URL_REAL = "https://tudominio.com/"
```

Por ejemplo:

```python
BASE_URL_REAL = "https://kitcherry.com/"
```

De esta forma, el minibot dejará de revisar la web local en XAMPP y pasará a revisar la web publicada en IONOS.

---

## 7. Flujo recomendado con logs reales

Cuando ya exista el archivo:

```text
logs/access_real.log
```

y esté activado:

```python
USAR_LOG_REAL = True
```

el flujo recomendado será:

```bat
python 001-crear-bbdd.py
python 003-analizar-logs.py
python 004-minibot-kitcherry.py
python 005-generar-graficas.py
python 006-generar-informe.py
```

En este caso no se debería ejecutar:

```bat
python 002-generar-log-demo.py
```

porque ese script sirve solo para generar datos ficticios.

---

## 8. Archivo BAT para entorno real

Para evitar confusiones, cuando la web esté en IONOS se podría crear un archivo nuevo llamado:

```text
000-ejecutar-real-ionos.bat
```

Con este contenido:

```bat
@echo off
chcp 65001 >nul

echo ====================================
echo KITCHERRY WEB ANALYTICS - IONOS
echo ====================================
echo.

echo [1/5] Creando/actualizando base de datos...
python 001-crear-bbdd.py

echo.
echo [2/5] Analizando logs reales...
python 003-analizar-logs.py

echo.
echo [3/5] Ejecutando minibot sobre la web real...
python 004-minibot-kitcherry.py

echo.
echo [4/5] Generando graficas...
python 005-generar-graficas.py

echo.
echo [5/5] Generando informe puente...
python 006-generar-informe.py

echo.
echo ====================================
echo PROCESO FINALIZADO
echo ====================================
echo.
echo Abre el panel:
echo informes/index.php
echo.

pause
```

---

## 9. Alternativa recomendada si IONOS no facilita logs Apache

Si el plan contratado en IONOS no permite acceder directamente a los logs reales del servidor, se puede crear un sistema propio de registro dentro de la web PHP.

Esta alternativa puede ser incluso más útil para el proyecto, porque permite controlar exactamente qué se guarda.

Por ejemplo, se podría crear un archivo en la web llamado:

```text
registrar_visita.php
```

Y guardar eventos en un archivo propio:

```text
logs/kitcherry_web.log
```

Con un formato simple:

```text
2026-05-14 18:20:00 | GET | /index.php | 200
2026-05-14 18:22:00 | POST | /index.php | formulario_contacto
2026-05-14 18:25:00 | GET | /pagina-no-existe.php | 404
```

De esta forma, aunque IONOS no entregue logs completos del servidor, la propia web de Kitcherry podría registrar la actividad necesaria para el panel.

---

## 10. Ejemplo de log propio en PHP

Un ejemplo básico de función PHP para registrar actividad sería:

```php
<?php
function registrarActividadWeb($tipoEvento = "visita", $estado = "200") {
    $carpetaLogs = __DIR__ . "/logs";

    if (!is_dir($carpetaLogs)) {
        mkdir($carpetaLogs, 0755, true);
    }

    $archivoLog = $carpetaLogs . "/kitcherry_web.log";

    $fecha = date("Y-m-d H:i:s");
    $metodo = $_SERVER["REQUEST_METHOD"] ?? "GET";
    $uri = $_SERVER["REQUEST_URI"] ?? "/";
    $ip = $_SERVER["REMOTE_ADDR"] ?? "IP_NO_DISPONIBLE";

    $linea = $fecha . " | " . $ip . " | " . $metodo . " | " . $uri . " | " . $estado . " | " . $tipoEvento . PHP_EOL;

    file_put_contents($archivoLog, $linea, FILE_APPEND);
}
```

Después se podría llamar en las páginas principales:

```php
registrarActividadWeb("visita", "200");
```

Y cuando se envía el formulario:

```php
registrarActividadWeb("formulario_contacto", "200");
```

---

## 11. Ventaja del log propio en PHP

El log propio tendría varias ventajas:

- no depende de que IONOS permita descargar logs Apache;
- permite registrar eventos concretos de Kitcherry;
- permite diferenciar una visita normal de un formulario enviado;
- permite registrar errores propios del formulario;
- permite guardar datos más limpios para el panel;
- permite adaptar mejor el análisis al proyecto.

Por ejemplo, se podrían registrar eventos como:

```text
visita
formulario_contacto
error_formulario
error_smtp
consulta_chatbot
```

---

## 12. Adaptación del analizador para log propio

Si se utiliza el log propio de PHP, habría que adaptar el archivo:

```text
003-analizar-logs.py
```

Para que, además de leer logs tipo Apache, también pueda leer líneas como:

```text
2026-05-14 18:22:00 | 88.14.25.10 | POST | /index.php | 200 | formulario_contacto
```

En ese caso, el sistema podría detectar directamente:

- visitas;
- formularios enviados;
- errores;
- eventos específicos de Kitcherry.

---

## 13. Protección de datos

Cuando el proyecto trabaje con datos reales, hay que tener cuidado con la información registrada.

Los logs pueden incluir:

- direcciones IP;
- páginas visitadas;
- fecha y hora de acceso;
- navegador;
- acciones realizadas en la web.

Para el proyecto, no interesa identificar a usuarios concretos, sino analizar el funcionamiento general de la web. Por eso, si el informe se va a incluir en documentación pública, se recomienda no mostrar IPs completas ni datos personales innecesarios.

Una opción más segura sería anonimizar parcialmente las IPs antes de mostrarlas o no incluirlas en el panel visual.

---

## 14. Recomendación para Kitcherry en IONOS

Para Kitcherry, la mejor estrategia sería esta:

1. Mantener los logs ficticios durante la fase de desarrollo.
2. Cuando la web esté en IONOS, comprobar si el hosting permite descargar logs de acceso.
3. Si existen logs descargables, guardarlos como:

```text
logs/access_real.log
```

4. Si no existen logs descargables, crear un sistema propio de log en PHP.
5. Activar en `config.py`:

```python
USAR_LOG_REAL = True
USAR_URL_REAL = True
```

6. Ejecutar el análisis real.
7. Revisar el panel PHP con datos reales.

---

## 15. Conclusión

Kitcherry Web Analytics está preparado para funcionar en dos fases:

### Fase actual

Uso de logs ficticios para demostrar el funcionamiento del sistema de seguimiento.

### Fase futura en IONOS

Uso de logs reales del servidor o de un log propio generado desde PHP.

De esta forma, el proyecto no se queda solo como una demostración local, sino que queda preparado para convertirse en una herramienta real de seguimiento visual para la web corporativa de Kitcherry.
