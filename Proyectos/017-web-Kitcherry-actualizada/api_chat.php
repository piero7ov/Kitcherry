<?php
// ==========================================================
// KITCHERRY - API CHAT OPTIMIZADA CON FLASK
// Archivo: api_chat.php
// ==========================================================

header("Content-Type: application/json; charset=UTF-8");

set_time_limit(600);
ini_set("max_execution_time", "600");
ini_set("display_errors", "0");


// ==========================================================
// FUNCIONES AUXILIARES
// ==========================================================

function responder_json($ok, $answer, $extra = []) {
    echo json_encode(array_merge([
        "ok" => $ok,
        "answer" => $answer
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    exit;
}


function llamar_flask($url, $payload) {
    $jsonPayload = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($jsonPayload === false) {
        return [
            "ok" => false,
            "answer" => "No se pudo preparar la petición para Flask."
        ];
    }

    // ------------------------------------------------------
    // Opción 1: cURL
    // ------------------------------------------------------

    if (function_exists("curl_init")) {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json; charset=UTF-8",
                "Content-Length: " . strlen($jsonPayload)
            ],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 600,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);

            return [
                "ok" => false,
                "answer" => "No se pudo conectar con el servidor Flask. Ejecuta primero servidor_flask/app.py. Detalle: " . $error
            ];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if (!is_array($data)) {
            return [
                "ok" => false,
                "answer" => "Flask no devolvió JSON válido.",
                "raw" => $response
            ];
        }

        if ($httpCode >= 400 && !isset($data["ok"])) {
            return [
                "ok" => false,
                "answer" => "Flask devolvió un error HTTP: " . $httpCode
            ];
        }

        return $data;
    }

    // ------------------------------------------------------
    // Opción 2: file_get_contents
    // ------------------------------------------------------

    $context = stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => "Content-Type: application/json; charset=UTF-8\r\n",
            "content" => $jsonPayload,
            "timeout" => 600
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return [
            "ok" => false,
            "answer" => "No se pudo conectar con el servidor Flask. Ejecuta primero servidor_flask/app.py."
        ];
    }

    $data = json_decode($response, true);

    if (!is_array($data)) {
        return [
            "ok" => false,
            "answer" => "Flask no devolvió JSON válido.",
            "raw" => $response
        ];
    }

    return $data;
}


// ==========================================================
// VALIDAR PETICIÓN
// ==========================================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder_json(false, "Método no permitido.");
}

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    responder_json(false, "No se recibió un JSON válido.");
}

$question = trim($data["question"] ?? "");
$mode = trim($data["mode"] ?? "kitcherry");

if ($question === "") {
    responder_json(false, "Escribe una pregunta para continuar.");
}

$allowedModes = ["kitcherry", "restaurante"];

if (!in_array($mode, $allowedModes, true)) {
    $mode = "kitcherry";
}


// ==========================================================
// LLAMAR A FLASK
// ==========================================================

$flaskUrl = "http://127.0.0.1:5005/chat";

$response = llamar_flask($flaskUrl, [
    "mode" => $mode,
    "question" => $question
]);

if (!is_array($response)) {
    responder_json(false, "Respuesta no válida del servidor Flask.");
}

responder_json(
    $response["ok"] ?? false,
    $response["answer"] ?? "No se recibió respuesta del servidor Flask.",
    [
        "mode" => $response["mode"] ?? $mode,
        "contact_intent" => $response["contact_intent"] ?? false
    ]
);