<?php

// ==========================================================
// CARGA DE DATOS
// ==========================================================

$carta = cargarJson($cartaPath);
$analisis = cargarJson($analisisPath);
$proceso = cargarJson($procesoPath);
$resumenes = cargarResumenes($summariesDir);

$revisionesData = cargarJson($revisionesPath, crearEstructuraRevisiones());
$revisionesData = normalizarEstructuraRevisiones($revisionesData);

$revisionesAntesMigracion = $revisionesData;
$revisionesData = migrarRevisionesAntiguasDesdeCarta($carta, $revisionesData);

if ($revisionesData !== $revisionesAntesMigracion) {
    guardarJson($revisionesPath, $revisionesData);
}

$mensajeSistema = procesarActualizacionRevisionSeparada($revisionesPath, $carta, $revisionesData);

$platosOriginales = $carta["platos"] ?? [];
$platos = is_array($platosOriginales) ? aplicarRevisionesAPlatos($platosOriginales, $revisionesData) : [];

$negocio = $carta["negocio"] ?? "Restaurante";
$totalPlatos = count($platos);

$categorias = obtenerCategorias($platos);
$alergenos = obtenerAlergenos($platos);

$totalConAlergenos = 0;
$totalSinAlergenos = 0;
$totalConFicha = 0;
$totalPendientes = 0;
$totalRevisados = 0;
$totalCorregidos = 0;
$totalConAdvertencias = 0;
$totalConTrazas = 0;

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

    $advertenciasPlato = obtenerAdvertenciasAlimentarias($plato);

    if (!empty($advertenciasPlato)) {
        $totalConAdvertencias++;
    }

    foreach ($advertenciasPlato as $advertenciaPlato) {
        if (($advertenciaPlato["tipo"] ?? "") === "trazas") {
            $totalConTrazas++;
            break;
        }
    }
}

$resultadosAnalisis = $analisis["resultados"] ?? [];
$estadoProceso = $proceso["estado_general"] ?? "sin ejecutar";
$pasosProceso = $proceso["pasos"] ?? [];

$logoExiste = file_exists($logoAbsPath);
$fechaInforme = date("d/m/Y H:i");
$totalCategorias = count($categorias);
$platosSensibles = obtenerPlatosSensibles($platos);

$totalRevisionesGuardadas = count($revisionesData["revisiones"] ?? []);
$ultimaActualizacionRevisiones = $revisionesData["ultima_actualizacion"] ?? "";

