<?php
// ==========================================================
// KITCHERRY - API CHAT
// Archivo: api_chat.php
// ==========================================================

header("Content-Type: application/json; charset=UTF-8");

set_time_limit(600);
ini_set("max_execution_time", "600");
ini_set("display_errors", "0");

function limpiar_utf8($texto) {
    if ($texto === null) {
        return "";
    }

    if (mb_check_encoding($texto, "UTF-8")) {
        return $texto;
    }

    $convertido = mb_convert_encoding($texto, "UTF-8", "Windows-1252");

    if ($convertido === false) {
        return utf8_encode($texto);
    }

    return $convertido;
}

function responder_json($ok, $answer) {
    $answer = limpiar_utf8($answer);

    echo json_encode([
        "ok" => $ok,
        "answer" => $answer
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder_json(false, "Método no permitido.");
}

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    responder_json(false, "No se recibió un JSON válido.");
}

$question = trim($data["question"] ?? "");

if ($question === "") {
    responder_json(false, "Escribe una pregunta para continuar.");
}

// ==========================================================
// RUTAS
// ==========================================================

$baseDir = dirname(__DIR__);

$pythonPath = $baseDir
    . DIRECTORY_SEPARATOR . "venv"
    . DIRECTORY_SEPARATOR . "Scripts"
    . DIRECTORY_SEPARATOR . "python.exe";

$scriptPath = $baseDir
    . DIRECTORY_SEPARATOR . "007-inferencia_web.py";

if (!file_exists($pythonPath)) {
    responder_json(false, "No se encontró el Python del entorno virtual.");
}

if (!file_exists($scriptPath)) {
    responder_json(false, "No se encontró el script de inferencia web.");
}

// ==========================================================
// EJECUTAR PYTHON
// ==========================================================

chdir($baseDir);

$command =
    "set PYTHONIOENCODING=utf-8 && "
    . '"' . $pythonPath . '" '
    . '"' . $scriptPath . '" '
    . escapeshellarg($question)
    . " 2>&1";

$output = [];
$returnCode = 0;

exec($command, $output, $returnCode);

$answer = trim(implode("\n", $output));

if ($returnCode !== 0) {
    responder_json(false, "Error al consultar el modelo: " . $answer);
}

if ($answer === "") {
    responder_json(false, "El motor de IA no devolvió ninguna respuesta.");
}

// Nos quedamos con la última línea útil porque Python puede imprimir carga del modelo
$lines = array_values(array_filter(array_map("trim", explode("\n", $answer))));

if (count($lines) > 0) {
    $answer = end($lines);
}

responder_json(true, $answer);