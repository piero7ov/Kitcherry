<?php
// ==========================================================
// KITCHERRY RESERVAS
// Archivo: includes/mensajes_reserva.php
// Generación de mensajes para recordatorios, cancelaciones y modificaciones
// ==========================================================

function formatearFechaMensajeReserva($fecha) {
    $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);

    if (!$fechaObj) {
        return $fecha;
    }

    return $fechaObj->format('d/m/Y');
}

function limpiarRespuestaMensajeIA($texto) {
    $texto = trim($texto);

    $texto = str_replace("```html", "", $texto);
    $texto = str_replace("```php", "", $texto);
    $texto = str_replace("```", "", $texto);

    $texto = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $texto);
    $texto = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $texto);

    return trim($texto);
}

function llamarOllamaMensajeReserva($prompt) {
    $ollamaUrl = getenv('OLLAMA_URL') ?: 'http://localhost:11434/api/generate';
    $ollamaModel = getenv('OLLAMA_MODEL') ?: 'llama3:latest';

    $payload = [
        'model' => $ollamaModel,
        'prompt' => $prompt,
        'stream' => false,
        'options' => [
            'temperature' => 0.1
        ]
    ];

    $opciones = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout' => 8
        ]
    ];

    $contexto = stream_context_create($opciones);
    $respuesta = @file_get_contents($ollamaUrl, false, $contexto);

    if ($respuesta === false) {
        return null;
    }

    $datos = json_decode($respuesta, true);

    if (!is_array($datos) || empty($datos['response'])) {
        return null;
    }

    return limpiarRespuestaMensajeIA($datos['response']);
}

function generarAsuntoMensajeReserva($reserva, $tipo) {
    $nombreNegocio = $reserva['negocio_nombre'] ?: 'nuestro restaurante';

    if ($tipo === 'recordatorio') {
        return 'Recordatorio de reserva - ' . $nombreNegocio;
    }

    if ($tipo === 'cancelacion') {
        return 'Cancelación de reserva - ' . $nombreNegocio;
    }

    if ($tipo === 'modificacion') {
        return 'Actualización de reserva - ' . $nombreNegocio;
    }

    return 'Información de reserva - ' . $nombreNegocio;
}

function generarCuerpoMensajeFallback($reserva, $tipo) {
    $nombreCliente = $reserva['cliente_nombre'] ?: 'cliente';
    $nombreNegocio = $reserva['negocio_nombre'] ?: 'nuestro restaurante';
    $fecha = formatearFechaMensajeReserva($reserva['fecha']);
    $hora = substr($reserva['hora'], 0, 5);
    $personas = (int)$reserva['personas'];

    $mesaTexto = 'sin mesa asignada';

    if (!empty($reserva['mesa_nombre'])) {
        $mesaTexto = $reserva['mesa_nombre'];

        if (!empty($reserva['mesa_zona'])) {
            $mesaTexto .= ' · ' . $reserva['mesa_zona'];
        }
    }

    $telefonoNegocio = trim($reserva['negocio_telefono'] ?? '');
    $firmaContacto = '';

    if ($telefonoNegocio !== '') {
        $firmaContacto = "\n\nPara cualquier cambio, puedes contactar con nosotros en el teléfono " . $telefonoNegocio . ".";
    }

    if ($tipo === 'recordatorio') {
        return "Hola " . $nombreCliente . ",\n\n"
            . "Te recordamos tu reserva en " . $nombreNegocio . " para el día " . $fecha . " a las " . $hora . ".\n\n"
            . "Reserva para " . $personas . " persona" . ($personas !== 1 ? "s" : "") . ".\n"
            . "Mesa: " . $mesaTexto . "."
            . $firmaContacto
            . "\n\nMuchas gracias,\n" . $nombreNegocio;
    }

    if ($tipo === 'cancelacion') {
        return "Hola " . $nombreCliente . ",\n\n"
            . "Te informamos de que tu reserva en " . $nombreNegocio . " para el día " . $fecha . " a las " . $hora . " ha sido cancelada.\n\n"
            . "Si necesitas hacer una nueva reserva o consultar cualquier detalle, puedes contactar con nosotros."
            . $firmaContacto
            . "\n\nMuchas gracias,\n" . $nombreNegocio;
    }

    if ($tipo === 'modificacion') {
        return "Hola " . $nombreCliente . ",\n\n"
            . "Te informamos de que tu reserva en " . $nombreNegocio . " ha sido actualizada.\n\n"
            . "Datos actuales de la reserva:\n"
            . "- Fecha: " . $fecha . "\n"
            . "- Hora: " . $hora . "\n"
            . "- Personas: " . $personas . "\n"
            . "- Mesa: " . $mesaTexto . "\n"
            . $firmaContacto
            . "\n\nMuchas gracias,\n" . $nombreNegocio;
    }

    return "Hola " . $nombreCliente . ",\n\n"
        . "Te contactamos desde " . $nombreNegocio . " en relación con tu reserva del día " . $fecha . " a las " . $hora . ".\n\n"
        . "Muchas gracias,\n" . $nombreNegocio;
}

