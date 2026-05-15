<?php
// ==========================================================
// KITCHERRY STAFF TRAINING
// Panel del responsable
// Archivo: admin/panel_funciones.php
// ==========================================================

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../funciones.php';

function obtenerConexionPanel(): PDO
{
    $conexion = obtenerConexionSQLite();
    inicializarBaseDatos($conexion);

    return $conexion;
}

function verificarLoginAdmin(PDO $conexion, string $usuario, string $password): ?array
{
    $consulta = $conexion->prepare("
        SELECT *
        FROM usuarios
        WHERE usuario = :usuario
        AND activo = 1
        LIMIT 1
    ");

    $consulta->execute([
        ':usuario' => $usuario
    ]);

    $admin = $consulta->fetch();

    if (!$admin) {
        return null;
    }

    if (!password_verify($password, $admin['password_hash'])) {
        return null;
    }

    $actualizar = $conexion->prepare("
        UPDATE usuarios
        SET ultimo_acceso = :ultimo_acceso
        WHERE id = :id
    ");

    $actualizar->execute([
        ':ultimo_acceso' => date('Y-m-d H:i:s'),
        ':id' => $admin['id']
    ]);

    return $admin;
}

function obtenerTodosLosIntentos(PDO $conexion): array
{
    $consulta = $conexion->query("
        SELECT *
        FROM intentos
        ORDER BY fecha_fin DESC, id DESC
    ");

    return $consulta->fetchAll();
}

function obtenerIntentosRecientes(PDO $conexion, int $limite = 10): array
{
    $consulta = $conexion->prepare("
        SELECT *
        FROM intentos
        ORDER BY fecha_fin DESC, id DESC
        LIMIT :limite
    ");

    $consulta->bindValue(':limite', $limite, PDO::PARAM_INT);
    $consulta->execute();

    return $consulta->fetchAll();
}

function obtenerResumenTrabajadores(PDO $conexion, array $bloques): array
{
    $intentos = obtenerTodosLosIntentos($conexion);
    $trabajadores = [];

    foreach ($intentos as $intento) {
        $email = normalizarEmail((string) ($intento['email'] ?? ''));

        if ($email === '') {
            continue;
        }

        if (!isset($trabajadores[$email])) {
            $trabajadores[$email] = [
                'trabajador' => $intento['trabajador'] ?? '',
                'email' => $email,
                'total_intentos' => 0,
                'ultimo_intento' => $intento['fecha_fin'] ?? '',
                'bloques' => []
            ];
        }

        $trabajadores[$email]['total_intentos']++;

        $bloque = (string) ($intento['bloque'] ?? '');
        $porcentaje = (int) ($intento['porcentaje'] ?? 0);

        if ($bloque === '') {
            continue;
        }

        if (
            !isset($trabajadores[$email]['bloques'][$bloque]) ||
            $porcentaje > (int) $trabajadores[$email]['bloques'][$bloque]['mejor_porcentaje']
        ) {
            $trabajadores[$email]['bloques'][$bloque] = [
                'bloque' => $bloque,
                'mejor_porcentaje' => $porcentaje,
                'estado' => $porcentaje >= NOTA_MINIMA_APROBADO ? 'Aprobado' : 'Necesita repasar',
                'aprobado' => $porcentaje >= NOTA_MINIMA_APROBADO,
                'intento_id' => (int) $intento['id'],
                'fecha_fin' => $intento['fecha_fin'] ?? ''
            ];
        }
    }

    foreach ($trabajadores as $email => $datos) {
        $bloquesAprobados = 0;
        $bloquesRealizados = 0;
        $bloquesParaRepasar = 0;
        $bloquesPendientes = 0;

        foreach ($bloques as $bloque) {
            if (!isset($datos['bloques'][$bloque])) {
                $bloquesPendientes++;
                continue;
            }

            $bloquesRealizados++;

            if (!empty($datos['bloques'][$bloque]['aprobado'])) {
                $bloquesAprobados++;
            } else {
                $bloquesParaRepasar++;
            }
        }

        $totalBloques = count($bloques);
        $porcentajeGeneral = $totalBloques > 0 ? (int) round(($bloquesAprobados / $totalBloques) * 100) : 0;

        if ($totalBloques === 0) {
            $estadoGeneral = 'Sin bloques';
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

        $trabajadores[$email]['total_bloques'] = $totalBloques;
        $trabajadores[$email]['bloques_realizados'] = $bloquesRealizados;
        $trabajadores[$email]['bloques_aprobados'] = $bloquesAprobados;
        $trabajadores[$email]['bloques_pendientes'] = $bloquesPendientes;
        $trabajadores[$email]['bloques_para_repasar'] = $bloquesParaRepasar;
        $trabajadores[$email]['porcentaje_general'] = $porcentajeGeneral;
        $trabajadores[$email]['estado_general'] = $estadoGeneral;
        $trabajadores[$email]['clase_estado'] = $claseEstado;
    }

    usort($trabajadores, function ($a, $b) {
        return ($b['porcentaje_general'] <=> $a['porcentaje_general'])
            ?: strcmp($a['trabajador'], $b['trabajador']);
    });

    return array_values($trabajadores);
}

function obtenerResumenGeneralPanel(PDO $conexion, array $bloques): array
{
    $trabajadores = obtenerResumenTrabajadores($conexion, $bloques);

    $consultaIntentos = $conexion->query("
        SELECT COUNT(*) AS total
        FROM intentos
    ");

    $totalIntentos = (int) ($consultaIntentos->fetch()['total'] ?? 0);

    $formacionesCompletas = 0;
    $formacionesIncompletas = 0;
    $sinIniciar = 0;
    $necesitanRepasar = 0;

    foreach ($trabajadores as $trabajador) {
        if (($trabajador['estado_general'] ?? '') === 'Formación completa') {
            $formacionesCompletas++;
        }

        if (($trabajador['estado_general'] ?? '') === 'Formación incompleta') {
            $formacionesIncompletas++;
        }

        if (($trabajador['estado_general'] ?? '') === 'Sin iniciar') {
            $sinIniciar++;
        }

        if ((int) ($trabajador['bloques_para_repasar'] ?? 0) > 0) {
            $necesitanRepasar++;
        }
    }

    return [
        'total_trabajadores' => count($trabajadores),
        'total_intentos' => $totalIntentos,
        'total_bloques' => count($bloques),
        'formaciones_completas' => $formacionesCompletas,
        'formaciones_incompletas' => $formacionesIncompletas,
        'sin_iniciar' => $sinIniciar,
        'necesitan_repasar' => $necesitanRepasar
    ];
}

function obtenerRankingTrabajadores(PDO $conexion, array $bloques, int $limite = 8): array
{
    $trabajadores = obtenerResumenTrabajadores($conexion, $bloques);

    usort($trabajadores, function ($a, $b) {
        return ($b['porcentaje_general'] <=> $a['porcentaje_general'])
            ?: ($b['bloques_aprobados'] <=> $a['bloques_aprobados'])
            ?: strcmp($a['trabajador'], $b['trabajador']);
    });

    return array_slice($trabajadores, 0, $limite);
}

function obtenerTrabajadoresConMasPendientes(PDO $conexion, array $bloques, int $limite = 8): array
{
    $trabajadores = obtenerResumenTrabajadores($conexion, $bloques);

    usort($trabajadores, function ($a, $b) {
        return ($b['bloques_pendientes'] <=> $a['bloques_pendientes'])
            ?: ($b['bloques_para_repasar'] <=> $a['bloques_para_repasar'])
            ?: strcmp($a['trabajador'], $b['trabajador']);
    });

    return array_slice($trabajadores, 0, $limite);
}

function obtenerPreguntasMasFalladas(PDO $conexion, int $limite = 10): array
{
    $consulta = $conexion->prepare("
        SELECT
            bloque,
            pregunta_id,
            pregunta,
            COUNT(*) AS total_fallos
        FROM respuestas
        WHERE es_correcta = 0
        GROUP BY bloque, pregunta_id, pregunta
        ORDER BY total_fallos DESC
        LIMIT :limite
    ");

    $consulta->bindValue(':limite', $limite, PDO::PARAM_INT);
    $consulta->execute();

    return $consulta->fetchAll();
}

function obtenerEstadisticasPorBloque(PDO $conexion, array $bloques): array
{
    $estadisticas = [];

    foreach ($bloques as $bloque) {
        $estadisticas[$bloque] = [
            'bloque' => $bloque,
            'total_intentos' => 0,
            'promedio' => null,
            'aprobados' => 0,
            'no_aprobados' => 0,
            'ultimo_intento' => '',
            'clase' => 'pending'
        ];
    }

    $consulta = $conexion->query("
        SELECT
            bloque,
            COUNT(*) AS total_intentos,
            AVG(porcentaje) AS promedio,
            SUM(CASE WHEN porcentaje >= " . (int) NOTA_MINIMA_APROBADO . " THEN 1 ELSE 0 END) AS aprobados,
            SUM(CASE WHEN porcentaje < " . (int) NOTA_MINIMA_APROBADO . " THEN 1 ELSE 0 END) AS no_aprobados,
            MAX(fecha_fin) AS ultimo_intento
        FROM intentos
        GROUP BY bloque
        ORDER BY promedio ASC
    ");

    $filas = $consulta->fetchAll();

    foreach ($filas as $fila) {
        $bloque = (string) ($fila['bloque'] ?? '');

        if ($bloque === '') {
            continue;
        }

        $promedio = $fila['promedio'] !== null ? (int) round((float) $fila['promedio']) : null;

        $clase = 'pending';

        if ($promedio !== null) {
            $clase = $promedio >= NOTA_MINIMA_APROBADO ? 'complete' : 'incomplete';
        }

        $estadisticas[$bloque] = [
            'bloque' => $bloque,
            'total_intentos' => (int) ($fila['total_intentos'] ?? 0),
            'promedio' => $promedio,
            'aprobados' => (int) ($fila['aprobados'] ?? 0),
            'no_aprobados' => (int) ($fila['no_aprobados'] ?? 0),
            'ultimo_intento' => (string) ($fila['ultimo_intento'] ?? ''),
            'clase' => $clase
        ];
    }

    uasort($estadisticas, function ($a, $b) {
        $promedioA = $a['promedio'] ?? 999;
        $promedioB = $b['promedio'] ?? 999;

        return $promedioA <=> $promedioB;
    });

    return array_values($estadisticas);
}

function obtenerIntentosPorEmail(PDO $conexion, string $email): array
{
    $consulta = $conexion->prepare("
        SELECT *
        FROM intentos
        WHERE email = :email
        ORDER BY fecha_fin DESC, id DESC
    ");

    $consulta->execute([
        ':email' => normalizarEmail($email)
    ]);

    return $consulta->fetchAll();
}

function obtenerDatosTrabajador(PDO $conexion, string $email): ?array
{
    $consulta = $conexion->prepare("
        SELECT *
        FROM intentos
        WHERE email = :email
        ORDER BY fecha_fin DESC, id DESC
        LIMIT 1
    ");

    $consulta->execute([
        ':email' => normalizarEmail($email)
    ]);

    $trabajador = $consulta->fetch();

    return $trabajador ?: null;
}

function obtenerProgresoDetalladoTrabajador(PDO $conexion, string $email, array $bloques): array
{
    $intentos = obtenerIntentosPorEmail($conexion, $email);
    $progreso = [];

    foreach ($bloques as $bloque) {
        $progreso[$bloque] = [
            'bloque' => $bloque,
            'realizado' => false,
            'aprobado' => false,
            'mejor_porcentaje' => null,
            'estado' => 'Pendiente',
            'total_intentos' => 0,
            'mejor_intento_id' => null,
            'ultimo_intento' => ''
        ];
    }

    foreach ($intentos as $intento) {
        $bloque = (string) ($intento['bloque'] ?? '');

        if (!isset($progreso[$bloque])) {
            continue;
        }

        $porcentaje = (int) ($intento['porcentaje'] ?? 0);

        $progreso[$bloque]['realizado'] = true;
        $progreso[$bloque]['total_intentos']++;

        if ($progreso[$bloque]['ultimo_intento'] === '') {
            $progreso[$bloque]['ultimo_intento'] = $intento['fecha_fin'] ?? '';
        }

        if (
            $progreso[$bloque]['mejor_porcentaje'] === null ||
            $porcentaje > (int) $progreso[$bloque]['mejor_porcentaje']
        ) {
            $progreso[$bloque]['mejor_porcentaje'] = $porcentaje;
            $progreso[$bloque]['mejor_intento_id'] = (int) $intento['id'];
            $progreso[$bloque]['aprobado'] = $porcentaje >= NOTA_MINIMA_APROBADO;
            $progreso[$bloque]['estado'] = $porcentaje >= NOTA_MINIMA_APROBADO ? 'Aprobado' : 'Necesita repasar';
        }
    }

    return $progreso;
}

function obtenerBloquesPendientesYRepasoTrabajador(array $progreso): array
{
    $pendientes = [];
    $repasar = [];
    $aprobados = [];

    foreach ($progreso as $bloque) {
        if (empty($bloque['realizado'])) {
            $pendientes[] = $bloque;
            continue;
        }

        if (empty($bloque['aprobado'])) {
            $repasar[] = $bloque;
            continue;
        }

        $aprobados[] = $bloque;
    }

    return [
        'pendientes' => $pendientes,
        'repasar' => $repasar,
        'aprobados' => $aprobados
    ];
}

function obtenerPreguntasFalladasPorTrabajador(PDO $conexion, string $email, int $limite = 20): array
{
    $consulta = $conexion->prepare("
        SELECT
            r.bloque,
            r.pregunta_id,
            r.pregunta,
            r.respuesta_correcta,
            COUNT(*) AS total_fallos,
            MAX(r.fecha) AS ultimo_fallo
        FROM respuestas r
        INNER JOIN intentos i ON i.id = r.intento_id
        WHERE i.email = :email
        AND r.es_correcta = 0
        GROUP BY r.bloque, r.pregunta_id, r.pregunta, r.respuesta_correcta
        ORDER BY total_fallos DESC, ultimo_fallo DESC
        LIMIT :limite
    ");

    $consulta->bindValue(':email', normalizarEmail($email), PDO::PARAM_STR);
    $consulta->bindValue(':limite', $limite, PDO::PARAM_INT);
    $consulta->execute();

    return $consulta->fetchAll();
}

function obtenerEvolucionTrabajadorPorBloque(PDO $conexion, string $email): array
{
    $consulta = $conexion->prepare("
        SELECT
            bloque,
            porcentaje,
            aciertos,
            total_preguntas,
            fecha_fin,
            duracion_segundos
        FROM intentos
        WHERE email = :email
        ORDER BY fecha_fin ASC, id ASC
    ");

    $consulta->execute([
        ':email' => normalizarEmail($email)
    ]);

    $filas = $consulta->fetchAll();
    $evolucion = [];

    foreach ($filas as $fila) {
        $bloque = (string) ($fila['bloque'] ?? '');

        if ($bloque === '') {
            continue;
        }

        if (!isset($evolucion[$bloque])) {
            $evolucion[$bloque] = [];
        }

        $evolucion[$bloque][] = $fila;
    }

    return $evolucion;
}

function obtenerIntentoPorId(PDO $conexion, int $id): ?array
{
    $consulta = $conexion->prepare("
        SELECT *
        FROM intentos
        WHERE id = :id
        LIMIT 1
    ");

    $consulta->execute([
        ':id' => $id
    ]);

    $intento = $consulta->fetch();

    return $intento ?: null;
}

function obtenerRespuestasPorIntento(PDO $conexion, int $intentoId): array
{
    $consulta = $conexion->prepare("
        SELECT *
        FROM respuestas
        WHERE intento_id = :intento_id
        ORDER BY id ASC
    ");

    $consulta->execute([
        ':intento_id' => $intentoId
    ]);

    return $consulta->fetchAll();
}

function formatearFechaPanel(?string $fecha): string
{
    if (empty($fecha)) {
        return '—';
    }

    $timestamp = strtotime($fecha);

    if ($timestamp === false) {
        return $fecha;
    }

    return date('d/m/Y H:i', $timestamp);
}