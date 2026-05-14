<?php
// ==========================================================
// KITCHERRY VOICE TASKS
// Archivo: api/procesar_voz_ia.php
// ==========================================================

header("Content-Type: application/json; charset=UTF-8");

function responderJson($datos) {
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function limpiarTexto($texto) {
    return trim($texto ?? "");
}

function minusculas($texto) {
    return mb_strtolower($texto, "UTF-8");
}

function primeraMayuscula($texto) {
    $texto = limpiarTexto($texto);

    if ($texto === "") {
        return "";
    }

    return mb_strtoupper(mb_substr($texto, 0, 1, "UTF-8"), "UTF-8") . mb_substr($texto, 1, null, "UTF-8");
}

function numeroPalabraANumero($valor) {
    $valor = minusculas(trim($valor));

    $mapa = [
        "cero" => "0",
        "un" => "1",
        "uno" => "1",
        "una" => "1",
        "dos" => "2",
        "tres" => "3",
        "cuatro" => "4",
        "cinco" => "5",
        "seis" => "6",
        "siete" => "7",
        "ocho" => "8",
        "nueve" => "9",
        "diez" => "10",
        "once" => "11",
        "doce" => "12",
        "trece" => "13",
        "catorce" => "14",
        "quince" => "15",
        "dieciséis" => "16",
        "dieciseis" => "16",
        "diecisiete" => "17",
        "dieciocho" => "18",
        "diecinueve" => "19",
        "veinte" => "20"
    ];

    return $mapa[$valor] ?? $valor;
}

function limpiarDescripcionOperativa($texto) {
    $texto = limpiarTexto($texto);

    $texto = preg_replace('/^(para\s+cocina|para\s+sala|para\s+barra|para\s+general)\s+/iu', '', $texto);
    $texto = preg_replace('/^(sube|subir|lleva|llevar|reponer|repón|apunta|añade|falta|faltan|necesitamos|necesito|poner|trae|traer)\s+/iu', '', $texto);

    return limpiarTexto($texto);
}

function normalizarDescripcion($descripcion) {
    $descripcion = limpiarTexto($descripcion);

    $reemplazos = [
        "/coca\s*-\s*cola/iu" => "Coca-Cola",
        "/cocacola/iu" => "Coca-Cola",
        "/coca\s+cola/iu" => "Coca-Cola",
        "/coca\s*-\s*cola\s*cero/iu" => "Coca-Cola Zero",
        "/coca\s*cola\s*cero/iu" => "Coca-Cola Zero",
        "/coca-colacero/iu" => "Coca-Cola Zero",
        "/cocacolacero/iu" => "Coca-Cola Zero",
        "/zero/iu" => "Zero"
    ];

    foreach ($reemplazos as $patron => $reemplazo) {
        $descripcion = preg_replace($patron, $reemplazo, $descripcion);
    }

    $descripcion = preg_replace('/\s+/', ' ', $descripcion);

    return primeraMayuscula($descripcion);
}

function esTipoTextoLibreTotal($tipo) {
    $tipo = minusculas($tipo);

    return (
        str_contains($tipo, "incidencia") ||
        str_contains($tipo, "aviso")
    );
}

function esTipoLineaSimple($tipo) {
    $tipo = minusculas($tipo);

    return (
        str_contains($tipo, "elaboración") ||
        str_contains($tipo, "elaboracion") ||
        str_contains($tipo, "tarea")
    );
}

function esTipoReposicion($tipo) {
    $tipo = minusculas($tipo);

    return str_contains($tipo, "reposición") || str_contains($tipo, "reposicion");
}

function limpiarTextoLibre($texto) {
    $texto = limpiarTexto($texto);

    $texto = preg_replace('/^(apunta|anota|avisar|avisa|recuerda|recordar|importante)\s+/iu', '', $texto);
    $texto = preg_replace('/\s+/', ' ', $texto);

    return primeraMayuscula($texto);
}

function dividirTextoLibreEnItems($texto) {
    $texto = str_replace(["\r\n", "\r"], "\n", $texto);
    $lineas = array_filter(array_map("trim", explode("\n", $texto)));

    $items = [];

    foreach ($lineas as $linea) {
        $linea = limpiarTextoLibre($linea);

        if ($linea !== "") {
            $items[] = [
                "descripcion" => $linea,
                "cantidad" => ""
            ];
        }
    }

    if (!empty($items)) {
        return $items;
    }

    $texto = limpiarTextoLibre($texto);

    if ($texto !== "") {
        $items[] = [
            "descripcion" => $texto,
            "cantidad" => ""
        ];
    }

    return $items;
}

function separarCantidadSoloSiEsClara($texto) {
    $texto = limpiarTexto($texto);
    $texto = str_replace(["–", "—"], "-", $texto);
    $texto = preg_replace('/\s*-\s*/u', '-', $texto);
    $texto = preg_replace('/\s+/', ' ', $texto);

    $patronNumero = '(\\d+|un|uno|una|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|once|doce|trece|catorce|quince|dieciséis|dieciseis|diecisiete|dieciocho|diecinueve|veinte)';

    // Caso seguro: "curry rojo 2"
    if (preg_match('/^(.+?)\\s+' . $patronNumero . '$/iu', $texto, $coincidencias)) {
        return [
            "descripcion" => primeraMayuscula($coincidencias[1]),
            "cantidad" => numeroPalabraANumero($coincidencias[2])
        ];
    }

    // Caso seguro: "curry rojo-2"
    if (preg_match('/^(.+?)-(' . $patronNumero . ')$/iu', $texto, $coincidencias)) {
        return [
            "descripcion" => primeraMayuscula($coincidencias[1]),
            "cantidad" => numeroPalabraANumero($coincidencias[2])
        ];
    }

    return [
        "descripcion" => primeraMayuscula($texto),
        "cantidad" => ""
    ];
}

function dividirLineasComoItemsSimples($texto) {
    $texto = str_replace(["\r\n", "\r"], "\n", $texto);
    $lineas = array_filter(array_map("trim", explode("\n", $texto)));

    $items = [];

    foreach ($lineas as $linea) {
        $linea = limpiarTextoLibre($linea);

        if ($linea === "") {
            continue;
        }

        $items[] = separarCantidadSoloSiEsClara($linea);
    }

    if (!empty($items)) {
        return $items;
    }

    $texto = limpiarTextoLibre($texto);

    if ($texto !== "") {
        $items[] = separarCantidadSoloSiEsClara($texto);
    }

    return $items;
}

function detectarCantidadPegadaAlFinal($texto) {
    $textoOriginal = limpiarTexto($texto);
    $textoMinuscula = minusculas($textoOriginal);

    $especiales = [
        "fantanaranjados" => [
            "descripcion" => "Fanta naranja",
            "cantidad" => "2"
        ],
        "fantanaranjauno" => [
            "descripcion" => "Fanta naranja",
            "cantidad" => "1"
        ],
        "fantanaranjauna" => [
            "descripcion" => "Fanta naranja",
            "cantidad" => "1"
        ],
        "fantanaranjatres" => [
            "descripcion" => "Fanta naranja",
            "cantidad" => "3"
        ],
        "fantanaranjacuatro" => [
            "descripcion" => "Fanta naranja",
            "cantidad" => "4"
        ],
        "fantanaranjacinco" => [
            "descripcion" => "Fanta naranja",
            "cantidad" => "5"
        ],
        "fantanaranjaseis" => [
            "descripcion" => "Fanta naranja",
            "cantidad" => "6"
        ]
    ];

    if (isset($especiales[$textoMinuscula])) {
        return $especiales[$textoMinuscula];
    }

    $sufijos = [
        "uno" => "1",
        "una" => "1",
        "dos" => "2",
        "tres" => "3",
        "cuatro" => "4",
        "cinco" => "5",
        "seis" => "6",
        "siete" => "7",
        "ocho" => "8",
        "nueve" => "9",
        "diez" => "10"
    ];

    foreach ($sufijos as $palabra => $numero) {
        if (mb_strlen($textoMinuscula, "UTF-8") <= mb_strlen($palabra, "UTF-8")) {
            continue;
        }

        if (str_ends_with($textoMinuscula, $palabra)) {
            $descripcion = mb_substr(
                $textoOriginal,
                0,
                mb_strlen($textoOriginal, "UTF-8") - mb_strlen($palabra, "UTF-8"),
                "UTF-8"
            );

            $descripcion = limpiarTexto($descripcion);

            if ($descripcion !== "" && mb_strlen($descripcion, "UTF-8") >= 3) {
                return [
                    "descripcion" => normalizarDescripcion($descripcion),
                    "cantidad" => $numero
                ];
            }
        }
    }

    return null;
}

function separarCantidadDescripcion($descripcion, $cantidad = "") {
    $descripcion = limpiarDescripcionOperativa($descripcion);
    $cantidad = limpiarTexto($cantidad);

    $descripcion = str_replace(["–", "—"], "-", $descripcion);
    $descripcion = preg_replace('/\s*-\s*/u', '-', $descripcion);
    $descripcion = preg_replace('/\s+/', ' ', $descripcion);

    if ($cantidad !== "") {
        $cantidad = numeroPalabraANumero($cantidad);

        return [
            "descripcion" => normalizarDescripcion($descripcion),
            "cantidad" => $cantidad
        ];
    }

    $patronNumero = '(\\d+|un|uno|una|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|once|doce|trece|catorce|quince|dieciséis|dieciseis|diecisiete|dieciocho|diecinueve|veinte)';
    $patronUnidad = '(caja|cajas|botella|botellas|paquete|paquetes|bolsa|bolsas|bote|botes|unidad|unidades|brick|bricks|lata|latas)';

    if (preg_match('/^(.+?)-(' . $patronNumero . ')$/iu', $descripcion, $coincidencias)) {
        return [
            "descripcion" => normalizarDescripcion($coincidencias[1]),
            "cantidad" => numeroPalabraANumero($coincidencias[2])
        ];
    }

    if (preg_match('/^' . $patronNumero . '\\s+' . $patronUnidad . '\\s+de\\s+(.+)$/iu', $descripcion, $coincidencias)) {
        $numero = numeroPalabraANumero($coincidencias[1]);
        $unidad = minusculas($coincidencias[2]);

        return [
            "descripcion" => normalizarDescripcion($coincidencias[3]),
            "cantidad" => $numero . " " . $unidad
        ];
    }

    if (preg_match('/^' . $patronNumero . '\\s+' . $patronUnidad . '\\s+(.+)$/iu', $descripcion, $coincidencias)) {
        $numero = numeroPalabraANumero($coincidencias[1]);
        $unidad = minusculas($coincidencias[2]);

        return [
            "descripcion" => normalizarDescripcion($coincidencias[3]),
            "cantidad" => $numero . " " . $unidad
        ];
    }

    if (preg_match('/^' . $patronNumero . '\\s+(.+)$/iu', $descripcion, $coincidencias)) {
        $cantidadDetectada = numeroPalabraANumero($coincidencias[1]);
        $descripcionDetectada = limpiarDescripcionOperativa($coincidencias[2]);

        return [
            "descripcion" => normalizarDescripcion($descripcionDetectada),
            "cantidad" => $cantidadDetectada
        ];
    }

    if (preg_match('/^(.+?)\\s+' . $patronNumero . '$/iu', $descripcion, $coincidencias)) {
        $descripcionDetectada = limpiarDescripcionOperativa($coincidencias[1]);
        $cantidadDetectada = numeroPalabraANumero($coincidencias[2]);

        return [
            "descripcion" => normalizarDescripcion($descripcionDetectada),
            "cantidad" => $cantidadDetectada
        ];
    }

    $pegado = detectarCantidadPegadaAlFinal($descripcion);

    if ($pegado !== null) {
        return $pegado;
    }

    return [
        "descripcion" => normalizarDescripcion($descripcion),
        "cantidad" => ""
    ];
}

function extraerJsonDesdeTexto($texto) {
    $texto = trim($texto);

    $json = json_decode($texto, true);
    if (is_array($json)) {
        return $json;
    }

    $inicio = strpos($texto, "{");
    $fin = strrpos($texto, "}");

    if ($inicio !== false && $fin !== false && $fin > $inicio) {
        $fragmento = substr($texto, $inicio, $fin - $inicio + 1);
        $json = json_decode($fragmento, true);

        if (is_array($json)) {
            return $json;
        }
    }

    return null;
}

function normalizarItems($items, $tipo) {
    $resultado = [];

    if (!is_array($items)) {
        return $resultado;
    }

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $descripcion = limpiarTexto($item["descripcion"] ?? "");
        $cantidad = limpiarTexto($item["cantidad"] ?? "");

        if ($descripcion === "") {
            continue;
        }

        if (esTipoTextoLibreTotal($tipo)) {
            $descripcionLibre = limpiarTextoLibre($descripcion);

            if ($descripcionLibre !== "") {
                $resultado[] = [
                    "descripcion" => $descripcionLibre,
                    "cantidad" => ""
                ];
            }
        } elseif (esTipoLineaSimple($tipo)) {
            $resultado[] = separarCantidadSoloSiEsClara($descripcion);
        } else {
            $itemLimpio = separarCantidadDescripcion($descripcion, $cantidad);

            if ($itemLimpio["descripcion"] !== "") {
                $resultado[] = $itemLimpio;
            }
        }
    }

    return $resultado;
}

