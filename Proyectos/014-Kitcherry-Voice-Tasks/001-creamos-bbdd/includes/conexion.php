<?php
// ==========================================================
// KITCHERRY VOICE TASKS
// Archivo: includes/conexion.php
// ==========================================================

$rutaCarpetaBD = dirname(__DIR__) . DIRECTORY_SEPARATOR . "db";
$rutaBD = $rutaCarpetaBD . DIRECTORY_SEPARATOR . "kitcherry_voice_tasks.sqlite";

// Si no existe la carpeta db, la creamos
if (!is_dir($rutaCarpetaBD)) {
    mkdir($rutaCarpetaBD, 0777, true);
}

try {
    // Conexión a SQLite
    $pdo = new PDO("sqlite:" . $rutaBD);

    // Mostrar errores de PDO
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Devolver resultados como array asociativo
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Activar claves foráneas en SQLite
    $pdo->exec("PRAGMA foreign_keys = ON;");

} catch (PDOException $e) {
    die("Error de conexión con SQLite: " . $e->getMessage());
}