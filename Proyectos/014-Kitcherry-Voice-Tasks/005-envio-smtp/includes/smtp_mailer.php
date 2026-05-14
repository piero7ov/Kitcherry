<?php
// ==========================================================
// KITCHERRY VOICE TASKS
// Archivo: includes/smtp_mailer.php
// Envío SMTP usando variables de entorno
// ==========================================================

function smtpLimpiarTexto($valor) {
    return trim($valor ?? "");
}

function smtpCodificarHeader($texto) {
    $texto = smtpLimpiarTexto($texto);

    if ($texto === "") {
        return "";
    }

    if (function_exists("mb_encode_mimeheader")) {
        return mb_encode_mimeheader($texto, "UTF-8", "B", "\r\n");
    }

    return "=?UTF-8?B?" . base64_encode($texto) . "?=";
}

function smtpFormatearEmail($email, $nombre = "") {
    $email = smtpLimpiarTexto($email);
    $nombre = smtpLimpiarTexto($nombre);

    if ($nombre === "") {
        return $email;
    }

    return smtpCodificarHeader($nombre) . " <" . $email . ">";
}

function smtpLeerRespuesta($conexion) {
    $respuesta = "";

    while (($linea = fgets($conexion, 515)) !== false) {
        $respuesta .= $linea;

        if (strlen($linea) >= 4 && $linea[3] === " ") {
            break;
        }
    }

    return $respuesta;
}

function smtpCodigoRespuesta($respuesta) {
    return (int)substr($respuesta, 0, 3);
}

function smtpEnviarComando($conexion, $comando, $codigosValidos) {
    fwrite($conexion, $comando . "\r\n");

    $respuesta = smtpLeerRespuesta($conexion);
    $codigo = smtpCodigoRespuesta($respuesta);

    if (!in_array($codigo, $codigosValidos)) {
        throw new Exception("Respuesta SMTP inesperada: " . trim($respuesta));
    }

    return $respuesta;
}

function smtpObtenerConfig() {
    $correo = getenv("MI_CORREO_KITCHERRY") ?: "";
    $password = getenv("MI_CONTRASENA_CORREO_KITCHERRY") ?: "";
    $servidor = getenv("MI_SERVIDORSMTP_CORREO_KITCHERRY") ?: "";
    $puerto = getenv("MI_PUERTOSMTP_CORREO_KITCHERRY") ?: "";

    $correo = smtpLimpiarTexto($correo);
    $password = smtpLimpiarTexto($password);
    $servidor = smtpLimpiarTexto($servidor);
    $puerto = (int)$puerto;

    if ($correo === "" || $password === "" || $servidor === "" || $puerto <= 0) {
        return [
            "ok" => false,
            "mensaje" => "Faltan variables de entorno SMTP.",
            "correo" => $correo,
            "password" => $password,
            "servidor" => $servidor,
            "puerto" => $puerto
        ];
    }

    return [
        "ok" => true,
        "mensaje" => "Configuración SMTP preparada.",
        "correo" => $correo,
        "password" => $password,
        "servidor" => $servidor,
        "puerto" => $puerto
    ];
}

function smtpPrepararDestinatarios($destinatarios) {
    $resultado = [];
    $emailsUsados = [];

    foreach ($destinatarios as $destinatario) {
        $email = smtpLimpiarTexto($destinatario["email"] ?? "");
        $nombre = smtpLimpiarTexto($destinatario["nombre"] ?? "");

        if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        $clave = mb_strtolower($email, "UTF-8");

        if (isset($emailsUsados[$clave])) {
            continue;
        }

        $emailsUsados[$clave] = true;

        $resultado[] = [
            "email" => $email,
            "nombre" => $nombre
        ];
    }

    return $resultado;
}

