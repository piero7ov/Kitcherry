<?php
// ==========================================================
// KITCHERRY - CHATBOT WEB
// Archivo: index.php
// ==========================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry | Chatbot de atención</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- CSS principal -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="page-shell">

        <header class="site-header">
            <div class="brand">
                <img src="assets/img/logo.png" alt="Logo de Kitcherry" class="brand-logo">

                <div class="brand-text">
                    <h1>Kitcherry</h1>
                    <p>Herramientas para hostelería</p>
                </div>
            </div>
        </header>

        <main class="chat-layout">

            <section class="hero-panel">
                <p class="eyebrow">Chatbot corporativo</p>

                <h2>Conoce Kitcherry hablando con su asistente</h2>

                <p class="hero-description">
                    Pregunta qué es Kitcherry, qué servicios ofrece y cómo puede ayudar
                    a pequeños negocios de hostelería a trabajar mejor.
                </p>

                <div class="mini-benefits">
                    <div>
                        <span>01</span>
                        <p>Respuestas rápidas sobre la marca.</p>
                    </div>

                    <div>
                        <span>02</span>
                        <p>Información clara para visitantes.</p>
                    </div>

                    <div>
                        <span>03</span>
                        <p>Atención digital sin complicaciones.</p>
                    </div>
                </div>
            </section>

            <section class="chat-card" id="chatCard">
                <div class="chat-header">
                    <img src="assets/img/logo.png" alt="Kitcherry Bot" class="chat-logo">

                    <div>
                        <h3>Asistente Kitcherry</h3>
                        <p>Información clara sobre la marca</p>
                    </div>
                </div>

                <div id="chatMessages" class="chat-messages">
                    <div class="message bot">
                        <div class="bubble">
                            Hola, soy el asistente de Kitcherry. Estoy aquí para ayudarte a conocer mejor la propuesta, sus soluciones y su enfoque para negocios de hostelería.
                        </div>
                    </div>
                </div>

                <div class="suggestions">
                    <button type="button" data-question="¿Qué es Kitcherry?">¿Qué es Kitcherry?</button>
                    <button type="button" data-question="¿Qué servicios ofrece Kitcherry?">Servicios</button>
                    <button type="button" data-question="¿Kitcherry vende comida?">¿Vende comida?</button>
                </div>

                <form id="chatForm" class="chat-form">
                    <input
                        type="text"
                        id="userInput"
                        placeholder="Escribe tu pregunta..."
                        autocomplete="off"
                    >

                    <button type="submit">
                        Enviar
                    </button>
                </form>
            </section>

        </main>

    </div>

    <script src="assets/js/chatbot.js"></script>
</body>
</html>