<?php
// ==========================================================
// KITCHERRY RESERVAS
// Archivo: database/crear_admin.php
// Crea el usuario administrador inicial
// ==========================================================

require_once __DIR__ . '/../includes/db.php';

$nombre = 'Administrador Kitcherry';
$email = 'kitcherryadmin';
$password = 'pierodev';
$rol = 'admin';

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Comprobar si ya existe
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email");
    $stmt->execute([
        ':email' => $email
    ]);

    $usuarioExistente = $stmt->fetch();

    if ($usuarioExistente) {
        echo "El usuario administrador ya existe.";
        exit;
    }

    // Insertar usuario
    $stmt = $pdo->prepare("
        INSERT INTO usuarios (nombre, email, password_hash, rol, activo)
        VALUES (:nombre, :email, :password_hash, :rol, 1)
    ");

    $stmt->execute([
        ':nombre' => $nombre,
        ':email' => $email,
        ':password_hash' => $passwordHash,
        ':rol' => $rol
    ]);

    echo "Usuario administrador creado correctamente.";

} catch (PDOException $e) {
    echo "Error al crear el usuario administrador: " . $e->getMessage();
}