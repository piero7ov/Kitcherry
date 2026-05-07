<?php
// ==========================================================
// KITCHERRY KITCHEN ASSISTANT
// Archivo: 019-asistente-flask.php
// Interfaz web con avatar 3D conectada a API Flask
// ==========================================================
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
                Asistente Flask activo
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
                            usando la API Flask, el manual interno y el modelo entrenado de Kitcherry.
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

    <script src="assets/js/asistente-flask.js"></script>

</body>
</html>