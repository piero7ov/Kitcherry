<?php
// ==========================================================
// Objetivo:
// - Mantener la consulta por alérgenos
// - Mantener la tabla/matriz de alérgenos
// - Permitir cambiar el estado de revisión de cada plato
// - Guardar el estado actualizado en out/carta_kitcherry.json
// - Mantener carta, documentos procesados y detalle técnico
// ==========================================================

declare(strict_types=1);

// ==========================================================
// RUTAS
// ==========================================================

$cartaPath = __DIR__ . "/out/carta_kitcherry.json";
$analisisPath = __DIR__ . "/out/analisis_ollama_carta.json";
$procesoPath = __DIR__ . "/out/proceso_integral_kitcherry.json";
$summariesDir = __DIR__ . "/summaries";

$logoWebPath = "logo/logo.png";
$logoAbsPath = __DIR__ . "/logo/logo.png";

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

function guardarJson(string $ruta, array $datos): bool
{
    $json = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        return false;
    }

    return file_put_contents($ruta, $json) !== false;
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

function minusculas(string $texto): string
{
    return mb_strtolower($texto, "UTF-8");
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

function nombreDocumentoAmigable(string $archivo): string
{
    $nombre = str_replace(".summary.txt", "", $archivo);
    $nombre = str_replace("_limpio", "", $nombre);
    $nombre = str_replace("_", " ", $nombre);

    return ucfirst($nombre);
}

function obtenerTextoFicha(array $plato): string
{
    $ficha = $plato["ficha_tecnica"] ?? [];

    if (!is_array($ficha)) {
        return "";
    }

    return trim(implode(" ", array_map("strval", $ficha)));
}

function platoTieneAlergeno(array $plato, string $alergenoBuscado): bool
{
    $alergenosPlato = $plato["alergenos_declarados"] ?? [];

    if (!is_array($alergenosPlato)) {
        return false;
    }

    $alergenoBuscado = minusculas($alergenoBuscado);

    foreach ($alergenosPlato as $alergeno) {
        if (minusculas((string)$alergeno) === $alergenoBuscado) {
            return true;
        }
    }

    return false;
}

function textoEstadoRevision(string $estado): string
{
    return match ($estado) {
        "revisado" => "Revisado",
        "corregido" => "Corregido",
        default => "Pendiente"
    };
}

function procesarActualizacionRevision(string $rutaCarta, array &$carta): array
{
    $respuesta = [
        "tipo" => "",
        "mensaje" => ""
    ];

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        return $respuesta;
    }

    $accion = $_POST["accion"] ?? "";

    if ($accion !== "actualizar_revision") {
        return $respuesta;
    }

    $platoId = (int)($_POST["plato_id"] ?? 0);
    $nuevoEstado = strtolower(trim((string)($_POST["nuevo_estado"] ?? "")));

    $estadosPermitidos = ["pendiente", "revisado", "corregido"];

    if ($platoId <= 0 || !in_array($nuevoEstado, $estadosPermitidos, true)) {
        return [
            "tipo" => "error",
            "mensaje" => "No se pudo actualizar el estado porque los datos recibidos no son válidos."
        ];
    }

    if (!isset($carta["platos"]) || !is_array($carta["platos"])) {
        return [
            "tipo" => "error",
            "mensaje" => "No se pudo actualizar el estado porque no se encontró la carta estructurada."
        ];
    }

    $indiceEncontrado = null;

    foreach ($carta["platos"] as $indice => $plato) {
        if ((int)($plato["id"] ?? 0) === $platoId) {
            $indiceEncontrado = $indice;
            break;
        }
    }

    if ($indiceEncontrado === null) {
        return [
            "tipo" => "error",
            "mensaje" => "No se encontró el plato seleccionado dentro de la carta."
        ];
    }

    $fechaActual = date("Y-m-d H:i:s");

    $carta["platos"][$indiceEncontrado]["estado_revision"] = $nuevoEstado;
    $carta["platos"][$indiceEncontrado]["revision_actualizada_en"] = $fechaActual;

    if (!isset($carta["platos"][$indiceEncontrado]["historial_revision"])) {
        $carta["platos"][$indiceEncontrado]["historial_revision"] = [];
    }

    if (!is_array($carta["platos"][$indiceEncontrado]["historial_revision"])) {
        $carta["platos"][$indiceEncontrado]["historial_revision"] = [];
    }

    $carta["platos"][$indiceEncontrado]["historial_revision"][] = [
        "estado" => $nuevoEstado,
        "fecha" => $fechaActual,
        "origen" => "panel_012"
    ];

    $carta["estado_general"] = "revision_en_proceso";
    $carta["ultima_revision_panel"] = $fechaActual;

    $guardado = guardarJson($rutaCarta, $carta);

    if (!$guardado) {
        return [
            "tipo" => "error",
            "mensaje" => "El estado se modificó en memoria, pero no se pudo guardar en el archivo JSON."
        ];
    }

    $nombrePlato = $carta["platos"][$indiceEncontrado]["nombre"] ?? "Plato";

    return [
        "tipo" => "ok",
        "mensaje" => "Estado actualizado: " . $nombrePlato . " ahora está marcado como " . textoEstadoRevision($nuevoEstado) . "."
    ];
}

