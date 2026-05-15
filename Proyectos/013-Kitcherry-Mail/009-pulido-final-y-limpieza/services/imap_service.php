<?php
// ==========================================================
// KITCHERRY MAIL
// Servicio de sincronización y acciones IMAP.
// Sincronización completa: Entrada, Papelera y Enviados.
// ==========================================================

declare(strict_types=1);

/**
 * Mantiene el nombre original para no tocar api.php.
 * Ahora sincroniza:
 * - Entrada / INBOX
 * - Papelera real
 * - Enviados reales
 */
function sincronizarInboxImap(PDO $pdo, array $sesion, int $limite = 30): array
{
    validarExtensionImap();

    $accountId = (int) ($sesion['account_id'] ?? 0);
    $email = (string) ($sesion['email'] ?? '');
    $password = (string) ($sesion['password'] ?? '');
    $host = (string) ($sesion['imap_host'] ?? '');

    if ($accountId <= 0 || $email === '' || $password === '' || $host === '') {
        throw new RuntimeException('Faltan datos de sesión para sincronizar por IMAP.');
    }

    $resultadoTotal = [
        'procesados' => 0,
        'insertados' => 0,
        'actualizados' => 0
    ];

    $resultadoInbox = sincronizarCarpetaImap($pdo, $sesion, 'INBOX', 'inbox', $limite);
    sumarResultadoSincronizacion($resultadoTotal, $resultadoInbox);

    $carpetaPapelera = detectarCarpetaDestinoImap($sesion, 'trash');

    if ($carpetaPapelera !== '') {
        $resultadoTrash = sincronizarCarpetaImap($pdo, $sesion, $carpetaPapelera, 'trash', $limite);
        sumarResultadoSincronizacion($resultadoTotal, $resultadoTrash);
    }

    $carpetaEnviados = detectarCarpetaEspecialImap($sesion, 'sent');

    if ($carpetaEnviados !== '') {
        $resultadoSent = sincronizarCarpetaImap($pdo, $sesion, $carpetaEnviados, 'sent', $limite);
        sumarResultadoSincronizacion($resultadoTotal, $resultadoSent);
    }

    return $resultadoTotal;
}

/**
 * Suma resultados de varias carpetas.
 */
function sumarResultadoSincronizacion(array &$total, array $parcial): void
{
    $total['procesados'] += (int) ($parcial['procesados'] ?? 0);
    $total['insertados'] += (int) ($parcial['insertados'] ?? 0);
    $total['actualizados'] += (int) ($parcial['actualizados'] ?? 0);
}

/**
 * Sincroniza una carpeta IMAP concreta.
 */
function sincronizarCarpetaImap(PDO $pdo, array $sesion, string $remoteFolder, string $localFolder, int $limite = 30): array
{
    validarExtensionImap();

    $accountId = (int) ($sesion['account_id'] ?? 0);
    $emailCuenta = (string) ($sesion['email'] ?? '');

    if ($remoteFolder === '' || $localFolder === '') {
        return [
            'procesados' => 0,
            'insertados' => 0,
            'actualizados' => 0
        ];
    }

    $imap = abrirBuzonImap($sesion, $remoteFolder, true);

    $insertados = 0;
    $actualizados = 0;
    $procesados = 0;

    try {
        $uids = imap_search($imap, 'ALL', SE_UID);

        if (!$uids) {
            return [
                'procesados' => 0,
                'insertados' => 0,
                'actualizados' => 0
            ];
        }

        sort($uids, SORT_NUMERIC);

        if (count($uids) > $limite) {
            $uids = array_slice($uids, -$limite);
        }

        foreach ($uids as $uid) {
            $uid = (int) $uid;

            $correo = construirCorreoDesdeImap($imap, $uid, $emailCuenta, $remoteFolder, $localFolder);
            $correo = ajustarCorreoSegunCarpetaLocal($correo, $localFolder, $emailCuenta);

            $resultado = guardarCorreoSincronizadoInteligenteImap($pdo, $accountId, $correo);

            if ($resultado === 'inserted') {
                $insertados++;
            }

            if ($resultado === 'updated') {
                $actualizados++;
            }

            $procesados++;
        }
    } finally {
        if (is_resource($imap) || $imap instanceof IMAP\Connection) {
            imap_close($imap);
        }
    }

    return [
        'procesados' => $procesados,
        'insertados' => $insertados,
        'actualizados' => $actualizados
    ];
}

/**
 * Ajusta estado/tipo según carpeta local.
 */
function ajustarCorreoSegunCarpetaLocal(array $correo, string $localFolder, string $emailCuenta): array
{
    if ($localFolder === 'sent') {
        $correo['status'] = 'respondido';
        $correo['type'] = 'interno';
        $correo['sender_name'] = 'Kitcherry Mail';
        $correo['sender_email'] = $emailCuenta;
    }

    if ($localFolder === 'trash') {
        $correo['folder'] = 'trash';
    }

    if ($localFolder === 'archived') {
        $correo['status'] = 'archivado';
    }

    return $correo;
}

/**
 * Guarda sincronización evitando duplicados por:
 * - remote_folder + imap_uid
 * - message_id
 * - folder + imap_uid
 */
