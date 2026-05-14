<?php
// ==========================================================
// KITCHERRY VOICE TASKS
// Archivo: api/actualizar_item.php
// ==========================================================

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../includes/conexion.php";

function responderJson($datos) {
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function limpiarTexto($valor) {
    return trim($valor ?? "");
}

$entrada = json_decode(file_get_contents("php://input"), true);

if (!is_array($entrada)) {
    responderJson([
        "ok" => false,
        "mensaje" => "No se han recibido datos válidos."
    ]);
}

$itemId = (int)($entrada["item_id"] ?? 0);
$campo = limpiarTexto($entrada["campo"] ?? "");
$valor = limpiarTexto($entrada["valor"] ?? "");

$camposPermitidos = ["descripcion", "cantidad"];

if ($itemId <= 0) {
    responderJson([
        "ok" => false,
        "mensaje" => "ID de elemento no válido."
    ]);
}

if (!in_array($campo, $camposPermitidos)) {
    responderJson([
        "ok" => false,
        "mensaje" => "Campo no permitido."
    ]);
}

if ($campo === "descripcion" && $valor === "") {
    responderJson([
        "ok" => false,
        "mensaje" => "La descripción no puede quedar vacía."
    ]);
}

try {
    $sql = "UPDATE items_lista SET {$campo} = :valor WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ":valor" => $valor,
        ":id" => $itemId
    ]);

    responderJson([
        "ok" => true,
        "mensaje" => "Elemento actualizado correctamente.",
        "valor" => $valor
    ]);

} catch (PDOException $e) {
    responderJson([
        "ok" => false,
        "mensaje" => $e->getMessage()
    ]);
}