function smtpConstruirMensaje($config, $destinatarios, $asunto, $cuerpo, $nombreAdjunto, $contenidoAdjunto) {
    $boundary = "=_KITCHERRY_" . md5(uniqid((string)time(), true));
    $fecha = date("r");

    $from = smtpFormatearEmail($config["correo"], "KITCHERRY Voice Tasks");

    $toHeaders = [];

    foreach ($destinatarios as $destinatario) {
        $toHeaders[] = smtpFormatearEmail($destinatario["email"], $destinatario["nombre"]);
    }

    $asuntoCodificado = smtpCodificarHeader($asunto);
    $nombreAdjuntoCodificado = smtpCodificarHeader($nombreAdjunto);

    $headers = [];
    $headers[] = "Date: " . $fecha;
    $headers[] = "From: " . $from;
    $headers[] = "To: " . implode(", ", $toHeaders);
    $headers[] = "Subject: " . $asuntoCodificado;
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: multipart/mixed; boundary=\"" . $boundary . "\"";

    $mensaje = implode("\r\n", $headers);
    $mensaje .= "\r\n\r\n";

    $mensaje .= "--" . $boundary . "\r\n";
    $mensaje .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $mensaje .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $mensaje .= $cuerpo . "\r\n\r\n";

    $mensaje .= "--" . $boundary . "\r\n";
    $mensaje .= "Content-Type: text/plain; name=\"" . $nombreAdjuntoCodificado . "\"\r\n";
    $mensaje .= "Content-Transfer-Encoding: base64\r\n";
    $mensaje .= "Content-Disposition: attachment; filename=\"" . $nombreAdjuntoCodificado . "\"\r\n\r\n";
    $mensaje .= chunk_split(base64_encode($contenidoAdjunto)) . "\r\n";
    $mensaje .= "--" . $boundary . "--\r\n";

    $mensaje = str_replace("\n.", "\n..", $mensaje);

    return $mensaje;
}

function smtpEnviarListaConAdjunto($destinatarios, $asunto, $cuerpo, $nombreAdjunto, $contenidoAdjunto) {
    $config = smtpObtenerConfig();

    if (!$config["ok"]) {
        return [
            "ok" => false,
            "mensaje" => $config["mensaje"]
        ];
    }

    $destinatarios = smtpPrepararDestinatarios($destinatarios);

    if (empty($destinatarios)) {
        return [
            "ok" => false,
            "mensaje" => "No hay destinatarios válidos."
        ];
    }

    $servidor = $config["servidor"];
    $puerto = (int)$config["puerto"];
    $correo = $config["correo"];
    $password = $config["password"];

    $hostConexion = $servidor;

    if ($puerto === 465) {
        $hostConexion = "ssl://" . $servidor;
    }

    $conexion = fsockopen($hostConexion, $puerto, $errno, $errstr, 20);

    if (!$conexion) {
        return [
            "ok" => false,
            "mensaje" => "No se pudo conectar con el servidor SMTP."
        ];
    }

    stream_set_timeout($conexion, 30);

    try {
        $respuestaInicial = smtpLeerRespuesta($conexion);

        if (!in_array(smtpCodigoRespuesta($respuestaInicial), [220])) {
            throw new Exception("El servidor SMTP no respondió correctamente.");
        }

        smtpEnviarComando($conexion, "EHLO localhost", [250]);

        if ($puerto === 587) {
            smtpEnviarComando($conexion, "STARTTLS", [220]);

            $cryptoOk = stream_socket_enable_crypto(
                $conexion,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT
            );

            if (!$cryptoOk) {
                throw new Exception("No se pudo activar STARTTLS.");
            }

            smtpEnviarComando($conexion, "EHLO localhost", [250]);
        }

        smtpEnviarComando($conexion, "AUTH LOGIN", [334]);
        smtpEnviarComando($conexion, base64_encode($correo), [334]);
        smtpEnviarComando($conexion, base64_encode($password), [235]);

        smtpEnviarComando($conexion, "MAIL FROM:<" . $correo . ">", [250]);

        foreach ($destinatarios as $destinatario) {
            smtpEnviarComando($conexion, "RCPT TO:<" . $destinatario["email"] . ">", [250, 251]);
        }

        smtpEnviarComando($conexion, "DATA", [354]);

        $mensaje = smtpConstruirMensaje(
            $config,
            $destinatarios,
            $asunto,
            $cuerpo,
            $nombreAdjunto,
            $contenidoAdjunto
        );

        fwrite($conexion, $mensaje . "\r\n.\r\n");

        $respuestaData = smtpLeerRespuesta($conexion);

        if (!in_array(smtpCodigoRespuesta($respuestaData), [250])) {
            throw new Exception("El servidor no aceptó el mensaje.");
        }

        smtpEnviarComando($conexion, "QUIT", [221]);

        fclose($conexion);

        return [
            "ok" => true,
            "mensaje" => "Lista enviada correctamente.",
            "total_destinatarios" => count($destinatarios)
        ];

    } catch (Exception $e) {
        @fwrite($conexion, "QUIT\r\n");
        @fclose($conexion);

        return [
            "ok" => false,
            "mensaje" => $e->getMessage()
        ];
    }
}