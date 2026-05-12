<?php
// ==========================================================
// KITCHERRY STOCK
// Archivo: api_proveedores.php
// Gestiona creación, edición y desactivación de proveedores
// usando Google Apps Script
// ==========================================================

require_once "config.php";

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "ok" => false,
        "mensaje" => "Método no permitido."
    ]);
    exit;
}

$entrada = file_get_contents("php://input");

if (!$entrada || trim($entrada) === "") {
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se han recibido datos."
    ]);
    exit;
}

$datos = json_decode($entrada, true);

if (!is_array($datos)) {
    echo json_encode([
        "ok" => false,
        "mensaje" => "Los datos recibidos no tienen formato JSON válido."
    ]);
    exit;
}

$accion = $datos["accion"] ?? "";

if ($accion === "") {
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se ha indicado ninguna acción."
    ]);
    exit;
}

$accionesPermitidas = [
    "crear_proveedor",
    "editar_proveedor",
    "desactivar_proveedor"
];

if (!in_array($accion, $accionesPermitidas)) {
    echo json_encode([
        "ok" => false,
        "mensaje" => "Acción no permitida."
    ]);
    exit;
}

$respuestaAppsScript = enviarDatosAAppsScript($URL_APPS_SCRIPT_SOLICITUDES, $entrada);

echo $respuestaAppsScript;
exit;

/**
 * Envía los datos a Google Apps Script.
 */
function enviarDatosAAppsScript($url, $json)
{
    if (!function_exists("curl_init")) {
        return json_encode([
            "ok" => false,
            "mensaje" => "cURL no está disponible en PHP."
        ]);
    }

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ],
        CURLOPT_USERAGENT => "Kitcherry-Stock-XAMPP"
    ]);

    $respuesta = curl_exec($curl);
    $errorCurl = curl_error($curl);
    $codigoHttp = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    if ($respuesta === false || trim($respuesta) === "") {
        return json_encode([
            "ok" => false,
            "mensaje" => "No se pudo contactar con Apps Script. " . $errorCurl
        ]);
    }

    if ($codigoHttp < 200 || $codigoHttp >= 300) {
        return json_encode([
            "ok" => false,
            "mensaje" => "Apps Script respondió con código HTTP " . $codigoHttp,
            "respuesta" => $respuesta
        ]);
    }

    $jsonRespuesta = json_decode($respuesta, true);

    if (!is_array($jsonRespuesta)) {
        return json_encode([
            "ok" => false,
            "mensaje" => "Apps Script respondió, pero no devolvió JSON válido.",
            "respuesta" => $respuesta
        ]);
    }

    return json_encode($jsonRespuesta);
}