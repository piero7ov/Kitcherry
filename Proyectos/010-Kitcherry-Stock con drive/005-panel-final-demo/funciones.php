<?php
// ==========================================================
// KITCHERRY STOCK
// Archivo: funciones.php
// Funciones auxiliares del sistema
// ==========================================================

function descargarCsvDesdeUrl($url)
{
    $contenido = @file_get_contents($url);

    if ($contenido !== false && trim($contenido) !== "") {
        return $contenido;
    }

    if (function_exists("curl_init")) {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_USERAGENT => "Kitcherry-Stock-XAMPP"
        ]);

        $contenido = curl_exec($curl);
        $error = curl_error($curl);

        curl_close($curl);

        if ($contenido !== false && trim($contenido) !== "") {
            return $contenido;
        }

        throw new Exception("No se pudo descargar el CSV desde Google Drive. Error cURL: " . $error);
    }

    throw new Exception("No se pudo leer el CSV. Revisa la URL publicada o la configuración de PHP.");
}

function convertirCsvEnArray($contenidoCsv)
{
    $temporal = fopen("php://temp", "r+");

    fwrite($temporal, $contenidoCsv);
    rewind($temporal);

    $cabeceras = fgetcsv($temporal);

    if (!$cabeceras) {
        fclose($temporal);
        return [];
    }

    $cabeceras = array_map(function ($cabecera) {
        return trim($cabecera);
    }, $cabeceras);

    $datos = [];

    while (($fila = fgetcsv($temporal)) !== false) {
        if (count($fila) === 1 && trim($fila[0]) === "") {
            continue;
        }

        $registro = [];

        foreach ($cabeceras as $indice => $cabecera) {
            $registro[$cabecera] = isset($fila[$indice]) ? trim($fila[$indice]) : "";
        }

        $datos[] = $registro;
    }

    fclose($temporal);

    return $datos;
}

function obtenerProductosDesdeDrive($urlCsv)
{
    $contenidoCsv = descargarCsvDesdeUrl($urlCsv);
    return convertirCsvEnArray($contenidoCsv);
}

function obtenerSolicitudesDesdeDrive($urlCsv)
{
    $contenidoCsv = descargarCsvDesdeUrl($urlCsv);
    $solicitudes = convertirCsvEnArray($contenidoCsv);

    return array_reverse($solicitudes);
}

function obtenerProveedoresDesdeDrive($urlCsv)
{
    $contenidoCsv = descargarCsvDesdeUrl($urlCsv);
    return convertirCsvEnArray($contenidoCsv);
}

function crearMapaProveedores($proveedores)
{
    $mapa = [];

    foreach ($proveedores as $proveedor) {
        $id = trim($proveedor["id_proveedor"] ?? "");

        if ($id !== "") {
            $mapa[$id] = $proveedor;
        }
    }

    return $mapa;
}

function enriquecerProductosConProveedores($productos, $mapaProveedores)
{
    foreach ($productos as $indice => $producto) {
        $idProveedor = $producto["id_proveedor"] ?? "";

        $productos[$indice]["nombre_proveedor"] = $mapaProveedores[$idProveedor]["nombre_proveedor"] ?? $idProveedor;
        $productos[$indice]["tipo_proveedor"] = $mapaProveedores[$idProveedor]["tipo"] ?? "";
        $productos[$indice]["telefono_proveedor"] = $mapaProveedores[$idProveedor]["telefono"] ?? "";
        $productos[$indice]["email_proveedor"] = $mapaProveedores[$idProveedor]["email"] ?? "";
    }

    return $productos;
}

function e($texto)
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, "UTF-8");
}

function formatoEuros($valor)
{
    if ($valor === "" || !is_numeric($valor)) {
        return "-";
    }

    return number_format((float)$valor, 2, ",", ".") . " €";
}

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

function clasePrioridad($prioridad)
{
    $prioridad = mb_strtolower(trim($prioridad));

    if ($prioridad === "urgente") {
        return "prioridad-urgente";
    }

    if ($prioridad === "alta") {
        return "prioridad-alta";
    }

    return "prioridad-normal";
}

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
        $estado = mb_strtolower(trim($producto["estado_stock"] ?? ""));

        if ($activo === "no") {
            $resumen["inactivos"]++;
        }

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

function calcularResumenSolicitudes($solicitudes)
{
    $resumen = [
        "total" => 0,
        "pendientes" => 0,
        "urgentes" => 0,
        "coste_estimado" => 0,
    ];

    foreach ($solicitudes as $solicitud) {
        $resumen["total"]++;

        $estado = mb_strtolower(trim($solicitud["estado"] ?? ""));
        $prioridad = mb_strtolower(trim($solicitud["prioridad"] ?? ""));
        $coste = $solicitud["coste_estimado"] ?? 0;

        if ($estado === "pendiente") {
            $resumen["pendientes"]++;
        }

        if ($prioridad === "urgente") {
            $resumen["urgentes"]++;
        }

        if (is_numeric($coste)) {
            $resumen["coste_estimado"] += (float)$coste;
        }
    }

    return $resumen;
}

function obtenerCategoriasUnicas($productos)
{
    $categorias = [];

    foreach ($productos as $producto) {
        $categoria = trim($producto["categoria"] ?? "");

        if ($categoria !== "" && !in_array($categoria, $categorias)) {
            $categorias[] = $categoria;
        }
    }

    sort($categorias);

    return $categorias;
}

function obtenerProveedoresUnicosProductos($productos)
{
    $proveedores = [];

    foreach ($productos as $producto) {
        $proveedor = trim($producto["nombre_proveedor"] ?? "");

        if ($proveedor !== "" && !in_array($proveedor, $proveedores)) {
            $proveedores[] = $proveedor;
        }
    }

    sort($proveedores);

    return $proveedores;
}

function obtenerProductosStockBajo($productos)
{
    $stockBajo = [];

    foreach ($productos as $producto) {
        $estado = mb_strtolower(trim($producto["estado_stock"] ?? ""));

        if ($estado === "stock bajo") {
            $stockBajo[] = $producto;
        }
    }

    return $stockBajo;
}