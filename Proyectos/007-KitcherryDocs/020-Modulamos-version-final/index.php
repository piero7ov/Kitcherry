<?php
// ==========================================================
// KITCHERRY DOCS - VERSIÓN FINAL
// Archivo: index.php
// Objetivo:
// - Mostrar la carta estructurada generada por procesar_kitcherry_docs.py
// - Consultar platos, ingredientes, alérgenos y advertencias alimentarias
// - Separar vista cliente y vista interna
// - Guardar revisiones manuales en out/revisiones_platos.json
// - Dejar el JavaScript separado en assets/js/informe.js
// ==========================================================

declare(strict_types=1);

// ==========================================================
// RUTAS
// ==========================================================

$cartaPath = __DIR__ . "/out/carta_kitcherry.json";
$analisisPath = __DIR__ . "/out/analisis_ollama_carta.json";
$procesoPath = __DIR__ . "/out/proceso_integral_kitcherry.json";
$revisionesPath = __DIR__ . "/out/revisiones_platos.json";
$summariesDir = __DIR__ . "/summaries";

$logoWebPath = "assets/img/logo.png";
$logoAbsPath = __DIR__ . "/assets/img/logo.png";

require_once __DIR__ . '/includes/functions.php';

require_once __DIR__ . '/includes/data.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry Docs | Carta y alérgenos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Estilos principales -->
    <link rel="stylesheet" href="assets/css/styles.css">

    <!-- JavaScript externo -->
    <script src="assets/js/informe.js" defer></script>
</head>

