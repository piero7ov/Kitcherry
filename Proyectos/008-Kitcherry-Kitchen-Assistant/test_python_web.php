<?php
header("Content-Type: text/plain; charset=utf-8");

$pythonPath = __DIR__ . DIRECTORY_SEPARATOR . "venv" . DIRECTORY_SEPARATOR . "Scripts" . DIRECTORY_SEPARATOR . "python.exe";
$scriptPath = __DIR__ . DIRECTORY_SEPARATOR . "017-inferencia_web.py";

echo "Python:\n$pythonPath\n\n";
echo "Script:\n$scriptPath\n\n";

echo "Existe Python: " . (file_exists($pythonPath) ? "SI" : "NO") . "\n";
echo "Existe Script: " . (file_exists($scriptPath) ? "SI" : "NO") . "\n\n";

$comando = '"' . $pythonPath . '" "' . $scriptPath . '"';

$descriptores = [
    0 => ["pipe", "r"],
    1 => ["pipe", "w"],
    2 => ["pipe", "w"]
];

$proceso = proc_open($comando, $descriptores, $pipes, __DIR__);

if (!is_resource($proceso)) {
    echo "No se pudo iniciar proc_open";
    exit;
}

$entrada = json_encode([
    "mensaje" => "hola como estas"
], JSON_UNESCAPED_UNICODE);

fwrite($pipes[0], $entrada);
fclose($pipes[0]);

$salida = stream_get_contents($pipes[1]);
fclose($pipes[1]);

$error = stream_get_contents($pipes[2]);
fclose($pipes[2]);

$codigo = proc_close($proceso);

echo "CODIGO:\n$codigo\n\n";
echo "SALIDA:\n$salida\n\n";
echo "ERROR:\n$error\n";
?>