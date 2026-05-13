<?php
// ==========================================================
// KITCHERRY RESERVAS
// Archivo: includes/exportaciones_reservas.php
// Funciones para exportar reservas a JSON y vista imprimible
// ==========================================================

function validarFechaExportacion($fecha) {
    $fecha = trim($fecha ?? '');

    $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);

    if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
        return date('Y-m-d');
    }

    return $fecha;
}

function normalizarTurnoExportacion($turno) {
    $turno = strtolower(trim($turno ?? 'todos'));

    $turnosValidos = [
        'todos',
        'comida',
        'cena'
    ];

    if (!in_array($turno, $turnosValidos, true)) {
        return 'todos';
    }

    return $turno;
}

function textoTurnoExportacion($turno) {
    $textos = [
        'todos' => 'Todos los turnos',
        'comida' => 'Comida',
        'cena' => 'Cena'
    ];

    return $textos[$turno] ?? ucfirst($turno);
}

function estadoTextoExportacion($estado) {
    $textos = [
        'pendiente' => 'Pendiente',
        'confirmada' => 'Confirmada',
        'modificada' => 'Modificada',
        'cancelada' => 'Cancelada',
        'no_presentada' => 'No presentada',
        'completada' => 'Completada'
    ];

    return $textos[$estado] ?? ucfirst(str_replace('_', ' ', $estado));
}

function formatearFechaExportacion($fecha) {
    $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);

    if (!$fechaObj) {
        return $fecha;
    }

    return $fechaObj->format('d/m/Y');
}

function limpiarTextoExportacion($valor) {
    return trim((string)($valor ?? ''));
}

