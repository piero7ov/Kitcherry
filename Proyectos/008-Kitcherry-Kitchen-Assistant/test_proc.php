<?php
header("Content-Type: text/plain; charset=utf-8");

echo "disable_functions:\n";
echo ini_get("disable_functions") . "\n\n";

echo "Probando proc_open:\n";

$descriptores = [
    0 => ["pipe", "r"],
    1 => ["pipe", "w"],
    2 => ["pipe", "w"]
];

$proceso = proc_open("cmd /c echo FUNCIONA", $descriptores, $pipes, __DIR__);

if (!is_resource($proceso)) {
    echo "proc_open NO funciona";
    exit;
}

fclose($pipes[0]);

$salida = stream_get_contents($pipes[1]);
fclose($pipes[1]);

$error = stream_get_contents($pipes[2]);
fclose($pipes[2]);

$codigo = proc_close($proceso);

echo "Salida: " . $salida . "\n";
echo "Error: " . $error . "\n";
echo "Código: " . $codigo . "\n";
?>