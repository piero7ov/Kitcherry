<?php
// ==========================================================
// KITCHERRY RESERVAS
// Archivo: alertas.php
// Gestión de alertas internas
// ==========================================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/alertas.php';

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

function formatearFechaAlerta($fecha) {
    $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);

    if (!$fechaObj) {
        return $fecha;
    }

    return $fechaObj->format('d/m/Y');
}

function nivelTexto($nivel) {
    $textos = [
        'info' => 'Info',
        'aviso' => 'Aviso',
        'riesgo' => 'Riesgo',
        'critico' => 'Crítico'
    ];

    return $textos[$nivel] ?? ucfirst($nivel);
}

function tipoTextoAlerta($tipo) {
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

$fecha = $_GET['fecha'] ?? ($_POST['fecha'] ?? date('Y-m-d'));
$vista = $_GET['vista'] ?? ($_POST['vista'] ?? 'pendientes');

$vistasDisponibles = ['pendientes', 'resueltas', 'todas'];

if (!in_array($vista, $vistasDisponibles, true)) {
    $vista = 'pendientes';
}

$negocio = null;
$alertas = [];

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

// ----------------------------------------------------------
// Acciones
// ----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $negocio) {

    if (($_POST['accion'] ?? '') === 'generar_alertas') {
        try {
            $totalGeneradas = generarAlertasPorFecha($pdo, (int)$negocio['id'], $fecha);
            $mensaje = 'Alertas revisadas correctamente. Alertas activas generadas: ' . $totalGeneradas . '.';
        } catch (PDOException $e) {
            $error = 'No se pudieron generar las alertas.';
        }
    }

    if (($_POST['accion'] ?? '') === 'resolver_alerta') {
        $alertaId = (int)($_POST['alerta_id'] ?? 0);

        if ($alertaId <= 0) {
            $error = 'La alerta seleccionada no es válida.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE alertas_reserva
                    SET
                        resuelta = 1,
                        resuelta_en = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':id' => $alertaId
                ]);

                $mensaje = 'Alerta marcada como resuelta.';

            } catch (PDOException $e) {
                $error = 'No se pudo resolver la alerta.';
            }
        }
    }

    if (($_POST['accion'] ?? '') === 'reabrir_alerta') {
        $alertaId = (int)($_POST['alerta_id'] ?? 0);

        if ($alertaId <= 0) {
            $error = 'La alerta seleccionada no es válida.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE alertas_reserva
                    SET
                        resuelta = 0,
                        resuelta_en = NULL
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':id' => $alertaId
                ]);

                $mensaje = 'Alerta reabierta correctamente.';

            } catch (PDOException $e) {
                $error = 'No se pudo reabrir la alerta.';
            }
        }
    }
}

// ----------------------------------------------------------
// Cargar alertas
// ----------------------------------------------------------
$totalAlertas = 0;
$totalInfo = 0;
$totalAviso = 0;
$totalRiesgo = 0;
$totalCritico = 0;