function dividirTextoEnPartes($texto) {
    $texto = str_replace(["\r\n", "\r"], "\n", $texto);
    $lineas = array_filter(array_map("trim", explode("\n", $texto)));

    $partes = [];

    foreach ($lineas as $linea) {
        $linea = trim($linea);

        if ($linea === "") {
            continue;
        }

        if (str_contains($linea, ",") || str_contains($linea, ";")) {
            $subpartes = preg_split('/[,;]+/u', $linea);

            foreach ($subpartes as $subparte) {
                $subparte = trim($subparte);

                if ($subparte !== "") {
                    $partes[] = $subparte;
                }
            }
        } else {
            $partes[] = $linea;
        }
    }

    if (count($partes) <= 1) {
        $textoPlano = str_replace([".", ";"], ",", $texto);
        $partes = preg_split('/,|\sy\s|\se\s/iu', $textoPlano);
        $partes = array_filter(array_map("trim", $partes));
    }

    return $partes;
}

function fallbackSimple($texto, $tipo) {
    if (esTipoTextoLibreTotal($tipo)) {
        return dividirTextoLibreEnItems($texto);
    }

    if (esTipoLineaSimple($tipo)) {
        return dividirLineasComoItemsSimples($texto);
    }

    $partes = dividirTextoEnPartes($texto);
    $items = [];

    foreach ($partes as $parte) {
        $parte = limpiarTexto($parte);

        if ($parte === "") {
            continue;
        }

        $item = separarCantidadDescripcion($parte);

        if ($item["descripcion"] === "") {
            continue;
        }

        $items[] = $item;
    }

    return $items;
}

