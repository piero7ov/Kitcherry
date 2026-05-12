<?php
// ==========================================================
// KITCHERRY RESERVAS
// Archivo: vista_mesas.php
// Vista visual de ocupación de mesas
// ==========================================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/alertas.php';

protegerPagina();

$usuario = usuarioActual();

$mensaje = '';
$error = '';

function limpiarTextoVistaMesas($valor) {
    return trim($valor ?? '');
}

function formatearFechaVistaMesas($fecha) {
    $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);

    if (!$fechaObj) {
        return $fecha;
    }

    return $fechaObj->format('d/m/Y');
}

function estadoTextoVistaMesas($estado) {
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

function claseEstadoVistaMesas($estado) {
    return 'estado-' . str_replace('_', '-', $estado);
}

function horaAMinutosVistaMesas($hora) {
    if (!$hora) {
        return 0;
    }

    $partes = explode(':', $hora);

    $horas = (int)($partes[0] ?? 0);
    $minutos = (int)($partes[1] ?? 0);

    return ($horas * 60) + $minutos;
}

function reservaCoincideConHoraVistaMesas($reserva, $horaSeleccionada, $duracionTotal) {
    $inicioReserva = horaAMinutosVistaMesas($reserva['hora']);
    $finReserva = $inicioReserva + $duracionTotal;
    $horaActual = horaAMinutosVistaMesas($horaSeleccionada);

    return ($horaActual >= $inicioReserva && $horaActual < $finReserva);
}

function reservaPerteneceAlServicioVistaMesas($reserva, $servicio) {
    if ($servicio === 'todo') {
        return true;
    }

    $minutos = horaAMinutosVistaMesas($reserva['hora']);

    // Comida: desde 12:00 hasta antes de 18:00
    if ($servicio === 'comida') {
        return ($minutos >= 720 && $minutos < 1080);
    }

    // Cena: desde 18:00 en adelante
    if ($servicio === 'cena') {
        return ($minutos >= 1080);
    }

    return true;
}

function textoServicioVistaMesas($servicio) {
    $textos = [
        'comida' => 'Comida',
        'cena' => 'Cena',
        'todo' => 'Todo el día'
    ];

    return $textos[$servicio] ?? 'Comida';
}

function horaDefectoServicioVistaMesas($servicio) {
    if ($servicio === 'cena') {
        return '21:00';
    }

    if ($servicio === 'todo') {
        return '14:00';
    }

    return '14:00';
}

function obtenerHorasRapidasVistaMesas($servicio) {
    if ($servicio === 'cena') {
        return ['20:00', '20:30', '21:00', '21:30', '22:00', '22:30'];
    }

    if ($servicio === 'todo') {
        return ['13:00', '14:00', '15:00', '20:00', '21:00', '22:00'];
    }

    return ['13:00', '13:30', '14:00', '14:30', '15:00', '15:30'];
}

function obtenerConfiguracionVistaMesas($pdo, $negocioId) {
    $configuracion = [
        'duracion_media_reserva' => 90,
        'margen_limpieza_mesa' => 15
    ];

    $stmt = $pdo->prepare("
        SELECT
            duracion_media_reserva,
            margen_limpieza_mesa
        FROM configuracion_reservas
        WHERE negocio_id = :negocio_id
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([
        ':negocio_id' => $negocioId
    ]);

    $resultado = $stmt->fetch();

    if ($resultado) {
        $configuracion['duracion_media_reserva'] = (int)($resultado['duracion_media_reserva'] ?? 90);
        $configuracion['margen_limpieza_mesa'] = (int)($resultado['margen_limpieza_mesa'] ?? 15);
    }

    return $configuracion;
}

$serviciosDisponibles = ['comida', 'cena', 'todo'];

$fecha = limpiarTextoVistaMesas($_GET['fecha'] ?? date('Y-m-d'));
$servicio = limpiarTextoVistaMesas($_GET['servicio'] ?? 'comida');
$hora = limpiarTextoVistaMesas($_GET['hora'] ?? '');

if (!in_array($servicio, $serviciosDisponibles, true)) {
    $servicio = 'comida';
}

if ($fecha === '') {
    $fecha = date('Y-m-d');
}

if ($hora === '') {
    $hora = horaDefectoServicioVistaMesas($servicio);
}

$horasRapidas = obtenerHorasRapidasVistaMesas($servicio);

$fechaAnterior = date('Y-m-d', strtotime($fecha . ' -1 day'));
$fechaSiguiente = date('Y-m-d', strtotime($fecha . ' +1 day'));

$negocio = null;
$mesas = [];
$reservasDia = [];
$reservasSinMesa = [];

$totalMesas = 0;
$totalLibres = 0;
$totalOcupadas = 0;
$totalDobladas = 0;
$totalConflictos = 0;
$totalSinAsignar = 0;

$duracionMedia = 90;
$margenLimpieza = 15;
$duracionTotal = 105;

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
        generarAlertasPorFecha($pdo, (int)$negocio['id'], $fecha);

        $configuracion = obtenerConfiguracionVistaMesas($pdo, (int)$negocio['id']);

        $duracionMedia = (int)$configuracion['duracion_media_reserva'];
        $margenLimpieza = (int)$configuracion['margen_limpieza_mesa'];
        $duracionTotal = $duracionMedia + $margenLimpieza;

        if ($duracionTotal <= 0) {
            $duracionTotal = 105;
        }

        $stmt = $pdo->prepare("
            SELECT
                id,
                nombre,
                capacidad,
                zona,
                orden
            FROM mesas
            WHERE negocio_id = :negocio_id
            AND activa = 1
            ORDER BY zona ASC, orden ASC, nombre ASC
        ");

        $stmt->execute([
            ':negocio_id' => $negocio['id']
        ]);

        $mesas = $stmt->fetchAll();

        $stmt = $pdo->prepare("
            SELECT
                r.id,
                r.cliente_id,
                r.mesa_id,
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
            AND r.estado IN ('pendiente', 'confirmada', 'modificada')
            ORDER BY r.hora ASC, r.id ASC
        ");

        $stmt->execute([
            ':negocio_id' => $negocio['id'],
            ':fecha' => $fecha
        ]);

        $reservasDia = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    $error = 'No se pudo cargar la vista de mesas.';
}

$ocupacionPorMesa = [];

foreach ($mesas as $mesa) {
    $mesaId = (int)$mesa['id'];

    $ocupacionPorMesa[$mesaId] = [
        'mesa' => $mesa,
        'reservas_hora' => [],
        'reservas_servicio' => []
    ];
}

foreach ($reservasDia as $reserva) {
    $perteneceServicio = reservaPerteneceAlServicioVistaMesas($reserva, $servicio);
    $coincideConHora = reservaCoincideConHoraVistaMesas($reserva, $hora, $duracionTotal);

    if (!$perteneceServicio) {
        continue;
    }

    $mesaId = (int)($reserva['mesa_id'] ?? 0);

    if ($mesaId > 0 && isset($ocupacionPorMesa[$mesaId])) {
        $ocupacionPorMesa[$mesaId]['reservas_servicio'][] = $reserva;

        if ($coincideConHora) {
            $ocupacionPorMesa[$mesaId]['reservas_hora'][] = $reserva;
        }
    } else {
        if ($coincideConHora) {
            $reservasSinMesa[] = $reserva;
        }
    }
}

$totalMesas = count($mesas);
$totalSinAsignar = count($reservasSinMesa);

foreach ($ocupacionPorMesa as $item) {
    $reservasHora = count($item['reservas_hora']);
    $reservasServicio = count($item['reservas_servicio']);

    if ($reservasHora > 1) {
        $totalConflictos++;
    } elseif ($reservasServicio > 1) {
        $totalDobladas++;
    } elseif ($reservasHora === 1) {
        $totalOcupadas++;
    } else {
        $totalLibres++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry Reservas | Vista de mesas</title>
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
                <a href="vista_mesas.php" class="active">Vista mesas</a>
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
                    <p class="eyebrow">Ocupación visual</p>
                    <h1>Vista de mesas</h1>
                </div>
            </header>

            <?php if (!$negocio): ?>
                <section class="panel-card aviso-configuracion">
                    <h3>Primero configura el negocio</h3>
                    <p>
                        Antes de ver la ocupación de mesas, debes completar la configuración principal del restaurante.
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
                            <?php echo textoServicioVistaMesas($servicio); ?> · 
                            <?php echo formatearFechaVistaMesas($fecha); ?> · 
                            <?php echo htmlspecialchars($hora); ?>
                        </h2>
                        <p>
                            Cada reserva ocupa la mesa durante <?php echo (int)$duracionMedia; ?> minutos
                            más <?php echo (int)$margenLimpieza; ?> minutos de margen. Las mesas dobladas tienen varias reservas dentro del servicio.
                        </p>
                    </div>

                    <div class="dashboard-actions">
                        <a href="reservas.php?fecha=<?php echo htmlspecialchars($fecha); ?>" class="btn btn-primary btn-inline">
                            Gestionar reservas
                        </a>

                        <a href="mesas.php" class="btn btn-muted btn-inline">
                            Editar mesas
                        </a>
                    </div>
                </section>

                <section class="dashboard-date-panel">
                    <form method="GET" class="dashboard-date-form">

                        <a href="vista_mesas.php?fecha=<?php echo htmlspecialchars($fechaAnterior); ?>&servicio=<?php echo htmlspecialchars($servicio); ?>&hora=<?php echo htmlspecialchars($hora); ?>" class="btn-mini btn-muted">
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

                        <div class="form-group dashboard-date-input">
                            <label for="servicio">Servicio</label>
                            <select id="servicio" name="servicio">
                                <option value="comida" <?php echo ($servicio === 'comida') ? 'selected' : ''; ?>>Comida</option>
                                <option value="cena" <?php echo ($servicio === 'cena') ? 'selected' : ''; ?>>Cena</option>
                                <option value="todo" <?php echo ($servicio === 'todo') ? 'selected' : ''; ?>>Todo el día</option>
                            </select>
                        </div>

                        <div class="form-group dashboard-date-input">
                            <label for="hora">Hora exacta</label>
                            <input 
                                type="time" 
                                id="hora" 
                                name="hora" 
                                value="<?php echo htmlspecialchars($hora); ?>"
                            >
                        </div>

                        <button type="submit" class="btn-mini btn-save">
                            Ver ocupación
                        </button>

                        <a href="vista_mesas.php?fecha=<?php echo date('Y-m-d'); ?>&servicio=<?php echo htmlspecialchars($servicio); ?>&hora=<?php echo htmlspecialchars($hora); ?>" class="btn-mini btn-muted">
                            Hoy
                        </a>

                        <a href="vista_mesas.php?fecha=<?php echo htmlspecialchars($fechaSiguiente); ?>&servicio=<?php echo htmlspecialchars($servicio); ?>&hora=<?php echo htmlspecialchars($hora); ?>" class="btn-mini btn-muted">
                            Día siguiente
                        </a>
                    </form>

                    <div class="horas-rapidas-panel">
                        <span>Horas rápidas:</span>

                        <div class="horas-rapidas-list">
                            <?php foreach ($horasRapidas as $horaRapida): ?>
                                <a 
                                    href="vista_mesas.php?fecha=<?php echo htmlspecialchars($fecha); ?>&servicio=<?php echo htmlspecialchars($servicio); ?>&hora=<?php echo htmlspecialchars($horaRapida); ?>"
                                    class="hora-rapida <?php echo ($hora === $horaRapida) ? 'active' : ''; ?>"
                                >
                                    <?php echo htmlspecialchars($horaRapida); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <section class="stats-grid">

                    <article class="stat-card">
                        <span class="stat-label">Mesas activas</span>
                        <strong><?php echo $totalMesas; ?></strong>
                        <p>Mesas disponibles en la configuración actual.</p>
                    </article>

                    <article class="stat-card">
                        <span class="stat-label">Libres</span>
                        <strong><?php echo $totalLibres; ?></strong>
                        <p>Mesas sin reserva en la hora seleccionada.</p>
                    </article>

                    <article class="stat-card">
                        <span class="stat-label">Ocupadas</span>
                        <strong><?php echo $totalOcupadas; ?></strong>
                        <p>Mesas con una reserva activa en esa franja.</p>
                    </article>

                    <article class="stat-card">
                        <span class="stat-label">Dobladas</span>
                        <strong><?php echo $totalDobladas; ?></strong>
                        <p>Mesas con varias reservas en el servicio.</p>
                    </article>

                    <article class="stat-card">
                        <span class="stat-label">Conflictos</span>
                        <strong><?php echo $totalConflictos; ?></strong>
                        <p>Mesas con reservas solapadas en esa franja.</p>
                    </article>

                </section>

                <section class="vista-mesas-layout">

                    <article class="panel-card">
                        <div class="panel-header-line">
                            <h3>Mapa de ocupación</h3>

                            <div class="vista-mesas-leyenda">
                                <span class="leyenda-item leyenda-libre">Libre</span>
                                <span class="leyenda-item leyenda-ocupada">Ocupada</span>
                                <span class="leyenda-item leyenda-doblada">Doblada</span>
                                <span class="leyenda-item leyenda-conflicto">Conflicto</span>
                            </div>
                        </div>

                        <?php if (empty($mesas)): ?>
                            <p>No hay mesas activas registradas.</p>
                        <?php else: ?>

                            <div class="mesas-ocupacion-grid">
                                <?php foreach ($ocupacionPorMesa as $item): ?>
                                    <?php
                                        $mesa = $item['mesa'];
                                        $reservasHora = $item['reservas_hora'];
                                        $reservasServicio = $item['reservas_servicio'];

                                        $cantidadReservasHora = count($reservasHora);
                                        $cantidadReservasServicio = count($reservasServicio);

                                        $claseMesa = 'mesa-libre';
                                        $textoMesa = 'Libre';

                                        if ($cantidadReservasHora > 1) {
                                            $claseMesa = 'mesa-conflicto';
                                            $textoMesa = 'Conflicto';
                                        } elseif ($cantidadReservasServicio > 1) {
                                            $claseMesa = 'mesa-doblada';
                                            $textoMesa = 'Doblada';
                                        } elseif ($cantidadReservasHora === 1) {
                                            $claseMesa = 'mesa-ocupada';
                                            $textoMesa = 'Ocupada';
                                        }

                                        $idsReservasHora = array_map(function($reserva) {
                                            return (int)$reserva['id'];
                                        }, $reservasHora);
                                    ?>

                                    <article class="mesa-ocupacion-card <?php echo $claseMesa; ?>">

                                        <div class="mesa-ocupacion-top">
                                            <div>
                                                <h4>
                                                    <?php echo htmlspecialchars($mesa['nombre']); ?>
                                                </h4>

                                                <p>
                                                    <?php echo htmlspecialchars($mesa['zona']); ?> ·
                                                    <?php echo (int)$mesa['capacidad']; ?> pax
                                                </p>
                                            </div>

                                            <span class="mesa-estado-pill">
                                                <?php echo $textoMesa; ?>
                                            </span>
                                        </div>

                                        <?php if ($cantidadReservasServicio === 0): ?>

                                            <p class="mesa-libre-texto">
                                                Sin reservas en este servicio.
                                            </p>

                                        <?php else: ?>

                                            <?php if ($cantidadReservasHora === 0): ?>
                                                <p class="mesa-libre-texto">
                                                    Libre a las <?php echo htmlspecialchars($hora); ?>, pero con reservas en el servicio.
                                                </p>
                                            <?php endif; ?>

                                            <div class="mesa-reservas-mini">
                                                <?php foreach ($reservasServicio as $reserva): ?>
                                                    <?php
                                                        $esReservaActual = in_array((int)$reserva['id'], $idsReservasHora, true);
                                                    ?>

                                                    <div class="mesa-reserva-mini <?php echo $esReservaActual ? 'mesa-reserva-actual' : 'mesa-reserva-fuera-hora'; ?>">
                                                        <div>
                                                            <strong>
                                                                <?php echo htmlspecialchars($reserva['hora']); ?> ·
                                                                <?php echo htmlspecialchars($reserva['cliente_nombre'] ?? 'Cliente sin nombre'); ?>
                                                            </strong>

                                                            <p>
                                                                <?php echo (int)$reserva['personas']; ?> personas ·
                                                                Tel: <?php echo htmlspecialchars($reserva['cliente_telefono'] ?: 'Sin teléfono'); ?>
                                                            </p>
                                                        </div>

                                                        <div class="mesa-reserva-pills">
                                                            <?php if ($esReservaActual): ?>
                                                                <span class="mesa-subpill mesa-subpill-actual">
                                                                    En esta hora
                                                                </span>
                                                            <?php endif; ?>

                                                            <span class="estado-pill <?php echo claseEstadoVistaMesas($reserva['estado']); ?>">
                                                                <?php echo estadoTextoVistaMesas($reserva['estado']); ?>
                                                            </span>

                                                            <?php if ((int)$reserva['total_alertas'] > 0): ?>
                                                                <span class="alerta-reserva-pill">
                                                                    <?php echo (int)$reserva['total_alertas']; ?> alerta<?php echo ((int)$reserva['total_alertas'] !== 1) ? 's' : ''; ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>

                                        <?php endif; ?>

                                    </article>

                                <?php endforeach; ?>
                            </div>

                        <?php endif; ?>
                    </article>

                    <article class="panel-card">
                        <div class="panel-header-line">
                            <h3>Reservas sin mesa</h3>

                            <span class="clientes-count">
                                <?php echo $totalSinAsignar; ?> en esta franja
                            </span>
                        </div>

                        <?php if (empty($reservasSinMesa)): ?>
                            <p>No hay reservas sin mesa asignada para esta hora.</p>
                        <?php else: ?>

                            <div class="reservas-sin-mesa-list">
                                <?php foreach ($reservasSinMesa as $reserva): ?>
                                    <article class="reserva-sin-mesa-card">

                                        <div>
                                            <strong>
                                                <?php echo htmlspecialchars($reserva['hora']); ?> ·
                                                <?php echo htmlspecialchars($reserva['cliente_nombre'] ?? 'Cliente sin nombre'); ?>
                                            </strong>

                                            <p>
                                                <?php echo (int)$reserva['personas']; ?> personas ·
                                                Tel: <?php echo htmlspecialchars($reserva['cliente_telefono'] ?: 'Sin teléfono'); ?>
                                            </p>
                                        </div>

                                        <div class="mesa-reserva-pills">
                                            <span class="estado-pill <?php echo claseEstadoVistaMesas($reserva['estado']); ?>">
                                                <?php echo estadoTextoVistaMesas($reserva['estado']); ?>
                                            </span>

                                            <?php if ((int)$reserva['total_alertas'] > 0): ?>
                                                <span class="alerta-reserva-pill">
                                                    <?php echo (int)$reserva['total_alertas']; ?> alerta<?php echo ((int)$reserva['total_alertas'] !== 1) ? 's' : ''; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                    </article>
                                <?php endforeach; ?>
                            </div>

                        <?php endif; ?>

                        <div class="quick-actions">
                            <a href="reservas.php?fecha=<?php echo htmlspecialchars($fecha); ?>&vista=activas" class="btn btn-primary btn-inline">
                                Asignar mesas
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