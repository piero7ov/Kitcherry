<?php
// ==========================================================
// KITCHERRY - QUÉ ES KITCHERRY
// Archivo: nosotros.php
// ==========================================================

require_once __DIR__ . "/includes/helpers.php";
require_once __DIR__ . "/includes/data.php";

$tituloPagina = "Qué es Kitcherry | Herramientas para hostelería";
$descripcionPagina = "Conoce Kitcherry, una marca de herramientas de software para hostelería pensada para ayudar a pequeños negocios a trabajar con más orden, ahorrar tiempo y modernizarse sin complicarse.";

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

            <h1>Software para hostelería, pensado desde problemas reales.</h1>

            <p>
                Kitcherry reúne herramientas digitales para ayudar a restaurantes, bares y cafeterías
                a trabajar con más orden, ahorrar tiempo y modernizar procesos del día a día.
            </p>

            <div class="nosotros-acciones nosotros-hero-acciones">
                <a href="productos.php" class="btn btn-principal">Ver productos</a>
                <a href="index.php#contacto" class="btn btn-secundario">Solicitar información</a>
            </div>
        </div>
    </section>

    <section class="pagina-interna nosotros-pagina">
        <div class="contenedor">

            <article class="bloque-destacado nosotros-intro-visual js-reveal">
                <div class="nosotros-intro-texto">
                    <span class="nosotros-tag">Qué es Kitcherry</span>

                    <h2>Un kit de herramientas digitales para negocios hosteleros</h2>

                    <p>
                        Kitcherry no se plantea como una única aplicación cerrada, sino como un conjunto
                        de productos que pueden usarse de forma independiente según las necesidades de cada negocio.
                    </p>

                    <p>
                        La idea es sencilla: crear soluciones claras para reservas, comunicación, cocina,
                        sala, stock, tareas internas y seguimiento digital.
                    </p>

                    <div class="nosotros-acciones">
                        <a href="productos.php" class="btn btn-principal">Ver catálogo</a>
                        <a href="index.php#contacto" class="btn btn-secundario">Contactar</a>
                    </div>
                </div>

                <figure class="nosotros-imagen nosotros-imagen-dark">
                    <img src="img/nosotros1.png" alt="Imagen abstracta de Kitcherry como sistema digital para hostelería" loading="lazy" decoding="async">
                </figure>
            </article>

            <div class="nosotros-grid-corto">

                <article class="bloque-texto nosotros-card-mini js-reveal">
                    <h2>Nace desde la hostelería</h2>
                    <p>
                        Kitcherry parte de problemas reales: consultas repetidas, reservas desordenadas,
                        dudas sobre platos, tareas internas y falta de tiempo durante el servicio.
                    </p>
                </article>

                <article class="bloque-texto nosotros-card-mini js-reveal">
                    <h2>Herramientas concretas</h2>
                    <p>
                        Cada producto responde a una necesidad específica del negocio, evitando plataformas
                        demasiado grandes o difíciles de implantar.
                    </p>
                </article>

                <article class="bloque-texto nosotros-card-mini js-reveal">
                    <h2>Tecnología útil</h2>
                    <p>
                        La IA y la automatización se usan como apoyo práctico para reducir tareas repetitivas,
                        ordenar información y ayudar al equipo.
                    </p>
                </article>

            </div>

            <article class="bloque-texto nosotros-split-visual js-reveal">
                <figure class="nosotros-imagen nosotros-imagen-light">
                    <img src="img/nosotros2.png" alt="Imagen abstracta de Kitcherry conectando herramientas digitales" loading="lazy" decoding="async">
                </figure>

                <div class="nosotros-split-texto">
                    <span class="nosotros-tag nosotros-tag-claro">Enfoque modular</span>

                    <h2>Una marca flexible, no una solución cerrada</h2>

                    <p>
                        Cada negocio puede empezar por la herramienta que más necesita: reservas, correo,
                        documentación, cocina, stock o tareas internas.
                    </p>

                    <ul class="lista-check">
                        <li>Productos independientes y combinables.</li>
                        <li>Implantación progresiva y sencilla.</li>
                        <li>Diseño pensado para pequeños negocios.</li>
                        <li>Control humano en los procesos importantes.</li>
                    </ul>
                </div>
            </article>

            <article class="bloque-texto nosotros-valores js-reveal">
                <h2>Misión, visión y valores</h2>

                <p>
                    La misión de Kitcherry es crear herramientas útiles para hostelería, capaces de ahorrar tiempo,
                    mejorar la organización y facilitar el trabajo diario sin perder cercanía.
                </p>

                <div class="grid-valores nosotros-grid-valores">
                    <div class="valor-card js-reveal">
                        <h3>Utilidad</h3>
                        <p>Resolver problemas concretos del día a día.</p>
                    </div>

                    <div class="valor-card js-reveal">
                        <h3>Sencillez</h3>
                        <p>Crear herramientas claras y fáciles de aplicar.</p>
                    </div>

                    <div class="valor-card js-reveal">
                        <h3>Cercanía</h3>
                        <p>Entender la hostelería desde su realidad diaria.</p>
                    </div>

                    <div class="valor-card js-reveal">
                        <h3>Apoyo humano</h3>
                        <p>Usar la tecnología para ayudar, no para sustituir.</p>
                    </div>
                </div>
            </article>

            <article class="bloque-destacado nosotros-cierre js-reveal">
                <div>
                    <span class="nosotros-tag">Kitcherry</span>
                    <h2>Herramientas claras para trabajar mejor</h2>
                    <p>
                        Una forma sencilla de modernizar procesos, reducir tareas repetitivas y mejorar
                        la organización diaria de un negocio hostelero.
                    </p>
                </div>

                <div class="nosotros-acciones nosotros-acciones-final">
                    <a href="productos.php" class="btn btn-principal">Ver productos</a>
                    <a href="index.php#contacto" class="btn btn-secundario">Lo quiero para mi negocio</a>
                </div>
            </article>

        </div>
    </section>

</main>

<?php
require_once __DIR__ . "/includes/footer.php";
?>