<?php
// ==========================================================
// KITCHERRY STOCK
// Archivo: index.php
// Sistema de stock conectado a Google Drive como base de datos
// ==========================================================

require_once "config.php";
require_once "funciones.php";

$error = null;
$productos = [];
$resumen = [
    "total" => 0,
    "correcto" => 0,
    "stock_bajo" => 0,
    "sobrestock" => 0,
    "inactivos" => 0,
];

try {
    // Cargamos los productos directamente desde el CSV publicado en Google Drive.
    $productos = obtenerProductosDesdeDrive($CSV_PRODUCTOS);

    // Calculamos resumen general.
    $resumen = calcularResumenStock($productos);
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kitcherry Stock</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="cabecera">
    <div class="contenedor cabecera-contenido">

        <div class="marca">
            <img src="assets/img/logo.png" alt="Logotipo Kitcherry" class="logo">

            <div class="marca-texto">
                <div class="nombre-kitcherry">
                    <span class="kit">KIT</span><span class="cherry">CHERRY</span>
                </div>

                <div class="nombre-stock">
                    Stock
                </div>
            </div>
        </div>

        <div class="cabecera-info">
            <h1>Gestión de stock para hostelería</h1>
            <p>
                Panel de consulta de productos conectado a Google Drive como base de datos.
            </p>
        </div>

    </div>
</header>

<main class="contenedor">

    <?php if ($error): ?>

        <section class="bloque error">
            <h2>Error al cargar los datos</h2>
            <p><?php echo e($error); ?></p>

            <p class="ayuda-error">
                Revisa que la hoja esté publicada como CSV y que la URL termine en
                <strong>output=csv</strong>.
            </p>
        </section>

    <?php else: ?>

        <section class="resumen-grid">
            <article class="tarjeta-resumen">
                <span class="numero"><?php echo e($resumen["total"]); ?></span>
                <span class="texto">Productos totales</span>
            </article>

            <article class="tarjeta-resumen">
                <span class="numero"><?php echo e($resumen["correcto"]); ?></span>
                <span class="texto">Stock correcto</span>
            </article>

            <article class="tarjeta-resumen alerta">
                <span class="numero"><?php echo e($resumen["stock_bajo"]); ?></span>
                <span class="texto">Stock bajo</span>
            </article>

            <article class="tarjeta-resumen">
                <span class="numero"><?php echo e($resumen["sobrestock"]); ?></span>
                <span class="texto">Sobrestock</span>
            </article>
        </section>

        <section class="bloque">
            <div class="bloque-titulo">
                <div>
                    <h2>Listado de productos</h2>
                    <p>
                        Productos y cantidades disponibles en el restaurante.
                    </p>
                </div>

                <span class="contador">
                    <?php echo count($productos); ?> registros
                </span>
            </div>

            <div class="tabla-contenedor">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Categoría</th>
                            <th>Producto</th>
                            <th>Unidad</th>
                            <th>Stock actual</th>
                            <th>Stock mínimo</th>
                            <th>Stock máximo</th>
                            <th>Coste</th>
                            <th>Proveedor</th>
                            <th>Zona</th>
                            <th>Estado</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($productos as $producto): ?>
                            <tr>
                                <td><?php echo e($producto["id_producto"] ?? ""); ?></td>

                                <td>
                                    <span class="categoria">
                                        <?php echo e($producto["categoria"] ?? ""); ?>
                                    </span>
                                </td>

                                <td>
                                    <strong><?php echo e($producto["nombre"] ?? ""); ?></strong>
                                    <small><?php echo e($producto["subcategoria"] ?? ""); ?></small>
                                </td>

                                <td><?php echo e($producto["unidad_medida"] ?? ""); ?></td>

                                <td><?php echo e($producto["stock_actual"] ?? ""); ?></td>

                                <td><?php echo e($producto["stock_minimo"] ?? ""); ?></td>

                                <td><?php echo e($producto["stock_maximo"] ?? ""); ?></td>

                                <td><?php echo formatoEuros($producto["coste_unitario"] ?? ""); ?></td>

                                <td><?php echo e($producto["id_proveedor"] ?? ""); ?></td>

                                <td><?php echo e($producto["zona_almacen"] ?? ""); ?></td>

                                <td>
                                    <?php
                                        $estado = $producto["estado_stock"] ?? "Desconocido";
                                        $claseEstado = claseEstadoStock($estado);
                                    ?>

                                    <span class="estado <?php echo e($claseEstado); ?>">
                                        <?php echo e($estado); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

    <?php endif; ?>

</main>

<footer class="footer">
    <div class="contenedor">
        <p>
            Kitcherry Stock · Sistema de apoyo a la gestión de stock en hostelería
        </p>
    </div>
</footer>

</body>
</html>