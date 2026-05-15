<?php
// ==========================================================
// KITCHERRY STAFF TRAINING
// Archivo: index.php
// ==========================================================

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';

$errorCarga = '';
$preguntas = [];

try {
    $preguntas = cargarPreguntasDesdeCsvUrl(CSV_URL);
} catch (Throwable $e) {
    $errorCarga = $e->getMessage();
}

$bloques = obtenerBloques($preguntas);

$bloqueSeleccionado = $_GET['bloque'] ?? '';
$bloqueSeleccionado = trim((string) $bloqueSeleccionado);

$preguntasDelBloque = [];

if ($bloqueSeleccionado !== '') {
    $preguntasDelBloque = obtenerPreguntasPorBloque($preguntas, $bloqueSeleccionado);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo limpiarTexto(APP_NAME); ?> | Cuestionario</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description" content="Sistema de formación práctica para personal de hostelería.">

    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="topbar">
    <a href="index.php" class="brand">
        <img src="assets/img/logo.png" alt="Logo Kitcherry" class="brand-logo">

        <span class="brand-text">
            <span class="brand-name">KITCHERRY</span>
            <span class="brand-product">Staff Training</span>
        </span>
    </a>
</header>

<main class="page">

    <?php if ($errorCarga !== ''): ?>

        <section class="notice error">
            <h2>No se pudo cargar el cuestionario</h2>
            <p><?php echo limpiarTexto($errorCarga); ?></p>
            <p>
                Revisa que el documento de Google Sheets siga publicado como CSV
                y que el servidor tenga acceso a Internet.
            </p>
        </section>

    <?php elseif (empty($preguntas)): ?>

        <section class="notice error">
            <h2>No se han encontrado preguntas</h2>
            <p>
                El CSV se ha cargado, pero no se han detectado preguntas válidas.
                Revisa las columnas: <strong>id, bloque, pregunta, opcion_a, opcion_b, opcion_c, opcion_d, correcta, explicacion</strong>.
            </p>
        </section>

    <?php else: ?>

        <?php if ($bloqueSeleccionado === ''): ?>

            <section class="section">
                <div class="section-header">
                    <h1>Elige un bloque de formación</h1>
                    <p>
                        Selecciona el tema que quieres practicar antes de comenzar el cuestionario.
                    </p>
                </div>

                <div class="blocks-grid">
                    <?php foreach ($bloques as $bloque): ?>
                        <?php
                            $cantidadPreguntas = count(obtenerPreguntasPorBloque($preguntas, $bloque));
                        ?>

                        <a class="block-card" href="index.php?bloque=<?php echo urlencode($bloque); ?>">
                            <span><?php echo limpiarTexto($bloque); ?></span>
                            <small><?php echo $cantidadPreguntas; ?> preguntas · Comenzar</small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

        <?php elseif (empty($preguntasDelBloque)): ?>

            <section class="notice error">
                <h2>Bloque no encontrado</h2>
                <p>El bloque seleccionado no tiene preguntas disponibles.</p>
                <a href="index.php" class="btn">Volver al inicio</a>
            </section>

        <?php else: ?>

            <section class="section">
                <div class="section-header">
                    <h1><?php echo limpiarTexto($bloqueSeleccionado); ?></h1>
                    <p>
                        Introduce tu nombre y responde todas las preguntas del bloque seleccionado.
                    </p>
                </div>

                <form class="quiz-form" action="resultado.php" method="POST">

                    <input type="hidden" name="bloque" value="<?php echo limpiarTexto($bloqueSeleccionado); ?>">

                    <div class="worker-box">
                        <label for="trabajador">Nombre del trabajador</label>
                        <input
                            type="text"
                            id="trabajador"
                            name="trabajador"
                            placeholder="Ejemplo: Laura García"
                            required
                        >
                    </div>

                    <?php foreach ($preguntasDelBloque as $indice => $pregunta): ?>
                        <?php
                            $idPregunta = $pregunta['id'];
                            $numero = $indice + 1;
                        ?>

                        <article class="question-card">
                            <div class="question-top">
                                <span>Pregunta <?php echo $numero; ?></span>
                                <small><?php echo limpiarTexto($bloqueSeleccionado); ?></small>
                            </div>

                            <h3><?php echo limpiarTexto($pregunta['pregunta']); ?></h3>

                            <div class="options">
                                <label>
                                    <input type="radio" name="respuestas[<?php echo limpiarTexto($idPregunta); ?>]" value="A" required>
                                    <span><?php echo limpiarTexto($pregunta['opcion_a'] ?? ''); ?></span>
                                </label>

                                <label>
                                    <input type="radio" name="respuestas[<?php echo limpiarTexto($idPregunta); ?>]" value="B">
                                    <span><?php echo limpiarTexto($pregunta['opcion_b'] ?? ''); ?></span>
                                </label>

                                <label>
                                    <input type="radio" name="respuestas[<?php echo limpiarTexto($idPregunta); ?>]" value="C">
                                    <span><?php echo limpiarTexto($pregunta['opcion_c'] ?? ''); ?></span>
                                </label>

                                <label>
                                    <input type="radio" name="respuestas[<?php echo limpiarTexto($idPregunta); ?>]" value="D">
                                    <span><?php echo limpiarTexto($pregunta['opcion_d'] ?? ''); ?></span>
                                </label>
                            </div>
                        </article>
                    <?php endforeach; ?>

                    <div class="form-actions">
                        <a href="index.php" class="btn secondary">Cambiar bloque</a>
                        <button type="submit" class="btn">Finalizar cuestionario</button>
                    </div>

                </form>
            </section>

        <?php endif; ?>

    <?php endif; ?>

</main>

<footer class="footer">
    <p>Kitcherry Staff Training · Formación práctica para personal de hostelería</p>
</footer>

</body>
</html>