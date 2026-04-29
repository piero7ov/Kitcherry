<?php
// ==========================================================
// KITCHERRY - WEB CORPORATIVA
// Archivo: index.php
// ==========================================================

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

$mensajeFormulario = "";
$tipoMensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = limpiar($_POST["nombre"] ?? "");
    $email = limpiar($_POST["email"] ?? "");
    $negocio = limpiar($_POST["negocio"] ?? "");
    $mensaje = limpiar($_POST["mensaje"] ?? "");

    if ($nombre === "" || $email === "" || $mensaje === "") {
        $mensajeFormulario = "Por favor, completa los campos obligatorios: nombre, correo y mensaje.";
        $tipoMensaje = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensajeFormulario = "Por favor, introduce un correo electrónico válido.";
        $tipoMensaje = "error";
    } else {
        try {
            enviarCorreoSMTP($nombre, $email, $negocio, $mensaje);

            $mensajeFormulario = "Gracias, " . $nombre . ". Hemos recibido tu consulta y la enviaremos al equipo de Kitcherry.";
            $tipoMensaje = "ok";
        } catch (Exception $e) {
            error_log("Error enviando correo SMTP desde Kitcherry: " . $e->getMessage());

            $mensajeFormulario = "No se pudo enviar la consulta en este momento. Revisa la configuración SMTP del servidor.";
            $tipoMensaje = "error";
        }
    }
}

$servicios = [
    [
        "numero" => "01",
        "titulo" => "Sistema inteligente de consultas y comunicaciones en hostelería",
        "texto" => "Ayuda a gestionar consultas frecuentes de clientes, organizar mensajes importantes y responder con mayor rapidez en momentos de alta carga de trabajo."
    ],
    [
        "numero" => "02",
        "titulo" => "Chatbot web para negocios de hostelería",
        "texto" => "Asistente digital adaptable para restaurantes, bares y cafeterías, capaz de resolver dudas frecuentes, orientar al cliente y mejorar la atención desde la web."
    ],
    [
        "numero" => "03",
        "titulo" => "Herramienta de gestión y seguimiento de reservas",
        "texto" => "Herramienta para ordenar solicitudes, confirmar reservas y reducir errores en la planificación diaria del servicio."
    ],
    [
        "numero" => "04",
        "titulo" => "Plataforma de información de carta, platos y alérgenos",
        "texto" => "Plataforma para comunicar información sobre menús, ingredientes y alérgenos de forma clara, accesible y actualizable."
    ],
    [
        "numero" => "05",
        "titulo" => "Asistente digital de apoyo a cocina y producción interna",
        "texto" => "Sistema de apoyo para consultar procedimientos, elaboraciones, pasos de preparación o información interna durante el servicio."
    ],
    [
        "numero" => "06",
        "titulo" => "Sistema de consulta rápida para personal de sala",
        "texto" => "Herramienta para que el equipo de sala consulte platos, sugerencias, alérgenos o información útil de atención al cliente."
    ],
    [
        "numero" => "07",
        "titulo" => "Plataforma de organización interna de tareas e incidencias",
        "texto" => "Plataforma para organizar tareas internas, registrar incidencias y mejorar la coordinación diaria del equipo."
    ],
    [
        "numero" => "08",
        "titulo" => "Sistema de apoyo a la gestión de pedidos o solicitudes internas",
        "texto" => "Sistema sencillo para gestionar pedidos internos, solicitudes operativas o movimientos de información dentro del negocio."
    ],
    [
        "numero" => "09",
        "titulo" => "Plataforma de comunicación comercial y presencia digital del negocio",
        "texto" => "Herramientas para comunicar promociones, novedades e información relevante del establecimiento de forma profesional."
    ],
    [
        "numero" => "10",
        "titulo" => "Plataforma modular de implantación, personalización y mejora continua",
        "texto" => "Modelo modular que permite adaptar las herramientas a cada negocio, implantarlas progresivamente y mejorarlas con acompañamiento."
    ]
];