function obtenerNegocioActivoExportacion(PDO $pdo) {
    $stmt = $pdo->query("
        SELECT
            id,
            nombre,
            email,
            telefono
        FROM negocios
        WHERE activo = 1
        ORDER BY id ASC
        LIMIT 1
    ");

    return $stmt->fetch();
}

function cargarReservasExportacion(PDO $pdo, $negocioId, $fecha, $turno = 'todos') {
    $sql = "
        SELECT
            r.id,
            r.negocio_id,
            r.cliente_id,
            r.mesa_id,
            r.fecha,
            r.hora,
            r.turno,
            r.personas,
            r.estado,
            r.origen,
            r.observaciones,
            r.alergias,
            r.preferencias,
            r.confirmacion_enviada,
            r.recordatorio_enviado,
            r.creado_en,
            r.actualizado_en,

            c.nombre AS cliente_nombre,
            c.telefono AS cliente_telefono,
            c.email AS cliente_email,

            m.nombre AS mesa_nombre,
            m.zona AS mesa_zona,
            m.capacidad AS mesa_capacidad,

            (
                SELECT COUNT(*)
                FROM alertas_reserva a
                WHERE a.reserva_id = r.id
                AND a.resuelta = 0
            ) AS total_alertas,

            (
                SELECT COUNT(*)
                FROM alertas_reserva a
                WHERE a.reserva_id = r.id
                AND a.resuelta = 0
                AND a.nivel = 'critico'
            ) AS total_alertas_criticas,

            (
                SELECT COUNT(*)
                FROM alertas_reserva a
                WHERE a.reserva_id = r.id
                AND a.resuelta = 0
                AND a.nivel = 'riesgo'
            ) AS total_alertas_riesgo

        FROM reservas r
        LEFT JOIN clientes c ON c.id = r.cliente_id
        LEFT JOIN mesas m ON m.id = r.mesa_id
        WHERE r.negocio_id = :negocio_id
        AND r.fecha = :fecha
    ";

    $params = [
        ':negocio_id' => $negocioId,
        ':fecha' => $fecha
    ];

    if ($turno !== 'todos') {
        $sql .= " AND r.turno = :turno ";
        $params[':turno'] = $turno;
    }

    $sql .= "
        ORDER BY
            r.hora ASC,
            r.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function calcularResumenExportacion(array $reservas) {
    $resumen = [
        'total_reservas' => 0,
        'total_comensales' => 0,
        'pendientes' => 0,
        'confirmadas' => 0,
        'modificadas' => 0,
        'canceladas' => 0,
        'no_presentadas' => 0,
        'completadas' => 0,
        'con_alergias' => 0,
        'sin_mesa' => 0,
        'con_alertas' => 0,
        'alertas_abiertas' => 0,
        'alertas_riesgo' => 0,
        'alertas_criticas' => 0
    ];

    foreach ($reservas as $reserva) {
        $estado = $reserva['estado'];

        $resumen['total_reservas']++;

        if (!in_array($estado, ['cancelada', 'no_presentada'], true)) {
            $resumen['total_comensales'] += (int)$reserva['personas'];
        }

        if ($estado === 'pendiente') {
            $resumen['pendientes']++;
        }

        if ($estado === 'confirmada') {
            $resumen['confirmadas']++;
        }

        if ($estado === 'modificada') {
            $resumen['modificadas']++;
        }

        if ($estado === 'cancelada') {
            $resumen['canceladas']++;
        }

        if ($estado === 'no_presentada') {
            $resumen['no_presentadas']++;
        }

        if ($estado === 'completada') {
            $resumen['completadas']++;
        }

        if (limpiarTextoExportacion($reserva['alergias']) !== '') {
            $resumen['con_alergias']++;
        }

        if (empty($reserva['mesa_id'])) {
            $resumen['sin_mesa']++;
        }

        $alertas = (int)$reserva['total_alertas'];
        $alertasRiesgo = (int)$reserva['total_alertas_riesgo'];
        $alertasCriticas = (int)$reserva['total_alertas_criticas'];

        if ($alertas > 0) {
            $resumen['con_alertas']++;
        }

        $resumen['alertas_abiertas'] += $alertas;
        $resumen['alertas_riesgo'] += $alertasRiesgo;
        $resumen['alertas_criticas'] += $alertasCriticas;
    }

    return $resumen;
}

function construirPayloadServiceMap(array $negocio, $fecha, $turno, array $reservas) {
    $reservasExportadas = [];

    foreach ($reservas as $reserva) {
        $mesa = null;

        if (!empty($reserva['mesa_id'])) {
            $mesa = [
                'id' => (int)$reserva['mesa_id'],
                'nombre' => $reserva['mesa_nombre'],
                'zona' => $reserva['mesa_zona'],
                'capacidad' => (int)$reserva['mesa_capacidad']
            ];
        }

        $reservasExportadas[] = [
            'id' => (int)$reserva['id'],
            'fecha' => $reserva['fecha'],
            'hora' => substr($reserva['hora'], 0, 5),
            'turno' => $reserva['turno'],
            'cliente' => limpiarTextoExportacion($reserva['cliente_nombre']),
            'telefono' => limpiarTextoExportacion($reserva['cliente_telefono']),
            'email' => limpiarTextoExportacion($reserva['cliente_email']),
            'personas' => (int)$reserva['personas'],
            'estado' => $reserva['estado'],
            'estado_texto' => estadoTextoExportacion($reserva['estado']),
            'mesa' => $mesa,
            'mesa_asignada' => !empty($reserva['mesa_id']) ? (int)$reserva['mesa_id'] : null,
            'zona_preferida' => limpiarTextoExportacion($reserva['preferencias']),
            'alergias' => limpiarTextoExportacion($reserva['alergias']),
            'preferencias' => limpiarTextoExportacion($reserva['preferencias']),
            'observaciones' => limpiarTextoExportacion($reserva['observaciones']),
            'origen' => limpiarTextoExportacion($reserva['origen']),
            'alertas' => [
                'total' => (int)$reserva['total_alertas'],
                'riesgo' => (int)$reserva['total_alertas_riesgo'],
                'criticas' => (int)$reserva['total_alertas_criticas']
            ],
            'confirmacion_enviada' => ((int)$reserva['confirmacion_enviada'] === 1),
            'recordatorio_enviado' => ((int)$reserva['recordatorio_enviado'] === 1),
            'creado_en' => $reserva['creado_en'],
            'actualizado_en' => $reserva['actualizado_en']
        ];
    }

    return [
        'version' => '1.0',
        'origen' => 'Kitcherry Reservas',
        'destino' => 'Kitcherry Service Map',
        'fecha_exportacion' => date('c'),
        'fecha_servicio' => $fecha,
        'fecha_servicio_formateada' => formatearFechaExportacion($fecha),
        'turno' => $turno,
        'turno_texto' => textoTurnoExportacion($turno),
        'negocio' => [
            'id' => (int)$negocio['id'],
            'nombre' => limpiarTextoExportacion($negocio['nombre']),
            'telefono' => limpiarTextoExportacion($negocio['telefono']),
            'email' => limpiarTextoExportacion($negocio['email'])
        ],
        'resumen' => calcularResumenExportacion($reservas),
        'reservas' => $reservasExportadas
    ];
}

function nombreArchivoExportacion($fecha, $turno, $extension) {
    $turnoArchivo = ($turno === 'todos') ? 'todos' : $turno;

    return 'reservas_' . $fecha . '_' . $turnoArchivo . '.' . $extension;
}

function enviarJsonServiceMap(array $payload, $fecha, $turno) {
    $nombreArchivo = nombreArchivoExportacion($fecha, $turno, 'json');

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );
}

function hExportacion($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

function renderizarVistaImprimibleReservas(array $payload) {
    $resumen = $payload['resumen'];
    $reservas = $payload['reservas'];
    $negocio = $payload['negocio'];

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>
            Kitcherry Reservas | <?php echo hExportacion($payload['fecha_servicio_formateada']); ?>
        </title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <style>
            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                padding: 28px;
                font-family: Arial, Helvetica, sans-serif;
                color: #161616;
                background: #f6f6f6;
            }

            .print-page {
                max-width: 1180px;
                margin: 0 auto;
                background: #ffffff;
                border-radius: 22px;
                padding: 30px;
                box-shadow: 0 18px 40px rgba(0, 0, 0, 0.08);
            }

            .print-header {
                display: flex;
                justify-content: space-between;
                gap: 20px;
                align-items: flex-start;
                border-bottom: 3px solid #C2182B;
                padding-bottom: 18px;
                margin-bottom: 22px;
            }

            .brand {
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 0.12em;
                color: #C2182B;
                font-weight: bold;
                margin-bottom: 6px;
            }

            h1 {
                margin: 0;
                font-size: 30px;
            }

            .subtitle {
                margin: 8px 0 0;
                color: #555555;
            }

            .print-actions {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
                justify-content: flex-end;
            }

            .btn-print {
                display: inline-block;
                border: 0;
                border-radius: 999px;
                padding: 10px 16px;
                background: #C2182B;
                color: #ffffff;
                font-weight: bold;
                text-decoration: none;
                cursor: pointer;
            }

            .btn-back {
                background: #161616;
            }

            .summary-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 12px;
                margin-bottom: 24px;
            }

            .summary-card {
                border: 1px solid #ececec;
                border-radius: 16px;
                padding: 14px;
                background: #fbfbfb;
            }

            .summary-card span {
                display: block;
                color: #666666;
                font-size: 12px;
                margin-bottom: 4px;
            }

            .summary-card strong {
                font-size: 26px;
            }

            .info-block {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
                margin-bottom: 22px;
            }

            .info-card {
                border-radius: 16px;
                padding: 14px;
                background: #fbeaec;
                border: 1px solid #f2c8cf;
            }

            .info-card strong {
                display: block;
                margin-bottom: 6px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 13px;
            }

            th,
            td {
                border-bottom: 1px solid #ececec;
                padding: 10px 8px;
                text-align: left;
                vertical-align: top;
            }

            th {
                background: #C2182B;
                color: #ffffff;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.04em;
            }

            tr:nth-child(even) td {
                background: #fafafa;
            }

            .pill {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: bold;
                background: #ececec;
            }

            .pill-warning {
                background: #fff3cd;
            }

            .pill-alert {
                background: #fbeaec;
                color: #C2182B;
            }

            .small-muted {
                color: #777777;
                font-size: 12px;
            }

            .empty {
                padding: 22px;
                border-radius: 16px;
                background: #fbfbfb;
                border: 1px dashed #cccccc;
                color: #666666;
            }

            .footer-note {
                margin-top: 24px;
                color: #777777;
                font-size: 12px;
                border-top: 1px solid #ececec;
                padding-top: 14px;
            }

            @media print {
                body {
                    background: #ffffff;
                    padding: 0;
                }

                .print-page {
                    max-width: none;
                    margin: 0;
                    box-shadow: none;
                    border-radius: 0;
                    padding: 18px;
                }

                .print-actions {
                    display: none;
                }

                .summary-grid {
                    grid-template-columns: repeat(4, 1fr);
                }

                th {
                    background: #eeeeee !important;
                    color: #161616 !important;
                }
            }

            @media (max-width: 800px) {
                .print-header {
                    flex-direction: column;
                }

                .summary-grid,
                .info-block {
                    grid-template-columns: 1fr 1fr;
                }
            }

            @media (max-width: 520px) {
                .summary-grid,
                .info-block {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    </head>
    <body>

        <main class="print-page">

            <header class="print-header">
                <div>
                    <div class="brand">Kitcherry Reservas</div>

                    <h1>
                        Reservas del día <?php echo hExportacion($payload['fecha_servicio_formateada']); ?>
                    </h1>

                    <p class="subtitle">
                        <?php echo hExportacion($negocio['nombre']); ?> ·
                        <?php echo hExportacion($payload['turno_texto']); ?>
                    </p>

                    <p class="small-muted">
                        Exportado el <?php echo hExportacion(date('d/m/Y H:i')); ?>
                    </p>
                </div>

                <div class="print-actions">
                    <button type="button" class="btn-print" onclick="window.print()">
                        Imprimir / guardar PDF
                    </button>

                    <a href="reservas.php?fecha=<?php echo hExportacion($payload['fecha_servicio']); ?>" class="btn-print btn-back">
                        Volver
                    </a>
                </div>
            </header>

            <section class="summary-grid">
                <article class="summary-card">
                    <span>Total reservas</span>
                    <strong><?php echo (int)$resumen['total_reservas']; ?></strong>
                </article>

                <article class="summary-card">
                    <span>Comensales</span>
                    <strong><?php echo (int)$resumen['total_comensales']; ?></strong>
                </article>

                <article class="summary-card">
                    <span>Pendientes</span>
                    <strong><?php echo (int)$resumen['pendientes']; ?></strong>
                </article>

                <article class="summary-card">
                    <span>Confirmadas</span>
                    <strong><?php echo (int)$resumen['confirmadas']; ?></strong>
                </article>

                <article class="summary-card">
                    <span>Con alergias</span>
                    <strong><?php echo (int)$resumen['con_alergias']; ?></strong>
                </article>

                <article class="summary-card">
                    <span>Sin mesa</span>
                    <strong><?php echo (int)$resumen['sin_mesa']; ?></strong>
                </article>

                <article class="summary-card">
                    <span>Alertas abiertas</span>
                    <strong><?php echo (int)$resumen['alertas_abiertas']; ?></strong>
                </article>

                <article class="summary-card">
                    <span>Críticas</span>
                    <strong><?php echo (int)$resumen['alertas_criticas']; ?></strong>
                </article>
            </section>

            <section class="info-block">
                <article class="info-card">
                    <strong>Uso interno</strong>
                    <span>
                        Esta hoja sirve como resumen operativo para sala, cocina o administración.
                    </span>
                </article>

                <article class="info-card">
                    <strong>Conexión con Service Map</strong>
                    <span>
                        La versión JSON de esta exportación puede cargarse después en Kitcherry Service Map.
                    </span>
                </article>
            </section>

            <?php if (empty($reservas)): ?>

                <div class="empty">
                    No hay reservas registradas para esta fecha y turno.
                </div>

            <?php else: ?>

                <table>
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Cliente</th>
                            <th>Pax</th>
                            <th>Mesa</th>
                            <th>Estado</th>
                            <th>Alergias / preferencias</th>
                            <th>Observaciones</th>
                            <th>Alertas</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($reservas as $reserva): ?>
                            <tr>
                                <td>
                                    <strong><?php echo hExportacion($reserva['hora']); ?></strong>
                                </td>

                                <td>
                                    <strong><?php echo hExportacion($reserva['cliente'] ?: 'Cliente sin nombre'); ?></strong>

                                    <div class="small-muted">
                                        Tel: <?php echo hExportacion($reserva['telefono'] ?: 'Sin teléfono'); ?>
                                    </div>

                                    <div class="small-muted">
                                        Email: <?php echo hExportacion($reserva['email'] ?: 'Sin email'); ?>
                                    </div>
                                </td>

                                <td>
                                    <?php echo (int)$reserva['personas']; ?>
                                </td>

                                <td>
                                    <?php if (!empty($reserva['mesa'])): ?>
                                        <strong><?php echo hExportacion($reserva['mesa']['nombre']); ?></strong>

                                        <div class="small-muted">
                                            <?php echo hExportacion($reserva['mesa']['zona']); ?> ·
                                            <?php echo (int)$reserva['mesa']['capacidad']; ?> pax
                                        </div>
                                    <?php else: ?>
                                        <span class="pill pill-warning">Sin mesa</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="pill">
                                        <?php echo hExportacion($reserva['estado_texto']); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($reserva['alergias'] !== ''): ?>
                                        <span class="pill pill-alert">
                                            <?php echo hExportacion($reserva['alergias']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="small-muted">Sin alergias indicadas</span>
                                    <?php endif; ?>

                                    <?php if ($reserva['preferencias'] !== ''): ?>
                                        <div class="small-muted">
                                            Pref: <?php echo hExportacion($reserva['preferencias']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php echo hExportacion($reserva['observaciones'] ?: ''); ?>
                                </td>

                                <td>
                                    <?php if ((int)$reserva['alertas']['total'] > 0): ?>
                                        <span class="pill pill-alert">
                                            <?php echo (int)$reserva['alertas']['total']; ?> abierta(s)
                                        </span>

                                        <div class="small-muted">
                                            Riesgo: <?php echo (int)$reserva['alertas']['riesgo']; ?> ·
                                            Críticas: <?php echo (int)$reserva['alertas']['criticas']; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="small-muted">Sin alertas</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php endif; ?>

            <p class="footer-note">
                Documento generado desde Kitcherry Reservas. La exportación JSON asociada está preparada para ser leída por Kitcherry Service Map.
            </p>

        </main>

    </body>
    </html>
    <?php

    return ob_get_clean();
}