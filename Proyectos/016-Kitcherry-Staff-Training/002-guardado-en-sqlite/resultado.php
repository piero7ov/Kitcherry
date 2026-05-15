<?php
// ==========================================================
// KITCHERRY STAFF TRAINING
// Archivo: resultado.php
// ==========================================================

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$trabajador = trim((string) ($_SESSION['staff_training']['trabajador'] ?? ''));
$email = normalizarEmail((string) ($_SESSION['staff_training']['email'] ?? ''));

if ($trabajador === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: index.php');
    exit;
}

$bloque = trim((string) ($_POST['bloque'] ?? ''));
$respuestasUsuario = $_POST['respuestas'] ?? [];

$fechaInicioTimestamp = (int) ($_POST['fecha_inicio_ts'] ?? 0);
$fechaFinTimestamp = time();

if ($fechaInicioTimestamp <= 0 || $fechaInicioTimestamp > $fechaFinTimestamp) {
    $fechaInicioTimestamp = $fechaFinTimestamp;
}

$duracionSegundos = max(0, $fechaFinTimestamp - $fechaInicioTimestamp);
$fechaInicio = date('Y-m-d H:i:s', $fechaInicioTimestamp);
$fechaFin = date('Y-m-d H:i:s', $fechaFinTimestamp);

if ($bloque === '' || !is_array($respuestasUsuario)) {
    header('Location: index.php');
    exit;
}

$errorCarga = '';
$preguntas = [];

try {
    $preguntas = cargarPreguntasDesdeCsvUrl(CSV_URL);
} catch (Throwable $e) {
    $errorCarga = $e->getMessage();
}

$bloques = obtenerBloques($preguntas);
$preguntasDelBloque = obtenerPreguntasPorBloque($preguntas, $bloque);

$totalPreguntas = count($preguntasDelBloque);
$aciertos = 0;
$detalleResultados = [];

foreach ($preguntasDelBloque as $pregunta) {
    $idPregunta = $pregunta['id'];
    $respuestaCorrecta = strtoupper(trim($pregunta['correcta'] ?? ''));
    $respuestaUsuario = strtoupper(trim((string) ($respuestasUsuario[$idPregunta] ?? '')));

    $esCorrecta = $respuestaUsuario === $respuestaCorrecta;

    if ($esCorrecta) {
        $aciertos++;
    }

    $detalleResultados[] = [
        'pregunta' => $pregunta,
        'respuesta_usuario' => $respuestaUsuario,
        'respuesta_correcta' => $respuestaCorrecta,
        'es_correcta' => $esCorrecta
    ];
}

$porcentaje = $totalPreguntas > 0 ? (int) round(($aciertos / $totalPreguntas) * 100) : 0;
$fallos = $totalPreguntas - $aciertos;

$aprobado = $porcentaje >= NOTA_MINIMA_APROBADO;

$claseEstado = $aprobado ? 'success' : 'warning';
$textoEstado = $aprobado ? 'Aprobado' : 'Necesita repasar';

$errorGuardado = '';
$siguienteBloquePendiente = null;