function guardarCorreoSincronizadoInteligenteImap(PDO $pdo, int $accountId, array $correo): string
{
    $idExacto = buscarCorreoPorRemoteUid($pdo, $accountId, $correo);

    if ($idExacto > 0) {
        actualizarCorreoSincronizadoImap($pdo, $accountId, $idExacto, $correo, false);
        return 'updated';
    }

    $idPorMessageId = buscarCorreoPorMessageIdImap($pdo, $accountId, (string) ($correo['message_id'] ?? ''));

    if ($idPorMessageId > 0) {
        actualizarCorreoSincronizadoImap($pdo, $accountId, $idPorMessageId, $correo, true);
        return 'updated';
    }

    $idPorFolderUid = buscarCorreoPorFolderUidImap($pdo, $accountId, (string) $correo['folder'], (string) $correo['imap_uid']);

    if ($idPorFolderUid > 0) {
        actualizarCorreoSincronizadoImap($pdo, $accountId, $idPorFolderUid, $correo, true);
        return 'updated';
    }

    insertarCorreoSincronizadoImap($pdo, $accountId, $correo);

    return 'inserted';
}

/**
 * Busca por carpeta remota + UID.
 */
function buscarCorreoPorRemoteUid(PDO $pdo, int $accountId, array $correo): int
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM emails
        WHERE account_id = :account_id
          AND remote_folder = :remote_folder
          AND imap_uid = :imap_uid
        LIMIT 1
    ");

    $stmt->execute([
        ':account_id' => $accountId,
        ':remote_folder' => (string) ($correo['remote_folder'] ?? 'INBOX'),
        ':imap_uid' => (string) ($correo['imap_uid'] ?? '')
    ]);

    $fila = $stmt->fetch();

    return $fila ? (int) $fila['id'] : 0;
}

/**
 * Busca por Message-ID.
 */
