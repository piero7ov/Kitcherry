<?php
// ==========================================================
// KITCHERRY - POLÍTICA DE COOKIES
// Archivo: cookies.php
// ==========================================================

require_once __DIR__ . "/includes/helpers.php";
require_once __DIR__ . "/includes/data.php";

$tituloPagina = "Política de cookies | Kitcherry";
$descripcionPagina = "Política de cookies de la página web corporativa de Kitcherry.";

require_once __DIR__ . "/includes/header.php";
?>

<main>

    <section class="hero-pagina">
        <div class="contenedor hero-pagina-contenido js-reveal">
            <div class="breadcrumb">
                <a href="index.php">Inicio</a>
                <span>/</span>
                <span>Política de cookies</span>
            </div>

            <h1>Política de cookies</h1>

            <p>
                Información sobre el uso de cookies y tecnologías similares en la página web de Kitcherry.
            </p>

            <div class="aviso-simple">
                Actualmente esta web no utiliza cookies propias ni de terceros con finalidad analítica,
                publicitaria o de seguimiento. Por ese motivo no se muestra banner de cookies.
            </div>
        </div>
    </section>

    <section class="pagina-interna fondo-suave">
        <div class="contenedor legal-layout">

            <aside class="legal-indice js-reveal">
                <h2>Contenido</h2>
                <nav>
                    <a href="#que-son">Qué son las cookies</a>
                    <a href="#uso">Uso actual</a>
                    <a href="#cookies-utilizadas">Cookies utilizadas</a>
                    <a href="#banner">Banner de cookies</a>
                    <a href="#futuro">Cambios futuros</a>
                    <a href="#gestion">Gestión del navegador</a>
                </nav>
            </aside>

            <div class="legal-contenido">

                <article id="que-son" class="legal-card js-reveal">
                    <h2>1. Qué son las cookies</h2>

                    <p>
                        Las cookies son pequeños archivos que una página web puede almacenar en el navegador o dispositivo
                        del usuario para recordar información técnica, preferencias, datos de navegación o información
                        relacionada con el uso del sitio.
                    </p>

                    <p>
                        También existen tecnologías similares, como el almacenamiento local del navegador o identificadores
                        técnicos, que pueden utilizarse para finalidades parecidas.
                    </p>
                </article>

                <article id="uso" class="legal-card js-reveal">
                    <h2>2. Uso actual de cookies en Kitcherry</h2>

                    <p>
                        En esta versión, la web corporativa de Kitcherry no instala cookies propias ni de terceros con
                        finalidad analítica, publicitaria, de personalización o seguimiento.
                    </p>

                    <p>
                        La página funciona como una web informativa con formulario de contacto y contenidos corporativos.
                        Los archivos JavaScript utilizados sirven para efectos visuales e interacción básica de la interfaz,
                        sin guardar preferencias ni rastrear la navegación del usuario.
                    </p>
                </article>

                <article id="cookies-utilizadas" class="legal-card js-reveal">
                    <h2>3. Cookies utilizadas</h2>

                    <p>
                        Actualmente no se utilizan cookies no necesarias.
                    </p>

                    <ul>
                        <li><strong>Cookies técnicas necesarias:</strong> no se utilizan de forma específica en esta versión.</li>
                        <li><strong>Cookies analíticas:</strong> no se utilizan.</li>
                        <li><strong>Cookies publicitarias:</strong> no se utilizan.</li>
                        <li><strong>Cookies de terceros:</strong> no se utilizan.</li>
                    </ul>
                </article>

                <article id="banner" class="legal-card js-reveal">
                    <h2>4. Banner de cookies</h2>

                    <p>
                        Al no utilizar cookies no necesarias, analíticas, publicitarias o de terceros, esta versión de la
                        web no necesita mostrar un banner de aceptación, rechazo o configuración de cookies.
                    </p>

                    <p>
                        Si en el futuro se añaden cookies no necesarias, el usuario deberá poder aceptarlas, rechazarlas
                        o configurarlas antes de que se instalen.
                    </p>
                </article>

                <article id="futuro" class="legal-card js-reveal">
                    <h2>5. Cambios futuros</h2>

                    <p>
                        Si el proyecto evoluciona y se incorporan herramientas como Google Analytics, píxeles publicitarios,
                        mapas externos, vídeos embebidos de terceros, sistemas de medición, herramientas de marketing
                        o integraciones que instalen cookies, esta política deberá actualizarse.
                    </p>

                    <p>
                        En ese caso, la web deberá informar de qué cookies se utilizan, quién las gestiona, durante cuánto
                        tiempo permanecen activas y con qué finalidad, además de incorporar un sistema de consentimiento
                        adecuado.
                    </p>
                </article>

                <article id="gestion" class="legal-card js-reveal">
                    <h2>6. Gestión desde el navegador</h2>

                    <p>
                        Aunque esta web no instala cookies no necesarias, el usuario puede configurar su navegador para
                        permitir, bloquear o eliminar cookies de cualquier página web.
                    </p>

                    <p>
                        Esta configuración depende del navegador utilizado y puede modificarse desde las opciones de privacidad
                        o seguridad del propio navegador.
                    </p>
                </article>

            </div>

        </div>
    </section>

</main>

<?php
require_once __DIR__ . "/includes/footer.php";
?>