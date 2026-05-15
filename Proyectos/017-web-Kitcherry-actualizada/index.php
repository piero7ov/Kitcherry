<?php
// ==========================================================
// KITCHERRY - WEB CORPORATIVA
// Archivo: index.php
// ==========================================================

require_once __DIR__ . "/includes/helpers.php";
require_once __DIR__ . "/includes/data.php";

// ==========================================================
// CONFIGURACIÓN SEO DE LA PÁGINA
// ==========================================================

$tituloPagina = "Kitcherry | Herramientas de software para hostelería";
$descripcionPagina = "Kitcherry ofrece herramientas de software para hostelería que utilizan IA como recurso práctico para automatizar consultas, organizar reservas y mejorar la operativa diaria.";

// ==========================================================
// ENLACES DE CONTACTO Y REDES
// ==========================================================

$emailContacto = "pieroolivaresdev@gmail.com";
$githubUrl = "https://github.com/piero7ov";
$linkedinUrl = "https://www.linkedin.com/in/piero7ov/";

// ==========================================================
// PRODUCTOS DE INTERÉS PARA EL FORMULARIO
// ==========================================================

$productosInteres = [
    "sistema-comunicaciones" => "Sistema inteligente de consultas y comunicaciones",
    "kitcherry-chatbot" => "Chatbot inteligente para atención al cliente",
    "kitcherry-reservas" => "Kitcherry Reservas",
    "kitcherry-docs" => "Kitcherry Docs",
    "kitcherry-kitchen-assistant" => "Kitcherry Kitchen Assistant",
    "kitcherry-service-map" => "Kitcherry Service Map",
    "kitcherry-voice-tasks" => "Kitcherry Voice Tasks",
    "kitcherry-stock" => "Kitcherry Stock",
    "kitcherry-mail" => "Kitcherry Mail",
    "kitcherry-staff-training" => "Kitcherry Staff Training"
];

$productoInteresId = limpiar($_GET["producto"] ?? "");
$productoInteresNombre = $productosInteres[$productoInteresId] ?? "";

// ==========================================================
// PROCESAMIENTO DEL FORMULARIO
// ==========================================================

$mensajeFormulario = "";
$tipoMensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = limpiar($_POST["nombre"] ?? "");
    $email = limpiar($_POST["email"] ?? "");
    $negocio = limpiar($_POST["negocio"] ?? "");
    $mensaje = limpiar($_POST["mensaje"] ?? "");
    $aceptaPrivacidad = isset($_POST["acepta_privacidad"]);

    $productoInteresId = limpiar($_POST["producto_interes"] ?? "");
    $productoInteresNombre = $productosInteres[$productoInteresId] ?? "";

    // Campo trampa antispam: debe quedar vacío para usuarios reales.
    $campoWeb = trim($_POST["web"] ?? "");

    if ($campoWeb !== "") {
        $mensajeFormulario = "Gracias. Hemos recibido tu consulta.";
        $tipoMensaje = "ok";
    } elseif ($nombre === "" || $email === "" || $mensaje === "") {
        $mensajeFormulario = "Por favor, completa los campos obligatorios: nombre, correo y mensaje.";
        $tipoMensaje = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensajeFormulario = "Por favor, introduce un correo electrónico válido.";
        $tipoMensaje = "error";
    } elseif (!$aceptaPrivacidad) {
        $mensajeFormulario = "Debes leer y aceptar la política de privacidad para enviar la consulta.";
        $tipoMensaje = "error";
    } else {
        try {
            $mensajeCorreo = $mensaje;

            if ($productoInteresNombre !== "") {
                $mensajeCorreo = "Producto de interés: " . $productoInteresNombre . "\n\n" . $mensaje;
            }

            enviarCorreoSMTP($nombre, $email, $negocio, $mensajeCorreo);

            $mensajeFormulario = "Gracias, " . $nombre . ". Hemos recibido tu consulta y responderemos lo antes posible.";
            $tipoMensaje = "ok";
        } catch (Exception $e) {
            error_log("Error enviando correo SMTP desde Kitcherry: " . $e->getMessage());

            $mensajeFormulario = "No se pudo enviar la consulta en este momento. Revisa la configuración SMTP del servidor.";
            $tipoMensaje = "error";
        }
    }
}