$necesidades = [
    [
        "titulo" => "Automatizar tareas repetitivas",
        "texto" => "Muchos pequeños negocios hosteleros repiten a diario las mismas respuestas sobre horarios, reservas, ubicación o servicios."
    ],
    [
        "titulo" => "Gestionar mejor consultas y reservas",
        "texto" => "El volumen de mensajes, llamadas o correos puede provocar olvidos, retrasos o desorganización."
    ],
    [
        "titulo" => "Comunicar información clara",
        "texto" => "La carta, los ingredientes y los alérgenos deben presentarse de forma comprensible tanto para clientes como para el personal."
    ],
    [
        "titulo" => "Ahorrar tiempo",
        "texto" => "El objetivo no es añadir más trabajo, sino reducir carga repetitiva y liberar tiempo para la atención real del negocio."
    ],
    [
        "titulo" => "Modernizar el negocio sin complicarse",
        "texto" => "La tecnología debe ser práctica, fácil de implantar y adaptada al ritmo real de restaurantes, bares y cafeterías."
    ],
    [
        "titulo" => "Mejorar la operativa diaria",
        "texto" => "Ordenar procesos internos, coordinar mejor al equipo y reducir errores tiene impacto directo en el servicio."
    ]
];

$flujoTrabajo = [
    [
        "paso" => "1",
        "titulo" => "Recibe las consultas",
        "texto" => "El negocio puede reunir las consultas habituales de clientes en un sistema más ordenado y fácil de revisar."
    ],
    [
        "paso" => "2",
        "titulo" => "Entiende la necesidad del cliente",
        "texto" => "La herramienta ayuda a diferenciar si la consulta está relacionada con reservas, horarios, carta, alérgenos, ubicación o servicios."
    ],
    [
        "paso" => "3",
        "titulo" => "Ordena la atención",
        "texto" => "Permite dar más visibilidad a las solicitudes importantes y reducir el riesgo de perder mensajes relevantes."
    ],
    [
        "paso" => "4",
        "titulo" => "Agiliza la respuesta",
        "texto" => "Facilita respuestas claras y adaptadas al negocio para mejorar la rapidez de atención sin saturar al equipo."
    ]
];

$logoPath = "img/logo-kitcherry.png";
$heroPath = "img/hero.png";
$videoPath = "destacados/email_sender.mp4";