// ==========================================================
// CARGA DE DATOS
// ==========================================================

$carta = cargarJson($cartaPath);
$mensajeSistema = procesarActualizacionRevision($cartaPath, $carta);

$analisis = cargarJson($analisisPath);
$proceso = cargarJson($procesoPath);
$resumenes = cargarResumenes($summariesDir);

$platos = $carta["platos"] ?? [];
$negocio = $carta["negocio"] ?? "Negocio no especificado";
$totalPlatos = count($platos);

$categorias = obtenerCategorias($platos);
$alergenos = obtenerAlergenos($platos);

$totalConAlergenos = 0;
$totalSinAlergenos = 0;
$totalConFicha = 0;
$totalPendientes = 0;
$totalRevisados = 0;
$totalCorregidos = 0;

foreach ($platos as $plato) {
    $listaAlergenos = $plato["alergenos_declarados"] ?? [];

    if (is_array($listaAlergenos) && count($listaAlergenos) > 0) {
        $totalConAlergenos++;
    } else {
        $totalSinAlergenos++;
    }

    if (tieneFichaTecnica($plato)) {
        $totalConFicha++;
    }

    $estadoRevision = strtolower((string)($plato["estado_revision"] ?? "pendiente"));

    if ($estadoRevision === "revisado") {
        $totalRevisados++;
    } elseif ($estadoRevision === "corregido") {
        $totalCorregidos++;
    } else {
        $totalPendientes++;
    }
}

$resultadosAnalisis = $analisis["resultados"] ?? [];
$estadoProceso = $proceso["estado_general"] ?? "sin ejecutar";
$pasosProceso = $proceso["pasos"] ?? [];

$logoExiste = file_exists($logoAbsPath);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry Docs | Carta y alérgenos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Hoja de estilos externa -->
    <link rel="stylesheet" href="styles.css">
</head>

