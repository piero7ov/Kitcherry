<?php
require_once "config.php";
require_once "funciones.php";

$idSolicitud = $_GET["id"] ?? "";
$solicitudes = obtenerSolicitudesDesdeDrive($CSV_SOLICITUDES);

$solicitudEncontrada = null;

foreach ($solicitudes as $solicitud) {
    if (($solicitud["id_solicitud"] ?? "") === $idSolicitud) {
        $solicitudEncontrada = $solicitud;
        break;
    }
}

function parsearProductosSolicitudVista($textoProductos)
{
    $items = [];
    $partes = explode("||", $textoProductos);

    foreach ($partes as $parte) {
        $parte = trim($parte);

        if ($parte === "") {
            continue;
        }

        $campos = array_map("trim", explode("|", $parte));

        $items[] = [
            "producto" => $campos[0] ?? "",
            "categoria" => $campos[1] ?? "",
            "cantidad" => $campos[2] ?? "",
            "coste" => $campos[3] ?? "",
        ];
    }

    return $items;
}

$productos = $solicitudEncontrada
    ? parsearProductosSolicitudVista($solicitudEncontrada["productos"] ?? "")
    : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud <?php echo e($idSolicitud); ?> - Kitcherry Stock</title>

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

        h2 {
            margin-top: 28px;
            border-bottom: 1px solid #dddddd;
            padding-bottom: 8px;
        }

        .marca {
            color: #C2182B;
            font-weight: bold;
        }

        .acciones {
            margin-bottom: 20px;
        }

        button {
            border: 1px solid #161616;
            background: #161616;
            color: #ffffff;
            padding: 10px 14px;
            font-weight: bold;
            cursor: pointer;
            margin-right: 8px;
        }

        .datos {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .dato {
            border: 1px solid #dddddd;
            padding: 12px;
        }

        .dato span {
            display: block;
            color: #555555;
            font-size: 12px;
            margin-bottom: 4px;
        }

        .dato strong {
            display: block;
            font-size: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
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

        .observaciones {
            border-left: 4px solid #C2182B;
            background: #fafafa;
            padding: 14px;
            white-space: pre-line;
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

<?php if (!$solicitudEncontrada): ?>

    <h1>Solicitud no encontrada</h1>
    <p>No se encontró ninguna solicitud con el ID indicado.</p>

<?php else: ?>

    <div class="cabecera">
        <div>
            <h1><span class="marca">Kitcherry</span> Stock</h1>
            <p>Solicitud interna de reposición</p>
        </div>

        <div>
            <p><strong>ID:</strong> <?php echo e($solicitudEncontrada["id_solicitud"] ?? ""); ?></p>
            <p><strong>Fecha:</strong> <?php echo e($solicitudEncontrada["fecha"] ?? ""); ?></p>
        </div>
    </div>

    <div class="acciones">
        <button onclick="window.print()">Imprimir / guardar PDF</button>
    </div>

    <div class="datos">
        <div class="dato">
            <span>Empleado</span>
            <strong><?php echo e($solicitudEncontrada["empleado"] ?? ""); ?></strong>
        </div>

        <div class="dato">
            <span>Zona</span>
            <strong><?php echo e($solicitudEncontrada["zona"] ?? ""); ?></strong>
        </div>

        <div class="dato">
            <span>Prioridad</span>
            <strong><?php echo e($solicitudEncontrada["prioridad"] ?? ""); ?></strong>
        </div>

        <div class="dato">
            <span>Estado</span>
            <strong><?php echo e($solicitudEncontrada["estado"] ?? ""); ?></strong>
        </div>

        <div class="dato">
            <span>Total unidades</span>
            <strong><?php echo e($solicitudEncontrada["total_unidades"] ?? ""); ?></strong>
        </div>

        <div class="dato">
            <span>Coste estimado</span>
            <strong><?php echo formatoEuros($solicitudEncontrada["coste_estimado"] ?? ""); ?></strong>
        </div>
    </div>

    <h2>Productos solicitados</h2>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Categoría</th>
                <th>Cantidad</th>
                <th>Coste</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($productos as $producto): ?>
                <tr>
                    <td><?php echo e($producto["producto"]); ?></td>
                    <td><?php echo e($producto["categoria"]); ?></td>
                    <td><?php echo e($producto["cantidad"]); ?></td>
                    <td><?php echo e($producto["coste"]); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Observaciones</h2>

    <div class="observaciones">
        <?php echo e($solicitudEncontrada["observaciones"] ?? ""); ?>
    </div>

<?php endif; ?>

</body>
</html>