$logoExiste = file_exists(__DIR__ . "/" . $logoPath);
$heroExiste = file_exists(__DIR__ . "/" . $heroPath);
$videoExiste = file_exists(__DIR__ . "/" . $videoPath);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <title>Kitcherry | Herramientas de software para hostelería</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description" content="Kitcherry ofrece herramientas de software para hostelería que utilizan IA como recurso práctico para automatizar consultas, organizar reservas y mejorar la operativa diaria.">

    <style>
    /* ==========================================================
    FUENTE CORPORATIVA
    Coolvetica solo para títulos, subtítulos y marca
    ========================================================== */

    @font-face {
        font-family: "Coolvetica";
        src: url("fuente/Coolvetica Rg.otf") format("opentype");
        font-weight: 400;
        font-style: normal;
        font-display: swap;
    }

    /* ==========================================================
    VARIABLES
    ========================================================== */

    :root {
        --rojo: #C2182B;
        --rojo-oscuro: #991726;
        --rojo-vivo: #ff2b3f;
        --rojo-suave: #fbeaec;
        --negro: #161616;
        --gris-texto: #555555;
        --gris-claro: #f6f6f6;
        --gris-medio: #ececec;
        --blanco: #ffffff;
        --sombra: 0 18px 40px rgba(0, 0, 0, 0.08);
        --radio: 22px;
        --ancho: 1180px;
    }

    /* ==========================================================
    RESET Y BASE
    ========================================================== */

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    html {
        scroll-behavior: smooth;
    }

    body {
        font-family: Arial, Helvetica, sans-serif;
        background: var(--blanco);
        color: var(--negro);
        line-height: 1.55;
    }

    img {
        max-width: 100%;
        display: block;
    }

    a {
        text-decoration: none;
        color: inherit;
    }

    ul {
        list-style: none;
    }

    h1,
    h2,
    h3,
    .marca-texto strong,
    .marca-texto span,
    .titulo-bloque h2,
    .card h3,
    .servicio h3,
    .caso-principal h2,
    .paso h3,
    .bloque-ia h2,
    .contacto-info h2,
    .footer strong,
    .footer-col h3,
    .dato strong,
    .video-demo h3 {
        font-family: "Coolvetica", Arial, Helvetica, sans-serif;
    }

    /* ==========================================================
    ESTRUCTURA GENERAL
    ========================================================== */

    .contenedor {
        width: min(92%, var(--ancho));
        margin: 0 auto;
    }

    .seccion {
        padding: 90px 0;
    }

    .fondo-suave {
        background: var(--gris-claro);
    }

    .titulo-bloque {
        max-width: 840px;
        margin-bottom: 38px;
    }

    .titulo-bloque h2 {
        font-size: clamp(2rem, 4vw, 3.2rem);
        line-height: 1.08;
        margin-bottom: 16px;
        font-weight: 800;
    }

    .titulo-bloque p {
        font-size: 1.04rem;
        color: var(--gris-texto);
    }

    /* ==========================================================
    BOTONES
    ========================================================== */

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 13px 22px;
        border-radius: 999px;
        border: 2px solid transparent;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 0.98rem;
        font-weight: 700;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .btn-principal {
        background: var(--rojo);
        color: var(--blanco);
    }

    .btn-principal:hover {
        background: var(--rojo-oscuro);
        transform: translateY(-2px);
    }

    .btn-secundario {
        background: transparent;
        color: var(--blanco);
        border-color: rgba(255, 255, 255, 0.65);
    }

    .btn-secundario:hover {
        background: var(--blanco);
        color: var(--negro);
        transform: translateY(-2px);
    }

    /* ==========================================================
    HEADER
    ========================================================== */

    .cabecera {
        position: sticky;
        top: 0;
        z-index: 1000;
        background: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(14px);
        border-bottom: 1px solid #ececec;
    }

    .cabecera-contenido {
        min-height: 82px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
    }

    .marca {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .marca-logo {
        width: 58px;
        height: 58px;
        object-fit: contain;
    }

    .marca-texto strong {
        display: block;
        font-size: 1.45rem;
        line-height: 1;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .marca-texto span {
        display: block;
        font-size: 0.82rem;
        color: var(--gris-texto);
        margin-top: 4px;
        font-weight: 400;
    }

    .marca-texto strong span {
        display: inline;
        font-size: inherit;
        margin-top: 0;
    }

    .marca-texto .kit {
        color: #000000;
    }

    .marca-texto .cherry {
        color: var(--rojo);
    }

    /* Botón menú móvil */
    .menu-toggle {
        display: none;
    }

    .menu-label {
        display: none;
        padding: 10px 14px;
        border: 1px solid #dddddd;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 700;
    }

    /* Menú principal limpio */
    .menu {
        display: flex;
        align-items: center;
        gap: 26px;
        font-size: 0.96rem;
        color: #444444;
        font-weight: 600;
    }

    .menu a {
        position: relative;
        padding: 6px 0;
    }

    .menu a:hover {
        color: var(--rojo);
    }

    .menu a::after {
        content: "";
        position: absolute;
        left: 0;
        bottom: 0;
        width: 0;
        height: 2px;
        background: var(--rojo);
        transition: width 0.2s ease;
    }

    .menu a:hover::after {
        width: 100%;
    }

    /* ==========================================================
    HERO
    ========================================================== */

    .hero {
        min-height: 620px;
        display: flex;
        align-items: center;
        position: relative;

        /* Permite que .hero-datos sobresalga del hero */
        overflow: visible;

        z-index: 10;
        color: var(--blanco);

        background:
            linear-gradient(90deg, rgba(0, 0, 0, 0.82) 0%, rgba(0, 0, 0, 0.63) 42%, rgba(0, 0, 0, 0.22) 100%),
            <?php if ($heroExiste): ?>
            url("<?php echo $heroPath; ?>")
            <?php else: ?>
            linear-gradient(135deg, #161616, #991726)
            <?php endif; ?>;

        background-size: cover;
        background-position: center;
    }

    .hero::after {
        content: "";
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at left center, rgba(194, 24, 43, 0.36), transparent 34%);
        pointer-events: none;
        z-index: 1;
    }

    .hero-contenido {
        position: relative;
        z-index: 2;
        max-width: 760px;
        padding: 105px 0;
    }

    .hero h1 {
        font-size: clamp(2.6rem, 6vw, 5.4rem);
        line-height: 0.98;
        margin-bottom: 24px;
        font-weight: 800;
    }

    .hero h1 .rojo {
        color: var(--rojo-vivo);
    }

    .hero p {
        font-size: 1.15rem;
        color: rgba(255, 255, 255, 0.88);
        max-width: 680px;
        margin-bottom: 30px;
    }

    .hero-acciones {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        margin-bottom: 42px;
    }

    /* Tarjeta flotante del hero */
    .hero-datos {
        width: min(92%, var(--ancho));
        position: absolute;
        left: 50%;
        bottom: -48px;
        transform: translateX(-50%);
        z-index: 50;
        background: var(--blanco);
        color: var(--negro);
        border: 1px solid #ececec;
        border-radius: 22px;
        box-shadow: var(--sombra);
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        overflow: hidden;
    }

    .dato {
        padding: 24px;
        display: flex;
        gap: 16px;
        align-items: flex-start;
        border-right: 1px solid #ececec;
    }

    .dato:last-child {
        border-right: none;
    }

    .dato-icono {
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        border-radius: 14px;
        background: var(--rojo-suave);
        color: var(--rojo);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        font-weight: 900;
    }

    .dato strong {
        display: block;
        font-size: 1rem;
        margin-bottom: 4px;
        font-weight: 800;
    }

    .dato span {
        color: var(--gris-texto);
        font-size: 0.9rem;
    }

    .despues-hero {
        padding-top: 100px;
        position: relative;
        z-index: 1;
    }

    /* ==========================================================
    NECESIDADES
    ========================================================== */

    .grid-necesidades {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 22px;
    }

    .card {
        background: var(--blanco);
        border: 1px solid #ececec;
        border-radius: var(--radio);
        padding: 28px;
        box-shadow: 0 10px 26px rgba(0, 0, 0, 0.035);
    }

    .card-numero {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: var(--rojo-suave);
        color: var(--rojo);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        margin-bottom: 18px;
    }

    .card h3 {
        font-size: 1.28rem;
        line-height: 1.18;
        margin-bottom: 12px;
        font-weight: 800;
    }

    .card p {
        color: var(--gris-texto);
    }

    /* ==========================================================
    SERVICIOS / SOLUCIONES
    ========================================================== */

    .grid-servicios {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 22px;
    }

    .servicio {
        background: var(--blanco);
        border: 1px solid #ececec;
        border-radius: var(--radio);
        padding: 28px;
        position: relative;
        overflow: hidden;
    }

    .servicio::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        width: 6px;
        height: 100%;
        background: var(--rojo);
    }

    .servicio-numero {
        display: inline-block;
        color: var(--rojo);
        font-weight: 900;
        font-size: 1.05rem;
        margin-bottom: 10px;
    }

    .servicio h3 {
        font-size: 1.38rem;
        line-height: 1.18;
        margin-bottom: 10px;
        font-weight: 800;
    }

    .servicio p {
        color: var(--gris-texto);
    }

    /* ==========================================================
    PRODUCTO DESTACADO
    ========================================================== */

    .caso-grid {
        display: grid;
        grid-template-columns: 0.95fr 1.05fr;
        gap: 32px;
        align-items: start;
    }

    .caso-principal {
        background: var(--negro);
        color: var(--blanco);
        border-radius: 28px;
        padding: 34px;
        box-shadow: var(--sombra);
    }

    .caso-principal h2 {
        font-size: clamp(2rem, 4vw, 3rem);
        line-height: 1.06;
        margin-bottom: 16px;
        font-weight: 800;
    }

    .caso-principal p {
        color: #d9d9d9;
        margin-bottom: 20px;
    }

    .caso-principal ul {
        display: grid;
        gap: 12px;
    }

    .caso-principal li {
        display: flex;
        gap: 10px;
        color: #f1f1f1;
    }

    .caso-principal li::before {
        content: "✓";
        width: 22px;
        height: 22px;
        flex: 0 0 22px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: var(--rojo);
        color: #ffffff;
        font-size: 0.8rem;
        margin-top: 1px;
    }

    .producto-demo {
        display: grid;
        gap: 22px;
    }

    .video-demo {
        min-height: 300px;
        background:
            linear-gradient(135deg, rgba(22, 22, 22, 0.96), rgba(80, 10, 18, 0.95));
        border-radius: 28px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: var(--sombra);
        overflow: hidden;
        color: #ffffff;
    }

    .video-demo video {
        width: 100%;
        height: 100%;
        min-height: 300px;
        object-fit: cover;
        display: block;
    }

    .video-placeholder {
        min-height: 300px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 34px;
        text-align: center;
    }

    .video-placeholder-contenido {
        max-width: 580px;
    }

    .video-icono {
        width: 76px;
        height: 76px;
        border-radius: 50%;
        background: var(--rojo);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 18px;
        font-size: 1.8rem;
        box-shadow: 0 14px 28px rgba(0, 0, 0, 0.22);
    }

    .video-demo h3 {
        font-size: 1.65rem;
        line-height: 1.1;
        margin-bottom: 12px;
        font-weight: 800;
    }

    .video-demo p {
        color: rgba(255, 255, 255, 0.78);
        margin-bottom: 12px;
    }

    .video-demo p:last-child {
        margin-bottom: 0;
    }

    .flujo {
        display: grid;
        gap: 18px;
    }

    .paso {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 18px;
        background: var(--blanco);
        border: 1px solid #ececec;
        border-radius: var(--radio);
        padding: 24px;
    }

    .paso-numero {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: var(--rojo);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-size: 1.05rem;
    }

    .paso h3 {
        margin-bottom: 6px;
        font-size: 1.18rem;
        font-weight: 800;
    }

    .paso p {
        color: var(--gris-texto);
    }

    /* ==========================================================
    BLOQUE IA
    ========================================================== */

    .bloque-ia {
        background: linear-gradient(135deg, rgba(194, 24, 43, 0.98), rgba(140, 18, 34, 0.98));
        color: #ffffff;
        border-radius: 30px;
        padding: 46px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        align-items: center;
    }

    .bloque-ia h2 {
        font-size: clamp(2rem, 4vw, 3rem);
        line-height: 1.06;
        margin-bottom: 16px;
        font-weight: 800;
    }

    .bloque-ia p {
        color: rgba(255, 255, 255, 0.88);
    }

    .ia-items {
        display: grid;
        gap: 15px;
    }

    .ia-item {
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 18px;
        padding: 18px;
    }

    .ia-item strong {
        display: block;
        margin-bottom: 6px;
        font-size: 1.06rem;
    }

    .ia-item span {
        color: rgba(255, 255, 255, 0.88);
    }

    /* ==========================================================
    CONTACTO
    ========================================================== */

    .contacto-grid {
        display: grid;
        grid-template-columns: 0.9fr 1.1fr;
        gap: 32px;
    }

    .contacto-info {
        background: var(--gris-claro);
        border-radius: 26px;
        padding: 34px;
    }

    .contacto-info h2 {
        font-size: 2.2rem;
        line-height: 1.08;
        margin-bottom: 14px;
        font-weight: 800;
    }

    .contacto-info p {
        color: var(--gris-texto);
        margin-bottom: 20px;
    }

    .contacto-info ul {
        display: grid;
        gap: 12px;
        color: var(--gris-texto);
    }

    .formulario {
        background: var(--blanco);
        border: 1px solid #ececec;
        border-radius: 26px;
        padding: 34px;
        box-shadow: var(--sombra);
    }

    .mensaje-formulario {
        padding: 14px 16px;
        border-radius: 12px;
        margin-bottom: 18px;
        font-weight: 700;
    }

    .mensaje-formulario.ok {
        background: #eaf8ef;
        color: #166534;
        border: 1px solid #bde7c7;
    }

    .mensaje-formulario.error {
        background: #fff1f2;
        color: #a11d33;
        border: 1px solid #ffc7d0;
    }

    .campo {
        margin-bottom: 18px;
    }

    .campo label {
        display: block;
        margin-bottom: 8px;
        font-size: 0.98rem;
        font-weight: 800;
    }

    .campo input,
    .campo textarea {
        width: 100%;
        border: 1px solid #dddddd;
        border-radius: 14px;
        padding: 14px 15px;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 0.96rem;
        outline: none;
        background: #ffffff;
    }

    .campo input:focus,
    .campo textarea:focus {
        border-color: var(--rojo);
        box-shadow: 0 0 0 4px rgba(194, 24, 43, 0.08);
    }

    .campo textarea {
        min-height: 140px;
        resize: vertical;
    }

    /* ==========================================================
    FOOTER CORPORATIVO
    ========================================================== */

    .footer {
        background: var(--negro);
        color: #ffffff;
        padding: 56px 0 42px;
    }

    .footer-contenido {
        display: grid;
        grid-template-columns: 1.4fr 0.8fr 1fr 0.8fr;
        gap: 34px;
        align-items: start;
        letter-spacing: 0.6px;
    }

    .footer-col h3 {
        font-size: 1.15rem;
        font-weight: 800;
        margin-bottom: 14px;
        color: #ffffff;
    }

    .footer-marca {
        display: flex;
        align-items: flex-start;
        gap: 14px;
    }

    .footer-logo {
        width: 52px;
        height: 52px;
        object-fit: contain;
        flex: 0 0 52px;
    }

    .footer strong {
        display: block;
        font-size: 1.2rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .footer strong span {
        display: inline;
    }

    .footer-marca .kit {
        color: #ffffff;
    }

    .footer-marca .cherry {
        color: var(--rojo);
    }

    .footer p,
    .footer a,
    .footer li {
        color: #d4d4d4;
        font-size: 0.92rem;
    }

    .footer-copy {
        margin-top: 8px;
        color: #a9a9a9;
    }

    .footer-nav-col {
        display: grid;
        gap: 9px;
    }

    .footer-nav-col a:hover {
        color: #ffffff;
    }

    .footer-lista {
        display: grid;
        gap: 9px;
    }

    .footer-lista strong {
        display: inline;
        color: #ffffff;
        font-size: inherit;
        text-transform: none;
        letter-spacing: 0;
    }

    .footer-redes {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 18px;
    }

    .footer-redes a {
        border: 1px solid rgba(255, 255, 255, 0.16);
        padding: 7px 12px;
        border-radius: 999px;
        font-size: 0.86rem;
    }

    .footer-redes a:hover {
        background: var(--rojo);
        border-color: var(--rojo);
        color: #ffffff;
    }

    /* ==========================================================
    RESPONSIVE
    ========================================================== */

    @media (max-width: 960px) {
        .menu-label {
            display: block;
        }

        .menu {
            display: none;
            position: absolute;
            top: 82px;
            left: 0;
            right: 0;
            background: #ffffff;
            border-bottom: 1px solid #ececec;
            flex-direction: column;
            align-items: flex-start;
            padding: 20px 4%;
        }

        .menu-toggle:checked ~ .menu {
            display: flex;
        }

        .hero {
            min-height: 640px;
            background-position: center;
        }

        .hero-datos {
            position: relative;
            left: auto;
            bottom: auto;
            transform: none;
            margin: -40px auto 0;
            grid-template-columns: repeat(2, 1fr);
            z-index: 50;
        }

        .despues-hero {
            padding-top: 60px;
        }

        .caso-grid,
        .bloque-ia,
        .contacto-grid,
        .grid-necesidades,
        .grid-servicios {
            grid-template-columns: 1fr;
        }

        .footer-contenido {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 640px) {
        .seccion {
            padding: 68px 0;
        }

        .hero {
            min-height: 580px;
        }

        .hero-contenido {
            padding: 76px 0;
        }

        .hero-datos {
            grid-template-columns: 1fr;
        }

        .dato {
            border-right: none;
            border-bottom: 1px solid #ececec;
        }

        .dato:last-child {
            border-bottom: none;
        }

        .paso {
            grid-template-columns: 1fr;
        }

        .contacto-info,
        .formulario,
        .caso-principal,
        .bloque-ia {
            padding: 26px;
        }

        .video-placeholder {
            padding: 26px;
        }

        .footer-contenido {
            grid-template-columns: 1fr;
        }

        .footer-marca {
            flex-direction: row;
            align-items: flex-start;
        }
    }
    </style>
</head>

<body id="inicio">

    <header class="cabecera">
        <div class="contenedor cabecera-contenido">

            <a href="#inicio" class="marca">
                <?php if ($logoExiste): ?>
                    <img src="<?php echo $logoPath; ?>" alt="Logotipo de Kitcherry" class="marca-logo">
                <?php endif; ?>

                <div class="marca-texto">
                    <strong>
                        <span class="kit">KIT</span><span class="cherry">CHERRY</span>
                    </strong>
                    <span>Herramientas para hostelería</span>
                </div>
            </a>

            <input type="checkbox" id="menu-toggle" class="menu-toggle">
            <label for="menu-toggle" class="menu-label">Menú</label>

            <nav class="menu">
                <a href="nosotros.php">Qué es Kitcherry</a>
                <a href="#servicios">Soluciones</a>
                <a href="#ia-practica">IA práctica</a>
                <a href="#contacto">Contacto</a>
            </nav>

        </div>
    </header>

    <main>

        <section class="hero">
            <div class="contenedor hero-contenido">

                <h1>
                    Software que simplifica la hostelería y mejora <span class="rojo">tu día a día.</span>
                </h1>

                <p>
                    Soluciones prácticas con IA para automatizar consultas, gestionar reservas,
                    comunicar información útil y organizar la operativa diaria de tu negocio.
                </p>

                <div class="hero-acciones">
                    <a href="#servicios" class="btn btn-principal">Ver soluciones</a>
                    <a href="#contacto" class="btn btn-secundario">Solicitar información</a>
                </div>

            </div>

            <div class="hero-datos">
                <div class="dato">
                    <div class="dato-icono">▦</div>
                    <div>
                        <strong>10 líneas de servicio</strong>
                        <span>Soluciones modulares adaptables a cada negocio</span>
                    </div>
                </div>

                <div class="dato">
                    <div class="dato-icono">IA</div>
                    <div>
                        <strong>IA como recurso</strong>
                        <span>Aplicada a necesidades reales del día a día</span>
                    </div>
                </div>

                <div class="dato">
                    <div class="dato-icono">⌂</div>
                    <div>
                        <strong>Enfoque hostelero</strong>
                        <span>Pensado para bares, cafeterías y restaurantes</span>
                    </div>
                </div>

                <div class="dato">
                    <div class="dato-icono">↻</div>
                    <div>
                        <strong>Ahorra tiempo</strong>
                        <span>Menos tareas repetitivas y más orden interno</span>
                    </div>
                </div>
            </div>
        </section>

        <section id="necesidades" class="seccion fondo-suave despues-hero">
            <div class="contenedor">

                <div class="titulo-bloque">
                    <h2>Necesidades reales del cliente</h2>
                    <p>
                        Antes de plantear un producto concreto, es importante identificar las necesidades del mercado para que el proyecto responda
                        a problemas reales y no solo a una idea teórica. En el caso de Kitcherry, la atención se centra en la hostelería,
                        especialmente en pequeños negocios que necesitan apoyo para organizar mejor ciertos procesos, ahorrar tiempo y mejorar su operativa diaria.
                    </p>
                </div>

                <div class="grid-necesidades">
                    <?php foreach ($necesidades as $index => $necesidad): ?>
                        <article class="card">
                            <div class="card-numero"><?php echo $index + 1; ?></div>
                            <h3><?php echo $necesidad["titulo"]; ?></h3>
                            <p><?php echo $necesidad["texto"]; ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>

            </div>
        </section>

        <section id="servicios" class="seccion">
            <div class="contenedor">

                <div class="titulo-bloque">
                    <h2>Soluciones de software para hostelería</h2>
                    <p>
                        Kitcherry se organiza en soluciones concretas que pueden funcionar de manera independiente o integrarse
                        dentro de un ecosistema modular, según las necesidades reales del negocio.
                    </p>
                </div>

                <div class="grid-servicios">
                    <?php foreach ($servicios as $servicio): ?>
                        <article class="servicio">
                            <span class="servicio-numero"><?php echo $servicio["numero"]; ?></span>
                            <h3><?php echo $servicio["titulo"]; ?></h3>
                            <p><?php echo $servicio["texto"]; ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>

            </div>
        </section>

        <section id="producto-destacado" class="seccion fondo-suave">
            <div class="contenedor caso-grid">

                <div class="caso-principal">
                    <h2>Sistema inteligente de consultas y comunicaciones</h2>

                    <p>
                        Nuestro primer producto destacado está pensado para negocios hosteleros que reciben muchas preguntas repetidas
                        y necesitan responder mejor sin aumentar la carga del equipo.
                    </p>

                    <p>
                        La solución ayuda a ordenar consultas, evitar mensajes perdidos, mejorar la rapidez de atención y ofrecer una comunicación
                        más clara al cliente desde el primer contacto.
                    </p>

                    <ul>
                        <li>Menos tiempo respondiendo siempre las mismas preguntas.</li>
                        <li>Mayor control sobre consultas de reservas, horarios, carta, ubicación o servicios.</li>
                        <li>Mejor atención al cliente en momentos de alta carga de trabajo.</li>
                        <li>Imagen más profesional y organizada para el negocio.</li>
                    </ul>
                </div>

                <div class="producto-demo">

                    <div class="video-demo">
                        <?php if ($videoExiste): ?>
                            <video controls preload="metadata">
                                <source src="<?php echo $videoPath; ?>" type="video/mp4">
                                Tu navegador no puede reproducir este vídeo.
                            </video>
                        <?php else: ?>
                            <div class="video-placeholder">
                                <div class="video-placeholder-contenido">
                                    <div class="video-icono">▶</div>
                                    <h3>Panel inteligente de correos para hostelería</h3>
                                    <p>
                                        Herramienta diseñada para ayudar a restaurantes y negocios hosteleros a gestionar sus consultas
                                        de forma más ágil, ordenada y profesional.
                                    </p>
                                    <p>
                                        El sistema clasifica automáticamente los correos, detecta el tipo de solicitud, prioriza mensajes
                                        según su urgencia operativa, extrae datos útiles, genera borradores de respuesta editables y permite
                                        mantener trazabilidad completa del trabajo realizado.
                                    </p>
                                    <p>
                                        La idea no es quitar el control humano, sino ahorrar tiempo, reducir olvidos y mejorar la atención
                                        diaria al cliente.
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flujo">
                        <?php foreach ($flujoTrabajo as $paso): ?>
                            <article class="paso">
                                <div class="paso-numero"><?php echo $paso["paso"]; ?></div>
                                <div>
                                    <h3><?php echo $paso["titulo"]; ?></h3>
                                    <p><?php echo $paso["texto"]; ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                </div>

            </div>
        </section>

        <section id="ia-practica" class="seccion">
            <div class="contenedor">

                <div class="bloque-ia">
                    <div>
                        <h2>IA como recurso práctico</h2>
                        <p>
                            En Kitcherry, la inteligencia artificial no se vende como un concepto abstracto. Se utiliza como apoyo para ordenar información,
                            reducir carga repetitiva, responder mejor y ayudar a que el negocio funcione con más claridad.
                        </p>
                    </div>

                    <div class="ia-items">
                        <div class="ia-item">
                            <strong>Automatización comprensible</strong>
                            <span>La tecnología debe resolver problemas concretos y ser fácil de entender.</span>
                        </div>

                        <div class="ia-item">
                            <strong>Implantación progresiva</strong>
                            <span>Cada negocio puede empezar con una solución concreta y ampliar después si lo necesita.</span>
                        </div>

                        <div class="ia-item">
                            <strong>Apoyo a las personas</strong>
                            <span>La IA no sustituye el trato humano; ayuda a liberar tiempo y a organizar mejor el trabajo.</span>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <section id="contacto" class="seccion fondo-suave">
            <div class="contenedor contacto-grid">

                <div class="contacto-info">
                    <h2>Cuéntanos qué necesita tu negocio</h2>
                    <p>
                        Si tienes un restaurante, bar, cafetería o negocio relacionado con hostelería, Kitcherry puede ayudarte a detectar
                        qué procesos podrían automatizarse o mejorarse.
                    </p>

                    <ul>
                        <li><strong>Email:</strong> contacto@kitcherry.com</li>
                        <li><strong>Sector:</strong> hostelería y restauración</li>
                        <li><strong>Zona inicial:</strong> Comunitat Valenciana</li>
                        <li><strong>Enfoque:</strong> software, automatización e inteligencia artificial aplicada</li>
                    </ul>
                </div>

                <form class="formulario" method="POST" action="#contacto">
                    <?php if ($mensajeFormulario !== ""): ?>
                        <div class="mensaje-formulario <?php echo $tipoMensaje; ?>">
                            <?php echo $mensajeFormulario; ?>
                        </div>
                    <?php endif; ?>

                    <div class="campo">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" placeholder="Tu nombre">
                    </div>

                    <div class="campo">
                        <label for="email">Correo electrónico *</label>
                        <input type="email" id="email" name="email" placeholder="tu@email.com">
                    </div>

                    <div class="campo">
                        <label for="negocio">Nombre del negocio</label>
                        <input type="text" id="negocio" name="negocio" placeholder="Nombre de tu restaurante, bar o cafetería">
                    </div>

                    <div class="campo">
                        <label for="mensaje">Mensaje *</label>
                        <textarea id="mensaje" name="mensaje" placeholder="Explícanos qué te gustaría mejorar: consultas, reservas, alérgenos, organización interna..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-principal">Enviar consulta</button>
                </form>

            </div>
        </section>

    </main>

    <footer class="footer">
        <div class="contenedor footer-contenido">

            <div class="footer-col footer-marca">
                <?php if ($logoExiste): ?>
                    <img src="<?php echo $logoPath; ?>" alt="Kitcherry" class="footer-logo">
                <?php endif; ?>

                <div>
                    <strong>
                        <span class="kit">KIT</span><span class="cherry">CHERRY</span>
                    </strong>
                    <p>Herramientas de software para hostelería.</p>
                    <p class="footer-copy">© <?php echo date("Y"); ?> Kitcherry. Todos los derechos reservados.</p>
                </div>
            </div>

            <div class="footer-col">
                <h3>Empresa</h3>
                <nav class="footer-nav-col">
                    <a href="index.php">Inicio</a>
                    <a href="nosotros.php">Qué es Kitcherry</a>
                    <a href="index.php#servicios">Soluciones</a>
                    <a href="index.php#ia-practica">IA práctica</a>
                    <a href="index.php#contacto">Contacto</a>
                </nav>
            </div>

            <div class="footer-col">
                <h3>Contacto</h3>
                <ul class="footer-lista">
                    <li><strong>Email:</strong> contacto@kitcherry.com</li>
                    <li><strong>Sector:</strong> Hostelería y restauración</li>
                    <li><strong>Zona:</strong> Comunitat Valenciana</li>
                </ul>
            </div>

            <div class="footer-col">
                <h3>Legal</h3>
                <nav class="footer-nav-col">
                    <a href="aviso-legal.php">Aviso legal</a>
                    <a href="privacidad.php">Política de privacidad</a>
                    <a href="cookies.php">Política de cookies</a>
                </nav>

                <div class="footer-redes">
                    <a href="#" aria-label="LinkedIn de Kitcherry">LinkedIn</a>
                </div>
            </div>

        </div>
    </footer>

</body>
</html>