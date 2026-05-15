<?php
// ==========================================================
// KITCHERRY STAFF TRAINING
// Panel del responsable
// Archivo: admin/index.php
// ==========================================================

declare(strict_types=1);

require_once __DIR__ . '/proteger.php';

$errorPanel = '';
$bloques = [];
$trabajadores = [];
$resumenGeneral = [
    'total_trabajadores' => 0,
    'total_intentos' => 0,
    'total_bloques' => 0,
    'formaciones_completas' => 0,
    'formaciones_incompletas' => 0,
    'sin_iniciar' => 0,
    'necesitan_repasar' => 0
];
$intentosRecientes = [];
$preguntasFalladas = [];
$estadisticasBloques = [];
$rankingTrabajadores = [];
$trabajadoresPendientes = [];

try {
    $conexion = obtenerConexionPanel();

    $preguntas = cargarPreguntasDesdeCsvUrl(CSV_URL);
    $bloques = obtenerBloques($preguntas);

    $trabajadores = obtenerResumenTrabajadores($conexion, $bloques);
    $resumenGeneral = obtenerResumenGeneralPanel($conexion, $bloques);
    $intentosRecientes = obtenerIntentosRecientes($conexion, 12);
    $preguntasFalladas = obtenerPreguntasMasFalladas($conexion, 12);
    $estadisticasBloques = obtenerEstadisticasPorBloque($conexion, $bloques);
    $rankingTrabajadores = obtenerRankingTrabajadores($conexion, $bloques, 8);
    $trabajadoresPendientes = obtenerTrabajadoresConMasPendientes($conexion, $bloques, 8);
} catch (Throwable $e) {
    $errorPanel = 'No se pudo cargar la información del panel.';
    error_log('Kitcherry Staff Training - panel index: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry Staff Training | Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">

<div class="admin-shell">

    <aside class="admin-sidebar">
        <a href="index.php" class="admin-brand">
            <img src="../assets/img/logo.png" alt="Logo Kitcherry">

            <span>
                <strong><b>KIT</b><em>CHERRY</em></strong>
                <small>Staff Training</small>
            </span>
        </a>

        <nav class="admin-nav">
            <a href="#resumen">Resumen</a>
            <a href="#bloques">Bloques</a>
            <a href="#trabajadores">Trabajadores</a>
            <a href="#ranking">Ranking</a>
            <a href="#intentos">Últimos intentos</a>
            <a href="#fallos">Preguntas falladas</a>
        </nav>

        <div class="admin-sidebar-footer">
            <span><?php echo limpiarTexto($adminSesion['usuario'] ?? 'Admin'); ?></span>
            <a href="logout.php">Cerrar sesión</a>
        </div>
    </aside>

    <main class="admin-main">

        <section class="admin-top">
            <div>
                <span>Panel del responsable</span>
                <h1>Seguimiento de formación</h1>
                <p>Consulta el progreso del personal, los bloques que necesitan refuerzo y las preguntas donde más falla el equipo.</p>
            </div>

            <div class="admin-top-actions">
                <a href="exportar.php?tipo=general" class="btn small">Exportar resumen general</a>
                <a href="../index.php" class="btn secondary small">Ver cuestionario</a>
            </div>
        </section>

        <?php if ($errorPanel !== ''): ?>

            <section class="notice error">
                <h2><?php echo limpiarTexto($errorPanel); ?></h2>
            </section>

        <?php else: ?>

            <section id="resumen" class="admin-section">
                <div class="admin-section-title">
                    <span>Vista general</span>
                    <h2>Resumen</h2>
                </div>

                <div class="admin-stats">
                    <article>
                        <span><?php echo (int) $resumenGeneral['total_trabajadores']; ?></span>
                        <p>Trabajadores</p>
                    </article>

                    <article>
                        <span><?php echo (int) $resumenGeneral['total_intentos']; ?></span>
                        <p>Intentos</p>
                    </article>

                    <article>
                        <span><?php echo (int) $resumenGeneral['total_bloques']; ?></span>
                        <p>Bloques</p>
                    </article>

                    <article>
                        <span><?php echo (int) $resumenGeneral['formaciones_completas']; ?></span>
                        <p>Completadas</p>
                    </article>

                    <article>
                        <span><?php echo (int) $resumenGeneral['formaciones_incompletas']; ?></span>
                        <p>Incompletas</p>
                    </article>

                    <article>
                        <span><?php echo (int) $resumenGeneral['necesitan_repasar']; ?></span>
                        <p>Con bloques a repasar</p>
                    </article>
                </div>
            </section>

            <section id="bloques" class="admin-section">
                <div class="admin-card panel-card-large">
                    <div class="admin-card-header">
                        <div>
                            <h2>Bloques más difíciles</h2>
                            <p>Promedio general, aprobados y no aprobados por bloque.</p>
                        </div>
                    </div>

                    <?php if (empty($estadisticasBloques)): ?>
                        <p class="empty-state">Todavía no hay estadísticas por bloque.</p>
                    <?php else: ?>
                        <div class="difficulty-grid">
                            <?php foreach ($estadisticasBloques as $bloque): ?>
                                <article class="difficulty-card <?php echo limpiarTexto($bloque['clase']); ?>">
                                    <div>
                                        <span><?php echo limpiarTexto($bloque['bloque']); ?></span>
                                        <strong>
                                            <?php echo $bloque['promedio'] !== null ? (int) $bloque['promedio'] . '%' : '—'; ?>
                                        </strong>
                                        <small>Promedio</small>
                                    </div>

                                    <ul>
                                        <li><?php echo (int) $bloque['total_intentos']; ?> intentos</li>
                                        <li><?php echo (int) $bloque['aprobados']; ?> aprobados</li>
                                        <li><?php echo (int) $bloque['no_aprobados']; ?> a repasar</li>
                                    </ul>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section id="trabajadores" class="admin-section">
                <div class="admin-card panel-card-large">
                    <div class="admin-card-header">
                        <div>
                            <h2>Trabajadores</h2>
                            <p>Resumen general por correo electrónico.</p>
                        </div>

                        <div class="admin-search">
                            <input
                                type="search"
                                placeholder="Buscar trabajador o email..."
                                data-worker-search
                            >
                        </div>
                    </div>

                    <?php if (empty($trabajadores)): ?>
                        <p class="empty-state">Todavía no hay resultados guardados.</p>
                    <?php else: ?>
                        <div class="table-wrap table-scroll">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Trabajador</th>
                                        <th>Email</th>
                                        <th>Progreso</th>
                                        <th>Pendientes</th>
                                        <th>Repasar</th>
                                        <th>Estado</th>
                                        <th>Intentos</th>
                                        <th>Último intento</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($trabajadores as $trabajador): ?>
                                        <tr data-worker-row data-search="<?php echo limpiarTexto(strtolower($trabajador['trabajador'] . ' ' . $trabajador['email'])); ?>">
                                            <td>
                                                <strong><?php echo limpiarTexto($trabajador['trabajador']); ?></strong>
                                            </td>
                                            <td><?php echo limpiarTexto($trabajador['email']); ?></td>
                                            <td>
                                                <?php echo (int) $trabajador['bloques_aprobados']; ?>
                                                /
                                                <?php echo (int) $trabajador['total_bloques']; ?>
                                                ·
                                                <?php echo (int) $trabajador['porcentaje_general']; ?>%
                                            </td>
                                            <td><?php echo (int) $trabajador['bloques_pendientes']; ?></td>
                                            <td><?php echo (int) $trabajador['bloques_para_repasar']; ?></td>
                                            <td>
                                                <span class="status-badge <?php echo limpiarTexto($trabajador['clase_estado']); ?>">
                                                    <?php echo limpiarTexto($trabajador['estado_general']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo (int) $trabajador['total_intentos']; ?></td>
                                            <td><?php echo limpiarTexto(formatearFechaPanel($trabajador['ultimo_intento'])); ?></td>
                                            <td>
                                                <a class="table-link" href="trabajador.php?email=<?php echo urlencode($trabajador['email']); ?>">
                                                    Ver detalle
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section id="ranking" class="admin-section">
                <div class="admin-grid-two">
                    <article class="admin-card">
                        <div class="admin-card-header">
                            <div>
                                <h2>Mejor progreso</h2>
                                <p>Trabajadores con más bloques aprobados.</p>
                            </div>
                        </div>

                        <?php if (empty($rankingTrabajadores)): ?>
                            <p class="empty-state">Todavía no hay ranking.</p>
                        <?php else: ?>
                            <div class="ranking-list">
                                <?php foreach ($rankingTrabajadores as $indice => $trabajador): ?>
                                    <a href="trabajador.php?email=<?php echo urlencode($trabajador['email']); ?>" class="ranking-item">
                                        <span><?php echo $indice + 1; ?></span>
                                        <div>
                                            <strong><?php echo limpiarTexto($trabajador['trabajador']); ?></strong>
                                            <small><?php echo (int) $trabajador['bloques_aprobados']; ?>/<?php echo (int) $trabajador['total_bloques']; ?> bloques aprobados</small>
                                        </div>
                                        <em><?php echo (int) $trabajador['porcentaje_general']; ?>%</em>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>

                    <article class="admin-card">
                        <div class="admin-card-header">
                            <div>
                                <h2>Más pendientes</h2>
                                <p>Trabajadores que necesitan completar o repasar bloques.</p>
                            </div>
                        </div>

                        <?php if (empty($trabajadoresPendientes)): ?>
                            <p class="empty-state">No hay trabajadores pendientes.</p>
                        <?php else: ?>
                            <div class="ranking-list">
                                <?php foreach ($trabajadoresPendientes as $trabajador): ?>
                                    <a href="trabajador.php?email=<?php echo urlencode($trabajador['email']); ?>" class="ranking-item warning">
                                        <span>!</span>
                                        <div>
                                            <strong><?php echo limpiarTexto($trabajador['trabajador']); ?></strong>
                                            <small>
                                                <?php echo (int) $trabajador['bloques_pendientes']; ?> pendientes ·
                                                <?php echo (int) $trabajador['bloques_para_repasar']; ?> a repasar
                                            </small>
                                        </div>
                                        <em><?php echo (int) $trabajador['porcentaje_general']; ?>%</em>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                </div>
            </section>

            <section id="intentos" class="admin-section">
                <div class="admin-card panel-card-large">
                    <div class="admin-card-header">
                        <div>
                            <h2>Últimos intentos</h2>
                            <p>Actividad reciente del cuestionario.</p>
                        </div>
                    </div>

                    <?php if (empty($intentosRecientes)): ?>
                        <p class="empty-state">No hay intentos registrados.</p>
                    <?php else: ?>
                        <div class="compact-list panel-scroll-list">
                            <?php foreach ($intentosRecientes as $intento): ?>
                                <a href="intento.php?id=<?php echo (int) $intento['id']; ?>" class="compact-item">
                                    <div>
                                        <strong><?php echo limpiarTexto($intento['trabajador']); ?></strong>
                                        <span>
                                            <?php echo limpiarTexto($intento['bloque']); ?>
                                            ·
                                            <?php echo limpiarTexto(formatearFechaPanel($intento['fecha_fin'])); ?>
                                            ·
                                            <?php echo limpiarTexto(formatearDuracion((int) $intento['duracion_segundos'])); ?>
                                        </span>
                                    </div>
                                    <em><?php echo (int) $intento['porcentaje']; ?>%</em>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section id="fallos" class="admin-section">
                <div class="admin-card panel-card-large">
                    <div class="admin-card-header">
                        <div>
                            <h2>Preguntas más falladas</h2>
                            <p>Sirve para detectar contenidos que necesitan refuerzo.</p>
                        </div>
                    </div>

                    <?php if (empty($preguntasFalladas)): ?>
                        <p class="empty-state">Todavía no hay fallos registrados.</p>
                    <?php else: ?>
                        <div class="failed-list panel-scroll-list">
                            <?php foreach ($preguntasFalladas as $pregunta): ?>
                                <div class="failed-item">
                                    <span><?php echo limpiarTexto($pregunta['bloque']); ?></span>
                                    <strong><?php echo limpiarTexto($pregunta['pregunta']); ?></strong>
                                    <small><?php echo (int) $pregunta['total_fallos']; ?> fallos</small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

        <?php endif; ?>

    </main>

</div>

<script>
const searchInput = document.querySelector('[data-worker-search]');
const workerRows = document.querySelectorAll('[data-worker-row]');

if (searchInput) {
    searchInput.addEventListener('input', function () {
        const value = this.value.trim().toLowerCase();

        workerRows.forEach(function (row) {
            const content = row.dataset.search || '';
            row.style.display = content.includes(value) ? '' : 'none';
        });
    });
}
</script>

</body>
</html>