<?php
// ==========================================================
// KITCHERRY - QUÉ ES KITCHERRY
// Archivo: nosotros.php
// ==========================================================

require_once __DIR__ . "/includes/helpers.php";
require_once __DIR__ . "/includes/data.php";

$tituloPagina = "Qué es Kitcherry | Herramientas para hostelería";
$descripcionPagina = "Conoce Kitcherry, una marca de herramientas de software para hostelería pensada para ayudar a pequeños negocios a trabajar mejor, ahorrar tiempo y modernizarse sin complicarse.";

require_once __DIR__ . "/includes/header.php";
?>

<main>

    <section class="hero-pagina">
        <div class="contenedor hero-pagina-contenido js-reveal">
            <div class="breadcrumb">
                <a href="index.php">Inicio</a>
                <span>/</span>
                <span>Qué es Kitcherry</span>
            </div>

            <h1>Qué es Kitcherry</h1>

            <p>
                Kitcherry es una propuesta de herramientas de software para hostelería, pensada para ayudar
                a pequeños negocios a ahorrar tiempo, organizar mejor sus procesos y modernizarse de forma sencilla.
            </p>
        </div>
    </section>

    <section class="pagina-interna">
        <div class="contenedor pagina-grid">

            <aside class="bloque-destacado js-reveal">
                <h2>Herramientas para hostelería</h2>

                <p>
                    Kitcherry nace con la idea de unir experiencia real en hostelería y desarrollo de software.
                </p>

                <p>
                    La marca no busca vender tecnología complicada, sino soluciones útiles para problemas cotidianos:
                    consultas, reservas, comunicación de información, organización interna y apoyo al equipo.
                </p>
            </aside>

            <div>

                <article class="bloque-texto js-reveal">
                    <h2>Una marca pensada desde dentro del sector</h2>

                    <p>
                        Kitcherry surge de observar el funcionamiento diario de bares, cafeterías y restaurantes.
                        En este tipo de negocios se repiten muchas tareas: responder preguntas frecuentes,
                        organizar reservas, aclarar información sobre la carta, comunicar alérgenos o coordinar
                        procesos internos.
                    </p>

                    <p>
                        El objetivo de la marca es transformar esas necesidades en herramientas digitales claras,
                        prácticas y fáciles de aplicar. Por eso, Kitcherry se presenta como una empresa cercana,
                        orientada a resolver problemas reales dentro del sector hostelero.
                    </p>
                </article>

                <article class="bloque-texto js-reveal">
                    <h2>Por qué se llama Kitcherry</h2>

                    <p>
                        El nombre Kitcherry combina dos ideas principales. Por una parte, hace referencia a
                        <strong>kitchen</strong>, conectando con el entorno de cocina y hostelería. Por otra parte,
                        se relaciona con el concepto de <strong>kit de herramientas</strong>, ya que la empresa
                        no se plantea como una única aplicación cerrada, sino como un conjunto de soluciones
                        modulares.
                    </p>

                    <p>
                        La cereza aporta personalidad visual y hace que la marca sea más recordable, mientras que
                        el enfoque tecnológico representa la organización, la automatización y el uso práctico de
                        la información.
                    </p>
                </article>

                <article class="bloque-texto js-reveal">
                    <h2>Qué pretende solucionar</h2>

                    <p>
                        Kitcherry está orientada a pequeños negocios hosteleros que quieren mejorar su día a día
                        sin implantar sistemas complejos. La idea es empezar por herramientas concretas que aporten
                        valor desde el primer momento.
                    </p>

                    <ul class="lista-check">
                        <li>Reducir tareas repetitivas relacionadas con consultas frecuentes.</li>
                        <li>Mejorar la organización de reservas y mensajes importantes.</li>
                        <li>Comunicar de forma más clara información sobre platos, carta y alérgenos.</li>
                        <li>Apoyar al personal de sala y cocina con información útil y accesible.</li>
                        <li>Modernizar el negocio sin perder cercanía ni trato humano.</li>
                    </ul>
                </article>

                <article class="bloque-texto js-reveal">
                    <h2>Misión, visión y valores</h2>

                    <p>
                        La misión de Kitcherry es desarrollar herramientas de software útiles para hostelería,
                        capaces de ahorrar tiempo y mejorar procesos cotidianos. La visión es convertirse en una
                        marca reconocida cuando un negocio hostelero piense en modernizarse de forma sencilla.
                    </p>

                    <div class="grid-valores">
                        <div class="valor-card js-reveal">
                            <h3>Utilidad</h3>
                            <p>Las herramientas deben resolver problemas concretos del día a día.</p>
                        </div>

                        <div class="valor-card js-reveal">
                            <h3>Sencillez</h3>
                            <p>La tecnología debe ser comprensible, clara y fácil de implantar.</p>
                        </div>

                        <div class="valor-card js-reveal">
                            <h3>Cercanía</h3>
                            <p>La marca debe hablar desde el conocimiento real del sector hostelero.</p>
                        </div>

                        <div class="valor-card js-reveal">
                            <h3>Orden</h3>
                            <p>El software debe ayudar a organizar mejor mensajes, reservas e información.</p>
                        </div>

                        <div class="valor-card js-reveal">
                            <h3>Innovación</h3>
                            <p>La IA se utiliza como recurso práctico, no como concepto abstracto.</p>
                        </div>

                        <div class="valor-card js-reveal">
                            <h3>Apoyo humano</h3>
                            <p>La tecnología debe ayudar a las personas, no sustituir el trato humano.</p>
                        </div>
                    </div>
                </article>

            </div>

        </div>
    </section>

</main>

<?php
require_once __DIR__ . "/includes/footer.php";
?>