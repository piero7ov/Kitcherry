<?php
require_once "config.php";
require_once "funciones.php";

$proveedores = obtenerProveedoresDesdeDrive($CSV_PROVEEDORES);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Exportar proveedores - Kitcherry Stock</title>

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
        <p>Listado de proveedores</p>
    </div>

    <div>
        <p><strong>Fecha:</strong> <?php echo date("d/m/Y H:i"); ?></p>
        <p><strong>Total proveedores:</strong> <?php echo count($proveedores); ?></p>
    </div>
</div>

<div class="acciones">
    <button onclick="window.print()">Imprimir / guardar PDF</button>
    <a href="<?php echo e($CSV_PROVEEDORES); ?>" class="secundario">Descargar CSV</a>
</div>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Proveedor</th>
            <th>Tipo</th>
            <th>Email</th>
            <th>Teléfono</th>
            <th>Ubicación</th>
            <th>Entrega</th>
            <th>Conservación</th>
            <th>Activo</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach ($proveedores as $proveedor): ?>
            <tr>
                <td><?php echo e($proveedor["id_proveedor"] ?? ""); ?></td>
                <td><?php echo e($proveedor["nombre_proveedor"] ?? ""); ?></td>
                <td><?php echo e($proveedor["tipo"] ?? ""); ?></td>
                <td><?php echo e($proveedor["email"] ?? ""); ?></td>
                <td><?php echo e($proveedor["telefono"] ?? ""); ?></td>
                <td><?php echo e($proveedor["ubicacion"] ?? ""); ?></td>
                <td><?php echo e($proveedor["tiempo_entrega_estimado"] ?? ""); ?></td>
                <td><?php echo e($proveedor["tipo_conservacion"] ?? ""); ?></td>
                <td><?php echo e($proveedor["activo"] ?? ""); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>