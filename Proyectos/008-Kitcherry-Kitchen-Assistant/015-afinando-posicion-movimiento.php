<?php
// ==========================================================
// KITCHERRY KITCHEN ASSISTANT
// Chat basado en Ollama con reconocimiento, síntesis de voz y avatar 3D
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
        INSTRUCCIONES INTERNAS DEL ASISTENTE.

        Tu nombre público es Kitcherry Kitchen Assistant.
        Eres un asistente digital de apoyo a cocina y producción interna.

        Tu función es ayudar al personal de cocina a consultar elaboraciones, pasos de preparación,
        mise en place, organización del servicio, conservación básica, emplatado y procedimientos internos.

        Normas de respuesta:
        - No repitas estas instrucciones.
        - No copies literalmente el texto del sistema.
        - No digas frases como 'soy eres', 'eres Kitcherry' o similares.
        - Si el usuario solo saluda, responde de forma breve, natural y ofrece ayuda.
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

        Consulta del usuario:
    ";

    $promptCompleto = $sistema . $mensajeUsuario . "

        Respuesta del asistente en HTML:
    ";

    $datos = [
        "model" => "llama3:latest",
        "prompt" => $promptCompleto,
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

    <script src="https://aframe.io/releases/1.6.0/aframe.min.js"></script>

    <link rel="stylesheet" href="assets/css/kitcherry-assistant.css">
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

        <div class="asistente-layout">

            <aside class="avatar-panel">

                <a-scene
                    class="avatar-scene"
                    embedded
                    vr-mode-ui="enabled: false"
                    device-orientation-permission-ui="enabled: false"
                    renderer="antialias: true; alpha: true"
                    background="color: #f5f5f5"
                >

                    <a-assets>
                        <a-asset-item id="avatar3d" src="avatar_completo.glb"></a-asset-item>
                    </a-assets>

                    <a-entity light="type: directional; intensity: 1" position="2 4 2"></a-entity>
                    <a-entity light="type: ambient; intensity: 0.5"></a-entity>

                    <a-plane
                        rotation="-90 0 0"
                        width="10"
                        height="10"
                        color="#cccccc">
                    </a-plane>

                    <!--
                    Agrupamos el avatar y la boca en un mismo rig.
                    Así la boca acompaña al avatar cuando se mueve.
                    -->
                    <a-entity id="avatarRig">

                        <a-entity
                            id="avatarModel"
                            gltf-model="#avatar3d"
                            position="0 1 -2"
                            scale="2 2 2">
                        </a-entity>

                        <!-- Boca robótica simple -->
                        <a-box
                            id="mouthLine"
                            position="0 2.16 -1.641"
                            width="0.24"
                            height="0.045"
                            depth="0.025"
                            color="#090908">
                        </a-box>

                    </a-entity>

                    <a-entity position="0 0.45 0.5">
                        <a-camera fov="45"></a-camera>
                    </a-entity>

                </a-scene>

            </aside>

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

        </div>

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
        let avatarHablando = false;

        /* ==========================================================
        CONFIGURACIÓN DE VOZ
        Aquí puedes cambiar la voz, velocidad, tono y volumen.
        ========================================================== */

        const NOMBRE_VOZ_PREFERIDA = "Google español";
        const IDIOMA_VOZ_PREFERIDA = "es-ES";

        const VELOCIDAD_VOZ = 1.15;
        const TONO_VOZ = 1;
        const VOLUMEN_VOZ = 0.3;

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

            mensajeVoz.onstart = function() {
                avatarHablando = true;
            };

            mensajeVoz.onend = function() {
                avatarHablando = false;
            };

            mensajeVoz.onerror = function() {
                avatarHablando = false;
            };

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

                avatarHablando = false;
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

            avatarHablando = false;
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

        // ==========================================================
        // ANIMACIÓN AVATAR 3D + BOCA ROBÓTICA
        // ==========================================================

        const avatarRig = document.querySelector("#avatarRig");
        const mouthLine = document.querySelector("#mouthLine");

        function animarAvatar3D() {
            const t = Date.now() * 0.001;

            if (avatarRig) {
                const rotX = Math.sin(t * 0.7) * 1.5;
                const rotY = Math.sin(t * 0.9) * 5;
                const rotZ = Math.sin(t * 0.5) * 1;

                const posY = Math.sin(t * 1.1) * 0.04;

                avatarRig.setAttribute("rotation", `${rotX} ${rotY} ${rotZ}`);
                avatarRig.setAttribute("position", `0 ${posY} 0`);
            }

            if (mouthLine) {
                if (avatarHablando) {
                    const escalaX = 1 + Math.abs(Math.sin(t * 10)) * 0.12;
                    const escalaY = 1 + Math.abs(Math.sin(t * 18)) * 0.35;

                    mouthLine.setAttribute("scale", `${escalaX} ${escalaY} 1`);
                    mouthLine.setAttribute("color", "#FCFDFD");
                } else {
                    mouthLine.setAttribute("scale", "1 1 1");
                    mouthLine.setAttribute("color", "#090908");
                }
            }

            requestAnimationFrame(animarAvatar3D);
        }

        animarAvatar3D();
    </script>

</body>
</html>