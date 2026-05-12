<?php
// ==========================================================
// KITCHERRY STOCK
// Versión: 001 - Conexión con Google Drive como base de datos
// Archivo: funciones.php
// ==========================================================

/**
 * Descarga el contenido de un CSV desde una URL.
 * Primero intenta usar file_get_contents().
 * Si falla, intenta usar cURL como alternativa.
 */
function descargarCsvDesdeUrl($url)
{
    // Intento 1: file_get_contents
    $contenido = @file_get_contents($url);

    if ($contenido !== false && trim($contenido) !== "") {
        return $contenido;
    }

    // Intento 2: cURL, por si file_get_contents falla.
    if (function_exists("curl_init")) {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $contenido = curl_exec($curl);
        $error = curl_error($curl);

        curl_close($curl);

        if ($contenido !== false && trim($contenido) !== "") {
            return $contenido;
        }

        throw new Exception("No se pudo descargar el CSV desde Drive. Error cURL: " . $error);
    }

    throw new Exception("No se pudo leer el CSV. Revisa la URL publicada o la configuración de PHP.");
}

/**
 * Convierte el contenido CSV en un array asociativo.
 * La primera fila del CSV se usa como cabecera.
 */
function convertirCsvEnArray($contenidoCsv)
{
    $lineas = preg_split("/\r\n|\n|\r/", trim($contenidoCsv));

    if (!$lineas || count($lineas) < 2) {
        return [];
    }

    // Primera fila: nombres de columnas.
    $cabeceras = str_getcsv($lineas[0]);

    // Limpiamos las cabeceras por seguridad.
    $cabeceras = array_map(function ($cabecera) {
        return trim($cabecera);
    }, $cabeceras);

    $datos = [];

    // Recorremos el resto de filas.
    for ($i = 1; $i < count($lineas); $i++) {
        $fila = str_getcsv($lineas[$i]);

        // Evita filas vacías.
        if (count($fila) === 1 && trim($fila[0]) === "") {
            continue;
        }

        $registro = [];

        foreach ($cabeceras as $indice => $cabecera) {
            $registro[$cabecera] = isset($fila[$indice]) ? trim($fila[$indice]) : "";
        }

        $datos[] = $registro;
    }

    return $datos;
}

/**
 * Carga los productos desde la URL CSV de Google Drive.
 */
function obtenerProductosDesdeDrive($urlCsv)
{
    $contenidoCsv = descargarCsvDesdeUrl($urlCsv);
    return convertirCsvEnArray($contenidoCsv);
}

/**
 * Limpia texto antes de mostrarlo en HTML.
 */
function e($texto)
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, "UTF-8");
}

/**
 * Devuelve una clase CSS según el estado del stock.
 */
function claseEstadoStock($estado)
{
    $estado = mb_strtolower(trim($estado));

    if ($estado === "stock bajo") {
        return "estado-bajo";
    }

    if ($estado === "sobrestock") {
        return "estado-sobrestock";
    }

    if ($estado === "correcto") {
        return "estado-correcto";
    }

    return "estado-desconocido";
}

/**
 * Convierte un número a formato moneda.
 */
function formatoEuros($valor)
{
    if ($valor === "" || !is_numeric($valor)) {
        return "-";
    }

    return number_format((float)$valor, 2, ",", ".") . " €";
}

/**
 * Calcula datos de resumen para el panel superior.
 */
function calcularResumenStock($productos)
{
    $resumen = [
        "total" => 0,
        "correcto" => 0,
        "stock_bajo" => 0,
        "sobrestock" => 0,
        "inactivos" => 0,
    ];

    foreach ($productos as $producto) {
        $resumen["total"]++;

        $activo = mb_strtolower(trim($producto["activo"] ?? ""));

        if ($activo === "no") {
            $resumen["inactivos"]++;
        }

        $estado = mb_strtolower(trim($producto["estado_stock"] ?? ""));

        if ($estado === "correcto") {
            $resumen["correcto"]++;
        } elseif ($estado === "stock bajo") {
            $resumen["stock_bajo"]++;
        } elseif ($estado === "sobrestock") {
            $resumen["sobrestock"]++;
        }
    }

    return $resumen;
}