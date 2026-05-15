<?php
// ==========================================================
// KITCHERRY - CATÁLOGO DE PRODUCTOS
// Archivo: productos.php
// ==========================================================

require_once __DIR__ . "/includes/helpers.php";
require_once __DIR__ . "/includes/data.php";

$tituloPagina = "Productos Kitcherry | Herramientas digitales para hostelería";
$descripcionPagina = "Catálogo de productos Kitcherry: herramientas digitales para reservas, comunicación, cocina, sala, stock, formación, correo, alérgenos y organización interna en hostelería.";

$imagenProductos1 = "img/productos1.png";
$imagenProductos2 = "img/productos2.png";

$imagenProductos1Existe = file_exists(__DIR__ . "/" . $imagenProductos1);
$imagenProductos2Existe = file_exists(__DIR__ . "/" . $imagenProductos2);

// ==========================================================
// INFORMACIÓN EXTRA PARA MAQUETAR CADA PRODUCTO
// Se usa junto al array $servicios de includes/data.php.
// Los enlaces de demo se siguen leyendo desde $servicios.
// ==========================================================

$productosMeta = [
    "01" => [
        "id" => "sistema-comunicaciones",
        "nombre" => "Kitcherry Communications",
        "categoria" => "Atención y comunicación",
        "frase" => "Consultas y respuestas bajo control.",
        "problema" => "Para negocios que reciben muchas consultas por correo, formularios o canales digitales y necesitan clasificarlas sin perder mensajes importantes.",
        "ideal" => "Restaurantes, bares o cafeterías con muchas preguntas repetidas sobre reservas, horarios, carta, ubicación, alérgenos o servicios.",
        "funciones" => [
            "Clasificación de consultas según intención.",
            "Priorización de mensajes importantes.",
            "Borradores de respuesta revisables.",
            "Seguimiento del estado de cada comunicación."
        ]
    ],
    "02" => [
        "id" => "kitcherry-chatbot",
        "nombre" => "Kitcherry Chatbot",
        "categoria" => "Atención al cliente",
        "frase" => "Un asistente web para responder dudas frecuentes.",
        "problema" => "Para negocios que quieren atender dudas comunes desde la web sin depender siempre de una respuesta manual inmediata.",
        "ideal" => "Restaurantes que quieren explicar servicios, carta, reservas, horarios o información frecuente desde una interfaz conversacional.",
        "funciones" => [
            "Modo corporativo para explicar Kitcherry.",
            "Modo restaurante adaptado a un negocio concreto.",
            "Respuestas sobre carta, servicios y reservas.",
            "Atención rápida desde la propia web."
        ]
    ],
    "03" => [
        "id" => "kitcherry-reservas",
        "nombre" => "Kitcherry Reservas",
        "categoria" => "Reservas y sala",
        "frase" => "Reservas, mesas y clientes bajo control.",
        "problema" => "Para negocios que gestionan reservas de forma manual y necesitan una vista más clara de ocupación, estados y clientes.",
        "ideal" => "Restaurantes, bares y cafeterías que quieren reducir errores, evitar conflictos de mesas y mejorar la planificación del servicio.",
        "funciones" => [
            "Registro y seguimiento de reservas.",
            "Control de mesas, estados y ocupación.",
            "Historial básico de clientes habituales.",
            "Confirmaciones, recordatorios y alertas internas."
        ]
    ],
    "04" => [
        "id" => "kitcherry-docs",
        "nombre" => "Kitcherry Docs",
        "categoria" => "Carta, platos y alérgenos",
        "frase" => "Información clara para sala, cocina y clientes.",
        "problema" => "Para negocios que tienen información de carta, fichas técnicas o alérgenos dispersa y difícil de consultar durante el servicio.",
        "ideal" => "Restaurantes que quieren comunicar mejor platos, ingredientes, precios, categorías y advertencias alimentarias.",
        "funciones" => [
            "Organización de carta y fichas técnicas.",
            "Consulta estructurada de platos e ingredientes.",
            "Control de alérgenos y advertencias.",
            "Vista interna para revisar la información."
        ]
    ],
    "05" => [
        "id" => "kitcherry-kitchen-assistant",
        "nombre" => "Kitcherry Kitchen Assistant",
        "categoria" => "Cocina y producción interna",
        "frase" => "Un asistente digital para apoyar al equipo de cocina.",
        "problema" => "Para cocinas donde el equipo necesita consultar elaboraciones, gramajes, pasos, emplatados o incidencias sin depender de memoria o documentos sueltos.",
        "ideal" => "Equipos de cocina que quieren acceder rápido a información interna durante preparación, producción o servicio.",
        "funciones" => [
            "Consultas por texto o voz.",
            "Respuestas apoyadas en manual interno.",
            "Avatar 3D y síntesis de voz.",
            "Control para evitar respuestas inventadas en temas delicados."
        ]
    ],
    "06" => [
        "id" => "kitcherry-service-map",
        "nombre" => "Kitcherry Service Map",
        "categoria" => "Sala y organización del servicio",
        "frase" => "Un plano visual para preparar mejor cada servicio.",
        "problema" => "Para negocios que necesitan visualizar mesas, reservas y ocupación del local de forma rápida antes o durante el servicio.",
        "ideal" => "Restaurantes con sala organizada por mesas, turnos, reservas dobladas o necesidad de imprimir una vista clara del servicio.",
        "funciones" => [
            "Plano visual de mesas y elementos de sala.",
            "Estados de mesas libres, reservadas u ocupadas.",
            "Colocación de reservas sobre el plano.",
            "Vista preparada para exportar o imprimir."
        ]
    ],
    "07" => [
        "id" => "kitcherry-voice-tasks",
        "nombre" => "Kitcherry Voice Tasks",
        "categoria" => "Tareas internas",
        "frase" => "Listas rápidas por voz para cocina y sala.",
        "problema" => "Para equipos que comunican reposiciones, elaboraciones, avisos o incidencias de forma verbal y luego pierden parte de la información.",
        "ideal" => "Cocina, sala o managers que necesitan crear listas claras de turno sin depender siempre de papel o mensajes desordenados.",
        "funciones" => [
            "Creación de listas por voz o escritura.",
            "Organización de tareas, productos y avisos.",
            "Edición rápida de elementos.",
            "Envío por correo al personal correspondiente."
        ]
    ],
    "08" => [
        "id" => "kitcherry-stock",
        "nombre" => "Kitcherry Stock",
        "categoria" => "Stock y solicitudes internas",
        "frase" => "Control de productos, proveedores y reposición.",
        "problema" => "Para negocios que necesitan controlar stock bajo, productos, proveedores y solicitudes internas sin montar una base de datos compleja.",
        "ideal" => "Pequeños restaurantes que quieren usar Google Drive y Google Sheets como base sencilla para organizar información interna.",
        "funciones" => [
            "Control de stock y productos.",
            "Detección de productos con bajo nivel.",
            "Gestión de proveedores.",
            "Solicitudes internas de reposición."
        ]
    ],
    "09" => [
        "id" => "kitcherry-mail",
        "nombre" => "Kitcherry Mail",
        "categoria" => "Correo y comunicación digital",
        "frase" => "Un cliente de correo pensado para negocios hosteleros.",
        "problema" => "Para negocios que reciben correos de clientes, proveedores o colaboradores y necesitan ordenarlos por estado y prioridad.",
        "ideal" => "Restaurantes que quieren centralizar mensajes, responder, reenviar, guardar borradores y hacer seguimiento visual de comunicaciones.",
        "funciones" => [
            "Sincronización de correos mediante IMAP.",
            "Borradores, respuestas y reenvíos.",
            "Estados, notas internas y búsqueda.",
            "Vista Kanban para seguimiento visual."
        ]
    ],
    "10" => [
        "id" => "kitcherry-staff-training",
        "nombre" => "Kitcherry Staff Training",
        "categoria" => "Formación y seguimiento",
        "frase" => "Formación interna más clara para el equipo.",
        "problema" => "Para negocios que necesitan formar al personal, revisar conocimientos clave y comprobar el progreso de cada trabajador.",
        "ideal" => "Restaurantes con rotación de personal, nuevos trabajadores o necesidad de ordenar formación básica del servicio.",
        "funciones" => [
            "Materiales de formación interna.",
            "Control de conocimientos clave.",
            "Seguimiento del progreso del personal.",
            "Organización clara para responsables del equipo."
        ]
    ]
];