$entrada = json_decode(file_get_contents("php://input"), true);

if (!is_array($entrada)) {
    responderJson([
        "ok" => false,
        "mensaje" => "No se han recibido datos válidos.",
        "items" => []
    ]);
}

$texto = limpiarTexto($entrada["texto"] ?? "");
$destino = limpiarTexto($entrada["destino"] ?? "");
$tipo = limpiarTexto($entrada["tipo"] ?? "");
$prioridad = limpiarTexto($entrada["prioridad"] ?? "");

if ($texto === "") {
    responderJson([
        "ok" => false,
        "mensaje" => "No hay texto para procesar.",
        "items" => []
    ]);
}

if (esTipoTextoLibreTotal($tipo)) {
    $itemsTextoLibre = dividirTextoLibreEnItems($texto);

    if (empty($itemsTextoLibre)) {
        responderJson([
            "ok" => false,
            "mensaje" => "No se pudieron detectar avisos o incidencias en el texto.",
            "modo" => "texto_libre",
            "items" => []
        ]);
    }

    responderJson([
        "ok" => true,
        "mensaje" => "Texto libre procesado sin recortar el contenido.",
        "modo" => "texto_libre",
        "items" => $itemsTextoLibre
    ]);
}

if (esTipoLineaSimple($tipo)) {
    $itemsLineaSimple = dividirLineasComoItemsSimples($texto);

    if (empty($itemsLineaSimple)) {
        responderJson([
            "ok" => false,
            "mensaje" => "No se pudieron detectar elementos en el texto.",
            "modo" => "linea_simple",
            "items" => []
        ]);
    }

    responderJson([
        "ok" => true,
        "mensaje" => "Lista procesada manteniendo cada línea como elemento.",
        "modo" => "linea_simple",
        "items" => $itemsLineaSimple
    ]);
}