function buscarCorreoPorMessageIdImap(PDO $pdo, int $accountId, string $messageId): int
{
    $messageId = trim($messageId);

    if ($messageId === '') {
        return 0;
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM emails
        WHERE account_id = :account_id
          AND message_id = :message_id
        ORDER BY
            CASE
                WHEN folder = 'inbox' THEN 0
                WHEN folder = 'sent' THEN 1
                WHEN folder = 'trash' THEN 2
                WHEN folder = 'archived' THEN 3
                ELSE 4
            END,
            id ASC
        LIMIT 1
    ");

    $stmt->execute([
        ':account_id' => $accountId,
        ':message_id' => $messageId
    ]);

    $fila = $stmt->fetch();

    return $fila ? (int) $fila['id'] : 0;
}

/**
 * Busca por folder local + UID para evitar el UNIQUE.
 */
function buscarCorreoPorFolderUidImap(PDO $pdo, int $accountId, string $folder, string $imapUid): int
{
    if ($imapUid === '') {
        return 0;
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM emails
        WHERE account_id = :account_id
          AND folder = :folder
          AND imap_uid = :imap_uid
        LIMIT 1
    ");

    $stmt->execute([
        ':account_id' => $accountId,
        ':folder' => $folder,
        ':imap_uid' => $imapUid
    ]);

    $fila = $stmt->fetch();

    return $fila ? (int) $fila['id'] : 0;
}

/**
 * Actualiza un correo existente respetando la lógica local.
 */
function actualizarCorreoSincronizadoImap(PDO $pdo, int $accountId, int $emailId, array $correo, bool $resolverMovimientoLocal): void
{
    $correoActual = obtenerCorreoLocalBasicoImap($pdo, $accountId, $emailId);

    $folderDestino = (string) $correoActual['folder'];
    $statusDestino = (string) $correoActual['status'];
    $previousFolder = $correoActual['previous_folder'];
    $previousStatus = $correoActual['previous_status'];

    if ($resolverMovimientoLocal) {
        $folderDestino = resolverFolderLocalDesdeSync((string) $correoActual['folder'], (string) $correo['folder']);

        if ($folderDestino !== (string) $correoActual['folder']) {
            if ($folderDestino === 'trash') {
                $previousFolder = (string) $correoActual['folder'];
                $previousStatus = (string) $correoActual['status'];
            }

            if ($folderDestino === 'inbox') {
                $statusDestino = $correoActual['previous_status'] ?: (string) $correo['status'];
                $previousFolder = null;
                $previousStatus = null;
            }

            if ($folderDestino === 'sent') {
                $statusDestino = 'respondido';
            }

            if ($folderDestino === 'archived') {
                $statusDestino = 'archivado';
            }
        }
    }

    if (!$resolverMovimientoLocal) {
        if ($folderDestino === 'sent') {
            $statusDestino = 'respondido';
        }

        if ($folderDestino === 'archived') {
            $statusDestino = 'archivado';
        }
    }

    resolverConflictoSyncUidImap(
        $pdo,
        $accountId,
        $emailId,
        $folderDestino,
        (string) ($correo['imap_uid'] ?? '')
    );

    $stmt = $pdo->prepare("
        UPDATE emails
        SET imap_uid = :imap_uid,
            remote_folder = :remote_folder,
            message_id = :message_id,
            in_reply_to = :in_reply_to,
            references_header = :references_header,
            thread_key = :thread_key,
            folder = :folder,
            previous_folder = :previous_folder,
            previous_status = :previous_status,
            sender_name = :sender_name,
            sender_email = :sender_email,
            recipient_email = :recipient_email,
            subject = :subject,
            summary = :summary,
            body_html = :body_html,
            email_date = :email_date,
            display_date = :display_date,
            is_read = :is_read,
            status = :status,
            priority = :priority,
            type = :type,
            has_attachments = :has_attachments,
            attachments_json = :attachments_json,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND account_id = :account_id
    ");

    $stmt->execute([
        ':imap_uid' => (string) ($correo['imap_uid'] ?? ''),
        ':remote_folder' => (string) ($correo['remote_folder'] ?? 'INBOX'),
        ':message_id' => (string) ($correo['message_id'] ?? ''),
        ':in_reply_to' => (string) ($correo['in_reply_to'] ?? ''),
        ':references_header' => (string) ($correo['references_header'] ?? ''),
        ':thread_key' => (string) ($correo['thread_key'] ?? ''),
        ':folder' => $folderDestino,
        ':previous_folder' => $previousFolder,
        ':previous_status' => $previousStatus,
        ':sender_name' => (string) ($correo['sender_name'] ?? 'Remitente desconocido'),
        ':sender_email' => (string) ($correo['sender_email'] ?? 'sin-correo'),
        ':recipient_email' => (string) ($correo['recipient_email'] ?? ''),
        ':subject' => (string) ($correo['subject'] ?? 'Sin asunto'),
        ':summary' => (string) ($correo['summary'] ?? 'Mensaje sin contenido'),
        ':body_html' => (string) ($correo['body_html'] ?? '<p>Mensaje sin contenido.</p>'),
        ':email_date' => (string) ($correo['email_date'] ?? date('Y-m-d H:i:s')),
        ':display_date' => (string) ($correo['display_date'] ?? 'Ahora'),
        ':is_read' => (int) ($correo['is_read'] ?? 0),
        ':status' => $statusDestino,
        ':priority' => (string) ($correo['priority'] ?? 'media'),
        ':type' => (string) ($correo['type'] ?? 'cliente'),
        ':has_attachments' => (int) ($correo['has_attachments'] ?? 0),
        ':attachments_json' => (string) ($correo['attachments_json'] ?? '[]'),
        ':id' => $emailId,
        ':account_id' => $accountId
    ]);
}

/**
 * Inserta correo nuevo sincronizado.
 */
function insertarCorreoSincronizadoImap(PDO $pdo, int $accountId, array $correo): void
{
    $status = (string) ($correo['status'] ?? 'pendiente');

    if ((string) $correo['folder'] === 'sent') {
        $status = 'respondido';
    }

    if ((string) $correo['folder'] === 'archived') {
        $status = 'archivado';
    }

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
        ':imap_uid' => (string) ($correo['imap_uid'] ?? ''),
        ':remote_folder' => (string) ($correo['remote_folder'] ?? 'INBOX'),
        ':message_id' => (string) ($correo['message_id'] ?? ''),
        ':in_reply_to' => (string) ($correo['in_reply_to'] ?? ''),
        ':references_header' => (string) ($correo['references_header'] ?? ''),
        ':thread_key' => (string) ($correo['thread_key'] ?? ''),
        ':folder' => (string) ($correo['folder'] ?? 'inbox'),
        ':sender_name' => (string) ($correo['sender_name'] ?? 'Remitente desconocido'),
        ':sender_email' => (string) ($correo['sender_email'] ?? 'sin-correo'),
        ':recipient_email' => (string) ($correo['recipient_email'] ?? ''),
        ':subject' => (string) ($correo['subject'] ?? 'Sin asunto'),
        ':summary' => (string) ($correo['summary'] ?? 'Mensaje sin contenido'),
        ':body_html' => (string) ($correo['body_html'] ?? '<p>Mensaje sin contenido.</p>'),
        ':email_date' => (string) ($correo['email_date'] ?? date('Y-m-d H:i:s')),
        ':display_date' => (string) ($correo['display_date'] ?? 'Ahora'),
        ':is_read' => (int) ($correo['is_read'] ?? 0),
        ':status' => $status,
        ':priority' => (string) ($correo['priority'] ?? 'media'),
        ':type' => (string) ($correo['type'] ?? 'cliente'),
        ':has_attachments' => (int) ($correo['has_attachments'] ?? 0),
        ':attachments_json' => (string) ($correo['attachments_json'] ?? '[]')
    ]);

    $emailId = (int) $pdo->lastInsertId();

    registrarHistorialCorreo($pdo, $emailId, 'Correo sincronizado desde IMAP.');
}

/**
 * Obtiene datos locales básicos.
 */
function obtenerCorreoLocalBasicoImap(PDO $pdo, int $accountId, int $emailId): array
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
        throw new RuntimeException('Correo local no encontrado durante la sincronización.');
    }

    return $correo;
}

/**
 * Decide qué carpeta local debe quedar tras sincronizar.
 */
function resolverFolderLocalDesdeSync(string $folderActual, string $folderSincronizado): string
{
    if ($folderSincronizado === 'trash') {
        return 'trash';
    }

    if ($folderSincronizado === 'sent') {
        return 'sent';
    }

    if ($folderSincronizado === 'inbox') {
        return 'inbox';
    }

    if ($folderSincronizado === 'archived') {
        if (in_array($folderActual, ['inbox', 'sent', 'drafts', 'trash'], true)) {
            return $folderActual;
        }

        return 'archived';
    }

    return $folderActual;
}

/**
 * Resuelve conflicto de UNIQUE(account_id, folder, imap_uid).
 */
function resolverConflictoSyncUidImap(PDO $pdo, int $accountId, int $idActual, string $folderDestino, string $imapUid): void
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
 * Marca un correo como leído/no leído en el servidor IMAP.
 */
