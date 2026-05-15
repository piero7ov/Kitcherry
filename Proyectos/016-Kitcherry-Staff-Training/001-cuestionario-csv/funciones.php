<?php
// ==========================================================
// KITCHERRY STAFF TRAINING
// Versión 001 - Cuestionario desde CSV publicado en Drive
// Archivo: funciones.php
// ==========================================================

declare(strict_types=1);

function limpiarTexto(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function descargarCsvDesdeUrl(string $url): string
{
    $contenido = '';

    $contexto = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: KitcherryStaffTraining/1.0\r\n",
            'timeout' => 15
        ]
    ]);

    $contenido = @file_get_contents($url, false, $contexto);

    if ($contenido !== false && trim($contenido) !== '') {
        return $contenido;
    }

    if (function_exists('curl_init')) {
        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'KitcherryStaffTraining/1.0'
        ]);

        $respuesta = curl_exec($curl);
        $error = curl_error($curl);

        curl_close($curl);

        if ($respuesta !== false && trim((string) $respuesta) !== '') {
            return (string) $respuesta;
        }

        throw new RuntimeException('No se pudo descargar el CSV. Error cURL: ' . $error);
    }

    throw new RuntimeException('No se pudo descargar el CSV. Revisa que allow_url_fopen esté activado o que cURL esté disponible.');
}

function detectarDelimitador(string $primeraLinea): string
{
    $columnasPuntoComa = count(str_getcsv($primeraLinea, ';'));
    $columnasComa = count(str_getcsv($primeraLinea, ','));

    return $columnasPuntoComa >= $columnasComa ? ';' : ',';
}

function cargarPreguntasDesdeCsvUrl(string $url): array
{
    $csv = descargarCsvDesdeUrl($url);

    // Normaliza saltos de línea.
    $csv = str_replace(["\r\n", "\r"], "\n", $csv);

    $lineas = explode("\n", trim($csv));

    if (empty($lineas[0])) {
        return [];
    }

    $delimitador = detectarDelimitador($lineas[0]);

    $memoria = fopen('php://temp', 'r+');

    if ($memoria === false) {
        return [];
    }

    fwrite($memoria, $csv);
    rewind($memoria);

    $cabeceras = fgetcsv($memoria, 0, $delimitador);

    if ($cabeceras === false) {
        fclose($memoria);
        return [];
    }

    // Elimina BOM si viene desde Excel, Drive o Google Sheets.
    $cabeceras[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cabeceras[0]);

    $cabeceras = array_map(function ($cabecera) {
        return trim((string) $cabecera);
    }, $cabeceras);

    $preguntas = [];

    while (($fila = fgetcsv($memoria, 0, $delimitador)) !== false) {
        if (count($fila) !== count($cabeceras)) {
            continue;
        }

        $pregunta = array_combine($cabeceras, $fila);

        if ($pregunta === false) {
            continue;
        }

        $pregunta = array_map(function ($valor) {
            return trim((string) $valor);
        }, $pregunta);

        if (
            empty($pregunta['id']) ||
            empty($pregunta['bloque']) ||
            empty($pregunta['pregunta']) ||
            empty($pregunta['correcta'])
        ) {
            continue;
        }

        $pregunta['correcta'] = strtoupper($pregunta['correcta']);

        $preguntas[] = $pregunta;
    }

    fclose($memoria);

    return $preguntas;
}

function obtenerBloques(array $preguntas): array
{
    $bloques = [];

    foreach ($preguntas as $pregunta) {
        $bloque = trim($pregunta['bloque'] ?? '');

        if ($bloque !== '' && !isset($bloques[$bloque])) {
            $bloques[$bloque] = $bloque;
        }
    }

    return array_values($bloques);
}

function obtenerPreguntasPorBloque(array $preguntas, string $bloqueSeleccionado): array
{
    return array_values(array_filter($preguntas, function ($pregunta) use ($bloqueSeleccionado) {
        return ($pregunta['bloque'] ?? '') === $bloqueSeleccionado;
    }));
}

function obtenerTextoRespuesta(array $pregunta, string $letra): string
{
    $mapa = [
        'A' => 'opcion_a',
        'B' => 'opcion_b',
        'C' => 'opcion_c',
        'D' => 'opcion_d'
    ];

    if (!isset($mapa[$letra])) {
        return 'Sin responder';
    }

    return $pregunta[$mapa[$letra]] ?? 'Sin respuesta';
}

function obtenerMensajeResultado(int $porcentaje): string
{
    if ($porcentaje >= NOTA_MINIMA_APROBADO) {
        return 'Buen resultado. El bloque está superado.';
    }

    return 'Resultado mejorable. Conviene repasar este bloque antes del servicio.';
}