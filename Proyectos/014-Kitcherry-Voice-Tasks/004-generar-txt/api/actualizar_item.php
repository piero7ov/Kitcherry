<?php
// ==========================================================
// KITCHERRY VOICE TASKS
// Archivo: api/actualizar_item.php
// ==========================================================

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../includes/conexion.php";
require_once __DIR__ . "/../includes/generador_txt.php";

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
    $stmtListaId = $pdo->prepare("
        SELECT lista_id
        FROM items_lista
        WHERE id = :id
    ");

    $stmtListaId->execute([
        ":id" => $itemId
    ]);

    $itemActual = $stmtListaId->fetch();

    if (!$itemActual) {
        responderJson([
            "ok" => false,
            "mensaje" => "Elemento no encontrado."
        ]);
    }

    $listaId = (int)$itemActual["lista_id"];

    $sql = "UPDATE items_lista SET {$campo} = :valor WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ":valor" => $valor,
        ":id" => $itemId
    ]);

    $resultadoTxt = guardarTxtLista($pdo, $listaId);

    responderJson([
        "ok" => true,
        "mensaje" => "Elemento actualizado correctamente.",
        "valor" => $valor,
        "lista_id" => $listaId,
        "contenido_txt" => $resultadoTxt["contenido_txt"] ?? ""
    ]);

} catch (PDOException $e) {
    responderJson([
        "ok" => false,
        "mensaje" => $e->getMessage()
    ]);
}