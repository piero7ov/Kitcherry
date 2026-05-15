<?php
// ==========================================================
// KITCHERRY MAIL
// API específica para mantenimiento y diagnóstico IMAP.
// ==========================================================

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/services/imap_service.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (empty($_SESSION['kitcherry_mail']['account_id'])) {
        responderMantenimientoError('Sesión no iniciada', 401);
    }

    $pdo = obtenerConexion();
    $accountId = (int) $_SESSION['kitcherry_mail']['account_id'];

    $entrada = json_decode(file_get_contents('php://input'), true);

    if (!is_array($entrada)) {
        $entrada = [];
    }

    $accion = $entrada['action'] ?? '';

    switch ($accion) {
        case 'maintenance_stats':
            obtenerEstadisticasMantenimiento($pdo, $accountId);
            break;

        case 'clean_local_only':
            limpiarCorreosSoloLocales($pdo, $accountId);
            break;

        case 'empty_local_trash':
            vaciarPapeleraLocal($pdo, $accountId);
            break;

        case 'rebuild_from_server':
            reconstruirDesdeServidor($pdo, $accountId);
            break;

        default:
            responderMantenimientoError('Acción no válida');
            break;
    }
} catch (Throwable $error) {
    responderMantenimientoError('Error interno: ' . $error->getMessage(), 500);
}

/**
 * Devuelve estadísticas locales y diagnóstico IMAP.
 */
function obtenerEstadisticasMantenimiento(PDO $pdo, int $accountId): void
{
    responderMantenimientoOk('Estadísticas cargadas', $pdo, $accountId);
}

/**
 * Elimina correos que solo existen en SQLite.
 */
function limpiarCorreosSoloLocales(PDO $pdo, int $accountId): void
{
    $ids = obtenerIdsCorreosSoloLocales($pdo, $accountId);
    $total = count($ids);

    eliminarCorreosLocalesPorIds($pdo, $accountId, $ids);

    responderMantenimientoOk('Correos solo locales eliminados: ' . $total, $pdo, $accountId);
}

/**
 * Vacía solo la papelera local de SQLite.
 * No elimina correos reales del servidor.
 */
function vaciarPapeleraLocal(PDO $pdo, int $accountId): void
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM emails
        WHERE account_id = :account_id
          AND folder = 'trash'
    ");

    $stmt->execute([
        ':account_id' => $accountId
    ]);

    $ids = array_map('intval', array_column($stmt->fetchAll(), 'id'));
    $total = count($ids);

    eliminarCorreosLocalesPorIds($pdo, $accountId, $ids);

    responderMantenimientoOk(
        'Papelera local vaciada: ' . $total . '. Si esos mensajes siguen en el servidor, volverán al reconstruir o sincronizar.',
        $pdo,
        $accountId
    );
}

/**
 * Borra todos los mensajes locales y vuelve a sincronizar desde IMAP.
 */
