<?php
// ==========================================================
// KITCHERRY - CABECERA
// Archivo: includes/header.php
// ==========================================================

$tituloPagina = $tituloPagina ?? "Kitcherry | Herramientas de software para hostelería";
$descripcionPagina = $descripcionPagina ?? "Kitcherry ofrece herramientas de software para hostelería que utilizan IA como recurso práctico para automatizar consultas, organizar reservas y mejorar la operativa diaria.";

$paginaActual = basename($_SERVER["PHP_SELF"]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <title><?php echo e($tituloPagina); ?></title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description" content="<?php echo e($descripcionPagina); ?>">

    <link rel="stylesheet" href="assets/css/style.css">

    <script src="assets/js/main.js" defer></script>
</head>

<body id="inicio">

    <header class="cabecera" id="cabecera">
        <div class="contenedor cabecera-contenido">

            <a href="index.php" class="marca" aria-label="Ir al inicio de Kitcherry">
                <?php if ($logoExiste): ?>
                    <img src="<?php echo e($logoPath); ?>" alt="Logotipo de Kitcherry" class="marca-logo">
                <?php endif; ?>

                <div class="marca-texto">
                    <strong>
                        <span class="kit">KIT</span><span class="cherry">CHERRY</span>
                    </strong>
                    <span>Herramientas para hostelería</span>
                </div>
            </a>

            <input type="checkbox" id="menu-toggle" class="menu-toggle">
            <label for="menu-toggle" class="menu-label">Menú</label>

            <nav class="menu" id="menu-principal">
                <a href="nosotros.php" class="<?php echo $paginaActual === 'nosotros.php' ? 'activo' : ''; ?>">
                    Qué es Kitcherry
                </a>

                <a href="index.php#servicios">
                    Soluciones
                </a>

                <a href="index.php#ia-practica">
                    IA práctica
                </a>

                <a href="index.php#contacto">
                    Contacto
                </a>
            </nav>

        </div>
    </header>