<?php
// ==========================================================
// KITCHERRY RESERVAS
// Archivo: dashboard.php
// Panel principal
// ==========================================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/alertas.php';

protegerPagina();

$usuario = usuarioActual();

$mensaje = '';
$error = '';

function seleccionado($valorActual, $valorOpcion) {
    return ($valorActual === $valorOpcion) ? 'selected' : '';
}

function estadoTextoDashboard($estado) {
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

function claseEstadoDashboard($estado) {
    return 'estado-' . str_replace('_', '-', $estado);
}

function claseCardDashboard($estado) {
    return 'dashboard-card-' . str_replace('_', '-', $estado);
}

function formatearFechaDashboard($fecha) {
    $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);

    if (!$fechaObj) {
        return $fecha;
    }

    return $fechaObj->format('d/m/Y');
}

function nivelTextoDashboard($nivel) {
    $textos = [
        'info' => 'Info',
        'aviso' => 'Aviso',
        'riesgo' => 'Riesgo',
        'critico' => 'Crítico'
    ];

    return $textos[$nivel] ?? ucfirst($nivel);
}

function tipoTextoDashboard($tipo) {
    $textos = [
        'alergias' => 'Alergias',
        'grupo_grande' => 'Grupo grande',
        'cliente_riesgo' => 'Cliente de riesgo',
        'sin_telefono' => 'Sin teléfono',
        'sin_email' => 'Sin email',
        'mesa_duplicada' => 'Mesa duplicada',
        'saturacion_hora' => 'Saturación'
    ];

    return $textos[$tipo] ?? ucfirst(str_replace('_', ' ', $tipo));
}

$fecha = $_GET['fecha'] ?? date('Y-m-d');
$fechaAnterior = date('Y-m-d', strtotime($fecha . ' -1 day'));
$fechaSiguiente = date('Y-m-d', strtotime($fecha . ' +1 day'));

$negocio = null;
$reservasDia = [];
$alertasDia = [];

$totalReservasDia = 0;
$totalComensalesDia = 0;
$totalPendientesDia = 0;
$totalConfirmadasDia = 0;
$totalAlertasAbiertas = 0;
$totalAlertasRiesgo = 0;
$totalAlertasCriticas = 0;

