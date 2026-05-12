<?php
// ==========================================================
// KITCHERRY RESERVAS
// Archivo: reservas.php
// Gestión de reservas del negocio
// ==========================================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

protegerPagina();

$usuario = usuarioActual();

$mensaje = '';
$error = '';

function limpiarTexto($valor) {
    return trim($valor ?? '');
}

function seleccionado($valorActual, $valorOpcion) {
    return ($valorActual === $valorOpcion) ? 'selected' : '';
}

function estadoTexto($estado) {
    $textos = [
        'pendiente' => 'Pendiente',
        'confirmada' => 'Confirmada',
        'modificada' => 'Modificada',
        'cancelada' => 'Cancelada',
        'no_presentada' => 'No presentada',
        'completada' => 'Completada'
    ];

    return $textos[$estado] ?? ucfirst($estado);
}

function claseEstado($estado) {
    return 'estado-' . str_replace('_', '-', $estado);
}

function claseCardEstado($estado) {
    return 'card-' . claseEstado($estado);
}

function determinarTurno($hora) {
    if ($hora >= '18:00') {
        return 'cena';
    }

    return 'comida';
}

function obtenerOCrearCliente($pdo, $clienteId, $nombre, $telefono, $email, $preferencias, $alergias) {
    $clienteId = (int)$clienteId;

    if ($clienteId > 0) {
        $stmt = $pdo->prepare("
            UPDATE clientes
            SET
                nombre = :nombre,
                telefono = :telefono,
                email = :email,
                preferencias = :preferencias,
                alergias = :alergias,
                actualizado_en = CURRENT_TIMESTAMP,
                ultima_reserva_en = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmt->execute([
            ':nombre' => $nombre,
            ':telefono' => $telefono,
            ':email' => $email,
            ':preferencias' => $preferencias,
            ':alergias' => $alergias,
            ':id' => $clienteId
        ]);

        return $clienteId;
    }

    $clienteExistente = null;

    if ($email !== '') {
        $stmt = $pdo->prepare("
            SELECT id 
            FROM clientes 
            WHERE email = :email 
            LIMIT 1
        ");

        $stmt->execute([
            ':email' => $email
        ]);

        $clienteExistente = $stmt->fetch();
    }

    if (!$clienteExistente && $telefono !== '') {
        $stmt = $pdo->prepare("
            SELECT id 
            FROM clientes 
            WHERE telefono = :telefono 
            LIMIT 1
        ");

        $stmt->execute([
            ':telefono' => $telefono
        ]);

        $clienteExistente = $stmt->fetch();
    }

    if ($clienteExistente) {
        $clienteId = (int)$clienteExistente['id'];

        $stmt = $pdo->prepare("
            UPDATE clientes
            SET
                nombre = :nombre,
                telefono = :telefono,
                email = :email,
                preferencias = :preferencias,
                alergias = :alergias,
                actualizado_en = CURRENT_TIMESTAMP,
                ultima_reserva_en = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmt->execute([
            ':nombre' => $nombre,
            ':telefono' => $telefono,
            ':email' => $email,
            ':preferencias' => $preferencias,
            ':alergias' => $alergias,
            ':id' => $clienteId
        ]);

        return $clienteId;
    }

    $stmt = $pdo->prepare("
        INSERT INTO clientes (
            nombre,
            telefono,
            email,
            preferencias,
            alergias,
            estado_cliente,
            archivado,
            ultima_reserva_en
        )
        VALUES (
            :nombre,
            :telefono,
            :email,
            :preferencias,
            :alergias,
            'nuevo',
            0,
            CURRENT_TIMESTAMP
        )
    ");

    $stmt->execute([
        ':nombre' => $nombre,
        ':telefono' => $telefono,
        ':email' => $email,
        ':preferencias' => $preferencias,
        ':alergias' => $alergias
    ]);

    return (int)$pdo->lastInsertId();
}

function registrarHistorialReserva($pdo, $reservaId, $usuarioId, $accion, $descripcion) {
    $stmt = $pdo->prepare("
        INSERT INTO historial_reservas (
            reserva_id,
            usuario_id,
            accion,
            descripcion
        )
        VALUES (
            :reserva_id,
            :usuario_id,
            :accion,
            :descripcion
        )
    ");

    $stmt->execute([
        ':reserva_id' => $reservaId,
        ':usuario_id' => $usuarioId,
        ':accion' => $accion,
        ':descripcion' => $descripcion
    ]);
}

$estadosDisponibles = [
    'pendiente',
    'confirmada',
    'modificada',
    'cancelada',
    'no_presentada',
    'completada'
];

$vistasDisponibles = [
    'activas',
    'historico',
    'todas'
];

$negocio = null;
$mesas = [];
$reservas = [];

$filtroFecha = $_GET['fecha'] ?? ($_POST['filtro_fecha'] ?? date('Y-m-d'));
$filtroEstado = $_GET['estado'] ?? ($_POST['filtro_estado'] ?? '');
$filtroVista = $_GET['vista'] ?? ($_POST['filtro_vista'] ?? 'activas');

if (!in_array($filtroVista, $vistasDisponibles, true)) {
    $filtroVista = 'activas';
}

if ($filtroEstado !== '' && !in_array($filtroEstado, $estadosDisponibles, true)) {
    $filtroEstado = '';
}

try {
    $stmt = $pdo->query("
        SELECT id, nombre 
        FROM negocios 
        WHERE activo = 1 
        ORDER BY id ASC 
        LIMIT 1
    ");

    $negocio = $stmt->fetch();

    if ($negocio) {
        $stmt = $pdo->prepare("
            SELECT id, nombre, capacidad, zona
            FROM mesas
            WHERE negocio_id = :negocio_id
            AND activa = 1
            ORDER BY zona ASC, orden ASC, nombre ASC
        ");

        $stmt->execute([
            ':negocio_id' => $negocio['id']
        ]);

        $mesas = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    $error = 'No se pudo cargar la información inicial.';
}

// ----------------------------------------------------------
// Procesar acciones
// ----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $negocio) {

    // ------------------------------------------------------
    // Crear reserva
    // ------------------------------------------------------
    if (($_POST['accion'] ?? '') === 'crear') {
        $nombreCliente = limpiarTexto($_POST['nombre_cliente'] ?? '');
        $telefono = limpiarTexto($_POST['telefono'] ?? '');
        $email = limpiarTexto($_POST['email'] ?? '');

        $fecha = limpiarTexto($_POST['fecha'] ?? '');
        $hora = limpiarTexto($_POST['hora'] ?? '');
        $personas = (int)($_POST['personas'] ?? 0);
        $mesaId = (int)($_POST['mesa_id'] ?? 0);
        $estado = limpiarTexto($_POST['estado'] ?? 'pendiente');

        $observaciones = limpiarTexto($_POST['observaciones'] ?? '');
        $alergias = limpiarTexto($_POST['alergias'] ?? '');
        $preferencias = limpiarTexto($_POST['preferencias'] ?? '');

        if ($nombreCliente === '') {
            $error = 'El nombre del cliente es obligatorio.';
        } elseif ($fecha === '') {
            $error = 'La fecha de la reserva es obligatoria.';
        } elseif ($hora === '') {
            $error = 'La hora de la reserva es obligatoria.';
        } elseif ($personas <= 0) {
            $error = 'El número de personas debe ser mayor que 0.';
        } elseif (!in_array($estado, $estadosDisponibles, true)) {
            $error = 'El estado seleccionado no es válido.';
        } else {
            try {
                $pdo->beginTransaction();

                $clienteId = obtenerOCrearCliente(
                    $pdo,
                    0,
                    $nombreCliente,
                    $telefono,
                    $email,
                    $preferencias,
                    $alergias
                );

                $turno = determinarTurno($hora);
                $mesaIdGuardar = ($mesaId > 0) ? $mesaId : null;

                $stmt = $pdo->prepare("
                    INSERT INTO reservas (
                        negocio_id,
                        cliente_id,
                        mesa_id,
                        fecha,
                        hora,
                        turno,
                        personas,
                        estado,
                        origen,
                        observaciones,
                        alergias,
                        preferencias
                    )
                    VALUES (
                        :negocio_id,
                        :cliente_id,
                        :mesa_id,
                        :fecha,
                        :hora,
                        :turno,
                        :personas,
                        :estado,
                        'manual',
                        :observaciones,
                        :alergias,
                        :preferencias
                    )
                ");

                $stmt->execute([
                    ':negocio_id' => $negocio['id'],
                    ':cliente_id' => $clienteId,
                    ':mesa_id' => $mesaIdGuardar,
                    ':fecha' => $fecha,
                    ':hora' => $hora,
                    ':turno' => $turno,
                    ':personas' => $personas,
                    ':estado' => $estado,
                    ':observaciones' => $observaciones,
                    ':alergias' => $alergias,
                    ':preferencias' => $preferencias
                ]);

                $reservaId = (int)$pdo->lastInsertId();

                registrarHistorialReserva(
                    $pdo,
                    $reservaId,
                    $usuario['id'],
                    'reserva_creada',
                    'Reserva creada manualmente desde el panel.'
                );

                $pdo->commit();

                $mensaje = 'Reserva creada correctamente.';
                $filtroFecha = $fecha;

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = 'No se pudo crear la reserva.';
            }
        }
    }

    // ------------------------------------------------------
    // Guardar una reserva desde el desplegable
    // ------------------------------------------------------
    if (($_POST['accion'] ?? '') === 'guardar_una') {
        $reservaId = (int)($_POST['reserva_id'] ?? 0);
        $clienteId = (int)($_POST['cliente_id'] ?? 0);

        $nombreCliente = limpiarTexto($_POST['nombre_cliente'] ?? '');
        $telefono = limpiarTexto($_POST['telefono'] ?? '');
        $email = limpiarTexto($_POST['email'] ?? '');

        $fecha = limpiarTexto($_POST['fecha'] ?? '');
        $hora = limpiarTexto($_POST['hora'] ?? '');
        $personas = (int)($_POST['personas'] ?? 0);
        $mesaId = (int)($_POST['mesa_id'] ?? 0);
        $estado = limpiarTexto($_POST['estado'] ?? 'pendiente');

        $observaciones = limpiarTexto($_POST['observaciones'] ?? '');
        $alergias = limpiarTexto($_POST['alergias'] ?? '');
        $preferencias = limpiarTexto($_POST['preferencias'] ?? '');

        if ($reservaId <= 0) {
            $error = 'La reserva seleccionada no es válida.';
        } elseif ($nombreCliente === '') {
            $error = 'El nombre del cliente es obligatorio.';
        } elseif ($fecha === '' || $hora === '') {
            $error = 'La fecha y la hora son obligatorias.';
        } elseif ($personas <= 0) {
            $error = 'El número de personas debe ser mayor que 0.';
        } elseif (!in_array($estado, $estadosDisponibles, true)) {
            $error = 'El estado seleccionado no es válido.';
        } else {
            try {
                $pdo->beginTransaction();

                $clienteIdFinal = obtenerOCrearCliente(
                    $pdo,
                    $clienteId,
                    $nombreCliente,
                    $telefono,
                    $email,
                    $preferencias,
                    $alergias
                );

                $mesaIdGuardar = ($mesaId > 0) ? $mesaId : null;

                $stmt = $pdo->prepare("
                    UPDATE reservas
                    SET
                        cliente_id = :cliente_id,
                        mesa_id = :mesa_id,
                        fecha = :fecha,
                        hora = :hora,
                        turno = :turno,
                        personas = :personas,
                        estado = :estado,
                        observaciones = :observaciones,
                        alergias = :alergias,
                        preferencias = :preferencias,
                        actualizado_en = CURRENT_TIMESTAMP
                    WHERE id = :id
                    AND negocio_id = :negocio_id
                ");

                $stmt->execute([
                    ':cliente_id' => $clienteIdFinal,
                    ':mesa_id' => $mesaIdGuardar,
                    ':fecha' => $fecha,
                    ':hora' => $hora,
                    ':turno' => determinarTurno($hora),
                    ':personas' => $personas,
                    ':estado' => $estado,
                    ':observaciones' => $observaciones,
                    ':alergias' => $alergias,
                    ':preferencias' => $preferencias,
                    ':id' => $reservaId,
                    ':negocio_id' => $negocio['id']
                ]);

                registrarHistorialReserva(
                    $pdo,
                    $reservaId,
                    $usuario['id'],
                    'reserva_actualizada',
                    'Reserva actualizada desde el panel.'
                );

                $pdo->commit();

                $mensaje = 'Reserva actualizada correctamente.';

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = 'No se pudo actualizar la reserva.';
            }
        }
    }

    // ------------------------------------------------------
    // Cambio rápido de estado
    // ------------------------------------------------------
    if (isset($_POST['accion_estado'])) {
        $accionEstado = limpiarTexto($_POST['accion_estado']);
        $partes = explode(':', $accionEstado);

        $nuevoEstado = $partes[0] ?? '';
        $reservaId = (int)($partes[1] ?? 0);

        if ($reservaId <= 0) {
            $error = 'La reserva seleccionada no es válida.';
        } elseif (!in_array($nuevoEstado, $estadosDisponibles, true)) {
            $error = 'El estado seleccionado no es válido.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE reservas
                    SET 
                        estado = :estado,
                        actualizado_en = CURRENT_TIMESTAMP
                    WHERE id = :id
                    AND negocio_id = :negocio_id
                ");

                $stmt->execute([
                    ':estado' => $nuevoEstado,
                    ':id' => $reservaId,
                    ':negocio_id' => $negocio['id']
                ]);

                registrarHistorialReserva(
                    $pdo,
                    $reservaId,
                    $usuario['id'],
                    'estado_actualizado',
                    'Estado cambiado a ' . estadoTexto($nuevoEstado) . '.'
                );

                $mensaje = 'Estado de reserva actualizado correctamente.';

            } catch (PDOException $e) {
                $error = 'No se pudo cambiar el estado de la reserva.';
            }
        }
    }
}

// ----------------------------------------------------------
// Cargar reservas
// ----------------------------------------------------------
if ($negocio) {
    try {
        $sql = "
            SELECT
                r.id,
                r.cliente_id,
                r.mesa_id,
                r.fecha,
                r.hora,
                r.turno,
                r.personas,
                r.estado,
                r.observaciones,
                r.alergias,
                r.preferencias,

                c.nombre AS cliente_nombre,
                c.telefono AS cliente_telefono,
                c.email AS cliente_email,

                m.nombre AS mesa_nombre,
                m.zona AS mesa_zona

            FROM reservas r
            LEFT JOIN clientes c ON c.id = r.cliente_id
            LEFT JOIN mesas m ON m.id = r.mesa_id
            WHERE r.negocio_id = :negocio_id
        ";

        $params = [
            ':negocio_id' => $negocio['id']
        ];

        if ($filtroFecha !== '') {
            $sql .= " AND r.fecha = :fecha ";
            $params[':fecha'] = $filtroFecha;
        }

        if ($filtroEstado !== '') {
            $sql .= " AND r.estado = :estado ";
            $params[':estado'] = $filtroEstado;
        } else {
            if ($filtroVista === 'activas') {
                $sql .= " AND r.estado NOT IN ('cancelada', 'no_presentada', 'completada') ";
            }

            if ($filtroVista === 'historico') {
                $sql .= " AND r.estado IN ('cancelada', 'no_presentada', 'completada') ";
            }
        }

        $sql .= " ORDER BY r.fecha ASC, r.hora ASC, r.id ASC ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $reservas = $stmt->fetchAll();

    } catch (PDOException $e) {
        $error = 'No se pudieron cargar las reservas.';
    }
}

$totalReservas = count($reservas);
$totalComensales = 0;
$totalPendientes = 0;
$totalConfirmadas = 0;

foreach ($reservas as $reserva) {
    $totalComensales += (int)$reserva['personas'];

    if ($reserva['estado'] === 'pendiente') {
        $totalPendientes++;
    }

    if ($reserva['estado'] === 'confirmada') {
        $totalConfirmadas++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry Reservas | Reservas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- CSS principal -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="app-layout">

        <aside class="sidebar">

            <div class="sidebar-brand">
                <img src="assets/img/logo.png" alt="Kitcherry" class="sidebar-logo">

                <div class="marca-texto">
                    <strong class="marca-nombre">
                        <span class="kit">KIT</span><span class="cherry">CHERRY</span>
                    </strong>
                    <span class="marca-producto">Reservas</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="reservas.php" class="active">Reservas</a>
                <a href="mesas.php">Mesas</a>
                <a href="#">Clientes</a>
                <a href="configuracion.php">Configuración</a>
            </nav>

            <div class="sidebar-footer">
                <span><?php echo htmlspecialchars($usuario['nombre']); ?></span>
                <a href="logout.php">Cerrar sesión</a>
            </div>

        </aside>

        <main class="main-content">

            <header class="topbar">
                <div>
                    <p class="eyebrow">Gestión diaria</p>
                    <h1>Reservas</h1>
                </div>
            </header>

            <?php if (!$negocio): ?>
                <section class="panel-card aviso-configuracion">
                    <h3>Primero configura el negocio</h3>
                    <p>
                        Antes de gestionar reservas, debes completar la configuración principal del restaurante.
                    </p>
                    <a href="configuracion.php" class="btn btn-primary btn-inline">
                        Ir a configuración
                    </a>
                </section>
            <?php endif; ?>

            <?php if ($mensaje !== ''): ?>
                <div class="alerta alerta-exito">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alerta alerta-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($negocio): ?>

                <section class="stats-grid">

                    <article class="stat-card">
                        <span class="stat-label">Reservas visibles</span>
                        <strong><?php echo $totalReservas; ?></strong>
                        <p>Reservas mostradas según la vista y filtros activos.</p>
                    </article>

                    <article class="stat-card">
                        <span class="stat-label">Comensales</span>
                        <strong><?php echo $totalComensales; ?></strong>
                        <p>Total de personas previstas en las reservas visibles.</p>
                    </article>

                    <article class="stat-card">
                        <span class="stat-label">Pendientes</span>
                        <strong><?php echo $totalPendientes; ?></strong>
                        <p>Reservas pendientes de revisión o confirmación.</p>
                    </article>

                    <article class="stat-card">
                        <span class="stat-label">Confirmadas</span>
                        <strong><?php echo $totalConfirmadas; ?></strong>
                        <p>Reservas ya confirmadas para el servicio.</p>
                    </article>

                </section>

                <section class="content-grid reservas-layout">

                    <article class="panel-card form-card">
                        <h3>Nueva reserva</h3>

                        <form method="POST" class="config-form reserva-form">
                            <input type="hidden" name="accion" value="crear">

                            <div class="form-group">
                                <label for="nombre_cliente">Nombre del cliente *</label>
                                <input 
                                    type="text" 
                                    id="nombre_cliente" 
                                    name="nombre_cliente" 
                                    placeholder="Ej: Laura Pérez"
                                    required
                                >
                            </div>

                            <div class="form-grid form-grid-small">
                                <div class="form-group">
                                    <label for="telefono">Teléfono</label>
                                    <input 
                                        type="text" 
                                        id="telefono" 
                                        name="telefono" 
                                        placeholder="Ej: 600 000 000"
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input 
                                        type="email" 
                                        id="email" 
                                        name="email" 
                                        placeholder="cliente@email.com"
                                    >
                                </div>
                            </div>

                            <div class="form-grid form-grid-small">
                                <div class="form-group">
                                    <label for="fecha">Fecha *</label>
                                    <input 
                                        type="date" 
                                        id="fecha" 
                                        name="fecha" 
                                        value="<?php echo htmlspecialchars($filtroFecha); ?>"
                                        required
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="hora">Hora *</label>
                                    <input 
                                        type="time" 
                                        id="hora" 
                                        name="hora"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="form-grid form-grid-small">
                                <div class="form-group">
                                    <label for="personas">Personas *</label>
                                    <input 
                                        type="number" 
                                        id="personas" 
                                        name="personas"
                                        min="1"
                                        required
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="estado">Estado</label>
                                    <select id="estado" name="estado">
                                        <?php foreach ($estadosDisponibles as $estado): ?>
                                            <option value="<?php echo htmlspecialchars($estado); ?>">
                                                <?php echo estadoTexto($estado); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="mesa_id">Mesa asignada</label>
                                <select id="mesa_id" name="mesa_id">
                                    <option value="0">Sin asignar</option>
                                    <?php foreach ($mesas as $mesa): ?>
                                        <option value="<?php echo (int)$mesa['id']; ?>">
                                            <?php echo htmlspecialchars($mesa['nombre']); ?> · 
                                            <?php echo htmlspecialchars($mesa['zona']); ?> · 
                                            <?php echo (int)$mesa['capacidad']; ?> pax
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="preferencias">Preferencias</label>
                                <input 
                                    type="text" 
                                    id="preferencias" 
                                    name="preferencias"
                                    placeholder="Ej: terraza, mesa tranquila..."
                                >
                            </div>

                            <div class="form-group">
                                <label for="alergias">Alergias</label>
                                <input 
                                    type="text" 
                                    id="alergias" 
                                    name="alergias"
                                    placeholder="Ej: sin gluten, frutos secos..."
                                >
                            </div>

                            <div class="form-group">
                                <label for="observaciones">Observaciones</label>
                                <textarea 
                                    id="observaciones" 
                                    name="observaciones"
                                    rows="3"
                                    placeholder="Notas internas de la reserva"
                                ></textarea>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    Crear reserva
                                </button>
                            </div>
                        </form>
                    </article>

                    <article class="panel-card">
                        <div class="panel-header-line">
                            <h3>Reservas registradas</h3>

                            <form method="GET" class="filter-bar">
                                <input 
                                    type="date" 
                                    name="fecha" 
                                    value="<?php echo htmlspecialchars($filtroFecha); ?>"
                                >

                                <select name="vista">
                                    <option value="activas" <?php echo seleccionado($filtroVista, 'activas'); ?>>Activas</option>
                                    <option value="historico" <?php echo seleccionado($filtroVista, 'historico'); ?>>Histórico</option>
                                    <option value="todas" <?php echo seleccionado($filtroVista, 'todas'); ?>>Todas</option>
                                </select>

                                <select name="estado">
                                    <option value="">Todos los estados</option>
                                    <?php foreach ($estadosDisponibles as $estado): ?>
                                        <option 
                                            value="<?php echo htmlspecialchars($estado); ?>"
                                            <?php echo seleccionado($filtroEstado, $estado); ?>
                                        >
                                            <?php echo estadoTexto($estado); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <button type="submit" class="btn-mini btn-save">
                                    Filtrar
                                </button>
                            </form>
                        </div>

                        <?php if (empty($reservas)): ?>
                            <p>
                                No hay reservas registradas para los filtros seleccionados.
                            </p>
                        <?php else: ?>

                            <div class="reservas-cards">
                                <?php foreach ($reservas as $reserva): ?>
                                    <form method="POST" class="reserva-card-form">

                                        <input type="hidden" name="filtro_fecha" value="<?php echo htmlspecialchars($filtroFecha); ?>">
                                        <input type="hidden" name="filtro_estado" value="<?php echo htmlspecialchars($filtroEstado); ?>">
                                        <input type="hidden" name="filtro_vista" value="<?php echo htmlspecialchars($filtroVista); ?>">

                                        <input type="hidden" name="reserva_id" value="<?php echo (int)$reserva['id']; ?>">
                                        <input type="hidden" name="cliente_id" value="<?php echo (int)$reserva['cliente_id']; ?>">

                                        <article class="reserva-card <?php echo claseCardEstado($reserva['estado']); ?>">

                                            <div class="reserva-compacta">

                                                <div class="reserva-main">
                                                    <span class="reserva-hora">
                                                        <?php echo htmlspecialchars($reserva['hora']); ?>
                                                    </span>

                                                    <div>
                                                        <h4>
                                                            <?php echo htmlspecialchars($reserva['cliente_nombre'] ?? 'Cliente sin nombre'); ?>
                                                        </h4>

                                                        <p>
                                                            <?php echo (int)$reserva['personas']; ?> personas · 
                                                            <?php echo htmlspecialchars($reserva['mesa_nombre'] ?? 'Sin mesa'); ?>

                                                            <?php if (!empty($reserva['mesa_zona'])): ?>
                                                                · <?php echo htmlspecialchars($reserva['mesa_zona']); ?>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="reserva-contacto">
                                                    <strong>Teléfono</strong>
                                                    <span>
                                                        <?php echo htmlspecialchars($reserva['cliente_telefono'] ?: 'Sin teléfono'); ?>
                                                    </span>
                                                </div>

                                                <div class="reserva-alertas">
                                                    <?php if (!empty($reserva['alergias'])): ?>
                                                        <span class="warning-pill">Alergias</span>
                                                    <?php endif; ?>

                                                    <?php if (!empty($reserva['observaciones'])): ?>
                                                        <span class="info-pill">Notas</span>
                                                    <?php endif; ?>
                                                </div>

                                                <span class="estado-pill reserva-pill <?php echo claseEstado($reserva['estado']); ?>">
                                                    <?php echo estadoTexto($reserva['estado']); ?>
                                                </span>

                                            </div>

                                            <div class="reserva-card-actions">
                                                <button 
                                                    type="submit" 
                                                    name="accion_estado" 
                                                    value="confirmada:<?php echo (int)$reserva['id']; ?>"
                                                    class="btn-mini btn-save"
                                                >
                                                    Confirmar
                                                </button>

                                                <button 
                                                    type="submit" 
                                                    name="accion_estado" 
                                                    value="cancelada:<?php echo (int)$reserva['id']; ?>"
                                                    class="btn-mini btn-muted"
                                                >
                                                    Cancelar
                                                </button>

                                                <button 
                                                    type="submit" 
                                                    name="accion_estado" 
                                                    value="no_presentada:<?php echo (int)$reserva['id']; ?>"
                                                    class="btn-mini btn-muted"
                                                >
                                                    No presentada
                                                </button>
                                            </div>

                                            <details class="reserva-detalles">
                                                <summary>Ver detalles / editar</summary>

                                                <div class="reserva-edit-grid">

                                                    <div class="form-group">
                                                        <label>Cliente</label>
                                                        <input 
                                                            type="text" 
                                                            name="nombre_cliente" 
                                                            value="<?php echo htmlspecialchars($reserva['cliente_nombre'] ?? ''); ?>"
                                                            required
                                                        >
                                                    </div>

                                                    <div class="form-group">
                                                        <label>Teléfono</label>
                                                        <input 
                                                            type="text" 
                                                            name="telefono" 
                                                            value="<?php echo htmlspecialchars($reserva['cliente_telefono'] ?? ''); ?>"
                                                        >
                                                    </div>

                                                    <div class="form-group">
                                                        <label>Email</label>
                                                        <input 
                                                            type="email" 
                                                            name="email" 
                                                            value="<?php echo htmlspecialchars($reserva['cliente_email'] ?? ''); ?>"
                                                        >
                                                    </div>

                                                    <div class="form-group">
                                                        <label>Fecha</label>
                                                        <input 
                                                            type="date" 
                                                            name="fecha" 
                                                            value="<?php echo htmlspecialchars($reserva['fecha']); ?>"
                                                            required
                                                        >
                                                    </div>

                                                    <div class="form-group">
                                                        <label>Hora</label>
                                                        <input 
                                                            type="time" 
                                                            name="hora" 
                                                            value="<?php echo htmlspecialchars($reserva['hora']); ?>"
                                                            required
                                                        >
                                                    </div>

                                                    <div class="form-group">
                                                        <label>Personas</label>
                                                        <input 
                                                            type="number" 
                                                            name="personas" 
                                                            min="1"
                                                            value="<?php echo (int)$reserva['personas']; ?>"
                                                            required
                                                        >
                                                    </div>

                                                    <div class="form-group">
                                                        <label>Mesa</label>
                                                        <select name="mesa_id">
                                                            <option value="0">Sin asignar</option>
                                                            <?php foreach ($mesas as $mesa): ?>
                                                                <option 
                                                                    value="<?php echo (int)$mesa['id']; ?>"
                                                                    <?php echo ((int)$reserva['mesa_id'] === (int)$mesa['id']) ? 'selected' : ''; ?>
                                                                >
                                                                    <?php echo htmlspecialchars($mesa['nombre']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="form-group">
                                                        <label>Estado</label>
                                                        <select name="estado">
                                                            <?php foreach ($estadosDisponibles as $estado): ?>
                                                                <option 
                                                                    value="<?php echo htmlspecialchars($estado); ?>"
                                                                    <?php echo seleccionado($reserva['estado'], $estado); ?>
                                                                >
                                                                    <?php echo estadoTexto($estado); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                </div>

                                                <div class="reserva-notes-grid">
                                                    <div class="form-group">
                                                        <label>Preferencias</label>
                                                        <input 
                                                            type="text" 
                                                            name="preferencias" 
                                                            value="<?php echo htmlspecialchars($reserva['preferencias'] ?? ''); ?>"
                                                            placeholder="Preferencias del cliente"
                                                        >
                                                    </div>

                                                    <div class="form-group">
                                                        <label>Alergias</label>
                                                        <input 
                                                            type="text" 
                                                            name="alergias" 
                                                            value="<?php echo htmlspecialchars($reserva['alergias'] ?? ''); ?>"
                                                            placeholder="Alergias o restricciones"
                                                        >
                                                    </div>

                                                    <div class="form-group reserva-observaciones">
                                                        <label>Observaciones</label>
                                                        <textarea 
                                                            name="observaciones"
                                                            rows="2"
                                                            placeholder="Notas internas"
                                                        ><?php echo htmlspecialchars($reserva['observaciones'] ?? ''); ?></textarea>
                                                    </div>
                                                </div>

                                                <div class="form-actions">
                                                    <button 
                                                        type="submit" 
                                                        name="accion" 
                                                        value="guardar_una" 
                                                        class="btn btn-primary btn-inline"
                                                    >
                                                        Guardar cambios de esta reserva
                                                    </button>
                                                </div>

                                            </details>

                                        </article>

                                    </form>
                                <?php endforeach; ?>
                            </div>

                        <?php endif; ?>

                    </article>

                </section>

            <?php endif; ?>

        </main>

    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>