<?php
// ==========================================================
// KITCHERRY WEB ANALYTICS
// Archivo: informes/index.php
// Panel PHP para visualizar estadísticas, minibot y gráficas
// ==========================================================

declare(strict_types=1);

$dbPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . "kitcherry_analytics.sqlite";
$graficasPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . "graficas";
$graficasUrl = "../graficas";
$assetsCss = "../assets/css/style.css";
$assetsJs = "../assets/js/modal-graficas.js";
$logoUrl = "../assets/img/logo.png";

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function conectarSqlite(string $dbPath): ?PDO
{
    if (!file_exists($dbPath)) {
        return null;
    }

    if (!extension_loaded("pdo_sqlite")) {
        return null;
    }

    try {
        $pdo = new PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (Throwable $error) {
        return null;
    }
}

function consultar(PDO $pdo, string $sql): array
{
    try {
        $stmt = $pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $error) {
        return [];
    }
}

function valorUnico(PDO $pdo, string $sql, mixed $defecto = 0): mixed
{
    try {
        $stmt = $pdo->query($sql);
        $valor = $stmt ? $stmt->fetchColumn() : false;

        if ($valor === false || $valor === null) {
            return $defecto;
        }

        return $valor;
    } catch (Throwable $error) {
        return $defecto;
    }
}

function tablaHtml(array $cabeceras, array $filas): string
{
    if (empty($filas)) {
        return "<p class='empty'>No hay datos disponibles.</p>";
    }

    $html = "<div class='table-wrap'>";
    $html .= "<table>";
    $html .= "<thead><tr>";

    foreach ($cabeceras as $cabecera) {
        $html .= "<th>" . e($cabecera) . "</th>";
    }

    $html .= "</tr></thead>";
    $html .= "<tbody>";

    foreach ($filas as $fila) {
        $html .= "<tr>";

        foreach (array_values($fila) as $celda) {
            $html .= "<td>" . e($celda ?? "") . "</td>";
        }

        $html .= "</tr>";
    }

    $html .= "</tbody>";
    $html .= "</table>";
    $html .= "</div>";

    return $html;
}

function graficaHtml(string $graficasPath, string $graficasUrl, string $archivo, string $titulo): string
{
    $ruta = $graficasPath . DIRECTORY_SEPARATOR . $archivo;

    if (!file_exists($ruta)) {
        return "<p class='empty'>Gráfica no disponible: " . e($archivo) . "</p>";
    }

    $src = $graficasUrl . "/" . $archivo;

    return "
        <article class='chart-card'>
            <button
                class='chart-open'
                type='button'
                data-src='" . e($src) . "'
                data-title='" . e($titulo) . "'
                aria-label='Abrir gráfica: " . e($titulo) . "'
            >
                <img src='" . e($src) . "' alt='" . e($titulo) . "'>
                <span class='chart-action'>Ver en grande</span>
            </button>
        </article>
    ";
}

function estadoGeneral(int $correctas, int $mejorables, int $revisar): string
{
    if ($revisar > 0) {
        return "Revisar";
    }

    if ($mejorables > 0) {
        return "Mejorable";
    }

    if ($correctas > 0) {
        return "Correcto";
    }

    return "Sin datos";
}

$pdo = conectarSqlite($dbPath);

if (!$pdo) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Kitcherry Web Analytics</title>
        <link rel="stylesheet" href="<?= e($assetsCss) ?>">
    </head>
    <body>
        <main class="container error-page">
            <section class="note">
                <h1>Kitcherry Web Analytics</h1>
                <p>No se ha podido abrir la base de datos SQLite.</p>
                <p>Ejecuta primero los scripts del proyecto:</p>
                <pre>python 001-crear-bbdd.py
python 002-generar-log-demo.py
python 003-analizar-logs.py
python 004-minibot-kitcherry.py
python 005-generar-graficas.py</pre>
            </section>
        </main>
    </body>
    </html>
    <?php
    exit;
}

