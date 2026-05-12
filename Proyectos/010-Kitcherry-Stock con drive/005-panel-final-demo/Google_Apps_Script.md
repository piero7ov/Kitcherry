# Google Apps Script en Kitcherry Stock

En esta versión se ha utilizado Google Apps Script para permitir que la aplicación web pueda guardar solicitudes internas directamente en Google Sheets.

Hasta la versión anterior, la solicitud solo se generaba en pantalla. En esta versión, cuando el usuario añade productos, rellena los datos y pulsa **Generar solicitud**, la información se envía desde la web a un archivo PHP y después a Google Apps Script.

Apps Script recibe los datos y los inserta como una nueva fila dentro de la pestaña `solicitudes` del Google Sheets.

## Flujo de funcionamiento

```text
Kitcherry Stock
        ↓
JavaScript recoge los datos de la solicitud
        ↓
PHP envía los datos a Apps Script
        ↓
Apps Script recibe el JSON
        ↓
Google Sheets guarda la solicitud en la pestaña solicitudes
```

## Código usado en Google Apps Script

```javascript
const NOMBRE_HOJA_SOLICITUDES = "solicitudes";

function doGet() {
  const respuesta = {
    ok: true,
    mensaje: "Kitcherry Stock Apps Script activo"
  };

  return ContentService
    .createTextOutput(JSON.stringify(respuesta))
    .setMimeType(ContentService.MimeType.JSON);
}

function doPost(e) {
  try {
    const hoja = SpreadsheetApp
      .getActiveSpreadsheet()
      .getSheetByName(NOMBRE_HOJA_SOLICITUDES);

    if (!hoja) {
      throw new Error("No existe la pestaña solicitudes.");
    }

    if (!e.postData || !e.postData.contents) {
      throw new Error("No se han recibido datos.");
    }

    const datos = JSON.parse(e.postData.contents);

    const idSolicitud = datos.id_solicitud || generarIdSolicitud();
    const fecha = datos.fecha || new Date();
    const empleado = datos.empleado || "";
    const zona = datos.zona || "";
    const prioridad = datos.prioridad || "Normal";
    const estado = datos.estado || "Pendiente";
    const totalProductos = datos.total_productos || 0;
    const totalUnidades = datos.total_unidades || 0;
    const costeEstimado = datos.coste_estimado || 0;
    const observaciones = datos.observaciones || "";
    const productos = datos.productos || "";

    hoja.appendRow([
      idSolicitud,
      fecha,
      empleado,
      zona,
      prioridad,
      estado,
      totalProductos,
      totalUnidades,
      costeEstimado,
      observaciones,
      productos
    ]);

    const respuesta = {
      ok: true,
      mensaje: "Solicitud guardada correctamente.",
      id_solicitud: idSolicitud
    };

    return ContentService
      .createTextOutput(JSON.stringify(respuesta))
      .setMimeType(ContentService.MimeType.JSON);

  } catch (error) {
    const respuesta = {
      ok: false,
      mensaje: error.message
    };

    return ContentService
      .createTextOutput(JSON.stringify(respuesta))
      .setMimeType(ContentService.MimeType.JSON);
  }
}

function generarIdSolicitud() {
  const fecha = Utilities.formatDate(
    new Date(),
    Session.getScriptTimeZone(),
    "yyyyMMdd-HHmmss"
  );

  const aleatorio = Math.floor(Math.random() * 900) + 100;

  return "SOL-" + fecha + "-" + aleatorio;
}
```

## Resultado conseguido

Con este script, Kitcherry Stock ya no solo lee productos desde Google Sheets, sino que también puede registrar nuevas solicitudes internas dentro de Drive.

La pestaña `solicitudes` actúa como una tabla de base de datos donde se guardan los datos principales de cada solicitud:

```text
id_solicitud
fecha
empleado
zona
prioridad
estado
total_productos
total_unidades
coste_estimado
observaciones
productos
```