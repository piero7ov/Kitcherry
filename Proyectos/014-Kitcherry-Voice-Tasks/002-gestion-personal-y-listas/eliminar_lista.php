<?php
// ==========================================================
// KITCHERRY VOICE TASKS
// Archivo: eliminar_lista.php
// ==========================================================

require_once __DIR__ . "/includes/conexion.php";

$id = (int)($_GET["id"] ?? 0);

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM listas WHERE id = :id");
        $stmt->execute([":id" => $id]);
    } catch (PDOException $e) {
        // En esta fase redirigimos igualmente al panel
    }
}

header("Location: index.php");
exit;