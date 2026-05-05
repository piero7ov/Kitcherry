<?php
// ==========================================================
// KITCHERRY DOCS
// Archivo: index.php
// Objetivo:
// - Consultar carta, platos, alérgenos y advertencias
// - Separar revisiones manuales del JSON principal
// - Mantener vista cliente e interna
// - Preparar documentos imprimibles
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

$logoWebPath = "logo/logo.png";
$logoAbsPath = __DIR__ . "/logo/logo.png";

// ==========================================================
// FUNCIONES BASE
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

    return is_array($datos) ? $datos : $defecto;
}

function guardarJson(string $ruta, array $datos): bool
{
    $carpeta = dirname($ruta);

    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0777, true);
    }

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
        if (!str_ends_with($archivo, ".summary.txt")) {
            continue;
        }

        $ruta = $carpeta . "/" . $archivo;
        $contenido = file_get_contents($ruta);

        if ($contenido !== false && trim($contenido) !== "") {
            $resumenes[] = [
                "archivo" => $archivo,
                "contenido" => trim($contenido)
            ];
        }
    }

    return $resumenes;
}

function minusculas(string $texto): string
{
    return mb_strtolower($texto, "UTF-8");
}

function normalizarTextoServidor(string $texto): string
{
    $texto = minusculas($texto);

    return str_replace(
        ["á", "é", "í", "ó", "ú", "ü", "ñ"],
        ["a", "e", "i", "o", "u", "u", "n"],
        $texto
    );
}

function valorATexto($valor): string
{
    if (is_array($valor)) {
        return trim(implode(" ", array_map("valorATexto", $valor)));
    }

    return trim((string)$valor);
}

function crearSlug(string $texto): string
{
    $texto = normalizarTextoServidor($texto);
    $texto = preg_replace('/[^a-z0-9]+/u', '-', $texto);

    if ($texto === null) {
        return "sin-nombre";
    }

    $texto = trim($texto, "-");

    return $texto !== "" ? $texto : "sin-nombre";
}

function crearClavePlato(array $plato): string
{
    $id = (int)($plato["id"] ?? 0);
    $nombre = (string)($plato["nombre"] ?? "sin-nombre");
    $slug = crearSlug($nombre);

    return $id > 0 ? "plato_" . $id . "_" . $slug : "plato_" . $slug;
}

// ==========================================================
// REVISIONES SEPARADAS
// ==========================================================

function crearEstructuraRevisiones(): array
{
    return [
        "proyecto" => "Kitcherry Docs",
        "version" => "017-revisiones-separadas",
        "descripcion" => "Estados de revisión guardados fuera de la carta generada automáticamente.",
        "ultima_actualizacion" => "",
        "total_revisiones" => 0,
        "revisiones" => []
    ];
}

function normalizarEstructuraRevisiones(array $datos): array
{
    $base = crearEstructuraRevisiones();

    foreach ($base as $clave => $valor) {
        if (!array_key_exists($clave, $datos)) {
            $datos[$clave] = $valor;
        }
    }

    if (!isset($datos["revisiones"]) || !is_array($datos["revisiones"])) {
        $datos["revisiones"] = [];
    }

    $datos["total_revisiones"] = count($datos["revisiones"]);

    return $datos;
}

function migrarRevisionesAntiguasDesdeCarta(array $carta, array $revisionesData): array
{
    $platos = $carta["platos"] ?? [];

    if (!is_array($platos)) {
        return $revisionesData;
    }

    foreach ($platos as $plato) {
        $estado = strtolower(trim((string)($plato["estado_revision"] ?? "pendiente")));
        $tieneFecha = trim((string)($plato["revision_actualizada_en"] ?? "")) !== "";
        $tieneHistorial = isset($plato["historial_revision"]) &&
            is_array($plato["historial_revision"]) &&
            count($plato["historial_revision"]) > 0;

        if ($estado === "pendiente" && !$tieneFecha && !$tieneHistorial) {
            continue;
        }

        $clave = crearClavePlato($plato);

        if (isset($revisionesData["revisiones"][$clave])) {
            continue;
        }

        $fecha = trim((string)($plato["revision_actualizada_en"] ?? ""));

        if ($fecha === "") {
            $fecha = date("Y-m-d H:i:s");
        }

        $historial = $plato["historial_revision"] ?? [];

        if (!is_array($historial)) {
            $historial = [];
        }

        if (empty($historial)) {
            $historial[] = [
                "estado" => $estado,
                "fecha" => $fecha,
                "origen" => "migracion_desde_carta"
            ];
        }

        $revisionesData["revisiones"][$clave] = [
            "plato_id" => (int)($plato["id"] ?? 0),
            "nombre" => (string)($plato["nombre"] ?? ""),
            "estado_revision" => $estado,
            "actualizado_en" => $fecha,
            "origen" => "migracion_desde_carta",
            "historial" => $historial
        ];
    }

    $revisionesData["total_revisiones"] = count($revisionesData["revisiones"]);

    if ($revisionesData["total_revisiones"] > 0 && trim((string)$revisionesData["ultima_actualizacion"]) === "") {
        $revisionesData["ultima_actualizacion"] = date("Y-m-d H:i:s");
    }

    return $revisionesData;
}

