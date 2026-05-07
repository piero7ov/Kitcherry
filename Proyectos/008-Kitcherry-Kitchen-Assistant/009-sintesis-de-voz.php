<?php
// ==========================================================
// KITCHERRY KITCHEN ASSISTANT
// Archivo: 009-sintesis-de-voz.php
// Chat basado en Ollama con reconocimiento y síntesis de voz
// ==========================================================

function limpiarRespuestaIA($texto) {
    $texto = trim($texto);

    $texto = str_replace("```html", "", $texto);
    $texto = str_replace("```php", "", $texto);
    $texto = str_replace("```", "", $texto);

    $texto = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $texto);
    $texto = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $texto);

    $texto = preg_replace('/^#{1,6}\s*(.*?)$/m', '<p><strong>$1</strong></p>', $texto);
    $texto = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $texto);
    $texto = preg_replace('/(^|\n)\s*[\*\-]\s+/', '<br>• ', $texto);

    $texto = str_replace("*", "", $texto);
    $texto = preg_replace("/\n{3,}/", "\n\n", $texto);

    if ($texto === strip_tags($texto)) {
        $partes = preg_split("/\n\s*\n/", $texto);
        $parrafos = [];

        foreach ($partes as $parte) {
            $parte = trim($parte);

            if ($parte !== "") {
                $parrafos[] = "<p>" . htmlspecialchars($parte, ENT_QUOTES, "UTF-8") . "</p>";
            }
        }

        $texto = implode("", $parrafos);
    }

    return $texto;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["ajax"]) && $_POST["ajax"] === "1") {

    header("Content-Type: application/json; charset=utf-8");

    $mensajeUsuario = trim($_POST["mensaje"] ?? "");

    if ($mensajeUsuario === "") {
        echo json_encode([
            "ok" => false,
            "respuesta" => "<p>Escribe una consulta válida.</p>"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sistema = "
        Eres Kitcherry Kitchen Assistant, un asistente digital de apoyo a cocina y producción interna.

        Tu función es ayudar al personal de cocina a consultar elaboraciones, pasos de preparación,
        mise en place, organización del servicio, conservación básica, emplatado y procedimientos internos.

        Normas de respuesta:
        - Devuelve únicamente HTML limpio.
        - No uses Markdown bajo ningún concepto.
        - No uses asteriscos para negritas ni listas.
        - No uses símbolos como **, *, #, ``` ni fences de código.
        - Si necesitas destacar una palabra, usa etiquetas <strong>.
        - Si necesitas separar ideas, usa párrafos HTML con <p>.
        - Si necesitas listar pasos, usa frases separadas en párrafos HTML, no listas Markdown.
        - Reduce tu respuesta a dos o tres párrafos.
        - Responde siempre en español.
        - Responde de forma clara, precisa, concisa y práctica.
        - No sustituyes al jefe de cocina ni al criterio profesional del equipo.
        - Si una pregunta afecta a alérgenos, seguridad alimentaria o decisiones delicadas, recomienda comprobar siempre la ficha técnica oficial del restaurante.
        - No inventes datos si no están claros.
        - Atiende a la siguiente petición por parte del usuario:

    ";

    $datos = [
        "model" => "llama3:latest",
        "prompt" => $sistema . $mensajeUsuario,
        "stream" => false
    ];

    $ch = curl_init("http://localhost:11434/api/generate");

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datos, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $resultado = curl_exec($ch);

    if ($resultado === false) {
        curl_close($ch);

        echo json_encode([
            "ok" => false,
            "respuesta" => "<p>No se ha podido conectar con el asistente. Revisa que Ollama esté funcionando.</p>"
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    curl_close($ch);

    $json = json_decode($resultado, true);

    $respuesta = $json["response"] ?? "<p>Sin respuesta del asistente.</p>";
    $respuesta = limpiarRespuestaIA($respuesta);

    echo json_encode([
        "ok" => true,
        "respuesta" => $respuesta
    ], JSON_UNESCAPED_UNICODE);

    exit;
}
?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kitcherry Kitchen Assistant</title>

    <style>
        @font-face {
            font-family: "Coolvetica";
            src: url("fuente/Coolvetica Rg.otf") format("opentype");
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }

        :root {
            --rojo: #C2182B;
            --rojo-oscuro: #991726;
            --rojo-suave: #fbeaec;
            --negro: #161616;
            --gris-texto: #555555;
            --gris-claro: #f6f6f6;
            --gris-medio: #e8e8e8;
            --blanco: #ffffff;
            --sombra: 0 18px 40px rgba(0, 0, 0, 0.08);
            --radio: 22px;
            --font-title: "Coolvetica", Arial, Helvetica, sans-serif;
            --font-body: Arial, Helvetica, sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            font-family: var(--font-body);
            background:
                radial-gradient(circle at top left, rgba(194, 24, 43, 0.12), transparent 34%),
                linear-gradient(135deg, #ffffff 0%, #f7f7f7 45%, #fbeaec 100%);
            color: var(--negro);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .oculto {
            display: none !important;
        }

        .cabecera {
            width: 100%;
            background: rgba(255, 255, 255, 0.92);
            border-bottom: 1px solid rgba(194, 24, 43, 0.12);
            backdrop-filter: blur(12px);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .contenedor-cabecera {
            max-width: 1180px;
            margin: 0 auto;
            padding: 18px 24px;
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

        .marca img {
            width: 58px;
            height: 58px;
            object-fit: contain;
            display: block;
        }

        .marca-texto {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .marca-texto strong {
            display: block;
            font-family: var(--font-title);
            font-size: 2.3rem;
            line-height: 0.9;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .marca-texto strong span {
            display: inline;
        }

        .marca-texto .kit {
            color: var(--negro);
        }

        .marca-texto .cherry {
            color: var(--rojo);
        }

        .marca-texto > span {
            display: block;
            margin-top: 5px;
            color: var(--gris-texto);
            font-size: 1.08rem;
            font-family: var(--font-title);
            letter-spacing: 0.03em;
        }

        .estado-asistente {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gris-texto);
            font-size: 0.95rem;
            background: var(--rojo-suave);
            border: 1px solid rgba(194, 24, 43, 0.16);
            padding: 10px 14px;
            border-radius: 999px;
        }

        .estado-asistente::before {
            content: "";
            width: 9px;
            height: 9px;
            background: var(--rojo);
            border-radius: 50%;
            box-shadow: 0 0 0 5px rgba(194, 24, 43, 0.12);
        }

        .chat-layout {
            max-width: 980px;
            height: calc(100vh - 95px);
            margin: 0 auto;
            padding: 34px 24px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .chat-card {
            flex: 1;
            min-height: 0;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(194, 24, 43, 0.14);
            border-radius: var(--radio);
            box-shadow: var(--sombra);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .chat-top {
            padding: 20px 22px;
            border-bottom: 1px solid var(--gris-medio);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            background: linear-gradient(135deg, #ffffff, #fff6f7);
        }

        .chat-top h1 {
            font-family: var(--font-title);
            color: var(--rojo);
            font-size: 2rem;
            line-height: 1;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .chat-top p {
            margin-top: 6px;
            color: var(--gris-texto);
            font-size: 0.95rem;
        }

        .chat-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: none;
        }

        .btn-limpiar,
        .btn-audio {
            border: 1px solid rgba(194, 24, 43, 0.18);
            background: var(--rojo-suave);
            color: var(--rojo-oscuro);
            padding: 9px 13px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .btn-limpiar:hover,
        .btn-audio:hover {
            background: var(--rojo);
            color: var(--blanco);
        }

        .btn-audio.activo {
            background: var(--rojo);
            color: var(--blanco);
        }

        .chat-body {
            flex: 1;
            min-height: 0;
            padding: 24px;
            overflow-y: auto;
            scroll-behavior: smooth;
            background:
                linear-gradient(rgba(255, 255, 255, 0.84), rgba(255, 255, 255, 0.84)),
                radial-gradient(circle at bottom right, rgba(194, 24, 43, 0.12), transparent 38%);
        }

        .chat-body::-webkit-scrollbar {
            width: 10px;
        }

        .chat-body::-webkit-scrollbar-track {
            background: #f3f3f3;
        }

        .chat-body::-webkit-scrollbar-thumb {
            background: rgba(194, 24, 43, 0.35);
            border-radius: 999px;
        }

        .chat-body::-webkit-scrollbar-thumb:hover {
            background: rgba(194, 24, 43, 0.55);
        }

        .mensaje {
            max-width: 82%;
            margin-bottom: 18px;
            padding: 15px 17px;
            border-radius: 18px;
            line-height: 1.55;
            font-size: 0.98rem;
        }

        .mensaje strong {
            display: block;
            margin-bottom: 6px;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .mensaje.usuario {
            margin-left: auto;
            background: var(--rojo);
            color: var(--blanco);
            border-bottom-right-radius: 6px;
        }

        .mensaje.asistente {
            margin-right: auto;
            background: var(--blanco);
            color: var(--negro);
            border: 1px solid var(--gris-medio);
            border-bottom-left-radius: 6px;
        }

        .mensaje.asistente p {
            margin-bottom: 10px;
        }

        .mensaje.asistente p:last-child {
            margin-bottom: 0;
        }

        .mensaje-bienvenida {
            max-width: 760px;
            margin: 0 auto;
            text-align: center;
            padding: 60px 20px;
        }

        .mensaje-bienvenida p {
            color: var(--gris-texto);
            line-height: 1.7;
            font-size: 1.02rem;
        }

        .mensaje-cargando {
            opacity: 0.8;
        }

        .escribiendo {
            display: flex;
            align-items: center;
            gap: 6px;
            padding-top: 4px;
        }

        .escribiendo span {
            width: 7px;
            height: 7px;
            background: var(--rojo);
            border-radius: 50%;
            animation: saltoPunto 1s infinite ease-in-out;
        }

        .escribiendo span:nth-child(2) {
            animation-delay: 0.15s;
        }

        .escribiendo span:nth-child(3) {
            animation-delay: 0.3s;
        }

        @keyframes saltoPunto {
            0%, 80%, 100% {
                transform: translateY(0);
                opacity: 0.45;
            }

            40% {
                transform: translateY(-5px);
                opacity: 1;
            }
        }

        .chat-form {
            border-top: 1px solid var(--gris-medio);
            padding: 18px;
            display: flex;
            gap: 12px;
            background: var(--blanco);
        }

        .chat-form input[type="text"] {
            flex: 1;
            border: 1px solid var(--gris-medio);
            border-radius: 999px;
            padding: 15px 18px;
            font-size: 1rem;
            outline: none;
            color: var(--negro);
            background: #fafafa;
        }

        .chat-form input[type="text"]:focus {
            border-color: var(--rojo);
            box-shadow: 0 0 0 4px rgba(194, 24, 43, 0.10);
            background: var(--blanco);
        }

        .chat-form button {
            border: none;
            border-radius: 999px;
            padding: 15px 24px;
            background: var(--rojo);
            color: var(--blanco);
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .chat-form button:hover {
            background: var(--rojo-oscuro);
            transform: translateY(-1px);
        }

        .chat-form button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-voz {
            width: 52px;
            min-width: 52px;
            padding: 15px 0 !important;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            background: var(--negro) !important;
        }

        .btn-voz:hover {
            background: #000000 !important;
        }

        .btn-voz.escuchando {
            background: var(--rojo) !important;
            box-shadow: 0 0 0 5px rgba(194, 24, 43, 0.14);
            animation: pulsoMicro 1s infinite ease-in-out;
        }

        @keyframes pulsoMicro {
            0%, 100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        .nota {
            color: var(--gris-texto);
            font-size: 0.82rem;
            text-align: center;
            line-height: 1.5;
        }

        @media (max-width: 720px) {
            .contenedor-cabecera {
                flex-direction: column;
                align-items: flex-start;
            }

            .estado-asistente {
                width: 100%;
                justify-content: center;
            }

            .chat-layout {
                height: calc(100vh - 170px);
                padding: 22px 14px;
            }

            .chat-card {
                min-height: 0;
            }

            .chat-top {
                flex-direction: column;
                align-items: flex-start;
            }

            .chat-top h1 {
                font-size: 1.55rem;
            }

            .chat-actions {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
            }

            .btn-limpiar,
            .btn-audio {
                width: 100%;
                text-align: center;
            }

            .mensaje {
                max-width: 100%;
            }

            .chat-form {
                flex-direction: column;
            }

            .chat-form button {
                width: 100%;
            }

            .btn-voz {
                width: 100%;
            }

            .marca-texto strong {
                font-size: 2.35rem;
            }

            .marca-texto > span {
                font-size: 0.98rem;
            }
        }
    </style>
</head>

<body>

    <header class="cabecera">
        <div class="contenedor-cabecera">

            <a href="#" class="marca">
                <img src="img/logo.png" alt="Logo de Kitcherry">

                <div class="marca-texto">
                    <strong>
                        <span class="kit">KIT</span><span class="cherry">CHERRY</span>
                    </strong>
                    <span>Kitchen Assistant</span>
                </div>
            </a>

            <div class="estado-asistente">
                Asistente de cocina activo
            </div>

        </div>
    </header>

    <main class="chat-layout">

        <section class="chat-card">

            <div class="chat-top">
                <div>
                    <h1>Kitchen Assistant</h1>
                    <p>Consulta elaboraciones, pasos de preparación, mise en place y procedimientos internos de cocina.</p>
                </div>

                <div class="chat-actions">
                    <button type="button" class="btn-audio activo" id="btnAudio">
                        Voz activada
                    </button>

                    <button type="button" class="btn-limpiar oculto" id="btnLimpiar">
                        Limpiar chat
                    </button>
                </div>
            </div>

            <div class="chat-body" id="chatBody">

                <div class="mensaje-bienvenida" id="mensajeBienvenida">
                    <p>
                        Escribe una consulta sobre elaboraciones, organización del servicio, conservación,
                        emplatado o procedimientos internos. El asistente responderá como apoyo operativo
                        para el equipo de cocina.
                    </p>
                </div>

            </div>

            <form class="chat-form" id="chatForm">
                <input
                    type="text"
                    name="mensaje"
                    id="mensajeInput"
                    placeholder="Ejemplo: ¿Cómo organizo la mise en place antes del servicio?"
                    autocomplete="off"
                    required
                >

                <button type="button" id="btnVoz" class="btn-voz" title="Hablar">
                    🎙️
                </button>

                <button type="submit" id="btnEnviar">Enviar</button>
            </form>

        </section>

        <p class="nota">
            Kitcherry Kitchen Assistant es una herramienta de apoyo. Para alérgenos, cantidades exactas o decisiones delicadas,
            se debe comprobar siempre la ficha técnica oficial del restaurante.
        </p>

    </main>

    <script>
        const chatBody = document.getElementById("chatBody");
        const chatForm = document.getElementById("chatForm");
        const mensajeInput = document.getElementById("mensajeInput");
        const btnEnviar = document.getElementById("btnEnviar");
        const btnLimpiar = document.getElementById("btnLimpiar");
        const btnVoz = document.getElementById("btnVoz");
        const btnAudio = document.getElementById("btnAudio");

        let vozActiva = true;
        let vozSeleccionada = null;

        /* ==========================================================
        CONFIGURACIÓN DE VOZ
        Aquí puedes cambiar la voz, velocidad, tono y volumen.
        ========================================================== */

        const NOMBRE_VOZ_PREFERIDA = "Google español";
        const IDIOMA_VOZ_PREFERIDA = "es-ES";

        const VELOCIDAD_VOZ = 1.15;
        const TONO_VOZ = 1;
        const VOLUMEN_VOZ = 1;

        function escaparHTML(texto) {
            const div = document.createElement("div");
            div.textContent = texto;
            return div.innerHTML;
        }

        function bajarScroll() {
            chatBody.scrollTop = chatBody.scrollHeight;
        }

        function mostrarBotonLimpiar() {
            btnLimpiar.classList.remove("oculto");
        }

        function ocultarBotonLimpiar() {
            btnLimpiar.classList.add("oculto");
        }

        function crearBienvenida() {
            chatBody.innerHTML =
                "<div class='mensaje-bienvenida' id='mensajeBienvenida'>" +
                    "<p>" +
                        "Escribe una consulta sobre elaboraciones, organización del servicio, conservación, " +
                        "emplatado o procedimientos internos. El asistente responderá como apoyo operativo " +
                        "para el equipo de cocina." +
                    "</p>" +
                "</div>";
        }

        function eliminarBienvenida() {
            const bienvenida = document.getElementById("mensajeBienvenida");

            if (bienvenida) {
                bienvenida.remove();
            }
        }

        function crearMensajeUsuario(texto) {
            const mensaje = document.createElement("div");

            mensaje.className = "mensaje usuario";
            mensaje.innerHTML =
                "<strong>Tú</strong>" +
                escaparHTML(texto);

            chatBody.appendChild(mensaje);
            bajarScroll();
        }

        function crearMensajeAsistente(html, cargando) {
            const mensaje = document.createElement("div");

            if (cargando) {
                mensaje.className = "mensaje asistente mensaje-cargando";
            } else {
                mensaje.className = "mensaje asistente";
            }

            mensaje.innerHTML =
                "<strong>Kitcherry Kitchen Assistant</strong>" +
                html;

            chatBody.appendChild(mensaje);
            bajarScroll();

            return mensaje;
        }

        function textoPlanoDesdeHTML(html) {
            const div = document.createElement("div");
            div.innerHTML = html;

            return div.textContent || div.innerText || "";
        }

        function cargarVozPreferida() {
            if (!("speechSynthesis" in window)) {
                return;
            }

            const voces = window.speechSynthesis.getVoices();

            if (voces.length === 0) {
                return;
            }

            vozSeleccionada = voces.find(function(voz) {
                return voz.name.toLowerCase().includes(NOMBRE_VOZ_PREFERIDA.toLowerCase());
            });

            if (!vozSeleccionada) {
                vozSeleccionada = voces.find(function(voz) {
                    return voz.lang === IDIOMA_VOZ_PREFERIDA;
                });
            }

            if (!vozSeleccionada) {
                vozSeleccionada = voces.find(function(voz) {
                    return voz.lang.toLowerCase().startsWith("es");
                });
            }

            console.log("Voz seleccionada:", vozSeleccionada);
            console.log("Voces disponibles:", voces.map(function(voz) {
                return voz.name + " | " + voz.lang;
            }));
        }

        function hablarTexto(texto) {
            if (!vozActiva) {
                return;
            }

            if (!("speechSynthesis" in window)) {
                return;
            }

            window.speechSynthesis.cancel();

            const mensajeVoz = new SpeechSynthesisUtterance(texto);

            mensajeVoz.lang = IDIOMA_VOZ_PREFERIDA;
            mensajeVoz.rate = VELOCIDAD_VOZ;
            mensajeVoz.pitch = TONO_VOZ;
            mensajeVoz.volume = VOLUMEN_VOZ;

            if (vozSeleccionada) {
                mensajeVoz.voice = vozSeleccionada;
                mensajeVoz.lang = vozSeleccionada.lang;
            }

            window.speechSynthesis.speak(mensajeVoz);
        }

        if ("speechSynthesis" in window) {
            cargarVozPreferida();

            window.speechSynthesis.onvoiceschanged = function() {
                cargarVozPreferida();
            };
        }

        btnAudio.addEventListener("click", function() {
            vozActiva = !vozActiva;

            if (vozActiva) {
                btnAudio.classList.add("activo");
                btnAudio.textContent = "Voz activada";
            } else {
                btnAudio.classList.remove("activo");
                btnAudio.textContent = "Voz desactivada";

                if ("speechSynthesis" in window) {
                    window.speechSynthesis.cancel();
                }
            }
        });

        btnLimpiar.addEventListener("click", function() {
            crearBienvenida();
            ocultarBotonLimpiar();
            mensajeInput.value = "";
            mensajeInput.focus();

            if ("speechSynthesis" in window) {
                window.speechSynthesis.cancel();
            }
        });

        chatForm.addEventListener("submit", async function(evento) {
            evento.preventDefault();

            const texto = mensajeInput.value.trim();

            if (texto === "") {
                return;
            }

            eliminarBienvenida();
            mostrarBotonLimpiar();

            crearMensajeUsuario(texto);

            mensajeInput.value = "";
            mensajeInput.focus();

            btnEnviar.disabled = true;
            btnEnviar.textContent = "Pensando...";

            const mensajeCargando = crearMensajeAsistente(
                "<p>Preparando respuesta</p>" +
                "<div class='escribiendo'>" +
                    "<span></span>" +
                    "<span></span>" +
                    "<span></span>" +
                "</div>",
                true
            );

            const datos = new FormData();
            datos.append("ajax", "1");
            datos.append("mensaje", texto);

            try {
                const respuesta = await fetch(window.location.pathname, {
                    method: "POST",
                    body: datos
                });

                const json = await respuesta.json();

                const htmlRespuesta = json.respuesta || "<p>Sin respuesta del asistente.</p>";

                mensajeCargando.classList.remove("mensaje-cargando");
                mensajeCargando.innerHTML =
                    "<strong>Kitcherry Kitchen Assistant</strong>" +
                    htmlRespuesta;

                bajarScroll();

                const textoParaLeer = textoPlanoDesdeHTML(htmlRespuesta);
                hablarTexto(textoParaLeer);

            } catch (error) {
                const htmlError = "<p>No se ha podido procesar la respuesta. Revisa que el servidor local y Ollama estén funcionando.</p>";

                mensajeCargando.classList.remove("mensaje-cargando");
                mensajeCargando.innerHTML =
                    "<strong>Kitcherry Kitchen Assistant</strong>" +
                    htmlError;

                hablarTexto(textoPlanoDesdeHTML(htmlError));
            }

            btnEnviar.disabled = false;
            btnEnviar.textContent = "Enviar";
        });

        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

        if (!SpeechRecognition) {
            btnVoz.disabled = true;
            btnVoz.title = "Reconocimiento de voz no disponible en este navegador";
            btnVoz.textContent = "🚫";
        } else {
            const reconocimiento = new SpeechRecognition();

            reconocimiento.lang = "es-ES";
            reconocimiento.continuous = false;
            reconocimiento.interimResults = false;

            let escuchando = false;

            btnVoz.addEventListener("click", function() {
                if (escuchando) {
                    reconocimiento.stop();
                    return;
                }

                reconocimiento.start();
            });

            reconocimiento.onstart = function() {
                escuchando = true;
                btnVoz.classList.add("escuchando");
                btnVoz.textContent = "🎤";
                btnVoz.title = "Escuchando...";
                mensajeInput.placeholder = "Escuchando...";
            };

            reconocimiento.onresult = function(evento) {
                const textoReconocido = evento.results[0][0].transcript;

                if (mensajeInput.value.trim() === "") {
                    mensajeInput.value = textoReconocido;
                } else {
                    mensajeInput.value += " " + textoReconocido;
                }

                mensajeInput.focus();
            };

            reconocimiento.onerror = function() {
                btnVoz.classList.remove("escuchando");
                btnVoz.textContent = "🎙️";
                btnVoz.title = "Hablar";
                mensajeInput.placeholder = "Ejemplo: ¿Cómo organizo la mise en place antes del servicio?";
                escuchando = false;
            };

            reconocimiento.onend = function() {
                btnVoz.classList.remove("escuchando");
                btnVoz.textContent = "🎙️";
                btnVoz.title = "Hablar";
                mensajeInput.placeholder = "Ejemplo: ¿Cómo organizo la mise en place antes del servicio?";
                escuchando = false;
            };
        }
    </script>

</body>
</html>