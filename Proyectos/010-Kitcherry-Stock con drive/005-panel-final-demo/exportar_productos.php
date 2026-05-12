<?php
require_once "config.php";
require_once "funciones.php";

$productos = obtenerProductosDesdeDrive($CSV_PRODUCTOS);
$proveedores = obtenerProveedoresDesdeDrive($CSV_PROVEEDORES);
$mapaProveedores = crearMapaProveedores($proveedores);
$productos = enriquecerProductosConProveedores($productos, $mapaProveedores);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Exportar productos - Kitcherry Stock</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            color: #161616;
            margin: 30px;
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

        .marca {
            color: #C2182B;
            font-weight: bold;
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
        }

        .estado-bajo {
            color: #C2182B;
            font-weight: bold;
        }

        @media print {
            .acciones {
                display: none;
            }

            body {
                margin: 0;
            }
        }
    </style>
</head>
<body>

<div class="cabecera">
    <div>
        <h1><span class="marca">Kitcherry</span> Stock</h1>
        <p>Listado de productos</p>
    </div>

    <div>
        <p><strong>Fecha:</strong> <?php echo date("d/m/Y H:i"); ?></p>
        <p><strong>Total productos:</strong> <?php echo count($productos); ?></p>
    </div>
</div>

<div class="acciones">
    <button onclick="window.print()">Imprimir / guardar PDF</button>
    <a href="<?php echo e($CSV_PRODUCTOS); ?>" class="secundario">Descargar CSV</a>
</div>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Producto</th>
            <th>Categoría</th>
            <th>Stock</th>
            <th>Mínimo</th>
            <th>Máximo</th>
            <th>Proveedor</th>
            <th>Zona</th>
            <th>Estado</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach ($productos as $producto): ?>
            <tr>
                <td><?php echo e($producto["id_producto"] ?? ""); ?></td>
                <td><?php echo e($producto["nombre"] ?? ""); ?></td>
                <td><?php echo e($producto["categoria"] ?? ""); ?></td>
                <td><?php echo e($producto["stock_actual"] ?? ""); ?> <?php echo e($producto["unidad_medida"] ?? ""); ?></td>
                <td><?php echo e($producto["stock_minimo"] ?? ""); ?></td>
                <td><?php echo e($producto["stock_maximo"] ?? ""); ?></td>
                <td><?php echo e($producto["nombre_proveedor"] ?? ""); ?></td>
                <td><?php echo e($producto["zona_almacen"] ?? ""); ?></td>
                <td class="<?php echo ($producto["estado_stock"] ?? "") === "Stock bajo" ? "estado-bajo" : ""; ?>">
                    <?php echo e($producto["estado_stock"] ?? ""); ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>