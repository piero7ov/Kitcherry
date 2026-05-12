<?php
require_once "config.php";
require_once "funciones.php";

$solicitudes = obtenerSolicitudesDesdeDrive($CSV_SOLICITUDES);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Exportar solicitudes - Kitcherry Stock</title>

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
        <p>Historial de solicitudes internas</p>
    </div>

    <div>
        <p><strong>Fecha:</strong> <?php echo date("d/m/Y H:i"); ?></p>
        <p><strong>Total solicitudes:</strong> <?php echo count($solicitudes); ?></p>
    </div>
</div>

<div class="acciones">
    <button onclick="window.print()">Imprimir / guardar PDF</button>
    <a href="<?php echo e($CSV_SOLICITUDES); ?>" class="secundario">Descargar CSV</a>
</div>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Empleado</th>
            <th>Zona</th>
            <th>Prioridad</th>
            <th>Estado</th>
            <th>Productos</th>
            <th>Unidades</th>
            <th>Coste</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach ($solicitudes as $solicitud): ?>
            <tr>
                <td><?php echo e($solicitud["id_solicitud"] ?? ""); ?></td>
                <td><?php echo e($solicitud["fecha"] ?? ""); ?></td>
                <td><?php echo e($solicitud["empleado"] ?? ""); ?></td>
                <td><?php echo e($solicitud["zona"] ?? ""); ?></td>
                <td><?php echo e($solicitud["prioridad"] ?? ""); ?></td>
                <td><?php echo e($solicitud["estado"] ?? ""); ?></td>
                <td><?php echo e($solicitud["total_productos"] ?? ""); ?></td>
                <td><?php echo e($solicitud["total_unidades"] ?? ""); ?></td>
                <td><?php echo formatoEuros($solicitud["coste_estimado"] ?? ""); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>