try {
    $stmt = $pdo->query("
        SELECT id, nombre
        FROM negocios
        WHERE activo = 1
        ORDER BY id ASC
        LIMIT 1
    ");

    $negocio = $stmt->fetch();

} catch (PDOException $e) {
    $error = 'No se pudo cargar el negocio.';
}

if ($negocio) {
    try {
        // Regeneramos alertas del día seleccionado para que el dashboard esté actualizado.
        generarAlertasPorFecha($pdo, (int)$negocio['id'], $fecha);

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM reservas
            WHERE negocio_id = :negocio_id
            AND fecha = :fecha
        ");

        $stmt->execute([
            ':negocio_id' => $negocio['id'],
            ':fecha' => $fecha
        ]);

        $totalReservasDia = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(personas), 0)
            FROM reservas
            WHERE negocio_id = :negocio_id
            AND fecha = :fecha
            AND estado NOT IN ('cancelada', 'no_presentada')
        ");

        $stmt->execute([
            ':negocio_id' => $negocio['id'],
            ':fecha' => $fecha
        ]);

        $totalComensalesDia = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM reservas
            WHERE negocio_id = :negocio_id
            AND fecha = :fecha
            AND estado = 'pendiente'
        ");

        $stmt->execute([
            ':negocio_id' => $negocio['id'],
            ':fecha' => $fecha
        ]);

        $totalPendientesDia = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM reservas
            WHERE negocio_id = :negocio_id
            AND fecha = :fecha
            AND estado = 'confirmada'
        ");

        $stmt->execute([
            ':negocio_id' => $negocio['id'],
            ':fecha' => $fecha
        ]);

        $totalConfirmadasDia = (int)$stmt->fetchColumn();

        $totalAlertasAbiertas = contarAlertasAbiertasFecha($pdo, (int)$negocio['id'], $fecha);
        $totalAlertasRiesgo = contarAlertasPorNivelFecha($pdo, (int)$negocio['id'], $fecha, 'riesgo');
        $totalAlertasCriticas = contarAlertasPorNivelFecha($pdo, (int)$negocio['id'], $fecha, 'critico');

        $stmt = $pdo->prepare("
            SELECT
                r.id,
                r.fecha,
                r.hora,
                r.personas,
                r.estado,
                r.alergias,
                r.observaciones,
                r.confirmacion_enviada,

                c.nombre AS cliente_nombre,
                c.telefono AS cliente_telefono,
                c.email AS cliente_email,

                m.nombre AS mesa_nombre,
                m.zona AS mesa_zona,

                (
                    SELECT COUNT(*)
                    FROM alertas_reserva a
                    WHERE a.reserva_id = r.id
                    AND a.resuelta = 0
                ) AS total_alertas

            FROM reservas r
            LEFT JOIN clientes c ON c.id = r.cliente_id
            LEFT JOIN mesas m ON m.id = r.mesa_id
            WHERE r.negocio_id = :negocio_id
            AND r.fecha = :fecha
            ORDER BY r.hora ASC, r.id ASC
        ");

        $stmt->execute([
            ':negocio_id' => $negocio['id'],
            ':fecha' => $fecha
        ]);

        $reservasDia = $stmt->fetchAll();

        $stmt = $pdo->prepare("
            SELECT
                a.id,
                a.reserva_id,
                a.tipo,
                a.nivel,
                a.mensaje,
                a.resuelta,

                r.fecha,
                r.hora,
                r.personas,
                r.estado,

                c.nombre AS cliente_nombre,
                c.telefono AS cliente_telefono,

                m.nombre AS mesa_nombre,
                m.zona AS mesa_zona

            FROM alertas_reserva a
            INNER JOIN reservas r ON r.id = a.reserva_id
            LEFT JOIN clientes c ON c.id = a.cliente_id
            LEFT JOIN mesas m ON m.id = r.mesa_id
            WHERE r.negocio_id = :negocio_id
            AND r.fecha = :fecha
            AND a.resuelta = 0
            ORDER BY
                CASE a.nivel
                    WHEN 'critico' THEN 1
                    WHEN 'riesgo' THEN 2
                    WHEN 'aviso' THEN 3
                    WHEN 'info' THEN 4
                    ELSE 5
                END,
                r.hora ASC,
                a.id ASC
            LIMIT 8
        ");

        $stmt->execute([
            ':negocio_id' => $negocio['id'],
            ':fecha' => $fecha
        ]);

        $alertasDia = $stmt->fetchAll();

    } catch (PDOException $e) {
        $error = 'No se pudieron cargar los datos del dashboard.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry Reservas | Dashboard</title>
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

            <nav class="sidebar-nav">
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="reservas.php">Reservas</a>
                <a href="mesas.php">Mesas</a>
                <a href="vista_mesas.php">Vista mesas</a>
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
                    <p class="eyebrow">Panel interno</p>
                    <h1>Dashboard</h1>
                </div>
            </header>

            <?php if (!$negocio): ?>
                <section class="panel-card aviso-configuracion">
                    <h3>Primero configura el negocio</h3>
                    <p>
                        Antes de utilizar el dashboard, debes completar la configuración principal del restaurante.
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

                <section class="dashboard-intro">
                    <div>
                        <h2>
                            Servicio del <?php echo formatearFechaDashboard($fecha); ?>
                        </h2>
                        <p>
                            Vista rápida de reservas, comensales, confirmaciones y alertas internas del día seleccionado.
                        </p>
                    </div>

                    <div class="dashboard-actions">
                        <a href="reservas.php?fecha=<?php echo htmlspecialchars($fecha); ?>" class="btn btn-primary btn-inline">
                            Gestionar reservas
                        </a>

                        <a href="alertas.php?fecha=<?php echo htmlspecialchars($fecha); ?>" class="btn btn-muted btn-inline">
                            Ver alertas
                        </a>
                    </div>
                </section>

                <section class="dashboard-date-panel">
                    <form method="GET" class="dashboard-date-form">

                        <a href="dashboard.php?fecha=<?php echo htmlspecialchars($fechaAnterior); ?>" class="btn-mini btn-muted">
                            Día anterior
                        </a>

                        <div class="form-group dashboard-date-input">
                            <label for="fecha">Fecha</label>
                            <input 
                                type="date" 
                                id="fecha" 
                                name="fecha" 
                                value="<?php echo htmlspecialchars($fecha); ?>"
                            >
                        </div>

                        <button type="submit" class="btn-mini btn-save">
                            Ver día
                        </button>

                        <a href="dashboard.php?fecha=<?php echo date('Y-m-d'); ?>" class="btn-mini btn-muted">
                            Hoy
                        </a>

                        <a href="dashboard.php?fecha=<?php echo htmlspecialchars($fechaSiguiente); ?>" class="btn-mini btn-muted">
                            Día siguiente
                        </a>
                    </form>
                </section>

                <section class="stats-grid">

                    <article class="stat-card">
                        <span class="stat-label">Reservas del día</span>
                        <strong><?php echo $totalReservasDia; ?></strong>
                        <p>Reservas registradas para la fecha seleccionada.</p>
                    </article>

                    <article class="stat-card">
                        <span class="stat-label">Comensales</span>
                        <strong><?php echo $totalComensalesDia; ?></strong>
                        <p>Total previsto sin contar canceladas o no presentadas.</p>
                    </article>

                    <article class="stat-card">
                        <span class="stat-label">Pendientes</span>
                        <strong><?php echo $totalPendientesDia; ?></strong>
                        <p>Reservas que todavía requieren revisión.</p>
                    </article>

                    <article class="stat-card">
                        <span class="stat-label">Alertas abiertas</span>
                        <strong><?php echo $totalAlertasAbiertas; ?></strong>
                        <p>
                            Riesgo: <?php echo $totalAlertasRiesgo; ?> ·
                            Críticas: <?php echo $totalAlertasCriticas; ?>
                        </p>
                    </article>

                </section>

                <section class="dashboard-grid">

                    <article class="panel-card">
                        <div class="panel-header-line">
                            <h3>Reservas del día seleccionado</h3>
                            <a href="reservas.php?fecha=<?php echo htmlspecialchars($fecha); ?>" class="link-mini">
                                Ver todas
                            </a>
                        </div>

                        <?php if (empty($reservasDia)): ?>
                            <p>No hay reservas registradas para esta fecha.</p>
                        <?php else: ?>

                            <div class="dashboard-reservas-list">
                                <?php foreach ($reservasDia as $reserva): ?>
                                    <article class="dashboard-reserva-item <?php echo claseCardDashboard($reserva['estado']); ?>">

                                        <div class="dashboard-reserva-main">
                                            <span class="reserva-hora">
                                                <?php echo htmlspecialchars($reserva['hora']); ?>
                                            </span>

                                            <div>
                                                <strong>
                                                    <?php echo htmlspecialchars($reserva['cliente_nombre'] ?? 'Cliente sin nombre'); ?>
                                                </strong>

                                                <p>
                                                    <?php echo (int)$reserva['personas']; ?> personas ·
                                                    <?php echo htmlspecialchars($reserva['mesa_nombre'] ?? 'Sin mesa'); ?>

                                                    <?php if (!empty($reserva['mesa_zona'])): ?>
                                                        · <?php echo htmlspecialchars($reserva['mesa_zona']); ?>
                                                    <?php endif; ?>
                                                </p>

                                                <p>
                                                    Tel:
                                                    <?php echo htmlspecialchars($reserva['cliente_telefono'] ?: 'Sin teléfono'); ?>
                                                </p>
                                            </div>
                                        </div>

                                        <div class="dashboard-reserva-side">
                                            <?php if (!empty($reserva['alergias'])): ?>
                                                <span class="warning-pill">Alergias</span>
                                            <?php endif; ?>

                                            <?php if ((int)$reserva['confirmacion_enviada'] === 1): ?>
                                                <span class="confirmacion-pill">Email enviado</span>
                                            <?php endif; ?>

                                            <?php if ((int)$reserva['total_alertas'] > 0): ?>
                                                <span class="alerta-reserva-pill">
                                                    <?php echo (int)$reserva['total_alertas']; ?> alerta<?php echo ((int)$reserva['total_alertas'] !== 1) ? 's' : ''; ?>
                                                </span>
                                            <?php endif; ?>

                                            <span class="estado-pill <?php echo claseEstadoDashboard($reserva['estado']); ?>">
                                                <?php echo estadoTextoDashboard($reserva['estado']); ?>
                                            </span>
                                        </div>

                                    </article>
                                <?php endforeach; ?>
                            </div>

                        <?php endif; ?>
                    </article>

                    <article class="panel-card">
                        <div class="panel-header-line">
                            <h3>Alertas internas</h3>
                            <a href="alertas.php?fecha=<?php echo htmlspecialchars($fecha); ?>" class="link-mini">
                                Ver alertas
                            </a>
                        </div>

                        <?php if (empty($alertasDia)): ?>
                            <p>No hay alertas abiertas para esta fecha.</p>
                        <?php else: ?>

                            <div class="dashboard-alertas-list">
                                <?php foreach ($alertasDia as $alerta): ?>
                                    <article class="dashboard-alerta-item alerta-nivel-<?php echo htmlspecialchars($alerta['nivel']); ?>">

                                        <div class="dashboard-alerta-top">
                                            <span class="alerta-nivel-pill alerta-pill-<?php echo htmlspecialchars($alerta['nivel']); ?>">
                                                <?php echo nivelTextoDashboard($alerta['nivel']); ?>
                                            </span>

                                            <span class="alerta-tipo">
                                                <?php echo tipoTextoDashboard($alerta['tipo']); ?>
                                            </span>
                                        </div>

                                        <strong>
                                            <?php echo htmlspecialchars($alerta['hora']); ?> ·
                                            <?php echo htmlspecialchars($alerta['cliente_nombre'] ?? 'Cliente sin nombre'); ?>
                                        </strong>

                                        <p>
                                            <?php echo htmlspecialchars($alerta['mensaje']); ?>
                                        </p>

                                    </article>
                                <?php endforeach; ?>
                            </div>

                        <?php endif; ?>

                        <div class="quick-actions">
                            <a href="alertas.php?fecha=<?php echo htmlspecialchars($fecha); ?>" class="btn btn-primary btn-inline">
                                Revisar alertas
                            </a>
                        </div>
                    </article>

                </section>

            <?php endif; ?>

        </main>

    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>