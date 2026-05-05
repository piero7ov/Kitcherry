<?php
// ==========================================================
// KITCHERRY - CHATBOT INTELIGENTE
// Archivo: chatbot.php
// ==========================================================

require_once __DIR__ . "/includes/helpers.php";

$tituloPagina = "Kitcherry | Chatbot inteligente";
$descripcionPagina = "Chatbot inteligente de Kitcherry para atención al cliente en negocios de hostelería.";
$logoDemo = "img/logo-kitcherry.png";
$logoDemoExiste = file_exists(__DIR__ . "/" . $logoDemo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo e($tituloPagina); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo e($descripcionPagina); ?>">

    <link rel="stylesheet" href="assets/css/chatbot-demo.css?v=web-integracion-2">
</head>
<body>

    <div class="page-shell">

        <header class="site-header">
            <div class="brand">
                <?php if ($logoDemoExiste): ?>
                    <img src="<?php echo e($logoDemo); ?>" alt="Logo de Kitcherry" class="brand-logo">
                <?php endif; ?>

            <div class="brand-text">
                <strong>
                    <span class="kit">KIT</span><span class="cherry">CHERRY</span>
                </strong>
                <span class="brand-subtitle">Chatbot inteligente</span>
            </div>
            </div>

            <a href="index.php" class="demo-back-link">
                Volver a la web
            </a>
        </header>

        <main class="chat-layout">

            <section class="hero-panel">
                <p class="eyebrow">Asistente inteligente</p>

                <h2>Prueba cómo Kitcherry adapta un chatbot a distintos contextos</h2>

                <p class="hero-description">
                    Este asistente muestra dos formas de uso: un modo corporativo para conocer
                    Kitcherry y un modo restaurante para ver cómo el chatbot puede responder
                    con información concreta de un negocio hostelero.
                </p>

                <div class="mini-benefits">
                    <div>
                        <span>01</span>
                        <p>Modo Kitcherry para explicar la marca, sus soluciones y su propuesta.</p>
                    </div>

                    <div>
                        <span>02</span>
                        <p>Modo restaurante para simular atención al cliente con información de carta y servicios.</p>
                    </div>

                    <div>
                        <span>03</span>
                        <p>Un ejemplo de cómo un chatbot puede adaptarse a restaurantes, bares o cafeterías.</p>
                    </div>
                </div>
            </section>

            <section class="chat-card" id="chatCard">

                <div class="chat-header">
                    <?php if ($logoDemoExiste): ?>
                        <img src="<?php echo e($logoDemo); ?>" alt="Kitcherry Bot" class="chat-logo">
                    <?php endif; ?>

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
                    <a href="index.php#contacto" id="contactButton">Contactar con Kitcherry</a>
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
                    El chatbot puede cometer errores. La información mostrada es orientativa y forma parte de una prueba interactiva del sistema Kitcherry.
                </p>

            </section>

        </main>

    </div>

    <script src="assets/js/chatbot-demo.js?v=web-integracion-2"></script>
</body>
</html>