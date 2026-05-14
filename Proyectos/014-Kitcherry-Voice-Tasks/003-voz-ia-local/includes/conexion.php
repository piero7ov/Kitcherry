<?php
// ==========================================================
// KITCHERRY VOICE TASKS
// Archivo: includes/conexion.php
// ==========================================================

$rutaCarpetaBD = dirname(__DIR__) . DIRECTORY_SEPARATOR . "db";
$rutaBD = $rutaCarpetaBD . DIRECTORY_SEPARATOR . "kitcherry_voice_tasks.sqlite";

if (!is_dir($rutaCarpetaBD)) {
    mkdir($rutaCarpetaBD, 0777, true);
}

try {
    $pdo = new PDO("sqlite:" . $rutaBD);

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("PRAGMA foreign_keys = ON;");

} catch (PDOException $e) {
    die("Error de conexión con SQLite: " . $e->getMessage());
}