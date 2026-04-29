// ==========================================================
// KITCHERRY - CHATBOT WEB MEJORADO / OPTIMIZADO CON FLASK
// Archivo: chatbot.js
// Guardar este archivo en UTF-8
// ==========================================================

console.log("chatbot.js cargado: versión flask-typewriter-3");

const chatForm = document.getElementById("chatForm");
const userInput = document.getElementById("userInput");
const chatMessages = document.getElementById("chatMessages");
const suggestions = document.getElementById("suggestions");

const modeButtons = document.querySelectorAll(".mode-option");
const chatTitle = document.getElementById("chatTitle");
const chatSubtitle = document.getElementById("chatSubtitle");
const modeHint = document.getElementById("modeHint");
const clearChatBtn = document.getElementById("clearChatBtn");
const chatDisclaimer = document.getElementById("chatDisclaimer");

const contactCta = document.getElementById("contactCta");
const contactButton = document.getElementById("contactButton");

let currentMode = "kitcherry";
let isWaitingResponse = false;

// ----------------------------------------------------------
// Tiempos visuales por modo
// ----------------------------------------------------------

const modeTiming = {
    kitcherry: {
        typeSpeed: 22,
        chunkSize: 1,
        minLoading: 900
    },

    restaurante: {
        typeSpeed: 22,
        chunkSize: 1,
        minLoading: 900
    }
};

const modeConfig = {
    kitcherry: {
        title: "Asistente Kitcherry",
        subtitle: "Modo corporativo",
        hint: "Estás usando el modo corporativo. Puedes preguntar por Kitcherry, sus productos y su enfoque para hostelería.",
        welcome: "Hola, soy el asistente de Kitcherry. Estoy aquí para ayudarte a conocer mejor la propuesta, sus soluciones y su enfoque para negocios de hostelería.",
        placeholder: "Escribe tu pregunta sobre Kitcherry...",
        disclaimer: "El chatbot puede cometer errores. La información mostrada es orientativa y forma parte de una demostración del sistema Kitcherry.",
        suggestions: [
            {
                label: "¿Qué es Kitcherry?",
                question: "¿Qué es Kitcherry?"
            },
            {
                label: "Productos",
                question: "¿Qué productos ofrece actualmente Kitcherry?"
            },
            {
                label: "Producto estrella",
                question: "¿Cuál es el producto estrella de Kitcherry?"
            },
            {
                label: "Modo restaurante",
                question: "¿Qué es el modo restaurante de Kitcherry?"
            }
        ]
    },

    restaurante: {
        title: "Asistente Restaurante",
        subtitle: "Modo Kamado",
        hint: "Estás usando el modo restaurante. Esta demo muestra cómo el chatbot puede adaptarse a un negocio hostelero concreto usando información de Kamado.",
        welcome: "Hola, estás probando el modo restaurante. Puedes consultar información sobre Kamado, su carta, alérgenos, reservas, delivery, cócteles o platos de referencia.",
        placeholder: "Escribe tu pregunta sobre Kamado...",
        disclaimer: "La información del chatbot es orientativa y puede contener errores. Los precios, platos y disponibilidad pueden cambiar. En caso de alergias o intolerancias, confirma siempre la información con el personal del restaurante.",
        suggestions: [
            {
                label: "¿Qué es Kamado?",
                question: "¿Qué es Kamado?"
            },
            {
                label: "Carta",
                question: "¿Qué platos puedo encontrar en Kamado?"
            },
            {
                label: "Pad Thai",
                question: "¿Cuánto cuesta Pad Thai en Kamado?"
            },
            {
                label: "Cócteles",
                question: "¿Kamado tiene cócteles sin alcohol?"
            }
        ]
    }
};

// ----------------------------------------------------------
// Utilidades
// ----------------------------------------------------------

function sleep(ms) {
    return new Promise(function (resolve) {
        setTimeout(resolve, ms);
    });
}