function cambiarLecturaImap(array $sesion, string $remoteFolder, string $uid, bool $leido): void
{
    validarExtensionImap();

    if ($uid === '' || $remoteFolder === 'LOCAL') {
        return;
    }

    $uidValido = asegurarUidValido($sesion, $remoteFolder, $uid, '');

    if ($uidValido === '') {
        throw new RuntimeException('No se pudo localizar el correo en el servidor para cambiar lectura.');
    }

    $imap = abrirBuzonImap($sesion, $remoteFolder, false);

    try {
        if ($leido) {
            $ok = imap_setflag_full($imap, $uidValido, "\\Seen", ST_UID);
        } else {
            $ok = imap_clearflag_full($imap, $uidValido, "\\Seen", ST_UID);
        }

        if (!$ok) {
            $error = imap_last_error() ?: 'No se pudo cambiar el estado de lectura en IMAP.';
            throw new RuntimeException($error);
        }
    } finally {
        if (is_resource($imap) || $imap instanceof IMAP\Connection) {
            imap_close($imap);
        }
    }
}

/**
 * Mueve un correo en IMAP a archivo o papelera.
 */
function moverCorreoImap(array $sesion, array $correo, string $tipo): array
{
    validarExtensionImap();

    $remoteFolderOrigen = (string) ($correo['remote_folder'] ?? 'INBOX');
    $uid = (string) ($correo['imap_uid'] ?? '');
    $messageId = (string) ($correo['message_id'] ?? '');

    if ($uid === '' || $remoteFolderOrigen === 'LOCAL') {
        return [
            'remote_folder' => $remoteFolderOrigen,
            'imap_uid' => $uid
        ];
    }

    $remoteFolderDestino = detectarCarpetaDestinoImap($sesion, $tipo);

    if ($remoteFolderDestino === '') {
        if ($tipo === 'trash') {
            throw new RuntimeException('No se ha encontrado una carpeta de papelera en el servidor IMAP.');
        }

        throw new RuntimeException('No se ha encontrado una carpeta de archivo en el servidor IMAP.');
    }

    if ($remoteFolderOrigen === $remoteFolderDestino) {
        return [
            'remote_folder' => $remoteFolderDestino,
            'imap_uid' => $uid
        ];
    }

    $uidOrigen = asegurarUidValido($sesion, $remoteFolderOrigen, $uid, $messageId);

    if ($uidOrigen === '') {
        $uidYaMovido = buscarUidPorMessageId($sesion, $remoteFolderDestino, $messageId);

        if ($uidYaMovido !== '') {
            return [
                'remote_folder' => $remoteFolderDestino,
                'imap_uid' => $uidYaMovido
            ];
        }

        throw new RuntimeException('No se pudo localizar el correo en la carpeta origen ni en la carpeta destino.');
    }

    $imap = abrirBuzonImap($sesion, $remoteFolderOrigen, false);

    try {
        $ok = imap_mail_move($imap, $uidOrigen, $remoteFolderDestino, CP_UID);

        if (!$ok) {
            $error = imap_last_error() ?: 'No se pudo mover el correo en IMAP.';
            throw new RuntimeException($error);
        }

        imap_expunge($imap);
    } finally {
        if (is_resource($imap) || $imap instanceof IMAP\Connection) {
            imap_close($imap);
        }
    }

    $nuevoUid = buscarUidPorMessageId($sesion, $remoteFolderDestino, $messageId);

    if ($nuevoUid === '') {
        $nuevoUid = $uidOrigen;
    }

    return [
        'remote_folder' => $remoteFolderDestino,
        'imap_uid' => $nuevoUid
    ];
}

/**
 * Restaura un correo desde archivo/papelera hacia INBOX.
 */
function restaurarCorreoImap(array $sesion, array $correo): array
{
    validarExtensionImap();

    $remoteFolderOrigen = (string) ($correo['remote_folder'] ?? '');
    $uid = (string) ($correo['imap_uid'] ?? '');
    $messageId = (string) ($correo['message_id'] ?? '');

    if ($uid === '' || $remoteFolderOrigen === '' || $remoteFolderOrigen === 'LOCAL') {
        return [
            'remote_folder' => $remoteFolderOrigen,
            'imap_uid' => $uid
        ];
    }

    if ($remoteFolderOrigen === 'INBOX') {
        return [
            'remote_folder' => 'INBOX',
            'imap_uid' => $uid
        ];
    }

    $uidOrigen = asegurarUidValido($sesion, $remoteFolderOrigen, $uid, $messageId);

    if ($uidOrigen === '') {
        $uidYaRestaurado = buscarUidPorMessageId($sesion, 'INBOX', $messageId);

        if ($uidYaRestaurado !== '') {
            return [
                'remote_folder' => 'INBOX',
                'imap_uid' => $uidYaRestaurado
            ];
        }

        throw new RuntimeException('No se pudo localizar el correo para restaurarlo.');
    }

    $imap = abrirBuzonImap($sesion, $remoteFolderOrigen, false);

    try {
        $ok = imap_mail_move($imap, $uidOrigen, 'INBOX', CP_UID);

        if (!$ok) {
            $error = imap_last_error() ?: 'No se pudo restaurar el correo en IMAP.';
            throw new RuntimeException($error);
        }

        imap_expunge($imap);
    } finally {
        if (is_resource($imap) || $imap instanceof IMAP\Connection) {
            imap_close($imap);
        }
    }

    $nuevoUid = buscarUidPorMessageId($sesion, 'INBOX', $messageId);

    if ($nuevoUid === '') {
        $nuevoUid = $uidOrigen;
    }

    return [
        'remote_folder' => 'INBOX',
        'imap_uid' => $nuevoUid
    ];
}

