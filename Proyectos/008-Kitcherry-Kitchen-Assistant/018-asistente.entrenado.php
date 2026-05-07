<?php
// ==========================================================
// KITCHERRY KITCHEN ASSISTANT
// Archivo: 018-asistente.entrenado.php
// Chat con modelo entrenado, reconocimiento, síntesis de voz y avatar 3D
// ==========================================================

set_time_limit(300);

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


function ejecutarInferenciaPython($mensajeUsuario) {
    /*
    ==========================================================
    CONEXIÓN PHP → PYTHON
    ==========================================================

    Este PHP no usa Ollama.

    Ahora llama a:
    - venv/Scripts/python.exe
    - 017-inferencia_web.py

    017-inferencia_web.py usa:
    - 006-inferencia.py
    - modelo-kitcherry-cocina-fusionado/
    - entrenamiento/*.jsonl
    */

    $pythonPath = __DIR__ . DIRECTORY_SEPARATOR . "venv" . DIRECTORY_SEPARATOR . "Scripts" . DIRECTORY_SEPARATOR . "python.exe";
    $scriptPath = __DIR__ . DIRECTORY_SEPARATOR . "017-inferencia_web.py";

    if (!file_exists($pythonPath)) {
        return [
            "ok" => false,
            "respuesta" => "No se ha encontrado Python dentro del entorno virtual. Revisa la ruta: " . $pythonPath,
            "error" => "Python no encontrado"
        ];
    }

    if (!file_exists($scriptPath)) {
        return [
            "ok" => false,
            "respuesta" => "No se ha encontrado el archivo 017-inferencia_web.py.",
            "error" => "Script de inferencia web no encontrado"
        ];
    }

    $comando = '"' . $pythonPath . '" "' . $scriptPath . '"';

    $descriptores = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];

    $proceso = proc_open(
        $comando,
        $descriptores,
        $pipes,
        __DIR__
    );

    if (!is_resource($proceso)) {
        return [
            "ok" => false,
            "respuesta" => "No se ha podido iniciar el proceso de inferencia.",
            "error" => "proc_open falló"
        ];
    }

    $entrada = json_encode([
        "mensaje" => $mensajeUsuario
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    fwrite($pipes[0], $entrada);
    fclose($pipes[0]);

    $salida = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $errorSalida = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $codigoSalida = proc_close($proceso);

    $salida = trim($salida);
    $errorSalida = trim($errorSalida);

    if ($salida === "") {
        return [
            "ok" => false,
            "respuesta" => "El asistente no devolvió respuesta. Revisa el entorno Python o el modelo fusionado.",
            "error" => $errorSalida
        ];
    }

    $json = json_decode($salida, true);

    if (!is_array($json)) {
        return [
            "ok" => false,
            "respuesta" => "La respuesta del asistente no tiene formato JSON válido.",
            "error" => $salida . "\n" . $errorSalida
        ];
    }

    return [
        "ok" => $json["ok"] ?? false,
        "respuesta" => $json["respuesta"] ?? "Sin respuesta del asistente.",
        "error" => $json["error"] ?? $errorSalida
    ];
}


if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["ajax"]) && $_POST["ajax"] === "1") {

    header("Content-Type: application/json; charset=utf-8");

    $mensajeUsuario = trim($_POST["mensaje"] ?? "");

    if ($mensajeUsuario === "") {
        echo json_encode([
            "ok" => false,
            "respuesta" => "<p>Escribe una consulta válida.</p>"
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    $resultado = ejecutarInferenciaPython($mensajeUsuario);

    $respuestaTexto = $resultado["respuesta"] ?? "Sin respuesta del asistente.";
    $respuestaHtml = limpiarRespuestaIA($respuestaTexto);

    echo json_encode([
        "ok" => $resultado["ok"] ?? false,
        "respuesta" => $respuestaHtml,
        "error" => $resultado["error"] ?? ""
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

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
                Asistente entrenado activo
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

                    <a-entity id="avatarRig">

                        <a-entity
                            id="avatarModel"
                            gltf-model="#avatar3d"
                            position="0 1 -2"
                            scale="2 2 2">
                        </a-entity>

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
                        <p>Consulta elaboraciones, gramajes, pasos de preparación, mise en place y procedimientos internos de cocina.</p>
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
                            Escribe una consulta sobre elaboraciones, gramajes, organización del servicio,
                            conservación, emplatado o procedimientos internos. El asistente responderá
                            usando el manual interno de cocina y el modelo entrenado de Kitcherry.
                        </p>
                    </div>

                </div>

                <form class="chat-form" id="chatForm">
                    <input
                        type="text"
                        name="mensaje"
                        id="mensajeInput"
                        placeholder="Ejemplo: medidas arroz jazmín"
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
        let intensidadHabla = 0;

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
                        "Escribe una consulta sobre elaboraciones, gramajes, organización del servicio, " +
                        "conservación, emplatado o procedimientos internos. El asistente responderá " +
                        "usando el manual interno de cocina y el modelo entrenado de Kitcherry." +
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
                "<p>Preparando respuesta con el modelo entrenado</p>" +
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

                const textoBruto = await respuesta.text();

                let json;

                try {
                    json = JSON.parse(textoBruto);
                } catch (errorJson) {
                    console.error("Respuesta no JSON recibida desde PHP:", textoBruto);

                    throw new Error("La respuesta del servidor no es JSON válido.");
                }

                const htmlRespuesta = json.respuesta || "<p>Sin respuesta del asistente.</p>";

                mensajeCargando.classList.remove("mensaje-cargando");
                mensajeCargando.innerHTML =
                    "<strong>Kitcherry Kitchen Assistant</strong>" +
                    htmlRespuesta;

                bajarScroll();

                const textoParaLeer = textoPlanoDesdeHTML(htmlRespuesta);
                hablarTexto(textoParaLeer);

            } catch (error) {
                console.error("Error en la petición:", error);

                const htmlError = "<p>No se ha podido procesar la respuesta. Revisa que Apache, PHP, el entorno Python y el modelo entrenado estén funcionando.</p>";

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
                mensajeInput.placeholder = "Ejemplo: medidas arroz jazmín";
                escuchando = false;
            };

            reconocimiento.onend = function() {
                btnVoz.classList.remove("escuchando");
                btnVoz.textContent = "🎙️";
                btnVoz.title = "Hablar";
                mensajeInput.placeholder = "Ejemplo: medidas arroz jazmín";
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

            if (avatarHablando) {
                intensidadHabla += (1 - intensidadHabla) * 0.04;
            } else {
                intensidadHabla += (0 - intensidadHabla) * 0.06;
            }

            if (avatarRig) {
                const rotXBase = Math.sin(t * 0.7) * 0.6;
                const rotYBase = Math.sin(t * 0.9) * 1.8;
                const rotZBase = Math.sin(t * 0.5) * 0.35;
                const posYBase = Math.sin(t * 1.1) * 0.015;

                const rotXHabla = Math.sin(t * 1.8) * 0.9 * intensidadHabla;
                const rotYHabla = Math.sin(t * 2.1) * 0.6 * intensidadHabla;
                const rotZHabla = Math.sin(t * 1.4) * 0.25 * intensidadHabla;
                const posYHabla = Math.sin(t * 2.8) * 0.035 * intensidadHabla;

                const rotX = rotXBase + rotXHabla;
                const rotY = rotYBase + rotYHabla;
                const rotZ = rotZBase + rotZHabla;
                const posY = posYBase + posYHabla;

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