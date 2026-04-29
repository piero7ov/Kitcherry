<?php
// ==========================================================
// KITCHERRY - POLÍTICA DE PRIVACIDAD
// Archivo: privacidad.php
// ==========================================================

require_once __DIR__ . "/includes/helpers.php";
require_once __DIR__ . "/includes/data.php";

$tituloPagina = "Política de privacidad | Kitcherry";
$descripcionPagina = "Política de privacidad de la página web corporativa de Kitcherry.";

require_once __DIR__ . "/includes/header.php";
?>

<main>

    <section class="hero-pagina">
        <div class="contenedor hero-pagina-contenido js-reveal">
            <div class="breadcrumb">
                <a href="index.php">Inicio</a>
                <span>/</span>
                <span>Política de privacidad</span>
            </div>

            <h1>Política de privacidad</h1>

            <p>
                Información básica sobre cómo Kitcherry trataría los datos enviados a través del formulario de contacto.
            </p>

            <div class="aviso-simple">
                Este texto es una maqueta inicial. Antes de usar la web en producción, esta política debe adaptarse
                con los datos reales del responsable y el tratamiento exacto de la información.
            </div>
        </div>
    </section>

    <section class="pagina-interna fondo-suave">
        <div class="contenedor legal-layout">

            <aside class="legal-indice js-reveal">
                <h2>Contenido</h2>
                <nav>
                    <a href="#responsable">Responsable</a>
                    <a href="#datos">Datos recogidos</a>
                    <a href="#finalidad">Finalidad</a>
                    <a href="#base">Base del tratamiento</a>
                    <a href="#conservacion">Conservación</a>
                    <a href="#derechos">Derechos</a>
                    <a href="#seguridad">Seguridad</a>
                </nav>
            </aside>

            <div class="legal-contenido">

                <article id="responsable" class="legal-card js-reveal">
                    <h2>1. Responsable del tratamiento</h2>

                    <p>
                        El responsable del tratamiento de los datos será Kitcherry o la persona/entidad titular del proyecto
                        cuando la empresa se constituya formalmente.
                    </p>

                    <p>
                        En una versión definitiva, este apartado deberá incluir los datos reales del responsable:
                        nombre o razón social, identificación fiscal, dirección y correo electrónico de contacto.
                    </p>
                </article>

                <article id="datos" class="legal-card js-reveal">
                    <h2>2. Datos que se pueden recoger</h2>

                    <p>
                        A través del formulario de contacto, la web puede recoger los datos que el usuario introduzca
                        voluntariamente.
                    </p>

                    <ul>
                        <li>Nombre.</li>
                        <li>Correo electrónico.</li>
                        <li>Nombre del negocio, si se indica.</li>
                        <li>Mensaje enviado por el usuario.</li>
                    </ul>
                </article>

                <article id="finalidad" class="legal-card js-reveal">
                    <h2>3. Finalidad del tratamiento</h2>

                    <p>
                        Los datos enviados mediante el formulario se utilizarán únicamente para responder consultas,
                        atender solicitudes de información y mantener una comunicación relacionada con los servicios
                        o soluciones de Kitcherry.
                    </p>

                    <p>
                        No se utilizarán estos datos para finalidades diferentes sin informar previamente al usuario.
                    </p>
                </article>

                <article id="base" class="legal-card js-reveal">
                    <h2>4. Base del tratamiento</h2>

                    <p>
                        La base del tratamiento será el consentimiento del usuario al enviar voluntariamente el formulario
                        de contacto y facilitar sus datos para recibir una respuesta.
                    </p>
                </article>

                <article id="conservacion" class="legal-card js-reveal">
                    <h2>5. Conservación de los datos</h2>

                    <p>
                        Los datos se conservarán durante el tiempo necesario para atender la consulta recibida y, en su caso,
                        mantener una relación de comunicación relacionada con la solicitud realizada.
                    </p>

                    <p>
                        Cuando los datos ya no sean necesarios, deberán eliminarse o bloquearse según corresponda.
                    </p>
                </article>

                <article id="derechos" class="legal-card js-reveal">
                    <h2>6. Derechos del usuario</h2>

                    <p>
                        El usuario podrá solicitar el acceso, rectificación, eliminación u oposición al tratamiento de sus datos,
                        así como otros derechos que puedan corresponderle según la normativa aplicable.
                    </p>

                    <p>
                        Para ejercer estos derechos, podrá contactar a través del correo indicado por Kitcherry.
                    </p>
                </article>

                <article id="seguridad" class="legal-card js-reveal">
                    <h2>7. Seguridad de la información</h2>

                    <p>
                        Kitcherry deberá aplicar medidas razonables para proteger la información enviada por los usuarios,
                        evitando accesos no autorizados, pérdida o uso indebido de los datos.
                    </p>

                    <p>
                        En una versión de producción, será importante utilizar HTTPS, proteger las credenciales SMTP
                        y evitar guardar datos sensibles directamente en el código.
                    </p>
                </article>

            </div>

        </div>
    </section>

</main>

<?php
require_once __DIR__ . "/includes/footer.php";
?>