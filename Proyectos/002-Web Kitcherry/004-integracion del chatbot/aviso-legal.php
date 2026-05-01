<?php
// ==========================================================
// KITCHERRY - AVISO LEGAL
// Archivo: aviso-legal.php
// ==========================================================

require_once __DIR__ . "/includes/helpers.php";
require_once __DIR__ . "/includes/data.php";

$tituloPagina = "Aviso legal | Kitcherry";
$descripcionPagina = "Aviso legal de la página web corporativa de Kitcherry.";

require_once __DIR__ . "/includes/header.php";
?>

<main>

    <section class="hero-pagina">
        <div class="contenedor hero-pagina-contenido js-reveal">
            <div class="breadcrumb">
                <a href="index.php">Inicio</a>
                <span>/</span>
                <span>Aviso legal</span>
            </div>

            <h1>Aviso legal</h1>

            <p>
                Información general sobre el uso de la página web corporativa de Kitcherry.
            </p>

            <div class="aviso-simple">
                Este texto es una maqueta inicial para el proyecto. Más adelante debería revisarse y completarse
                con datos reales antes de publicar una versión definitiva.
            </div>
        </div>
    </section>

    <section class="pagina-interna fondo-suave">
        <div class="contenedor legal-layout">

            <aside class="legal-indice js-reveal">
                <h2>Contenido</h2>
                <nav>
                    <a href="#titularidad">Titularidad</a>
                    <a href="#objeto">Objeto de la web</a>
                    <a href="#uso">Uso de la página</a>
                    <a href="#propiedad">Propiedad intelectual</a>
                    <a href="#responsabilidad">Responsabilidad</a>
                    <a href="#contacto-legal">Contacto</a>
                </nav>
            </aside>

            <div class="legal-contenido">

                <article id="titularidad" class="legal-card js-reveal">
                    <h2>1. Titularidad de la web</h2>

                    <p>
                        Esta página web pertenece al proyecto Kitcherry, una propuesta de marca orientada al desarrollo
                        de herramientas de software para hostelería.
                    </p>

                    <p>
                        En una versión real de la empresa, este apartado debería incluir los datos identificativos del
                        titular, como nombre o razón social, NIF/CIF, domicilio, correo electrónico de contacto y demás
                        información legal correspondiente.
                    </p>
                </article>

                <article id="objeto" class="legal-card js-reveal">
                    <h2>2. Objeto de la web</h2>

                    <p>
                        La finalidad de esta web es presentar Kitcherry, explicar sus servicios y mostrar soluciones
                        orientadas a la hostelería, especialmente relacionadas con automatización, gestión de consultas,
                        reservas, comunicación de información y apoyo operativo.
                    </p>

                    <p>
                        La información publicada tiene carácter informativo y puede modificarse, ampliarse o actualizarse
                        conforme evolucione el proyecto.
                    </p>
                </article>

                <article id="uso" class="legal-card js-reveal">
                    <h2>3. Uso de la página</h2>

                    <p>
                        El usuario se compromete a utilizar esta web de forma correcta, respetando la finalidad informativa
                        del sitio y evitando cualquier uso que pueda dañar, sobrecargar o afectar al funcionamiento de la página.
                    </p>

                    <ul>
                        <li>No utilizar la web para fines ilícitos.</li>
                        <li>No intentar acceder a zonas privadas o sistemas internos.</li>
                        <li>No introducir información falsa en los formularios.</li>
                        <li>No realizar acciones que puedan perjudicar la seguridad del sitio.</li>
                    </ul>
                </article>

                <article id="propiedad" class="legal-card js-reveal">
                    <h2>4. Propiedad intelectual e industrial</h2>

                    <p>
                        Los contenidos de esta web, incluyendo textos, diseño, estructura, logotipo, colores, imágenes
                        y elementos gráficos, forman parte de la identidad visual y comunicativa de Kitcherry.
                    </p>

                    <p>
                        Queda prohibida su reproducción o uso no autorizado sin permiso previo del titular del proyecto.
                    </p>
                </article>

                <article id="responsabilidad" class="legal-card js-reveal">
                    <h2>5. Responsabilidad</h2>

                    <p>
                        Kitcherry procurará mantener la información de la web actualizada y accesible. No obstante,
                        al tratarse de una maqueta inicial del proyecto, algunos contenidos pueden estar sujetos a cambios
                        o encontrarse en fase de desarrollo.
                    </p>

                    <p>
                        La web puede contener enlaces a páginas externas. Kitcherry no se hace responsable del contenido
                        o funcionamiento de sitios web de terceros.
                    </p>
                </article>

                <article id="contacto-legal" class="legal-card js-reveal">
                    <h2>6. Contacto</h2>

                    <p>
                        Para cualquier consulta relacionada con esta página web, el usuario puede contactar a través del
                        correo electrónico indicado en la sección de contacto.
                    </p>

                    <p>
                        Correo de contacto provisional: <strong>contacto@kitcherry.com</strong>
                    </p>
                </article>

            </div>

        </div>
    </section>

</main>

<?php
require_once __DIR__ . "/includes/footer.php";
?>