$ollamaUrl = getenv("OLLAMA_URL") ?: "http://localhost:11434/api/generate";
$ollamaModel = getenv("OLLAMA_MODEL") ?: "llama3:latest";

$prompt = "
Eres un asistente interno para un restaurante.

Tu tarea es convertir texto dictado por voz en una lista operativa simple.

Destino de la lista: {$destino}
Tipo de lista: {$tipo}
Prioridad general: {$prioridad}

Reglas:
- Devuelve SOLO JSON válido.
- No uses markdown.
- No expliques nada.
- No inventes productos.
- Separa cada producto, tarea o elemento en un elemento distinto.
- Si el texto viene en varias líneas, trata cada línea como un posible elemento.
- Cada elemento debe tener:
  - descripcion
  - cantidad
- Si la cantidad aparece al principio, sepárala.
  Ejemplo: \"dos limas\" => descripcion: \"Limas\", cantidad: \"2\".
- Si la cantidad aparece al final, sepárala.
  Ejemplo: \"salsa Pad Thai 1\" => descripcion: \"Salsa Pad Thai\", cantidad: \"1\".
- Si la cantidad aparece pegada con guion, sepárala.
  Ejemplo: \"nestea-3\" => descripcion: \"Nestea\", cantidad: \"3\".
- Si la cantidad aparece como palabra pegada al final, sepárala cuando sea evidente.
  Ejemplo: \"fantanaranjados\" => descripcion: \"Fanta naranja\", cantidad: \"2\".
