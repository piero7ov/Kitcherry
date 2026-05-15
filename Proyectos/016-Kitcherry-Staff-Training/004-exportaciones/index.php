<?php
// ==========================================================
// KITCHERRY STAFF TRAINING
// Archivo: index.php
// ==========================================================

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';

$mensajeErrorUsuario = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'registrar_trabajador') {
        $trabajador = trim((string) ($_POST['trabajador'] ?? ''));
        $email = normalizarEmail((string) ($_POST['email'] ?? ''));

        if ($trabajador === '') {
            $mensajeErrorUsuario = 'Introduce el nombre del trabajador.';
        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensajeErrorUsuario = 'Introduce un correo electrónico válido.';
        } else {
            $_SESSION['staff_training'] = [
                'trabajador' => $trabajador,
                'email' => $email
            ];

            header('Location: index.php');
            exit;
        }
    }

    if ($accion === 'cambiar_trabajador') {
        unset($_SESSION['staff_training']);

        header('Location: index.php');
        exit;
    }
}

$trabajadorSesion = $_SESSION['staff_training']['trabajador'] ?? '';
$emailSesion = $_SESSION['staff_training']['email'] ?? '';

$usuarioIdentificado = $trabajadorSesion !== '' && $emailSesion !== '';

$errorCarga = '';
$preguntas = [];
$progresoPorBloque = [];

if ($usuarioIdentificado) {
    try {
        $preguntas = cargarPreguntasDesdeCsvUrl(CSV_URL);
    } catch (Throwable $e) {
        $errorCarga = $e->getMessage();
    }

    try {
        $conexion = obtenerConexionSQLite();
        inicializarBaseDatos($conexion);
        $progresoPorBloque = obtenerMejoresIntentosPorEmail($conexion, $emailSesion);
    } catch (Throwable $e) {
        error_log('Kitcherry Staff Training - error cargando progreso: ' . $e->getMessage());
        $progresoPorBloque = [];
    }
}

$bloques = obtenerBloques($preguntas);
$resumenProgreso = obtenerResumenProgreso($bloques, $progresoPorBloque);

$bloqueSeleccionado = $_GET['bloque'] ?? '';
$bloqueSeleccionado = trim((string) $bloqueSeleccionado);

$preguntasDelBloque = [];

if ($bloqueSeleccionado !== '') {
    $preguntasDelBloque = obtenerPreguntasPorBloque($preguntas, $bloqueSeleccionado);
}

$fechaInicioTimestamp = time();

$progresoBloqueSeleccionado = null;

if ($bloqueSeleccionado !== '' && isset($progresoPorBloque[$bloqueSeleccionado])) {
    $progresoBloqueSeleccionado = $progresoPorBloque[$bloqueSeleccionado];
}

$siguienteBloquePendiente = obtenerSiguienteBloquePendiente($bloques, $progresoPorBloque, $bloqueSeleccionado);
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
    <div class="topbar-inner">
        <a href="index.php" class="brand">
            <img src="assets/img/logo.png" alt="Logo Kitcherry" class="brand-logo">

            <span class="brand-text">
                <span class="brand-name">
                    <span class="brand-kit">KIT</span><span class="brand-cherry">CHERRY</span>
                </span>
                <span class="brand-product">Staff Training</span>
            </span>
        </a>

        <a href="admin/login.php" class="admin-entry">Ir a admin</a>
    </div>
</header>

