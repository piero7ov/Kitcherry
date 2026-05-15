<?php
// ==========================================================
// KITCHERRY MAIL
// Servicio SMTP por sockets.
// Envío real de correos sin librerías externas.
// ==========================================================

declare(strict_types=1);

/**
 * Envía un correo por SMTP usando sockets.
 */
function enviarCorreoSmtp(array $sesion, array $datos): array
{
    $emailUsuario = trim((string) ($sesion['email'] ?? ''));
    $password = (string) ($sesion['password'] ?? '');
    $smtpHost = trim((string) ($sesion['smtp_host'] ?? ''));
    $smtpPort = (int) ($sesion['smtp_port'] ?? 587);
    $smtpEncryption = trim((string) ($sesion['smtp_encryption'] ?? 'tls'));

    if ($emailUsuario === '' || $password === '' || $smtpHost === '') {
        throw new RuntimeException('Faltan datos SMTP en la sesión.');
    }

    $destinatarios = normalizarDestinatariosSmtp((string) ($datos['to'] ?? ''));

    if (!$destinatarios) {
        throw new RuntimeException('No hay destinatarios válidos.');
    }

    $asunto = trim((string) ($datos['subject'] ?? ''));

    if ($asunto === '') {
        $asunto = 'Sin asunto';
    }

    $html = trim((string) ($datos['body_html'] ?? ''));

    if ($html === '') {
        $html = '<p>Mensaje sin contenido.</p>';
    }

    $messageId = generarMessageIdSmtp($emailUsuario);
    $fecha = date('r');

    $headers = construirHeadersSmtp([
        'from_email' => $emailUsuario,
        'from_name' => 'Kitcherry Mail',
        'to' => implode(', ', $destinatarios),
        'subject' => $asunto,
        'message_id' => $messageId,
        'in_reply_to' => trim((string) ($datos['in_reply_to'] ?? '')),
        'references' => trim((string) ($datos['references'] ?? '')),
        'date' => $fecha
    ]);

    $body = quoted_printable_encode($html);

    $mensaje = $headers
        . "\r\n"
        . $body;

    $socket = abrirSocketSmtp($smtpHost, $smtpPort, $smtpEncryption);

    try {
        smtpEsperar($socket, [220]);

        smtpEnviarLinea($socket, 'EHLO localhost');
        smtpEsperar($socket, [250]);

        if ($smtpEncryption === 'tls') {
            smtpEnviarLinea($socket, 'STARTTLS');
            smtpEsperar($socket, [220]);

            $cryptoOk = stream_socket_enable_crypto(
                $socket,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT
            );

            if ($cryptoOk !== true) {
                throw new RuntimeException('No se pudo activar STARTTLS en la conexión SMTP.');
            }

            smtpEnviarLinea($socket, 'EHLO localhost');
            smtpEsperar($socket, [250]);
        }

        smtpEnviarLinea($socket, 'AUTH LOGIN');
        smtpEsperar($socket, [334]);

        smtpEnviarLinea($socket, base64_encode($emailUsuario));
        smtpEsperar($socket, [334]);

        smtpEnviarLinea($socket, base64_encode($password));
        smtpEsperar($socket, [235]);

        smtpEnviarLinea($socket, 'MAIL FROM:<' . $emailUsuario . '>');
        smtpEsperar($socket, [250]);

        foreach ($destinatarios as $destinatario) {
            smtpEnviarLinea($socket, 'RCPT TO:<' . $destinatario . '>');
            smtpEsperar($socket, [250, 251]);
        }

        smtpEnviarLinea($socket, 'DATA');
        smtpEsperar($socket, [354]);

        smtpEnviarLinea($socket, prepararMensajeDataSmtp($mensaje) . "\r\n.");
        smtpEsperar($socket, [250]);

        smtpEnviarLinea($socket, 'QUIT');
    } finally {
        if (is_resource($socket)) {
            fclose($socket);
        }
    }

    return [
        'message_id' => $messageId,
        'to' => implode(', ', $destinatarios),
        'subject' => $asunto,
        'body_html' => $html
    ];
}

/**
 * Abre socket SMTP.
 */