<body>
<div class="page">

    <header class="topbar">
        <div class="topbar-inner">
            <a href="#" class="brand">
                <?php if ($logoExiste): ?>
                    <img src="<?= e($logoWebPath) ?>" alt="Logo Kitcherry" class="brand-logo">
                <?php else: ?>
                    <div class="brand-fallback">K</div>
                <?php endif; ?>

                <div class="brand-text">
                    <strong>
                        <span class="kit">KIT</span><span class="cherry">CHERRY DOCS</span>
                    </strong>
                    <span class="brand-subtitle">Herramientas para hostelería</span>
                </div>
            </a>

            <nav class="nav">
                <a href="#consulta-alergenos">Consulta alérgenos</a>
                <a href="#consulta">Carta</a>
                <a href="#tabla-alergenos">Tabla alérgenos</a>
                <a href="#documentos">Documentos</a>
                <a href="#tecnico">Detalle técnico</a>
            </nav>
        </div>
    </header>

    <main class="container">

        <section class="hero">
            <div class="hero-main">
                <span class="eyebrow">Carta, platos y alérgenos</span>

                <h1>Panel de consulta alimentaria</h1>

                <p>
                    Consulta de forma rápida la información de platos, ingredientes, alérgenos y fichas técnicas
                    generada a partir de documentos del establecimiento.
                </p>

                <div class="hero-actions">
                    <a href="#consulta-alergenos" class="btn btn-white">Consultar por alergia</a>
                    <a href="#consulta" class="btn btn-soft">Ver carta completa</a>
                </div>
            </div>

            <aside class="hero-side">
                <div class="quick-status">
                    <h2><?= e($negocio) ?></h2>
                    <p>
                        La carta está estructurada y lista para revisión interna. Los datos pueden consultarse
                        por el equipo de sala, cocina o administración.
                    </p>
                    <span class="status-pill"><?= e((string)$totalPendientes) ?> platos pendientes de revisión</span>
                </div>

                <div class="alert-box">
                    <strong>Revisión necesaria</strong>
                    La información sobre alérgenos debe ser verificada por una persona responsable antes de publicarse
                    o comunicarse al cliente.
                </div>
            </aside>
        </section>

        <?php if ($mensajeSistema["mensaje"] !== ""): ?>
            <div class="system-message <?= e($mensajeSistema["tipo"] === "ok" ? "system-ok" : "system-error") ?>">
                <?= e($mensajeSistema["mensaje"]) ?>
            </div>
        <?php endif; ?>

        <section class="stats">
            <article class="stat-card">
                <span>Platos en carta</span>
                <strong><?= e((string)$totalPlatos) ?></strong>
            </article>

            <article class="stat-card">
                <span>Pendientes</span>
                <strong><?= e((string)$totalPendientes) ?></strong>
            </article>

            <article class="stat-card">
                <span>Revisados</span>
                <strong><?= e((string)$totalRevisados) ?></strong>
            </article>

            <article class="stat-card">
                <span>Corregidos</span>
                <strong><?= e((string)$totalCorregidos) ?></strong>
            </article>
        </section>

        <section class="stats secondary-stats">
            <article class="stat-card">
                <span>Con alérgenos</span>
                <strong><?= e((string)$totalConAlergenos) ?></strong>
            </article>

            <article class="stat-card">
                <span>Sin alérgenos declarados</span>
                <strong><?= e((string)$totalSinAlergenos) ?></strong>
            </article>

            <article class="stat-card">
                <span>Con ficha técnica</span>
                <strong><?= e((string)$totalConFicha) ?></strong>
            </article>

            <article class="stat-card">
                <span>Estado general</span>
                <strong class="stat-text"><?= e($carta["estado_general"] ?? "borrador") ?></strong>
            </article>
        </section>

        <section id="consulta-alergenos" class="section">
            <div class="section-title">
                <div>
                    <h2>Consulta por alérgenos</h2>
                    <p>
                        Selecciona uno o varios alérgenos para ayudar al personal de sala a localizar platos
                        que los contienen o platos aparentemente aptos.
                    </p>
                </div>
            </div>

            <div class="card allergy-consult">
                <div class="consult-header">
                    <div>
                        <h3>¿Qué necesita saber el cliente?</h3>
                        <p>
                            Esta herramienta es de apoyo interno. Antes de confirmar la información al cliente,
                            debe revisarse con el responsable del establecimiento.
                        </p>
                    </div>

                    <div class="consult-mode">
                        <label for="modoAlergenos">Modo de consulta</label>
                        <select id="modoAlergenos">
                            <option value="contienen">Mostrar platos que contienen estos alérgenos</option>
                            <option value="aptos">Mostrar platos aparentemente aptos</option>
                        </select>
                    </div>
                </div>

                <div class="allergy-buttons">
                    <?php foreach ($alergenos as $alergeno): ?>
                        <button
                            type="button"
                            class="allergy-chip js-allergy-chip"
                            data-alergeno="<?= e(minusculas($alergeno)) ?>"
                        >
                            <?= e($alergeno) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="consult-footer">
                    <p id="consultaAlergenosTexto">
                        Selecciona uno o varios alérgenos para iniciar la consulta.
                    </p>

                    <button type="button" class="btn-reset" id="limpiarAlergenos">
                        Limpiar selección
                    </button>
                </div>
            </div>
        </section>

        <section id="consulta" class="section">
            <div class="section-title">
                <div>
                    <h2>Carta y platos</h2>
                    <p>
                        Busca un plato o filtra por categoría, alérgeno o estado de revisión. Esta vista está pensada
                        para consultar información durante el trabajo diario del establecimiento.
                    </p>
                </div>

                <span class="visible-counter">
                    Mostrando <strong id="contadorVisibles"><?= e((string)$totalPlatos) ?></strong> platos
                </span>
            </div>

            <div class="card filters">
                <div class="field">
                    <label for="buscar">Buscar plato, ingrediente o alérgeno</label>
                    <input type="search" id="buscar" placeholder="Ejemplo: queso, gluten, tarta...">
                </div>

                <div class="field">
                    <label for="categoria">Categoría</label>
                    <select id="categoria">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?= e(minusculas($categoria)) ?>"><?= e($categoria) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="alergeno">Contiene alérgeno</label>
                    <select id="alergeno">
                        <option value="">Todos</option>
                        <?php foreach ($alergenos as $alergeno): ?>
                            <option value="<?= e(minusculas($alergeno)) ?>"><?= e($alergeno) ?></option>
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
                        $id = (int)($plato["id"] ?? 0);
                        $nombre = $plato["nombre"] ?? "";
                        $categoria = $plato["categoria"] ?? "";
                        $precio = $plato["precio"] ?? "";
                        $descripcion = $plato["descripcion"] ?? "";
                        $ingredientes = $plato["ingredientes_detectados"] ?? [];
                        $alergenosPlato = $plato["alergenos_declarados"] ?? [];
                        $estado = $plato["estado_revision"] ?? "pendiente";
                        $ficha = $plato["ficha_tecnica"] ?? [];
                        $fuentes = $plato["fuentes"] ?? [];
                        $revisionActualizada = $plato["revision_actualizada_en"] ?? "";

                        $textoBusqueda = implode(" ", [
                            $nombre,
                            $categoria,
                            $descripcion,
                            implode(" ", is_array($ingredientes) ? $ingredientes : []),
                            implode(" ", is_array($alergenosPlato) ? $alergenosPlato : []),
                            obtenerTextoFicha($plato)
                        ]);

                        $dataAlergenos = is_array($alergenosPlato)
                            ? minusculas(implode("|", $alergenosPlato))
                            : "";
                        ?>

                        <article
                            class="card plato-card js-plato"
                            data-text="<?= e(minusculas($textoBusqueda)) ?>"
                            data-categoria="<?= e(minusculas($categoria)) ?>"
                            data-alergenos="<?= e($dataAlergenos) ?>"
                            data-revision="<?= e(minusculas($estado)) ?>"
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
                                <div class="mini-title">Ingredientes</div>

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
                                        <span class="chip chip-ok">Sin alérgenos declarados</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <form method="post" action="#consulta" class="revision-form">
                                <input type="hidden" name="accion" value="actualizar_revision">
                                <input type="hidden" name="plato_id" value="<?= e((string)$id) ?>">

                                <div class="mini-title">Estado de revisión</div>

                                <div class="revision-actions">
                                    <button
                                        type="submit"
                                        name="nuevo_estado"
                                        value="pendiente"
                                        class="revision-btn <?= $estado === "pendiente" ? "is-selected" : "" ?>"
                                    >
                                        Pendiente
                                    </button>

                                    <button
                                        type="submit"
                                        name="nuevo_estado"
                                        value="revisado"
                                        class="revision-btn <?= $estado === "revisado" ? "is-selected" : "" ?>"
                                    >
                                        Revisado
                                    </button>

                                    <button
                                        type="submit"
                                        name="nuevo_estado"
                                        value="corregido"
                                        class="revision-btn <?= $estado === "corregido" ? "is-selected" : "" ?>"
                                    >
                                        Corregido
                                    </button>
                                </div>

                                <?php if ($revisionActualizada !== ""): ?>
                                    <p class="revision-date">
                                        Última actualización: <?= e($revisionActualizada) ?>
                                    </p>
                                <?php endif; ?>
                            </form>

                            <?php if (tieneFichaTecnica($plato)): ?>
                                <details>
                                    <summary>Ver ficha técnica</summary>

                                    <div class="details-body">
                                        <?php if (!empty($ficha["raciones"])): ?>
                                            <p><strong>Raciones:</strong> <?= e($ficha["raciones"]) ?></p>
                                        <?php endif; ?>

                                        <?php if (!empty($ficha["ingredientes"])): ?>
                                            <p><strong>Ingredientes de ficha:</strong> <?= e($ficha["ingredientes"]) ?></p>
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
                                    <strong>Origen:</strong> <?= e(implode(", ", $fuentes)) ?>
                                </p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section id="tabla-alergenos" class="section">
            <div class="section-title">
                <div>
                    <h2>Tabla rápida de alérgenos</h2>
                    <p>
                        Vista en matriz para revisar de forma clara qué alérgenos aparecen declarados en cada plato.
                        Es útil para control interno, revisión del responsable y preparación de información para sala.
                    </p>
                </div>

                <span class="visible-counter">
                    Mostrando <strong id="contadorMatriz"><?= e((string)$totalPlatos) ?></strong> filas
                </span>
            </div>

            <div class="card matrix-tools">
                <div class="field">
                    <label for="buscarMatriz">Buscar en la tabla</label>
                    <input type="search" id="buscarMatriz" placeholder="Ejemplo: croquetas, gluten, postres...">
                </div>

                <label class="matrix-check">
                    <input type="checkbox" id="soloConAlergenosMatriz">
                    <span>Mostrar solo platos con alérgenos declarados</span>
                </label>
            </div>

            <?php if (empty($platos) || empty($alergenos)): ?>
                <div class="card empty">
                    No hay datos suficientes para generar la tabla de alérgenos.
                </div>
            <?php else: ?>
                <div class="card allergen-matrix-card">
                    <div class="matrix-scroll">
                        <table class="allergen-table">
                            <thead>
                                <tr>
                                    <th>Plato</th>
                                    <th>Categoría</th>
                                    <?php foreach ($alergenos as $alergeno): ?>
                                        <th><?= e($alergeno) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($platos as $plato): ?>
                                    <?php
                                    $nombre = $plato["nombre"] ?? "";
                                    $categoria = $plato["categoria"] ?? "";
                                    $alergenosPlato = $plato["alergenos_declarados"] ?? [];

                                    $textoFila = implode(" ", [
                                        $nombre,
                                        $categoria,
                                        is_array($alergenosPlato) ? implode(" ", $alergenosPlato) : ""
                                    ]);

                                    $tieneAlergenos = is_array($alergenosPlato) && count($alergenosPlato) > 0;
                                    ?>

                                    <tr
                                        class="js-matriz-row"
                                        data-text="<?= e(minusculas($textoFila)) ?>"
                                        data-tiene-alergenos="<?= $tieneAlergenos ? "si" : "no" ?>"
                                    >
                                        <td class="matrix-plato">
                                            <strong><?= e($nombre) ?></strong>
                                        </td>

                                        <td>
                                            <span class="matrix-category"><?= e($categoria !== "" ? $categoria : "Sin categoría") ?></span>
                                        </td>

                                        <?php foreach ($alergenos as $alergeno): ?>
                                            <?php $presente = platoTieneAlergeno($plato, $alergeno); ?>

                                            <?php if ($presente): ?>
                                                <td class="cell-present" title="<?= e($nombre . " contiene " . $alergeno) ?>">
                                                    Sí
                                                </td>
                                            <?php else: ?>
                                                <td class="cell-empty" title="<?= e($nombre . " no tiene " . $alergeno . " declarado") ?>">
                                                    —
                                                </td>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="matrix-note">
                        <strong>Importante:</strong>
                        La ausencia de un alérgeno en esta tabla solo significa que no aparece declarado en los documentos procesados.
                        La información debe revisarse antes de comunicarla al cliente.
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section id="documentos" class="section">
            <div class="section-title">
                <div>
                    <h2>Documentos procesados</h2>
                    <p>
                        Resumen de los documentos que han alimentado la carta estructurada. Esta parte sirve para
                        comprobar de dónde viene la información sin mostrar detalles técnicos innecesarios.
                    </p>
                </div>
            </div>

            <?php if (empty($resultadosAnalisis)): ?>
                <div class="card empty">
                    No hay análisis documental disponible. Ejecuta el proceso integral.
                </div>
            <?php else: ?>
                <div class="docs-grid">
                    <?php foreach ($resultadosAnalisis as $resultado): ?>
                        <?php
                        $ia = $resultado["analisis_ia"] ?? [];
                        $tipo = $ia["tipo_documento"] ?? "otro";
                        $nivel = $ia["nivel_revision_recomendado"] ?? "medio";
                        $platosIa = $ia["platos_mencionados"] ?? [];
                        $alergenosIa = $ia["alergenos_mencionados"] ?? [];
                        $advertenciasIa = $ia["advertencias"] ?? [];
                        ?>

                        <article class="card doc-card">
                            <div class="plato-meta doc-meta">
                                <span class="badge badge-categoria"><?= e($tipo) ?></span>
                                <span class="badge <?= e(claseNivelRevision($nivel)) ?>">Revisión <?= e($nivel) ?></span>
                            </div>

                            <h3><?= e($resultado["archivo"] ?? "Documento") ?></h3>

                            <?php if (!empty($ia["resumen_utilidad"])): ?>
                                <p><?= e($ia["resumen_utilidad"]) ?></p>
                            <?php endif; ?>

                            <?php if (is_array($platosIa) && count($platosIa) > 0): ?>
                                <div class="doc-block">
                                    <div class="mini-title">Platos localizados</div>

                                    <div class="chip-group">
                                        <?php foreach (array_slice($platosIa, 0, 8) as $platoIa): ?>
                                            <span class="chip"><?= e($platoIa) ?></span>
                                        <?php endforeach; ?>

                                        <?php if (count($platosIa) > 8): ?>
                                            <span class="chip">+<?= e((string)(count($platosIa) - 8)) ?> más</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (is_array($alergenosIa) && count($alergenosIa) > 0): ?>
                                <div class="doc-block">
                                    <div class="mini-title">Alérgenos detectados</div>

                                    <div class="chip-group">
                                        <?php foreach ($alergenosIa as $alergenoIa): ?>
                                            <span class="chip chip-alergeno"><?= e($alergenoIa) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($ia["uso_en_kitcherry"])): ?>
                                <div class="doc-block">
                                    <div class="mini-title">Uso dentro de Kitcherry</div>
                                    <p><?= e($ia["uso_en_kitcherry"]) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (is_array($advertenciasIa) && count($advertenciasIa) > 0): ?>
                                <details class="doc-warning">
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

        <?php if (!empty($resumenes)): ?>
            <section class="section">
                <div class="section-title">
                    <div>
                        <h2>Resumen interno</h2>
                        <p>
                            Resumen breve de los documentos procesados. Esta información puede ayudar al responsable
                            del establecimiento a revisar rápidamente el contenido original.
                        </p>
                    </div>
                </div>

                <div class="docs-grid">
                    <?php foreach ($resumenes as $resumen): ?>
                        <article class="card doc-card">
                            <h3><?= e(nombreDocumentoAmigable($resumen["archivo"])) ?></h3>
                            <p><?= e($resumen["contenido"]) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section id="tecnico" class="section">
            <details class="card technical-box">
                <summary>Detalle técnico del procesamiento</summary>

                <div class="details-body">
                    <p>
                        Este apartado queda oculto por defecto porque está pensado para revisión técnica o presentación
                        del desarrollo. El usuario hostelero normalmente solo necesita la consulta de carta, ingredientes
                        y alérgenos.
                    </p>

                    <p>
                        <strong>Estado del proceso:</strong> <?= e($estadoProceso) ?>
                    </p>
                </div>

                <?php if (!empty($pasosProceso)): ?>
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
            </details>
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

    const botonesAlergenos = Array.from(document.querySelectorAll(".js-allergy-chip"));
    const modoAlergenos = document.getElementById("modoAlergenos");
    const limpiarAlergenos = document.getElementById("limpiarAlergenos");
    const consultaAlergenosTexto = document.getElementById("consultaAlergenosTexto");

    const buscarMatriz = document.getElementById("buscarMatriz");
    const soloConAlergenosMatriz = document.getElementById("soloConAlergenosMatriz");
    const filasMatriz = Array.from(document.querySelectorAll(".js-matriz-row"));
    const contadorMatriz = document.getElementById("contadorMatriz");

    let alergenosSeleccionados = [];

    function normalizar(texto) {
        return (texto || "")
            .toString()
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .trim();
    }

    function obtenerAlergenosSeleccionadosTexto() {
        return alergenosSeleccionados
            .map(function (alergeno) {
                return alergeno.charAt(0).toUpperCase() + alergeno.slice(1);
            })
            .join(", ");
    }

    function actualizarTextoConsultaAlergenos(visibles) {
        if (alergenosSeleccionados.length === 0) {
            consultaAlergenosTexto.textContent = "Selecciona uno o varios alérgenos para iniciar la consulta.";
            return;
        }

        const textoAlergenos = obtenerAlergenosSeleccionadosTexto();

        if (modoAlergenos.value === "aptos") {
            consultaAlergenosTexto.textContent =
                "Mostrando " + visibles + " platos aparentemente aptos para evitar: " + textoAlergenos + ".";
        } else {
            consultaAlergenosTexto.textContent =
                "Mostrando " + visibles + " platos que contienen alguno de estos alérgenos: " + textoAlergenos + ".";
        }
    }

    function coincideModoConsultaAlergenos(alergenosPlato) {
        if (alergenosSeleccionados.length === 0) {
            return true;
        }

        const contieneAlguno = alergenosSeleccionados.some(function (alergenoSeleccionado) {
            return alergenosPlato.includes(alergenoSeleccionado);
        });

        if (modoAlergenos.value === "aptos") {
            return !contieneAlguno;
        }

        return contieneAlguno;
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
            const coincideAlergenoSelect = alergenoSeleccionado === "" || alergenosPlato.includes(alergenoSeleccionado);
            const coincideRevision = revisionSeleccionada === "" || revisionPlato === revisionSeleccionada;
            const coincideConsultaAlergenos = coincideModoConsultaAlergenos(alergenosPlato);

            const visible =
                coincideTexto &&
                coincideCategoria &&
                coincideAlergenoSelect &&
                coincideRevision &&
                coincideConsultaAlergenos;

            plato.style.display = visible ? "" : "none";

            if (visible) {
                visibles++;
            }
        });

        contadorVisibles.textContent = visibles;
        actualizarTextoConsultaAlergenos(visibles);
    }

    function filtrarMatriz() {
        if (!buscarMatriz || !soloConAlergenosMatriz || !contadorMatriz) {
            return;
        }

        const textoBuscar = normalizar(buscarMatriz.value);
        const soloConAlergenos = soloConAlergenosMatriz.checked;

        let visibles = 0;

        filasMatriz.forEach(function (fila) {
            const textoFila = normalizar(fila.dataset.text);
            const tieneAlergenos = fila.dataset.tieneAlergenos === "si";

            const coincideTexto = textoBuscar === "" || textoFila.includes(textoBuscar);
            const coincideAlergenos = !soloConAlergenos || tieneAlergenos;

            const visible = coincideTexto && coincideAlergenos;

            fila.style.display = visible ? "" : "none";

            if (visible) {
                visibles++;
            }
        });

        contadorMatriz.textContent = visibles;
    }

    botonesAlergenos.forEach(function (boton) {
        boton.addEventListener("click", function () {
            const alergeno = normalizar(boton.dataset.alergeno);

            if (alergenosSeleccionados.includes(alergeno)) {
                alergenosSeleccionados = alergenosSeleccionados.filter(function (item) {
                    return item !== alergeno;
                });

                boton.classList.remove("is-active");
            } else {
                alergenosSeleccionados.push(alergeno);
                boton.classList.add("is-active");
            }

            filtrarPlatos();
        });
    });

    limpiarAlergenos.addEventListener("click", function () {
        alergenosSeleccionados = [];

        botonesAlergenos.forEach(function (boton) {
            boton.classList.remove("is-active");
        });

        filtrarPlatos();
    });

    buscar.addEventListener("input", filtrarPlatos);
    categoria.addEventListener("change", filtrarPlatos);
    alergeno.addEventListener("change", filtrarPlatos);
    revision.addEventListener("change", filtrarPlatos);
    modoAlergenos.addEventListener("change", filtrarPlatos);

    if (buscarMatriz) {
        buscarMatriz.addEventListener("input", filtrarMatriz);
    }

    if (soloConAlergenosMatriz) {
        soloConAlergenosMatriz.addEventListener("change", filtrarMatriz);
    }
</script>

</body>
</html>