<main class="page">

    <?php if (!$usuarioIdentificado): ?>

        <section class="section access-card">
            <div class="section-header">
                <h1>Identificación del trabajador</h1>
                <p>
                    Introduce tus datos una sola vez para acceder a los bloques de formación.
                </p>
            </div>

            <?php if ($mensajeErrorUsuario !== ''): ?>
                <div class="inline-error">
                    <?php echo limpiarTexto($mensajeErrorUsuario); ?>
                </div>
            <?php endif; ?>

            <form action="index.php" method="POST" class="access-form">
                <input type="hidden" name="accion" value="registrar_trabajador">

                <div class="worker-grid">
                    <div class="field-group">
                        <label for="trabajador">Nombre del trabajador</label>
                        <input
                            type="text"
                            id="trabajador"
                            name="trabajador"
                            placeholder="Ejemplo: Laura García"
                            required
                        >
                    </div>

                    <div class="field-group">
                        <label for="email">Correo electrónico</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Ejemplo: laura@restaurante.com"
                            required
                        >
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn">Acceder a la formación</button>
                </div>
            </form>
        </section>

    <?php elseif ($errorCarga !== ''): ?>

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

        <section class="user-strip">
            <div>
                <span>Trabajador</span>
                <strong><?php echo limpiarTexto($trabajadorSesion); ?></strong>
                <small><?php echo limpiarTexto($emailSesion); ?></small>
            </div>

            <form action="index.php" method="POST">
                <input type="hidden" name="accion" value="cambiar_trabajador">
                <button type="submit" class="btn secondary small">Cambiar usuario</button>
            </form>
        </section>

        <?php if ($bloqueSeleccionado === ''): ?>

            <section class="progress-overview <?php echo limpiarTexto($resumenProgreso['clase_estado']); ?>">
                <div>
                    <span>Progreso general</span>
                    <strong>
                        <?php echo (int) $resumenProgreso['bloques_aprobados']; ?>
                        de
                        <?php echo (int) $resumenProgreso['total_bloques']; ?>
                        bloques aprobados
                    </strong>
                    <small><?php echo limpiarTexto($resumenProgreso['estado_general']); ?></small>
                </div>

                <div class="progress-meter">
                    <span><?php echo (int) $resumenProgreso['porcentaje_general']; ?>%</span>
                </div>

                <?php if ($siguienteBloquePendiente !== null): ?>
                    <a href="index.php?bloque=<?php echo urlencode($siguienteBloquePendiente); ?>" class="btn">
                        Siguiente bloque pendiente
                    </a>
                <?php endif; ?>
            </section>

            <section class="section">
                <div class="section-header">
                    <h1>Elige un bloque de formación</h1>
                    <p>
                        Selecciona el tema que quieres practicar. Si ya has realizado un bloque,
                        verás tu mejor resultado antes de entrar.
                    </p>
                </div>

                <div class="blocks-grid">
                    <?php foreach ($bloques as $bloque): ?>
                        <?php
                            $cantidadPreguntas = count(obtenerPreguntasPorBloque($preguntas, $bloque));
                            $progreso = $progresoPorBloque[$bloque] ?? null;

                            $claseProgreso = 'block-pending';

                            if ($progreso !== null) {
                                $claseProgreso = $progreso['aprobado'] ? 'block-approved' : 'block-review';
                            }
                        ?>

                        <a class="block-card <?php echo $claseProgreso; ?>" href="index.php?bloque=<?php echo urlencode($bloque); ?>">
                            <span><?php echo limpiarTexto($bloque); ?></span>

                            <?php if ($progreso === null): ?>
                                <small><?php echo $cantidadPreguntas; ?> preguntas · Pendiente</small>
                            <?php else: ?>
                                <div class="block-progress">
                                    <strong><?php echo (int) $progreso['mejor_porcentaje']; ?>%</strong>
                                    <small>
                                        Mejor intento · <?php echo limpiarTexto($progreso['estado']); ?>
                                    </small>
                                    <em>
                                        <?php echo (int) $progreso['total_intentos']; ?>
                                        <?php echo ((int) $progreso['total_intentos'] === 1) ? 'intento' : 'intentos'; ?>
                                    </em>
                                </div>
                            <?php endif; ?>
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

                    <?php if ($progresoBloqueSeleccionado !== null): ?>
                        <div class="current-progress <?php echo $progresoBloqueSeleccionado['aprobado'] ? 'approved' : 'review'; ?>">
                            <span>Mejor intento</span>
                            <strong><?php echo (int) $progresoBloqueSeleccionado['mejor_porcentaje']; ?>%</strong>
                            <small><?php echo limpiarTexto($progresoBloqueSeleccionado['estado']); ?></small>
                        </div>

                        <?php if (!empty($progresoBloqueSeleccionado['aprobado'])): ?>
                            <div class="approved-warning">
                                Ya tienes este bloque aprobado. Puedes repetirlo si quieres mejorar tu resultado.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <p>
                        Responde todas las preguntas del bloque seleccionado.
                    </p>
                </div>

                <form class="quiz-form" action="resultado.php" method="POST">

                    <input type="hidden" name="bloque" value="<?php echo limpiarTexto($bloqueSeleccionado); ?>">
                    <input type="hidden" name="fecha_inicio_ts" value="<?php echo $fechaInicioTimestamp; ?>">

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
                        <a href="index.php" class="btn secondary">Volver a bloques</a>
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