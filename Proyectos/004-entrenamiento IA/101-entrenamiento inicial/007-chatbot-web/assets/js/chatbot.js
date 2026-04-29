// ==========================================================
// KITCHERRY - CHATBOT JS
// Archivo: chatbot.js
// ==========================================================

const chatForm = document.getElementById("chatForm");
const userInput = document.getElementById("userInput");
const chatMessages = document.getElementById("chatMessages");
const suggestionButtons = document.querySelectorAll(".suggestions button");

function addMessage(text, type) {
    const message = document.createElement("div");
    message.classList.add("message", type);

    const bubble = document.createElement("div");
    bubble.classList.add("bubble");
    bubble.textContent = text;

    message.appendChild(bubble);
    chatMessages.appendChild(message);

    chatMessages.scrollTop = chatMessages.scrollHeight;
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

    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function removeLoadingMessage() {
    const loadingMessage = document.getElementById("loadingMessage");

    if (loadingMessage) {
        loadingMessage.remove();
    }
}

async function sendQuestion(question) {
    if (!question.trim()) {
        return;
    }

    addMessage(question, "user");
    userInput.value = "";
    userInput.disabled = true;

    addLoadingMessage();

    try {
        const response = await fetch("api_chat.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                question: question
            })
        });

        const text = await response.text();

        let data;

        try {
            data = JSON.parse(text);
        } catch (error) {
            removeLoadingMessage();
            console.error("Respuesta no JSON:", text);
            addMessage("La API no devolvió JSON válido. Respuesta recibida: " + text, "bot");
            userInput.disabled = false;
            userInput.focus();
            return;
        }

        removeLoadingMessage();

        if (data.ok) {
            addMessage(data.answer, "bot");
        } else {
            addMessage(data.answer || "Ha ocurrido un error.", "bot");
        }

    } catch (error) {
        removeLoadingMessage();
        console.error(error);
        addMessage("Error técnico: " + error.message, "bot");
    }

    userInput.disabled = false;
    userInput.focus();
}

chatForm.addEventListener("submit", function (event) {
    event.preventDefault();

    const question = userInput.value.trim();
    sendQuestion(question);
});

suggestionButtons.forEach(function (button) {
    button.addEventListener("click", function () {
        const question = button.dataset.question;
        sendQuestion(question);
    });
});