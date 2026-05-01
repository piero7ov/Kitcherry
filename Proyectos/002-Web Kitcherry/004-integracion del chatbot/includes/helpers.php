<?php
// ==========================================================
// KITCHERRY - FUNCIONES AUXILIARES
// Archivo: includes/helpers.php
// ==========================================================

function e($dato) {
    return htmlspecialchars((string) $dato, ENT_QUOTES, "UTF-8");
}

function limpiar($dato) {
    return htmlspecialchars(trim($dato), ENT_QUOTES, "UTF-8");
}

function limpiarParaCabecera($dato) {
    return str_replace(["\r", "\n"], "", trim($dato));
}

function smtpLeerRespuesta($conexion) {
    $respuesta = "";

    while ($linea = fgets($conexion, 515)) {
        $respuesta .= $linea;

        // Las respuestas multilínea terminan cuando el cuarto carácter es un espacio
        if (isset($linea[3]) && $linea[3] === " ") {
            break;
        }
    }

    return $respuesta;
}

function smtpComando($conexion, $comando, $codigosEsperados) {
    if ($comando !== null) {
        fwrite($conexion, $comando . "\r\n");
    }

    $respuesta = smtpLeerRespuesta($conexion);
    $codigo = (int) substr($respuesta, 0, 3);

    if (!in_array($codigo, $codigosEsperados)) {
        throw new Exception("Error SMTP. Comando: " . $comando . " | Respuesta: " . trim($respuesta));
    }

    return $respuesta;
}

function codificarAsunto($texto) {
    if (function_exists("mb_encode_mimeheader")) {
        return mb_encode_mimeheader($texto, "UTF-8", "B", "\r\n");
    }

    return "=?UTF-8?B?" . base64_encode($texto) . "?=";
}

function enviarCorreoSMTP($nombre, $email, $negocio, $mensaje) {
    // ==========================================================
    // VARIABLES DE ENTORNO SMTP
    // ==========================================================

    $smtpUsuario = getenv("MI_CORREO_KITCHERRY");
    $smtpPassword = getenv("MI_CONTRASENA_CORREO_KITCHERRY");
    $smtpServidor = getenv("MI_SERVIDORSMTP_CORREO_KITCHERRY");
    $smtpPuerto = (int) (getenv("MI_PUERTOSMTP_CORREO_KITCHERRY") ?: 587);

    $correoDestino = $smtpUsuario;

    if (!$smtpUsuario || !$smtpPassword || !$smtpServidor || !$smtpPuerto) {
        throw new Exception("Faltan variables de entorno SMTP.");
    }

    // ==========================================================
    // CONEXIÓN SMTP
    // ==========================================================

    $timeout = 20;

    if ($smtpPuerto === 465) {
        // SMTP con SSL directo
        $conexion = stream_socket_client(
            "ssl://" . $smtpServidor . ":" . $smtpPuerto,
            $errno,
            $errstr,
            $timeout
        );
    } else {
        // SMTP con STARTTLS, normalmente puerto 587
        $conexion = stream_socket_client(
            $smtpServidor . ":" . $smtpPuerto,
            $errno,
            $errstr,
            $timeout
        );
    }

    if (!$conexion) {
        throw new Exception("No se pudo conectar al servidor SMTP: " . $errstr);
    }

    stream_set_timeout($conexion, $timeout);

    smtpComando($conexion, null, [220]);

    $hostLocal = gethostname() ?: "kitcherry.local";

    smtpComando($conexion, "EHLO " . $hostLocal, [250]);

    if ($smtpPuerto !== 465) {
        smtpComando($conexion, "STARTTLS", [220]);

        $cryptoOk = stream_socket_enable_crypto(
            $conexion,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
        );

        if (!$cryptoOk) {
            throw new Exception("No se pudo activar TLS en la conexión SMTP.");
        }

        smtpComando($conexion, "EHLO " . $hostLocal, [250]);
    }

    // ==========================================================
    // AUTENTICACIÓN SMTP
    // ==========================================================

    smtpComando($conexion, "AUTH LOGIN", [334]);
    smtpComando($conexion, base64_encode($smtpUsuario), [334]);
    smtpComando($conexion, base64_encode($smtpPassword), [235]);

    // ==========================================================
    // CONTENIDO DEL CORREO
    // ==========================================================

    $emailCabecera = limpiarParaCabecera($email);

    $asunto = "Nueva consulta desde la web de Kitcherry";

    $cuerpo = "";
    $cuerpo .= "Nueva consulta recibida desde la web de Kitcherry\r\n";
    $cuerpo .= "====================================================\r\n\r\n";
    $cuerpo .= "Nombre: " . $nombre . "\r\n";
    $cuerpo .= "Email: " . $email . "\r\n";
    $cuerpo .= "Negocio: " . ($negocio !== "" ? $negocio : "No indicado") . "\r\n\r\n";
    $cuerpo .= "Mensaje:\r\n";
    $cuerpo .= $mensaje . "\r\n\r\n";
    $cuerpo .= "====================================================\r\n";
    $cuerpo .= "Este correo ha sido enviado automáticamente desde el formulario de contacto de Kitcherry.\r\n";

    $headers = [];
    $headers[] = "Date: " . date("r");
    $headers[] = "From: Kitcherry Web <" . $smtpUsuario . ">";
    $headers[] = "Reply-To: " . $emailCabecera;
    $headers[] = "To: " . $correoDestino;
    $headers[] = "Subject: " . codificarAsunto($asunto);
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/plain; charset=UTF-8";
    $headers[] = "Content-Transfer-Encoding: 8bit";
    $headers[] = "X-Mailer: Kitcherry SMTP Form";

    $correoCompleto = implode("\r\n", $headers) . "\r\n\r\n" . $cuerpo;

    // Normalizar saltos de línea
    $correoCompleto = preg_replace("/\r\n|\r|\n/", "\r\n", $correoCompleto);

    // Evitar problemas SMTP con líneas que empiezan por punto
    $correoCompleto = preg_replace("/^\./m", "..", $correoCompleto);

    // ==========================================================
    // ENVÍO DEL CORREO
    // ==========================================================

    smtpComando($conexion, "MAIL FROM:<" . $smtpUsuario . ">", [250]);
    smtpComando($conexion, "RCPT TO:<" . $correoDestino . ">", [250, 251]);
    smtpComando($conexion, "DATA", [354]);

    fwrite($conexion, $correoCompleto . "\r\n.\r\n");

    smtpComando($conexion, null, [250]);

    smtpComando($conexion, "QUIT", [221]);

    fclose($conexion);

    return true;
}