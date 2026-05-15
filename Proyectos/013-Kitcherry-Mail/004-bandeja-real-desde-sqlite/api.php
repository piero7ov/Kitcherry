<?php
// ==========================================================
// KITCHERRY MAIL
// API interna para trabajar contra SQLite e IMAP.
// ==========================================================

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/services/imap_service.php';

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

        case 'save_sent_local':
            guardarEnviadosLocal($pdo, $accountId, $entrada);
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
 * Marca un correo como leído o no leído.
 */
function actualizarLectura(PDO $pdo, int $accountId, array $entrada): void
{
    $id = obtenerIdCorreo($entrada);
    validarCorreoCuenta($pdo, $accountId, $id);

    $leido = !empty($entrada['is_read']) ? 1 : 0;
    $silencioso = !empty($entrada['silent']);

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
        registrarHistorialCorreo($pdo, $id, $leido ? 'Marcado como leído.' : 'Marcado como no leído.');
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

    $stmt = $pdo->prepare("
        UPDATE emails
        SET folder = :folder,
            status = :status,
            previous_folder = :previous_folder,
            previous_status = :previous_status,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND account_id = :account_id
    ");

    $stmt->execute([
        ':folder' => $carpeta,
        ':status' => $estado,
        ':previous_folder' => $previousFolder,
        ':previous_status' => $previousStatus,
        ':id' => $id,
        ':account_id' => $accountId
    ]);

    if ($carpeta === 'archived') {
        registrarHistorialCorreo($pdo, $id, 'Correo archivado.');
        responderOk($pdo, $accountId, 'Correo archivado');
    }

    if ($carpeta === 'trash') {
        registrarHistorialCorreo($pdo, $id, 'Correo enviado a papelera.');
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

    $stmt = $pdo->prepare("
        UPDATE emails
        SET folder = :folder,
            status = :status,
            previous_folder = NULL,
            previous_status = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND account_id = :account_id
    ");

    $stmt->execute([
        ':folder' => $carpetaDestino,
        ':status' => $estadoDestino,
        ':id' => $id,
        ':account_id' => $accountId
    ]);

    if ($carpetaActual === 'archived') {
        registrarHistorialCorreo($pdo, $id, 'Correo desarchivado.');
        responderOk($pdo, $accountId, 'Correo desarchivado');
    }

    if ($carpetaActual === 'trash') {
        registrarHistorialCorreo($pdo, $id, 'Correo restaurado desde papelera.');
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
    validarCorreoCuenta($pdo, $accountId, $id);

    $estado = $entrada['status'] ?? '';
    $estadosPermitidos = ['pendiente', 'revision', 'respondido', 'importante', 'archivado'];

    if (!in_array($estado, $estadosPermitidos, true)) {
        responderError('Estado no válido');
    }

    if ($estado === 'archivado') {
        $correoActual = obtenerCorreoBasico($pdo, $accountId, $id);

        $stmt = $pdo->prepare("
            UPDATE emails
            SET status = :status,
                folder = 'archived',
                previous_folder = :previous_folder,
                previous_status = :previous_status,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND account_id = :account_id
        ");

        $stmt->execute([
            ':status' => $estado,
            ':previous_folder' => $correoActual['folder'],
            ':previous_status' => $correoActual['status'],
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
    validarCorreoCuenta($pdo, $accountId, $id);

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
 * Guarda un borrador local.
 */
function guardarBorrador(PDO $pdo, int $accountId, array $entrada): void
{
    crearCorreoLocalDesdeEditor($pdo, $accountId, $entrada, 'drafts', 'revision', 'Borrador guardado.');
    responderOk($pdo, $accountId, 'Borrador guardado');
}

/**
 * Guarda una copia local en enviados.
 * El envío SMTP real se añadirá más adelante.
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
            message_id,
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
            :message_id,
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
        ':message_id' => 'local-' . uniqid('', true),
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
 * Obtiene datos básicos del correo.
 */
function obtenerCorreoBasico(PDO $pdo, int $accountId, int $emailId): array
{
    $stmt = $pdo->prepare("
        SELECT id, folder, status, previous_folder, previous_status
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
 * Valida que el correo pertenece a la cuenta activa.
 */
function validarCorreoCuenta(PDO $pdo, int $accountId, int $emailId): void
{
    obtenerCorreoBasico($pdo, $accountId, $emailId);
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