function reconstruirDesdeServidor(PDO $pdo, int $accountId): void
{
    $stmtIds = $pdo->prepare("
        SELECT id
        FROM emails
        WHERE account_id = :account_id
    ");

    $stmtIds->execute([
        ':account_id' => $accountId
    ]);

    $ids = array_map('intval', array_column($stmtIds->fetchAll(), 'id'));
    $totalEliminados = count($ids);

    eliminarCorreosLocalesPorIds($pdo, $accountId, $ids);

    $resultado = sincronizarInboxImap($pdo, $_SESSION['kitcherry_mail'], 50);

    responderMantenimientoOk(
        'Base reconstruida desde IMAP. Eliminados locales: '
        . $totalEliminados
        . ' | Nuevos: '
        . $resultado['insertados']
        . ' | Actualizados: '
        . $resultado['actualizados'],
        $pdo,
        $accountId
    );
}

/**
 * Calcula estadísticas locales en SQLite.
 */
function calcularEstadisticasMantenimiento(PDO $pdo, int $accountId): array
{
    $stats = [
        'total' => 0,
        'inbox' => 0,
        'sent' => 0,
        'drafts' => 0,
        'archived' => 0,
        'trash' => 0,
        'local_only' => 0,
        'synced' => 0
    ];

    $stmt = $pdo->prepare("
        SELECT
            folder,
            remote_folder,
            imap_uid,
            COUNT(*) AS total
        FROM emails
        WHERE account_id = :account_id
        GROUP BY folder, remote_folder, imap_uid
    ");

    $stmt->execute([
        ':account_id' => $accountId
    ]);

    $filas = $stmt->fetchAll();

    foreach ($filas as $fila) {
        $total = (int) $fila['total'];
        $folder = (string) $fila['folder'];
        $remoteFolder = (string) ($fila['remote_folder'] ?? '');
        $imapUid = (string) ($fila['imap_uid'] ?? '');

        $stats['total'] += $total;

        if (isset($stats[$folder])) {
            $stats[$folder] += $total;
        }

        if ($remoteFolder === 'LOCAL' || $imapUid === '') {
            $stats['local_only'] += $total;
        } else {
            $stats['synced'] += $total;
        }
    }

    return $stats;
}

/**
 * Diagnóstico de carpetas IMAP detectadas.
 */
function diagnosticarCarpetasImap(array $sesion): array
{
    $diagnostico = [
        'ok' => true,
        'nota' => 'Los valores IMAP son mensajes individuales. Gmail puede mostrar menos si agrupa por conversaciones.',
        'carpetas' => []
    ];

    try {
        validarExtensionImap();

        $carpetaEntrada = 'INBOX';
        $carpetaPapelera = detectarCarpetaDestinoImap($sesion, 'trash');
        $carpetaEnviados = detectarCarpetaEspecialImap($sesion, 'sent');

        $carpetas = [
            [
                'clave' => 'inbox',
                'nombre' => 'Entrada',
                'folder' => $carpetaEntrada
            ],
            [
                'clave' => 'trash',
                'nombre' => 'Papelera',
                'folder' => $carpetaPapelera
            ],
            [
                'clave' => 'sent',
                'nombre' => 'Enviados',
                'folder' => $carpetaEnviados
            ]
        ];

        foreach ($carpetas as $carpeta) {
            if ($carpeta['folder'] === '') {
                $diagnostico['carpetas'][] = [
                    'clave' => $carpeta['clave'],
                    'nombre' => $carpeta['nombre'],
                    'folder' => 'No detectada',
                    'mensajes' => 0,
                    'estado' => 'No detectada'
                ];

                continue;
            }

            try {
                $totalMensajes = contarMensajesImap($sesion, $carpeta['folder']);

                $diagnostico['carpetas'][] = [
                    'clave' => $carpeta['clave'],
                    'nombre' => $carpeta['nombre'],
                    'folder' => $carpeta['folder'],
                    'mensajes' => $totalMensajes,
                    'estado' => 'Detectada'
                ];
            } catch (Throwable $errorCarpeta) {
                $diagnostico['carpetas'][] = [
                    'clave' => $carpeta['clave'],
                    'nombre' => $carpeta['nombre'],
                    'folder' => $carpeta['folder'],
                    'mensajes' => 0,
                    'estado' => 'Error: ' . $errorCarpeta->getMessage()
                ];
            }
        }
    } catch (Throwable $error) {
        $diagnostico['ok'] = false;
        $diagnostico['nota'] = 'No se pudo leer el diagnóstico IMAP: ' . $error->getMessage();
        $diagnostico['carpetas'] = [];
    }

    return $diagnostico;
}

/**
 * Cuenta mensajes reales que devuelve una carpeta IMAP.
 */
function contarMensajesImap(array $sesion, string $folder): int
{
    $imap = abrirBuzonImap($sesion, $folder, true);

    try {
        return (int) imap_num_msg($imap);
    } finally {
        if (is_resource($imap) || $imap instanceof IMAP\Connection) {
            imap_close($imap);
        }
    }
}

/**
 * Obtiene IDs de correos que no vienen del servidor.
 */
function obtenerIdsCorreosSoloLocales(PDO $pdo, int $accountId): array
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM emails
        WHERE account_id = :account_id
          AND (
                remote_folder = 'LOCAL'
                OR imap_uid IS NULL
                OR imap_uid = ''
          )
    ");

    $stmt->execute([
        ':account_id' => $accountId
    ]);

    return array_map('intval', array_column($stmt->fetchAll(), 'id'));
}

/**
 * Elimina correos locales y sus relaciones.
 */
function eliminarCorreosLocalesPorIds(PDO $pdo, int $accountId, array $ids): void
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

    if (!$ids) {
        return;
    }

    $pdo->beginTransaction();

    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmtVerificar = $pdo->prepare("
            SELECT id
            FROM emails
            WHERE account_id = ?
              AND id IN ($placeholders)
        ");

        $stmtVerificar->execute(array_merge([$accountId], $ids));

        $idsVerificados = array_map('intval', array_column($stmtVerificar->fetchAll(), 'id'));

        if (!$idsVerificados) {
            $pdo->commit();
            return;
        }

        $placeholdersVerificados = implode(',', array_fill(0, count($idsVerificados), '?'));

        $stmtNotas = $pdo->prepare("
            DELETE FROM notes
            WHERE email_id IN ($placeholdersVerificados)
        ");

        $stmtNotas->execute($idsVerificados);

        $stmtHistorial = $pdo->prepare("
            DELETE FROM history
            WHERE email_id IN ($placeholdersVerificados)
        ");

        $stmtHistorial->execute($idsVerificados);

        $stmtEmails = $pdo->prepare("
            DELETE FROM emails
            WHERE account_id = ?
              AND id IN ($placeholdersVerificados)
        ");

        $stmtEmails->execute(array_merge([$accountId], $idsVerificados));

        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

/**
 * Respuesta correcta de mantenimiento.
 */
function responderMantenimientoOk(string $mensaje, PDO $pdo, int $accountId): void
{
    echo json_encode([
        'ok' => true,
        'message' => $mensaje,
        'stats' => calcularEstadisticasMantenimiento($pdo, $accountId),
        'remote' => diagnosticarCarpetasImap($_SESSION['kitcherry_mail'])
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

/**
 * Respuesta de error.
 */
function responderMantenimientoError(string $mensaje, int $codigo = 400): void
{
    http_response_code($codigo);

    echo json_encode([
        'ok' => false,
        'message' => $mensaje
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}