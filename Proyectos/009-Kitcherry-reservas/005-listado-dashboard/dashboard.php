<?php
// ==========================================================
// KITCHERRY RESERVAS
// Archivo: dashboard.php
// Panel principal operativo
// ==========================================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

protegerPagina();

$usuario = usuarioActual();

$fechaSeleccionada = $_GET['fecha'] ?? date('Y-m-d');

$fechaValida = DateTime::createFromFormat('Y-m-d', $fechaSeleccionada);

if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fechaSeleccionada) {
    $fechaSeleccionada = date('Y-m-d');
    $fechaValida = DateTime::createFromFormat('Y-m-d', $fechaSeleccionada);
}

$fechaAnteriorObj = clone $fechaValida;
$fechaAnteriorObj->modify('-1 day');

$fechaSiguienteObj = clone $fechaValida;
$fechaSiguienteObj->modify('+1 day');

$fechaAnterior = $fechaAnteriorObj->format('Y-m-d');
$fechaSiguiente = $fechaSiguienteObj->format('Y-m-d');

$fechaHoy = date('Y-m-d');
$esHoy = ($fechaSeleccionada === $fechaHoy);
$fechaMostrada = $fechaValida->format('d/m/Y');

$totalReservas = 0;
$totalClientes = 0;
$totalMesas = 0;
$totalComensalesDia = 0;
$reservasDia = 0;
$reservasPendientesDia = 0;
$reservasConfirmadasDia = 0;
$capacidadActiva = 0;

$negocioConfigurado = false;
$nombreNegocio = '';

$reservasDelDia = [];
$reservasPendientes = [];

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

