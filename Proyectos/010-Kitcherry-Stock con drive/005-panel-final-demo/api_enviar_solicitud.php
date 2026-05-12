<?php
// ==========================================================
// KITCHERRY STOCK
// Archivo: api_enviar_solicitud.php
// Envía una solicitud registrada por email mediante SMTP
// ==========================================================

require_once "config.php";
require_once "funciones.php";
require_once "includes/smtp.php";

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "ok" => false,
        "mensaje" => "Método no permitido."
    ]);
    exit;
}

$entrada = file_get_contents("php://input");
$datos = json_decode($entrada, true);

if (!is_array($datos)) {
    echo json_encode([
        "ok" => false,
        "mensaje" => "JSON no válido."
    ]);
    exit;
}

$idSolicitud = trim($datos["id_solicitud"] ?? "");
$destinatario = trim($datos["destinatario"] ?? "");

if ($idSolicitud === "") {
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se ha recibido el ID de la solicitud."
    ]);
    exit;
}

try {
    if ($destinatario === "") {
        throw new Exception("Debes indicar un correo destinatario.");
    }

    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("El correo destinatario no tiene un formato válido.");
    }

    $solicitudes = obtenerSolicitudesDesdeDrive($CSV_SOLICITUDES);
    $solicitud = buscarSolicitudPorId($solicitudes, $idSolicitud);

    if (!$solicitud) {
        throw new Exception("No se encontró la solicitud indicada.");
    }

    $asunto = "Solicitud interna de reposición - " . $idSolicitud;

    $html = construirHTMLSolicitudEmail($solicitud);
    $texto = construirTextoSolicitudEmail($solicitud);

    enviarCorreoSMTP($destinatario, $asunto, $html, $texto);

    echo json_encode([
        "ok" => true,
        "mensaje" => "Solicitud enviada correctamente por email.",
        "destinatario" => $destinatario
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode([
        "ok" => false,
        "mensaje" => $e->getMessage()
    ]);
    exit;
}

function buscarSolicitudPorId($solicitudes, $idSolicitud)
{
    foreach ($solicitudes as $solicitud) {
        if (($solicitud["id_solicitud"] ?? "") === $idSolicitud) {
            return $solicitud;
        }
    }

    return null;
}

function parsearProductosSolicitud($textoProductos)
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

function construirHTMLSolicitudEmail($solicitud)
{
    $productos = parsearProductosSolicitud($solicitud["productos"] ?? "");

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
    </head>
    <body style="font-family: Arial, sans-serif; color: #161616;">
        <h2 style="color:#C2182B;">Kitcherry Stock</h2>
        <h3>Solicitud interna de reposición</h3>

        <p><strong>ID:</strong> <?php echo htmlspecialchars($solicitud["id_solicitud"] ?? "", ENT_QUOTES, "UTF-8"); ?></p>
        <p><strong>Fecha:</strong> <?php echo htmlspecialchars($solicitud["fecha"] ?? "", ENT_QUOTES, "UTF-8"); ?></p>
        <p><strong>Empleado:</strong> <?php echo htmlspecialchars($solicitud["empleado"] ?? "", ENT_QUOTES, "UTF-8"); ?></p>
        <p><strong>Zona:</strong> <?php echo htmlspecialchars($solicitud["zona"] ?? "", ENT_QUOTES, "UTF-8"); ?></p>
        <p><strong>Prioridad:</strong> <?php echo htmlspecialchars($solicitud["prioridad"] ?? "", ENT_QUOTES, "UTF-8"); ?></p>
        <p><strong>Estado:</strong> <?php echo htmlspecialchars($solicitud["estado"] ?? "", ENT_QUOTES, "UTF-8"); ?></p>

        <h3>Productos solicitados</h3>

        <table cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse; width:100%;">
            <thead>
                <tr style="background:#161616; color:#ffffff;">
                    <th align="left">Producto</th>
                    <th align="left">Categoría</th>
                    <th align="left">Cantidad</th>
                    <th align="left">Coste</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($productos as $producto): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($producto["producto"], ENT_QUOTES, "UTF-8"); ?></td>
                        <td><?php echo htmlspecialchars($producto["categoria"], ENT_QUOTES, "UTF-8"); ?></td>
                        <td><?php echo htmlspecialchars($producto["cantidad"], ENT_QUOTES, "UTF-8"); ?></td>
                        <td><?php echo htmlspecialchars($producto["coste"], ENT_QUOTES, "UTF-8"); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p><strong>Total productos:</strong> <?php echo htmlspecialchars($solicitud["total_productos"] ?? "", ENT_QUOTES, "UTF-8"); ?></p>
        <p><strong>Total unidades:</strong> <?php echo htmlspecialchars($solicitud["total_unidades"] ?? "", ENT_QUOTES, "UTF-8"); ?></p>
        <p><strong>Coste estimado:</strong> <?php echo htmlspecialchars($solicitud["coste_estimado"] ?? "", ENT_QUOTES, "UTF-8"); ?> €</p>

        <h3>Observaciones</h3>
        <p><?php echo nl2br(htmlspecialchars($solicitud["observaciones"] ?? "", ENT_QUOTES, "UTF-8")); ?></p>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function construirTextoSolicitudEmail($solicitud)
{
    $productos = parsearProductosSolicitud($solicitud["productos"] ?? "");

    $texto = "";
    $texto .= "KITCHERRY STOCK\n";
    $texto .= "Solicitud interna de reposición\n\n";
    $texto .= "ID: " . ($solicitud["id_solicitud"] ?? "") . "\n";
    $texto .= "Fecha: " . ($solicitud["fecha"] ?? "") . "\n";
    $texto .= "Empleado: " . ($solicitud["empleado"] ?? "") . "\n";
    $texto .= "Zona: " . ($solicitud["zona"] ?? "") . "\n";
    $texto .= "Prioridad: " . ($solicitud["prioridad"] ?? "") . "\n";
    $texto .= "Estado: " . ($solicitud["estado"] ?? "") . "\n\n";

    $texto .= "PRODUCTOS SOLICITADOS\n";
    $texto .= "--------------------------------\n";

    foreach ($productos as $producto) {
        $texto .= "- " . $producto["producto"] . " | " . $producto["categoria"] . " | " . $producto["cantidad"] . " | " . $producto["coste"] . "\n";
    }

    $texto .= "\n";
    $texto .= "Total productos: " . ($solicitud["total_productos"] ?? "") . "\n";
    $texto .= "Total unidades: " . ($solicitud["total_unidades"] ?? "") . "\n";
    $texto .= "Coste estimado: " . ($solicitud["coste_estimado"] ?? "") . " €\n\n";

    $texto .= "OBSERVACIONES\n";
    $texto .= "--------------------------------\n";
    $texto .= ($solicitud["observaciones"] ?? "");

    return $texto;
}