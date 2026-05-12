<?php
// ==========================================================
// KITCHERRY RESERVAS
// Archivo: clientes.php
// Clientes y seguimiento básico de fidelidad
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

function estadoTextoCliente($estado) {
    $textos = [
        'nuevo' => 'Nuevo',
        'recurrente' => 'Recurrente',
        'habitual' => 'Habitual',
        'destacado' => 'Destacado',
        'inactivo' => 'Inactivo',
        'riesgo' => 'Riesgo'
    ];

    return $textos[$estado] ?? ucfirst($estado);
}

function claseEstadoCliente($estado) {
    return 'cliente-estado-' . str_replace('_', '-', $estado);
}

function estadoTextoReserva($estado) {
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

function claseEstadoReserva($estado) {
    return 'estado-' . str_replace('_', '-', $estado);
}

function formatearFecha($fecha) {
    if (!$fecha) {
        return 'Sin fecha';
    }

    $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);

    if (!$fechaObj) {
        return $fecha;
    }

    return $fechaObj->format('d/m/Y');
}

function actualizarEstadosClientes($pdo) {
    $limiteInactividad = date('Y-m-d', strtotime('-120 days'));

    $stmt = $pdo->query("
        SELECT
            c.id,
            c.estado_cliente,
            COUNT(r.id) AS total_reservas,
            SUM(CASE WHEN r.estado = 'no_presentada' THEN 1 ELSE 0 END) AS total_no_presentadas,
            MAX(r.fecha) AS ultima_reserva
        FROM clientes c
        LEFT JOIN reservas r ON r.cliente_id = c.id
        WHERE c.archivado = 0
        GROUP BY c.id
    ");

    $clientes = $stmt->fetchAll();

    $stmtUpdate = $pdo->prepare("
        UPDATE clientes
        SET
            estado_cliente = :estado_cliente,
            ultima_reserva_en = :ultima_reserva_en,
            actualizado_en = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    foreach ($clientes as $cliente) {
        $estadoActual = $cliente['estado_cliente'];
        $totalReservas = (int)$cliente['total_reservas'];
        $totalNoPresentadas = (int)$cliente['total_no_presentadas'];
        $ultimaReserva = $cliente['ultima_reserva'];

        if ($estadoActual === 'destacado') {
            $nuevoEstado = 'destacado';
        } elseif ($totalNoPresentadas >= 2) {
            $nuevoEstado = 'riesgo';
        } elseif ($ultimaReserva && $ultimaReserva < $limiteInactividad) {
            $nuevoEstado = 'inactivo';
        } elseif ($totalReservas >= 5) {
            $nuevoEstado = 'habitual';
        } elseif ($totalReservas >= 2) {
            $nuevoEstado = 'recurrente';
        } else {
            $nuevoEstado = 'nuevo';
        }

        $stmtUpdate->execute([
            ':estado_cliente' => $nuevoEstado,
            ':ultima_reserva_en' => $ultimaReserva,
            ':id' => $cliente['id']
        ]);
    }
}

$estadosCliente = [
    'nuevo',
    'recurrente',
    'habitual',
    'destacado',
    'inactivo',
    'riesgo'
];

$vistasCliente = [
    'activos' => 'Activos',
    'archivados' => 'Archivados',
    'todos' => 'Todos'
];

$filtroEstado = $_GET['estado'] ?? '';
$vistaClientes = $_GET['vista'] ?? 'activos';
$busqueda = limpiarTexto($_GET['q'] ?? '');
$clienteSeleccionadoId = (int)($_GET['cliente_id'] ?? 0);

if ($filtroEstado !== '' && !in_array($filtroEstado, $estadosCliente, true)) {
    $filtroEstado = '';
}

if (!array_key_exists($vistaClientes, $vistasCliente)) {
    $vistaClientes = 'activos';
}

// ----------------------------------------------------------
// Procesar acciones
// ----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (($_POST['accion'] ?? '') === 'actualizar_estados') {
        try {
            actualizarEstadosClientes($pdo);
            $mensaje = 'Estados de clientes actualizados correctamente.';
        } catch (PDOException $e) {
            $error = 'No se pudieron actualizar los estados de clientes.';
        }
    }

    if (($_POST['accion'] ?? '') === 'guardar_cliente') {
        $clienteId = (int)($_POST['cliente_id'] ?? 0);
        $nombre = limpiarTexto($_POST['nombre'] ?? '');
        $telefono = limpiarTexto($_POST['telefono'] ?? '');
        $email = limpiarTexto($_POST['email'] ?? '');
        $estadoCliente = limpiarTexto($_POST['estado_cliente'] ?? 'nuevo');
        $preferencias = limpiarTexto($_POST['preferencias'] ?? '');
        $alergias = limpiarTexto($_POST['alergias'] ?? '');
        $notasInternas = limpiarTexto($_POST['notas_internas'] ?? '');

        if ($clienteId <= 0) {
            $error = 'El cliente seleccionado no es válido.';
        } elseif ($nombre === '') {
            $error = 'El nombre del cliente es obligatorio.';
        } elseif (!in_array($estadoCliente, $estadosCliente, true)) {
            $error = 'El estado del cliente no es válido.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE clientes
                    SET
                        nombre = :nombre,
                        telefono = :telefono,
                        email = :email,
                        estado_cliente = :estado_cliente,
                        preferencias = :preferencias,
                        alergias = :alergias,
                        notas_internas = :notas_internas,
                        actualizado_en = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':nombre' => $nombre,
                    ':telefono' => $telefono,
                    ':email' => $email,
                    ':estado_cliente' => $estadoCliente,
                    ':preferencias' => $preferencias,
                    ':alergias' => $alergias,
                    ':notas_internas' => $notasInternas,
                    ':id' => $clienteId
                ]);

                $mensaje = 'Cliente actualizado correctamente.';
                $clienteSeleccionadoId = $clienteId;

            } catch (PDOException $e) {
                $error = 'No se pudo actualizar el cliente.';
            }
        }
    }

    if (($_POST['accion'] ?? '') === 'archivar_cliente') {
        $clienteId = (int)($_POST['cliente_id'] ?? 0);

        if ($clienteId <= 0) {
            $error = 'El cliente seleccionado no es válido.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE clientes
                    SET
                        archivado = 1,
                        actualizado_en = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':id' => $clienteId
                ]);

                $mensaje = 'Cliente archivado correctamente.';
                $clienteSeleccionadoId = 0;
                $vistaClientes = 'archivados';

            } catch (PDOException $e) {
                $error = 'No se pudo archivar el cliente.';
            }
        }
    }

    if (($_POST['accion'] ?? '') === 'reactivar_cliente') {
        $clienteId = (int)($_POST['cliente_id'] ?? 0);

        if ($clienteId <= 0) {
            $error = 'El cliente seleccionado no es válido.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE clientes
                    SET
                        archivado = 0,
                        actualizado_en = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':id' => $clienteId
                ]);

                $mensaje = 'Cliente reactivado correctamente.';
                $clienteSeleccionadoId = $clienteId;
                $vistaClientes = 'activos';

            } catch (PDOException $e) {
                $error = 'No se pudo reactivar el cliente.';
            }
        }
    }
}