function generarPromptMensajeReserva($reserva, $tipo) {
    $nombreCliente = $reserva['cliente_nombre'] ?: 'cliente';
    $nombreNegocio = $reserva['negocio_nombre'] ?: 'restaurante';
    $fecha = formatearFechaMensajeReserva($reserva['fecha']);
    $hora = substr($reserva['hora'], 0, 5);
    $personas = (int)$reserva['personas'];
    $tono = $reserva['tono_comunicacion'] ?: 'cercano';

    $mesa = 'Sin mesa asignada';

    if (!empty($reserva['mesa_nombre'])) {
        $mesa = $reserva['mesa_nombre'];

        if (!empty($reserva['mesa_zona'])) {
            $mesa .= ' · ' . $reserva['mesa_zona'];
        }
    }

    $accion = 'informar sobre la reserva';

    if ($tipo === 'recordatorio') {
        $accion = 'recordar al cliente su reserva';
    }

    if ($tipo === 'cancelacion') {
        $accion = 'informar al cliente de la cancelación de su reserva';
    }

    if ($tipo === 'modificacion') {
        $accion = 'informar al cliente de que su reserva ha sido actualizada';
    }

    return "Redacta un email breve y profesional en español para " . $accion . ".\n\n"
        . "Datos:\n"
        . "Negocio: " . $nombreNegocio . "\n"
        . "Cliente: " . $nombreCliente . "\n"
        . "Fecha: " . $fecha . "\n"
        . "Hora: " . $hora . "\n"
        . "Personas: " . $personas . "\n"
        . "Mesa: " . $mesa . "\n"
        . "Tono: " . $tono . "\n\n"
        . "Condiciones:\n"
        . "- No inventes datos.\n"
        . "- No añadas enlaces.\n"
        . "- No uses HTML.\n"
        . "- Devuelve solo el cuerpo del email.\n"
        . "- Mantén el mensaje claro, natural y corto.";
}

function generarCuerpoMensajeReserva($reserva, $tipo, $usarIA = true) {
    if ($usarIA) {
        $prompt = generarPromptMensajeReserva($reserva, $tipo);
        $respuestaIA = llamarOllamaMensajeReserva($prompt);

        if ($respuestaIA !== null && trim($respuestaIA) !== '') {
            return $respuestaIA;
        }
    }

    return generarCuerpoMensajeFallback($reserva, $tipo);
}

function textoTipoMensajeReserva($tipo) {
    $textos = [
        'recordatorio' => 'Recordatorio',
        'cancelacion' => 'Cancelación',
        'modificacion' => 'Modificación',
        'confirmacion' => 'Confirmación'
    ];

    return $textos[$tipo] ?? ucfirst($tipo);
}

function accionHistorialMensajeReserva($tipo) {
    $acciones = [
        'recordatorio' => 'recordatorio_enviado',
        'cancelacion' => 'cancelacion_enviada',
        'modificacion' => 'modificacion_enviada'
    ];

    return $acciones[$tipo] ?? 'mensaje_enviado';
}