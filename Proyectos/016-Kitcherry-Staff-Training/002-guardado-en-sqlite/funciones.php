<?php
// ==========================================================
// KITCHERRY STAFF TRAINING
// Archivo: funciones.php
// ==========================================================

declare(strict_types=1);

function limpiarTexto(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function descargarCsvDesdeUrl(string $url): string
{
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

    $csv = str_replace(["\r\n", "\r"], "\n", $csv);
    $csv = trim($csv);

    if ($csv === '') {
        return [];
    }

    $lineas = explode("\n", $csv);

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

function normalizarEmail(string $email): string
{
    return strtolower(trim($email));
}

function formatearDuracion(int $segundos): string
{
    if ($segundos < 60) {
        return $segundos . ' s';
    }

    $minutos = intdiv($segundos, 60);
    $restoSegundos = $segundos % 60;

    if ($minutos < 60) {
        return $minutos . ' min ' . $restoSegundos . ' s';
    }

    $horas = intdiv($minutos, 60);
    $restoMinutos = $minutos % 60;

    return $horas . ' h ' . $restoMinutos . ' min';
}

function obtenerConexionSQLite(): PDO
{
    $directorioBaseDatos = dirname(DB_PATH);

    if (!is_dir($directorioBaseDatos)) {
        mkdir($directorioBaseDatos, 0775, true);
    }

    $conexion = new PDO('sqlite:' . DB_PATH);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    return $conexion;
}

function columnaExisteSQLite(PDO $conexion, string $tabla, string $columna): bool
{
    $consulta = $conexion->query("PRAGMA table_info($tabla)");
    $columnas = $consulta->fetchAll();

    foreach ($columnas as $infoColumna) {
        if (($infoColumna['name'] ?? '') === $columna) {
            return true;
        }
    }

    return false;
}

function inicializarBaseDatos(PDO $conexion): void
{
    $conexion->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            rol TEXT NOT NULL,
            activo INTEGER NOT NULL DEFAULT 1,
            creado_en TEXT NOT NULL,
            ultimo_acceso TEXT
        )
    ");

    $conexion->exec("
        CREATE TABLE IF NOT EXISTS intentos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            trabajador TEXT NOT NULL,
            email TEXT NOT NULL,
            bloque TEXT NOT NULL,
            total_preguntas INTEGER NOT NULL,
            aciertos INTEGER NOT NULL,
            fallos INTEGER NOT NULL,
            porcentaje INTEGER NOT NULL,
            estado TEXT NOT NULL,
            fecha_inicio TEXT NOT NULL,
            fecha_fin TEXT NOT NULL,
            duracion_segundos INTEGER NOT NULL DEFAULT 0
        )
    ");

    $conexion->exec("
        CREATE TABLE IF NOT EXISTS respuestas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            intento_id INTEGER NOT NULL,
            pregunta_id TEXT NOT NULL,
            bloque TEXT NOT NULL,
            pregunta TEXT NOT NULL,
            respuesta_usuario TEXT NOT NULL,
            respuesta_correcta TEXT NOT NULL,
            es_correcta INTEGER NOT NULL,
            explicacion TEXT,
            fecha TEXT NOT NULL,
            FOREIGN KEY (intento_id) REFERENCES intentos(id)
        )
    ");

    if (!columnaExisteSQLite($conexion, 'intentos', 'email')) {
        $conexion->exec("ALTER TABLE intentos ADD COLUMN email TEXT NOT NULL DEFAULT ''");
    }

    if (!columnaExisteSQLite($conexion, 'intentos', 'fecha_inicio')) {
        $conexion->exec("ALTER TABLE intentos ADD COLUMN fecha_inicio TEXT NOT NULL DEFAULT ''");
    }

    if (!columnaExisteSQLite($conexion, 'intentos', 'fecha_fin')) {
        $conexion->exec("ALTER TABLE intentos ADD COLUMN fecha_fin TEXT NOT NULL DEFAULT ''");
    }

    if (!columnaExisteSQLite($conexion, 'intentos', 'duracion_segundos')) {
        $conexion->exec("ALTER TABLE intentos ADD COLUMN duracion_segundos INTEGER NOT NULL DEFAULT 0");
    }

    if (!columnaExisteSQLite($conexion, 'respuestas', 'bloque')) {
        $conexion->exec("ALTER TABLE respuestas ADD COLUMN bloque TEXT NOT NULL DEFAULT ''");
    }

    if (!columnaExisteSQLite($conexion, 'respuestas', 'fecha')) {
        $conexion->exec("ALTER TABLE respuestas ADD COLUMN fecha TEXT NOT NULL DEFAULT ''");
    }

    crearUsuarioAdminInicial($conexion);
}

function crearUsuarioAdminInicial(PDO $conexion): void
{
    $consulta = $conexion->prepare("
        SELECT COUNT(*) AS total
        FROM usuarios
        WHERE usuario = :usuario
    ");

    $consulta->execute([
        ':usuario' => ADMIN_USUARIO
    ]);

    $resultado = $consulta->fetch();
    $total = (int) ($resultado['total'] ?? 0);

    if ($total > 0) {
        return;
    }

    $insertar = $conexion->prepare("
        INSERT INTO usuarios (
            usuario,
            password_hash,
            rol,
            activo,
            creado_en,
            ultimo_acceso
        ) VALUES (
            :usuario,
            :password_hash,
            :rol,
            :activo,
            :creado_en,
            NULL
        )
    ");

    $insertar->execute([
        ':usuario' => ADMIN_USUARIO,
        ':password_hash' => password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT),
        ':rol' => ADMIN_ROL,
        ':activo' => 1,
        ':creado_en' => date('Y-m-d H:i:s')
    ]);
}

function guardarIntentoSQLite(
    PDO $conexion,
    string $trabajador,
    string $email,
    string $bloque,
    int $totalPreguntas,
    int $aciertos,
    int $fallos,
    int $porcentaje,
    string $estado,
    string $fechaInicio,
    string $fechaFin,
    int $duracionSegundos
): int {
    $consulta = $conexion->prepare("
        INSERT INTO intentos (
            trabajador,
            email,
            bloque,
            total_preguntas,
            aciertos,
            fallos,
            porcentaje,
            estado,
            fecha_inicio,
            fecha_fin,
            duracion_segundos
        ) VALUES (
            :trabajador,
            :email,
            :bloque,
            :total_preguntas,
            :aciertos,
            :fallos,
            :porcentaje,
            :estado,
            :fecha_inicio,
            :fecha_fin,
            :duracion_segundos
        )
    ");

    $consulta->execute([
        ':trabajador' => $trabajador,
        ':email' => $email,
        ':bloque' => $bloque,
        ':total_preguntas' => $totalPreguntas,
        ':aciertos' => $aciertos,
        ':fallos' => $fallos,
        ':porcentaje' => $porcentaje,
        ':estado' => $estado,
        ':fecha_inicio' => $fechaInicio,
        ':fecha_fin' => $fechaFin,
        ':duracion_segundos' => $duracionSegundos
    ]);

    return (int) $conexion->lastInsertId();
}

function guardarRespuestasIntentoSQLite(
    PDO $conexion,
    int $intentoId,
    string $bloque,
    array $detalleResultados,
    string $fecha
): void {
    $consulta = $conexion->prepare("
        INSERT INTO respuestas (
            intento_id,
            pregunta_id,
            bloque,
            pregunta,
            respuesta_usuario,
            respuesta_correcta,
            es_correcta,
            explicacion,
            fecha
        ) VALUES (
            :intento_id,
            :pregunta_id,
            :bloque,
            :pregunta,
            :respuesta_usuario,
            :respuesta_correcta,
            :es_correcta,
            :explicacion,
            :fecha
        )
    ");

    foreach ($detalleResultados as $detalle) {
        $pregunta = $detalle['pregunta'];

        $consulta->execute([
            ':intento_id' => $intentoId,
            ':pregunta_id' => $pregunta['id'] ?? '',
            ':bloque' => $bloque,
            ':pregunta' => $pregunta['pregunta'] ?? '',
            ':respuesta_usuario' => $detalle['respuesta_usuario'] !== '' ? $detalle['respuesta_usuario'] : 'Sin responder',
            ':respuesta_correcta' => $detalle['respuesta_correcta'],
            ':es_correcta' => $detalle['es_correcta'] ? 1 : 0,
            ':explicacion' => $pregunta['explicacion'] ?? '',
            ':fecha' => $fecha
        ]);
    }
}

function obtenerMejoresIntentosPorEmail(PDO $conexion, string $email): array
{
    $email = normalizarEmail($email);

    $consulta = $conexion->prepare("
        SELECT
            bloque,
            MAX(porcentaje) AS mejor_porcentaje,
            COUNT(*) AS total_intentos,
            MAX(fecha_fin) AS ultimo_intento
        FROM intentos
        WHERE email = :email
        GROUP BY bloque
        ORDER BY bloque ASC
    ");

    $consulta->execute([
        ':email' => $email
    ]);

    $filas = $consulta->fetchAll();

    $progreso = [];

    foreach ($filas as $fila) {
        $bloque = (string) ($fila['bloque'] ?? '');
        $mejorPorcentaje = (int) ($fila['mejor_porcentaje'] ?? 0);
        $totalIntentos = (int) ($fila['total_intentos'] ?? 0);
        $ultimoIntento = (string) ($fila['ultimo_intento'] ?? '');

        if ($bloque === '') {
            continue;
        }

        $progreso[$bloque] = [
            'bloque' => $bloque,
            'mejor_porcentaje' => $mejorPorcentaje,
            'total_intentos' => $totalIntentos,
            'ultimo_intento' => $ultimoIntento,
            'aprobado' => $mejorPorcentaje >= NOTA_MINIMA_APROBADO,
            'estado' => $mejorPorcentaje >= NOTA_MINIMA_APROBADO ? 'Aprobado' : 'Necesita repasar'
        ];
    }

    return $progreso;
}

function obtenerResumenProgreso(array $bloques, array $progresoPorBloque): array
{
    $totalBloques = count($bloques);
    $bloquesAprobados = 0;
    $bloquesRealizados = 0;
    $bloquesPendientes = 0;
    $bloquesParaRepasar = 0;

    foreach ($bloques as $bloque) {
        $progreso = $progresoPorBloque[$bloque] ?? null;

        if ($progreso === null) {
            $bloquesPendientes++;
            continue;
        }

        $bloquesRealizados++;

        if (!empty($progreso['aprobado'])) {
            $bloquesAprobados++;
        } else {
            $bloquesParaRepasar++;
        }
    }

    $porcentajeGeneral = $totalBloques > 0 ? (int) round(($bloquesAprobados / $totalBloques) * 100) : 0;

    if ($totalBloques === 0) {
        $estadoGeneral = 'Sin bloques disponibles';
        $claseEstado = 'pending';
    } elseif ($bloquesAprobados === $totalBloques) {
        $estadoGeneral = 'Formación completa';
        $claseEstado = 'complete';
    } elseif ($bloquesRealizados === 0) {
        $estadoGeneral = 'Sin iniciar';
        $claseEstado = 'pending';
    } else {
        $estadoGeneral = 'Formación incompleta';
        $claseEstado = 'incomplete';
    }

    return [
        'total_bloques' => $totalBloques,
        'bloques_aprobados' => $bloquesAprobados,
        'bloques_realizados' => $bloquesRealizados,
        'bloques_pendientes' => $bloquesPendientes,
        'bloques_para_repasar' => $bloquesParaRepasar,
        'porcentaje_general' => $porcentajeGeneral,
        'estado_general' => $estadoGeneral,
        'clase_estado' => $claseEstado
    ];
}

function obtenerSiguienteBloquePendiente(array $bloques, array $progresoPorBloque, string $bloqueActual = ''): ?string
{
    if (empty($bloques)) {
        return null;
    }

    $indiceActual = array_search($bloqueActual, $bloques, true);

    if ($indiceActual !== false) {
        for ($i = $indiceActual + 1; $i < count($bloques); $i++) {
            $bloque = $bloques[$i];
            $progreso = $progresoPorBloque[$bloque] ?? null;

            if ($progreso === null || empty($progreso['aprobado'])) {
                return $bloque;
            }
        }
    }

    foreach ($bloques as $bloque) {
        if ($bloque === $bloqueActual) {
            continue;
        }

        $progreso = $progresoPorBloque[$bloque] ?? null;

        if ($progreso === null || empty($progreso['aprobado'])) {
            return $bloque;
        }
    }

    return null;
}