- Convierte números escritos en texto a número.
  Ejemplo: \"uno\" => \"1\", \"dos\" => \"2\", \"cuatro\" => \"4\".
- Si aparece \"cero\" o \"zero\" en Coca-Cola, forma parte del producto, no de la cantidad.
- Si no hay cantidad clara, deja cantidad como cadena vacía.
- No repitas el tipo ni la prioridad en cada elemento.

Formato obligatorio:
{
  \"items\": [
    {
      \"descripcion\": \"Limas\",
      \"cantidad\": \"2\"
    },
    {
      \"descripcion\": \"Nestea\",
      \"cantidad\": \"3\"
    }
  ]
}

Texto dictado:
{$texto}
";

$payload = [
    "model" => $ollamaModel,
    "prompt" => $prompt,
    "stream" => false,
    "format" => "json",
    "options" => [
        "temperature" => 0.1,
        "top_p" => 0.9
    ]
];

$items = [];
$mensaje = "";
$modo = "ia_local";

if (!function_exists("curl_init")) {
    $items = fallbackSimple($texto, $tipo);
    $modo = "fallback";
    $mensaje = "La extensión cURL no está activa. Se ha usado una separación básica del texto.";
} else {
    $ch = curl_init($ollamaUrl);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 45
    ]);

    $respuesta = curl_exec($ch);
    $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($respuesta === false || $codigoHttp < 200 || $codigoHttp >= 300) {
        $items = fallbackSimple($texto, $tipo);
        $modo = "fallback";
        $mensaje = "No se pudo conectar correctamente con Ollama. Se ha usado una separación básica del texto.";
    } else {
        $jsonOllama = json_decode($respuesta, true);
        $textoRespuesta = $jsonOllama["response"] ?? "";

        $jsonExtraido = extraerJsonDesdeTexto($textoRespuesta);

        if (is_array($jsonExtraido) && isset($jsonExtraido["items"])) {
            $items = normalizarItems($jsonExtraido["items"], $tipo);
            $mensaje = "Texto procesado con IA local.";
        } else {
            $items = fallbackSimple($texto, $tipo);
            $modo = "fallback";
            $mensaje = "La IA local no devolvió un JSON válido. Se ha usado una separación básica del texto.";
        }
    }
}

if (empty($items)) {
    responderJson([
        "ok" => false,
        "mensaje" => "No se pudieron detectar elementos en el texto.",
        "modo" => $modo,
        "items" => []
    ]);
}

responderJson([
    "ok" => true,
    "mensaje" => $mensaje,
    "modo" => $modo,
    "items" => $items
]);