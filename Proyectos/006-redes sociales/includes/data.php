<?php
// ==========================================================
// KITCHERRY - DATOS DE LA WEB
// Archivo: includes/data.php
// ==========================================================

$servicios = [
    [
        "numero" => "01",
        "titulo" => "Sistema inteligente de consultas y comunicaciones en hostelería",
        "texto" => "Ayuda a recibir, clasificar y organizar consultas frecuentes de clientes, priorizar mensajes importantes y preparar respuestas con mayor rapidez en momentos de alta carga de trabajo."
    ],
    [
        "numero" => "02",
        "titulo" => "Chatbot inteligente para atención al cliente",
        "texto" => "Asistente conversacional para web que responde dudas frecuentes y puede adaptarse a la información de cada negocio, como servicios, carta, reservas, delivery, precios de referencia o alérgenos.",
        "boton" => "Probar demo del chatbot",
        "url" => "chatbot.php"
    ],
    [
        "numero" => "03",
        "titulo" => "Kitcherry Reservas: reservas y mesas bajo control",
        "texto" => "Solución para restaurantes, bares y cafeterías que permite gestionar reservas, visualizar la ocupación de mesas, evitar conflictos y mejorar la comunicación con los clientes mediante confirmaciones y recordatorios.",
        "url" => "https://youtu.be/m8J8CziCXFw",
        "boton" => "Ver Kitcherry Reservas"
    ],
    [
        "numero" => "04",
        "titulo" => "Kitcherry Docs: plataforma de información de carta, platos y alérgenos",
        "texto" => "Herramienta para transformar cartas, fichas técnicas y tablas de alérgenos en una consulta clara, estructurada y útil para sala, cocina y administración.",
        "url" => "https://youtu.be/1M8zZGY-MYE",
        "boton" => "Ver Kitcherry Docs en acción"
    ],
    [
        "numero" => "05",
        "titulo" => "Asistente digital de cocina con IA",
        "texto" => "Un asistente inteligente para que el personal de cocina consulte recetas internas, gramajes, mise en place, emplatados e incidencias de forma rápida, por texto o voz. Integra avatar 3D, respuestas basadas en un manual interno y controles para evitar información inventada en consultas delicadas.",
        "url" => "https://youtu.be/HMuVmJm6ejc",
        "boton" => "Ver Kitcherry Kitchen Assistant"
    ],
    [
        "numero" => "06",
        "titulo" => "Kitcherry Service Map: plano visual de sala",
        "texto" => "Herramienta visual para organizar el servicio del restaurante, colocar reservas sobre un plano de mesas, identificar mesas libres, reservadas u ocupadas y gestionar situaciones como mesas dobladas de forma clara y práctica.",
        "url" => "https://youtu.be/du_hKgylAB0",
        "boton" => "Ver Kitcherry Service Map"
    ],
    [
        "numero" => "07",
        "titulo" => "Plataforma de organización interna de tareas e incidencias",
        "texto" => "Plataforma para organizar tareas internas, registrar incidencias y mejorar la coordinación diaria del equipo."
    ],
    [
        "numero" => "08",
        "titulo" => "Sistema de gestión de stock y solicitudes internas con Google Drive",
        "texto" => "Solución interna para restaurantes y negocios hosteleros que permite controlar el stock, detectar productos que necesitan reposición, organizar proveedores y gestionar solicitudes internas de forma más rápida, clara y profesional.",
        "url" => "https://youtu.be/LDCuCiqKuEY",
        "boton" => "Ver Kitcherry Stock"
    ],
    [
        "numero" => "09",
        "titulo" => "Plataforma de comunicación comercial y presencia digital del negocio",
        "texto" => "Herramientas para comunicar promociones, novedades e información relevante del establecimiento de forma profesional."
    ],
    [
        "numero" => "10",
        "titulo" => "Plataforma modular de implantación, personalización y mejora continua",
        "texto" => "Modelo modular que permite adaptar las herramientas a cada negocio, implantarlas progresivamente y mejorarlas con acompañamiento."
    ]
];

$necesidades = [
    [
        "titulo" => "Automatizar tareas repetitivas",
        "texto" => "Muchos pequeños negocios hosteleros repiten a diario las mismas respuestas sobre horarios, reservas, ubicación o servicios."
    ],
    [
        "titulo" => "Gestionar mejor consultas y reservas",
        "texto" => "El volumen de mensajes, llamadas o correos puede provocar olvidos, retrasos o desorganización."
    ],
    [
        "titulo" => "Comunicar información clara",
        "texto" => "La carta, los ingredientes y los alérgenos deben presentarse de forma comprensible tanto para clientes como para el personal."
    ],
    [
        "titulo" => "Ahorrar tiempo",
        "texto" => "El objetivo no es añadir más trabajo, sino reducir carga repetitiva y liberar tiempo para la atención real del negocio."
    ],
    [
        "titulo" => "Modernizar el negocio sin complicarse",
        "texto" => "La tecnología debe ser práctica, fácil de implantar y adaptada al ritmo real de restaurantes, bares y cafeterías."
    ],
    [
        "titulo" => "Mejorar la operativa diaria",
        "texto" => "Ordenar procesos internos, coordinar mejor al equipo y reducir errores tiene impacto directo en el servicio."
    ]
];

$flujoTrabajo = [
    [
        "paso" => "1",
        "titulo" => "Recibe las consultas",
        "texto" => "El negocio puede reunir las consultas habituales de clientes en un sistema más ordenado y fácil de revisar."
    ],
    [
        "paso" => "2",
        "titulo" => "Entiende la necesidad del cliente",
        "texto" => "La herramienta ayuda a diferenciar si la consulta está relacionada con reservas, horarios, carta, alérgenos, ubicación o servicios."
    ],
    [
        "paso" => "3",
        "titulo" => "Ordena la atención",
        "texto" => "Permite dar más visibilidad a las solicitudes importantes y reducir el riesgo de perder mensajes relevantes."
    ],
    [
        "paso" => "4",
        "titulo" => "Agiliza la respuesta",
        "texto" => "Facilita respuestas claras y adaptadas al negocio para mejorar la rapidez de atención sin saturar al equipo."
    ]
];

// ==========================================================
// RUTAS DE ARCHIVOS
// ==========================================================

$logoPath = "img/logo-kitcherry.png";
$heroPath = "img/hero.png";
$videoPath = "destacados/email_sender.mp4";

$logoExiste = file_exists(__DIR__ . "/../" . $logoPath);
$heroExiste = file_exists(__DIR__ . "/../" . $heroPath);
$videoExiste = file_exists(__DIR__ . "/../" . $videoPath);