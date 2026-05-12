<?php
// ==========================================================
// KITCHERRY STOCK
// Archivo: exportar_stock_bajo.php
// Vista imprimible de productos con stock bajo
// ==========================================================

require_once "config.php";
require_once "funciones.php";

$productos = obtenerProductosDesdeDrive($CSV_PRODUCTOS);
$proveedores = obtenerProveedoresDesdeDrive($CSV_PROVEEDORES);

$mapaProveedores = crearMapaProveedores($proveedores);
$productos = enriquecerProductosConProveedores($productos, $mapaProveedores);

$productosStockBajo = obtenerProductosStockBajo($productos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <title>Stock bajo - Kitcherry Stock</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            color: #161616;
            margin: 30px;
            background: #ffffff;
        }

        .cabecera {
            border-bottom: 3px solid #C2182B;
            padding-bottom: 15px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        h1 {
            margin: 0;
            font-size: 28px;
        }

        h2 {
            margin: 0 0 8px;
            font-size: 20px;
        }

        p {
            margin: 4px 0;
        }

        .marca {
            color: #C2182B;
            font-weight: bold;
        }

        .resumen {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 22px;
        }

        .dato {
            border: 1px solid #dddddd;
            padding: 12px;
            background: #fafafa;
        }

        .dato span {
            display: block;
            color: #555555;
            font-size: 12px;
            margin-bottom: 4px;
        }

        .dato strong {
            display: block;
            font-size: 18px;
        }

        .acciones {
            margin-bottom: 20px;
        }

        button,
        a {
            display: inline-block;
            border: 1px solid #161616;
            background: #161616;
            color: #ffffff;
            padding: 10px 14px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
            margin-right: 8px;
        }

        a.secundario {
            background: #ffffff;
            color: #161616;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        th {
            background: #161616;
            color: #ffffff;
            text-align: left;
            padding: 8px;
        }

        td {
            border-bottom: 1px solid #dddddd;
            padding: 8px;
            vertical-align: top;
        }

        .estado-bajo {
            color: #C2182B;
            font-weight: bold;
        }

        .cantidad-baja {
            color: #C2182B;
            font-weight: bold;
        }

        .mensaje-vacio {
            border-left: 4px solid #C2182B;
            background: #fafafa;
            padding: 16px;
        }

        @media print {
            .acciones {
                display: none;
            }

            body {
                margin: 0;
            }

            .cabecera {
                margin-top: 0;
            }
        }
    </style>
</head>
<body>

<div class="cabecera">
    <div>
        <h1><span class="marca">Kitcherry</span> Stock</h1>
        <p>Listado de productos con stock bajo</p>
    </div>

    <div>
        <p><strong>Fecha:</strong> <?php echo date("d/m/Y H:i"); ?></p>
        <p><strong>Total productos:</strong> <?php echo count($productosStockBajo); ?></p>
    </div>
</div>

<div class="acciones">
    <button onclick="window.print()">Imprimir / guardar PDF</button>
    <a href="exportar_productos.php" class="secundario">Ver todos los productos</a>
</div>

<div class="resumen">
    <div class="dato">
        <span>Productos revisados</span>
        <strong><?php echo count($productos); ?></strong>
    </div>

    <div class="dato">
        <span>Productos con stock bajo</span>
        <strong><?php echo count($productosStockBajo); ?></strong>
    </div>

    <div class="dato">
        <span>Tipo de informe</span>
        <strong>Reposición</strong>
    </div>
</div>

<?php if (count($productosStockBajo) === 0): ?>

    <div class="mensaje-vacio">
        <h2>No hay productos con stock bajo</h2>
        <p>Actualmente no se han detectado productos por debajo del mínimo configurado.</p>
    </div>

<?php else: ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Producto</th>
                <th>Categoría</th>
                <th>Stock actual</th>
                <th>Stock mínimo</th>
                <th>Diferencia</th>
                <th>Proveedor</th>
                <th>Zona</th>
                <th>Estado</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($productosStockBajo as $producto): ?>
                <?php
                    $stockActual = (float)($producto["stock_actual"] ?? 0);
                    $stockMinimo = (float)($producto["stock_minimo"] ?? 0);
                    $diferencia = max(0, $stockMinimo - $stockActual);
                    $unidad = $producto["unidad_medida"] ?? "";
                ?>

                <tr>
                    <td><?php echo e($producto["id_producto"] ?? ""); ?></td>

                    <td>
                        <strong><?php echo e($producto["nombre"] ?? ""); ?></strong><br>
                        <span><?php echo e($producto["subcategoria"] ?? ""); ?></span>
                    </td>

                    <td><?php echo e($producto["categoria"] ?? ""); ?></td>

                    <td class="cantidad-baja">
                        <?php echo e($producto["stock_actual"] ?? ""); ?>
                        <?php echo e($unidad); ?>
                    </td>

                    <td>
                        <?php echo e($producto["stock_minimo"] ?? ""); ?>
                        <?php echo e($unidad); ?>
                    </td>

                    <td>
                        <?php echo e($diferencia); ?>
                        <?php echo e($unidad); ?>
                    </td>

                    <td>
                        <?php echo e($producto["nombre_proveedor"] ?? ""); ?><br>
                        <small><?php echo e($producto["id_proveedor"] ?? ""); ?></small>
                    </td>

                    <td><?php echo e($producto["zona_almacen"] ?? ""); ?></td>

                    <td class="estado-bajo">
                        <?php echo e($producto["estado_stock"] ?? "Stock bajo"); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<?php endif; ?>

</body>
</html>