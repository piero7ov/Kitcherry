<?php
// ==========================================================
// KITCHERRY STAFF TRAINING
// Panel del responsable
// Archivo: admin/intento.php
// ==========================================================

declare(strict_types=1);

require_once __DIR__ . '/proteger.php';

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$errorPanel = '';
$intento = null;
$respuestas = [];

try {
    $conexion = obtenerConexionPanel();

    $intento = obtenerIntentoPorId($conexion, $id);

    if ($intento === null) {
        header('Location: index.php');
        exit;
    }

    $respuestas = obtenerRespuestasPorIntento($conexion, $id);
} catch (Throwable $e) {
    $errorPanel = 'No se pudo cargar el intento.';
    error_log('Kitcherry Staff Training - intento: ' . $e->getMessage());
}

$aprobado = isset($intento['porcentaje']) && (int) $intento['porcentaje'] >= NOTA_MINIMA_APROBADO;
$claseEstado = $aprobado ? 'success' : 'warning';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry Staff Training | Intento</title>
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

    <?php if ($errorPanel !== ''): ?>

        <section class="notice error">
            <h2><?php echo limpiarTexto($errorPanel); ?></h2>
            <a href="index.php" class="btn">Volver al panel</a>
        </section>

    <?php else: ?>

        <section class="admin-header">
            <div>
                <span>Detalle del intento</span>
                <h1><?php echo limpiarTexto($intento['bloque']); ?></h1>
                <p>
                    <?php echo limpiarTexto($intento['trabajador']); ?>
                    ·
                    <?php echo limpiarTexto($intento['email']); ?>
                </p>
            </div>

            <div class="admin-actions">
                <a href="trabajador.php?email=<?php echo urlencode($intento['email']); ?>" class="btn secondary small">
                    Volver al trabajador
                </a>
                <a href="index.php" class="btn secondary small">Panel</a>
            </div>
        </section>

        <section class="result-hero">
            <div>
                <p class="eyebrow">Resultado</p>
                <h1><?php echo (int) $intento['porcentaje']; ?>%</h1>
                <p>
                    Fecha:
                    <strong><?php echo limpiarTexto(formatearFechaPanel($intento['fecha_fin'])); ?></strong>
                </p>
                <p>
                    Duración:
                    <strong><?php echo limpiarTexto(formatearDuracion((int) $intento['duracion_segundos'])); ?></strong>
                </p>
            </div>

            <div class="score-card <?php echo $claseEstado; ?>">
                <span><?php echo (int) $intento['aciertos']; ?>/<?php echo (int) $intento['total_preguntas']; ?></span>
                <strong><?php echo limpiarTexto($intento['estado']); ?></strong>
            </div>
        </section>

        <section class="admin-card">
            <div class="admin-card-header">
                <div>
                    <h2>Respuestas del intento</h2>
                    <p>Detalle de respuestas correctas e incorrectas.</p>
                </div>
            </div>

            <?php if (empty($respuestas)): ?>
                <p class="empty-state">No hay respuestas registradas para este intento.</p>
            <?php else: ?>
                <div class="review-list">
                    <?php foreach ($respuestas as $indice => $respuesta): ?>
                        <?php
                            $correcta = (int) $respuesta['es_correcta'] === 1;
                            $clase = $correcta ? 'correct' : 'incorrect';
                            $estado = $correcta ? 'Correcta' : 'Incorrecta';
                        ?>

                        <article class="review-card <?php echo $clase; ?>">
                            <div class="question-top">
                                <span>Pregunta <?php echo $indice + 1; ?></span>
                                <small><?php echo limpiarTexto($estado); ?></small>
                            </div>

                            <h3><?php echo limpiarTexto($respuesta['pregunta']); ?></h3>

                            <div class="answer-detail">
                                <p>
                                    <strong>Respuesta del trabajador:</strong>
                                    <?php echo limpiarTexto($respuesta['respuesta_usuario']); ?>
                                </p>

                                <p>
                                    <strong>Respuesta correcta:</strong>
                                    <?php echo limpiarTexto($respuesta['respuesta_correcta']); ?>
                                </p>

                                <?php if (!empty($respuesta['explicacion'])): ?>
                                    <p class="explanation">
                                        <strong>Explicación:</strong>
                                        <?php echo limpiarTexto($respuesta['explicacion']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
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