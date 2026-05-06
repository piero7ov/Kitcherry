<?php

declare(strict_types=1);

// ==========================================================
// FUNCIONES AUXILIARES GENERALES
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

function normalizarTextoServidor(string $texto): string
{
    $texto = minusculas($texto);

    $texto = str_replace(
        ["á", "é", "í", "ó", "ú", "ü", "ñ"],
        ["a", "e", "i", "o", "u", "u", "n"],
        $texto
    );

    return $texto;
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

// ==========================================================
// FUNCIONES DE REVISIÓN
// ==========================================================

function crearClavePlato(array $plato): string
{
    $id = (int)($plato["id"] ?? 0);
    $nombre = (string)($plato["nombre"] ?? "sin-nombre");
    $slug = crearSlug($nombre);

    if ($id > 0) {
        return "plato_" . $id . "_" . $slug;
    }

    return "plato_" . $slug;
}

function crearEstructuraRevisiones(): array
{
    return [
        "proyecto" => "Kitcherry Docs",
        "version" => "018-version-final-casa-pochi",
        "descripcion" => "Archivo independiente para guardar estados de revisión de platos sin modificar la carta generada automáticamente.",
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

function textoEstadoRevision(string $estado): string
{
    return match ($estado) {
        "revisado" => "Revisado",
        "corregido" => "Corregido",
        default => "Pendiente"
    };
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

function migrarRevisionesAntiguasDesdeCarta(array $carta, array $revisionesData): array
{
    $platos = $carta["platos"] ?? [];

    if (!is_array($platos)) {
        return $revisionesData;
    }

    foreach ($platos as $plato) {
        $estado = strtolower(trim((string)($plato["estado_revision"] ?? "pendiente")));
        $tieneFecha = trim((string)($plato["revision_actualizada_en"] ?? "")) !== "";
        $tieneHistorial = isset($plato["historial_revision"]) && is_array($plato["historial_revision"]) && count($plato["historial_revision"]) > 0;

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
            if (!isset($platos[$indice]["estado_revision"])) {
                $platos[$indice]["estado_revision"] = "pendiente";
            }

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

function procesarActualizacionRevisionSeparada(string $rutaRevisiones, array $carta, array &$revisionesData): array
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

    $platos = $carta["platos"] ?? [];

    if (!is_array($platos)) {
        return [
            "tipo" => "error",
            "mensaje" => "No se pudo actualizar el estado porque no se encontró la carta estructurada."
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
            "mensaje" => "No se encontró el plato seleccionado dentro de la carta."
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
        "origen" => "panel_final"
    ];

    $revisionesData["revisiones"][$clave] = [
        "plato_id" => (int)($platoEncontrado["id"] ?? 0),
        "nombre" => (string)($platoEncontrado["nombre"] ?? ""),
        "estado_revision" => $nuevoEstado,
        "actualizado_en" => $fechaActual,
        "origen" => "panel_final",
        "historial" => $historial
    ];

    $revisionesData["ultima_actualizacion"] = $fechaActual;
    $revisionesData["total_revisiones"] = count($revisionesData["revisiones"]);

    $guardado = guardarJson($rutaRevisiones, $revisionesData);

    if (!$guardado) {
        return [
            "tipo" => "error",
            "mensaje" => "El estado se modificó en memoria, pero no se pudo guardar en out/revisiones_platos.json."
        ];
    }

    $nombrePlato = $platoEncontrado["nombre"] ?? "Plato";

    return [
        "tipo" => "ok",
        "mensaje" => "Estado guardado: " . $nombrePlato . " ahora está marcado como " . textoEstadoRevision($nuevoEstado) . "."
    ];
}

// ==========================================================
// FUNCIONES DE CARTA, ALÉRGENOS Y DOCUMENTOS
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
        if (trim((string)$valor) !== "") {
            return true;
        }
    }

    return false;
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

function obtenerDocumentoAmigable(string $archivo, string $tipo = ""): array
{
    $archivoMinusculas = minusculas($archivo);
    $tipoMinusculas = minusculas($tipo);

    if (str_contains($archivoMinusculas, "carta") || $tipoMinusculas === "carta") {
        return [
            "titulo" => "Carta del restaurante",
            "tipo" => "Carta",
            "descripcion" => "Documento principal con platos, categorías, precios, descripciones e ingredientes del establecimiento.",
            "uso" => "Sirve para construir la carta digital y relacionar cada plato con su información básica.",
            "icono" => "🍽️"
        ];
    }

    if (str_contains($archivoMinusculas, "fichas") || $tipoMinusculas === "fichas_tecnicas") {
        return [
            "titulo" => "Fichas técnicas de cocina",
            "tipo" => "Ficha técnica",
            "descripcion" => "Documento interno con elaboraciones, ingredientes, conservación, raciones y detalles de preparación.",
            "uso" => "Sirve para ampliar la información de cada plato y facilitar la revisión por cocina o responsable del negocio.",
            "icono" => "👨‍🍳"
        ];
    }

    if (str_contains($archivoMinusculas, "alergenos") || $tipoMinusculas === "tabla_alergenos") {
        return [
            "titulo" => "Tabla de alérgenos",
            "tipo" => "Alérgenos",
            "descripcion" => "Documento de control que relaciona platos con alérgenos declarados.",
            "uso" => "Sirve para revisar la seguridad alimentaria y preparar información útil para sala.",
            "icono" => "⚠️"
        ];
    }

    return [
        "titulo" => nombreDocumentoAmigable($archivo),
        "tipo" => "Documento",
        "descripcion" => "Documento utilizado como fuente de información para generar la carta estructurada.",
        "uso" => "Sirve como apoyo documental dentro del módulo de carta, platos y alérgenos.",
        "icono" => "📄"
    ];
}

function textoNivelRevision(string $nivel): string
{
    $nivel = strtolower(trim($nivel));

    return match ($nivel) {
        "alto" => "Revisión alta",
        "medio" => "Revisión media",
        "bajo" => "Revisión baja",
        default => "Revisión recomendada"
    };
}

function descripcionNivelRevision(string $nivel): string
{
    $nivel = strtolower(trim($nivel));

    return match ($nivel) {
        "alto" => "Debe revisarse antes de usar la información con clientes, especialmente si contiene alérgenos.",
        "medio" => "Conviene comprobar la información antes de publicarla o utilizarla en sala.",
        "bajo" => "La información parece sencilla, pero puede revisarse para mantener el control documental.",
        default => "Se recomienda revisar el documento antes de usar su información."
    };
}

function limitarLista(array $lista, int $limite = 8): array
{
    return array_slice($lista, 0, $limite);
}

// ==========================================================
// FUNCIONES DE ADVERTENCIAS ALIMENTARIAS
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

    $textoAlergenosFicha = trim((string)($ficha["alergenos_texto"] ?? ""));
    $textoConservacion = trim((string)($ficha["conservacion"] ?? ""));
    $textoIngredientesFicha = trim((string)($ficha["ingredientes"] ?? ""));
    $textoDescripcion = trim((string)($plato["descripcion"] ?? ""));

    $textoCompletoFicha = trim(implode(" ", [
        $textoAlergenosFicha,
        $textoConservacion,
        $textoIngredientesFicha,
        $textoDescripcion
    ]));

    $frasesSensibles = extraerFrasesSensibles($textoCompletoFicha);

    foreach ($frasesSensibles as $fraseSensible) {
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
            "texto" => "No se han declarado alérgenos para este plato en los documentos procesados. Conviene confirmarlo antes de comunicarlo al cliente.",
            "visibilidad" => "cliente_interna"
        ];
    }

    if (count($alergenos) >= 4) {
        $advertencias[] = [
            "tipo" => "varios_alergenos",
            "titulo" => "Plato con varios alérgenos",
            "texto" => "Este plato contiene varios alérgenos declarados. Es recomendable revisarlo con especial atención antes de responder dudas del cliente.",
            "visibilidad" => "interna"
        ];
    }

    if ($estado === "pendiente") {
        $advertencias[] = [
            "tipo" => "revision",
            "titulo" => "Pendiente de revisión interna",
            "texto" => "La información de este plato todavía está pendiente de revisión por una persona responsable del establecimiento.",
            "visibilidad" => "interna"
        ];
    }

    if ($estado === "corregido") {
        $advertencias[] = [
            "tipo" => "revision",
            "titulo" => "Información corregida",
            "texto" => "Este plato ha sido marcado como corregido. Conviene verificar que los cambios realizados coinciden con la información actual de cocina.",
            "visibilidad" => "interna"
        ];
    }

    if ($nombre !== "" && str_contains(normalizarTextoServidor($nombre), "fruta") && count($alergenos) === 0) {
        $advertencias[] = [
            "tipo" => "apto_condicional",
            "titulo" => "Apto de forma orientativa",
            "texto" => "Aunque no se han declarado alérgenos, debe comprobarse la manipulación y posible contacto cruzado antes de confirmarlo a una persona alérgica.",
            "visibilidad" => "cliente_interna"
        ];
    }

    return $advertencias;
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

function tieneAdvertenciasCliente(array $advertencias): bool
{
    foreach ($advertencias as $advertencia) {
        $visibilidad = $advertencia["visibilidad"] ?? "cliente_interna";

        if ($visibilidad === "cliente_interna") {
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

    if (empty($textos)) {
        return "Sin advertencias destacadas.";
    }

    return implode(" | ", $textos);
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