if ($negocio) {
    try {
        generarAlertasPorFecha($pdo, (int)$negocio['id'], $fecha);

        $totalAlertas = contarAlertasAbiertasFecha($pdo, (int)$negocio['id'], $fecha);
        $totalInfo = contarAlertasPorNivelFecha($pdo, (int)$negocio['id'], $fecha, 'info');
        $totalAviso = contarAlertasPorNivelFecha($pdo, (int)$negocio['id'], $fecha, 'aviso');
        $totalRiesgo = contarAlertasPorNivelFecha($pdo, (int)$negocio['id'], $fecha, 'riesgo');
        $totalCritico = contarAlertasPorNivelFecha($pdo, (int)$negocio['id'], $fecha, 'critico');

        $sql = "
            SELECT
                a.id,
                a.reserva_id,
                a.cliente_id,
                a.tipo,
                a.nivel,
                a.mensaje,
                a.resuelta,
                a.creado_en,
                a.resuelta_en,

                r.fecha,
                r.hora,
                r.personas,
                r.estado,

                c.nombre AS cliente_nombre,
                c.telefono AS cliente_telefono,
                c.email AS cliente_email,

                m.nombre AS mesa_nombre,
                m.zona AS mesa_zona

            FROM alertas_reserva a
            INNER JOIN reservas r ON r.id = a.reserva_id
            LEFT JOIN clientes c ON c.id = a.cliente_id
            LEFT JOIN mesas m ON m.id = r.mesa_id
            WHERE r.negocio_id = :negocio_id
            AND r.fecha = :fecha
        ";

        $params = [
            ':negocio_id' => $negocio['id'],
            ':fecha' => $fecha
        ];

        if ($vista === 'pendientes') {
            $sql .= " AND a.resuelta = 0 ";
        }

        if ($vista === 'resueltas') {
            $sql .= " AND a.resuelta = 1 ";
        }

        $sql .= "
            ORDER BY
                a.resuelta ASC,
                CASE a.nivel
                    WHEN 'critico' THEN 1
                    WHEN 'riesgo' THEN 2
                    WHEN 'aviso' THEN 3
                    WHEN 'info' THEN 4
                    ELSE 5
                END,
                r.hora ASC,
                a.id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $alertas = $stmt->fetchAll();

    } catch (PDOException $e) {
        $error = 'No se pudieron cargar las alertas.';
    }
}

