<?php
// ==========================================================
// Objetivo:
// - Mostrar visualmente la carta estructurada generada por Python
// - Consultar platos, ingredientes, alérgenos y fichas técnicas
// - Mostrar resúmenes generados con Ollama
// - Mostrar análisis documental e informe del proceso integral
// ==========================================================

declare(strict_types=1);

// ==========================================================
// RUTAS
// ==========================================================

$cartaPath = __DIR__ . "/out/carta_kitcherry.json";
$analisisPath = __DIR__ . "/out/analisis_ollama_carta.json";
$procesoPath = __DIR__ . "/out/proceso_integral_kitcherry.json";
$summariesDir = __DIR__ . "/summaries";

// ==========================================================
// FUNCIONES AUXILIARES
// ==========================================================

function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

function cargarJson(string $ruta, array $defecto = []): array
{
    if (!file_exists($ruta)) {
        return $defecto;
    }

    $contenido = file_get_contents($ruta);

    if ($contenido === false || trim($contenido) === "") {
        return $defecto;
    }

    $datos = json_decode($contenido, true);

    if (!is_array($datos)) {
        return $defecto;
    }

    return $datos;
}

function cargarResumenes(string $carpeta): array
{
    $resumenes = [];

    if (!is_dir($carpeta)) {
        return $resumenes;
    }

    $archivos = scandir($carpeta);

    if ($archivos === false) {
        return $resumenes;
    }

    foreach ($archivos as $archivo) {
        if (str_ends_with($archivo, ".summary.txt")) {
            $ruta = $carpeta . "/" . $archivo;
            $contenido = file_get_contents($ruta);

            if ($contenido !== false) {
                $resumenes[] = [
                    "archivo" => $archivo,
                    "contenido" => trim($contenido)
                ];
            }
        }
    }

    return $resumenes;
}

function obtenerCategorias(array $platos): array
{
    $categorias = [];

    foreach ($platos as $plato) {
        $categoria = trim((string)($plato["categoria"] ?? ""));

        if ($categoria !== "" && !in_array($categoria, $categorias, true)) {
            $categorias[] = $categoria;
        }
    }

    sort($categorias);

    return $categorias;
}

function obtenerAlergenos(array $platos): array
{
    $alergenos = [];

    foreach ($platos as $plato) {
        $lista = $plato["alergenos_declarados"] ?? [];

        if (!is_array($lista)) {
            continue;
        }

        foreach ($lista as $alergeno) {
            $alergeno = trim((string)$alergeno);

            if ($alergeno !== "" && !in_array($alergeno, $alergenos, true)) {
                $alergenos[] = $alergeno;
            }
        }
    }

    sort($alergenos);

    return $alergenos;
}

function tieneFichaTecnica(array $plato): bool
{
    $ficha = $plato["ficha_tecnica"] ?? [];

    if (!is_array($ficha)) {
        return false;
    }

    foreach ($ficha as $valor) {
        if (trim((string)$valor) !== "") {
            return true;
        }
    }

    return false;
}

function claseRevision(string $estado): string
{
    $estado = strtolower(trim($estado));

    return match ($estado) {
        "revisado" => "estado-revisado",
        "corregido" => "estado-corregido",
        default => "estado-pendiente"
    };
}

function claseNivelRevision(string $nivel): string
{
    $nivel = strtolower(trim($nivel));

    return match ($nivel) {
        "bajo" => "nivel-bajo",
        "medio" => "nivel-medio",
        "alto" => "nivel-alto",
        default => "nivel-medio"
    };
}

// ==========================================================
// CARGA DE DATOS
// ==========================================================

$carta = cargarJson($cartaPath);
$analisis = cargarJson($analisisPath);
$proceso = cargarJson($procesoPath);
$resumenes = cargarResumenes($summariesDir);

$platos = $carta["platos"] ?? [];
$negocio = $carta["negocio"] ?? "Negocio no especificado";
$totalPlatos = count($platos);

$categorias = obtenerCategorias($platos);
$alergenos = obtenerAlergenos($platos);

$totalConAlergenos = 0;
$totalConFicha = 0;
$totalPendientes = 0;