// ==========================================================
// HERO DINÁMICO
// Si existe img/hero.png, se usa como fondo.
// Si no existe, se usa un degradado.
// ==========================================================

$heroStyle = $heroExiste
    ? '--hero-bg: url("' . e($heroPath) . '");'
    : '--hero-bg: linear-gradient(135deg, #161616, #991726);';

require_once __DIR__ . "/includes/header.php";
?>

    <main>

        <section class="hero" style='<?php echo $heroStyle; ?>'>
            <div class="contenedor hero-contenido js-reveal">

                <h1>
                    Software que simplifica la hostelería y mejora <span class="rojo">tu día a día.</span>
                </h1>

                <p>
                    Herramientas de software pensadas para pequeños negocios hosteleros que quieren ahorrar tiempo,
                    organizar mejor sus consultas y reservas, y modernizar su día a día sin complicarse.
                </p>

                <div class="hero-acciones">
                    <a href="productos.php" class="btn btn-principal">Ver productos</a>
                    <a href="#contacto" class="btn btn-secundario">Solicitar información</a>
                </div>

            </div>

            <div class="hero-datos js-reveal">
                <div class="dato">
                    <div class="dato-icono">▦</div>
                    <div>
                        <strong>10 líneas de servicio</strong>
                        <span>Soluciones modulares adaptables a cada negocio</span>
                    </div>
                </div>

                <div class="dato">
                    <div class="dato-icono">IA</div>
                    <div>
                        <strong>IA como recurso</strong>
                        <span>Aplicada a necesidades reales del día a día</span>
                    </div>
                </div>

                <div class="dato">
                    <div class="dato-icono">⌂</div>
                    <div>
                        <strong>Enfoque hostelero</strong>
                        <span>Pensado para bares, cafeterías y restaurantes</span>
                    </div>
                </div>

                <div class="dato">
                    <div class="dato-icono">↻</div>
                    <div>
                        <strong>Ahorra tiempo</strong>
                        <span>Menos tareas repetitivas y más orden interno</span>
                    </div>
                </div>
            </div>
        </section>

        <section id="necesidades" class="seccion fondo-suave despues-hero">
            <div class="contenedor">

                <div class="titulo-bloque js-reveal">
                    <h2>Necesidades reales del cliente</h2>
                    <p>
                        Kitcherry parte de problemas reales del sector hostelero: mensajes repetidos, reservas desordenadas,
                        dudas sobre carta o alérgenos y falta de tiempo en momentos de alta carga de trabajo.
                    </p>
                </div>

                <div class="grid-necesidades">
                    <?php foreach ($necesidades as $index => $necesidad): ?>
                        <article class="card js-reveal">
                            <div class="card-numero"><?php echo $index + 1; ?></div>
                            <h3><?php echo e($necesidad["titulo"]); ?></h3>
                            <p><?php echo e($necesidad["texto"]); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>

            </div>
        </section>

        <section id="producto-destacado" class="seccion">
            <div class="contenedor caso-grid">

                <div class="caso-principal js-reveal">
                    <span class="etiqueta-producto">Producto destacado</span>

                    <h2>Sistema inteligente de consultas y comunicaciones</h2>

                    <p>
                        Una herramienta pensada para negocios hosteleros que reciben muchas consultas por correo,
                        formulario web u otros canales y necesitan gestionarlas de forma más rápida y ordenada.
                    </p>

                    <ul>
                        <li>Clasifica mensajes según su intención principal.</li>
                        <li>Prioriza consultas importantes relacionadas con reservas, horarios, carta o alérgenos.</li>
                        <li>Prepara borradores de respuesta editables antes de enviarlos.</li>
                        <li>Permite mantener trazabilidad del estado de cada mensaje.</li>
                    </ul>

                    <div class="producto-destacado-acciones">
                        <a href="productos.php#sistema-comunicaciones" class="btn btn-principal">Ver producto</a>
                        <a href="index.php?producto=sistema-comunicaciones#contacto" class="btn btn-secundario">Lo quiero para mi negocio</a>
                    </div>
                </div>

                <div class="producto-demo js-reveal">

                    <div class="video-demo">
                        <?php if ($videoExiste): ?>
                            <video controls preload="metadata">
                                <source src="<?php echo e($videoPath); ?>" type="video/mp4">
                                Tu navegador no puede reproducir este vídeo.
                            </video>
                        <?php else: ?>
                            <div class="video-placeholder">
                                <div class="video-placeholder-contenido">
                                    <div class="video-icono">▶</div>
                                    <h3>Panel inteligente de correos para hostelería</h3>
                                    <p>
                                        Clasifica consultas, detecta solicitudes importantes y ayuda a responder con más orden
                                        sin perder el control humano.
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flujo">
                        <?php foreach ($flujoTrabajo as $paso): ?>
                            <article class="paso js-reveal">
                                <div class="paso-numero"><?php echo e($paso["paso"]); ?></div>
                                <div>
                                    <h3><?php echo e($paso["titulo"]); ?></h3>
                                    <p><?php echo e($paso["texto"]); ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                </div>

            </div>
        </section>

        <section id="servicios" class="seccion fondo-suave">
            <div class="contenedor">

                <div class="titulo-bloque js-reveal">
                    <h2>Soluciones de software para distintas áreas del negocio</h2>
                    <p>
                        Kitcherry reúne un catálogo de productos para reservas, comunicación, cocina, sala, stock,
                        tareas internas, formación y seguimiento digital. Aquí tienes una vista resumida del catálogo.
                    </p>
                </div>

                <div class="catalogo-resumen-grid">
                    <article class="catalogo-card js-reveal">
                        <span>01</span>
                        <h3>Atención y comunicación</h3>
                        <p>Consultas, chatbot y correo para responder mejor y ordenar la comunicación del negocio.</p>
                    </article>

                    <article class="catalogo-card js-reveal">
                        <span>02</span>
                        <h3>Reservas y sala</h3>
                        <p>Reservas, mesas, estados, ocupación y plano visual para preparar mejor cada servicio.</p>
                    </article>

                    <article class="catalogo-card js-reveal">
                        <span>03</span>
                        <h3>Cocina y operativa interna</h3>
                        <p>Apoyo a cocina, tareas internas por voz, stock y solicitudes de reposición.</p>
                    </article>

                    <article class="catalogo-card js-reveal">
                        <span>04</span>
                        <h3>Información y formación</h3>
                        <p>Documentación de carta, alérgenos, formación del personal y seguimiento del progreso.</p>
                    </article>
                </div>

                <div class="catalogo-cta js-reveal">
                    <div>
                        <h3>Explora todos los productos de Kitcherry</h3>
                        <p>
                            En la página de productos podrás ver cada herramienta con más detalle, acceder a su demo
                            y solicitar información del producto que te interese.
                        </p>
                    </div>

                    <a href="productos.php" class="btn btn-principal">Ver catálogo completo</a>
                </div>

            </div>
        </section>

        <section id="ia-practica" class="seccion">
            <div class="contenedor">

                <div class="bloque-ia js-reveal">
                    <div>
                        <h2>IA como recurso práctico</h2>
                        <p>
                            En Kitcherry, la inteligencia artificial no se vende como un concepto abstracto. Se utiliza como apoyo para ordenar información,
                            reducir carga repetitiva, responder mejor y ayudar a que el negocio funcione con más claridad.
                        </p>
                    </div>

                    <div class="ia-items">
                        <div class="ia-item">
                            <strong>Automatización comprensible</strong>
                            <span>La tecnología debe resolver problemas concretos y ser fácil de entender.</span>
                        </div>

                        <div class="ia-item">
                            <strong>Implantación progresiva</strong>
                            <span>Cada negocio puede empezar con una solución concreta y ampliar después si lo necesita.</span>
                        </div>

                        <div class="ia-item">
                            <strong>Apoyo a las personas</strong>
                            <span>La IA no sustituye el trato humano; ayuda a liberar tiempo y a organizar mejor el trabajo.</span>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <section id="contacto" class="seccion fondo-suave">
            <div class="contenedor contacto-grid">

                <div class="contacto-info js-reveal">
                    <h2>Cuéntanos qué necesita tu negocio</h2>
                    <p>
                        Si tienes un restaurante, bar, cafetería o negocio relacionado con hostelería, Kitcherry puede ayudarte a detectar
                        qué procesos podrían automatizarse o mejorarse.
                    </p>

                    <?php if ($productoInteresNombre !== ""): ?>
                        <div class="producto-interes-aviso">
                            <strong>Producto de interés:</strong>
                            <span><?php echo e($productoInteresNombre); ?></span>
                        </div>
                    <?php endif; ?>

                    <ul>
                        <li><strong>Email:</strong> <?php echo e($emailContacto); ?></li>
                        <li><strong>Sector:</strong> hostelería y restauración</li>
                        <li><strong>Zona inicial:</strong> Comunitat Valenciana</li>
                        <li><strong>Enfoque:</strong> software, automatización e inteligencia artificial aplicada</li>
                    </ul>

                    <div class="redes-contacto" aria-label="Canales de contacto">
                        <a href="mailto:<?php echo e($emailContacto); ?>" aria-label="Enviar email a Kitcherry">
                            <img src="img/redes/email.png" alt="Email">
                        </a>

                        <a href="<?php echo e($githubUrl); ?>" target="_blank" rel="noopener" aria-label="GitHub de Piero Olivares">
                            <img src="img/redes/github.png" alt="GitHub">
                        </a>

                        <a href="<?php echo e($linkedinUrl); ?>" target="_blank" rel="noopener" aria-label="LinkedIn de Kitcherry">
                            <img src="img/redes/linkedin.png" alt="LinkedIn">
                        </a>
                    </div>
                </div>

                <form class="formulario js-reveal" method="POST" action="#contacto" id="formulario-contacto">
                    <?php if ($mensajeFormulario !== ""): ?>
                        <div class="mensaje-formulario <?php echo e($tipoMensaje); ?>">
                            <?php echo e($mensajeFormulario); ?>
                        </div>
                    <?php endif; ?>

                    <div class="campo campo-oculto" aria-hidden="true">
                        <label for="web">Web</label>
                        <input type="text" id="web" name="web" tabindex="-1" autocomplete="off">
                    </div>

                    <input type="hidden" name="producto_interes" value="<?php echo e($productoInteresId); ?>">

                    <?php if ($productoInteresNombre !== ""): ?>
                        <div class="campo producto-interes-form">
                            <label>Producto seleccionado</label>
                            <div><?php echo e($productoInteresNombre); ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="campo">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" placeholder="Tu nombre" autocomplete="name" maxlength="80" required>
                    </div>

                    <div class="campo">
                        <label for="email">Correo electrónico *</label>
                        <input type="email" id="email" name="email" placeholder="tu@email.com" autocomplete="email" maxlength="120" required>
                    </div>

                    <div class="campo">
                        <label for="negocio">Nombre del negocio</label>
                        <input type="text" id="negocio" name="negocio" placeholder="Nombre de tu restaurante, bar o cafetería" autocomplete="organization" maxlength="120">
                    </div>

                    <div class="campo">
                        <label for="mensaje">Mensaje *</label>
                        <textarea id="mensaje" name="mensaje" placeholder="Explícanos qué te gustaría mejorar: consultas, reservas, alérgenos, organización interna..." maxlength="1500" required></textarea>
                    </div>

                    <div class="info-privacidad-form">
                        <p>
                            <strong>Protección de datos:</strong> Kitcherry utilizará los datos enviados únicamente para responder a tu consulta.
                            Puedes ejercer tus derechos según nuestra
                            <a href="privacidad.php" target="_blank" rel="noopener">política de privacidad</a>.
                        </p>
                    </div>

                    <div class="campo checkbox-legal">
                        <label for="acepta_privacidad">
                            <input type="checkbox" id="acepta_privacidad" name="acepta_privacidad" value="1" required>
                            <span>He leído y acepto la <a href="privacidad.php" target="_blank" rel="noopener">política de privacidad</a>.</span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-principal">Enviar consulta</button>
                </form>

            </div>
        </section>

    </main>

<?php
require_once __DIR__ . "/includes/footer.php";
?>