/**
 * Valida extensión IMAP.
 */
function validarExtensionImap(): void
{
    if (!extension_loaded('imap')) {
        throw new RuntimeException('La extensión IMAP de PHP no está activa. Activa extension=imap en php.ini y reinicia Apache.');
    }
}

/**
 * Abre una carpeta IMAP.
 */
function abrirBuzonImap(array $sesion, string $folder = 'INBOX', bool $soloLectura = true)
{
    $email = (string) ($sesion['email'] ?? '');
    $password = (string) ($sesion['password'] ?? '');

    if ($email === '' || $password === '') {
        throw new RuntimeException('Faltan credenciales de correo en la sesión.');
    }

    $mailbox = construirMailboxDesdeSesion($sesion, $folder);
    $flags = $soloLectura ? OP_READONLY : 0;

    $imap = @imap_open($mailbox, $email, $password, $flags);

    if (!$imap) {
        $error = imap_last_error() ?: 'No se pudo abrir la carpeta IMAP: ' . $folder;
        throw new RuntimeException($error);
    }

    return $imap;
}

/**
 * Construye la cadena completa de conexión a una carpeta.
 */
function construirMailboxDesdeSesion(array $sesion, string $folder): string
{
    return construirBaseImapDesdeSesion($sesion) . $folder;
}

/**
 * Construye la base IMAP sin carpeta.
 */
function construirBaseImapDesdeSesion(array $sesion): string
{
    $host = (string) ($sesion['imap_host'] ?? '');
    $port = (int) ($sesion['imap_port'] ?? 993);
    $encryption = (string) ($sesion['imap_encryption'] ?? 'ssl');

    if ($host === '') {
        throw new RuntimeException('Falta servidor IMAP.');
    }

    $opciones = '/imap';

    if ($encryption === 'ssl') {
        $opciones .= '/ssl';
    } elseif ($encryption === 'tls') {
        $opciones .= '/tls';
    } else {
        $opciones .= '/notls';
    }

    return '{' . $host . ':' . $port . $opciones . '}';
}

/**
 * Detecta carpeta de destino para archivo o papelera.
 */
function detectarCarpetaDestinoImap(array $sesion, string $tipo): string
{
    $carpetas = listarCarpetasImap($sesion);

    if ($tipo === 'trash') {
        return buscarCarpetaPorCandidatos($carpetas, [
            '[gmail]/trash',
            '[gmail]/papelera',
            '[google mail]/trash',
            '[google mail]/papelera',
            'trash',
            'papelera',
            'deleted messages',
            'deleted items',
            'bin'
        ]);
    }

    return buscarCarpetaPorCandidatos($carpetas, [
        '[gmail]/all mail',
        '[gmail]/todos',
        '[google mail]/all mail',
        '[google mail]/todos',
        'all mail',
        'todos',
        'archive',
        'archives',
        'archivados',
        'archivo'
    ]);
}

/**
 * Detecta carpetas especiales como Enviados.
 */
function detectarCarpetaEspecialImap(array $sesion, string $tipo): string
{
    $carpetas = listarCarpetasImap($sesion);

    if ($tipo === 'sent') {
        return buscarCarpetaPorCandidatos($carpetas, [
            '[gmail]/sent mail',
            '[gmail]/enviados',
            '[google mail]/sent mail',
            '[google mail]/enviados',
            'sent',
            'sent mail',
            'enviados',
            'elementos enviados',
            'sent items'
        ]);
    }

    return '';
}

/**
 * Lista carpetas IMAP disponibles.
 */
function listarCarpetasImap(array $sesion): array
{
    $imap = abrirBuzonImap($sesion, 'INBOX', true);
    $base = construirBaseImapDesdeSesion($sesion);

    try {
        $lista = imap_list($imap, $base, '*');

        if (!$lista) {
            return [];
        }

        $carpetas = [];

        foreach ($lista as $mailboxCompleto) {
            $carpetas[] = str_replace($base, '', $mailboxCompleto);
        }

        return $carpetas;
    } finally {
        if (is_resource($imap) || $imap instanceof IMAP\Connection) {
            imap_close($imap);
        }
    }
}

/**
 * Busca una carpeta entre candidatos.
 */
function buscarCarpetaPorCandidatos(array $carpetas, array $candidatos): string
{
    foreach ($carpetas as $carpeta) {
        $normalizada = normalizarNombreCarpeta($carpeta);

        foreach ($candidatos as $candidato) {
            if ($normalizada === normalizarNombreCarpeta($candidato)) {
                return $carpeta;
            }
        }
    }

    foreach ($carpetas as $carpeta) {
        $normalizada = normalizarNombreCarpeta($carpeta);

        foreach ($candidatos as $candidato) {
            if (str_contains($normalizada, normalizarNombreCarpeta($candidato))) {
                return $carpeta;
            }
        }
    }

    return '';
}

/**
 * Normaliza nombre de carpeta para comparar.
 */
function normalizarNombreCarpeta(string $texto): string
{
    $texto = strtolower(trim($texto));

    $reemplazos = [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'ñ' => 'n'
    ];

    return strtr($texto, $reemplazos);
}

/**
 * Asegura que un UID sigue existiendo. Si no, intenta buscar por Message-ID.
 */
