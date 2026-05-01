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
// PROCESAMIENTO DEL FORMULARIO
// ==========================================================

$mensajeFormulario = "";
$tipoMensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = limpiar($_POST["nombre"] ?? "");
    $email = limpiar($_POST["email"] ?? "");
    $negocio = limpiar($_POST["negocio"] ?? "");
    $mensaje = limpiar($_POST["mensaje"] ?? "");

    if ($nombre === "" || $email === "" || $mensaje === "") {
        $mensajeFormulario = "Por favor, completa los campos obligatorios: nombre, correo y mensaje.";
        $tipoMensaje = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensajeFormulario = "Por favor, introduce un correo electrónico válido.";
        $tipoMensaje = "error";
    } else {
        try {
            enviarCorreoSMTP($nombre, $email, $negocio, $mensaje);

            $mensajeFormulario = "Gracias, " . $nombre . ". Hemos recibido tu consulta y la enviaremos al equipo de Kitcherry.";
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
                    Herramientas de software pensadas para pequeños negocios hosteleros que quieren ahorrar tiempo, organizar mejor sus consultas y reservas, y modernizar su día a día sin complicarse.
                </p>

                <div class="hero-acciones">
                    <a href="#servicios" class="btn btn-principal">Ver soluciones</a>
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
                    Antes de plantear un producto concreto, es importante identificar las necesidades del mercado para que el proyecto responda
                    a problemas reales y no solo a una idea teórica. En el caso de Kitcherry, la atención se centra en la hostelería,
                    especialmente en pequeños negocios que necesitan apoyo para organizar mejor ciertos procesos, ahorrar tiempo y mejorar su operativa diaria.
                </p>
                <br>
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

        <section id="servicios" class="seccion">
            <div class="contenedor">

                <div class="titulo-bloque js-reveal">
                    <h2>Soluciones de software para hostelería</h2>
                    <p>
                        Kitcherry se organiza en soluciones concretas que pueden funcionar de manera independiente o integrarse
                        dentro de un ecosistema modular, según las necesidades reales del negocio.
                    </p>
                </div>

                <div class="grid-servicios">
                    <?php foreach ($servicios as $servicio): ?>
                        <article class="servicio js-reveal">
                            <span class="servicio-numero"><?php echo e($servicio["numero"]); ?></span>
                            <h3><?php echo e($servicio["titulo"]); ?></h3>
                            <p><?php echo e($servicio["texto"]); ?></p>

                            <?php if (!empty($servicio["url"]) && !empty($servicio["boton"])): ?>
                                <a href="<?php echo e($servicio["url"]); ?>" class="btn btn-principal" style="margin-top: 18px;">
                                    <?php echo e($servicio["boton"]); ?>
                                </a>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>

            </div>
        </section>

        <section id="producto-destacado" class="seccion fondo-suave">
            <div class="contenedor caso-grid">

                <div class="caso-principal js-reveal">
                    <h2>Sistema inteligente de consultas y comunicaciones</h2>

                    <p>
                        Nuestro primer producto destacado está pensado para negocios hosteleros que reciben muchas preguntas repetidas
                        y necesitan responder mejor sin aumentar la carga del equipo.
                    </p>

                    <p>
                        La solución ayuda a ordenar consultas, evitar mensajes perdidos, mejorar la rapidez de atención y ofrecer una comunicación
                        más clara al cliente desde el primer contacto.
                    </p>

                    <ul>
                        <li>Menos tiempo respondiendo siempre las mismas preguntas.</li>
                        <li>Mayor control sobre consultas de reservas, horarios, carta, ubicación o servicios.</li>
                        <li>Mejor atención al cliente en momentos de alta carga de trabajo.</li>
                        <li>Imagen más profesional y organizada para el negocio.</li>
                    </ul>
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
                                        Herramienta diseñada para ayudar a restaurantes y negocios hosteleros a gestionar sus consultas
                                        de forma más ágil, ordenada y profesional.
                                    </p>
                                    <p>
                                        El sistema clasifica automáticamente los correos, detecta el tipo de solicitud, prioriza mensajes
                                        según su urgencia operativa, extrae datos útiles, genera borradores de respuesta editables y permite
                                        mantener trazabilidad completa del trabajo realizado.
                                    </p>
                                    <p>
                                        La idea no es quitar el control humano, sino ahorrar tiempo, reducir olvidos y mejorar la atención
                                        diaria al cliente.
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

                    <ul>
                        <li><strong>Email:</strong> contacto@kitcherry.com</li>
                        <li><strong>Sector:</strong> hostelería y restauración</li>
                        <li><strong>Zona inicial:</strong> Comunitat Valenciana</li>
                        <li><strong>Enfoque:</strong> software, automatización e inteligencia artificial aplicada</li>
                    </ul>
                </div>

                <form class="formulario js-reveal" method="POST" action="#contacto" id="formulario-contacto">
                    <?php if ($mensajeFormulario !== ""): ?>
                        <div class="mensaje-formulario <?php echo e($tipoMensaje); ?>">
                            <?php echo e($mensajeFormulario); ?>
                        </div>
                    <?php endif; ?>

                    <div class="campo">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" placeholder="Tu nombre">
                    </div>

                    <div class="campo">
                        <label for="email">Correo electrónico *</label>
                        <input type="email" id="email" name="email" placeholder="tu@email.com">
                    </div>

                    <div class="campo">
                        <label for="negocio">Nombre del negocio</label>
                        <input type="text" id="negocio" name="negocio" placeholder="Nombre de tu restaurante, bar o cafetería">
                    </div>

                    <div class="campo">
                        <label for="mensaje">Mensaje *</label>
                        <textarea id="mensaje" name="mensaje" placeholder="Explícanos qué te gustaría mejorar: consultas, reservas, alérgenos, organización interna..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-principal">Enviar consulta</button>
                </form>

            </div>
        </section>

    </main>

<?php
require_once __DIR__ . "/includes/footer.php";
?>