function abrirSocketSmtp(string $host, int $port, string $encryption)
{
    $transport = $host;

    if ($encryption === 'ssl') {
        $transport = 'ssl://' . $host;
    }

    $errno = 0;
    $errstr = '';

    $socket = @fsockopen($transport, $port, $errno, $errstr, 30);

    if (!$socket) {
        throw new RuntimeException('No se pudo conectar al servidor SMTP: ' . $errstr);
    }

    stream_set_timeout($socket, 30);

    return $socket;
}

/**
 * Envía una línea al socket SMTP.
 */
function smtpEnviarLinea($socket, string $linea): void
{
    fwrite($socket, $linea . "\r\n");
}

/**
 * Lee respuesta SMTP completa.
 */
function smtpLeerRespuesta($socket): string
{
    $respuesta = '';

    while (($linea = fgets($socket, 515)) !== false) {
        $respuesta .= $linea;

        if (strlen($linea) >= 4 && $linea[3] === ' ') {
            break;
        }
    }

    return trim($respuesta);
}

/**
 * Espera códigos SMTP válidos.
 */
function smtpEsperar($socket, array $codigosEsperados): string
{
    $respuesta = smtpLeerRespuesta($socket);

    if ($respuesta === '') {
        throw new RuntimeException('El servidor SMTP no respondió.');
    }

    $codigo = (int) substr($respuesta, 0, 3);

    if (!in_array($codigo, $codigosEsperados, true)) {
        throw new RuntimeException('Respuesta SMTP inesperada: ' . $respuesta);
    }

    return $respuesta;
}

/**
 * Construye cabeceras del correo.
 */
function construirHeadersSmtp(array $datos): string
{
    $fromName = codificarMimeHeaderSmtp((string) $datos['from_name']);
    $subject = codificarMimeHeaderSmtp((string) $datos['subject']);

    $headers = [];

    $headers[] = 'Date: ' . $datos['date'];
    $headers[] = 'From: ' . $fromName . ' <' . $datos['from_email'] . '>';
    $headers[] = 'Reply-To: ' . $datos['from_email'];
    $headers[] = 'To: ' . $datos['to'];
    $headers[] = 'Subject: ' . $subject;
    $headers[] = 'Message-ID: ' . $datos['message_id'];

    if (!empty($datos['in_reply_to'])) {
        $headers[] = 'In-Reply-To: ' . $datos['in_reply_to'];
    }

    if (!empty($datos['references'])) {
        $headers[] = 'References: ' . $datos['references'];
    }

    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: quoted-printable';
    $headers[] = 'X-Mailer: Kitcherry Mail';

    return implode("\r\n", $headers) . "\r\n";
}

/**
 * Codifica cabeceras MIME.
 */
function codificarMimeHeaderSmtp(string $texto): string
{
    $texto = trim($texto);

    if ($texto === '') {
        return '';
    }

    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($texto, 'UTF-8', 'B', "\r\n");
    }

    return '=?UTF-8?B?' . base64_encode($texto) . '?=';
}

/**
 * Normaliza lista de destinatarios.
 */
function normalizarDestinatariosSmtp(string $to): array
{
    $partes = preg_split('/[,;]+/', $to);
    $destinatarios = [];

    foreach ($partes as $parte) {
        $parte = trim($parte);

        if ($parte === '') {
            continue;
        }

        if (preg_match('/<([^>]+)>/', $parte, $coincidencias)) {
            $parte = trim($coincidencias[1]);
        }

        if (filter_var($parte, FILTER_VALIDATE_EMAIL)) {
            $destinatarios[] = $parte;
        }
    }

    return array_values(array_unique($destinatarios));
}

/**
 * Genera Message-ID.
 */
function generarMessageIdSmtp(string $email): string
{
    $dominio = 'kitcherry.local';

    if (str_contains($email, '@')) {
        $dominio = substr(strrchr($email, '@'), 1) ?: $dominio;
    }

    return '<' . bin2hex(random_bytes(16)) . '@' . $dominio . '>';
}

/**
 * Prepara mensaje para DATA.
 * SMTP exige duplicar puntos al inicio de línea.
 */
function prepararMensajeDataSmtp(string $mensaje): string
{
    $mensaje = str_replace(["\r\n", "\r"], "\n", $mensaje);
    $lineas = explode("\n", $mensaje);

    foreach ($lineas as &$linea) {
        if (str_starts_with($linea, '.')) {
            $linea = '.' . $linea;
        }
    }

    return implode("\r\n", $lineas);
}