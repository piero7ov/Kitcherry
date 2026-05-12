<?php
// ==========================================================
// KITCHERRY RESERVAS
// Archivo: includes/alertas.php
// Reglas internas para detectar alertas en reservas
// ==========================================================

function alertaEstadosActivosSql() {
    return "'pendiente', 'confirmada', 'modificada'";
}

function obtenerTamanoGrupoGrande($pdo, $negocioId) {
    $stmt = $pdo->prepare("
        SELECT tamano_grupo_grande
        FROM configuracion_reservas
        WHERE negocio_id = :negocio_id
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([
        ':negocio_id' => $negocioId
    ]);

    $valor = $stmt->fetchColumn();

    if (!$valor) {
        return 6;
    }

    return (int)$valor;
}

function insertarAlertaReserva($pdo, $reservaId, $clienteId, $tipo, $nivel, $mensaje) {
    // ------------------------------------------------------
    // Evita que una alerta ya resuelta vuelva a aparecer
    // automáticamente cuando se regeneran alertas.
    // ------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM alertas_reserva
        WHERE reserva_id = :reserva_id
        AND tipo = :tipo
        AND resuelta = 1
    ");

    $stmt->execute([
        ':reserva_id' => $reservaId,
        ':tipo' => $tipo
    ]);

    $yaResuelta = (int)$stmt->fetchColumn();

    if ($yaResuelta > 0) {
        return false;
    }

    $stmt = $pdo->prepare("
        INSERT INTO alertas_reserva (
            reserva_id,
            cliente_id,
            tipo,
            nivel,
            mensaje,
            resuelta
        )
        VALUES (
            :reserva_id,
            :cliente_id,
            :tipo,
            :nivel,
            :mensaje,
            0
        )
    ");

    $stmt->execute([
        ':reserva_id' => $reservaId,
        ':cliente_id' => $clienteId,
        ':tipo' => $tipo,
        ':nivel' => $nivel,
        ':mensaje' => $mensaje
    ]);

    return true;
}

function cargarReservaParaAlertas($pdo, $reservaId) {
    $stmt = $pdo->prepare("
        SELECT
            r.id,
            r.negocio_id,
            r.cliente_id,
            r.mesa_id,
            r.fecha,
            r.hora,
            r.personas,
            r.estado,
            r.alergias AS reserva_alergias,
            r.observaciones,
            r.preferencias,

            c.nombre AS cliente_nombre,
            c.telefono AS cliente_telefono,
            c.email AS cliente_email,
            c.estado_cliente,
            c.alergias AS cliente_alergias,

            m.nombre AS mesa_nombre,
            m.zona AS mesa_zona

        FROM reservas r
        LEFT JOIN clientes c ON c.id = r.cliente_id
        LEFT JOIN mesas m ON m.id = r.mesa_id
        WHERE r.id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $reservaId
    ]);

    return $stmt->fetch();
}

function contarNoPresentadasCliente($pdo, $clienteId) {
    if (!$clienteId) {
        return 0;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM reservas
        WHERE cliente_id = :cliente_id
        AND estado = 'no_presentada'
    ");

    $stmt->execute([
        ':cliente_id' => $clienteId
    ]);

    return (int)$stmt->fetchColumn();
}

function contarReservasMismaMesaHora($pdo, $reserva) {
    if (empty($reserva['mesa_id'])) {
        return 0;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM reservas
        WHERE negocio_id = :negocio_id
        AND mesa_id = :mesa_id
        AND fecha = :fecha
        AND hora = :hora
        AND estado IN ('pendiente', 'confirmada', 'modificada')
    ");

    $stmt->execute([
        ':negocio_id' => $reserva['negocio_id'],
        ':mesa_id' => $reserva['mesa_id'],
        ':fecha' => $reserva['fecha'],
        ':hora' => $reserva['hora']
    ]);

    return (int)$stmt->fetchColumn();
}

function contarReservasMismaHora($pdo, $reserva) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM reservas
        WHERE negocio_id = :negocio_id
        AND fecha = :fecha
        AND hora = :hora
        AND estado IN ('pendiente', 'confirmada', 'modificada')
    ");

    $stmt->execute([
        ':negocio_id' => $reserva['negocio_id'],
        ':fecha' => $reserva['fecha'],
        ':hora' => $reserva['hora']
    ]);

    return (int)$stmt->fetchColumn();
}

function limpiarAlertasPendientesReserva($pdo, $reservaId) {
    // ------------------------------------------------------
    // Solo borra alertas pendientes.
    // Las resueltas se conservan para no reaparecer solas.
    // ------------------------------------------------------
    $stmt = $pdo->prepare("
        DELETE FROM alertas_reserva
        WHERE reserva_id = :reserva_id
        AND resuelta = 0
    ");

    $stmt->execute([
        ':reserva_id' => $reservaId
    ]);
}

function sumarAlertaSiInsertada($insertada, &$totalAlertas) {
    if ($insertada) {
        $totalAlertas++;
    }
}

function generarAlertasReserva($pdo, $reservaId) {
    $reserva = cargarReservaParaAlertas($pdo, $reservaId);

    if (!$reserva) {
        return 0;
    }

    limpiarAlertasPendientesReserva($pdo, $reservaId);

    if (in_array($reserva['estado'], ['cancelada', 'no_presentada', 'completada'], true)) {
        return 0;
    }

    $totalAlertas = 0;
    $clienteId = $reserva['cliente_id'] ? (int)$reserva['cliente_id'] : null;
    $tamanoGrupoGrande = obtenerTamanoGrupoGrande($pdo, (int)$reserva['negocio_id']);

    $alergiasReserva = trim($reserva['reserva_alergias'] ?? '');
    $alergiasCliente = trim($reserva['cliente_alergias'] ?? '');

    // ------------------------------------------------------
    // Alerta: alergias o restricciones
    // ------------------------------------------------------
    if ($alergiasReserva !== '' || $alergiasCliente !== '') {
        $insertada = insertarAlertaReserva(
            $pdo,
            $reserva['id'],
            $clienteId,
            'alergias',
            'aviso',
            'Reserva con alergias o restricciones alimentarias. Revisar antes del servicio.'
        );

        sumarAlertaSiInsertada($insertada, $totalAlertas);
    }

    // ------------------------------------------------------
    // Alerta: grupo grande
    // ------------------------------------------------------
    if ((int)$reserva['personas'] >= $tamanoGrupoGrande) {
        $insertada = insertarAlertaReserva(
            $pdo,
            $reserva['id'],
            $clienteId,
            'grupo_grande',
            'aviso',
            'Grupo grande de ' . (int)$reserva['personas'] . ' personas. Conviene revisar mesa y organización del servicio.'
        );

        sumarAlertaSiInsertada($insertada, $totalAlertas);
    }

    // ------------------------------------------------------
    // Alerta: cliente de riesgo
    // ------------------------------------------------------
    $noPresentadas = contarNoPresentadasCliente($pdo, $clienteId);

    if ($noPresentadas >= 2 || $reserva['estado_cliente'] === 'riesgo') {
        $insertada = insertarAlertaReserva(
            $pdo,
            $reserva['id'],
            $clienteId,
            'cliente_riesgo',
            'riesgo',
            'Cliente con historial de no presentadas. Conviene confirmar la reserva antes del servicio.'
        );

        sumarAlertaSiInsertada($insertada, $totalAlertas);
    }

    // ------------------------------------------------------
    // Alerta: reserva sin teléfono
    // ------------------------------------------------------
    if (trim($reserva['cliente_telefono'] ?? '') === '') {
        $insertada = insertarAlertaReserva(
            $pdo,
            $reserva['id'],
            $clienteId,
            'sin_telefono',
            'aviso',
            'Reserva sin teléfono de contacto. Puede dificultar avisos o cambios de última hora.'
        );

        sumarAlertaSiInsertada($insertada, $totalAlertas);
    }

    // ------------------------------------------------------
    // Alerta: reserva sin email
    // ------------------------------------------------------
    if (trim($reserva['cliente_email'] ?? '') === '') {
        $insertada = insertarAlertaReserva(
            $pdo,
            $reserva['id'],
            $clienteId,
            'sin_email',
            'info',
            'Reserva sin email. No se podrá enviar confirmación o recordatorio por correo.'
        );

        sumarAlertaSiInsertada($insertada, $totalAlertas);
    }

    // ------------------------------------------------------
    // Alerta: mesa duplicada
    // ------------------------------------------------------
    $reservasMismaMesaHora = contarReservasMismaMesaHora($pdo, $reserva);

    if ($reservasMismaMesaHora > 1) {
        $insertada = insertarAlertaReserva(
            $pdo,
            $reserva['id'],
            $clienteId,
            'mesa_duplicada',
            'critico',
            'La mesa asignada coincide con otra reserva activa en la misma fecha y hora.'
        );

        sumarAlertaSiInsertada($insertada, $totalAlertas);
    }

    // ------------------------------------------------------
    // Alerta: saturación de reservas a la misma hora
    // ------------------------------------------------------
    $reservasMismaHora = contarReservasMismaHora($pdo, $reserva);

    if ($reservasMismaHora >= 4) {
        $insertada = insertarAlertaReserva(
            $pdo,
            $reserva['id'],
            $clienteId,
            'saturacion_hora',
            'riesgo',
            'Hay varias reservas activas a la misma hora. Revisar posible saturación del servicio.'
        );

        sumarAlertaSiInsertada($insertada, $totalAlertas);
    }

    return $totalAlertas;
}

function generarAlertasPorFecha($pdo, $negocioId, $fecha) {
    $stmt = $pdo->prepare("
        SELECT id
        FROM reservas
        WHERE negocio_id = :negocio_id
        AND fecha = :fecha
        AND estado IN ('pendiente', 'confirmada', 'modificada')
        ORDER BY hora ASC, id ASC
    ");

    $stmt->execute([
        ':negocio_id' => $negocioId,
        ':fecha' => $fecha
    ]);

    $reservas = $stmt->fetchAll();

    $total = 0;

    foreach ($reservas as $reserva) {
        $total += generarAlertasReserva($pdo, (int)$reserva['id']);
    }

    return $total;
}

function contarAlertasAbiertasFecha($pdo, $negocioId, $fecha) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM alertas_reserva a
        INNER JOIN reservas r ON r.id = a.reserva_id
        WHERE r.negocio_id = :negocio_id
        AND r.fecha = :fecha
        AND a.resuelta = 0
    ");

    $stmt->execute([
        ':negocio_id' => $negocioId,
        ':fecha' => $fecha
    ]);

    return (int)$stmt->fetchColumn();
}

function contarAlertasPorNivelFecha($pdo, $negocioId, $fecha, $nivel) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM alertas_reserva a
        INNER JOIN reservas r ON r.id = a.reserva_id
        WHERE r.negocio_id = :negocio_id
        AND r.fecha = :fecha
        AND a.nivel = :nivel
        AND a.resuelta = 0
    ");

    $stmt->execute([
        ':negocio_id' => $negocioId,
        ':fecha' => $fecha,
        ':nivel' => $nivel
    ]);

    return (int)$stmt->fetchColumn();
}