require_once __DIR__ . "/includes/header.php";
?>

<main>

    <section class="hero-pagina productos-hero">
        <div class="contenedor productos-hero-grid js-reveal">
            <div class="productos-hero-texto">
                <div class="breadcrumb">
                    <a href="index.php">Inicio</a>
                    <span>/</span>
                    <span>Productos</span>
                </div>

                <h1>Productos digitales para mejorar la hostelería.</h1>

                <p>
                    Kitcherry reúne herramientas pensadas para restaurantes, bares y cafeterías que quieren trabajar
                    con más orden, ahorrar tiempo y modernizar procesos sin complicarse.
                </p>

                <div class="hero-acciones productos-hero-acciones">
                    <a href="#catalogo-productos" class="btn btn-principal">Ver catálogo</a>
                    <a href="index.php#contacto" class="btn btn-secundario">Solicitar información</a>
                </div>
            </div>

            <figure class="productos-hero-imagen">
                <?php if ($imagenProductos1Existe): ?>
                    <img src="<?php echo e($imagenProductos1); ?>" alt="Imagen abstracta de productos digitales Kitcherry" loading="lazy" decoding="async">
                <?php else: ?>
                    <div class="productos-imagen-placeholder">
                        <span>Kitcherry</span>
                        <strong>Catálogo modular</strong>
                    </div>
                <?php endif; ?>
            </figure>
        </div>
    </section>

    <section class="seccion fondo-suave productos-intro">
        <div class="contenedor">

            <div class="titulo-bloque js-reveal">
                <h2>Un catálogo modular para distintas áreas del negocio</h2>
                <p>
                    Cada producto se centra en una necesidad concreta: comunicación, reservas, cocina, sala,
                    stock, tareas internas, formación o documentación. Puedes empezar por una herramienta y ampliar después.
                </p>
            </div>

            <div class="productos-categorias-grid">
                <article class="productos-categoria-card js-reveal">
                    <span>01</span>
                    <h3>Comunicación</h3>
                    <p>Consultas, chatbot y correo para responder mejor y ordenar mensajes.</p>
                </article>

                <article class="productos-categoria-card js-reveal">
                    <span>02</span>
                    <h3>Reservas y sala</h3>
                    <p>Gestión de reservas, mesas, ocupación y planificación visual del servicio.</p>
                </article>

                <article class="productos-categoria-card js-reveal">
                    <span>03</span>
                    <h3>Cocina y tareas</h3>
                    <p>Apoyo interno, listas por voz, procesos de cocina y coordinación del equipo.</p>
                </article>

                <article class="productos-categoria-card js-reveal">
                    <span>04</span>
                    <h3>Stock y formación</h3>
                    <p>Control interno, documentación, alérgenos y aprendizaje del personal.</p>
                </article>
            </div>

        </div>
    </section>

    <section id="catalogo-productos" class="seccion productos-catalogo">
        <div class="contenedor">

            <div class="titulo-bloque js-reveal">
                <h2>Catálogo de productos Kitcherry</h2>
                <p>
                    Revisa cada herramienta, accede a su demo y solicita información del producto que más encaje
                    con las necesidades de tu negocio.
                </p>
            </div>

            <div class="productos-lista">
                <?php foreach ($servicios as $servicio): ?>
                    <?php
                        $numero = $servicio["numero"] ?? "";
                        $meta = $productosMeta[$numero] ?? [];

                        $id = $meta["id"] ?? "producto-" . $numero;
                        $nombre = $meta["nombre"] ?? ($servicio["titulo"] ?? "Producto Kitcherry");
                        $categoria = $meta["categoria"] ?? "Producto Kitcherry";
                        $frase = $meta["frase"] ?? "";
                        $problema = $meta["problema"] ?? ($servicio["texto"] ?? "");
                        $ideal = $meta["ideal"] ?? "";
                        $funciones = $meta["funciones"] ?? [];

                        $urlDemo = $servicio["url"] ?? "";
                        $textoBotonDemo = $servicio["boton"] ?? "Ver demo";
                        $esEnlaceExterno = str_starts_with($urlDemo, "http://") || str_starts_with($urlDemo, "https://");
                    ?>

                    <article class="producto-detalle js-reveal" id="<?php echo e($id); ?>">
                        <div class="producto-detalle-header">
                            <div>
                                <span class="producto-numero"><?php echo e($numero); ?></span>
                                <span class="producto-categoria"><?php echo e($categoria); ?></span>
                            </div>

                            <a href="#catalogo-productos" class="producto-volver" aria-label="Volver al inicio del catálogo">
                                ↑ Catálogo
                            </a>
                        </div>

                        <div class="producto-detalle-grid">

                            <div class="producto-info">
                                <h2><?php echo e($nombre); ?></h2>

                                <?php if ($frase !== ""): ?>
                                    <p class="producto-frase"><?php echo e($frase); ?></p>
                                <?php endif; ?>

                                <p>
                                    <?php echo e($servicio["texto"] ?? ""); ?>
                                </p>

                                <div class="producto-acciones">
                                    <?php if ($urlDemo !== ""): ?>
                                        <a
                                            href="<?php echo e($urlDemo); ?>"
                                            class="btn btn-principal"
                                            <?php if ($esEnlaceExterno): ?>
                                                target="_blank" rel="noopener"
                                            <?php endif; ?>
                                        >
                                            <?php echo e($textoBotonDemo); ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="index.php#producto-destacado" class="btn btn-principal">
                                            Ver producto destacado
                                        </a>
                                    <?php endif; ?>

                                    <a href="index.php?producto=<?php echo urlencode($id); ?>#contacto" class="btn btn-secundario producto-btn-claro">
                                        Lo quiero para mi negocio
                                    </a>
                                </div>
                            </div>

                            <div class="producto-panel">
                                <div class="producto-panel-bloque">
                                    <h3>Problema que resuelve</h3>
                                    <p><?php echo e($problema); ?></p>
                                </div>

                                <?php if ($ideal !== ""): ?>
                                    <div class="producto-panel-bloque">
                                        <h3>Ideal para</h3>
                                        <p><?php echo e($ideal); ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($funciones)): ?>
                                    <div class="producto-panel-bloque">
                                        <h3>Funciones principales</h3>
                                        <ul class="lista-check producto-funciones">
                                            <?php foreach ($funciones as $funcion): ?>
                                                <li><?php echo e($funcion); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

        </div>
    </section>

    <section class="seccion fondo-suave productos-bloque-visual">
        <div class="contenedor productos-split-final js-reveal">

            <figure class="productos-final-imagen">
                <?php if ($imagenProductos2Existe): ?>
                    <img src="<?php echo e($imagenProductos2); ?>" alt="Imagen abstracta de herramientas conectadas de Kitcherry" loading="lazy" decoding="async">
                <?php else: ?>
                    <div class="productos-imagen-placeholder productos-imagen-placeholder-claro">
                        <span>Kitcherry</span>
                        <strong>Herramientas conectadas</strong>
                    </div>
                <?php endif; ?>
            </figure>

            <div class="productos-final-texto">
                <span class="producto-categoria">Implantación progresiva</span>

                <h2>Empieza por el producto que más necesita tu negocio</h2>

                <p>
                    No todos los restaurantes necesitan lo mismo. Por eso, Kitcherry permite plantear una adopción modular:
                    empezar con una herramienta concreta y ampliar después según el ritmo, tamaño y necesidades del negocio.
                </p>

                <div class="productos-final-lista">
                    <div>
                        <strong>01</strong>
                        <span>Elegir la necesidad principal</span>
                    </div>

                    <div>
                        <strong>02</strong>
                        <span>Probar la demo del producto</span>
                    </div>

                    <div>
                        <strong>03</strong>
                        <span>Solicitar información o adaptación</span>
                    </div>
                </div>

                <div class="producto-acciones">
                    <a href="index.php#contacto" class="btn btn-principal">Solicitar información</a>
                    <a href="nosotros.php" class="btn btn-secundario producto-btn-claro">Qué es Kitcherry</a>
                </div>
            </div>

        </div>
    </section>

</main>

<?php
require_once __DIR__ . "/includes/footer.php";
?>