function asegurarUidValido(array $sesion, string $remoteFolder, string $uid, string $messageId): string
{
    if ($uid !== '' && existeUidEnCarpeta($sesion, $remoteFolder, $uid)) {
        return $uid;
    }

    if ($messageId !== '') {
        return buscarUidPorMessageId($sesion, $remoteFolder, $messageId);
    }

    return '';
}

/**
 * Comprueba si un UID existe en una carpeta.
 */
function existeUidEnCarpeta(array $sesion, string $remoteFolder, string $uid): bool
{
    $imap = abrirBuzonImap($sesion, $remoteFolder, true);

    try {
        $overview = @imap_fetch_overview($imap, $uid, FT_UID);
        return is_array($overview) && !empty($overview);
    } finally {
        if (is_resource($imap) || $imap instanceof IMAP\Connection) {
            imap_close($imap);
        }
    }
}

/**
 * Busca UID por Message-ID en una carpeta.
 */
function buscarUidPorMessageId(array $sesion, string $remoteFolder, string $messageId): string
{
    $messageId = trim($messageId);

    if ($messageId === '') {
        return '';
    }

    $imap = abrirBuzonImap($sesion, $remoteFolder, true);

    try {
        $messageIdBuscado = normalizarMessageId($messageId);
        $uids = imap_search($imap, 'ALL', SE_UID);

        if (!$uids) {
            return '';
        }

        sort($uids, SORT_NUMERIC);
        $uids = array_reverse($uids);
        $uids = array_slice($uids, 0, 500);

        foreach ($uids as $uid) {
            $uid = (int) $uid;
            $header = @imap_fetchheader($imap, $uid, FT_UID);
            $headers = @imap_rfc822_parse_headers((string) $header);

            if (!$headers) {
                continue;
            }

            $messageIdActual = normalizarMessageId((string) ($headers->message_id ?? ''));

            if ($messageIdActual !== '' && $messageIdActual === $messageIdBuscado) {
                return (string) $uid;
            }
        }

        return '';
    } finally {
        if (is_resource($imap) || $imap instanceof IMAP\Connection) {
            imap_close($imap);
        }
    }
}

/**
 * Normaliza Message-ID.
 */
function normalizarMessageId(string $messageId): string
{
    $messageId = trim($messageId);
    $messageId = trim($messageId, '<>');
    return strtolower($messageId);
}

/**
 * Construye un correo normalizado desde IMAP.
 */
function construirCorreoDesdeImap($imap, int $uid, string $correoCuenta, string $remoteFolder, string $localFolder): array
{
    $overviewList = imap_fetch_overview($imap, (string) $uid, FT_UID);

    if (!$overviewList || empty($overviewList[0])) {
        throw new RuntimeException('No se pudo leer el resumen del correo UID ' . $uid);
    }

    $overview = $overviewList[0];

    $rawHeader = imap_fetchheader($imap, $uid, FT_UID);
    $headers = @imap_rfc822_parse_headers($rawHeader);

    if (!$headers) {
        $headers = new stdClass();
    }

    $subjectRaw = $headers->subject ?? ($overview->subject ?? 'Sin asunto');
    $subject = decodificarCabeceraMime((string) $subjectRaw);

    $messageId = limpiarCabeceraTexto($headers->message_id ?? ($overview->message_id ?? ''));
    $inReplyTo = limpiarCabeceraTexto($headers->in_reply_to ?? '');
    $references = limpiarCabeceraTexto($headers->references ?? '');

    $from = obtenerRemitenteDesdeHeaders($headers, $overview);
    $to = obtenerDestinatarioDesdeHeaders($headers, $correoCuenta);

    $fechaOriginal = (string) ($headers->date ?? ($overview->date ?? 'now'));
    $timestamp = strtotime($fechaOriginal);

    if ($timestamp === false) {
        $timestamp = time();
    }

    $emailDate = date('Y-m-d H:i:s', $timestamp);
    $displayDate = formatearFechaCorreo($timestamp);

    $estructura = imap_fetchstructure($imap, $uid, FT_UID);
    $adjuntos = detectarAdjuntosCorreo($estructura);

    $bodyHtml = obtenerCuerpoCorreo($imap, $uid, $estructura);
    $bodyHtml = limpiarHtmlCorreo($bodyHtml);

    $summary = crearResumenDesdeHtml($bodyHtml, 120);

    $threadKey = $inReplyTo !== ''
        ? $inReplyTo
        : ($references !== '' ? $references : ($messageId !== '' ? $messageId : 'uid-' . $uid));

    return [
        'imap_uid' => (string) $uid,
        'remote_folder' => $remoteFolder,
        'message_id' => $messageId,
        'in_reply_to' => $inReplyTo,
        'references_header' => $references,
        'thread_key' => $threadKey,
        'folder' => $localFolder,
        'sender_name' => $from['name'],
        'sender_email' => $from['email'],
        'recipient_email' => $to,
        'subject' => $subject !== '' ? $subject : 'Sin asunto',
        'summary' => $summary,
        'body_html' => $bodyHtml,
        'email_date' => $emailDate,
        'display_date' => $displayDate,
        'is_read' => !empty($overview->seen) ? 1 : 0,
        'status' => 'pendiente',
        'priority' => 'media',
        'type' => 'cliente',
        'has_attachments' => count($adjuntos) > 0 ? 1 : 0,
        'attachments_json' => json_encode($adjuntos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ];
}

/**
 * Decodifica cabeceras MIME como asuntos y nombres.
 */
function decodificarCabeceraMime(string $texto): string
{
    if ($texto === '') {
        return '';
    }

    $partes = @imap_mime_header_decode($texto);

    if (!$partes) {
        return trim(imap_utf8($texto));
    }

    $resultado = '';

    foreach ($partes as $parte) {
        $charset = strtoupper((string) ($parte->charset ?? 'UTF-8'));
        $fragmento = (string) ($parte->text ?? '');

        if ($charset !== 'DEFAULT' && $charset !== 'UTF-8' && function_exists('mb_convert_encoding')) {
            $fragmento = @mb_convert_encoding($fragmento, 'UTF-8', $charset);
        }

        $resultado .= $fragmento;
    }

    return trim($resultado);
}

/**
 * Limpia texto de cabecera.
 */
function limpiarCabeceraTexto($valor): string
{
    return trim((string) $valor);
}

/**
 * Obtiene remitente.
 */
function obtenerRemitenteDesdeHeaders($headers, object $overview): array
{
    if (isset($headers->from[0])) {
        $from = $headers->from[0];

        $email = '';
        $name = '';

        if (!empty($from->mailbox) && !empty($from->host)) {
            $email = $from->mailbox . '@' . $from->host;
        }

        if (!empty($from->personal)) {
            $name = decodificarCabeceraMime((string) $from->personal);
        }

        if ($name === '') {
            $name = $email !== '' ? $email : 'Remitente desconocido';
        }

        return [
            'name' => $name,
            'email' => $email !== '' ? $email : 'sin-correo'
        ];
    }

    $fromText = decodificarCabeceraMime((string) ($overview->from ?? ''));

    return [
        'name' => $fromText !== '' ? $fromText : 'Remitente desconocido',
        'email' => extraerEmailDesdeTexto($fromText)
    ];
}

/**
 * Obtiene destinatario principal.
 */
function obtenerDestinatarioDesdeHeaders($headers, string $correoCuenta): string
{
    if (isset($headers->to[0])) {
        $to = $headers->to[0];

        if (!empty($to->mailbox) && !empty($to->host)) {
            return $to->mailbox . '@' . $to->host;
        }
    }

    return $correoCuenta;
}

/**
 * Extrae email de un texto.
 */
function extraerEmailDesdeTexto(string $texto): string
{
    if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $texto, $coincidencias)) {
        return $coincidencias[0];
    }

    return 'sin-correo';
}

