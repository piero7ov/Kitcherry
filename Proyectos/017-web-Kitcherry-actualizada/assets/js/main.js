// ==========================================================
// KITCHERRY - JAVASCRIPT PRINCIPAL
// Archivo: assets/js/main.js
// ==========================================================

document.documentElement.classList.add("js");

document.addEventListener("DOMContentLoaded", function () {
    prepararElementosAnimados();
    controlarCabecera();
    revelarElementos();
    cerrarMenuMovil();
    marcarMenuActivo();
    moverLuzHeroPrincipal();
    moverLuzHeroPaginas();
});

// ==========================================================
// 1. Preparar elementos animados de toda la web
// ==========================================================

function prepararElementosAnimados() {
    const selectoresAnimados = [
        ".hero-contenido",
        ".hero-datos",
        ".titulo-bloque",
        ".card",
        ".servicio",
        ".caso-principal",
        ".producto-demo",
        ".video-demo",
        ".paso",
        ".bloque-ia",
        ".ia-item",
        ".contacto-info",
        ".formulario",

        // Páginas internas
        ".hero-pagina-contenido",
        ".bloque-destacado",
        ".bloque-texto",
        ".valor-card",
        ".legal-indice",
        ".legal-card"
    ];

    const elementos = document.querySelectorAll(selectoresAnimados.join(", "));

    elementos.forEach(function (elemento) {
        elemento.classList.add("js-reveal");
    });

    prepararDireccionesAnimacion();
    aplicarRetrasosSuaves();
}

// ==========================================================
// 2. Dar variedad a las animaciones
// ==========================================================

function prepararDireccionesAnimacion() {
    const heroes = document.querySelectorAll(
        ".hero-contenido, .hero-pagina-contenido"
    );

    const destacadosIzquierda = document.querySelectorAll(
        ".bloque-destacado, .contacto-info, .caso-principal, .legal-indice"
    );

    const destacadosDerecha = document.querySelectorAll(
        ".bloque-texto, .formulario, .producto-demo, .legal-card"
    );

    heroes.forEach(function (elemento) {
        elemento.classList.add("reveal-up");
    });

    destacadosIzquierda.forEach(function (elemento) {
        elemento.classList.add("reveal-left");
    });

    destacadosDerecha.forEach(function (elemento) {
        elemento.classList.add("reveal-right");
    });
}

// ==========================================================
// 3. Aplicar pequeños retrasos por grupos
// ==========================================================

function aplicarRetrasosSuaves() {
    const grupos = [
        ".grid-necesidades .card",
        ".grid-servicios .servicio",
        ".flujo .paso",
        ".ia-items .ia-item",
        ".grid-valores .valor-card",
        ".legal-contenido .legal-card"
    ];

    grupos.forEach(function (selector) {
        const elementos = document.querySelectorAll(selector);

        elementos.forEach(function (elemento, index) {
            const retraso = Math.min(index * 90, 540);
            elemento.style.transitionDelay = retraso + "ms";
        });
    });
}

// ==========================================================
// 4. Cambiar estilo de la cabecera al hacer scroll
// ==========================================================

function controlarCabecera() {
    const cabecera = document.getElementById("cabecera");

    if (!cabecera) {
        return;
    }

    function actualizarCabecera() {
        if (window.scrollY > 20) {
            cabecera.classList.add("scrolled");
        } else {
            cabecera.classList.remove("scrolled");
        }
    }

    actualizarCabecera();

    window.addEventListener("scroll", actualizarCabecera);
}

// ==========================================================
// 5. Efecto de aparición al hacer scroll
// ==========================================================

function revelarElementos() {
    const elementos = document.querySelectorAll(".js-reveal");

    if (elementos.length === 0) {
        return;
    }

    const opciones = {
        threshold: 0.12,
        rootMargin: "0px 0px -60px 0px"
    };

    const observador = new IntersectionObserver(function (entradas) {
        entradas.forEach(function (entrada) {
            if (entrada.isIntersecting) {
                setTimeout(function () {
                    entrada.target.classList.add("is-visible");
                }, 120);

                observador.unobserve(entrada.target);
            }
        });
    }, opciones);

    elementos.forEach(function (elemento) {
        observador.observe(elemento);
    });
}

// ==========================================================
// 6. Cerrar menú móvil al pulsar un enlace
// ==========================================================