function aplicarRevisionesAPlatos(array $platos, array $revisionesData): array
{
    $revisiones = $revisionesData["revisiones"] ?? [];

    if (!is_array($revisiones)) {
        return $platos;
    }

    foreach ($platos as $indice => $plato) {
        $clave = crearClavePlato($plato);

        if (!isset($revisiones[$clave]) || !is_array($revisiones[$clave])) {
            $platos[$indice]["estado_revision"] = $platos[$indice]["estado_revision"] ?? "pendiente";
            continue;
        }

        $revision = $revisiones[$clave];

        $platos[$indice]["estado_revision"] = $revision["estado_revision"] ?? "pendiente";
        $platos[$indice]["revision_actualizada_en"] = $revision["actualizado_en"] ?? "";
        $platos[$indice]["historial_revision"] = $revision["historial"] ?? [];
        $platos[$indice]["revision_origen"] = "revisiones_platos.json";
    }

    return $platos;
}

function textoEstadoRevision(string $estado): string
{
    return match ($estado) {
        "revisado" => "Revisado",
        "corregido" => "Corregido",
        default => "Pendiente"
    };
}

function procesarActualizacionRevision(string $rutaRevisiones, array $carta, array &$revisionesData): array
{
    $respuesta = [
        "tipo" => "",
        "mensaje" => ""
    ];

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        return $respuesta;
    }

    if (($_POST["accion"] ?? "") !== "actualizar_revision") {
        return $respuesta;
    }

    $platoId = (int)($_POST["plato_id"] ?? 0);
    $nuevoEstado = strtolower(trim((string)($_POST["nuevo_estado"] ?? "")));
    $estadosPermitidos = ["pendiente", "revisado", "corregido"];

    if ($platoId <= 0 || !in_array($nuevoEstado, $estadosPermitidos, true)) {
        return [
            "tipo" => "error",
            "mensaje" => "No se pudo actualizar la revisión porque los datos recibidos no son válidos."
        ];
    }

    $platos = $carta["platos"] ?? [];

    if (!is_array($platos)) {
        return [
            "tipo" => "error",
            "mensaje" => "No se encontró la carta estructurada."
        ];
    }

    $platoEncontrado = null;

    foreach ($platos as $plato) {
        if ((int)($plato["id"] ?? 0) === $platoId) {
            $platoEncontrado = $plato;
            break;
        }
    }

    if ($platoEncontrado === null) {
        return [
            "tipo" => "error",
            "mensaje" => "No se encontró el plato seleccionado."
        ];
    }

    $fechaActual = date("Y-m-d H:i:s");
    $clave = crearClavePlato($platoEncontrado);

    if (!isset($revisionesData["revisiones"]) || !is_array($revisionesData["revisiones"])) {
        $revisionesData["revisiones"] = [];
    }

    $revisionAnterior = $revisionesData["revisiones"][$clave] ?? [];
    $historial = $revisionAnterior["historial"] ?? [];

    if (!is_array($historial)) {
        $historial = [];
    }

    $historial[] = [
        "estado" => $nuevoEstado,
        "fecha" => $fechaActual,
        "origen" => "panel"
    ];

    $revisionesData["revisiones"][$clave] = [
        "plato_id" => (int)($platoEncontrado["id"] ?? 0),
        "nombre" => (string)($platoEncontrado["nombre"] ?? ""),
        "estado_revision" => $nuevoEstado,
        "actualizado_en" => $fechaActual,
        "origen" => "panel",
        "historial" => $historial
    ];

    $revisionesData["ultima_actualizacion"] = $fechaActual;
    $revisionesData["total_revisiones"] = count($revisionesData["revisiones"]);

    if (!guardarJson($rutaRevisiones, $revisionesData)) {
        return [
            "tipo" => "error",
            "mensaje" => "La revisión se actualizó, pero no se pudo guardar en out/revisiones_platos.json."
        ];
    }

    $nombrePlato = $platoEncontrado["nombre"] ?? "Plato";

    return [
        "tipo" => "ok",
        "mensaje" => $nombrePlato . " marcado como " . textoEstadoRevision($nuevoEstado) . "."
    ];
}