// ----------------------------------------------------------
// Actualizar estados automáticamente al entrar
// ----------------------------------------------------------
try {
    actualizarEstadosClientes($pdo);
} catch (PDOException $e) {
    // Si falla, no rompemos la página.
}

// ----------------------------------------------------------
// Estadísticas generales
// ----------------------------------------------------------
$totalClientes = 0;
$totalRecurrentes = 0;
$totalHabituales = 0;
$totalRiesgo = 0;
$totalArchivados = 0;

try {
    $totalClientes = (int)$pdo->query("
        SELECT COUNT(*) 
        FROM clientes 
        WHERE archivado = 0
    ")->fetchColumn();

    $totalRecurrentes = (int)$pdo->query("
        SELECT COUNT(*) 
        FROM clientes 
        WHERE archivado = 0 
        AND estado_cliente IN ('recurrente', 'habitual', 'destacado')
    ")->fetchColumn();

    $totalHabituales = (int)$pdo->query("
        SELECT COUNT(*) 
        FROM clientes 
        WHERE archivado = 0 
        AND estado_cliente IN ('habitual', 'destacado')
    ")->fetchColumn();

    $totalRiesgo = (int)$pdo->query("
        SELECT COUNT(*) 
        FROM clientes 
        WHERE archivado = 0 
        AND estado_cliente = 'riesgo'
    ")->fetchColumn();

    $totalArchivados = (int)$pdo->query("
        SELECT COUNT(*) 
        FROM clientes 
        WHERE archivado = 1
    ")->fetchColumn();

} catch (PDOException $e) {
    $error = 'No se pudieron cargar las estadísticas de clientes.';
}

// ----------------------------------------------------------
// Cargar clientes
// ----------------------------------------------------------
$clientes = [];

try {
    $sql = "
        SELECT
            c.id,
            c.nombre,
            c.telefono,
            c.email,
            c.preferencias,
            c.alergias,
            c.notas_internas,
            c.estado_cliente,
            c.ultima_reserva_en,
            c.archivado,

            COUNT(r.id) AS total_reservas,
            SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) AS total_completadas,
            SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) AS total_canceladas,
            SUM(CASE WHEN r.estado = 'no_presentada' THEN 1 ELSE 0 END) AS total_no_presentadas,
            MAX(r.fecha) AS ultima_reserva

        FROM clientes c
        LEFT JOIN reservas r ON r.cliente_id = c.id
        WHERE 1 = 1
    ";

    $params = [];

    if ($vistaClientes === 'activos') {
        $sql .= " AND c.archivado = 0 ";
    } elseif ($vistaClientes === 'archivados') {
        $sql .= " AND c.archivado = 1 ";
    }

    if ($filtroEstado !== '') {
        $sql .= " AND c.estado_cliente = :estado_cliente ";
        $params[':estado_cliente'] = $filtroEstado;
    }

    if ($busqueda !== '') {
        $sql .= "
            AND (
                c.nombre LIKE :busqueda
                OR c.telefono LIKE :busqueda
                OR c.email LIKE :busqueda
            )
        ";

        $params[':busqueda'] = '%' . $busqueda . '%';
    }

    $sql .= "
        GROUP BY c.id
        ORDER BY
            c.archivado ASC,
            CASE c.estado_cliente
                WHEN 'riesgo' THEN 1
                WHEN 'destacado' THEN 2
                WHEN 'habitual' THEN 3
                WHEN 'recurrente' THEN 4
                WHEN 'nuevo' THEN 5
                WHEN 'inactivo' THEN 6
                ELSE 7
            END,
            ultima_reserva DESC,
            c.nombre ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $clientes = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = 'No se pudieron cargar los clientes.';
}

// ----------------------------------------------------------
// Cargar cliente seleccionado e historial
// ----------------------------------------------------------
$clienteDetalle = null;
$historialReservas = [];

if ($clienteSeleccionadoId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM clientes
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $clienteSeleccionadoId
        ]);

        $clienteDetalle = $stmt->fetch();

        if ($clienteDetalle) {
            $stmt = $pdo->prepare("
                SELECT
                    r.id,
                    r.fecha,
                    r.hora,
                    r.personas,
                    r.estado,
                    r.observaciones,
                    r.alergias,
                    r.preferencias,
                    m.nombre AS mesa_nombre,
                    m.zona AS mesa_zona
                FROM reservas r
                LEFT JOIN mesas m ON m.id = r.mesa_id
                WHERE r.cliente_id = :cliente_id
                ORDER BY r.fecha DESC, r.hora DESC
                LIMIT 20
            ");

            $stmt->execute([
                ':cliente_id' => $clienteSeleccionadoId
            ]);

            $historialReservas = $stmt->fetchAll();
        }

    } catch (PDOException $e) {
        $error = 'No se pudo cargar el historial del cliente.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry Reservas | Clientes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- CSS principal -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="app-layout">

        <aside class="sidebar">

        <a href="dashboard.php" class="sidebar-brand sidebar-brand-link">
            <img src="assets/img/logo.png" alt="Kitcherry" class="sidebar-logo">

            <div class="marca-texto">
                <strong class="marca-nombre">
                    <span class="kit">KIT</span><span class="cherry">CHERRY</span>
                </strong>
                <span class="marca-producto">Reservas</span>
            </div>
        </a>
            <<nav class="sidebar-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="reservas.php">Reservas</a>
                <a href="mesas.php">Mesas</a>
                <a href="clientes.php">Clientes</a>
                <a href="alertas.php">Alertas</a>
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
                    <p class="eyebrow">Seguimiento básico</p>
                    <h1>Clientes</h1>
                </div>
            </header>

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

            <section class="stats-grid">

                <article class="stat-card">
                    <span class="stat-label">Clientes activos</span>
                    <strong><?php echo $totalClientes; ?></strong>
                    <p>Archivados: <?php echo $totalArchivados; ?></p>
                </article>

                <article class="stat-card">
                    <span class="stat-label">Recurrentes</span>
                    <strong><?php echo $totalRecurrentes; ?></strong>
                    <p>Clientes con varias reservas registradas.</p>
                </article>

                <article class="stat-card">
                    <span class="stat-label">Habituales</span>
                    <strong><?php echo $totalHabituales; ?></strong>
                    <p>Clientes frecuentes o destacados.</p>
                </article>

                <article class="stat-card">
                    <span class="stat-label">Riesgo</span>
                    <strong><?php echo $totalRiesgo; ?></strong>
                    <p>Clientes con varias no presentaciones.</p>
                </article>

            </section>

            <section class="clientes-toolbar panel-card">

                <form method="GET" class="clientes-filter-form">

                    <div class="form-group">
                        <label for="q">Buscar cliente</label>
                        <input 
                            type="text" 
                            id="q" 
                            name="q" 
                            value="<?php echo htmlspecialchars($busqueda); ?>"
                            placeholder="Nombre, teléfono o email"
                        >
                    </div>

                    <div class="form-group">
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado">
                            <option value="">Todos</option>

                            <?php foreach ($estadosCliente as $estado): ?>
                                <option 
                                    value="<?php echo htmlspecialchars($estado); ?>"
                                    <?php echo seleccionado($filtroEstado, $estado); ?>
                                >
                                    <?php echo estadoTextoCliente($estado); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="vista">Vista</label>
                        <select id="vista" name="vista">
                            <?php foreach ($vistasCliente as $valorVista => $textoVista): ?>
                                <option 
                                    value="<?php echo htmlspecialchars($valorVista); ?>"
                                    <?php echo seleccionado($vistaClientes, $valorVista); ?>
                                >
                                    <?php echo htmlspecialchars($textoVista); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="clientes-filter-actions">
                        <button type="submit" class="btn btn-primary btn-inline">
                            Filtrar
                        </button>

                        <a href="clientes.php" class="btn btn-muted btn-inline">
                            Limpiar
                        </a>
                    </div>
                </form>

                <form method="POST" class="clientes-refresh-form">
                    <button 
                        type="submit" 
                        name="accion" 
                        value="actualizar_estados"
                        class="btn btn-muted btn-inline"
                    >
                        Actualizar estados
                    </button>
                </form>

            </section>

            <section class="clientes-layout">

                <article class="panel-card">
                    <div class="panel-header-line">
                        <h3>Clientes registrados</h3>
                        <span class="clientes-count">
                            <?php echo count($clientes); ?> visibles
                        </span>
                    </div>

                    <?php if (empty($clientes)): ?>
                        <p>No hay clientes para los filtros seleccionados.</p>
                    <?php else: ?>

                        <div class="clientes-list">
                            <?php foreach ($clientes as $cliente): ?>
                                <article class="cliente-card <?php echo claseEstadoCliente($cliente['estado_cliente']); ?> <?php echo ((int)$cliente['archivado'] === 1) ? 'cliente-archivado' : ''; ?>">

                                    <div class="cliente-card-main">
                                        <div>
                                            <h4><?php echo htmlspecialchars($cliente['nombre']); ?></h4>

                                            <p>
                                                Tel: <?php echo htmlspecialchars($cliente['telefono'] ?: 'Sin teléfono'); ?>
                                            </p>

                                            <?php if (!empty($cliente['email'])): ?>
                                                <p><?php echo htmlspecialchars($cliente['email']); ?></p>
                                            <?php endif; ?>
                                        </div>

                                        <div class="cliente-card-pills">
                                            <span class="cliente-pill <?php echo claseEstadoCliente($cliente['estado_cliente']); ?>">
                                                <?php echo estadoTextoCliente($cliente['estado_cliente']); ?>
                                            </span>

                                            <?php if ((int)$cliente['archivado'] === 1): ?>
                                                <span class="cliente-archivo-pill">
                                                    Archivado
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="cliente-card-stats">
                                        <span>
                                            <strong><?php echo (int)$cliente['total_reservas']; ?></strong>
                                            reservas
                                        </span>

                                        <span>
                                            <strong><?php echo (int)$cliente['total_no_presentadas']; ?></strong>
                                            no presentadas
                                        </span>

                                        <span>
                                            Última:
                                            <strong>
                                                <?php echo formatearFecha($cliente['ultima_reserva']); ?>
                                            </strong>
                                        </span>
                                    </div>

                                    <div class="cliente-card-actions">
                                        <a 
                                            href="clientes.php?cliente_id=<?php echo (int)$cliente['id']; ?>&q=<?php echo urlencode($busqueda); ?>&estado=<?php echo urlencode($filtroEstado); ?>&vista=<?php echo urlencode($vistaClientes); ?>" 
                                            class="btn-mini btn-save"
                                        >
                                            Ver historial
                                        </a>

                                        <?php if ((int)$cliente['archivado'] === 1): ?>
                                            <form method="POST" class="cliente-inline-form">
                                                <input type="hidden" name="cliente_id" value="<?php echo (int)$cliente['id']; ?>">

                                                <button 
                                                    type="submit" 
                                                    name="accion" 
                                                    value="reactivar_cliente"
                                                    class="btn-mini btn-muted"
                                                >
                                                    Reactivar
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                </article>
                            <?php endforeach; ?>
                        </div>

                    <?php endif; ?>

                </article>

                <article class="panel-card">

                    <?php if (!$clienteDetalle): ?>

                        <h3>Detalle del cliente</h3>
                        <p>
                            Selecciona un cliente para ver sus datos, historial de reservas,
                            notas internas y comportamiento básico.
                        </p>

                    <?php else: ?>

                        <div class="panel-header-line">
                            <h3>Detalle del cliente</h3>

                            <div class="cliente-card-pills">
                                <span class="cliente-pill <?php echo claseEstadoCliente($clienteDetalle['estado_cliente']); ?>">
                                    <?php echo estadoTextoCliente($clienteDetalle['estado_cliente']); ?>
                                </span>

                                <?php if ((int)$clienteDetalle['archivado'] === 1): ?>
                                    <span class="cliente-archivo-pill">
                                        Archivado
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <form method="POST" class="config-form cliente-detail-form">

                            <input type="hidden" name="cliente_id" value="<?php echo (int)$clienteDetalle['id']; ?>">

                            <div class="form-grid form-grid-small">
                                <div class="form-group">
                                    <label for="nombre">Nombre</label>
                                    <input 
                                        type="text" 
                                        id="nombre" 
                                        name="nombre" 
                                        value="<?php echo htmlspecialchars($clienteDetalle['nombre']); ?>"
                                        required
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="estado_cliente">Estado</label>
                                    <select id="estado_cliente" name="estado_cliente">
                                        <?php foreach ($estadosCliente as $estado): ?>
                                            <option 
                                                value="<?php echo htmlspecialchars($estado); ?>"
                                                <?php echo seleccionado($clienteDetalle['estado_cliente'], $estado); ?>
                                            >
                                                <?php echo estadoTextoCliente($estado); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-grid form-grid-small">
                                <div class="form-group">
                                    <label for="telefono">Teléfono</label>
                                    <input 
                                        type="text" 
                                        id="telefono" 
                                        name="telefono" 
                                        value="<?php echo htmlspecialchars($clienteDetalle['telefono'] ?? ''); ?>"
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input 
                                        type="email" 
                                        id="email" 
                                        name="email" 
                                        value="<?php echo htmlspecialchars($clienteDetalle['email'] ?? ''); ?>"
                                    >
                                </div>
                            </div>

                            <div class="form-grid form-grid-small">
                                <div class="form-group">
                                    <label for="preferencias">Preferencias</label>
                                    <input 
                                        type="text" 
                                        id="preferencias" 
                                        name="preferencias" 
                                        value="<?php echo htmlspecialchars($clienteDetalle['preferencias'] ?? ''); ?>"
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="alergias">Alergias</label>
                                    <input 
                                        type="text" 
                                        id="alergias" 
                                        name="alergias" 
                                        value="<?php echo htmlspecialchars($clienteDetalle['alergias'] ?? ''); ?>"
                                    >
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="notas_internas">Notas internas</label>
                                <textarea 
                                    id="notas_internas" 
                                    name="notas_internas" 
                                    rows="3"
                                    placeholder="Notas útiles para futuras reservas"
                                ><?php echo htmlspecialchars($clienteDetalle['notas_internas'] ?? ''); ?></textarea>
                            </div>

                            <div class="cliente-detail-actions">
                                <button 
                                    type="submit" 
                                    name="accion" 
                                    value="guardar_cliente" 
                                    class="btn btn-primary btn-inline"
                                >
                                    Guardar cliente
                                </button>

                                <?php if ((int)$clienteDetalle['archivado'] === 1): ?>
                                    <button 
                                        type="submit" 
                                        name="accion" 
                                        value="reactivar_cliente" 
                                        class="btn btn-muted btn-inline"
                                    >
                                        Reactivar cliente
                                    </button>
                                <?php else: ?>
                                    <button 
                                        type="submit" 
                                        name="accion" 
                                        value="archivar_cliente" 
                                        class="btn btn-muted btn-inline"
                                        onclick="return confirm('¿Seguro que quieres archivar este cliente?');"
                                    >
                                        Archivar cliente
                                    </button>
                                <?php endif; ?>
                            </div>

                        </form>

                        <div class="form-separator"></div>

                        <h3>Historial de reservas</h3>

                        <?php if (empty($historialReservas)): ?>
                            <p>Este cliente todavía no tiene reservas asociadas.</p>
                        <?php else: ?>

                            <div class="cliente-history-list">
                                <?php foreach ($historialReservas as $reserva): ?>
                                    <div class="cliente-history-item">
                                        <div>
                                            <strong>
                                                <?php echo formatearFecha($reserva['fecha']); ?> ·
                                                <?php echo htmlspecialchars($reserva['hora']); ?>
                                            </strong>

                                            <p>
                                                <?php echo (int)$reserva['personas']; ?> personas ·
                                                <?php echo htmlspecialchars($reserva['mesa_nombre'] ?? 'Sin mesa'); ?>

                                                <?php if (!empty($reserva['mesa_zona'])): ?>
                                                    · <?php echo htmlspecialchars($reserva['mesa_zona']); ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>

                                        <span class="estado-pill <?php echo claseEstadoReserva($reserva['estado']); ?>">
                                            <?php echo estadoTextoReserva($reserva['estado']); ?>
                                        </span>

                                        <?php if (!empty($reserva['alergias'])): ?>
                                            <span class="warning-pill">Alergias</span>
                                        <?php endif; ?>

                                        <?php if (!empty($reserva['observaciones'])): ?>
                                            <span class="info-pill">Notas</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        <?php endif; ?>

                    <?php endif; ?>

                </article>

            </section>

        </main>

    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>