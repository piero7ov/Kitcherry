<?php
// ==========================================================
// KITCHERRY RESERVAS
// Archivo: exportar_reservas.php
// Exportación de reservas del día en JSON o vista imprimible PDF
// ==========================================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/alertas.php';
require_once __DIR__ . '/includes/exportaciones_reservas.php';

protegerPagina();

$fecha = $_GET['fecha'] ?? date('Y-m-d');
$formato = $_GET['formato'] ?? 'json';
$turno = $_GET['turno'] ?? 'todos';

$fecha = validarFechaExportacion($fecha);
$turno = normalizarTurnoExportacion($turno);

if (!in_array($formato, ['json', 'pdf'], true)) {
    $formato = 'json';
}

try {
    $negocio = obtenerNegocioActivoExportacion($pdo);

    if (!$negocio) {
        throw new Exception('No hay ningún negocio activo configurado.');
    }

    // Regeneramos alertas para que la exportación salga actualizada.
    generarAlertasPorFecha($pdo, (int)$negocio['id'], $fecha);

    $reservas = cargarReservasExportacion(
        $pdo,
        (int)$negocio['id'],
        $fecha,
        $turno
    );

    $payload = construirPayloadServiceMap(
        $negocio,
        $fecha,
        $turno,
        $reservas
    );

    if ($formato === 'json') {
        enviarJsonServiceMap($payload, $fecha, $turno);
        exit;
    }

    echo renderizarVistaImprimibleReservas($payload);
    exit;

} catch (Throwable $e) {
    http_response_code(500);

    if ($formato === 'json') {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'ok' => false,
            'error' => 'No se pudo generar la exportación.',
            'detalle' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        exit;
    }

    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Error de exportación | Kitcherry Reservas</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <style>
            body {
                font-family: Arial, Helvetica, sans-serif;
                background: #f6f6f6;
                color: #161616;
                padding: 40px;
            }

            .error-box {
                max-width: 700px;
                margin: 0 auto;
                background: #ffffff;
                border-radius: 18px;
                padding: 28px;
                box-shadow: 0 18px 40px rgba(0, 0, 0, 0.08);
                border-left: 6px solid #C2182B;
            }

            h1 {
                margin-top: 0;
                color: #C2182B;
            }

            a {
                color: #C2182B;
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>No se pudo generar la exportación</h1>
            <p>
                <?php echo htmlspecialchars($e->getMessage()); ?>
            </p>
            <p>
                <a href="reservas.php">Volver a reservas</a>
            </p>
        </div>
    </body>
    </html>
    <?php
}