if ($errorCarga === '' && $totalPreguntas > 0) {
    try {
        $conexion = obtenerConexionSQLite();
        inicializarBaseDatos($conexion);

        $conexion->beginTransaction();

        $intentoId = guardarIntentoSQLite(
            $conexion,
            $trabajador,
            $email,
            $bloque,
            $totalPreguntas,
            $aciertos,
            $fallos,
            $porcentaje,
            $textoEstado,
            $fechaInicio,
            $fechaFin,
            $duracionSegundos
        );

        guardarRespuestasIntentoSQLite(
            $conexion,
            $intentoId,
            $bloque,
            $detalleResultados,
            $fechaFin
        );

        $conexion->commit();

        $progresoActualizado = obtenerMejoresIntentosPorEmail($conexion, $email);
        $siguienteBloquePendiente = obtenerSiguienteBloquePendiente($bloques, $progresoActualizado, $bloque);
    } catch (Throwable $e) {
        if (isset($conexion) && $conexion instanceof PDO && $conexion->inTransaction()) {
            $conexion->rollBack();
        }

        $errorGuardado = $e->getMessage();
        error_log('Kitcherry Staff Training - error guardando resultado: ' . $errorGuardado);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo limpiarTexto(APP_NAME); ?> | Resultado</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description" content="Resultado del cuestionario de formación interna de Kitcherry.">

    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="topbar">
    <a href="index.php" class="brand">
        <img src="assets/img/logo.png" alt="Logo Kitcherry" class="brand-logo">

        <span class="brand-text">
            <span class="brand-name">
                <span class="brand-kit">KIT</span><span class="brand-cherry">CHERRY</span>
            </span>
            <span class="brand-product">Staff Training</span>
        </span>
    </a>
</header>

<main class="page">

    <?php if ($errorCarga !== ''): ?>

        <section class="notice error">
            <h2>No se pudo corregir el cuestionario</h2>
            <p><?php echo limpiarTexto($errorCarga); ?></p>
            <a href="index.php" class="btn">Volver al inicio</a>
        </section>

    <?php elseif ($totalPreguntas === 0): ?>

        <section class="notice error">
            <h2>No se encontraron preguntas del bloque</h2>
            <p>
                El bloque seleccionado no existe o no tiene preguntas disponibles en el CSV.
            </p>
            <a href="index.php" class="btn">Volver al inicio</a>
        </section>

    <?php else: ?>

        <section class="result-hero">
            <div>
                <p class="eyebrow">Resultado del cuestionario</p>
                <h1><?php echo limpiarTexto($bloque); ?></h1>
                <p>
                    Trabajador:
                    <strong><?php echo limpiarTexto($trabajador); ?></strong>
                </p>
                <p>
                    Correo:
                    <strong><?php echo limpiarTexto($email); ?></strong>
                </p>
            </div>

            <div class="score-card <?php echo $claseEstado; ?>">
                <span><?php echo $porcentaje; ?>%</span>
                <strong><?php echo limpiarTexto($textoEstado); ?></strong>
            </div>
        </section>

        <section class="summary-grid">
            <article>
                <span><?php echo $totalPreguntas; ?></span>
                <p>Preguntas</p>
            </article>

            <article>
                <span><?php echo $aciertos; ?></span>
                <p>Aciertos</p>
            </article>

            <article>
                <span><?php echo $fallos; ?></span>
                <p>Fallos</p>
            </article>

            <article>
                <span><?php echo limpiarTexto(formatearDuracion($duracionSegundos)); ?></span>
                <p>Duración</p>
            </article>
        </section>

        <section class="section">
            <div class="section-header">
                <h2>Detalle de respuestas</h2>
                <p>
                    Revisa tus respuestas y las explicaciones de cada pregunta.
                </p>
            </div>

            <div class="review-list">
                <?php foreach ($detalleResultados as $indice => $detalle): ?>
                    <?php
                        $pregunta = $detalle['pregunta'];
                        $numero = $indice + 1;

                        $respuestaUsuario = $detalle['respuesta_usuario'];
                        $respuestaCorrecta = $detalle['respuesta_correcta'];

                        $textoUsuario = obtenerTextoRespuesta($pregunta, $respuestaUsuario);
                        $textoCorrecto = obtenerTextoRespuesta($pregunta, $respuestaCorrecta);

                        $clasePregunta = $detalle['es_correcta'] ? 'correct' : 'incorrect';
                        $estadoPregunta = $detalle['es_correcta'] ? 'Correcta' : 'Incorrecta';
                    ?>

                    <article class="review-card <?php echo $clasePregunta; ?>">
                        <div class="question-top">
                            <span>Pregunta <?php echo $numero; ?></span>
                            <small><?php echo limpiarTexto($estadoPregunta); ?></small>
                        </div>

                        <h3><?php echo limpiarTexto($pregunta['pregunta']); ?></h3>

                        <div class="answer-detail">
                            <p>
                                <strong>Tu respuesta:</strong>
                                <?php echo limpiarTexto($respuestaUsuario !== '' ? $respuestaUsuario . ' - ' . $textoUsuario : 'Sin responder'); ?>
                            </p>

                            <p>
                                <strong>Respuesta correcta:</strong>
                                <?php echo limpiarTexto($respuestaCorrecta . ' - ' . $textoCorrecto); ?>
                            </p>

                            <?php if (!empty($pregunta['explicacion'])): ?>
                                <p class="explanation">
                                    <strong>Explicación:</strong>
                                    <?php echo limpiarTexto($pregunta['explicacion']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="form-actions">
                <a href="index.php" class="btn secondary">Volver a bloques</a>
                <a href="index.php?bloque=<?php echo urlencode($bloque); ?>" class="btn secondary">Repetir bloque</a>

                <?php if ($siguienteBloquePendiente !== null): ?>
                    <a href="index.php?bloque=<?php echo urlencode($siguienteBloquePendiente); ?>" class="btn">
                        Siguiente bloque pendiente
                    </a>
                <?php endif; ?>
            </div>
        </section>

    <?php endif; ?>

</main>

<footer class="footer">
    <p>Kitcherry Staff Training · Formación práctica para personal de hostelería</p>
</footer>

</body>
</html>