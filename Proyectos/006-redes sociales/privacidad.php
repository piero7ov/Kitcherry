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
                Información sobre cómo Kitcherry recoge, utiliza y protege los datos personales enviados a través de esta web.
            </p>

            <div class="aviso-simple">
                Kitcherry tratará los datos enviados a través del formulario únicamente para responder consultas
                relacionadas con sus soluciones y servicios.
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
                    <a href="#base">Base legal</a>
                    <a href="#destinatarios">Destinatarios</a>
                    <a href="#conservacion">Conservación</a>
                    <a href="#derechos">Derechos</a>
                    <a href="#comerciales">Comunicaciones comerciales</a>
                    <a href="#decisiones">Decisiones automatizadas</a>
                    <a href="#seguridad">Seguridad</a>
                    <a href="#reclamaciones">Reclamaciones</a>
                </nav>
            </aside>

            <div class="legal-contenido">

                <article id="responsable" class="legal-card js-reveal">
                    <h2>1. Responsable del tratamiento</h2>

                    <p>
                        El responsable del tratamiento de los datos recogidos a través de esta web es <strong>Kitcherry</strong>.
                    </p>

                    <ul>
                        <li><strong>Nombre comercial:</strong> Kitcherry.</li>
                        <li><strong>Titular o razón social:</strong> a completar.</li>
                        <li><strong>NIF/CIF:</strong> a completar.</li>
                        <li><strong>Domicilio:</strong> a completar.</li>
                        <li><strong>Correo electrónico de contacto:</strong> contacto@kitcherry.com.</li>
                    </ul>
                </article>

                <article id="datos" class="legal-card js-reveal">
                    <h2>2. Datos personales que se pueden recoger</h2>

                    <p>
                        A través del formulario de contacto, Kitcherry puede recoger los datos que el usuario introduzca
                        voluntariamente para solicitar información.
                    </p>

                    <ul>
                        <li>Nombre.</li>
                        <li>Correo electrónico.</li>
                        <li>Nombre del negocio, si el usuario lo indica.</li>
                        <li>Mensaje o consulta enviada por el usuario.</li>
                        <li>Datos técnicos mínimos necesarios para el funcionamiento y seguridad de la web, cuando proceda.</li>
                    </ul>

                    <p>
                        El usuario debe evitar introducir datos especialmente sensibles o información innecesaria dentro del formulario.
                    </p>
                </article>

                <article id="finalidad" class="legal-card js-reveal">
                    <h2>3. Finalidad del tratamiento</h2>

                    <p>
                        Los datos enviados mediante el formulario se utilizarán para las siguientes finalidades:
                    </p>

                    <ul>
                        <li>Responder a consultas recibidas desde la web.</li>
                        <li>Atender solicitudes de información sobre Kitcherry y sus soluciones.</li>
                        <li>Mantener la comunicación iniciada por el usuario.</li>
                        <li>Gestionar posibles propuestas, demostraciones o contactos comerciales solicitados por el propio interesado.</li>
                        <li>Garantizar la seguridad básica del formulario y evitar usos abusivos o fraudulentos.</li>
                    </ul>

                    <p>
                        Los datos no se utilizarán para fines distintos de los indicados sin informar previamente al usuario
                        y, cuando sea necesario, solicitar su consentimiento.
                    </p>
                </article>

                <article id="base" class="legal-card js-reveal">
                    <h2>4. Base legal del tratamiento</h2>

                    <p>
                        La base legal para tratar los datos enviados mediante el formulario es el consentimiento del usuario,
                        manifestado al completar el formulario, aceptar la política de privacidad y enviar voluntariamente su consulta.
                    </p>

                    <p>
                        También puede existir una base precontractual cuando el usuario solicita información sobre servicios,
                        soluciones, presupuestos o posibles colaboraciones relacionadas con Kitcherry.
                    </p>
                </article>

                <article id="destinatarios" class="legal-card js-reveal">
                    <h2>5. Destinatarios y comunicaciones de datos</h2>

                    <p>
                        Kitcherry no venderá ni cederá los datos personales del usuario a terceros con fines comerciales.
                    </p>

                    <p>
                        Podrán tener acceso a los datos determinados proveedores técnicos necesarios para el funcionamiento
                        de la web, como servicios de alojamiento, correo electrónico, mantenimiento técnico o seguridad.
                        Estos proveedores actuarán únicamente cuando sea necesario para prestar el servicio.
                    </p>

                    <p>
                        No está prevista la transferencia internacional de datos. Si en el futuro se utilizan proveedores
                        ubicados fuera del Espacio Económico Europeo, esta política deberá actualizarse y se aplicarán las garantías legales correspondientes.
                    </p>
                </article>

                <article id="conservacion" class="legal-card js-reveal">
                    <h2>6. Plazo de conservación</h2>

                    <p>
                        Los datos enviados mediante el formulario se conservarán durante el tiempo necesario para responder
                        a la consulta y gestionar la comunicación iniciada por el usuario.
                    </p>

                    <p>
                        Como criterio general, las consultas podrán conservarse durante un máximo de 12 meses, salvo que exista
                        una relación posterior, una solicitud pendiente, una obligación legal o una necesidad legítima de conservar
                        la información durante más tiempo.
                    </p>

                    <p>
                        Cuando los datos ya no sean necesarios, serán eliminados o bloqueados conforme a la normativa aplicable.
                    </p>
                </article>

                <article id="derechos" class="legal-card js-reveal">
                    <h2>7. Derechos del usuario</h2>

                    <p>
                        El usuario puede ejercer, cuando proceda, los siguientes derechos en relación con sus datos personales:
                    </p>

                    <ul>
                        <li><strong>Acceso:</strong> saber qué datos personales se están tratando.</li>
                        <li><strong>Rectificación:</strong> solicitar la corrección de datos inexactos.</li>
                        <li><strong>Supresión:</strong> solicitar la eliminación de sus datos cuando ya no sean necesarios.</li>
                        <li><strong>Oposición:</strong> oponerse a determinados tratamientos.</li>
                        <li><strong>Limitación:</strong> solicitar que se limite el tratamiento de sus datos en determinados casos.</li>
                        <li><strong>Portabilidad:</strong> recibir sus datos en un formato estructurado cuando sea aplicable.</li>
                    </ul>

                    <p>
                        Para ejercer estos derechos, el usuario puede enviar una solicitud al correo:
                        <strong>contacto@kitcherry.com</strong>.
                    </p>
                </article>

                <article id="comerciales" class="legal-card js-reveal">
                    <h2>8. Comunicaciones comerciales</h2>

                    <p>
                        Kitcherry no enviará comunicaciones comerciales, newsletters o promociones por correo electrónico
                        sin consentimiento previo del usuario o sin una base legal que lo permita.
                    </p>

                    <p>
                        Si en el futuro se habilita una suscripción a comunicaciones comerciales, se informará claramente
                        al usuario y se ofrecerá un mecanismo sencillo para darse de baja.
                    </p>
                </article>

                <article id="decisiones" class="legal-card js-reveal">
                    <h2>9. Decisiones automatizadas</h2>

                    <p>
                        Esta web informativa no toma decisiones automatizadas con efectos jurídicos sobre los usuarios
                        ni realiza perfiles comerciales a partir del formulario de contacto.
                    </p>

                    <p>
                        Las soluciones de inteligencia artificial descritas en la web se presentan como herramientas de apoyo
                        para negocios hosteleros, no como sistemas destinados a tomar decisiones relevantes sobre personas
                        sin intervención humana.
                    </p>
                </article>

                <article id="seguridad" class="legal-card js-reveal">
                    <h2>10. Seguridad de la información</h2>

                    <p>
                        Kitcherry aplicará medidas técnicas y organizativas razonables para proteger los datos personales
                        frente a pérdida, uso indebido, acceso no autorizado, alteración o divulgación no permitida.
                    </p>

                    <p>
                        Entre estas medidas pueden incluirse el uso de HTTPS, protección de credenciales, control de accesos,
                        limitación de permisos, mantenimiento técnico y configuración segura del servidor.
                    </p>
                </article>

                <article id="reclamaciones" class="legal-card js-reveal">
                    <h2>11. Reclamaciones</h2>

                    <p>
                        Si el usuario considera que sus datos personales no han sido tratados correctamente, puede contactar
                        primero con Kitcherry a través del correo indicado.
                    </p>

                    <p>
                        También puede presentar una reclamación ante la Agencia Española de Protección de Datos si considera
                        que el tratamiento no se ajusta a la normativa aplicable.
                    </p>
                </article>

            </div>

        </div>
    </section>

</main>

<?php
require_once __DIR__ . "/includes/footer.php";
?>