foreach ($platos as $plato) {
    $listaAlergenos = $plato["alergenos_declarados"] ?? [];

    if (is_array($listaAlergenos) && count($listaAlergenos) > 0) {
        $totalConAlergenos++;
    }

    if (tieneFichaTecnica($plato)) {
        $totalConFicha++;
    }

    if (($plato["estado_revision"] ?? "pendiente") === "pendiente") {
        $totalPendientes++;
    }
}

$resultadosAnalisis = $analisis["resultados"] ?? [];
$estadoProceso = $proceso["estado_general"] ?? "sin ejecutar";
$resumenSalidas = $proceso["resumen_salidas"] ?? [];
$pasosProceso = $proceso["pasos"] ?? [];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry Docs | Informe de carta y alérgenos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        /* ==================================================
           VARIABLES
        ================================================== */

        :root {
            --rojo: #C2182B;
            --rojo-oscuro: #991726;
            --rojo-suave: #fbeaec;
            --negro: #151515;
            --gris-texto: #555555;
            --gris-claro: #f7f7f7;
            --gris-medio: #e9e9e9;
            --blanco: #ffffff;
            --verde: #16803c;
            --verde-suave: #e8f7ee;
            --amarillo: #9a6b00;
            --amarillo-suave: #fff7dc;
            --azul: #1d4ed8;
            --azul-suave: #eaf1ff;
            --sombra: 0 18px 40px rgba(0, 0, 0, 0.08);
        }

        /* ==================================================
           RESET
        ================================================== */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: var(--gris-claro);
            color: var(--negro);
            line-height: 1.6;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        /* ==================================================
           LAYOUT GENERAL
        ================================================== */

        .page {
            min-height: 100vh;
        }

        .topbar {
            background: var(--blanco);
            border-bottom: 1px solid var(--gris-medio);
            position: sticky;
            top: 0;
            z-index: 20;
        }

        .topbar-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .brand {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .brand strong {
            font-size: 1.45rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .brand .kit {
            color: var(--negro);
        }

        .brand .cherry {
            color: var(--rojo);
        }

        .brand span:last-child {
            font-size: 0.85rem;
            color: var(--gris-texto);
        }

        .nav {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .nav a {
            font-size: 0.9rem;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--gris-claro);
            color: var(--gris-texto);
            border: 1px solid transparent;
        }

        .nav a:hover {
            border-color: var(--rojo);
            color: var(--rojo);
            background: var(--rojo-suave);
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 32px 24px 60px;
        }

        /* ==================================================
           HERO
        ================================================== */

        .hero {
            background: linear-gradient(135deg, var(--rojo), var(--rojo-oscuro));
            color: var(--blanco);
            border-radius: 28px;
            padding: 38px;
            box-shadow: var(--sombra);
            display: grid;
            grid-template-columns: 1.4fr 0.8fr;
            gap: 30px;
            align-items: center;
            margin-bottom: 30px;
        }

        .hero h1 {
            font-size: clamp(2rem, 4vw, 4rem);
            line-height: 1;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 16px;
        }

        .hero p {
            max-width: 760px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.05rem;
        }

        .hero-panel {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 22px;
            padding: 22px;
        }

        .hero-panel strong {
            display: block;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.75);
        }

        .hero-panel span {
            display: block;
            font-size: 1.4rem;
            font-weight: 700;
        }

        .hero-meta {
            margin-top: 18px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .hero-meta span {
            background: rgba(255, 255, 255, 0.15);
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 0.85rem;
        }

        /* ==================================================
           BLOQUES
        ================================================== */

        .section {
            margin-top: 34px;
        }

        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 20px;
            margin-bottom: 18px;
        }

        .section-title h2 {
            font-size: 1.7rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .section-title p {
            color: var(--gris-texto);
            max-width: 720px;
            font-size: 0.95rem;
        }

        .card {
            background: var(--blanco);
            border: 1px solid var(--gris-medio);
            border-radius: 22px;
            box-shadow: var(--sombra);
        }

        /* ==================================================
           ESTADÍSTICAS
        ================================================== */

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        .stat-card {
            background: var(--blanco);
            border: 1px solid var(--gris-medio);
            border-radius: 20px;
            padding: 22px;
            box-shadow: var(--sombra);
        }

        .stat-card span {
            color: var(--gris-texto);
            font-size: 0.88rem;
        }

        .stat-card strong {
            display: block;
            font-size: 2rem;
            line-height: 1.1;
            margin-top: 8px;
            color: var(--rojo);
        }

        /* ==================================================
           FILTROS
        ================================================== */

        .filters {
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr 1fr;
            gap: 14px;
            padding: 18px;
            margin-bottom: 18px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field label {
            font-size: 0.82rem;
            color: var(--gris-texto);
            font-weight: 700;
        }

        .field input,
        .field select {
            width: 100%;
            border: 1px solid var(--gris-medio);
            background: var(--blanco);
            border-radius: 14px;
            padding: 12px 14px;
            font-size: 0.95rem;
            outline: none;
        }

        .field input:focus,
        .field select:focus {
            border-color: var(--rojo);
            box-shadow: 0 0 0 4px var(--rojo-suave);
        }

        .visible-counter {
            color: var(--gris-texto);
            font-size: 0.9rem;
        }

        /* ==================================================
           CARTA
        ================================================== */

        .platos-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .plato-card {
            padding: 22px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .plato-header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
        }

        .plato-header h3 {
            font-size: 1.25rem;
            line-height: 1.2;
        }

        .precio {
            background: var(--negro);
            color: var(--blanco);
            padding: 8px 12px;
            border-radius: 999px;
            font-weight: 700;
            white-space: nowrap;
            font-size: 0.9rem;
        }

        .plato-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 0.82rem;
            font-weight: 700;
            border: 1px solid transparent;
        }

        .badge-categoria {
            background: var(--azul-suave);
            color: var(--azul);
        }

        .estado-pendiente {
            background: var(--amarillo-suave);
            color: var(--amarillo);
        }

        .estado-revisado {
            background: var(--verde-suave);
            color: var(--verde);
        }

        .estado-corregido {
            background: var(--azul-suave);
            color: var(--azul);
        }

        .descripcion {
            color: var(--gris-texto);
        }

        .chip-group {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            padding: 6px 9px;
            border-radius: 999px;
            font-size: 0.78rem;
            background: var(--gris-claro);
            border: 1px solid var(--gris-medio);
            color: var(--gris-texto);
        }

        .chip-alergeno {
            background: var(--rojo-suave);
            color: var(--rojo);
            border-color: rgba(194, 24, 43, 0.18);
            font-weight: 700;
        }

        .mini-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--gris-texto);
            font-weight: 700;
            margin-bottom: 8px;
        }

        details {
            background: var(--gris-claro);
            border: 1px solid var(--gris-medio);
            border-radius: 16px;
            padding: 12px 14px;
        }

        summary {
            cursor: pointer;
            font-weight: 700;
            color: var(--negro);
        }

        .details-body {
            margin-top: 12px;
            color: var(--gris-texto);
            display: grid;
            gap: 10px;
            font-size: 0.92rem;
        }

        .source-list {
            color: var(--gris-texto);
            font-size: 0.82rem;
        }

        /* ==================================================
           RESÚMENES / ANÁLISIS / PROCESO
        ================================================== */

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .info-card {
            padding: 20px;
        }

        .info-card h3 {
            margin-bottom: 10px;
            font-size: 1.05rem;
        }

        .info-card p {
            color: var(--gris-texto);
            font-size: 0.92rem;
        }

        .nivel-alto {
            background: var(--rojo-suave);
            color: var(--rojo);
        }

        .nivel-medio {
            background: var(--amarillo-suave);
            color: var(--amarillo);
        }

        .nivel-bajo {
            background: var(--verde-suave);
            color: var(--verde);
        }

        .process-list {
            display: grid;
            gap: 12px;
        }

        .process-item {
            background: var(--blanco);
            border: 1px solid var(--gris-medio);
            border-radius: 18px;
            padding: 16px;
            display: grid;
            grid-template-columns: 90px 1fr auto;
            gap: 14px;
            align-items: center;
        }

        .step-number {
            background: var(--rojo);
            color: var(--blanco);
            border-radius: 14px;
            padding: 10px;
            font-weight: 700;
            text-align: center;
        }

        .step-info strong {
            display: block;
        }

        .step-info span {
            color: var(--gris-texto);
            font-size: 0.88rem;
        }

        .step-status {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.82rem;
            border-radius: 999px;
            padding: 7px 10px;
        }

        .status-ok {
            color: var(--verde);
            background: var(--verde-suave);
        }

        .status-error {
            color: var(--rojo);
            background: var(--rojo-suave);
        }

        .status-omitido {
            color: var(--amarillo);
            background: var(--amarillo-suave);
        }

        .empty {
            padding: 30px;
            color: var(--gris-texto);
            text-align: center;
        }

        /* ==================================================
           RESPONSIVE
        ================================================== */

        @media (max-width: 1000px) {
            .hero {
                grid-template-columns: 1fr;
            }

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .filters {
                grid-template-columns: 1fr 1fr;
            }

            .platos-grid {
                grid-template-columns: 1fr;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 650px) {
            .topbar-inner {
                align-items: flex-start;
                flex-direction: column;
            }

            .hero {
                padding: 26px;
                border-radius: 20px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .filters {
                grid-template-columns: 1fr;
            }

            .process-item {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 24px 16px 50px;
            }
        }
    </style>
</head>

<body>
<div class="page">

    <header class="topbar">
        <div class="topbar-inner">
            <div class="brand">
                <strong><span class="kit">KIT</span><span class="cherry">CHERRY</span></strong>
                <span>Plataforma de información de carta, platos y alérgenos</span>
            </div>

            <nav class="nav">
                <a href="#carta">Carta</a>
                <a href="#resumenes">Resúmenes</a>
                <a href="#analisis">Análisis IA</a>
                <a href="#proceso">Proceso</a>
            </nav>
        </div>
    </header>

    <main class="container">

        <section class="hero">
            <div>
                <h1>Kitcherry Docs</h1>
                <p>
                    Visor de carta estructurada para consultar platos, ingredientes, alérgenos,
                    fichas técnicas y documentos procesados dentro del módulo de información alimentaria.
                </p>

                <div class="hero-meta">
                    <span>Negocio: <?= e($negocio) ?></span>
                    <span>Estado: <?= e($carta["estado_general"] ?? "sin estado") ?></span>
                    <span>Generado: <?= e($carta["fecha_generacion"] ?? "sin fecha") ?></span>
                </div>
            </div>

            <div class="hero-panel">
                <strong>Proceso integral</strong>
                <span><?= e($estadoProceso) ?></span>
                <div class="hero-meta">
                    <span><?= e((string)($resumenSalidas["txt_limpios_generados"] ?? 0)) ?> TXT limpios</span>
                    <span><?= e((string)($resumenSalidas["resumenes_generados"] ?? 0)) ?> resúmenes</span>
                    <span><?= e((string)($resumenSalidas["documentos_analizados_ollama"] ?? 0)) ?> análisis IA</span>
                </div>
            </div>
        </section>

        <section class="stats">
            <article class="stat-card">
                <span>Platos estructurados</span>
                <strong><?= e((string)$totalPlatos) ?></strong>
            </article>

            <article class="stat-card">
                <span>Platos con alérgenos</span>
                <strong><?= e((string)$totalConAlergenos) ?></strong>
            </article>

            <article class="stat-card">
                <span>Con ficha técnica</span>
                <strong><?= e((string)$totalConFicha) ?></strong>
            </article>

            <article class="stat-card">
                <span>Pendientes de revisión</span>
                <strong><?= e((string)$totalPendientes) ?></strong>
            </article>
        </section>

        <section id="carta" class="section">
            <div class="section-title">
                <div>
                    <h2>Carta estructurada</h2>
                    <p>
                        Consulta los platos generados desde los documentos de prueba. La información de alérgenos
                        aparece marcada como pendiente hasta que sea revisada por el responsable del establecimiento.
                    </p>
                </div>
                <span class="visible-counter">
                    Mostrando <strong id="contadorVisibles"><?= e((string)$totalPlatos) ?></strong> platos
                </span>
            </div>

            <div class="card filters">
                <div class="field">
                    <label for="buscar">Buscar</label>
                    <input type="search" id="buscar" placeholder="Buscar plato, ingrediente o alérgeno...">
                </div>

                <div class="field">
                    <label for="categoria">Categoría</label>
                    <select id="categoria">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?= e(mb_strtolower($categoria)) ?>"><?= e($categoria) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="alergeno">Alérgeno</label>
                    <select id="alergeno">
                        <option value="">Todos</option>
                        <?php foreach ($alergenos as $alergeno): ?>
                            <option value="<?= e(mb_strtolower($alergeno)) ?>"><?= e($alergeno) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="revision">Revisión</label>
                    <select id="revision">
                        <option value="">Todos</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="revisado">Revisado</option>
                        <option value="corregido">Corregido</option>
                    </select>
                </div>
            </div>

            <?php if (empty($platos)): ?>
                <div class="card empty">
                    No se ha encontrado información de carta. Ejecuta primero el proceso integral.
                </div>
            <?php else: ?>
                <div class="platos-grid" id="platosGrid">
                    <?php foreach ($platos as $plato): ?>
                        <?php
                        $nombre = $plato["nombre"] ?? "";
                        $categoria = $plato["categoria"] ?? "";
                        $precio = $plato["precio"] ?? "";
                        $descripcion = $plato["descripcion"] ?? "";
                        $ingredientes = $plato["ingredientes_detectados"] ?? [];
                        $alergenosPlato = $plato["alergenos_declarados"] ?? [];
                        $estado = $plato["estado_revision"] ?? "pendiente";
                        $ficha = $plato["ficha_tecnica"] ?? [];
                        $fuentes = $plato["fuentes"] ?? [];

                        $textoBusqueda = implode(" ", [
                            $nombre,
                            $categoria,
                            $descripcion,
                            implode(" ", is_array($ingredientes) ? $ingredientes : []),
                            implode(" ", is_array($alergenosPlato) ? $alergenosPlato : [])
                        ]);

                        $dataAlergenos = is_array($alergenosPlato)
                            ? mb_strtolower(implode("|", $alergenosPlato))
                            : "";
                        ?>

                        <article
                            class="card plato-card js-plato"
                            data-text="<?= e(mb_strtolower($textoBusqueda)) ?>"
                            data-categoria="<?= e(mb_strtolower($categoria)) ?>"
                            data-alergenos="<?= e($dataAlergenos) ?>"
                            data-revision="<?= e(mb_strtolower($estado)) ?>"
                        >
                            <div class="plato-header">
                                <div>
                                    <h3><?= e($nombre) ?></h3>
                                    <div class="plato-meta">
                                        <span class="badge badge-categoria"><?= e($categoria !== "" ? $categoria : "Sin categoría") ?></span>
                                        <span class="badge <?= e(claseRevision($estado)) ?>"><?= e($estado) ?></span>
                                    </div>
                                </div>

                                <span class="precio"><?= e($precio !== "" ? $precio : "Sin precio") ?></span>
                            </div>

                            <?php if ($descripcion !== ""): ?>
                                <p class="descripcion"><?= e($descripcion) ?></p>
                            <?php endif; ?>

                            <div>
                                <div class="mini-title">Ingredientes detectados</div>
                                <div class="chip-group">
                                    <?php if (is_array($ingredientes) && count($ingredientes) > 0): ?>
                                        <?php foreach ($ingredientes as $ingrediente): ?>
                                            <span class="chip"><?= e($ingrediente) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="chip">Sin ingredientes detectados</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div>
                                <div class="mini-title">Alérgenos declarados</div>
                                <div class="chip-group">
                                    <?php if (is_array($alergenosPlato) && count($alergenosPlato) > 0): ?>
                                        <?php foreach ($alergenosPlato as $alergeno): ?>
                                            <span class="chip chip-alergeno"><?= e($alergeno) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="chip">Sin alérgenos declarados</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (tieneFichaTecnica($plato)): ?>
                                <details>
                                    <summary>Ver ficha técnica</summary>
                                    <div class="details-body">
                                        <?php if (!empty($ficha["raciones"])): ?>
                                            <p><strong>Raciones:</strong> <?= e($ficha["raciones"]) ?></p>
                                        <?php endif; ?>

                                        <?php if (!empty($ficha["ingredientes"])): ?>
                                            <p><strong>Ingredientes:</strong> <?= e($ficha["ingredientes"]) ?></p>
                                        <?php endif; ?>

                                        <?php if (!empty($ficha["elaboracion"])): ?>
                                            <p><strong>Elaboración:</strong> <?= e($ficha["elaboracion"]) ?></p>
                                        <?php endif; ?>

                                        <?php if (!empty($ficha["conservacion"])): ?>
                                            <p><strong>Conservación:</strong> <?= e($ficha["conservacion"]) ?></p>
                                        <?php endif; ?>

                                        <?php if (!empty($ficha["alergenos_texto"])): ?>
                                            <p><strong>Alérgenos en ficha:</strong> <?= e($ficha["alergenos_texto"]) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            <?php endif; ?>

                            <?php if (is_array($fuentes) && count($fuentes) > 0): ?>
                                <p class="source-list">
                                    <strong>Fuentes:</strong> <?= e(implode(", ", $fuentes)) ?>
                                </p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section id="resumenes" class="section">
            <div class="section-title">
                <div>
                    <h2>Resúmenes generados</h2>
                    <p>
                        Resúmenes automáticos creados desde los TXT limpios mediante Ollama.
                    </p>
                </div>
            </div>

            <?php if (empty($resumenes)): ?>
                <div class="card empty">
                    No hay resúmenes disponibles. Ejecuta el paso 005 o el proceso integral.
                </div>
            <?php else: ?>
                <div class="info-grid">
                    <?php foreach ($resumenes as $resumen): ?>
                        <article class="card info-card">
                            <h3><?= e($resumen["archivo"]) ?></h3>
                            <p><?= e($resumen["contenido"]) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section id="analisis" class="section">
            <div class="section-title">
                <div>
                    <h2>Análisis documental con IA</h2>
                    <p>
                        Clasificación y revisión generada por Ollama para cada documento procesado.
                    </p>
                </div>
            </div>

            <?php if (empty($resultadosAnalisis)): ?>
                <div class="card empty">
                    No hay análisis documental disponible. Ejecuta el paso 006 o el proceso integral.
                </div>
            <?php else: ?>
                <div class="info-grid">
                    <?php foreach ($resultadosAnalisis as $resultado): ?>
                        <?php
                        $ia = $resultado["analisis_ia"] ?? [];
                        $tipo = $ia["tipo_documento"] ?? "otro";
                        $nivel = $ia["nivel_revision_recomendado"] ?? "medio";
                        $platosIa = $ia["platos_mencionados"] ?? [];
                        $alergenosIa = $ia["alergenos_mencionados"] ?? [];
                        $advertenciasIa = $ia["advertencias"] ?? [];
                        ?>

                        <article class="card info-card">
                            <div class="plato-meta" style="margin-bottom: 12px;">
                                <span class="badge badge-categoria"><?= e($tipo) ?></span>
                                <span class="badge <?= e(claseNivelRevision($nivel)) ?>">Revisión <?= e($nivel) ?></span>
                            </div>

                            <h3><?= e($resultado["archivo"] ?? "Documento") ?></h3>

                            <?php if (!empty($ia["resumen_utilidad"])): ?>
                                <p><?= e($ia["resumen_utilidad"]) ?></p>
                            <?php endif; ?>

                            <?php if (is_array($platosIa) && count($platosIa) > 0): ?>
                                <div style="margin-top: 14px;">
                                    <div class="mini-title">Platos mencionados</div>
                                    <div class="chip-group">
                                        <?php foreach ($platosIa as $platoIa): ?>
                                            <span class="chip"><?= e($platoIa) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (is_array($alergenosIa) && count($alergenosIa) > 0): ?>
                                <div style="margin-top: 14px;">
                                    <div class="mini-title">Alérgenos mencionados</div>
                                    <div class="chip-group">
                                        <?php foreach ($alergenosIa as $alergenoIa): ?>
                                            <span class="chip chip-alergeno"><?= e($alergenoIa) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($ia["uso_en_kitcherry"])): ?>
                                <div style="margin-top: 14px;">
                                    <div class="mini-title">Uso en Kitcherry</div>
                                    <p><?= e($ia["uso_en_kitcherry"]) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($ia["motivo_revision"])): ?>
                                <div style="margin-top: 14px;">
                                    <div class="mini-title">Motivo de revisión</div>
                                    <p><?= e($ia["motivo_revision"]) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (is_array($advertenciasIa) && count($advertenciasIa) > 0): ?>
                                <details style="margin-top: 14px;">
                                    <summary>Advertencias</summary>
                                    <div class="details-body">
                                        <?php foreach ($advertenciasIa as $advertencia): ?>
                                            <p><?= e($advertencia) ?></p>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section id="proceso" class="section">
            <div class="section-title">
                <div>
                    <h2>Proceso integral</h2>
                    <p>
                        Registro de ejecución de las iteraciones del proyecto.
                    </p>
                </div>
            </div>

            <?php if (empty($pasosProceso)): ?>
                <div class="card empty">
                    No hay informe del proceso integral. Ejecuta el archivo 007-proceso-integral-kitcherry.py.
                </div>
            <?php else: ?>
                <div class="process-list">
                    <?php foreach ($pasosProceso as $paso): ?>
                        <?php
                        $estadoPaso = $paso["estado"] ?? "sin_estado";

                        $claseEstado = match ($estadoPaso) {
                            "ok" => "status-ok",
                            "omitido" => "status-omitido",
                            default => "status-error"
                        };
                        ?>

                        <article class="process-item">
                            <div class="step-number"><?= e($paso["numero"] ?? "") ?></div>

                            <div class="step-info">
                                <strong><?= e($paso["nombre"] ?? "") ?></strong>
                                <span><?= e($paso["archivo"] ?? "") ?> · <?= e((string)($paso["duracion_segundos"] ?? 0)) ?> segundos</span>
                            </div>

                            <span class="step-status <?= e($claseEstado) ?>"><?= e($estadoPaso) ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    </main>
