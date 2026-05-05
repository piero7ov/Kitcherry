<?php
// ==========================================================
// KITCHERRY - DATOS DE LA WEB
// Archivo: includes/data.php
// ==========================================================

$servicios = [
    [
        "numero" => "01",
        "titulo" => "Sistema inteligente de consultas y comunicaciones en hostelería",
        "texto" => "Ayuda a gestionar consultas frecuentes de clientes, organizar mensajes importantes y responder con mayor rapidez en momentos de alta carga de trabajo."
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
        "titulo" => "Herramienta de gestión y seguimiento de reservas",
        "texto" => "Herramienta para ordenar solicitudes, confirmar reservas y reducir errores en la planificación diaria del servicio."
    ],
    [
        "numero" => "04",
        "titulo" => "Plataforma de información de carta, platos y alérgenos",
        "texto" => "Plataforma para comunicar información sobre menús, ingredientes y alérgenos de forma clara, accesible y actualizable."
    ],
    [
        "numero" => "05",
        "titulo" => "Asistente digital de apoyo a cocina y producción interna",
        "texto" => "Sistema de apoyo para consultar procedimientos, elaboraciones, pasos de preparación o información interna durante el servicio."
    ],
    [
        "numero" => "06",
        "titulo" => "Sistema de consulta rápida para personal de sala",
        "texto" => "Herramienta para que el equipo de sala consulte platos, sugerencias, alérgenos o información útil de atención al cliente."
    ],
    [
        "numero" => "07",
        "titulo" => "Plataforma de organización interna de tareas e incidencias",
        "texto" => "Plataforma para organizar tareas internas, registrar incidencias y mejorar la coordinación diaria del equipo."
    ],
    [
        "numero" => "08",
        "titulo" => "Sistema de apoyo a la gestión de pedidos o solicitudes internas",
        "texto" => "Sistema sencillo para gestionar pedidos internos, solicitudes operativas o movimientos de información dentro del negocio."
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