$totalVisitas = (int) valorUnico($pdo, "SELECT COUNT(*) FROM visitas_web WHERE es_error = 0");
$totalFormularios = (int) valorUnico($pdo, "SELECT COUNT(*) FROM visitas_web WHERE es_formulario = 1");
$totalErrores = (int) valorUnico($pdo, "SELECT COUNT(*) FROM visitas_web WHERE es_error = 1");
$paginasRevisadas = (int) valorUnico($pdo, "SELECT COUNT(*) FROM revision_minibot");
$enlacesDetectados = (int) valorUnico($pdo, "SELECT COUNT(*) FROM enlaces_minibot");
$enlacesRotos = (int) valorUnico($pdo, "SELECT COUNT(*) FROM enlaces_minibot WHERE es_roto = 1");

$paginasCorrectas = (int) valorUnico($pdo, "SELECT COUNT(*) FROM revision_minibot WHERE estado_tecnico = 'Correcto'");
$paginasMejorables = (int) valorUnico($pdo, "SELECT COUNT(*) FROM revision_minibot WHERE estado_tecnico = 'Mejorable'");
$paginasARevisar = (int) valorUnico($pdo, "SELECT COUNT(*) FROM revision_minibot WHERE estado_tecnico = 'Revisar'");

$estadoGeneral = estadoGeneral($paginasCorrectas, $paginasMejorables, $paginasARevisar);

$webAnalizada = valorUnico(
    $pdo,
    "SELECT url FROM revision_minibot ORDER BY id ASC LIMIT 1",
    "Configurar BASE_URL en config.py"
);

