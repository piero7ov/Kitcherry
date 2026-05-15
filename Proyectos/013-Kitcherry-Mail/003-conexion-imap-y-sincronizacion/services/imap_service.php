<?php
// ==========================================================
// KITCHERRY MAIL
// Servicio de sincronización IMAP.
// ==========================================================

declare(strict_types=1);

/**
 * Sincroniza los últimos correos de INBOX usando IMAP.
 */
function sincronizarInboxImap(PDO $pdo, array $sesion, int $limite = 30): array
{
    if (!extension_loaded('imap')) {
        throw new RuntimeException('La extensión IMAP de PHP no está activa. Activa extension=imap en php.ini y reinicia Apache.');
    }

    $accountId = (int) ($sesion['account_id'] ?? 0);
    $email = (string) ($sesion['email'] ?? '');
    $password = (string) ($sesion['password'] ?? '');
    $host = (string) ($sesion['imap_host'] ?? '');
    $port = (int) ($sesion['imap_port'] ?? 993);
    $encryption = (string) ($sesion['imap_encryption'] ?? 'ssl');

    if ($accountId <= 0 || $email === '' || $password === '' || $host === '') {
        throw new RuntimeException('Faltan datos de sesión para sincronizar por IMAP.');
    }

    $mailbox = construirMailboxImap($host, $port, $encryption);

    $imap = @imap_open($mailbox, $email, $password, OP_READONLY);

    if (!$imap) {
        $error = imap_last_error() ?: 'No se pudo conectar con el servidor IMAP.';
        throw new RuntimeException($error);
    }

    $insertados = 0;
    $actualizados = 0;
    $procesados = 0;

    try {
        $uids = imap_search($imap, 'ALL', SE_UID);

        if (!$uids) {
            imap_close($imap);

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

            $correo = construirCorreoDesdeImap($imap, $uid, $email);

            $resultado = guardarCorreoSincronizado($pdo, $accountId, $correo);

            if ($resultado === 'inserted') {
                $insertados++;
            }

            if ($resultado === 'updated') {
                $actualizados++;
            }

            $procesados++;
        }
    } finally {
        imap_close($imap);
    }

    return [
        'procesados' => $procesados,
        'insertados' => $insertados,
        'actualizados' => $actualizados
    ];
}

/**
 * Construye la cadena de conexión IMAP.
 */
function construirMailboxImap(string $host, int $port, string $encryption): string
{
    $opciones = '/imap';

    if ($encryption === 'ssl') {
        $opciones .= '/ssl';
    } elseif ($encryption === 'tls') {
        $opciones .= '/tls';
    } else {
        $opciones .= '/notls';
    }

    return '{' . $host . ':' . $port . $opciones . '}INBOX';
}

/**
 * Construye un correo normalizado desde IMAP.
 */
function construirCorreoDesdeImap($imap, int $uid, string $correoCuenta): array
{
    $overviewList = imap_fetch_overview($imap, (string) $uid, FT_UID);

    if (!$overviewList || empty($overviewList[0])) {
        throw new RuntimeException('No se pudo leer el resumen del correo UID ' . $uid);
    }

    $overview = $overviewList[0];

    $rawHeader = imap_fetchheader($imap, $uid, FT_UID);
    $headers = @imap_rfc822_parse_headers($rawHeader);

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

    $bodyHtml = obtenerCuerpoCorreo($imap, $uid);
    $bodyHtml = limpiarHtmlCorreo($bodyHtml);

    $summary = crearResumenDesdeHtml($bodyHtml, 120);

    $threadKey = $inReplyTo !== ''
        ? $inReplyTo
        : ($references !== '' ? $references : ($messageId !== '' ? $messageId : 'uid-' . $uid));

    return [
        'imap_uid' => (string) $uid,
        'message_id' => $messageId,
        'in_reply_to' => $inReplyTo,
        'references_header' => $references,
        'thread_key' => $threadKey,
        'folder' => 'inbox',
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
        'type' => 'cliente'
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
        return imap_utf8($texto);
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
function obtenerCuerpoCorreo($imap, int $uid): string
{
    $estructura = imap_fetchstructure($imap, $uid, FT_UID);

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