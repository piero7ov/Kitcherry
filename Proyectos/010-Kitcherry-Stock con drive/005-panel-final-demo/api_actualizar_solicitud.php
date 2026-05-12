<?php
// ==========================================================
// KITCHERRY STOCK
// Archivo: api_actualizar_solicitud.php
// Actualiza el estado de una solicitud usando Google Apps Script
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

$idSolicitud = trim($datos["id_solicitud"] ?? "");
$estado = trim($datos["estado"] ?? "");

if ($idSolicitud === "") {
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se ha recibido el ID de la solicitud."
    ]);
    exit;
}

if ($estado === "") {
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se ha recibido el nuevo estado."
    ]);
    exit;
}

$estadosPermitidos = [
    "Pendiente",
    "Enviada",
    "Gestionada",
    "Cancelada"
];

if (!in_array($estado, $estadosPermitidos)) {
    echo json_encode([
        "ok" => false,
        "mensaje" => "Estado no permitido."
    ]);
    exit;
}

$payload = [
    "accion" => "actualizar_estado_solicitud",
    "id_solicitud" => $idSolicitud,
    "estado" => $estado
];

$respuestaAppsScript = enviarDatosAAppsScript(
    $URL_APPS_SCRIPT_SOLICITUDES,
    json_encode($payload)
);

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