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
                Información sobre el uso de cookies en la página web corporativa de Kitcherry.
            </p>

            <div class="aviso-simple">
                Actualmente esta maqueta no incluye un sistema real de cookies ni analítica. Si más adelante se añade
                Google Analytics, píxeles o herramientas similares, esta página deberá actualizarse.
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
                    <a href="#tipos">Tipos de cookies</a>
                    <a href="#gestion">Gestión</a>
                    <a href="#actualizacion">Actualización</a>
                </nav>
            </aside>

            <div class="legal-contenido">

                <article id="que-son" class="legal-card js-reveal">
                    <h2>1. Qué son las cookies</h2>

                    <p>
                        Las cookies son pequeños archivos que una página web puede almacenar en el navegador del usuario
                        para recordar información técnica, preferencias o datos relacionados con la navegación.
                    </p>

                    <p>
                        Su uso puede servir para mejorar la experiencia, mantener sesiones, recordar ajustes o analizar
                        el uso de una página web.
                    </p>
                </article>

                <article id="uso" class="legal-card js-reveal">
                    <h2>2. Uso actual de cookies en Kitcherry</h2>

                    <p>
                        En esta versión inicial, la web corporativa de Kitcherry no utiliza cookies propias con finalidad
                        publicitaria, analítica o de seguimiento avanzado.
                    </p>

                    <p>
                        La página funciona como una web informativa básica con formulario de contacto y contenidos
                        corporativos.
                    </p>
                </article>

                <article id="tipos" class="legal-card js-reveal">
                    <h2>3. Tipos de cookies que podrían utilizarse en el futuro</h2>

                    <p>
                        Si el proyecto evoluciona, la web podría incorporar distintos tipos de cookies según las
                        funcionalidades añadidas.
                    </p>

                    <ul>
                        <li><strong>Cookies técnicas:</strong> necesarias para el funcionamiento básico de la web.</li>
                        <li><strong>Cookies de preferencias:</strong> usadas para recordar ajustes del usuario.</li>
                        <li><strong>Cookies de análisis:</strong> utilizadas para conocer estadísticas de navegación.</li>
                        <li><strong>Cookies de terceros:</strong> procedentes de servicios externos integrados en la web.</li>
                    </ul>
                </article>

                <article id="gestion" class="legal-card js-reveal">
                    <h2>4. Gestión de cookies</h2>

                    <p>
                        El usuario puede configurar su navegador para permitir, bloquear o eliminar cookies. Esta gestión
                        depende del navegador utilizado.
                    </p>

                    <p>
                        Si en el futuro Kitcherry incorpora cookies no necesarias, deberá añadirse un sistema de aviso
                        o configuración para que el usuario pueda aceptar o rechazar su uso según corresponda.
                    </p>
                </article>

                <article id="actualizacion" class="legal-card js-reveal">
                    <h2>5. Actualización de esta política</h2>

                    <p>
                        Esta política podrá modificarse si la web incorpora nuevas herramientas, servicios externos,
                        analítica, publicidad o cualquier sistema que utilice cookies.
                    </p>

                    <p>
                        La versión definitiva deberá reflejar con claridad qué cookies se utilizan, quién las gestiona,
                        durante cuánto tiempo permanecen activas y con qué finalidad.
                    </p>
                </article>

            </div>

        </div>
    </section>

</main>

<?php
require_once __DIR__ . "/includes/footer.php";
?>