$paginasMasVisitadas = consultar($pdo, "
    SELECT pagina_limpia AS Página, COUNT(*) AS Visitas
    FROM visitas_web
    WHERE es_error = 0
    GROUP BY pagina_limpia
    ORDER BY Visitas DESC
    LIMIT 10
");

$erroresDetectados = consultar($pdo, "
    SELECT pagina_limpia AS Página, codigo_estado AS Código, COUNT(*) AS Cantidad
    FROM visitas_web
    WHERE es_error = 1
    GROUP BY pagina_limpia, codigo_estado
    ORDER BY Cantidad DESC
    LIMIT 10
");

$historial = consultar($pdo, "
    SELECT
        id AS Ejecución,
        fecha_ejecucion AS Fecha,
        total_visitas AS Visitas,
        total_formularios AS Formularios,
        total_errores AS Errores,
        paginas_revisadas AS 'Páginas revisadas',
        enlaces_detectados AS 'Enlaces detectados'
    FROM ejecuciones_analisis
    ORDER BY id DESC
    LIMIT 10
");

$revisionMinibot = consultar($pdo, "
    SELECT
        url AS URL,
        codigo_estado AS Código,
        CASE
            WHEN tiempo_respuesta IS NULL THEN ''
            ELSE tiempo_respuesta || ' s'
        END AS Tiempo,
        CASE WHEN tiene_title = 1 THEN 'Sí' ELSE 'No' END AS Title,
        CASE WHEN tiene_meta_description = 1 THEN 'Sí' ELSE 'No' END AS Description,
        CASE WHEN tiene_h1 = 1 THEN 'Sí' ELSE 'No' END AS H1,
        CASE WHEN tiene_lang = 1 THEN 'Sí' ELSE 'No' END AS 'Lang ES',
        enlaces_rotos AS 'Enlaces rotos',
        estado_tecnico AS Estado,
        COALESCE(error, '') AS 'Aviso/Error'
    FROM revision_minibot
    ORDER BY id ASC
    LIMIT 30
");

$enlacesRotosTabla = consultar($pdo, "
    SELECT
        origen AS Origen,
        destino AS Destino,
        codigo_estado AS Código,
        observacion AS Observación
    FROM enlaces_minibot
    WHERE es_roto = 1
    ORDER BY id ASC
    LIMIT 20
");

$fechaInforme = date("d/m/Y H:i");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry Web Analytics</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= e($assetsCss) ?>">
</head>
<body>
    <header>
        <div class="container">
            <div class="brand">
                <img class="brand-logo" src="<?= e($logoUrl) ?>" alt="Logo de Kitcherry">

                <div class="brand-text">
                    <h1 class="brand-name">
                        <span class="brand-kit">KIT</span><span class="brand-cherry">CHERRY</span>
                        <span class="brand-analytics">Web Analytics</span>
                    </h1>

                    <p class="subtitle">Panel de seguimiento visual de la web corporativa.</p>
                </div>
            </div>
        </div>
    </header>

    <div class="container panel-layout">
        <aside class="sidebar">
            <nav class="nav-card">
                <p class="nav-title">Panel</p>
                <a href="#resumen">Resumen</a>
                <a href="#semaforo">Semáforo técnico</a>
                <a href="#historial">Historial</a>
                <a href="#graficas">Gráficas</a>
                <a href="#tablas">Tablas</a>
                <a href="#minibot">Minibot</a>
                <a href="#exportar">Exportar PDF</a>

                <button class="print-btn" type="button" onclick="window.print()">
                    Imprimir / guardar PDF
                </button>
            </nav>
        </aside>

        <main class="content">
            <section id="resumen" class="panel-section">
                <div class="section-heading">
                    <div>
                        <h2>Resumen general</h2>
                        <p>Vista rápida de la actividad registrada y de la revisión técnica.</p>
                    </div>
                </div>

                <div class="grid">
                    <div class="card">
                        <p class="metric"><?= e($totalVisitas) ?></p>
                        <p class="label">Visitas registradas</p>
                    </div>

                    <div class="card">
                        <p class="metric"><?= e($totalFormularios) ?></p>
                        <p class="label">Formularios enviados</p>
                    </div>

                    <div class="card">
                        <p class="metric"><?= e($totalErrores) ?></p>
                        <p class="label">Errores detectados</p>
                    </div>

                    <div class="card">
                        <p class="metric"><?= e($paginasRevisadas) ?></p>
                        <p class="label">Páginas revisadas</p>
                    </div>

                    <div class="card">
                        <p class="metric"><?= e($enlacesDetectados) ?></p>
                        <p class="label">Enlaces detectados</p>
                    </div>

                    <div class="card">
                        <p class="metric"><?= e($enlacesRotos) ?></p>
                        <p class="label">Enlaces rotos</p>
                    </div>
                </div>

                <section class="note">
                    <strong>Interpretación:</strong> este panel combina dos tipos de seguimiento. Por una parte, analiza logs ficticios para medir actividad de usuarios, como visitas, páginas consultadas, formularios enviados y errores. Por otra parte, utiliza un minibot para revisar técnicamente la web, detectando enlaces rotos, elementos SEO básicos y el estado general de cada página.

                    <span class="note-url">Web analizada: <?= e($webAnalizada) ?></span>
                    <span class="note-url">Estado general: <?= e($estadoGeneral) ?></span>
                </section>
            </section>

            <section id="semaforo" class="panel-section">
                <div class="section-heading">
                    <div>
                        <h2>Semáforo técnico del minibot</h2>
                        <p>Resumen del estado técnico de la web según la revisión automática.</p>
                    </div>
                </div>

                <div class="status-grid">
                    <article class="status-card status-ok">
                        <span class="status-label">Correcto</span>
                        <strong><?= e($paginasCorrectas) ?></strong>
                        <p>Páginas que cargan bien y tienen los elementos básicos.</p>
                    </article>

                    <article class="status-card status-warning">
                        <span class="status-label">Mejorable</span>
                        <strong><?= e($paginasMejorables) ?></strong>
                        <p>Páginas que cargan, pero necesitan ajustar algún elemento.</p>
                    </article>

                    <article class="status-card status-error">
                        <span class="status-label">Revisar</span>
                        <strong><?= e($paginasARevisar) ?></strong>
                        <p>Páginas con errores, enlaces rotos o fallos importantes.</p>
                    </article>

                    <article class="status-card status-links">
                        <span class="status-label">Enlaces rotos</span>
                        <strong><?= e($enlacesRotos) ?></strong>
                        <p>Enlaces internos que no responden correctamente.</p>
                    </article>
                </div>
            </section>

            <section id="historial" class="panel-section">
                <div class="section-heading">
                    <div>
                        <h2>Historial de seguimiento</h2>
                        <p>Registro de las últimas ejecuciones para comparar la evolución del análisis.</p>
                    </div>
                </div>

                <div class="chart-grid chart-grid-single">
                    <?= graficaHtml($graficasPath, $graficasUrl, "historial_ejecuciones.png", "Historial de seguimiento por ejecución") ?>
                </div>

                <?= tablaHtml(
                    ["Ejecución", "Fecha", "Visitas", "Formularios", "Errores", "Páginas revisadas", "Enlaces detectados"],
                    $historial
                ) ?>
            </section>

            <section id="graficas" class="panel-section">
                <div class="section-heading">
                    <div>
                        <h2>Gráficas de actividad</h2>
                        <p>Haz clic sobre cualquier gráfica para verla ampliada.</p>
                    </div>
                </div>

                <div class="chart-grid">
                    <?= graficaHtml($graficasPath, $graficasUrl, "distribucion_actividad_tarta.png", "Distribución de actividad") ?>
                    <?= graficaHtml($graficasPath, $graficasUrl, "paginas_mas_visitadas.png", "Páginas más visitadas") ?>
                    <?= graficaHtml($graficasPath, $graficasUrl, "visitas_por_dia.png", "Visitas por día") ?>
                    <?= graficaHtml($graficasPath, $graficasUrl, "formularios_por_dia.png", "Formularios por día") ?>
                    <?= graficaHtml($graficasPath, $graficasUrl, "errores_http.png", "Errores HTTP") ?>
                    <?= graficaHtml($graficasPath, $graficasUrl, "actividad_por_hora.png", "Actividad por hora") ?>
                    <?= graficaHtml($graficasPath, $graficasUrl, "revision_minibot.png", "Semáforo técnico del minibot") ?>
                    <?= graficaHtml($graficasPath, $graficasUrl, "seo_basico_minibot.png", "SEO básico detectado") ?>
                    <?= graficaHtml($graficasPath, $graficasUrl, "enlaces_rotos_minibot.png", "Estado de enlaces internos") ?>
                </div>
            </section>

            <section id="tablas" class="panel-section">
                <h2>Páginas más visitadas</h2>
                <?= tablaHtml(["Página", "Visitas"], $paginasMasVisitadas) ?>

                <h2 class="section-subtitle">Errores detectados</h2>
                <?= tablaHtml(["Página", "Código", "Cantidad"], $erroresDetectados) ?>
            </section>

            <section id="minibot" class="panel-section">
                <h2>Revisión técnica del minibot</h2>
                <?= tablaHtml(
                    ["URL", "Código", "Tiempo", "Title", "Description", "H1", "Lang ES", "Enlaces rotos", "Estado", "Aviso/Error"],
                    $revisionMinibot
                ) ?>

                <h2 class="section-subtitle">Enlaces rotos detectados</h2>
                <?= tablaHtml(["Origen", "Destino", "Código", "Observación"], $enlacesRotosTabla) ?>
            </section>

            <section id="exportar" class="panel-section export-section">
                <div>
                    <h2>Exportar informe</h2>
                    <p>Este panel está preparado para imprimirse o guardarse como PDF desde el navegador.</p>
                    <p>Fecha del informe: <?= e($fechaInforme) ?></p>
                </div>

                <button class="print-btn print-btn-large" type="button" onclick="window.print()">
                    Imprimir / guardar como PDF
                </button>
            </section>
        </main>
    </div>

    <div class="modal" id="chartModal" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>

        <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="modalChartTitle">
            <button class="modal-close" type="button" data-close-modal aria-label="Cerrar gráfica">×</button>
            <h2 id="modalChartTitle">Gráfica</h2>
            <img id="modalChartImg" src="" alt="">
        </div>
    </div>

    <script src="<?= e($assetsJs) ?>"></script>
</body>
</html>