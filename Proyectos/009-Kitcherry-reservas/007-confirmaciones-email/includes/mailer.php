<?php
// ==========================================================
// KITCHERRY RESERVAS
// Archivo: includes/mailer.php
// Envío de correos SMTP usando variables de entorno
// ==========================================================

function smtpLeerRespuesta($socket) {
    $respuesta = '';

    while ($linea = fgets($socket, 515)) {
        $respuesta .= $linea;

        if (isset($linea[3]) && $linea[3] === ' ') {
            break;
        }
    }

    return $respuesta;
}

function smtpCodigoRespuesta($respuesta) {
    return (int)substr($respuesta, 0, 3);
}

function smtpEnviarComando($socket, $comando, $codigosEsperados) {
    fwrite($socket, $comando . "\r\n");

    $respuesta = smtpLeerRespuesta($socket);
    $codigo = smtpCodigoRespuesta($respuesta);

    if (!in_array($codigo, $codigosEsperados, true)) {
        throw new Exception("Error SMTP: " . trim($respuesta));
    }

    return $respuesta;
}

function codificarAsunto($texto) {
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($texto, 'UTF-8', 'B', "\r\n");
    }

    return '=?UTF-8?B?' . base64_encode($texto) . '?=';
}

function normalizarSaltosCorreo($texto) {
    $texto = str_replace(["\r\n", "\r"], "\n", $texto);
    return str_replace("\n", "\r\n", $texto);
}

function enviarCorreoSMTP($destinatario, $asunto, $cuerpo, $nombreRemitente = 'Kitcherry Reservas') {
    $correo = getenv("MI_CORREO_KITCHERRY");
    $password = getenv("MI_CONTRASENA_CORREO_KITCHERRY");
    $smtpServer = getenv("MI_SERVIDORSMTP_CORREO_KITCHERRY");
    $smtpPort = (int)getenv("MI_PUERTOSMTP_CORREO_KITCHERRY");

    if (!$correo || !$password || !$smtpServer || !$smtpPort) {
        throw new Exception("Faltan variables de entorno SMTP.");
    }

    if (!$destinatario) {
        throw new Exception("No hay destinatario para enviar el correo.");
    }

    $hostConexion = ($smtpPort === 465)
        ? "ssl://" . $smtpServer
        : $smtpServer;

    $socket = stream_socket_client(
        $hostConexion . ":" . $smtpPort,
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        throw new Exception("No se pudo conectar con SMTP: " . $errstr);
    }

    stream_set_timeout($socket, 30);

    $respuesta = smtpLeerRespuesta($socket);

    if (smtpCodigoRespuesta($respuesta) !== 220) {
        fclose($socket);
        throw new Exception("Respuesta SMTP inesperada: " . trim($respuesta));
    }

    smtpEnviarComando($socket, "EHLO localhost", [250]);

    if ($smtpPort === 587) {
        smtpEnviarComando($socket, "STARTTLS", [220]);

        $cryptoOk = stream_socket_enable_crypto(
            $socket,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
        );

        if (!$cryptoOk) {
            fclose($socket);
            throw new Exception("No se pudo iniciar STARTTLS.");
        }

        smtpEnviarComando($socket, "EHLO localhost", [250]);
    }

    smtpEnviarComando($socket, "AUTH LOGIN", [334]);
    smtpEnviarComando($socket, base64_encode($correo), [334]);
    smtpEnviarComando($socket, base64_encode($password), [235]);

    smtpEnviarComando($socket, "MAIL FROM:<" . $correo . ">", [250]);
    smtpEnviarComando($socket, "RCPT TO:<" . $destinatario . ">", [250, 251]);
    smtpEnviarComando($socket, "DATA", [354]);

    $asuntoCodificado = codificarAsunto($asunto);
    $nombreCodificado = codificarAsunto($nombreRemitente);
    $cuerpo = normalizarSaltosCorreo($cuerpo);

    $headers = [];
    $headers[] = "Date: " . date(DATE_RFC2822);
    $headers[] = "From: " . $nombreCodificado . " <" . $correo . ">";
    $headers[] = "Reply-To: " . $correo;
    $headers[] = "To: <" . $destinatario . ">";
    $headers[] = "Subject: " . $asuntoCodificado;
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/plain; charset=UTF-8";
    $headers[] = "Content-Transfer-Encoding: 8bit";

    $mensaje = implode("\r\n", $headers);
    $mensaje .= "\r\n\r\n";
    $mensaje .= $cuerpo;
    $mensaje .= "\r\n.";

    smtpEnviarComando($socket, $mensaje, [250]);
    smtpEnviarComando($socket, "QUIT", [221]);

    fclose($socket);

    return true;
}