/**
 * Obtiene el mejor cuerpo disponible del correo.
 */
function obtenerCuerpoCorreo($imap, int $uid, $estructura = null): string
{
    if (!$estructura) {
        $estructura = imap_fetchstructure($imap, $uid, FT_UID);
    }

    if (!$estructura) {
        $body = imap_body($imap, $uid, FT_UID | FT_PEEK);
        return nl2br(htmlspecialchars(trim((string) $body), ENT_QUOTES, 'UTF-8'));
    }

    $html = '';
    $plain = '';

    recorrerPartesCorreo($imap, $uid, $estructura, '', $html, $plain);

    if (trim($html) !== '') {
        return $html;
    }

    if (trim($plain) !== '') {
        return nl2br(htmlspecialchars(trim($plain), ENT_QUOTES, 'UTF-8'));
    }

    $body = imap_body($imap, $uid, FT_UID | FT_PEEK);

    return nl2br(htmlspecialchars(trim((string) $body), ENT_QUOTES, 'UTF-8'));
}

/**
 * Recorre partes del correo buscando HTML o texto plano.
 */
function recorrerPartesCorreo($imap, int $uid, object $estructura, string $numeroParte, string &$html, string &$plain): void
{
    if (isset($estructura->parts) && is_array($estructura->parts)) {
        foreach ($estructura->parts as $indice => $parte) {
            $nuevoNumero = $numeroParte === ''
                ? (string) ($indice + 1)
                : $numeroParte . '.' . ($indice + 1);

            recorrerPartesCorreo($imap, $uid, $parte, $nuevoNumero, $html, $plain);
        }

        return;
    }

    if ((int) ($estructura->type ?? -1) !== 0) {
        return;
    }

    $subtipo = strtolower((string) ($estructura->subtype ?? ''));

    $body = $numeroParte === ''
        ? imap_body($imap, $uid, FT_UID | FT_PEEK)
        : imap_fetchbody($imap, $uid, $numeroParte, FT_UID | FT_PEEK);

    $body = decodificarTransferencia((string) $body, (int) ($estructura->encoding ?? 0));
    $body = convertirCharset($body, obtenerCharsetParte($estructura));

    if ($subtipo === 'html' && trim($html) === '') {
        $html = $body;
    }

    if ($subtipo === 'plain' && trim($plain) === '') {
        $plain = $body;
    }
}

/**
 * Detecta adjuntos sin descargarlos.
 */
function detectarAdjuntosCorreo($estructura): array
{
    if (!$estructura) {
        return [];
    }

    $adjuntos = [];
    recorrerAdjuntosCorreo($estructura, '', $adjuntos);

    return $adjuntos;
}

/**
 * Recorre partes buscando posibles adjuntos.
 */
