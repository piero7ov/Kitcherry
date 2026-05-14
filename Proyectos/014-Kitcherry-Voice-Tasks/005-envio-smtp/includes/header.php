<?php
// ==========================================================
// KITCHERRY VOICE TASKS
// Archivo: includes/header.php
// ==========================================================

if (!isset($tituloPagina)) {
    $tituloPagina = "Kitcherry Voice Tasks";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($tituloPagina); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="header">
    <div class="contenedor header-contenido">

        <a href="index.php" class="marca">
            <img src="assets/img/logo.png" alt="Logo Kitcherry" class="logo">

            <div class="marca-texto">
                <h1>
                    <span class="kit">KIT</span><span class="cherry">CHERRY</span>
                </h1>
                <p>Voice Tasks</p>
            </div>
        </a>

        <nav class="nav-principal">
            <a href="index.php">Panel</a>
            <a href="personal.php">Personal</a>
            <a href="nueva_lista.php">Nueva lista</a>
        </nav>

    </div>
</header>

<main class="contenedor">