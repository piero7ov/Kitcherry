<?php
// ==========================================================
// KITCHERRY RESERVAS
// Archivo: includes/db.php
// Conexión a SQLite
// ==========================================================

$databasePath = __DIR__ . '/../database/kitcherry-reservas.db';

try {
    $pdo = new PDO('sqlite:' . $databasePath);

    // Mostrar errores de PDO como excepciones
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Devolver resultados como arrays asociativos
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Activar claves foráneas en SQLite
    $pdo->exec("PRAGMA foreign_keys = ON");

} catch (PDOException $e) {
    die("Error al conectar con la base de datos: " . $e->getMessage());
}