<?php
// ==========================================================
// KITCHERRY MAIL
// API interna para trabajar contra SQLite, IMAP, SMTP e hilos.
// Incluye borradores editables.
// ==========================================================

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/services/imap_service.php';
require_once __DIR__ . '/services/smtp_service.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (empty($_SESSION['kitcherry_mail']['account_id'])) {
        responderError('Sesión no iniciada', 401);
    }

    $pdo = obtenerConexion();
    $accountId = (int) $_SESSION['kitcherry_mail']['account_id'];

    $entrada = json_decode(file_get_contents('php://input'), true);

    if (!is_array($entrada)) {
        $entrada = [];
    }

    $accion = $entrada['action'] ?? '';

    switch ($accion) {
        case 'sync':
            sincronizarCorreoReal($pdo, $accountId);
            break;

        case 'get_thread':
            obtenerHiloCorreo($pdo, $accountId, $entrada);
            break;

        case 'update_read':
            actualizarLectura($pdo, $accountId, $entrada);
            break;

        case 'move_folder':
            moverCarpeta($pdo, $accountId, $entrada);
            break;

        case 'restore_inbox':
            restaurarCorreo($pdo, $accountId, $entrada);
            break;

        case 'update_status':
            actualizarEstado($pdo, $accountId, $entrada);
            break;

        case 'add_note':
            agregarNota($pdo, $accountId, $entrada);
            break;

        case 'save_draft':
            guardarBorrador($pdo, $accountId, $entrada);
            break;

        case 'update_draft':
            actualizarBorrador($pdo, $accountId, $entrada);
            break;

        case 'save_sent_local':
            guardarEnviadosLocal($pdo, $accountId, $entrada);
            break;

        case 'send_smtp':
            enviarCorreoReal($pdo, $accountId, $entrada);
            break;

        default:
            responderError('Acción no válida');
            break;
    }
} catch (Throwable $error) {
    responderError('Error interno: ' . $error->getMessage(), 500);
}

/**
 * Sincroniza correos reales desde IMAP.
 */
function sincronizarCorreoReal(PDO $pdo, int $accountId): void
{
    $resultado = sincronizarInboxImap($pdo, $_SESSION['kitcherry_mail'], 30);

    $mensaje = 'Sincronización completada. Nuevos: '
        . $resultado['insertados']
        . ' | Actualizados: '
        . $resultado['actualizados'];

    responderOk($pdo, $accountId, $mensaje);
}

/**
 * Devuelve los mensajes relacionados con un correo.
 */
