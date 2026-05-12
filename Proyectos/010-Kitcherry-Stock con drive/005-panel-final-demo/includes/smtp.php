<?php
// ==========================================================
// KITCHERRY STOCK
// Archivo: includes/smtp.php
// Envío de correos mediante SMTP usando variables de entorno
// ==========================================================

function obtenerConfigSMTP()
{
    $correo = getenv("MI_CORREO_KITCHERRY");
    $password = getenv("MI_CONTRASENA_CORREO_KITCHERRY");
    $servidor = getenv("MI_SERVIDORSMTP_CORREO_KITCHERRY");
    $puerto = getenv("MI_PUERTOSMTP_CORREO_KITCHERRY");

    if (!$correo || !$password || !$servidor || !$puerto) {
        throw new Exception(
            "Faltan variables de entorno SMTP. Revisa MI_CORREO_KITCHERRY, MI_CONTRASENA_CORREO_KITCHERRY, MI_SERVIDORSMTP_CORREO_KITCHERRY y MI_PUERTOSMTP_CORREO_KITCHERRY."
        );
    }

    return [
        "correo" => $correo,
        "password" => $password,
        "servidor" => $servidor,
        "puerto" => (int)$puerto,
    ];
}

function enviarCorreoSMTP($destinatario, $asunto, $html, $textoPlano = "")
{
    $config = obtenerConfigSMTP();

    $correo = $config["correo"];
    $password = $config["password"];
    $servidor = $config["servidor"];
    $puerto = $config["puerto"];

    if ($textoPlano === "") {
        $textoPlano = strip_tags(str_replace(["<br>", "<br/>", "<br />"], "\n", $html));
    }

    $esSSL = $puerto === 465;
    $hostSocket = $esSSL ? "ssl://" . $servidor : $servidor;

    $socket = fsockopen($hostSocket, $puerto, $errno, $errstr, 30);

    if (!$socket) {
        throw new Exception("No se pudo conectar al servidor SMTP: $errstr ($errno)");
    }

    stream_set_timeout($socket, 30);

    smtpLeerRespuesta($socket, [220]);

    smtpComando($socket, "EHLO localhost", [250]);

    if ($puerto === 587) {
        smtpComando($socket, "STARTTLS", [220]);

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception("No se pudo iniciar STARTTLS.");
        }

        smtpComando($socket, "EHLO localhost", [250]);
    }

    smtpComando($socket, "AUTH LOGIN", [334]);
    smtpComando($socket, base64_encode($correo), [334]);
    smtpComando($socket, base64_encode($password), [235]);

    smtpComando($socket, "MAIL FROM:<$correo>", [250]);
    smtpComando($socket, "RCPT TO:<$destinatario>", [250, 251]);
    smtpComando($socket, "DATA", [354]);

    $boundary = "KITCHERRY_" . md5(uniqid("", true));

    $asuntoCodificado = function_exists("mb_encode_mimeheader")
        ? mb_encode_mimeheader($asunto, "UTF-8", "B", "\r\n")
        : "=?UTF-8?B?" . base64_encode($asunto) . "?=";

    $headers = "";
    $headers .= "Date: " . date("r") . "\r\n";
    $headers .= "From: Kitcherry Stock <$correo>\r\n";
    $headers .= "To: <$destinatario>\r\n";
    $headers .= "Subject: $asuntoCodificado\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

    $mensaje = "";
    $mensaje .= $headers . "\r\n";

    $mensaje .= "--$boundary\r\n";
    $mensaje .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $mensaje .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $mensaje .= $textoPlano . "\r\n\r\n";

    $mensaje .= "--$boundary\r\n";
    $mensaje .= "Content-Type: text/html; charset=UTF-8\r\n";
    $mensaje .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $mensaje .= $html . "\r\n\r\n";

    $mensaje .= "--$boundary--\r\n";

    $mensaje = str_replace("\r\n.", "\r\n..", $mensaje);

    fwrite($socket, $mensaje . "\r\n.\r\n");

    smtpLeerRespuesta($socket, [250]);

    smtpComando($socket, "QUIT", [221]);

    fclose($socket);

    return true;
}

function smtpComando($socket, $comando, $codigosEsperados)
{
    fwrite($socket, $comando . "\r\n");
    return smtpLeerRespuesta($socket, $codigosEsperados);
}

function smtpLeerRespuesta($socket, $codigosEsperados)
{
    $respuesta = "";

    while ($linea = fgets($socket, 515)) {
        $respuesta .= $linea;

        if (isset($linea[3]) && $linea[3] === " ") {
            break;
        }
    }

    $codigo = (int)substr($respuesta, 0, 3);

    if (!in_array($codigo, $codigosEsperados)) {
        throw new Exception("Respuesta SMTP inesperada: " . trim($respuesta));
    }

    return $respuesta;
}