<body class="view-mode-internal">
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
                <a href="#modo-vista">Modo de vista</a>
                <a href="#consulta-alergenos">Consulta alérgenos</a>
                <a href="#consulta">Carta</a>
                <a href="#tabla-alergenos">Tabla alérgenos</a>
                <a href="#advertencias">Advertencias</a>
                <a href="#impresion">Imprimir</a>
                <a href="#documentos" class="internal-only">Documentos</a>
                <a href="#tecnico" class="internal-only">Detalle técnico</a>
            </nav>
        </div>
    </header>

    <main class="container">

        <div class="print-header">
            <strong>KITCHERRY DOCS</strong>
            <span><?= e($negocio) ?> · <?= e($fechaInforme) ?></span>
            <p>Documento generado para consulta de carta, platos, ingredientes, alérgenos y advertencias alimentarias.</p>
        </div>

        <section class="hero">
            <div class="hero-main">
                <span class="eyebrow">Carta, platos y alérgenos</span>

                <h1>Panel de consulta alimentaria</h1>

                <p class="internal-only">
                    Consulta de forma rápida la información de platos, ingredientes, alérgenos, trazas, advertencias,
                    fichas técnicas, revisión interna y documentos procesados.
                </p>

                <p class="client-only">
                    Consulta de forma sencilla los platos, ingredientes principales, alérgenos declarados
                    y advertencias alimentarias del establecimiento.
                </p>

                <div class="hero-actions">
                    <a href="#consulta-alergenos" class="btn btn-white">Consultar por alergia</a>
                    <a href="#advertencias" class="btn btn-soft">Ver advertencias</a>
                </div>
            </div>

            <aside class="hero-side">
                <div class="quick-status">
                    <h2><?= e($negocio) ?></h2>

                    <p class="internal-only">
                        La carta está estructurada y lista para revisión interna. Las revisiones manuales se guardan
                        aparte para no perderse al regenerar el proceso.
                    </p>

                    <p class="client-only">
                        Información de carta preparada para consulta clara de platos, ingredientes, alérgenos declarados
                        y avisos alimentarios.
                    </p>

                    <span class="status-pill internal-only"><?= e((string)$totalPendientes) ?> platos pendientes de revisión</span>
                    <span class="status-pill client-only"><?= e((string)$totalPlatos) ?> platos disponibles para consulta</span>
                </div>

                <div class="alert-box">
                    <strong>Revisión necesaria</strong>
                    La información sobre alérgenos, trazas y contaminación cruzada debe ser verificada por una persona
                    responsable antes de publicarse o comunicarse al cliente.
                </div>
            </aside>
        </section>

        <?php if ($mensajeSistema["mensaje"] !== ""): ?>
            <div class="system-message <?= e($mensajeSistema["tipo"] === "ok" ? "system-ok" : "system-error") ?> internal-only">
                <?= e($mensajeSistema["mensaje"]) ?>
            </div>
        <?php endif; ?>

        <section id="modo-vista" class="section no-print">
            <div class="card view-mode-card">
                <div class="view-mode-text">
                    <span class="eyebrow-dark">Modo de visualización</span>
                    <h2>Vista cliente / vista interna</h2>
                    <p id="textoModoVista">
                        Estás usando la vista interna, pensada para revisar estados, documentos, fuentes y detalles técnicos.
                    </p>
                </div>

                <div class="view-mode-actions">
                    <button type="button" class="view-mode-btn" data-view-mode="client">
                        Vista cliente
                    </button>

                    <button type="button" class="view-mode-btn is-active" data-view-mode="internal">
                        Vista interna
                    </button>
                </div>
            </div>

            <div class="card client-mode-banner client-only">
                <strong>Vista cliente activa</strong>
                <p>
                    En esta vista se ocultan estados internos, fuentes técnicas, formularios de revisión,
                    documentos procesados y detalles de desarrollo. Se mantiene la información útil para consulta externa.
                </p>
            </div>
        </section>

        <section class="stats stats-internal">
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

        <section class="stats stats-client client-only">
            <article class="stat-card">
                <span>Platos en carta</span>
                <strong><?= e((string)$totalPlatos) ?></strong>
            </article>

            <article class="stat-card">
                <span>Categorías</span>
                <strong><?= e((string)$totalCategorias) ?></strong>
            </article>

            <article class="stat-card">
                <span>Con alérgenos declarados</span>
                <strong><?= e((string)$totalConAlergenos) ?></strong>
            </article>

            <article class="stat-card">
                <span>Con avisos alimentarios</span>
                <strong><?= e((string)$totalConAdvertencias) ?></strong>
            </article>
        </section>

        <section class="stats secondary-stats internal-only">
            <article class="stat-card">
                <span>Con alérgenos</span>
                <strong><?= e((string)$totalConAlergenos) ?></strong>
            </article>

            <article class="stat-card">
                <span>Sin alérgenos declarados</span>
                <strong><?= e((string)$totalSinAlergenos) ?></strong>
            </article>

            <article class="stat-card">
                <span>Con posibles trazas</span>
                <strong><?= e((string)$totalConTrazas) ?></strong>
            </article>

            <article class="stat-card">
                <span>Revisiones guardadas</span>
                <strong><?= e((string)$totalRevisionesGuardadas) ?></strong>
            </article>
        </section>

        <section id="consulta-alergenos" class="section">
            <div class="section-title">
                <div>
                    <h2>Consulta por alérgenos</h2>
                    <p class="internal-only">
                        Selecciona uno o varios alérgenos para ayudar al personal de sala a localizar platos
                        que los contienen o platos aparentemente aptos.
                    </p>

                    <p class="client-only">
                        Selecciona uno o varios alérgenos para consultar la información declarada en la carta.
                    </p>
                </div>
            </div>

            <div class="card allergy-consult">
                <div class="consult-header">
                    <div>
                        <h3>¿Qué necesita saber el cliente?</h3>

                        <p class="internal-only">
                            Esta herramienta es de apoyo interno. Antes de confirmar la información al cliente,
                            debe revisarse con el responsable del establecimiento.
                        </p>

                        <p class="client-only">
                            Esta consulta muestra información declarada por el establecimiento. En caso de alergia grave,
                            consulta siempre con el personal responsable.
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

        <section id="consulta" class="section print-section print-carta">
            <div class="section-title">
                <div>
                    <h2>Carta y platos</h2>

                    <p class="internal-only">
                        Busca un plato o filtra por categoría, alérgeno o estado de revisión. Las revisiones se guardan
                        en un archivo independiente para mantenerlas aunque se regenere la carta.
                    </p>

                    <p class="client-only">
                        Busca un plato o filtra por categoría y alérgeno para consultar la información disponible.
                    </p>
                </div>

                <span class="visible-counter">
                    Mostrando <strong id="contadorVisibles"><?= e((string)$totalPlatos) ?></strong> platos
                </span>
            </div>

            <div class="card filters">
                <div class="field">
                    <label for="buscar">Buscar plato, ingrediente, alérgeno o advertencia</label>
                    <input type="search" id="buscar" placeholder="Ejemplo: queso, gluten, trazas, tarta...">
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

                <div class="field internal-only">
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
                        $revisionOrigen = $plato["revision_origen"] ?? "";
                        $advertenciasPlato = obtenerAdvertenciasAlimentarias($plato);

                        $hayAdvertencias = !empty($advertenciasPlato);
                        $hayAdvertenciasCliente = tieneAdvertenciasCliente($advertenciasPlato);
                        $soloAdvertenciasInternas = $hayAdvertencias && !$hayAdvertenciasCliente;

                        $textoAdvertencias = textoAdvertenciasParaImpresion($advertenciasPlato, true);

                        $textoBusqueda = implode(" ", [
                            $nombre,
                            $categoria,
                            $descripcion,
                            implode(" ", is_array($ingredientes) ? $ingredientes : []),
                            implode(" ", is_array($alergenosPlato) ? $alergenosPlato : []),
                            obtenerTextoFicha($plato),
                            $textoAdvertencias
                        ]);

                        $dataAlergenos = is_array($alergenosPlato)
                            ? minusculas(implode("|", $alergenosPlato))
                            : "";
                        ?>

                        <article
                            class="card plato-card js-plato <?= $hayAdvertencias ? "plato-card-warning" : "" ?>"
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
                                        <span class="badge <?= e(claseRevision($estado)) ?> badge-revision internal-only"><?= e($estado) ?></span>

                                        <?php if ($hayAdvertenciasCliente): ?>
                                            <span class="badge badge-food-warning">
                                                Aviso alimentario
                                            </span>
                                        <?php elseif ($soloAdvertenciasInternas): ?>
                                            <span class="badge badge-food-warning internal-only">
                                                Revisión interna
                                            </span>
                                        <?php endif; ?>
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

                            <?php if ($hayAdvertencias): ?>
                                <div class="food-warning-summary <?= $soloAdvertenciasInternas ? "internal-only" : "" ?>">
                                    <div>
                                        <strong class="client-only">Este plato tiene avisos alimentarios.</strong>
                                        <strong class="internal-only">Este plato tiene <?= e((string)count($advertenciasPlato)) ?> aviso(s) de revisión.</strong>

                                        <p class="client-only">
                                            Revisa el detalle en la sección de advertencias antes de confirmar información sensible.
                                        </p>

                                        <p class="internal-only">
                                            El detalle completo se muestra en la sección de advertencias alimentarias para evitar duplicar información en la carta.
                                        </p>
                                    </div>

                                    <a href="#advertencias" class="food-warning-link">
                                        Ver detalle
                                    </a>
                                </div>
                            <?php endif; ?>

                            <form method="post" action="#consulta" class="revision-form internal-only">
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
                                        <?php if ($revisionOrigen !== ""): ?>
                                            · Guardado en revisiones separadas
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </form>

                            <?php if (tieneFichaTecnica($plato)): ?>
                                <details class="internal-only">
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
                                <p class="source-list internal-only">
                                    <strong>Origen:</strong> <?= e(implode(", ", $fuentes)) ?>
                                </p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section id="tabla-alergenos" class="section print-section print-matriz">
            <div class="section-title">
                <div>
                    <h2>Tabla rápida de alérgenos</h2>

                    <p class="internal-only">
                        Vista en matriz para revisar de forma clara qué alérgenos aparecen declarados en cada plato.
                        Es útil para control interno, revisión del responsable y preparación de información para sala.
                    </p>

                    <p class="client-only">
                        Vista rápida para consultar qué alérgenos aparecen declarados en cada plato.
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
                                    <th>Advertencias</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($platos as $plato): ?>
                                    <?php
                                    $nombre = $plato["nombre"] ?? "";
                                    $categoria = $plato["categoria"] ?? "";
                                    $alergenosPlato = $plato["alergenos_declarados"] ?? [];
                                    $advertenciasPlato = obtenerAdvertenciasAlimentarias($plato);

                                    $textoFila = implode(" ", [
                                        $nombre,
                                        $categoria,
                                        is_array($alergenosPlato) ? implode(" ", $alergenosPlato) : "",
                                        textoAdvertenciasParaImpresion($advertenciasPlato, true)
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

                                        <td class="<?= !empty($advertenciasPlato) ? "cell-warning" : "cell-empty" ?>">
                                            <?= !empty($advertenciasPlato) ? "Aviso" : "—" ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="matrix-note">
                        <strong>Importante:</strong>
                        La ausencia de un alérgeno en esta tabla solo significa que no aparece declarado en los documentos procesados.
                        Las trazas, la manipulación y la contaminación cruzada deben revisarse antes de comunicar la información al cliente.
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section id="advertencias" class="section print-section print-advertencias">
            <div class="section-title">
                <div>
                    <h2>Advertencias alimentarias</h2>

                    <p class="internal-only">
                        Resumen de platos que requieren especial atención por posibles trazas, ausencia de alérgenos declarados,
                        varios alérgenos o estado pendiente de revisión.
                    </p>

                    <p class="client-only">
                        Resumen de avisos alimentarios destacados. Ante una alergia o intolerancia, consulta siempre con el personal responsable.
                    </p>
                </div>
            </div>

            <?php if (empty($platosSensibles)): ?>
                <div class="card empty">
                    No se han detectado advertencias alimentarias destacadas.
                </div>
            <?php else: ?>
                <div class="sensitive-grid">
                    <?php foreach ($platosSensibles as $itemSensible): ?>
                        <?php
                        $platoSensible = $itemSensible["plato"];
                        $advertenciasSensibles = $itemSensible["advertencias"];

                        $hayClienteSensible = tieneAdvertenciasCliente($advertenciasSensibles);
                        $claseTarjetaSensible = $hayClienteSensible ? "" : "internal-only";

                        $nombreSensible = $platoSensible["nombre"] ?? "";
                        $categoriaSensible = $platoSensible["categoria"] ?? "";
                        $alergenosSensible = $platoSensible["alergenos_declarados"] ?? [];
                        ?>

                        <article class="card sensitive-card <?= e($claseTarjetaSensible) ?>">
                            <div class="sensitive-header">
                                <div>
                                    <span class="badge badge-categoria"><?= e($categoriaSensible !== "" ? $categoriaSensible : "Sin categoría") ?></span>
                                    <h3><?= e($nombreSensible) ?></h3>
                                </div>

                                <span class="sensitive-count">
                                    <?= e((string)count($advertenciasSensibles)) ?> avisos
                                </span>
                            </div>

                            <div class="chip-group">
                                <?php if (is_array($alergenosSensible) && count($alergenosSensible) > 0): ?>
                                    <?php foreach ($alergenosSensible as $alergenoSensible): ?>
                                        <span class="chip chip-alergeno"><?= e($alergenoSensible) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="chip chip-ok">Sin alérgenos declarados</span>
                                <?php endif; ?>
                            </div>

                            <div class="food-warning-list">
                                <?php foreach ($advertenciasSensibles as $advertenciaSensible): ?>
                                    <?php
                                    $visibilidadSensible = $advertenciaSensible["visibilidad"] ?? "cliente_interna";
                                    $claseVisibilidadSensible = $visibilidadSensible === "interna" ? "internal-only" : "";
                                    ?>

                                    <article class="food-warning-item <?= e(claseAdvertenciaAlimentaria((string)($advertenciaSensible["tipo"] ?? ""))) ?> <?= e($claseVisibilidadSensible) ?>">
                                        <strong><?= e($advertenciaSensible["titulo"] ?? "Advertencia") ?></strong>
                                        <p><?= e($advertenciaSensible["texto"] ?? "") ?></p>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="print-only print-matriz-compacta">
            <div class="section-title">
                <div>
                    <h2>Tabla de alérgenos para impresión</h2>
                    <p>
                        Vista compacta preparada para imprimir o guardar como PDF sin cortes de columnas.
                    </p>
                </div>
            </div>

            <table class="print-allergen-summary">
                <thead>
                    <tr>
                        <th>Plato</th>
                        <th>Categoría</th>
                        <th>Alérgenos declarados</th>
                        <th class="internal-only">Estado</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($platos as $plato): ?>
                        <?php
                        $nombre = $plato["nombre"] ?? "";
                        $categoria = $plato["categoria"] ?? "";
                        $estado = $plato["estado_revision"] ?? "pendiente";
                        $alergenosPlato = $plato["alergenos_declarados"] ?? [];

                        $textoAlergenos = "Sin alérgenos declarados";

                        if (is_array($alergenosPlato) && count($alergenosPlato) > 0) {
                            $textoAlergenos = implode(", ", $alergenosPlato);
                        }
                        ?>

                        <tr>
                            <td><strong><?= e($nombre) ?></strong></td>
                            <td><?= e($categoria !== "" ? $categoria : "Sin categoría") ?></td>
                            <td><?= e($textoAlergenos) ?></td>
                            <td class="internal-only"><?= e(textoEstadoRevision($estado)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="print-warning-box">
                <strong>Importante:</strong>
                La ausencia de un alérgeno solo significa que no aparece declarado en los documentos procesados.
                La información debe ser revisada por una persona responsable antes de comunicarse al cliente.
            </div>
        </section>

        <section id="impresion" class="section no-print">
            <div class="section-title">
                <div>
                    <h2>Imprimir o guardar PDF</h2>
                    <p class="internal-only">
                        Prepara documentos útiles para sala, cocina o revisión interna. Puedes imprimirlos directamente
                        o guardarlos como PDF desde la ventana de impresión del navegador.
                    </p>

                    <p class="client-only">
                        Prepara una versión sencilla para consultar o guardar la carta y los alérgenos declarados.
                    </p>
                </div>
            </div>

            <div class="print-actions-grid">
                <article class="card print-card">
                    <h3>Carta para sala</h3>
                    <p>
                        Genera una vista imprimible con platos, precios, ingredientes, alérgenos declarados y avisos destacados.
                    </p>
                    <button type="button" class="print-btn" data-print-mode="carta">
                        Imprimir carta
                    </button>
                </article>

                <article class="card print-card">
                    <h3>Tabla de alérgenos</h3>
                    <p>
                        Genera una vista compacta de alérgenos por plato, útil para consulta rápida.
                    </p>
                    <button type="button" class="print-btn" data-print-mode="matriz">
                        Imprimir tabla
                    </button>
                </article>

                <article class="card print-card">
                    <h3>Advertencias</h3>
                    <p>
                        Genera una vista con platos sensibles, trazas y avisos alimentarios destacados.
                    </p>
                    <button type="button" class="print-btn" data-print-mode="advertencias">
                        Imprimir advertencias
                    </button>
                </article>

                <article class="card print-card internal-only">
                    <h3>Informe interno</h3>
                    <p>
                        Genera una vista con documentos procesados, advertencias, revisión y resúmenes internos.
                    </p>
                    <button type="button" class="print-btn" data-print-mode="interno">
                        Imprimir informe
                    </button>
                </article>
            </div>
        </section>

        <section id="documentos" class="section print-section print-interno internal-only">
            <div class="section-title">
                <div>
                    <h2>Origen de la información</h2>
                    <p>
                        Documentos utilizados para generar la carta estructurada. Esta vista resume de forma clara
                        qué aporta cada documento sin mostrar nombres técnicos innecesarios.
                    </p>
                </div>
            </div>

            <?php if (empty($resultadosAnalisis)): ?>
                <div class="card empty">
                    No hay análisis documental disponible. Ejecuta el proceso integral.
                </div>
            <?php else: ?>
                <div class="docs-friendly-grid">
                    <?php foreach ($resultadosAnalisis as $resultado): ?>
                        <?php
                        $ia = $resultado["analisis_ia"] ?? [];
                        $archivoTecnico = (string)($resultado["archivo"] ?? "Documento");
                        $tipo = (string)($ia["tipo_documento"] ?? "otro");
                        $nivel = (string)($ia["nivel_revision_recomendado"] ?? "medio");

                        $documentoAmigable = obtenerDocumentoAmigable($archivoTecnico, $tipo);

                        $platosIa = $ia["platos_mencionados"] ?? [];
                        $alergenosIa = $ia["alergenos_mencionados"] ?? [];
                        $ingredientesIa = $ia["ingredientes_relevantes"] ?? [];
                        $advertenciasIa = $ia["advertencias"] ?? [];

                        if (!is_array($platosIa)) {
                            $platosIa = [];
                        }

                        if (!is_array($alergenosIa)) {
                            $alergenosIa = [];
                        }

                        if (!is_array($ingredientesIa)) {
                            $ingredientesIa = [];
                        }

                        if (!is_array($advertenciasIa)) {
                            $advertenciasIa = [];
                        }
                        ?>

                        <article class="card doc-friendly-card">
                            <div class="doc-friendly-header">
                                <div class="doc-icon">
                                    <?= e($documentoAmigable["icono"]) ?>
                                </div>

                                <div>
                                    <span class="doc-type"><?= e($documentoAmigable["tipo"]) ?></span>
                                    <h3><?= e($documentoAmigable["titulo"]) ?></h3>
                                </div>
                            </div>

                            <p class="doc-friendly-description">
                                <?= e($documentoAmigable["descripcion"]) ?>
                            </p>

                            <div class="doc-friendly-status">
                                <span class="badge <?= e(claseNivelRevision($nivel)) ?>">
                                    <?= e(textoNivelRevision($nivel)) ?>
                                </span>

                                <p>
                                    <?= e(descripcionNivelRevision($nivel)) ?>
                                </p>
                            </div>

                            <div class="doc-friendly-block">
                                <div class="mini-title">Uso dentro de Kitcherry Docs</div>
                                <p>
                                    <?php if (!empty($ia["uso_en_kitcherry"])): ?>
                                        <?= e($ia["uso_en_kitcherry"]) ?>
                                    <?php else: ?>
                                        <?= e($documentoAmigable["uso"]) ?>
                                    <?php endif; ?>
                                </p>
                            </div>

                            <?php if (!empty($ia["resumen_utilidad"])): ?>
                                <div class="doc-friendly-block">
                                    <div class="mini-title">Resumen del contenido</div>
                                    <p><?= e($ia["resumen_utilidad"]) ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="doc-mini-stats">
                                <div>
                                    <strong><?= e((string)count($platosIa)) ?></strong>
                                    <span>platos detectados</span>
                                </div>

                                <div>
                                    <strong><?= e((string)count($alergenosIa)) ?></strong>
                                    <span>alérgenos detectados</span>
                                </div>

                                <div>
                                    <strong><?= e((string)count($ingredientesIa)) ?></strong>
                                    <span>ingredientes</span>
                                </div>
                            </div>

                            <?php if (count($platosIa) > 0): ?>
                                <div class="doc-friendly-block">
                                    <div class="mini-title">Platos localizados</div>

                                    <div class="chip-group">
                                        <?php foreach (limitarLista($platosIa, 8) as $platoIa): ?>
                                            <span class="chip"><?= e($platoIa) ?></span>
                                        <?php endforeach; ?>

                                        <?php if (count($platosIa) > 8): ?>
                                            <span class="chip">+<?= e((string)(count($platosIa) - 8)) ?> más</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (count($alergenosIa) > 0): ?>
                                <div class="doc-friendly-block">
                                    <div class="mini-title">Alérgenos mencionados</div>

                                    <div class="chip-group">
                                        <?php foreach ($alergenosIa as $alergenoIa): ?>
                                            <span class="chip chip-alergeno"><?= e($alergenoIa) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (count($advertenciasIa) > 0): ?>
                                <details class="doc-warning doc-friendly-warning" open>
                                    <summary>Advertencias de revisión</summary>

                                    <div class="details-body">
                                        <?php foreach ($advertenciasIa as $advertencia): ?>
                                            <p><?= e($advertencia) ?></p>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            <?php endif; ?>

                            <details class="doc-technical-name">
                                <summary>Ver nombre técnico</summary>

                                <div class="details-body">
                                    <p>
                                        <strong>Archivo:</strong> <?= e($archivoTecnico) ?>
                                    </p>

                                    <p>
                                        <strong>Tipo detectado:</strong> <?= e($tipo) ?>
                                    </p>
                                </div>
                            </details>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if (!empty($resumenes)): ?>
            <section class="section print-section print-interno print-interno-extra internal-only">
                <div class="section-title">
                    <div>
                        <h2>Resumen interno de documentos</h2>
                        <p>
                            Resumen breve generado a partir de los documentos procesados. Sirve como apoyo para que el
                            responsable revise rápidamente la información original.
                        </p>
                    </div>
                </div>

                <div class="docs-friendly-grid">
                    <?php foreach ($resumenes as $resumen): ?>
                        <?php
                        $documentoResumen = obtenerDocumentoAmigable($resumen["archivo"]);
                        ?>

                        <article class="card doc-friendly-card summary-friendly-card">
                            <div class="doc-friendly-header">
                                <div class="doc-icon">
                                    <?= e($documentoResumen["icono"]) ?>
                                </div>

                                <div>
                                    <span class="doc-type">Resumen</span>
                                    <h3><?= e($documentoResumen["titulo"]) ?></h3>
                                </div>
                            </div>

                            <p class="doc-friendly-description">
                                <?= e($resumen["contenido"]) ?>
                            </p>

                            <details class="doc-technical-name">
                                <summary>Ver archivo original</summary>

                                <div class="details-body">
                                    <p>
                                        <strong>Archivo:</strong> <?= e($resumen["archivo"]) ?>
                                    </p>
                                </div>
                            </details>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section id="tecnico" class="section internal-only">
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

                    <p>
                        <strong>Archivo de carta generado automáticamente:</strong> out/carta_kitcherry.json
                    </p>

                    <p>
                        <strong>Archivo de revisiones manuales:</strong> out/revisiones_platos.json
                    </p>

                    <p>
                        <strong>Revisiones guardadas:</strong> <?= e((string)$totalRevisionesGuardadas) ?>
                    </p>

                    <?php if ($ultimaActualizacionRevisiones !== ""): ?>
                        <p>
                            <strong>Última actualización de revisiones:</strong> <?= e($ultimaActualizacionRevisiones) ?>
                        </p>
                    <?php endif; ?>
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

</body>
</html>