function recorrerAdjuntosCorreo(object $parte, string $numeroParte, array &$adjuntos): void
{
    if (isset($parte->parts) && is_array($parte->parts)) {
        foreach ($parte->parts as $indice => $subParte) {
            $nuevoNumero = $numeroParte === ''
                ? (string) ($indice + 1)
                : $numeroParte . '.' . ($indice + 1);

            recorrerAdjuntosCorreo($subParte, $nuevoNumero, $adjuntos);
        }
    }

    $nombre = obtenerNombreArchivoParte($parte);
    $disposition = strtolower((string) ($parte->disposition ?? ''));

    $esAdjunto = $nombre !== '' || $disposition === 'attachment';

    if (!$esAdjunto) {
        return;
    }

    $tipo = obtenerTipoMimeParte($parte);

    $adjuntos[] = [
        'part' => $numeroParte !== '' ? $numeroParte : '1',
        'name' => $nombre !== '' ? $nombre : 'archivo-adjunto',
        'type' => $tipo,
        'size' => isset($parte->bytes) ? (int) $parte->bytes : 0,
        'sizeLabel' => isset($parte->bytes) ? formatearTamanoArchivo((int) $parte->bytes) : 'Tamaño desconocido'
    ];
}

/**
 * Obtiene nombre del archivo adjunto.
 */
function obtenerNombreArchivoParte(object $parte): string
{
    $atributos = [];

    if (!empty($parte->dparameters) && is_array($parte->dparameters)) {
        foreach ($parte->dparameters as $parametro) {
            $atributos[strtolower((string) ($parametro->attribute ?? ''))] = (string) ($parametro->value ?? '');
        }
    }

    if (!empty($parte->parameters) && is_array($parte->parameters)) {
        foreach ($parte->parameters as $parametro) {
            $atributos[strtolower((string) ($parametro->attribute ?? ''))] = (string) ($parametro->value ?? '');
        }
    }

    $nombre = $atributos['filename'] ?? ($atributos['name'] ?? '');

    return decodificarCabeceraMime($nombre);
}

/**
 * Obtiene tipo MIME aproximado de una parte.
 */
function obtenerTipoMimeParte(object $parte): string
{
    $tipos = [
        0 => 'text',
        1 => 'multipart',
        2 => 'message',
        3 => 'application',
        4 => 'audio',
        5 => 'image',
        6 => 'video',
        7 => 'other'
    ];

    $tipoBase = $tipos[(int) ($parte->type ?? 7)] ?? 'other';
    $subtipo = strtolower((string) ($parte->subtype ?? ''));

    if ($subtipo !== '') {
        return $tipoBase . '/' . $subtipo;
    }

    return $tipoBase;
}

/**
 * Formatea tamaño de archivo.
 */
function formatearTamanoArchivo(int $bytes): string
{
    if ($bytes <= 0) {
        return 'Tamaño desconocido';
    }

    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    if ($bytes < 1024 * 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return round($bytes / (1024 * 1024), 1) . ' MB';
}

/**
 * Decodifica transfer encoding.
 */
function decodificarTransferencia(string $body, int $encoding): string
{
    if ($encoding === 3) {
        return base64_decode($body) ?: '';
    }

    if ($encoding === 4) {
        return quoted_printable_decode($body);
    }

    return $body;
}

/**
 * Obtiene charset de una parte.
 */
function obtenerCharsetParte(object $parte): string
{
    if (!empty($parte->parameters) && is_array($parte->parameters)) {
        foreach ($parte->parameters as $parametro) {
            if (strtolower((string) ($parametro->attribute ?? '')) === 'charset') {
                return (string) ($parametro->value ?? 'UTF-8');
            }
        }
    }

    if (!empty($parte->dparameters) && is_array($parte->dparameters)) {
        foreach ($parte->dparameters as $parametro) {
            if (strtolower((string) ($parametro->attribute ?? '')) === 'charset') {
                return (string) ($parametro->value ?? 'UTF-8');
            }
        }
    }

    return 'UTF-8';
}

/**
 * Convierte cuerpo a UTF-8.
 */
function convertirCharset(string $texto, string $charset): string
{
    $charset = strtoupper(trim($charset));

    if ($charset === '' || $charset === 'UTF-8' || !function_exists('mb_convert_encoding')) {
        return $texto;
    }

    $convertido = @mb_convert_encoding($texto, 'UTF-8', $charset);

    return $convertido !== false ? $convertido : $texto;
}

/**
 * Limpieza básica del HTML del correo.
 */
function limpiarHtmlCorreo(string $html): string
{
    $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
    $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
    $html = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $html);
    $html = preg_replace('/<object\b[^>]*>(.*?)<\/object>/is', '', $html);
    $html = preg_replace('/<embed\b[^>]*>/is', '', $html);
    $html = preg_replace('/<form\b[^>]*>(.*?)<\/form>/is', '', $html);
    $html = preg_replace('/\son\w+="[^"]*"/i', '', $html);
    $html = preg_replace("/\son\w+='[^']*'/i", '', $html);
    $html = preg_replace('/javascript:/i', '', $html);

    $html = trim((string) $html);

    if ($html === '') {
        return '<p>Mensaje sin contenido.</p>';
    }

    return $html;
}

/**
 * Formatea la fecha para la interfaz.
 */
function formatearFechaCorreo(int $timestamp): string
{
    $hoy = date('Y-m-d');
    $fecha = date('Y-m-d', $timestamp);

    if ($fecha === $hoy) {
        return 'Hoy · ' . date('H:i', $timestamp);
    }

    if ($fecha === date('Y-m-d', strtotime('-1 day'))) {
        return 'Ayer · ' . date('H:i', $timestamp);
    }

    return date('d/m/Y · H:i', $timestamp);
}