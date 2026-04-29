<?php
// ==========================================================
// KITCHERRY - CHATBOT WEB MEJORADO
// Archivo: index.php
// ==========================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry | Chatbot inteligente</title>
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
                <p class="eyebrow">Asistente inteligente</p>

                <h2>Dos modos para demostrar la adaptación de Kitcherry</h2>

                <p class="hero-description">
                    Prueba el modo corporativo para conocer Kitcherry o cambia al modo restaurante
                    para ver cómo un asistente puede adaptarse a un negocio hostelero concreto.
                </p>

                <div class="mini-benefits">
                    <div>
                        <span>01</span>
                        <p>Modo Kitcherry para explicar la marca y sus soluciones.</p>
                    </div>

                    <div>
                        <span>02</span>
                        <p>Modo restaurante para simular atención al cliente con datos de Kamado.</p>
                    </div>

                    <div>
                        <span>03</span>
                        <p>Una base para futuros asistentes adaptados a otros negocios hosteleros.</p>
                    </div>
                </div>
            </section>

            <section class="chat-card" id="chatCard">

                <div class="chat-header">
                    <img src="assets/img/logo.png" alt="Kitcherry Bot" class="chat-logo">

                    <div class="chat-header-content">
                        <div>
                            <h3 id="chatTitle">Asistente Kitcherry</h3>
                            <p id="chatSubtitle">Modo corporativo</p>
                        </div>

                        <button type="button" id="clearChatBtn" class="clear-chat-btn">
                            Nueva conversación
                        </button>
                    </div>
                </div>

                <!-- Selector de modo -->
                <div class="mode-selector" id="modeSelector">
                    <button type="button" class="mode-option active" data-mode="kitcherry">
                        Kitcherry
                    </button>

                    <button type="button" class="mode-option" data-mode="restaurante">
                        Restaurante
                    </button>
                </div>

                <div id="modeHint" class="mode-hint">
                    Estás usando el modo corporativo. Puedes preguntar por Kitcherry, sus productos y su enfoque para hostelería.
                </div>

                <div id="chatMessages" class="chat-messages">
                    <div class="message bot">
                        <div class="bubble">
                            Hola, soy el asistente de Kitcherry. Estoy aquí para ayudarte a conocer mejor la propuesta, sus soluciones y su enfoque para negocios de hostelería.
                        </div>
                    </div>
                </div>

                <div class="suggestions" id="suggestions">
                    <button type="button" data-question="¿Qué es Kitcherry?">¿Qué es Kitcherry?</button>
                    <button type="button" data-question="¿Qué productos ofrece actualmente Kitcherry?">Productos</button>
                    <button type="button" data-question="¿Cuál es el producto estrella de Kitcherry?">Producto estrella</button>
                </div>

                <div id="contactCta" class="contact-cta hidden">
                    <p>¿Quieres valorar esta solución para tu negocio?</p>
                    <a href="#" id="contactButton">Contactar con Kitcherry</a>
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

                <p id="chatDisclaimer" class="chat-disclaimer">
                    El chatbot puede cometer errores. La información mostrada es orientativa y forma parte de una demostración del sistema Kitcherry.
                </p>

            </section>

        </main>

    </div>

    <script src="assets/js/chatbot.js"></script>
</body>
</html>