// ==========================================================
// DATOS DE CARTA
// ==========================================================

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
        if (valorATexto($valor) !== "") {
            return true;
        }
    }

    return false;
}

function obtenerTextoFicha(array $plato): string
{
    $ficha = $plato["ficha_tecnica"] ?? [];

    return is_array($ficha) ? valorATexto($ficha) : "";
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

// ==========================================================
// CLASES VISUALES
// ==========================================================

function claseRevision(string $estado): string
{
    return match (strtolower(trim($estado))) {
        "revisado" => "estado-revisado",
        "corregido" => "estado-corregido",
        default => "estado-pendiente"
    };
}

function claseNivelRevision(string $nivel): string
{
    return match (strtolower(trim($nivel))) {
        "bajo" => "nivel-bajo",
        "medio" => "nivel-medio",
        "alto" => "nivel-alto",
        default => "nivel-medio"
    };
}

function claseAdvertenciaAlimentaria(string $tipo): string
{
    return match ($tipo) {
        "trazas" => "food-warning-trazas",
        "sin_alergenos" => "food-warning-sin-alergenos",
        "varios_alergenos" => "food-warning-varios",
        "revision" => "food-warning-revision",
        "apto_condicional" => "food-warning-apto",
        default => "food-warning-general"
    };
}

// ==========================================================
// DOCUMENTOS PROCESADOS
// ==========================================================

function nombreDocumentoAmigable(string $archivo): string
{
    $nombre = str_replace(".summary.txt", "", $archivo);
    $nombre = str_replace("_limpio", "", $nombre);
    $nombre = str_replace("_", " ", $nombre);

    return ucfirst($nombre);
}

function obtenerDocumentoAmigable(string $archivo, string $tipo = ""): array
{
    $archivoMinusculas = minusculas($archivo);
    $tipoMinusculas = minusculas($tipo);

    if (str_contains($archivoMinusculas, "carta") || $tipoMinusculas === "carta") {
        return [
            "titulo" => "Carta del restaurante",
            "tipo" => "Carta",
            "descripcion" => "Documento con platos, categorías, precios, descripciones e ingredientes.",
            "uso" => "Base para construir la carta digital.",
            "icono" => "🍽️"
        ];
    }

    if (str_contains($archivoMinusculas, "fichas") || $tipoMinusculas === "fichas_tecnicas") {
        return [
            "titulo" => "Fichas técnicas de cocina",
            "tipo" => "Ficha técnica",
            "descripcion" => "Documento interno con elaboraciones, ingredientes, raciones, conservación y preparación.",
            "uso" => "Amplía la información de cada plato y facilita la revisión interna.",
            "icono" => "👨‍🍳"
        ];
    }

    if (str_contains($archivoMinusculas, "alergenos") || $tipoMinusculas === "tabla_alergenos") {
        return [
            "titulo" => "Tabla de alérgenos",
            "tipo" => "Alérgenos",
            "descripcion" => "Documento de control que relaciona platos con alérgenos declarados.",
            "uso" => "Apoya la revisión alimentaria y la consulta por parte del personal.",
            "icono" => "⚠️"
        ];
    }

    return [
        "titulo" => nombreDocumentoAmigable($archivo),
        "tipo" => "Documento",
        "descripcion" => "Documento utilizado como fuente para generar la información estructurada.",
        "uso" => "Sirve como apoyo dentro del módulo de carta, platos y alérgenos.",
        "icono" => "📄"
    ];
}

function textoNivelRevision(string $nivel): string
{
    return match (strtolower(trim($nivel))) {
        "alto" => "Revisión alta",
        "medio" => "Revisión media",
        "bajo" => "Revisión baja",
        default => "Revisión recomendada"
    };
}

function descripcionNivelRevision(string $nivel): string
{
    return match (strtolower(trim($nivel))) {
        "alto" => "Debe revisarse antes de usar la información con clientes.",
        "medio" => "Conviene comprobar la información antes de publicarla o utilizarla en sala.",
        "bajo" => "La información parece sencilla, pero se recomienda mantener control documental.",
        default => "Se recomienda revisar el documento antes de usar su información."
    };
}

function limitarLista(array $lista, int $limite = 8): array
{
    return array_slice($lista, 0, $limite);
}

// ==========================================================
// ADVERTENCIAS ALIMENTARIAS
// ==========================================================

function extraerFrasesSensibles(string $texto): array
{
    $frasesDetectadas = [];
    $texto = trim($texto);

    if ($texto === "") {
        return $frasesDetectadas;
    }

    $frases = preg_split('/(?<=[.!?])\s+/u', $texto);

    if (!is_array($frases)) {
        $frases = [$texto];
    }

    $palabrasClave = [
        "trazas",
        "puede contener",
        "contaminación cruzada",
        "contaminacion cruzada",
        "según el tipo",
        "pan rallado industrial",
        "galleta",
        "base",
        "caldo base",
        "industrial"
    ];

    foreach ($frases as $frase) {
        $fraseLimpia = trim($frase);

        if ($fraseLimpia === "") {
            continue;
        }

        $fraseNormalizada = normalizarTextoServidor($fraseLimpia);

        foreach ($palabrasClave as $palabraClave) {
            if (str_contains($fraseNormalizada, normalizarTextoServidor($palabraClave))) {
                if (!in_array($fraseLimpia, $frasesDetectadas, true)) {
                    $frasesDetectadas[] = $fraseLimpia;
                }

                break;
            }
        }
    }

    return $frasesDetectadas;
}

function obtenerAdvertenciasAlimentarias(array $plato): array
{
    $advertencias = [];

    $nombre = trim((string)($plato["nombre"] ?? ""));
    $estado = strtolower(trim((string)($plato["estado_revision"] ?? "pendiente")));
    $alergenos = $plato["alergenos_declarados"] ?? [];
    $ficha = $plato["ficha_tecnica"] ?? [];

    if (!is_array($alergenos)) {
        $alergenos = [];
    }

    if (!is_array($ficha)) {
        $ficha = [];
    }

    $textoCompleto = trim(implode(" ", [
        (string)($ficha["alergenos_texto"] ?? ""),
        (string)($ficha["conservacion"] ?? ""),
        (string)($ficha["ingredientes"] ?? ""),
        (string)($plato["descripcion"] ?? "")
    ]));

    foreach (extraerFrasesSensibles($textoCompleto) as $fraseSensible) {
        $advertencias[] = [
            "tipo" => "trazas",
            "titulo" => "Posibles trazas o contaminación cruzada",
            "texto" => $fraseSensible,
            "visibilidad" => "cliente_interna"
        ];
    }

    if (count($alergenos) === 0) {
        $advertencias[] = [
            "tipo" => "sin_alergenos",
            "titulo" => "Sin alérgenos declarados",
            "texto" => "No aparecen alérgenos declarados en los documentos procesados. Conviene confirmarlo antes de comunicarlo.",
            "visibilidad" => "cliente_interna"
        ];
    }

    if (count($alergenos) >= 4) {
        $advertencias[] = [
            "tipo" => "varios_alergenos",
            "titulo" => "Plato con varios alérgenos",
            "texto" => "Contiene varios alérgenos declarados. Revisión interna recomendada antes de responder dudas del cliente.",
            "visibilidad" => "interna"
        ];
    }

    if ($estado === "pendiente") {
        $advertencias[] = [
            "tipo" => "revision",
            "titulo" => "Pendiente de revisión interna",
            "texto" => "La información todavía no ha sido revisada por una persona responsable.",
            "visibilidad" => "interna"
        ];
    }

    if ($estado === "corregido") {
        $advertencias[] = [
            "tipo" => "revision",
            "titulo" => "Información corregida",
            "texto" => "Este plato fue marcado como corregido. Conviene verificar que coincide con la información actual de cocina.",
            "visibilidad" => "interna"
        ];
    }

    if ($nombre !== "" && str_contains(normalizarTextoServidor($nombre), "fruta") && count($alergenos) === 0) {
        $advertencias[] = [
            "tipo" => "apto_condicional",
            "titulo" => "Apto de forma orientativa",
            "texto" => "Debe comprobarse la manipulación y el posible contacto cruzado antes de confirmarlo a una persona alérgica.",
            "visibilidad" => "cliente_interna"
        ];
    }

    return $advertencias;
}

function tieneAdvertenciasCliente(array $advertencias): bool
{
    foreach ($advertencias as $advertencia) {
        if (($advertencia["visibilidad"] ?? "cliente_interna") === "cliente_interna") {
            return true;
        }
    }

    return false;
}

function textoAdvertenciasParaImpresion(array $advertencias, bool $modoInterno = true): string
{
    $textos = [];

    foreach ($advertencias as $advertencia) {
        $visibilidad = $advertencia["visibilidad"] ?? "cliente_interna";

        if (!$modoInterno && $visibilidad !== "cliente_interna") {
            continue;
        }

        $titulo = trim((string)($advertencia["titulo"] ?? ""));
        $texto = trim((string)($advertencia["texto"] ?? ""));

        if ($titulo !== "" && $texto !== "") {
            $textos[] = $titulo . ": " . $texto;
        } elseif ($texto !== "") {
            $textos[] = $texto;
        }
    }

    return empty($textos) ? "Sin advertencias destacadas." : implode(" | ", $textos);
}

function obtenerPlatosSensibles(array $platos): array
{
    $sensibles = [];

    foreach ($platos as $plato) {
        $advertencias = obtenerAdvertenciasAlimentarias($plato);

        if (!empty($advertencias)) {
            $sensibles[] = [
                "plato" => $plato,
                "advertencias" => $advertencias
            ];
        }
    }

    return $sensibles;
}

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

$mensajeSistema = procesarActualizacionRevision($revisionesPath, $carta, $revisionesData);

$platosOriginales = $carta["platos"] ?? [];
$platos = is_array($platosOriginales) ? aplicarRevisionesAPlatos($platosOriginales, $revisionesData) : [];

$negocio = $carta["negocio"] ?? "Negocio no especificado";
$totalPlatos = count($platos);
$categorias = obtenerCategorias($platos);
$alergenos = obtenerAlergenos($platos);

$totalConAlergenos = 0;
$totalSinAlergenos = 0;
$totalPendientes = 0;
$totalRevisados = 0;
$totalCorregidos = 0;
$totalConAdvertencias = 0;
$totalConTrazas = 0;

foreach ($platos as $plato) {
    $listaAlergenos = $plato["alergenos_declarados"] ?? [];
    $advertenciasPlato = obtenerAdvertenciasAlimentarias($plato);
    $estadoRevision = strtolower((string)($plato["estado_revision"] ?? "pendiente"));

    if (is_array($listaAlergenos) && count($listaAlergenos) > 0) {
        $totalConAlergenos++;
    } else {
        $totalSinAlergenos++;
    }

    if ($estadoRevision === "revisado") {
        $totalRevisados++;
    } elseif ($estadoRevision === "corregido") {
        $totalCorregidos++;
    } else {
        $totalPendientes++;
    }

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
                <a href="#modo-vista">Vista</a>
                <a href="#consulta-alergenos">Consulta alérgenos</a>
                <a href="#consulta">Carta</a>
                <a href="#tabla-alergenos">Tabla</a>
                <a href="#advertencias">Advertencias</a>
                <a href="#impresion">Imprimir</a>
                <a href="#documentos" class="internal-only">Documentos</a>
                <a href="#tecnico" class="internal-only">Técnico</a>
            </nav>
        </div>
    </header>

    <main class="container">

        <div class="print-header">
            <strong>KITCHERRY DOCS</strong>
            <span><?= e($negocio) ?> · <?= e($fechaInforme) ?></span>
            <p>Consulta de carta, ingredientes, alérgenos y advertencias alimentarias.</p>
        </div>

        <section class="hero">
            <div class="hero-main">
                <span class="eyebrow">Carta, platos y alérgenos</span>

                <h1>Panel de consulta alimentaria</h1>

                <p class="internal-only">
                    Revisa platos, ingredientes, alérgenos, advertencias, fichas técnicas y estados internos desde un único panel.
                </p>

                <p class="client-only">
                    Consulta platos, ingredientes principales, alérgenos declarados y avisos alimentarios del establecimiento.
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
                        Carta estructurada con revisiones manuales guardadas en un archivo independiente.
                    </p>

                    <p class="client-only">
                        Información preparada para consulta clara de platos, alérgenos y avisos.
                    </p>

                    <span class="status-pill internal-only"><?= e((string)$totalPendientes) ?> platos pendientes</span>
                    <span class="status-pill client-only"><?= e((string)$totalPlatos) ?> platos disponibles</span>
                </div>

                <div class="alert-box">
                    <strong>Revisión necesaria</strong>
                    La información sobre alérgenos, trazas y contaminación cruzada debe verificarse antes de comunicarse al cliente.
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
                        Estás usando la vista interna, pensada para revisión, documentos, fuentes y detalle técnico.
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
                    Se ocultan revisiones internas, fuentes técnicas, documentos procesados y detalles de desarrollo.
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
                <span>Con alérgenos</span>
                <strong><?= e((string)$totalConAlergenos) ?></strong>
            </article>

            <article class="stat-card">
                <span>Con avisos</span>
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
                        Selecciona alérgenos para localizar platos que los contienen o platos aparentemente aptos.
                    </p>

                    <p class="client-only">
                        Selecciona alérgenos para consultar la información declarada en la carta.
                    </p>
                </div>
            </div>

            <div class="card allergy-consult">
                <div class="consult-header">
                    <div>
                        <h3>¿Qué necesita saber el cliente?</h3>

                        <p class="internal-only">
                            Herramienta de apoyo. Antes de confirmar información sensible, revisa con el responsable del establecimiento.
                        </p>

                        <p class="client-only">
                            En caso de alergia grave, consulta siempre con el personal responsable.
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
                        Busca por plato, ingrediente, alérgeno, advertencia o estado de revisión.
                    </p>

                    <p class="client-only">
                        Busca platos o filtra por categoría y alérgeno.
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

                        $textoBusqueda = implode(" ", [
                            $nombre,
                            $categoria,
                            $descripcion,
                            is_array($ingredientes) ? implode(" ", $ingredientes) : "",
                            is_array($alergenosPlato) ? implode(" ", $alergenosPlato) : "",
                            obtenerTextoFicha($plato),
                            textoAdvertenciasParaImpresion($advertenciasPlato, true)
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
                                        <span class="badge badge-categoria">
                                            <?= e($categoria !== "" ? $categoria : "Sin categoría") ?>
                                        </span>

                                        <span class="badge <?= e(claseRevision($estado)) ?> badge-revision internal-only">
                                            <?= e($estado) ?>
                                        </span>

                                        <?php if ($hayAdvertenciasCliente): ?>
                                            <span class="badge badge-food-warning">Aviso alimentario</span>
                                        <?php elseif ($soloAdvertenciasInternas): ?>
                                            <span class="badge badge-food-warning internal-only">Revisión interna</span>
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
                                        <strong class="internal-only">
                                            <?= e((string)count($advertenciasPlato)) ?> aviso(s) de revisión.
                                        </strong>

                                        <p class="client-only">
                                            Consulta el detalle antes de confirmar información sensible.
                                        </p>

                                        <p class="internal-only">
                                            El detalle se muestra en la sección de advertencias para evitar duplicar información.
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
                        Matriz de consulta para revisar alérgenos declarados por plato.
                    </p>

                    <p class="client-only">
                        Vista rápida de alérgenos declarados por plato.
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
                                            <span class="matrix-category">
                                                <?= e($categoria !== "" ? $categoria : "Sin categoría") ?>
                                            </span>
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
                        La ausencia de un alérgeno solo significa que no aparece declarado en los documentos procesados.
                        Las trazas y la contaminación cruzada deben revisarse antes de comunicar la información.
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section id="advertencias" class="section print-section print-advertencias">
            <div class="section-title">
                <div>
                    <h2>Advertencias alimentarias</h2>

                    <p class="internal-only">
                        Platos que requieren atención por trazas, alérgenos no declarados, varios alérgenos o revisión pendiente.
                    </p>

                    <p class="client-only">
                        Avisos destacados. Ante alergias o intolerancias, consulta siempre con el personal responsable.
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
                                    <span class="badge badge-categoria">
                                        <?= e($categoriaSensible !== "" ? $categoriaSensible : "Sin categoría") ?>
                                    </span>

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
                    <p>Vista compacta preparada para imprimir o guardar como PDF.</p>
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
                La información debe ser revisada por una persona responsable antes de comunicarse al cliente.
            </div>
        </section>

        <section id="impresion" class="section no-print">
            <div class="section-title">
                <div>
                    <h2>Imprimir o guardar PDF</h2>

                    <p class="internal-only">
                        Genera documentos para sala, revisión interna o consulta alimentaria.
                    </p>

                    <p class="client-only">
                        Genera una versión sencilla de carta y alérgenos declarados.
                    </p>
                </div>
            </div>

            <div class="print-actions-grid">
                <article class="card print-card">
                    <h3>Carta para sala</h3>
                    <p>Platos, precios, ingredientes, alérgenos y avisos destacados.</p>
                    <button type="button" class="print-btn" data-print-mode="carta">
                        Imprimir carta
                    </button>
                </article>

                <article class="card print-card">
                    <h3>Tabla de alérgenos</h3>
                    <p>Vista compacta de alérgenos por plato.</p>
                    <button type="button" class="print-btn" data-print-mode="matriz">
                        Imprimir tabla
                    </button>
                </article>

                <article class="card print-card">
                    <h3>Advertencias</h3>
                    <p>Platos sensibles, trazas y avisos alimentarios.</p>
                    <button type="button" class="print-btn" data-print-mode="advertencias">
                        Imprimir advertencias
                    </button>
                </article>

                <article class="card print-card internal-only">
                    <h3>Informe interno</h3>
                    <p>Documentos procesados, revisiones, advertencias y detalle técnico.</p>
                    <button type="button" class="print-btn" data-print-mode="interno">
                        Imprimir informe
                    </button>
                </article>
            </div>
        </section>

        <section id="documentos" class="section print-section print-interno internal-only">
            <div class="section-title">
                <div>
                    <h2>Documentos procesados</h2>
                    <p>
                        Resumen claro de los documentos utilizados para generar la carta estructurada.
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

                        $platosIa = is_array($platosIa) ? $platosIa : [];
                        $alergenosIa = is_array($alergenosIa) ? $alergenosIa : [];
                        $ingredientesIa = is_array($ingredientesIa) ? $ingredientesIa : [];
                        $advertenciasIa = is_array($advertenciasIa) ? $advertenciasIa : [];
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

                                <p><?= e(descripcionNivelRevision($nivel)) ?></p>
                            </div>

                            <div class="doc-friendly-block">
                                <div class="mini-title">Uso dentro de Kitcherry Docs</div>
                                <p>
                                    <?= e(!empty($ia["uso_en_kitcherry"]) ? $ia["uso_en_kitcherry"] : $documentoAmigable["uso"]) ?>
                                </p>
                            </div>

                            <?php if (!empty($ia["resumen_utilidad"])): ?>
                                <div class="doc-friendly-block">
                                    <div class="mini-title">Resumen</div>
                                    <p><?= e($ia["resumen_utilidad"]) ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="doc-mini-stats">
                                <div>
                                    <strong><?= e((string)count($platosIa)) ?></strong>
                                    <span>platos</span>
                                </div>

                                <div>
                                    <strong><?= e((string)count($alergenosIa)) ?></strong>
                                    <span>alérgenos</span>
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
                                    <p><strong>Archivo:</strong> <?= e($archivoTecnico) ?></p>
                                    <p><strong>Tipo detectado:</strong> <?= e($tipo) ?></p>
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
                        <h2>Resumen interno</h2>
                        <p>
                            Resumen breve de los documentos procesados para revisión rápida.
                        </p>
                    </div>
                </div>

                <div class="docs-friendly-grid">
                    <?php foreach ($resumenes as $resumen): ?>
                        <?php $documentoResumen = obtenerDocumentoAmigable($resumen["archivo"]); ?>

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
                                    <p><strong>Archivo:</strong> <?= e($resumen["archivo"]) ?></p>
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
                        Apartado interno para revisar proceso, archivos generados y estado de las revisiones.
                    </p>

                    <p><strong>Estado del proceso:</strong> <?= e($estadoProceso) ?></p>
                    <p><strong>Carta generada:</strong> out/carta_kitcherry.json</p>
                    <p><strong>Revisiones manuales:</strong> out/revisiones_platos.json</p>
                    <p><strong>Revisiones guardadas:</strong> <?= e((string)$totalRevisionesGuardadas) ?></p>

                    <?php if ($ultimaActualizacionRevisiones !== ""): ?>
                        <p><strong>Última actualización:</strong> <?= e($ultimaActualizacionRevisiones) ?></p>
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
                                    <span>
                                        <?= e($paso["archivo"] ?? "") ?> · <?= e((string)($paso["duracion_segundos"] ?? 0)) ?> segundos
                                    </span>
                                </div>

                                <span class="step-status <?= e($claseEstado) ?>">
                                    <?= e($estadoPaso) ?>
                                </span>
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

    const botonesImpresion = Array.from(document.querySelectorAll("[data-print-mode]"));
    const botonesModoVista = Array.from(document.querySelectorAll("[data-view-mode]"));
    const textoModoVista = document.getElementById("textoModoVista");

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
                "Mostrando " + visibles + " platos aparentemente aptos para evitar: " + textoAlergenos + ". Revisa posibles trazas o advertencias.";
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

        return modoAlergenos.value === "aptos" ? !contieneAlguno : contieneAlguno;
    }

    function filtrarPlatos() {
        const textoBuscar = normalizar(buscar.value);
        const categoriaSeleccionada = normalizar(categoria.value);
        const alergenoSeleccionado = normalizar(alergeno.value);
        const revisionSeleccionada = revision ? normalizar(revision.value) : "";

        let visibles = 0;

        platos.forEach(function (plato) {
            const texto = normalizar(plato.dataset.text);
            const categoriaPlato = normalizar(plato.dataset.categoria);
            const alergenosPlato = normalizar(plato.dataset.alergenos);
            const revisionPlato = normalizar(plato.dataset.revision);

            const visible =
                (textoBuscar === "" || texto.includes(textoBuscar)) &&
                (categoriaSeleccionada === "" || categoriaPlato === categoriaSeleccionada) &&
                (alergenoSeleccionado === "" || alergenosPlato.includes(alergenoSeleccionado)) &&
                (revisionSeleccionada === "" || revisionPlato === revisionSeleccionada) &&
                coincideModoConsultaAlergenos(alergenosPlato);

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

            const visible =
                (textoBuscar === "" || textoFila.includes(textoBuscar)) &&
                (!soloConAlergenos || tieneAlergenos);

            fila.style.display = visible ? "" : "none";

            if (visible) {
                visibles++;
            }
        });

        contadorMatriz.textContent = visibles;
    }

    function limpiarModosImpresion() {
        document.body.classList.remove("print-mode-carta");
        document.body.classList.remove("print-mode-matriz");
        document.body.classList.remove("print-mode-interno");
        document.body.classList.remove("print-mode-advertencias");
    }

    function cambiarModoVista(modo) {
        document.body.classList.remove("view-mode-client");
        document.body.classList.remove("view-mode-internal");

        if (modo === "client") {
            document.body.classList.add("view-mode-client");

            if (textoModoVista) {
                textoModoVista.textContent =
                    "Estás usando la vista cliente. Se ocultan revisiones, fuentes técnicas y detalles internos.";
            }
        } else {
            document.body.classList.add("view-mode-internal");

            if (textoModoVista) {
                textoModoVista.textContent =
                    "Estás usando la vista interna, pensada para revisión, documentos, fuentes y detalle técnico.";
            }
        }

        botonesModoVista.forEach(function (boton) {
            boton.classList.toggle("is-active", boton.dataset.viewMode === modo);
        });

        localStorage.setItem("kitcherry_docs_view_mode", modo);

        filtrarPlatos();
        filtrarMatriz();
    }

    botonesAlergenos.forEach(function (boton) {
        boton.addEventListener("click", function () {
            const alergenoSeleccionado = normalizar(boton.dataset.alergeno);

            if (alergenosSeleccionados.includes(alergenoSeleccionado)) {
                alergenosSeleccionados = alergenosSeleccionados.filter(function (item) {
                    return item !== alergenoSeleccionado;
                });

                boton.classList.remove("is-active");
            } else {
                alergenosSeleccionados.push(alergenoSeleccionado);
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
    modoAlergenos.addEventListener("change", filtrarPlatos);

    if (revision) {
        revision.addEventListener("change", filtrarPlatos);
    }

    if (buscarMatriz) {
        buscarMatriz.addEventListener("input", filtrarMatriz);
    }

    if (soloConAlergenosMatriz) {
        soloConAlergenosMatriz.addEventListener("change", filtrarMatriz);
    }

    botonesImpresion.forEach(function (boton) {
        boton.addEventListener("click", function () {
            const modo = boton.dataset.printMode;

            limpiarModosImpresion();

            if (modo === "carta") {
                document.body.classList.add("print-mode-carta");
            }

            if (modo === "matriz") {
                document.body.classList.add("print-mode-matriz");
            }

            if (modo === "interno") {
                document.body.classList.add("print-mode-interno");
            }

            if (modo === "advertencias") {
                document.body.classList.add("print-mode-advertencias");
            }

            window.print();
        });
    });

    botonesModoVista.forEach(function (boton) {
        boton.addEventListener("click", function () {
            cambiarModoVista(boton.dataset.viewMode);
        });
    });

    window.addEventListener("afterprint", function () {
        limpiarModosImpresion();
    });

    const modoGuardado = localStorage.getItem("kitcherry_docs_view_mode") || "internal";
    cambiarModoVista(modoGuardado);
</script>

</body>
</html>