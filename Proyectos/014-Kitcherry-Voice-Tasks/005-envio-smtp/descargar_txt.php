<?php
// ==========================================================
// KITCHERRY VOICE TASKS
// Archivo: descargar_txt.php
// ==========================================================

require_once __DIR__ . "/includes/conexion.php";
require_once __DIR__ . "/includes/generador_txt.php";

$id = (int)($_GET["id"] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo "ID de lista no válido.";
    exit;
}

try {
    $resultado = guardarTxtLista($pdo, $id);

    if (!$resultado["ok"]) {
        http_response_code(404);
        echo "Lista no encontrada.";
        exit;
    }

    $nombreArchivo = generarNombreArchivoTxt($resultado["lista"]);

    header("Content-Type: text/plain; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"" . $nombreArchivo . "\"");

    echo $resultado["contenido_txt"];
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo "Error al preparar la descarga.";
    exit;
}