$fechaAnterior = date('Y-m-d', strtotime($fecha . ' -1 day'));
$fechaSiguiente = date('Y-m-d', strtotime($fecha . ' +1 day'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry Reservas | Alertas</title>
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
                <a href="dashboard.php">Dashboard</a>
                <a href="reservas.php">Reservas</a>
                <a href="mesas.php">Mesas</a>
                <a href="vista_mesas.php">Vista mesas</a>
                <a href="clientes.php">Clientes</a>
                <a href="alertas.php" class="active">Alertas</a>
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
                    <p class="eyebrow">Control interno</p>
                    <h1>Alertas</h1>
                </div>
            </header>

            <?php if (!$negocio): ?>
                <section class="panel-card aviso-configuracion">
                    <h3>Primero configura el negocio</h3>
                    <p>
                        Antes de gestionar alertas, debes completar la configuración principal del restaurante.
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
                        <span class="stat-label">Alertas abiertas</span>
                        <strong><?php echo $totalAlertas; ?></strong>
                        <p>Alertas pendientes para la fecha seleccionada.</p>
                    </article>

                    <article class="stat-card">
                        <span class="stat-label">Avisos</span>
                        <strong><?php echo $totalAviso; ?></strong>
                        <p>Situaciones que conviene revisar.</p>
                    </article>

                    <article class="stat-card">
                        <span class="stat-label">Riesgo</span>
                        <strong><?php echo $totalRiesgo; ?></strong>
                        <p>Reservas que requieren más atención.</p>
                    </article>

                    <article class="stat-card">
                        <span class="stat-label">Críticas</span>
                        <strong><?php echo $totalCritico; ?></strong>
                        <p>Posibles conflictos importantes.</p>
                    </article>

                </section>

                <section class="dashboard-date-panel">
                    <form method="GET" class="dashboard-date-form">

                        <a href="alertas.php?fecha=<?php echo htmlspecialchars($fechaAnterior); ?>&vista=<?php echo htmlspecialchars($vista); ?>" class="btn-mini btn-muted">
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

                        <select name="vista">
                            <option value="pendientes" <?php echo seleccionado($vista, 'pendientes'); ?>>Pendientes</option>
                            <option value="resueltas" <?php echo seleccionado($vista, 'resueltas'); ?>>Resueltas</option>
                            <option value="todas" <?php echo seleccionado($vista, 'todas'); ?>>Todas</option>
                        </select>

                        <button type="submit" class="btn-mini btn-save">
                            Ver alertas
                        </button>

                        <a href="alertas.php?fecha=<?php echo date('Y-m-d'); ?>&vista=<?php echo htmlspecialchars($vista); ?>" class="btn-mini btn-muted">
                            Hoy
                        </a>

                        <a href="alertas.php?fecha=<?php echo htmlspecialchars($fechaSiguiente); ?>&vista=<?php echo htmlspecialchars($vista); ?>" class="btn-mini btn-muted">
                            Día siguiente
                        </a>
                    </form>

                    <form method="POST" class="alertas-generar-form">
                        <input type="hidden" name="fecha" value="<?php echo htmlspecialchars($fecha); ?>">
                        <input type="hidden" name="vista" value="<?php echo htmlspecialchars($vista); ?>">

                        <button 
                            type="submit" 
                            name="accion" 
                            value="generar_alertas"
                            class="btn btn-primary btn-inline"
                        >
                            Revisar alertas del día
                        </button>
                    </form>
                </section>

                <section class="panel-card">

                    <div class="panel-header-line">
                        <h3>Alertas del <?php echo formatearFechaAlerta($fecha); ?></h3>
                        <span class="clientes-count">
                            <?php echo count($alertas); ?> visibles
                        </span>
                    </div>

                    <?php if (empty($alertas)): ?>
                        <p>No hay alertas para los filtros seleccionados.</p>
                    <?php else: ?>

                        <div class="alertas-list">
                            <?php foreach ($alertas as $alerta): ?>
                                <article class="alerta-card alerta-nivel-<?php echo htmlspecialchars($alerta['nivel']); ?> <?php echo ((int)$alerta['resuelta'] === 1) ? 'alerta-resuelta' : ''; ?>">

                                    <div class="alerta-card-main">
                                        <div>
                                            <span class="alerta-nivel-pill alerta-pill-<?php echo htmlspecialchars($alerta['nivel']); ?>">
                                                <?php echo nivelTexto($alerta['nivel']); ?>
                                            </span>

                                            <span class="alerta-tipo">
                                                <?php echo tipoTextoAlerta($alerta['tipo']); ?>
                                            </span>
                                        </div>

                                        <?php if ((int)$alerta['resuelta'] === 1): ?>
                                            <span class="cliente-archivo-pill">Resuelta</span>
                                        <?php endif; ?>
                                    </div>

                                    <h4>
                                        <?php echo htmlspecialchars($alerta['cliente_nombre'] ?? 'Cliente sin nombre'); ?>
                                    </h4>

                                    <p class="alerta-mensaje">
                                        <?php echo htmlspecialchars($alerta['mensaje']); ?>
                                    </p>

                                    <div class="alerta-meta">
                                        <span><?php echo htmlspecialchars($alerta['hora']); ?></span>
                                        <span><?php echo (int)$alerta['personas']; ?> personas</span>
                                        <span><?php echo htmlspecialchars($alerta['mesa_nombre'] ?? 'Sin mesa'); ?></span>
                                        <span><?php echo htmlspecialchars($alerta['cliente_telefono'] ?: 'Sin teléfono'); ?></span>
                                    </div>

                                    <div class="alerta-actions">
                                        <a 
                                            href="reservas.php?fecha=<?php echo htmlspecialchars($alerta['fecha']); ?>&vista=todas" 
                                            class="btn-mini btn-save"
                                        >
                                            Ver reserva
                                        </a>

                                        <form method="POST" class="cliente-inline-form">
                                            <input type="hidden" name="alerta_id" value="<?php echo (int)$alerta['id']; ?>">
                                            <input type="hidden" name="fecha" value="<?php echo htmlspecialchars($fecha); ?>">
                                            <input type="hidden" name="vista" value="<?php echo htmlspecialchars($vista); ?>">

                                            <?php if ((int)$alerta['resuelta'] === 1): ?>
                                                <button 
                                                    type="submit" 
                                                    name="accion" 
                                                    value="reabrir_alerta"
                                                    class="btn-mini btn-muted"
                                                >
                                                    Reabrir
                                                </button>
                                            <?php else: ?>
                                                <button 
                                                    type="submit" 
                                                    name="accion" 
                                                    value="resolver_alerta"
                                                    class="btn-mini btn-muted"
                                                >
                                                    Resolver
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </div>

                                </article>
                            <?php endforeach; ?>
                        </div>

                    <?php endif; ?>

                </section>

            <?php endif; ?>

        </main>

    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>