function cerrarMenuMovil() {
    const checkboxMenu = document.getElementById("menu-toggle");
    const enlacesMenu = document.querySelectorAll(".menu a");

    if (!checkboxMenu || enlacesMenu.length === 0) {
        return;
    }

    enlacesMenu.forEach(function (enlace) {
        enlace.addEventListener("click", function () {
            checkboxMenu.checked = false;
        });
    });
}

// ==========================================================
// 7. Marcar enlace activo del menú
// Funciona en index.php y en páginas internas.
// ==========================================================

function marcarMenuActivo() {
    const enlaces = document.querySelectorAll(".menu a");

    if (enlaces.length === 0) {
        return;
    }

    const rutaActual = window.location.pathname;
    const paginaActual = rutaActual.split("/").pop() || "index.php";

    enlaces.forEach(function (enlace) {
        enlace.classList.remove("activo");

        const href = enlace.getAttribute("href");

        if (!href) {
            return;
        }

        const paginaHref = href.split("#")[0];

        if (paginaHref === paginaActual && !href.includes("#")) {
            enlace.classList.add("activo");
        }
    });

    const secciones = [];

    enlaces.forEach(function (enlace) {
        const href = enlace.getAttribute("href");

        if (!href || !href.includes("#")) {
            return;
        }

        const partes = href.split("#");
        const paginaHref = partes[0];
        const idSeccion = "#" + partes[1];

        const enlacePertenecePaginaActual =
            paginaHref === "" ||
            paginaHref === paginaActual ||
            (paginaActual === "" && paginaHref === "index.php");

        if (!enlacePertenecePaginaActual) {
            return;
        }

        const seccion = document.querySelector(idSeccion);

        if (seccion) {
            secciones.push({
                enlace: enlace,
                seccion: seccion
            });
        }
    });

    if (secciones.length === 0) {
        return;
    }

    function actualizarActivoPorScroll() {
        const posicionActual = window.scrollY + 150;

        let enlaceActivo = null;

        secciones.forEach(function (item) {
            const inicio = item.seccion.offsetTop;
            const fin = inicio + item.seccion.offsetHeight;

            if (posicionActual >= inicio && posicionActual < fin) {
                enlaceActivo = item.enlace;
            }
        });

        if (enlaceActivo) {
            enlaces.forEach(function (enlace) {
                enlace.classList.remove("activo");
            });

            enlaceActivo.classList.add("activo");
        }
    }

    actualizarActivoPorScroll();

    window.addEventListener("scroll", actualizarActivoPorScroll);
}

// ==========================================================
// 8. Movimiento sutil de la luz roja del hero principal
// ==========================================================

function moverLuzHeroPrincipal() {
    const hero = document.querySelector(".hero");

    if (!hero) {
        return;
    }

    hero.addEventListener("mousemove", function (evento) {
        const rect = hero.getBoundingClientRect();

        const x = ((evento.clientX - rect.left) / rect.width) * 100;
        const y = ((evento.clientY - rect.top) / rect.height) * 100;

        hero.style.setProperty("--hero-x", x.toFixed(2) + "%");
        hero.style.setProperty("--hero-y", y.toFixed(2) + "%");
    });

    hero.addEventListener("mouseleave", function () {
        hero.style.setProperty("--hero-x", "12%");
        hero.style.setProperty("--hero-y", "50%");
    });
}

// ==========================================================
// 9. Movimiento sutil de la luz roja en páginas internas
// ==========================================================

function moverLuzHeroPaginas() {
    const heroPagina = document.querySelector(".hero-pagina");

    if (!heroPagina) {
        return;
    }

    heroPagina.addEventListener("mousemove", function (evento) {
        const rect = heroPagina.getBoundingClientRect();

        const x = ((evento.clientX - rect.left) / rect.width) * 100;
        const y = ((evento.clientY - rect.top) / rect.height) * 100;

        heroPagina.style.setProperty("--pagina-hero-x", x.toFixed(2) + "%");
        heroPagina.style.setProperty("--pagina-hero-y", y.toFixed(2) + "%");
    });

    heroPagina.addEventListener("mouseleave", function () {
        heroPagina.style.setProperty("--pagina-hero-x", "12%");
        heroPagina.style.setProperty("--pagina-hero-y", "50%");
    });
}