<?php
// ==========================================================
// KITCHERRY STAFF TRAINING
// Panel del responsable
// Archivo: admin/trabajador.php
// ==========================================================

declare(strict_types=1);

require_once __DIR__ . '/proteger.php';

$email = normalizarEmail((string) ($_GET['email'] ?? ''));

if ($email === '') {
    header('Location: index.php');
    exit;
}

$errorPanel = '';
$trabajador = null;
$bloques = [];
$progreso = [];
$resumen = [];
$intentos = [];
$bloquesClasificados = [
    'pendientes' => [],
    'repasar' => [],
    'aprobados' => []
];
$preguntasFalladasTrabajador = [];
$evolucion = [];

try {
    $conexion = obtenerConexionPanel();

    $preguntas = cargarPreguntasDesdeCsvUrl(CSV_URL);
    $bloques = obtenerBloques($preguntas);

    $trabajador = obtenerDatosTrabajador($conexion, $email);

    if ($trabajador === null) {
        header('Location: index.php');
        exit;
    }

    $progreso = obtenerProgresoDetalladoTrabajador($conexion, $email, $bloques);
    $progresoSimple = obtenerMejoresIntentosPorEmail($conexion, $email);
    $resumen = obtenerResumenProgreso($bloques, $progresoSimple);
    $intentos = obtenerIntentosPorEmail($conexion, $email);
    $bloquesClasificados = obtenerBloquesPendientesYRepasoTrabajador($progreso);
    $preguntasFalladasTrabajador = obtenerPreguntasFalladasPorTrabajador($conexion, $email, 20);
    $evolucion = obtenerEvolucionTrabajadorPorBloque($conexion, $email);
} catch (Throwable $e) {
    $errorPanel = 'No se pudo cargar el detalle del trabajador.';
    error_log('Kitcherry Staff Training - trabajador: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry Staff Training | Trabajador</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

<header class="topbar">
    <a href="index.php" class="brand">
        <img src="../assets/img/logo.png" alt="Logo Kitcherry" class="brand-logo">

        <span class="brand-text">
            <span class="brand-name">
                <span class="brand-kit">KIT</span><span class="brand-cherry">CHERRY</span>
            </span>
            <span class="brand-product">Staff Training</span>
        </span>
    </a>
</header>

<main class="page admin-page">

    <section class="admin-header">
        <div>
            <span>Detalle del trabajador</span>
            <h1><?php echo limpiarTexto($trabajador['trabajador'] ?? 'Trabajador'); ?></h1>
            <p><?php echo limpiarTexto($email); ?></p>
        </div>

        <div class="admin-actions">
            <a href="index.php" class="btn secondary small">Volver al panel</a>
            <a href="logout.php" class="btn secondary small">Cerrar sesión</a>
        </div>
    </section>

    <?php if ($errorPanel !== ''): ?>

        <section class="notice error">
            <h2><?php echo limpiarTexto($errorPanel); ?></h2>
        </section>

    <?php else: ?>

        <section class="progress-overview <?php echo limpiarTexto($resumen['clase_estado']); ?>">
            <div>
                <span>Progreso general</span>
                <strong>
                    <?php echo (int) $resumen['bloques_aprobados']; ?>
                    de
                    <?php echo (int) $resumen['total_bloques']; ?>
                    bloques aprobados
                </strong>
                <small><?php echo limpiarTexto($resumen['estado_general']); ?></small>
            </div>

            <div class="progress-meter">
                <span><?php echo (int) $resumen['porcentaje_general']; ?>%</span>
            </div>
        </section>

        <section class="admin-grid-two">
            <article class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <h2>Pendientes</h2>
                        <p>Bloques que todavía no ha realizado.</p>
                    </div>
                </div>

                <?php if (empty($bloquesClasificados['pendientes'])): ?>
                    <p class="empty-state">No tiene bloques pendientes.</p>
                <?php else: ?>
                    <div class="tag-list">
                        <?php foreach ($bloquesClasificados['pendientes'] as $bloque): ?>
                            <span><?php echo limpiarTexto($bloque['bloque']); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>

            <article class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <h2>Necesita repasar</h2>
                        <p>Bloques realizados pero no aprobados.</p>
                    </div>
                </div>

                <?php if (empty($bloquesClasificados['repasar'])): ?>
                    <p class="empty-state">No tiene bloques para repasar.</p>
                <?php else: ?>
                    <div class="tag-list warning">
                        <?php foreach ($bloquesClasificados['repasar'] as $bloque): ?>
                            <span>
                                <?php echo limpiarTexto($bloque['bloque']); ?>
                                ·
                                <?php echo (int) $bloque['mejor_porcentaje']; ?>%
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </section>

        <section class="admin-card">
            <div class="admin-card-header">
                <div>
                    <h2>Progreso por bloque</h2>
                    <p>Mejor intento registrado por cada bloque de formación.</p>
                </div>
            </div>

            <div class="admin-blocks-grid">
                <?php foreach ($progreso as $bloque): ?>
                    <?php
                        $claseBloque = 'pending';

                        if (!empty($bloque['realizado'])) {
                            $claseBloque = !empty($bloque['aprobado']) ? 'complete' : 'incomplete';
                        }
                    ?>

                    <article class="admin-block-card <?php echo $claseBloque; ?>">
                        <span><?php echo limpiarTexto($bloque['bloque']); ?></span>

                        <?php if (empty($bloque['realizado'])): ?>
                            <strong>Pendiente</strong>
                            <small>Sin intentos</small>
                        <?php else: ?>
                            <strong><?php echo (int) $bloque['mejor_porcentaje']; ?>%</strong>
                            <small><?php echo limpiarTexto($bloque['estado']); ?></small>
                            <em>
                                <?php echo (int) $bloque['total_intentos']; ?>
                                <?php echo ((int) $bloque['total_intentos'] === 1) ? 'intento' : 'intentos'; ?>
                            </em>

                            <?php if (!empty($bloque['mejor_intento_id'])): ?>
                                <a href="intento.php?id=<?php echo (int) $bloque['mejor_intento_id']; ?>">
                                    Ver mejor intento
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="admin-grid-two">
            <article class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <h2>Preguntas falladas</h2>
                        <p>Preguntas donde este trabajador ha fallado más veces.</p>
                    </div>
                </div>

                <?php if (empty($preguntasFalladasTrabajador)): ?>
                    <p class="empty-state">No tiene fallos registrados.</p>
                <?php else: ?>
                    <div class="failed-list panel-scroll-list">
                        <?php foreach ($preguntasFalladasTrabajador as $pregunta): ?>
                            <div class="failed-item">
                                <span><?php echo limpiarTexto($pregunta['bloque']); ?></span>
                                <strong><?php echo limpiarTexto($pregunta['pregunta']); ?></strong>
                                <small>
                                    <?php echo (int) $pregunta['total_fallos']; ?> fallos ·
                                    último: <?php echo limpiarTexto(formatearFechaPanel($pregunta['ultimo_fallo'])); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>

            <article class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <h2>Evolución</h2>
                        <p>Historial resumido por bloque.</p>
                    </div>
                </div>

                <?php if (empty($evolucion)): ?>
                    <p class="empty-state">No hay evolución registrada.</p>
                <?php else: ?>
                    <div class="evolution-list panel-scroll-list">
                        <?php foreach ($evolucion as $bloque => $items): ?>
                            <div class="evolution-block">
                                <strong><?php echo limpiarTexto($bloque); ?></strong>

                                <?php foreach ($items as $item): ?>
                                    <div class="evolution-row">
                                        <span><?php echo limpiarTexto(formatearFechaPanel($item['fecha_fin'])); ?></span>
                                        <em><?php echo (int) $item['porcentaje']; ?>%</em>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </section>

        <section class="admin-card">
            <div class="admin-card-header">
                <div>
                    <h2>Historial de intentos</h2>
                    <p>Todos los cuestionarios realizados por este trabajador.</p>
                </div>
            </div>

            <?php if (empty($intentos)): ?>
                <p class="empty-state">Este trabajador todavía no tiene intentos registrados.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Bloque</th>
                                <th>Resultado</th>
                                <th>Estado</th>
                                <th>Duración</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($intentos as $intento): ?>
                                <tr>
                                    <td><?php echo limpiarTexto(formatearFechaPanel($intento['fecha_fin'])); ?></td>
                                    <td><?php echo limpiarTexto($intento['bloque']); ?></td>
                                    <td>
                                        <?php echo (int) $intento['porcentaje']; ?>%
                                        ·
                                        <?php echo (int) $intento['aciertos']; ?>/<?php echo (int) $intento['total_preguntas']; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo ((int) $intento['porcentaje'] >= NOTA_MINIMA_APROBADO) ? 'complete' : 'incomplete'; ?>">
                                            <?php echo limpiarTexto($intento['estado']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo limpiarTexto(formatearDuracion((int) $intento['duracion_segundos'])); ?></td>
                                    <td>
                                        <a class="table-link" href="intento.php?id=<?php echo (int) $intento['id']; ?>">
                                            Ver intento
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

    <?php endif; ?>

</main>

<footer class="footer">
    <p>Kitcherry Staff Training · Panel del responsable</p>
</footer>

</body>
</html>