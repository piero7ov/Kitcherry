// ==========================================================
// KITCHERRY KITCHEN ASSISTANT
// Archivo: assets/js/asistente-flask.js
// Frontend del asistente conectado a Flask
// ==========================================================

const API_FLASK_CHAT = "http://127.0.0.1:5000/chat";

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

// ==========================================================
// CONFIGURACIÓN DE VOZ
// ==========================================================

const NOMBRE_VOZ_PREFERIDA = "Google español";
const IDIOMA_VOZ_PREFERIDA = "es-ES";

const VELOCIDAD_VOZ = 1.15;
const TONO_VOZ = 1;
const VOLUMEN_VOZ = 0.3;

// ==========================================================
// UTILIDADES
// ==========================================================

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
        "usando la API Flask, el manual interno y el modelo entrenado de Kitcherry." +
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

function textoAParrafosHTML(texto) {
    const textoSeguro = String(texto || "").trim();

    if (textoSeguro === "") {
        return "<p>Sin respuesta del asistente.</p>";
    }

    const partes = textoSeguro.split(/\n\s*\n/);

    return partes.map(function (parte) {
        return "<p>" + escaparHTML(parte.trim()) + "</p>";
    }).join("");
}

function esperar(ms) {
    return new Promise(function (resolve) {
        setTimeout(resolve, ms);
    });
}

// ==========================================================
// VOZ
// ==========================================================

function cargarVozPreferida() {
    if (!("speechSynthesis" in window)) {
        return;
    }

    const voces = window.speechSynthesis.getVoices();

    if (voces.length === 0) {
        return;
    }

    vozSeleccionada = voces.find(function (voz) {
        return voz.name.toLowerCase().includes(NOMBRE_VOZ_PREFERIDA.toLowerCase());
    });

    if (!vozSeleccionada) {
        vozSeleccionada = voces.find(function (voz) {
            return voz.lang === IDIOMA_VOZ_PREFERIDA;
        });
    }

    if (!vozSeleccionada) {
        vozSeleccionada = voces.find(function (voz) {
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

    mensajeVoz.onstart = function () {
        avatarHablando = true;
    };

    mensajeVoz.onend = function () {
        avatarHablando = false;
    };

    mensajeVoz.onerror = function () {
        avatarHablando = false;
    };

    window.speechSynthesis.speak(mensajeVoz);
}

if ("speechSynthesis" in window) {
    cargarVozPreferida();

    window.speechSynthesis.onvoiceschanged = function () {
        cargarVozPreferida();
    };
}

// ==========================================================
// BOTONES
// ==========================================================

btnAudio.addEventListener("click", function () {
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

btnLimpiar.addEventListener("click", function () {
    crearBienvenida();
    ocultarBotonLimpiar();
    mensajeInput.value = "";
    mensajeInput.focus();

    if ("speechSynthesis" in window) {
        window.speechSynthesis.cancel();
    }

    avatarHablando = false;
});

// ==========================================================
// CHAT CON ANIMACIÓN MÍNIMA DE 3 SEGUNDOS
// ==========================================================

chatForm.addEventListener("submit", async function (evento) {
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
        "<p>Analizando consulta del manual interno</p>" +
        "<div class='escribiendo'>" +
        "<span></span>" +
        "<span></span>" +
        "<span></span>" +
        "</div>",
        true
    );

    const inicioPensando = Date.now();

    try {
        const respuestaFetch = await fetch(API_FLASK_CHAT, {
            method: "POST",
            headers: {
                "Content-Type": "application/json; charset=utf-8"
            },
            body: JSON.stringify({
                mensaje: texto
            })
        });

        const json = await respuestaFetch.json();

        const tiempoPasado = Date.now() - inicioPensando;
        const tiempoRestante = Math.max(0, 3000 - tiempoPasado);

        await esperar(tiempoRestante);

        const respuestaTexto = json.respuesta || "Sin respuesta del asistente.";
        const htmlRespuesta = textoAParrafosHTML(respuestaTexto);

        mensajeCargando.classList.remove("mensaje-cargando");
        mensajeCargando.innerHTML =
            "<strong>Kitcherry Kitchen Assistant</strong>" +
            htmlRespuesta;

        bajarScroll();

        const textoParaLeer = textoPlanoDesdeHTML(htmlRespuesta);
        hablarTexto(textoParaLeer);

    } catch (error) {
        console.error("Error conectando con Flask:", error);

        const tiempoPasado = Date.now() - inicioPensando;
        const tiempoRestante = Math.max(0, 3000 - tiempoPasado);

        await esperar(tiempoRestante);

        const htmlError =
            "<p>No se ha podido conectar con la API Flask. Revisa que hayas ejecutado " +
            "<strong>py .\\019-api_flask.py</strong> y que el servidor esté activo en el puerto 5000.</p>";

        mensajeCargando.classList.remove("mensaje-cargando");
        mensajeCargando.innerHTML =
            "<strong>Kitcherry Kitchen Assistant</strong>" +
            htmlError;

        hablarTexto(textoPlanoDesdeHTML(htmlError));
    }

    btnEnviar.disabled = false;
    btnEnviar.textContent = "Enviar";
});

// ==========================================================
// RECONOCIMIENTO DE VOZ
// ==========================================================

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

    btnVoz.addEventListener("click", function () {
        if (escuchando) {
            reconocimiento.stop();
            return;
        }

        reconocimiento.start();
    });

    reconocimiento.onstart = function () {
        escuchando = true;
        btnVoz.classList.add("escuchando");
        btnVoz.textContent = "🎤";
        btnVoz.title = "Escuchando...";
        mensajeInput.placeholder = "Escuchando...";
    };

    reconocimiento.onresult = function (evento) {
        const textoReconocido = evento.results[0][0].transcript;

        if (mensajeInput.value.trim() === "") {
            mensajeInput.value = textoReconocido;
        } else {
            mensajeInput.value += " " + textoReconocido;
        }

        mensajeInput.focus();
    };

    reconocimiento.onerror = function () {
        btnVoz.classList.remove("escuchando");
        btnVoz.textContent = "🎙️";
        btnVoz.title = "Hablar";
        mensajeInput.placeholder = "Ejemplo: medidas arroz jazmín";
        escuchando = false;
    };

    reconocimiento.onend = function () {
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