function obtenerHiloCorreo(PDO $pdo, int $accountId, array $entrada): void
{
    $id = obtenerIdCorreo($entrada);

    $stmt = $pdo->prepare("
        SELECT
            id,
            folder,
            sender_name,
            sender_email,
            recipient_email,
            subject,
            summary,
            body_html,
            email_date,
            display_date,
            status,
            type,
            message_id,
            in_reply_to,
            references_header,
            thread_key
        FROM emails
        WHERE account_id = :account_id
        ORDER BY datetime(email_date) ASC, id ASC
    ");

    $stmt->execute([
        ':account_id' => $accountId
    ]);

    $correos = $stmt->fetchAll();

    $correoBase = null;

    foreach ($correos as $correo) {
        if ((int) $correo['id'] === $id) {
            $correoBase = $correo;
            break;
        }
    }

    if (!$correoBase) {
        responderError('Correo no encontrado');
    }

    $idsRelacionados = [];
    $claves = extraerClavesConversacion($correoBase);

    $idsRelacionados[(int) $correoBase['id']] = true;

    for ($pasada = 0; $pasada < 4; $pasada++) {
        foreach ($correos as $correo) {
            $correoId = (int) $correo['id'];

            if (isset($idsRelacionados[$correoId])) {
                $claves = array_values(array_unique(array_merge(
                    $claves,
                    extraerClavesConversacion($correo)
                )));

                continue;
            }

            if (correoPerteneceAlHilo($correoBase, $correo, $claves)) {
                $idsRelacionados[$correoId] = true;

                $claves = array_values(array_unique(array_merge(
                    $claves,
                    extraerClavesConversacion($correo)
                )));
            }
        }
    }

    $hilo = [];

    foreach ($correos as $correo) {
        $correoId = (int) $correo['id'];

        if (!isset($idsRelacionados[$correoId])) {
            continue;
        }

        $parent = buscarPadreConversacion($correos, $correo);
        $esEnviado = in_array((string) $correo['folder'], ['sent', 'drafts'], true)
            || (string) $correo['type'] === 'interno';

        $hilo[] = [
            'id' => $correoId,
            'actual' => $correoId === $id,
            'direccion' => $esEnviado ? 'enviado' : 'recibido',
            'carpeta' => $correo['folder'],
            'remitente' => $correo['sender_name'],
            'email' => $correo['sender_email'],
            'destinatario' => $correo['recipient_email'],
            'asunto' => $correo['subject'],
            'resumen' => $correo['summary'],
            'cuerpo' => $correo['body_html'],
            'fecha' => $correo['display_date'],
            'estado' => $correo['status'],
            'messageId' => $correo['message_id'],
            'inReplyTo' => $correo['in_reply_to'],
            'referencesHeader' => $correo['references_header'],
            'threadKey' => $correo['thread_key'],
            'respondeA' => $parent
        ];
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Hilo cargado',
        'hilo' => $hilo,
        'total' => count($hilo)
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

/**
 * Extrae claves de relación para una conversación.
 */
function extraerClavesConversacion(array $correo): array
{
    $claves = [];

    foreach (['message_id', 'in_reply_to', 'thread_key'] as $campo) {
        $valor = normalizarIdConversacion((string) ($correo[$campo] ?? ''));

        if ($valor !== '') {
            $claves[] = $valor;
        }
    }

    $referencias = extraerIdsDesdeReferences((string) ($correo['references_header'] ?? ''));

    foreach ($referencias as $referencia) {
        $claves[] = $referencia;
    }

    return array_values(array_unique($claves));
}

/**
 * Determina si un correo pertenece al hilo.
 */
function correoPerteneceAlHilo(array $correoBase, array $correo, array $claves): bool
{
    if ((int) $correoBase['id'] === (int) $correo['id']) {
        return true;
    }

    $threadBase = normalizarIdConversacion((string) ($correoBase['thread_key'] ?? ''));
    $threadCorreo = normalizarIdConversacion((string) ($correo['thread_key'] ?? ''));

    if ($threadBase !== '' && $threadCorreo !== '' && $threadBase === $threadCorreo) {
        return true;
    }

    $clavesCorreo = extraerClavesConversacion($correo);

    foreach ($clavesCorreo as $clave) {
        if ($clave !== '' && in_array($clave, $claves, true)) {
            return true;
        }
    }

    return false;
}

/**
 * Busca el correo padre al que responde un mensaje.
 */
function buscarPadreConversacion(array $correos, array $correo): ?array
{
    $inReplyTo = normalizarIdConversacion((string) ($correo['in_reply_to'] ?? ''));

    if ($inReplyTo === '') {
        return null;
    }

    foreach ($correos as $posiblePadre) {
        $messageIdPadre = normalizarIdConversacion((string) ($posiblePadre['message_id'] ?? ''));

        if ($messageIdPadre !== '' && $messageIdPadre === $inReplyTo) {
            return [
                'id' => (int) $posiblePadre['id'],
                'asunto' => $posiblePadre['subject'],
                'remitente' => $posiblePadre['sender_name'],
                'fecha' => $posiblePadre['display_date']
            ];
        }
    }

    return null;
}

/**
 * Extrae Message-ID desde References.
 */
function extraerIdsDesdeReferences(string $references): array
{
    $references = trim($references);

    if ($references === '') {
        return [];
    }

    $ids = [];

    if (preg_match_all('/<([^>]+)>/', $references, $coincidencias)) {
        foreach ($coincidencias[1] as $id) {
            $normalizado = normalizarIdConversacion($id);

            if ($normalizado !== '') {
                $ids[] = $normalizado;
            }
        }

        return array_values(array_unique($ids));
    }

    $partes = preg_split('/\s+/', $references);

    foreach ($partes as $parte) {
        $normalizado = normalizarIdConversacion($parte);

        if ($normalizado !== '') {
            $ids[] = $normalizado;
        }
    }

    return array_values(array_unique($ids));
}

/**
 * Normaliza identificadores de correo.
 */
function normalizarIdConversacion(string $id): string
{
    $id = trim($id);
    $id = trim($id, '<>');
    $id = strtolower($id);

    return $id;
}

/**
 * Envía un correo real por SMTP y lo guarda en Enviados local.
 * Si viene de un borrador, elimina el borrador local después del envío.
 */
function enviarCorreoReal(PDO $pdo, int $accountId, array $entrada): void
{
    $sourceId = (int) ($entrada['source_id'] ?? 0);
    $draftId = (int) ($entrada['draft_id'] ?? 0);
    $modo = trim((string) ($entrada['mode'] ?? 'nuevo'));

    $inReplyToLocal = '';
    $referencesLocal = '';
    $threadKeyLocal = '';

    $inReplyToHeader = '';
    $referencesHeader = '';

    if ($draftId > 0) {
        validarBorradorExiste($pdo, $accountId, $draftId);
    }

    if ($sourceId > 0 && $modo !== 'borrador') {
        $correoOrigen = obtenerCorreoParaRespuesta($pdo, $accountId, $sourceId);

        $inReplyToLocal = (string) ($correoOrigen['message_id'] ?? '');
        $referencesLocal = trim(
            ((string) ($correoOrigen['references_header'] ?? '')) . ' ' . $inReplyToLocal
        );

        $threadKeyLocal = (string) ($correoOrigen['thread_key'] ?? '');

        if ($threadKeyLocal === '') {
            $threadKeyLocal = $inReplyToLocal;
        }

        if ($modo === 'responder') {
            $inReplyToHeader = $inReplyToLocal;
            $referencesHeader = $referencesLocal;
        }
    }

    $resultadoEnvio = enviarCorreoSmtp($_SESSION['kitcherry_mail'], [
        'to' => (string) ($entrada['to'] ?? ''),
        'subject' => (string) ($entrada['subject'] ?? ''),
        'body_html' => (string) ($entrada['body_html'] ?? ''),
        'in_reply_to' => $inReplyToHeader,
        'references' => $referencesHeader
    ]);

    if ($threadKeyLocal === '') {
        $threadKeyLocal = $resultadoEnvio['message_id'];
    }

    $enviadoId = crearCorreoEnviadoSmtp($pdo, $accountId, [
        'to' => $resultadoEnvio['to'],
        'subject' => $resultadoEnvio['subject'],
        'body_html' => $resultadoEnvio['body_html'],
        'message_id' => $resultadoEnvio['message_id'],
        'in_reply_to' => $inReplyToLocal,
        'references_header' => $referencesLocal,
        'thread_key' => $threadKeyLocal
    ]);

    if ($sourceId > 0 && in_array($modo, ['responder', 'reenviar'], true)) {
        $accion = $modo === 'responder'
            ? 'Respuesta enviada por SMTP.'
            : 'Correo reenviado por SMTP.';

        $stmt = $pdo->prepare("
            UPDATE emails
            SET status = 'respondido',
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND account_id = :account_id
        ");

        $stmt->execute([
            ':id' => $sourceId,
            ':account_id' => $accountId
        ]);

        registrarHistorialCorreo($pdo, $sourceId, $accion);
    }

    registrarHistorialCorreo($pdo, $enviadoId, 'Correo enviado por SMTP.');

    if ($draftId > 0) {
        eliminarBorradorLocal($pdo, $accountId, $draftId);
    }

    responderOk($pdo, $accountId, 'Correo enviado correctamente');
}

/**
 * Marca un correo como leído o no leído en SQLite y en IMAP si existe en servidor.
 */
function actualizarLectura(PDO $pdo, int $accountId, array $entrada): void
{
    $id = obtenerIdCorreo($entrada);
    $correoActual = obtenerCorreoBasico($pdo, $accountId, $id);

    $leido = !empty($entrada['is_read']) ? 1 : 0;
    $silencioso = !empty($entrada['silent']);

    if (!empty($correoActual['imap_uid'])) {
        cambiarLecturaImap(
            $_SESSION['kitcherry_mail'],
            (string) ($correoActual['remote_folder'] ?: 'INBOX'),
            (string) $correoActual['imap_uid'],
            (bool) $leido
        );
    }

    $stmt = $pdo->prepare("
        UPDATE emails
        SET is_read = :is_read,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND account_id = :account_id
    ");

    $stmt->execute([
        ':is_read' => $leido,
        ':id' => $id,
        ':account_id' => $accountId
    ]);

    if (!$silencioso) {
        registrarHistorialCorreo($pdo, $id, $leido ? 'Marcado como leído en SQLite e IMAP.' : 'Marcado como no leído en SQLite e IMAP.');
    }

    responderOk($pdo, $accountId, $leido ? 'Correo marcado como leído' : 'Correo marcado como no leído');
}

/**
 * Mueve un correo a archivados o papelera.
 */
function moverCarpeta(PDO $pdo, int $accountId, array $entrada): void
{
    $id = obtenerIdCorreo($entrada);
    $correoActual = obtenerCorreoBasico($pdo, $accountId, $id);

    $carpeta = $entrada['folder'] ?? '';
    $carpetasPermitidas = ['inbox', 'sent', 'drafts', 'archived', 'trash'];

    if (!in_array($carpeta, $carpetasPermitidas, true)) {
        responderError('Carpeta no válida');
    }

    $estado = $entrada['status'] ?? $correoActual['status'];

    if ($carpeta === 'archived') {
        $estado = 'archivado';
    }

    $previousFolder = $correoActual['folder'];
    $previousStatus = $correoActual['status'];

    if (in_array($previousFolder, ['archived', 'trash'], true)) {
        $previousFolder = $correoActual['previous_folder'] ?: 'inbox';
    }

    if ($previousStatus === 'archivado') {
        $previousStatus = $correoActual['previous_status'] ?: 'pendiente';
    }

    $nuevoRemoteFolder = $correoActual['remote_folder'] ?: 'INBOX';
    $nuevoImapUid = $correoActual['imap_uid'];

    if (!empty($correoActual['imap_uid']) && in_array($carpeta, ['archived', 'trash'], true)) {
        $tipoMovimiento = $carpeta === 'trash' ? 'trash' : 'archive';

        $resultadoServidor = moverCorreoImap(
            $_SESSION['kitcherry_mail'],
            [
                'remote_folder' => (string) ($correoActual['remote_folder'] ?: 'INBOX'),
                'imap_uid' => (string) $correoActual['imap_uid'],
                'message_id' => (string) ($correoActual['message_id'] ?? '')
            ],
            $tipoMovimiento
        );

        $nuevoRemoteFolder = $resultadoServidor['remote_folder'];
        $nuevoImapUid = $resultadoServidor['imap_uid'];
    }

    resolverConflictoCarpetaUid($pdo, $accountId, $id, $carpeta, (string) $nuevoImapUid);

    $stmt = $pdo->prepare("
        UPDATE emails
        SET folder = :folder,
            status = :status,
            previous_folder = :previous_folder,
            previous_status = :previous_status,
            remote_folder = :remote_folder,
            imap_uid = :imap_uid,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND account_id = :account_id
    ");

    $stmt->execute([
        ':folder' => $carpeta,
        ':status' => $estado,
        ':previous_folder' => $previousFolder,
        ':previous_status' => $previousStatus,
        ':remote_folder' => $nuevoRemoteFolder,
        ':imap_uid' => $nuevoImapUid,
        ':id' => $id,
        ':account_id' => $accountId
    ]);

    if ($carpeta === 'archived') {
        registrarHistorialCorreo($pdo, $id, 'Correo archivado en SQLite e IMAP.');
        responderOk($pdo, $accountId, 'Correo archivado');
    }

    if ($carpeta === 'trash') {
        registrarHistorialCorreo($pdo, $id, 'Correo enviado a papelera en SQLite e IMAP.');
        responderOk($pdo, $accountId, 'Correo enviado a papelera');
    }

    registrarHistorialCorreo($pdo, $id, 'Correo movido de carpeta.');

    responderOk($pdo, $accountId, 'Correo movido');
}

/**
 * Restaura un correo archivado o enviado a papelera.
 */
function restaurarCorreo(PDO $pdo, int $accountId, array $entrada): void
{
    $id = obtenerIdCorreo($entrada);
    $correoActual = obtenerCorreoBasico($pdo, $accountId, $id);

    $carpetaActual = $correoActual['folder'];
    $carpetaDestino = $correoActual['previous_folder'] ?: 'inbox';
    $estadoDestino = $correoActual['previous_status'] ?: 'pendiente';

    if (!in_array($carpetaDestino, ['inbox', 'sent', 'drafts'], true)) {
        $carpetaDestino = 'inbox';
    }

    if (!in_array($estadoDestino, ['pendiente', 'revision', 'respondido', 'importante'], true)) {
        $estadoDestino = 'pendiente';
    }

    $nuevoRemoteFolder = $correoActual['remote_folder'] ?: 'INBOX';
    $nuevoImapUid = $correoActual['imap_uid'];

    if (
        !empty($correoActual['imap_uid'])
        && !empty($correoActual['remote_folder'])
        && $correoActual['remote_folder'] !== 'INBOX'
        && $correoActual['remote_folder'] !== 'LOCAL'
    ) {
        $resultadoServidor = restaurarCorreoImap(
            $_SESSION['kitcherry_mail'],
            [
                'remote_folder' => (string) $correoActual['remote_folder'],
                'imap_uid' => (string) $correoActual['imap_uid'],
                'message_id' => (string) ($correoActual['message_id'] ?? '')
            ]
        );

        $nuevoRemoteFolder = $resultadoServidor['remote_folder'];
        $nuevoImapUid = $resultadoServidor['imap_uid'];
    }

    resolverConflictoCarpetaUid($pdo, $accountId, $id, $carpetaDestino, (string) $nuevoImapUid);

    $stmt = $pdo->prepare("
        UPDATE emails
        SET folder = :folder,
            status = :status,
            previous_folder = NULL,
            previous_status = NULL,
            remote_folder = :remote_folder,
            imap_uid = :imap_uid,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND account_id = :account_id
    ");

    $stmt->execute([
        ':folder' => $carpetaDestino,
        ':status' => $estadoDestino,
        ':remote_folder' => $nuevoRemoteFolder,
        ':imap_uid' => $nuevoImapUid,
        ':id' => $id,
        ':account_id' => $accountId
    ]);

    if ($carpetaActual === 'archived') {
        registrarHistorialCorreo($pdo, $id, 'Correo desarchivado en SQLite e IMAP.');
        responderOk($pdo, $accountId, 'Correo desarchivado');
    }

    if ($carpetaActual === 'trash') {
        registrarHistorialCorreo($pdo, $id, 'Correo restaurado desde papelera en SQLite e IMAP.');
        responderOk($pdo, $accountId, 'Correo restaurado');
    }

    registrarHistorialCorreo($pdo, $id, 'Correo restaurado.');

    responderOk($pdo, $accountId, 'Correo restaurado');
}

/**
 * Actualiza el estado interno del correo.
 */
function actualizarEstado(PDO $pdo, int $accountId, array $entrada): void
{
    $id = obtenerIdCorreo($entrada);
    $correoActual = obtenerCorreoBasico($pdo, $accountId, $id);

    $estado = $entrada['status'] ?? '';
    $estadosPermitidos = ['pendiente', 'revision', 'respondido', 'importante', 'archivado'];

    if (!in_array($estado, $estadosPermitidos, true)) {
        responderError('Estado no válido');
    }

    if ($estado === 'archivado') {
        $nuevoRemoteFolder = $correoActual['remote_folder'] ?: 'INBOX';
        $nuevoImapUid = $correoActual['imap_uid'];

        if (!empty($correoActual['imap_uid'])) {
            $resultadoServidor = moverCorreoImap(
                $_SESSION['kitcherry_mail'],
                [
                    'remote_folder' => (string) ($correoActual['remote_folder'] ?: 'INBOX'),
                    'imap_uid' => (string) $correoActual['imap_uid'],
                    'message_id' => (string) ($correoActual['message_id'] ?? '')
                ],
                'archive'
            );

            $nuevoRemoteFolder = $resultadoServidor['remote_folder'];
            $nuevoImapUid = $resultadoServidor['imap_uid'];
        }

        resolverConflictoCarpetaUid($pdo, $accountId, $id, 'archived', (string) $nuevoImapUid);

        $stmt = $pdo->prepare("
            UPDATE emails
            SET status = :status,
                folder = 'archived',
                previous_folder = :previous_folder,
                previous_status = :previous_status,
                remote_folder = :remote_folder,
                imap_uid = :imap_uid,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND account_id = :account_id
        ");

        $stmt->execute([
            ':status' => $estado,
            ':previous_folder' => $correoActual['folder'],
            ':previous_status' => $correoActual['status'],
            ':remote_folder' => $nuevoRemoteFolder,
            ':imap_uid' => $nuevoImapUid,
            ':id' => $id,
            ':account_id' => $accountId
        ]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE emails
            SET status = :status,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND account_id = :account_id
        ");

        $stmt->execute([
            ':status' => $estado,
            ':id' => $id,
            ':account_id' => $accountId
        ]);
    }

    registrarHistorialCorreo($pdo, $id, 'Estado cambiado a ' . textoEstadoServidor($estado) . '.');

    responderOk($pdo, $accountId, 'Estado actualizado');
}

/**
 * Añade nota interna.
 */
function agregarNota(PDO $pdo, int $accountId, array $entrada): void
{
    $id = obtenerIdCorreo($entrada);
    obtenerCorreoBasico($pdo, $accountId, $id);

    $nota = trim((string) ($entrada['note'] ?? ''));

    if ($nota === '') {
        responderError('La nota no puede estar vacía');
    }

    $stmt = $pdo->prepare("
        INSERT INTO notes (email_id, note)
        VALUES (:email_id, :note)
    ");

    $stmt->execute([
        ':email_id' => $id,
        ':note' => $nota
    ]);

    registrarHistorialCorreo($pdo, $id, 'Nota interna añadida.');

    responderOk($pdo, $accountId, 'Nota añadida');
}

/**
 * Guarda un borrador local nuevo.
 */
function guardarBorrador(PDO $pdo, int $accountId, array $entrada): void
{
    crearCorreoLocalDesdeEditor($pdo, $accountId, $entrada, 'drafts', 'revision', 'Borrador guardado.');
    responderOk($pdo, $accountId, 'Borrador guardado');
}

/**
 * Actualiza un borrador local existente.
 */
function actualizarBorrador(PDO $pdo, int $accountId, array $entrada): void
{
    $id = obtenerIdCorreo($entrada);
    validarBorradorExiste($pdo, $accountId, $id);

    $destinatario = trim((string) ($entrada['to'] ?? ''));
    $asunto = trim((string) ($entrada['subject'] ?? ''));
    $cuerpo = trim((string) ($entrada['body_html'] ?? ''));

    if ($destinatario === '') {
        $destinatario = 'Sin destinatario';
    }

    if ($asunto === '') {
        $asunto = 'Sin asunto';
    }

    if ($cuerpo === '') {
        $cuerpo = '<p>Mensaje sin contenido</p>';
    }

    $resumen = crearResumenDesdeHtml($cuerpo, 100);

    $stmt = $pdo->prepare("
        UPDATE emails
        SET recipient_email = :recipient_email,
            subject = :subject,
            summary = :summary,
            body_html = :body_html,
            email_date = :email_date,
            display_date = :display_date,
            is_read = 1,
            status = 'revision',
            priority = 'media',
            type = 'interno',
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND account_id = :account_id
          AND folder = 'drafts'
    ");

    $stmt->execute([
        ':recipient_email' => $destinatario,
        ':subject' => $asunto,
        ':summary' => $resumen,
        ':body_html' => $cuerpo,
        ':email_date' => date('Y-m-d H:i:s'),
        ':display_date' => 'Ahora',
        ':id' => $id,
        ':account_id' => $accountId
    ]);

    registrarHistorialCorreo($pdo, $id, 'Borrador actualizado.');

    responderOk($pdo, $accountId, 'Borrador actualizado');
}

/**
 * Guarda una copia local en enviados.
 */
function guardarEnviadosLocal(PDO $pdo, int $accountId, array $entrada): void
{
    crearCorreoLocalDesdeEditor($pdo, $accountId, $entrada, 'sent', 'respondido', 'Correo guardado en enviados.');
    responderOk($pdo, $accountId, 'Correo guardado en enviados');
}

/**
 * Crea correo local desde el editor.
 */
function crearCorreoLocalDesdeEditor(PDO $pdo, int $accountId, array $entrada, string $carpeta, string $estado, string $historial): int
{
    $destinatario = trim((string) ($entrada['to'] ?? ''));
    $asunto = trim((string) ($entrada['subject'] ?? ''));
    $cuerpo = trim((string) ($entrada['body_html'] ?? ''));

    if ($destinatario === '') {
        $destinatario = 'Sin destinatario';
    }

    if ($asunto === '') {
        $asunto = 'Sin asunto';
    }

    if ($cuerpo === '') {
        $cuerpo = '<p>Mensaje sin contenido</p>';
    }

    $cuenta = obtenerCuentaPorId($pdo, $accountId);

    if (!$cuenta) {
        responderError('Cuenta no encontrada');
    }

    $resumen = crearResumenDesdeHtml($cuerpo, 100);

    $stmt = $pdo->prepare("
        INSERT INTO emails (
            account_id,
            imap_uid,
            remote_folder,
            message_id,
            thread_key,
            folder,
            sender_name,
            sender_email,
            recipient_email,
            subject,
            summary,
            body_html,
            email_date,
            display_date,
            is_read,
            status,
            priority,
            type,
            has_attachments,
            attachments_json
        ) VALUES (
            :account_id,
            :imap_uid,
            :remote_folder,
            :message_id,
            :thread_key,
            :folder,
            :sender_name,
            :sender_email,
            :recipient_email,
            :subject,
            :summary,
            :body_html,
            :email_date,
            :display_date,
            :is_read,
            :status,
            :priority,
            :type,
            :has_attachments,
            :attachments_json
        )
    ");

    $messageIdLocal = 'local-' . uniqid('', true);

    $stmt->execute([
        ':account_id' => $accountId,
        ':imap_uid' => null,
        ':remote_folder' => 'LOCAL',
        ':message_id' => $messageIdLocal,
        ':thread_key' => $messageIdLocal,
        ':folder' => $carpeta,
        ':sender_name' => 'Kitcherry Mail',
        ':sender_email' => $cuenta['email'],
        ':recipient_email' => $destinatario,
        ':subject' => $asunto,
        ':summary' => $resumen,
        ':body_html' => $cuerpo,
        ':email_date' => date('Y-m-d H:i:s'),
        ':display_date' => 'Ahora',
        ':is_read' => 1,
        ':status' => $estado,
        ':priority' => 'media',
        ':type' => 'interno',
        ':has_attachments' => 0,
        ':attachments_json' => '[]'
    ]);

    $id = (int) $pdo->lastInsertId();

    registrarHistorialCorreo($pdo, $id, $historial);

    return $id;
}

/**
 * Guarda en SQLite un correo enviado por SMTP.
 */
function crearCorreoEnviadoSmtp(PDO $pdo, int $accountId, array $datos): int
{
    $cuenta = obtenerCuentaPorId($pdo, $accountId);

    if (!$cuenta) {
        responderError('Cuenta no encontrada');
    }

    $cuerpo = trim((string) ($datos['body_html'] ?? ''));

    if ($cuerpo === '') {
        $cuerpo = '<p>Mensaje sin contenido</p>';
    }

    $asunto = trim((string) ($datos['subject'] ?? ''));

    if ($asunto === '') {
        $asunto = 'Sin asunto';
    }

    $destinatario = trim((string) ($datos['to'] ?? ''));

    if ($destinatario === '') {
        $destinatario = 'Sin destinatario';
    }

    $messageId = trim((string) ($datos['message_id'] ?? ''));

    if ($messageId === '') {
        $messageId = 'local-' . uniqid('', true);
    }

    $threadKey = trim((string) ($datos['thread_key'] ?? ''));

    if ($threadKey === '') {
        $threadKey = $messageId;
    }

    $resumen = crearResumenDesdeHtml($cuerpo, 120);

    $stmt = $pdo->prepare("
        INSERT INTO emails (
            account_id,
            imap_uid,
            remote_folder,
            message_id,
            in_reply_to,
            references_header,
            thread_key,
            folder,
            sender_name,
            sender_email,
            recipient_email,
            subject,
            summary,
            body_html,
            email_date,
            display_date,
            is_read,
            status,
            priority,
            type,
            has_attachments,
            attachments_json
        ) VALUES (
            :account_id,
            :imap_uid,
            :remote_folder,
            :message_id,
            :in_reply_to,
            :references_header,
            :thread_key,
            :folder,
            :sender_name,
            :sender_email,
            :recipient_email,
            :subject,
            :summary,
            :body_html,
            :email_date,
            :display_date,
            :is_read,
            :status,
            :priority,
            :type,
            :has_attachments,
            :attachments_json
        )
    ");

    $stmt->execute([
        ':account_id' => $accountId,
        ':imap_uid' => null,
        ':remote_folder' => 'LOCAL',
        ':message_id' => $messageId,
        ':in_reply_to' => trim((string) ($datos['in_reply_to'] ?? '')),
        ':references_header' => trim((string) ($datos['references_header'] ?? '')),
        ':thread_key' => $threadKey,
        ':folder' => 'sent',
        ':sender_name' => 'Kitcherry Mail',
        ':sender_email' => $cuenta['email'],
        ':recipient_email' => $destinatario,
        ':subject' => $asunto,
        ':summary' => $resumen,
        ':body_html' => $cuerpo,
        ':email_date' => date('Y-m-d H:i:s'),
        ':display_date' => 'Ahora',
        ':is_read' => 1,
        ':status' => 'respondido',
        ':priority' => 'media',
        ':type' => 'interno',
        ':has_attachments' => 0,
        ':attachments_json' => '[]'
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Valida que un borrador existe y pertenece a la cuenta.
 */
function validarBorradorExiste(PDO $pdo, int $accountId, int $draftId): array
{
    $stmt = $pdo->prepare("
        SELECT id, folder
        FROM emails
        WHERE id = :id
          AND account_id = :account_id
          AND folder = 'drafts'
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $draftId,
        ':account_id' => $accountId
    ]);

    $borrador = $stmt->fetch();

    if (!$borrador) {
        responderError('Borrador no encontrado');
    }

    return $borrador;
}

/**
 * Elimina un borrador local después de enviarlo.
 */
function eliminarBorradorLocal(PDO $pdo, int $accountId, int $draftId): void
{
    validarBorradorExiste($pdo, $accountId, $draftId);

    $pdo->beginTransaction();

    try {
        $stmtNotas = $pdo->prepare("
            DELETE FROM notes
            WHERE email_id = :email_id
        ");

        $stmtNotas->execute([
            ':email_id' => $draftId
        ]);

        $stmtHistorial = $pdo->prepare("
            DELETE FROM history
            WHERE email_id = :email_id
        ");

        $stmtHistorial->execute([
            ':email_id' => $draftId
        ]);

        $stmtCorreo = $pdo->prepare("
            DELETE FROM emails
            WHERE id = :id
              AND account_id = :account_id
              AND folder = 'drafts'
        ");

        $stmtCorreo->execute([
            ':id' => $draftId,
            ':account_id' => $accountId
        ]);

        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

/**
 * Obtiene datos básicos del correo.
 */
function obtenerCorreoBasico(PDO $pdo, int $accountId, int $emailId): array
{
    $stmt = $pdo->prepare("
        SELECT
            id,
            folder,
            previous_folder,
            previous_status,
            status,
            imap_uid,
            remote_folder,
            message_id
        FROM emails
        WHERE id = :id
          AND account_id = :account_id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $emailId,
        ':account_id' => $accountId
    ]);

    $correo = $stmt->fetch();

    if (!$correo) {
        responderError('Correo no encontrado');
    }

    return $correo;
}

/**
 * Obtiene correo para respuesta o reenvío.
 */
function obtenerCorreoParaRespuesta(PDO $pdo, int $accountId, int $emailId): array
{
    $stmt = $pdo->prepare("
        SELECT
            id,
            message_id,
            in_reply_to,
            references_header,
            thread_key,
            subject,
            sender_email,
            sender_name
        FROM emails
        WHERE id = :id
          AND account_id = :account_id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $emailId,
        ':account_id' => $accountId
    ]);

    $correo = $stmt->fetch();

    if (!$correo) {
        responderError('Correo origen no encontrado');
    }

    return $correo;
}

/**
 * Elimina o fusiona un registro local que chocaría con UNIQUE(account_id, folder, imap_uid).
 */
function resolverConflictoCarpetaUid(PDO $pdo, int $accountId, int $idActual, string $folderDestino, string $imapUid): void
{
    if ($imapUid === '') {
        return;
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM emails
        WHERE account_id = :account_id
          AND folder = :folder
          AND imap_uid = :imap_uid
          AND id <> :id_actual
    ");

    $stmt->execute([
        ':account_id' => $accountId,
        ':folder' => $folderDestino,
        ':imap_uid' => $imapUid,
        ':id_actual' => $idActual
    ]);

    $conflictos = $stmt->fetchAll();

    foreach ($conflictos as $conflicto) {
        $idConflicto = (int) $conflicto['id'];

        $stmtNotas = $pdo->prepare("
            UPDATE notes
            SET email_id = :id_actual
            WHERE email_id = :id_conflicto
        ");

        $stmtNotas->execute([
            ':id_actual' => $idActual,
            ':id_conflicto' => $idConflicto
        ]);

        $stmtHistorial = $pdo->prepare("
            UPDATE history
            SET email_id = :id_actual
            WHERE email_id = :id_conflicto
        ");

        $stmtHistorial->execute([
            ':id_actual' => $idActual,
            ':id_conflicto' => $idConflicto
        ]);

        $stmtEliminar = $pdo->prepare("
            DELETE FROM emails
            WHERE id = :id_conflicto
              AND account_id = :account_id
        ");

        $stmtEliminar->execute([
            ':id_conflicto' => $idConflicto,
            ':account_id' => $accountId
        ]);
    }
}

/**
 * Obtiene ID de correo.
 */
function obtenerIdCorreo(array $entrada): int
{
    $id = (int) ($entrada['id'] ?? 0);

    if ($id <= 0) {
        responderError('ID de correo no válido');
    }

    return $id;
}

/**
 * Traduce estados.
 */
function textoEstadoServidor(string $estado): string
{
    $estados = [
        'pendiente' => 'Pendiente',
        'revision' => 'Revisión',
        'respondido' => 'Respondido',
        'importante' => 'Importante',
        'archivado' => 'Archivado'
    ];

    return $estados[$estado] ?? $estado;
}

/**
 * Respuesta correcta.
 */
function responderOk(PDO $pdo, int $accountId, string $mensaje): void
{
    echo json_encode([
        'ok' => true,
        'message' => $mensaje,
        'correos' => obtenerCorreosParaVista($pdo, $accountId)
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

/**
 * Respuesta de error.
 */
function responderError(string $mensaje, int $codigo = 400): void
{
    http_response_code($codigo);

    echo json_encode([
        'ok' => false,
        'message' => $mensaje
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}