try {
    $totalReservas = (int)$pdo->query("SELECT COUNT(*) FROM reservas")->fetchColumn();
    $totalClientes = (int)$pdo->query("SELECT COUNT(*) FROM clientes WHERE archivado = 0")->fetchColumn();
    $totalMesas = (int)$pdo->query("SELECT COUNT(*) FROM mesas WHERE activa = 1")->fetchColumn();

    $stmt = $pdo->query("SELECT COALESCE(SUM(capacidad), 0) FROM mesas WHERE activa = 1");
    $capacidadActiva = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM reservas 
        WHERE fecha = :fecha
    ");

    $stmt->execute([
        ':fecha' => $fechaSeleccionada
    ]);

    $reservasDia = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(personas), 0) 
        FROM reservas 
        WHERE fecha = :fecha
        AND estado NOT IN ('cancelada', 'no_presentada')
    ");

    $stmt->execute([
        ':fecha' => $fechaSeleccionada
    ]);

    $totalComensalesDia = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM reservas 
        WHERE fecha = :fecha 
        AND estado = 'pendiente'
    ");

    $stmt->execute([
        ':fecha' => $fechaSeleccionada
    ]);

    $reservasPendientesDia = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM reservas 
        WHERE fecha = :fecha 
        AND estado = 'confirmada'
    ");

    $stmt->execute([
        ':fecha' => $fechaSeleccionada
    ]);

    $reservasConfirmadasDia = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("
        SELECT nombre 
        FROM negocios 
        WHERE activo = 1 
        ORDER BY id ASC 
        LIMIT 1
    ");

    $negocio = $stmt->fetch();

    if ($negocio) {
        $negocioConfigurado = true;
        $nombreNegocio = $negocio['nombre'];
    }

    $stmt = $pdo->prepare("
        SELECT
            r.id,
            r.fecha,
            r.hora,
            r.personas,
            r.estado,
            r.alergias,
            r.observaciones,
            c.nombre AS cliente_nombre,
            c.telefono AS cliente_telefono,
            m.nombre AS mesa_nombre,
            m.zona AS mesa_zona
        FROM reservas r
        LEFT JOIN clientes c ON c.id = r.cliente_id
        LEFT JOIN mesas m ON m.id = r.mesa_id
        WHERE r.fecha = :fecha
        AND r.estado NOT IN ('cancelada', 'no_presentada', 'completada')
        ORDER BY r.hora ASC
        LIMIT 50
    ");

    $stmt->execute([
        ':fecha' => $fechaSeleccionada
    ]);

    $reservasDelDia = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT
            r.id,
            r.fecha,
            r.hora,
            r.personas,
            r.estado,
            c.nombre AS cliente_nombre,
            c.telefono AS cliente_telefono,
            m.nombre AS mesa_nombre
        FROM reservas r
        LEFT JOIN clientes c ON c.id = r.cliente_id
        LEFT JOIN mesas m ON m.id = r.mesa_id
        WHERE r.fecha = :fecha
        AND r.estado = 'pendiente'
        ORDER BY r.hora ASC
        LIMIT 20
    ");

    $stmt->execute([
        ':fecha' => $fechaSeleccionada
    ]);

    $reservasPendientes = $stmt->fetchAll();

} catch (PDOException $e) {
    // En esta versión evitamos romper el panel si aún faltan datos.
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
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="reservas.php">Reservas</a>
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
                    <p class="eyebrow">Panel interno</p>
                    <h1>Dashboard</h1>
                </div>
            </header>

            <section class="dashboard-intro">
                <div>
                    <h2>Kitcherry Reservas</h2>

                    <?php if ($negocioConfigurado): ?>
                        <p>
                            Resumen operativo para 
                            <strong><?php echo htmlspecialchars($nombreNegocio); ?></strong>
                            · <?php echo $esHoy ? 'Hoy' : 'Día seleccionado'; ?>:
                            <strong><?php echo htmlspecialchars($fechaMostrada); ?></strong>
                        </p>
                    <?php else: ?>
                        <p>
                            Completa la configuración inicial para empezar a gestionar reservas.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="dashboard-actions">
                    <a href="reservas.php?fecha=<?php echo urlencode($fechaSeleccionada); ?>&vista=activas" class="btn btn-primary btn-inline">
                        Ver reservas
                    </a>

                    <a href="mesas.php" class="btn btn-muted btn-inline">
                        Gestionar mesas
                    </a>
                </div>
            </section>

            <section class="dashboard-date-panel">
                <form method="GET" class="dashboard-date-form">

                    <a 
                        href="dashboard.php?fecha=<?php echo urlencode($fechaAnterior); ?>" 
                        class="btn-mini btn-muted"
                    >
                        Día anterior
                    </a>

                    <div class="form-group dashboard-date-input">
                        <label for="fecha">Fecha del panel</label>
                        <input 
                            type="date" 
                            id="fecha" 
                            name="fecha" 
                            value="<?php echo htmlspecialchars($fechaSeleccionada); ?>"
                        >
                    </div>

                    <button type="submit" class="btn-mini btn-save">
                        Ver día
                    </button>

                    <a 
                        href="dashboard.php" 
                        class="btn-mini btn-muted"
                    >
                        Hoy
                    </a>

                    <a 
                        href="dashboard.php?fecha=<?php echo urlencode($fechaSiguiente); ?>" 
                        class="btn-mini btn-muted"
                    >
                        Día siguiente
                    </a>

                </form>
            </section>

            <?php if (!$negocioConfigurado): ?>
                <section class="panel-card aviso-configuracion">
                    <h3>Configura primero tu negocio</h3>
                    <p>
                        Antes de empezar a gestionar mesas y reservas, es recomendable completar
                        los datos básicos del restaurante, horarios, capacidad y reglas de servicio.
                    </p>
                    <a href="configuracion.php" class="btn btn-primary btn-inline">
                        Ir a configuración
                    </a>
                </section>
            <?php endif; ?>

            <section class="stats-grid">

                <article class="stat-card">
                    <span class="stat-label">Reservas del día</span>
                    <strong><?php echo $reservasDia; ?></strong>
                    <p>Reservas registradas para la fecha seleccionada.</p>
                </article>

                <article class="stat-card">
                    <span class="stat-label">Comensales del día</span>
                    <strong><?php echo $totalComensalesDia; ?></strong>
                    <p>Total previsto sin contar canceladas o no presentadas.</p>
                </article>

                <article class="stat-card">
                    <span class="stat-label">Pendientes</span>
                    <strong><?php echo $reservasPendientesDia; ?></strong>
                    <p>Reservas que todavía requieren revisión.</p>
                </article>

                <article class="stat-card">
                    <span class="stat-label">Mesas activas</span>
                    <strong><?php echo $totalMesas; ?></strong>
                    <p>Capacidad total activa: <?php echo $capacidadActiva; ?> personas.</p>
                </article>

            </section>

            <section class="dashboard-grid">

                <article class="panel-card">
                    <div class="panel-header-line">
                        <h3>Reservas del día seleccionado</h3>
                        <a href="reservas.php?fecha=<?php echo urlencode($fechaSeleccionada); ?>&vista=activas" class="link-mini">
                            Ver todas
                        </a>
                    </div>

                    <?php if (empty($reservasDelDia)): ?>
                        <p>No hay reservas activas para esta fecha.</p>
                    <?php else: ?>
                        <div class="dashboard-reservas-list">
                            <?php foreach ($reservasDelDia as $reserva): ?>
                                <div class="dashboard-reserva-item <?php echo claseCardDashboard($reserva['estado']); ?>">
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
                                        </div>
                                    </div>

                                    <div class="dashboard-reserva-side">
                                        <span class="estado-pill <?php echo claseEstadoDashboard($reserva['estado']); ?>">
                                            <?php echo estadoTextoDashboard($reserva['estado']); ?>
                                        </span>

                                        <?php if (!empty($reserva['alergias'])): ?>
                                            <span class="warning-pill">Alergias</span>
                                        <?php endif; ?>

                                        <?php if (!empty($reserva['observaciones'])): ?>
                                            <span class="info-pill">Notas</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>

                <article class="panel-card">
                    <h3>Pendientes de revisión</h3>

                    <?php if (empty($reservasPendientes)): ?>
                        <p>No hay reservas pendientes para esta fecha.</p>
                    <?php else: ?>
                        <div class="dashboard-pendientes-list">
                            <?php foreach ($reservasPendientes as $reserva): ?>
                                <div class="dashboard-pendiente-item dashboard-card-pendiente">
                                    <strong>
                                        <?php echo htmlspecialchars($reserva['hora']); ?> ·
                                        <?php echo htmlspecialchars($reserva['cliente_nombre'] ?? 'Cliente sin nombre'); ?>
                                    </strong>

                                    <p>
                                        <?php echo (int)$reserva['personas']; ?> personas ·
                                        <?php echo htmlspecialchars($reserva['mesa_nombre'] ?? 'Sin mesa'); ?>
                                    </p>

                                    <span>
                                        Tel: <?php echo htmlspecialchars($reserva['cliente_telefono'] ?: 'Sin teléfono'); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="quick-actions">
                        <a href="reservas.php?fecha=<?php echo urlencode($fechaSeleccionada); ?>&vista=activas" class="btn btn-primary btn-inline">
                            Gestionar reservas
                        </a>
                    </div>
                </article>

            </section>

        </main>

    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>