function scrollToBottom() {
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function getTiming(mode) {
    return modeTiming[mode] || modeTiming.kitcherry;
}

async function waitMinimumLoading(startTime, mode) {
    const timing = getTiming(mode);
    const elapsedTime = Date.now() - startTime;

    if (elapsedTime < timing.minLoading) {
        await sleep(timing.minLoading - elapsedTime);
    }
}

// ----------------------------------------------------------
// Mensajes
// ----------------------------------------------------------

function addMessage(text, type) {
    const message = document.createElement("div");
    message.classList.add("message", type);

    const bubble = document.createElement("div");
    bubble.classList.add("bubble");
    bubble.textContent = text;

    message.appendChild(bubble);
    chatMessages.appendChild(message);

    scrollToBottom();

    return bubble;
}

async function addBotMessageWithTyping(text, mode) {
    const timing = getTiming(mode);

    const message = document.createElement("div");
    message.classList.add("message", "bot");

    const bubble = document.createElement("div");
    bubble.classList.add("bubble");

    message.appendChild(bubble);
    chatMessages.appendChild(message);

    scrollToBottom();

    let currentText = "";

    for (let i = 0; i < text.length; i += timing.chunkSize) {
        currentText = text.slice(0, i + timing.chunkSize);
        bubble.textContent = currentText;
        scrollToBottom();
        await sleep(timing.typeSpeed);
    }

    bubble.textContent = text;
    scrollToBottom();
}

function addLoadingMessage() {
    const message = document.createElement("div");
    message.classList.add("message", "bot", "loading-message");
    message.id = "loadingMessage";

    const bubble = document.createElement("div");
    bubble.classList.add("bubble", "typing");

    bubble.innerHTML = `
        <span></span>
        <span></span>
        <span></span>
    `;

    message.appendChild(bubble);
    chatMessages.appendChild(message);

    scrollToBottom();
}

function removeLoadingMessage() {
    const loadingMessage = document.getElementById("loadingMessage");

    if (loadingMessage) {
        loadingMessage.remove();
    }
}

function clearMessagesWithWelcome() {
    chatMessages.innerHTML = "";
    addMessage(modeConfig[currentMode].welcome, "bot");
    hideContactCta();
}

// ----------------------------------------------------------
// Sugerencias
// ----------------------------------------------------------

function renderSuggestions() {
    suggestions.innerHTML = "";

    modeConfig[currentMode].suggestions.forEach(function (item) {
        const button = document.createElement("button");
        button.type = "button";
        button.textContent = item.label;
        button.dataset.question = item.question;

        button.addEventListener("click", function () {
            sendQuestion(item.question);
        });

        suggestions.appendChild(button);
    });
}

// ----------------------------------------------------------
// Modos
// ----------------------------------------------------------

function setMode(mode) {
    if (!modeConfig[mode]) {
        return;
    }

    currentMode = mode;

    modeButtons.forEach(function (button) {
        if (button.dataset.mode === mode) {
            button.classList.add("active");
        } else {
            button.classList.remove("active");
        }
    });

    chatTitle.textContent = modeConfig[mode].title;
    chatSubtitle.textContent = modeConfig[mode].subtitle;
    modeHint.textContent = modeConfig[mode].hint;
    userInput.placeholder = modeConfig[mode].placeholder;

    if (chatDisclaimer) {
        chatDisclaimer.textContent = modeConfig[mode].disclaimer;
    }

    renderSuggestions();
    clearMessagesWithWelcome();
}

modeButtons.forEach(function (button) {
    button.addEventListener("click", function () {
        if (isWaitingResponse) {
            return;
        }

        setMode(button.dataset.mode);
    });
});

// ----------------------------------------------------------
// Contacto inteligente
// ----------------------------------------------------------

function showContactCta() {
    if (!contactCta) {
        return;
    }

    contactCta.classList.remove("hidden");
}

function hideContactCta() {
    if (!contactCta) {
        return;
    }

    contactCta.classList.add("hidden");
}

if (contactButton) {
    contactButton.addEventListener("click", function (event) {
        event.preventDefault();

        addBotMessageWithTyping(
            "Esta acción conectará con la sección de contacto cuando el chatbot se integre en la web corporativa de Kitcherry.",
            currentMode
        );
    });
}

// ----------------------------------------------------------
// Estado de interfaz
// ----------------------------------------------------------

function setWaitingState(isWaiting) {
    isWaitingResponse = isWaiting;

    userInput.disabled = isWaiting;

    const submitButton = chatForm.querySelector("button[type='submit']");

    if (submitButton) {
        submitButton.disabled = isWaiting;
    }

    if (clearChatBtn) {
        clearChatBtn.disabled = isWaiting;
    }

    modeButtons.forEach(function (button) {
        button.disabled = isWaiting;
    });
}

// ----------------------------------------------------------
// Enviar pregunta
// ----------------------------------------------------------

async function sendQuestion(question) {
    if (!question.trim() || isWaitingResponse) {
        return;
    }

    const requestMode = currentMode;

    addMessage(question, "user");
    userInput.value = "";

    setWaitingState(true);
    addLoadingMessage();

    const startTime = Date.now();

    try {
        const response = await fetch("api_chat.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json; charset=UTF-8"
            },
            body: JSON.stringify({
                mode: requestMode,
                question: question
            })
        });

        const text = await response.text();

        let data;

        try {
            data = JSON.parse(text);
        } catch (error) {
            await waitMinimumLoading(startTime, requestMode);
            removeLoadingMessage();

            await addBotMessageWithTyping(
                "La respuesta del servidor no se pudo interpretar correctamente. Revisa la API del chatbot.",
                requestMode
            );

            console.error("Respuesta no JSON:", text);
            return;
        }

        await waitMinimumLoading(startTime, requestMode);
        removeLoadingMessage();

        if (data.ok) {
            await addBotMessageWithTyping(data.answer, requestMode);

            if (data.contact_intent) {
                showContactCta();
            }
        } else {
            await addBotMessageWithTyping(
                data.answer || "Ahora mismo no he podido responder. Inténtalo de nuevo en unos segundos.",
                requestMode
            );
        }

    } catch (error) {
        await waitMinimumLoading(startTime, requestMode);
        removeLoadingMessage();

        await addBotMessageWithTyping(
            "Ahora mismo no he podido conectar con el motor de IA local. Inténtalo de nuevo en unos segundos.",
            requestMode
        );

        console.error(error);
    } finally {
        setWaitingState(false);
        userInput.focus();
    }
}

// ----------------------------------------------------------
// Eventos
// ----------------------------------------------------------

chatForm.addEventListener("submit", function (event) {
    event.preventDefault();

    const question = userInput.value.trim();
    sendQuestion(question);
});

if (clearChatBtn) {
    clearChatBtn.addEventListener("click", function () {
        if (isWaitingResponse) {
            return;
        }

        clearMessagesWithWelcome();
        userInput.focus();
    });
}

// ----------------------------------------------------------
// Inicio
// ----------------------------------------------------------

renderSuggestions();

if (chatDisclaimer) {
    chatDisclaimer.textContent = modeConfig[currentMode].disclaimer;
}

clearMessagesWithWelcome();