<?php
// ==========================================================
// KITCHERRY - API CHAT MEJORADA
// Archivo: api_chat.php
// ==========================================================

header("Content-Type: application/json; charset=UTF-8");

set_time_limit(600);
ini_set("max_execution_time", "600");
ini_set("display_errors", "0");

// ==========================================================
// FUNCIONES AUXILIARES
// ==========================================================

function limpiar_utf8($texto) {
    if ($texto === null) {
        return "";
    }

    if (function_exists("mb_check_encoding") && mb_check_encoding($texto, "UTF-8")) {
        return $texto;
    }

    if (function_exists("mb_convert_encoding")) {
        $convertido = mb_convert_encoding($texto, "UTF-8", "Windows-1252");

        if ($convertido !== false) {
            return $convertido;
        }
    }

    if (function_exists("iconv")) {
        $convertido = @iconv("Windows-1252", "UTF-8//IGNORE", $texto);

        if ($convertido !== false) {
            return $convertido;
        }
    }

    return $texto;
}

function responder_json($ok, $answer, $extra = []) {
    $answer = limpiar_utf8($answer);

    echo json_encode(array_merge([
        "ok" => $ok,
        "answer" => $answer
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    exit;
}

function contiene($texto, $patrones) {
    foreach ($patrones as $patron) {
        if (strpos($texto, $patron) !== false) {
            return true;
        }
    }

    return false;
}

function normalizar_texto($texto) {
    $texto = strtolower($texto);

    $reemplazos = [
        "á" => "a",
        "é" => "e",
        "í" => "i",
        "ó" => "o",
        "ú" => "u",
        "ñ" => "n"
    ];

    return strtr($texto, $reemplazos);
}

function detectar_intencion_contacto($pregunta) {
    $pregunta = normalizar_texto($pregunta);

    $patrones = [
        "quiero contratar",
        "contratar kitcherry",
        "me interesa",
        "quiero una demo",
        "solicitar demo",
        "pedir demo",
        "quiero probar",
        "como puedo contactar",
        "contactar con kitcherry",
        "quiero mas informacion",
        "tengo un restaurante",
        "para mi restaurante",
        "para mi negocio",
        "podemos hablar",
        "presupuesto",
        "solicitar informacion"
    ];

    return contiene($pregunta, $patrones);
}

function extraer_respuesta_util($output) {
    $texto = trim(implode("\n", $output));

    if ($texto === "") {
        return "";
    }

    $lineas = array_values(array_filter(array_map("trim", explode("\n", $texto))));

    if (count($lineas) === 0) {
        return "";
    }

    // Nos quedamos con la última línea útil para evitar líneas tipo "Loading weights..."
    return end($lineas);
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
// RUTAS
// ==========================================================

// api_chat.php está dentro de:
// 004-entrenamiento IA/104-chatbot-web-mejorado
//
// dirname(__DIR__) apunta a:
// 004-entrenamiento IA

$baseDir = dirname(__DIR__);

// ----------------------------------------------------------
// Modo Kitcherry
// ----------------------------------------------------------

$pythonKitcherry = $baseDir
    . DIRECTORY_SEPARATOR . "102-reentrenamiento"
    . DIRECTORY_SEPARATOR . "venv"
    . DIRECTORY_SEPARATOR . "Scripts"
    . DIRECTORY_SEPARATOR . "python.exe";

$scriptKitcherry = $baseDir
    . DIRECTORY_SEPARATOR . "102-reentrenamiento"
    . DIRECTORY_SEPARATOR . "011-inferencia-corporativo-v2-web.py";

// ----------------------------------------------------------
// Modo Restaurante
// ----------------------------------------------------------

$pythonRestaurante = $baseDir
    . DIRECTORY_SEPARATOR . "103-modo-restaurante-kamado"
    . DIRECTORY_SEPARATOR . "venv"
    . DIRECTORY_SEPARATOR . "Scripts"
    . DIRECTORY_SEPARATOR . "python.exe";

$scriptRestaurante = $baseDir
    . DIRECTORY_SEPARATOR . "103-modo-restaurante-kamado"
    . DIRECTORY_SEPARATOR . "015-inferencia-kamado-web.py";

// ==========================================================
// SELECCIONAR MOTOR SEGÚN MODO
// ==========================================================

if ($mode === "restaurante") {
    $pythonPath = $pythonRestaurante;
    $scriptPath = $scriptRestaurante;
    $workingDir = $baseDir . DIRECTORY_SEPARATOR . "103-modo-restaurante-kamado";
} else {
    $pythonPath = $pythonKitcherry;
    $scriptPath = $scriptKitcherry;
    $workingDir = $baseDir . DIRECTORY_SEPARATOR . "102-reentrenamiento";
}

// ==========================================================
// COMPROBAR ARCHIVOS
// ==========================================================

if (!file_exists($pythonPath)) {
    responder_json(false, "No se encontró el Python del entorno virtual para el modo seleccionado.");
}

if (!file_exists($scriptPath)) {
    responder_json(false, "No se encontró el script de inferencia para el modo seleccionado.");
}

if (!is_dir($workingDir)) {
    responder_json(false, "No se encontró la carpeta de trabajo del modo seleccionado.");
}

// ==========================================================
// EJECUTAR PYTHON
// ==========================================================

chdir($workingDir);

$command =
    "set PYTHONIOENCODING=utf-8 && "
    . '"' . $pythonPath . '" '
    . '"' . $scriptPath . '" '
    . escapeshellarg($question)
    . " 2>&1";

$output = [];
$returnCode = 0;

exec($command, $output, $returnCode);

if ($returnCode !== 0) {
    $error = trim(implode("\n", $output));

    responder_json(false, "Error al consultar el modelo: " . $error, [
        "mode" => $mode
    ]);
}

$answer = extraer_respuesta_util($output);

if ($answer === "") {
    responder_json(false, "El motor de IA no devolvió ninguna respuesta.", [
        "mode" => $mode
    ]);
}

$contactIntent = detectar_intencion_contacto($question);

responder_json(true, $answer, [
    "mode" => $mode,
    "contact_intent" => $contactIntent
]);