</div>

<script>
    const buscar = document.getElementById("buscar");
    const categoria = document.getElementById("categoria");
    const alergeno = document.getElementById("alergeno");
    const revision = document.getElementById("revision");
    const platos = Array.from(document.querySelectorAll(".js-plato"));
    const contadorVisibles = document.getElementById("contadorVisibles");

    function normalizar(texto) {
        return (texto || "")
            .toString()
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .trim();
    }

    function filtrarPlatos() {
        const textoBuscar = normalizar(buscar.value);
        const categoriaSeleccionada = normalizar(categoria.value);
        const alergenoSeleccionado = normalizar(alergeno.value);
        const revisionSeleccionada = normalizar(revision.value);

        let visibles = 0;

        platos.forEach(function (plato) {
            const texto = normalizar(plato.dataset.text);
            const categoriaPlato = normalizar(plato.dataset.categoria);
            const alergenosPlato = normalizar(plato.dataset.alergenos);
            const revisionPlato = normalizar(plato.dataset.revision);

            const coincideTexto = textoBuscar === "" || texto.includes(textoBuscar);
            const coincideCategoria = categoriaSeleccionada === "" || categoriaPlato === categoriaSeleccionada;
            const coincideAlergeno = alergenoSeleccionado === "" || alergenosPlato.includes(alergenoSeleccionado);
            const coincideRevision = revisionSeleccionada === "" || revisionPlato === revisionSeleccionada;

            const visible = coincideTexto && coincideCategoria && coincideAlergeno && coincideRevision;

            plato.style.display = visible ? "" : "none";

            if (visible) {
                visibles++;
            }
        });

        contadorVisibles.textContent = visibles;
    }

    buscar.addEventListener("input", filtrarPlatos);
    categoria.addEventListener("change", filtrarPlatos);
    alergeno.addEventListener("change", filtrarPlatos);
    revision.addEventListener("change", filtrarPlatos);
</script>

</body>
</html>