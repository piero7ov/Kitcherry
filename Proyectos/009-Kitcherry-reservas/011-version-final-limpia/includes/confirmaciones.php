<?php
// ==========================================================
// KITCHERRY RESERVAS
// Archivo: includes/confirmaciones.php
// Generación de mensajes de confirmación
// ==========================================================

function formatearFechaConfirmacion($fecha) {
    $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);

    if (!$fechaObj) {
        return $fecha;
    }

    return $fechaObj->format('d/m/Y');
}

function generarMensajeConfirmacionBase($reserva) {
    $nombreCliente = $reserva['cliente_nombre'] ?: 'cliente';
    $nombreNegocio = $reserva['negocio_nombre'] ?: 'nuestro restaurante';
    $fecha = formatearFechaConfirmacion($reserva['fecha']);
    $hora = $reserva['hora'];
    $personas = (int)$reserva['personas'];

    $texto = "Hola " . $nombreCliente . ",\n\n";
    $texto .= "te confirmamos tu reserva en " . $nombreNegocio . " para " . $personas . " persona";

    if ($personas !== 1) {
        $texto .= "s";
    }

    $texto .= " el día " . $fecha . " a las " . $hora . ".\n\n";
    $texto .= "Te esperamos. Si necesitas modificar o cancelar la reserva, puedes responder a este mensaje.\n\n";
    $texto .= "Gracias por confiar en nosotros.\n";
    $texto .= $nombreNegocio;

    return $texto;
}

function generarMensajeConfirmacionIA($reserva) {
    $ollamaUrl = getenv("OLLAMA_URL") ?: "http://localhost:11434/api/generate";
    $ollamaModel = getenv("OLLAMA_MODEL") ?: "llama3:latest";

    $tono = $reserva['tono_comunicacion'] ?: 'cercano';

    $prompt = "
Genera un email breve de confirmación de reserva en español.

Datos:
- Negocio: {$reserva['negocio_nombre']}
- Cliente: {$reserva['cliente_nombre']}
- Fecha: " . formatearFechaConfirmacion($reserva['fecha']) . "
- Hora: {$reserva['hora']}
- Personas: {$reserva['personas']}
- Tono: {$tono}

Condiciones:
- No inventes datos.
- No añadas descuentos.
- No añadas enlaces.
- No uses asunto.
- Devuelve solo el cuerpo del email.
- Debe sonar natural y profesional.
";

    $payload = [
        "model" => $ollamaModel,
        "prompt" => $prompt,
        "stream" => false,
        "options" => [
            "temperature" => 0.1
        ]
    ];

    $contexto = stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => "Content-Type: application/json\r\n",
            "content" => json_encode($payload, JSON_UNESCAPED_UNICODE),
            "timeout" => 20
        ]
    ]);

    $respuesta = @file_get_contents($ollamaUrl, false, $contexto);

    if (!$respuesta) {
        return null;
    }

    $json = json_decode($respuesta, true);

    if (!isset($json['response'])) {
        return null;
    }

    $texto = trim($json['response']);

    if ($texto === '') {
        return null;
    }

    return $texto;
}

function generarCuerpoConfirmacion($reserva) {
    $mensajeIA = generarMensajeConfirmacionIA($reserva);

    if ($mensajeIA) {
        return $mensajeIA;
    }

    return generarMensajeConfirmacionBase($reserva);
}

function generarAsuntoConfirmacion($reserva) {
    $nombreNegocio = $reserva['negocio_nombre'] ?: 'Kitcherry Reservas';
    return "Confirmación de reserva - " . $nombreNegocio;
}