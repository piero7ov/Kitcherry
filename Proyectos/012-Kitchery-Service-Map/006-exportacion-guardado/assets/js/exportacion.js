// ==========================================================
// KITCHERRY SERVICE MAP
// Archivo: assets/js/exportacion.js
// Pantalla de exportación del plano guardado
// ==========================================================

document.addEventListener("DOMContentLoaded", function () {
    const STORAGE_KEY = "kitcherry_service_map_plano_importacion_entorno";

    const exportCanvas = document.getElementById("export-canvas");
    const ctx = exportCanvas.getContext("2d");

    const exportEstadoSidebar = document.getElementById("export-estado-sidebar");
    const exportAlerta = document.getElementById("export-alerta");

    const resumenNegocio = document.getElementById("resumen-negocio");
    const resumenFechaTurno = document.getElementById("resumen-fecha-turno");
    const resumenMesas = document.getElementById("resumen-mesas");
    const resumenReservas = document.getElementById("resumen-reservas");
    const resumenColocadas = document.getElementById("resumen-colocadas");
    const resumenPendientes = document.getElementById("resumen-pendientes");
    const resumenObjetos = document.getElementById("resumen-objetos");

    const exportReservasColocadas = document.getElementById("export-reservas-colocadas");
    const exportReservasPendientes = document.getElementById("export-reservas-pendientes");

    const btnExportarJson = document.getElementById("btn-exportar-json");
    const btnImportarPlano = document.getElementById("btn-importar-plano");
    const inputImportarPlano = document.getElementById("input-importar-plano");
    const btnExportarPng = document.getElementById("btn-exportar-png");
    const btnImprimir = document.getElementById("btn-imprimir");

    let datosPlano = null;
    let canvasWidth = 0;
    let canvasHeight = 0;

    cargarDatos();
    ajustarCanvas();
    renderizarPantalla();

    window.addEventListener("resize", function () {
        ajustarCanvas();
        dibujarPlano();
    });

    btnExportarJson.addEventListener("click", function () {
        exportarJson();
    });

    btnImportarPlano.addEventListener("click", function () {
        inputImportarPlano.click();
    });

    inputImportarPlano.addEventListener("change", function (evento) {
        const archivo = evento.target.files[0];

        if (!archivo) {
            return;
        }

        importarPlanoJson(archivo);
        inputImportarPlano.value = "";
    });

    btnExportarPng.addEventListener("click", function () {
        exportarPng();
    });

    btnImprimir.addEventListener("click", function () {
        imprimirVistaLimpia();
    });

    window.addEventListener("afterprint", function () {
        ajustarCanvas();
        dibujarPlano();
    });

    function cargarDatos() {
        const datosGuardados = localStorage.getItem(STORAGE_KEY);

        if (!datosGuardados) {
            datosPlano = null;
            return;
        }

        try {
            datosPlano = JSON.parse(datosGuardados);

            if (!Array.isArray(datosPlano.mesas)) {
                datosPlano.mesas = [];
            }

            if (!Array.isArray(datosPlano.objetosEntorno)) {
                datosPlano.objetosEntorno = [];
            }

            if (!Array.isArray(datosPlano.reservasImportadas)) {
                datosPlano.reservasImportadas = [];
            }
        } catch (error) {
            console.error("No se pudo leer el plano guardado:", error);
            datosPlano = null;
        }
    }

    function ajustarCanvas() {
        const wrapper = exportCanvas.parentElement;
        const rect = wrapper.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;

        canvasWidth = rect.width;
        canvasHeight = rect.height;

        exportCanvas.width = Math.round(canvasWidth * dpr);
        exportCanvas.height = Math.round(canvasHeight * dpr);

        exportCanvas.style.width = canvasWidth + "px";
        exportCanvas.style.height = canvasHeight + "px";

        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }

    function renderizarPantalla() {
        if (!datosPlano) {
            mostrarSinDatos();
            dibujarPlanoVacio();
            return;
        }

        const mesas = datosPlano.mesas || [];
        const objetos = datosPlano.objetosEntorno || [];
        const reservas = datosPlano.reservasImportadas || [];

        const colocadas = obtenerReservasColocadas();
        const pendientes = obtenerReservasPendientes();

        const servicio = datosPlano.servicioImportado || {};
        const negocio = servicio.negocio || {};

        resumenNegocio.textContent = negocio.nombre || "Sin negocio";
        resumenFechaTurno.textContent = obtenerTextoServicio();

        resumenMesas.textContent = mesas.length;
        resumenReservas.textContent = reservas.length;
        resumenColocadas.textContent = colocadas.length;
        resumenPendientes.textContent = pendientes.length;
        resumenObjetos.textContent = objetos.length;

        exportEstadoSidebar.className = "servicio-info-card sidebar-service-info";
        exportEstadoSidebar.innerHTML = `
            <strong>${escaparHTML(negocio.nombre || "Plano guardado")}</strong>
            <span>${escaparHTML(obtenerTextoServicio())}</span>
            <span>${mesas.length} mesas · ${reservas.length} reservas · ${objetos.length} objetos</span>
        `;

        exportAlerta.className = "servicio-info-card export-alerta";
        exportAlerta.innerHTML = `
            <strong>Plano cargado correctamente</strong>
            <span>Último guardado: ${escaparHTML(formatearFechaGuardado(datosPlano.guardado_en))}</span>
        `;

        activarBotonesExportacion(true);

        renderizarListasReservas();
        dibujarPlano();
    }

    function mostrarSinDatos() {
        resumenNegocio.textContent = "-";
        resumenFechaTurno.textContent = "No hay ningún plano guardado.";
        resumenMesas.textContent = "0";
        resumenReservas.textContent = "0";
        resumenColocadas.textContent = "0";
        resumenPendientes.textContent = "0";
        resumenObjetos.textContent = "0";

        exportEstadoSidebar.className = "empty-state sidebar-service-info";
        exportEstadoSidebar.innerHTML = "No hay un plano guardado todavía. Vuelve a la pantalla Plano y pulsa “Guardar plano”.";

        exportAlerta.className = "empty-state export-alerta";
        exportAlerta.innerHTML = "No hay datos para exportar. Puedes importar un plano JSON o volver al plano para crear uno.";

        exportReservasColocadas.innerHTML = `
            <div class="empty-state">
                No hay reservas colocadas.
            </div>
        `;

        exportReservasPendientes.innerHTML = `
            <div class="empty-state">
                No hay reservas pendientes.
            </div>
        `;

        activarBotonesExportacion(false);
    }

    function activarBotonesExportacion(activo) {
        btnExportarJson.disabled = !activo;
        btnExportarPng.disabled = !activo;
        btnImprimir.disabled = !activo;

        btnImportarPlano.disabled = false;
    }

    function obtenerTextoServicio() {
        if (!datosPlano || !datosPlano.servicioImportado) {
            return "Sin datos de servicio.";
        }

        const servicio = datosPlano.servicioImportado;

        const fecha = servicio.fecha_servicio_formateada || servicio.fecha_servicio || "Sin fecha";
        const turno = servicio.turno_texto || servicio.turno || "Sin turno";

        return "Servicio: " + fecha + " · " + turno;
    }

    function obtenerTextoFechaPlano() {
        if (!datosPlano || !datosPlano.servicioImportado) {
            return "Sin fecha";
        }

        const servicio = datosPlano.servicioImportado;

        const fecha = servicio.fecha_servicio_formateada ||
            servicio.fecha_servicio ||
            "Sin fecha";

        const turno = servicio.turno_texto ||
            servicio.turno ||
            "";

        if (turno !== "") {
            return fecha + " · " + turno;
        }

        return fecha;
    }

    function formatearFechaGuardado(fechaIso) {
        if (!fechaIso) {
            return "Sin fecha de guardado";
        }

        const fecha = new Date(fechaIso);

        if (Number.isNaN(fecha.getTime())) {
            return fechaIso;
        }

        return fecha.toLocaleString("es-ES");
    }

    function obtenerReservasColocadas() {
        if (!datosPlano || !Array.isArray(datosPlano.reservasImportadas)) {
            return [];
        }

        return datosPlano.reservasImportadas.filter(function (reserva) {
            return reserva.colocacion && reserva.colocacion.colocada === true;
        });
    }

    function obtenerReservasPendientes() {
        if (!datosPlano || !Array.isArray(datosPlano.reservasImportadas)) {
            return [];
        }

        return datosPlano.reservasImportadas.filter(function (reserva) {
            return !reserva.colocacion || reserva.colocacion.colocada !== true;
        });
    }

    function renderizarListasReservas() {
        const colocadas = obtenerReservasColocadas();
        const pendientes = obtenerReservasPendientes();

        if (colocadas.length === 0) {
            exportReservasColocadas.innerHTML = `
                <div class="empty-state">
                    No hay reservas colocadas en el plano.
                </div>
            `;
        } else {
            exportReservasColocadas.innerHTML = colocadas.map(function (reserva) {
                return crearHtmlReserva(reserva, "colocada");
            }).join("");
        }

        if (pendientes.length === 0) {
            exportReservasPendientes.innerHTML = `
                <div class="empty-state">
                    No hay reservas pendientes.
                </div>
            `;
        } else {
            exportReservasPendientes.innerHTML = pendientes.map(function (reserva) {
                return crearHtmlReserva(reserva, "pendiente");
            }).join("");
        }
    }

    function crearHtmlReserva(reserva, tipo) {
        const mesa = obtenerMesaPorId(reserva.colocacion ? reserva.colocacion.mesa_canvas_id : null);

        let mesaTexto = "Sin mesa colocada";

        if (mesa) {
            mesaTexto = "Colocada en " + mesa.nombre + " · " + mesa.zona;
        } else if (reserva.mesa_nombre) {
            mesaTexto = "Mesa original: " + reserva.mesa_nombre;
        }

        const alertasTotal = reserva.alertas && reserva.alertas.total
            ? parseInt(reserva.alertas.total, 10)
            : 0;

        let pills = `
            <span>${escaparHTML(reserva.estado_reserva_texto || "Reserva")}</span>
            <span>${parseInt(reserva.personas, 10) || 0} pax</span>
        `;

        if (reserva.banderas && reserva.banderas.tiene_alergias) {
            pills += `<span class="alergia">Alergias</span>`;
        }

        if (alertasTotal > 0) {
            pills += `<span class="alerta">${alertasTotal} alerta</span>`;
        }

        if (reserva.colocacion && reserva.colocacion.asignacion_manual === true) {
            pills += `<span>Asignación manual</span>`;
        }

        if (reservaEstaEnMesaDoblada(reserva)) {
            pills += `<span class="doblada">Mesa doblada</span>`;
        }

        return `
            <article class="export-reserva-card ${tipo}">
                <strong>${escaparHTML(reserva.hora || "--:--")} · ${escaparHTML(reserva.cliente || "Cliente sin nombre")}</strong>
                <p>${escaparHTML(mesaTexto)}</p>
                <p>Tel: ${escaparHTML(reserva.telefono || "Sin teléfono")}</p>

                <div class="export-mini-pills">
                    ${pills}
                </div>
            </article>
        `;
    }

    function reservaEstaEnMesaDoblada(reserva) {
        if (!datosPlano || !Array.isArray(datosPlano.reservasImportadas)) {
            return false;
        }

        if (!reserva.colocacion || !reserva.colocacion.mesa_canvas_id) {
            return false;
        }

        const mesaId = parseInt(reserva.colocacion.mesa_canvas_id, 10);
        const mesa = obtenerMesaPorId(mesaId);

        if (mesa && mesa.doblada === true) {
            return true;
        }

        const reservasMismaMesa = datosPlano.reservasImportadas.filter(function (item) {
            return item.colocacion &&
                item.colocacion.colocada === true &&
                parseInt(item.colocacion.mesa_canvas_id, 10) === mesaId;
        });

        return reservasMismaMesa.length > 1;
    }

    function obtenerMesaPorId(id) {
        if (!datosPlano || !Array.isArray(datosPlano.mesas)) {
            return null;
        }

        const idNumerico = parseInt(id, 10);

        return datosPlano.mesas.find(function (mesa) {
            return parseInt(mesa.id, 10) === idNumerico;
        }) || null;
    }

    function dibujarPlanoVacio() {
        limpiarCanvas();

        ctx.save();

        dibujarFondo();

        ctx.fillStyle = "#555555";
        ctx.font = "bold 18px Arial";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText("No hay plano guardado", canvasWidth / 2, canvasHeight / 2 - 12);

        ctx.font = "14px Arial";
        ctx.fillText("Vuelve al plano, guarda el trabajo o importa un JSON.", canvasWidth / 2, canvasHeight / 2 + 18);

        ctx.restore();
    }

    function dibujarPlano() {
        if (!datosPlano) {
            dibujarPlanoVacio();
            return;
        }

        limpiarCanvas();
        dibujarFondo();

        const bounds = calcularBoundsPlano();

        ctx.save();

        const escala = calcularEscala(bounds);
        const offset = calcularOffset(bounds, escala);

        ctx.translate(offset.x, offset.y);
        ctx.scale(escala, escala);

        const objetos = datosPlano.objetosEntorno || [];
        const mesas = datosPlano.mesas || [];

        objetos.forEach(function (objeto) {
            dibujarObjeto(objeto);
        });

        mesas.forEach(function (mesa) {
            const reservas = obtenerReservasDeMesa(mesa.id);
            dibujarMesa(mesa, reservas);
        });

        ctx.restore();

        dibujarCabeceraPlano();
    }

    function limpiarCanvas() {
        ctx.clearRect(0, 0, canvasWidth, canvasHeight);
    }

    function dibujarFondo() {
        ctx.save();

        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, canvasWidth, canvasHeight);

        ctx.strokeStyle = "rgba(194, 24, 43, 0.05)";
        ctx.lineWidth = 1;

        const separacion = 32;

        for (let x = 0; x <= canvasWidth; x += separacion) {
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x, canvasHeight);
            ctx.stroke();
        }

        for (let y = 0; y <= canvasHeight; y += separacion) {
            ctx.beginPath();
            ctx.moveTo(0, y);
            ctx.lineTo(canvasWidth, y);
            ctx.stroke();
        }

        ctx.restore();
    }

    function dibujarCabeceraPlano() {
        if (!datosPlano) {
            return;
        }

        const texto = obtenerTextoFechaPlano();

        ctx.save();

        ctx.fillStyle = "rgba(255, 255, 255, 0.88)";
        ctx.strokeStyle = "rgba(194, 24, 43, 0.16)";
        ctx.lineWidth = 1;

        const anchoCaja = 210;
        const altoCaja = 34;
        const x = canvasWidth - anchoCaja - 18;
        const y = 18;

        dibujarRectRedondeado(x, y, anchoCaja, altoCaja, 12);
        ctx.fill();
        ctx.stroke();

        ctx.fillStyle = "#555555";
        ctx.font = "bold 12px Arial";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText(texto, x + anchoCaja / 2, y + altoCaja / 2);

        ctx.restore();
    }

    function calcularBoundsPlano() {
        const mesas = datosPlano.mesas || [];
        const objetos = datosPlano.objetosEntorno || [];

        const elementos = mesas.concat(objetos);

        if (elementos.length === 0) {
            return {
                minX: 0,
                minY: 0,
                maxX: 800,
                maxY: 500,
                width: 800,
                height: 500
            };
        }

        let minX = Infinity;
        let minY = Infinity;
        let maxX = -Infinity;
        let maxY = -Infinity;

        elementos.forEach(function (elemento) {
            const extension = obtenerExtensionElemento(elemento);

            minX = Math.min(minX, elemento.x - extension.x);
            minY = Math.min(minY, elemento.y - extension.y);
            maxX = Math.max(maxX, elemento.x + extension.x);
            maxY = Math.max(maxY, elemento.y + extension.y);
        });

        const margen = 80;

        minX -= margen;
        minY -= margen;
        maxX += margen;
        maxY += margen;

        return {
            minX: minX,
            minY: minY,
            maxX: maxX,
            maxY: maxY,
            width: maxX - minX,
            height: maxY - minY
        };
    }

    function calcularBoundsPlanoAjustado(margen) {
        const mesas = datosPlano.mesas || [];
        const objetos = datosPlano.objetosEntorno || [];

        const elementos = mesas.concat(objetos);

        if (elementos.length === 0) {
            return {
                minX: 0,
                minY: 0,
                maxX: 800,
                maxY: 500,
                width: 800,
                height: 500
            };
        }

        let minX = Infinity;
        let minY = Infinity;
        let maxX = -Infinity;
        let maxY = -Infinity;

        elementos.forEach(function (elemento) {
            const extension = obtenerExtensionElemento(elemento);

            minX = Math.min(minX, elemento.x - extension.x);
            minY = Math.min(minY, elemento.y - extension.y);
            maxX = Math.max(maxX, elemento.x + extension.x);
            maxY = Math.max(maxY, elemento.y + extension.y);
        });

        minX -= margen;
        minY -= margen;
        maxX += margen;
        maxY += margen;

        return {
            minX: minX,
            minY: minY,
            maxX: maxX,
            maxY: maxY,
            width: maxX - minX,
            height: maxY - minY
        };
    }

    function obtenerExtensionElemento(elemento) {
        let extensionX = (elemento.ancho || 80) / 2;
        let extensionY = (elemento.alto || 80) / 2;

        if (elemento.rotacion !== undefined) {
            const angulo = gradosARadianes(elemento.rotacion || 0);
            const coseno = Math.abs(Math.cos(angulo));
            const seno = Math.abs(Math.sin(angulo));

            extensionX = ((elemento.ancho || 80) / 2) * coseno + ((elemento.alto || 80) / 2) * seno;
            extensionY = ((elemento.ancho || 80) / 2) * seno + ((elemento.alto || 80) / 2) * coseno;
        }

        return {
            x: extensionX,
            y: extensionY
        };
    }

    function calcularEscala(bounds) {
        const espacioX = canvasWidth - 50;
        const espacioY = canvasHeight - 90;

        const escalaX = espacioX / bounds.width;
        const escalaY = espacioY / bounds.height;

        return Math.min(1.25, Math.max(0.2, Math.min(escalaX, escalaY)));
    }

    function calcularOffset(bounds, escala) {
        const anchoEscalado = bounds.width * escala;
        const altoEscalado = bounds.height * escala;

        return {
            x: (canvasWidth - anchoEscalado) / 2 - bounds.minX * escala,
            y: 60 + (canvasHeight - 75 - altoEscalado) / 2 - bounds.minY * escala
        };
    }

    function dibujarPlanoExportacionAjustado(bounds) {
        limpiarCanvas();
        dibujarFondo();

        ctx.save();

        const escalaX = canvasWidth / bounds.width;
        const escalaY = canvasHeight / bounds.height;
        const escala = Math.min(escalaX, escalaY);

        const anchoEscalado = bounds.width * escala;
        const altoEscalado = bounds.height * escala;

        const offsetX = (canvasWidth - anchoEscalado) / 2 - bounds.minX * escala;
        const offsetY = (canvasHeight - altoEscalado) / 2 - bounds.minY * escala;

        ctx.translate(offsetX, offsetY);
        ctx.scale(escala, escala);

        const objetos = datosPlano.objetosEntorno || [];
        const mesas = datosPlano.mesas || [];

        objetos.forEach(function (objeto) {
            dibujarObjeto(objeto);
        });

        mesas.forEach(function (mesa) {
            const reservas = obtenerReservasDeMesa(mesa.id);
            dibujarMesa(mesa, reservas);
        });

        ctx.restore();

        dibujarCabeceraPlano();
    }

    function dibujarObjeto(objeto) {
        ctx.save();

        ctx.translate(objeto.x, objeto.y);
        ctx.rotate(gradosARadianes(objeto.rotacion || 0));
        ctx.globalAlpha = Math.max(0.2, Math.min(1, (objeto.opacidad || 100) / 100));

        ctx.fillStyle = objeto.color || "#6d28d9";
        ctx.strokeStyle = oscurecerColor(objeto.color || "#6d28d9");
        ctx.lineWidth = 2;

        const x = -(objeto.ancho || 120) / 2;
        const y = -(objeto.alto || 40) / 2;
        const radio = objeto.tipo === "paraban" ? 8 : 14;

        dibujarRectRedondeado(x, y, objeto.ancho || 120, objeto.alto || 40, radio);
        ctx.fill();
        ctx.stroke();

        const texto = String(objeto.nombre || "").toUpperCase();

        if (texto !== "" && !(objeto.tipo === "columna" && objeto.ancho < 58)) {
            ctx.fillStyle = colorTextoSobreFondo(objeto.color || "#6d28d9");
            ctx.font = objeto.tipo === "paraban" ? "bold 10px Arial" : "bold 13px Arial";
            ctx.textAlign = "center";
            ctx.textBaseline = "middle";
            ctx.fillText(acortarTexto(texto, 18), 0, 0);
        }

        ctx.restore();
    }

    function dibujarMesa(mesa, reservas) {
        const estadoVisual = obtenerEstadoVisualMesa(mesa, reservas);
        const colores = obtenerColoresEstado(estadoVisual);

        ctx.save();

        ctx.fillStyle = colores.fondo;
        ctx.strokeStyle = colores.borde;
        ctx.lineWidth = 2;

        if (mesa.tipo === "redonda") {
            const radio = (mesa.ancho || 80) / 2;

            ctx.beginPath();
            ctx.arc(mesa.x, mesa.y, radio, 0, Math.PI * 2);
            ctx.closePath();
            ctx.fill();
            ctx.stroke();
        } else {
            const ancho = mesa.ancho || 100;
            const alto = mesa.alto || 80;

            dibujarRectRedondeado(mesa.x - ancho / 2, mesa.y - alto / 2, ancho, alto, 14);
            ctx.fill();
            ctx.stroke();
        }

        ctx.fillStyle = "#171717";
        ctx.font = "bold 14px Arial";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText(mesa.nombre || "Mesa", mesa.x, mesa.y - 15);

        ctx.fillStyle = "#555555";
        ctx.font = "bold 12px Arial";
        ctx.fillText((mesa.capacidad || 0) + " pax", mesa.x, mesa.y + 1);

        ctx.fillStyle = colores.texto;
        ctx.font = "bold 11px Arial";
        ctx.fillText(textoEstado(estadoVisual), mesa.x, mesa.y + 17);

        if (reservas.length > 0) {
            dibujarEtiquetaReservaMesa(mesa, reservas);
        }

        if (mesa.doblada === true || reservas.length > 1) {
            dibujarEtiquetaDoblada(mesa);
        }

        ctx.restore();
    }

    function dibujarEtiquetaReservaMesa(mesa, reservas) {
        const total = reservas.length;
        const primera = reservas[0];

        const texto = total === 1
            ? primera.hora + " · " + acortarTexto(primera.cliente, 14)
            : total + " reservas";

        const anchoEtiqueta = Math.min(Math.max(texto.length * 7, 86), (mesa.ancho || 100) + 30);
        const altoEtiqueta = 20;

        const x = mesa.x - anchoEtiqueta / 2;
        const y = mesa.y - (mesa.alto || 80) / 2 + 6;

        ctx.save();

        ctx.fillStyle = "#ffffff";
        ctx.strokeStyle = "rgba(194, 24, 43, 0.28)";
        ctx.lineWidth = 1.5;

        dibujarRectRedondeado(x, y, anchoEtiqueta, altoEtiqueta, 10);
        ctx.fill();
        ctx.stroke();

        ctx.fillStyle = "#C2182B";
        ctx.font = "bold 10px Arial";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText(texto, mesa.x, y + altoEtiqueta / 2);

        ctx.restore();
    }

    function dibujarEtiquetaDoblada(mesa) {
        const texto = "Doblada";
        const anchoEtiqueta = 70;
        const altoEtiqueta = 20;

        const x = mesa.x - anchoEtiqueta / 2;
        const y = mesa.y + (mesa.alto || 80) / 2 - altoEtiqueta - 6;

        ctx.save();

        ctx.fillStyle = "#eef2ff";
        ctx.strokeStyle = "#4f46e5";
        ctx.lineWidth = 1.5;

        dibujarRectRedondeado(x, y, anchoEtiqueta, altoEtiqueta, 10);
        ctx.fill();
        ctx.stroke();

        ctx.fillStyle = "#3730a3";
        ctx.font = "bold 10px Arial";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText(texto, mesa.x, y + altoEtiqueta / 2);

        ctx.restore();
    }

    function obtenerReservasDeMesa(mesaId) {
        if (!datosPlano || !Array.isArray(datosPlano.reservasImportadas)) {
            return [];
        }

        return datosPlano.reservasImportadas
            .filter(function (reserva) {
                return reserva.colocacion && parseInt(reserva.colocacion.mesa_canvas_id, 10) === parseInt(mesaId, 10);
            })
            .sort(function (a, b) {
                return String(a.hora).localeCompare(String(b.hora));
            });
    }

    function obtenerEstadoVisualMesa(mesa, reservas) {
        if (mesa.estado === "libre" && reservas.length > 0) {
            return "reservada";
        }

        return mesa.estado || "libre";
    }

    function obtenerColoresEstado(estado) {
        const colores = {
            libre: {
                fondo: "#f0fbf5",
                borde: "#1f9d55",
                texto: "#1f9d55"
            },
            reservada: {
                fondo: "#fff9e8",
                borde: "#f4b400",
                texto: "#9a6a00"
            },
            ocupada: {
                fondo: "#fbeaec",
                borde: "#C2182B",
                texto: "#991726"
            },
            bloqueada: {
                fondo: "#f2f2f2",
                borde: "#171717",
                texto: "#171717"
            }
        };

        return colores[estado] || colores.libre;
    }

    function textoEstado(estado) {
        const textos = {
            libre: "Libre",
            reservada: "Reservada",
            ocupada: "Ocupada",
            bloqueada: "Bloqueada"
        };

        return textos[estado] || "Libre";
    }

    function exportarJson() {
        if (!datosPlano) {
            alert("No hay plano para exportar.");
            return;
        }

        const nombreArchivo = generarNombreArchivo("json");
        const contenido = JSON.stringify(datosPlano, null, 4);
        const blob = new Blob([contenido], { type: "application/json" });

        descargarBlob(blob, nombreArchivo);
    }

    function importarPlanoJson(archivo) {
        const lector = new FileReader();

        lector.onload = function (evento) {
            try {
                const datos = JSON.parse(evento.target.result);

                if (!datos || !Array.isArray(datos.mesas)) {
                    alert("El archivo no parece ser un plano válido de Kitcherry Service Map.");
                    return;
                }

                localStorage.setItem(STORAGE_KEY, JSON.stringify(datos));

                datosPlano = datos;

                renderizarPantalla();

                alert("Plano importado correctamente.");
            } catch (error) {
                alert("No se pudo importar el plano. Revisa que sea un JSON válido.");
            }
        };

        lector.readAsText(archivo, "UTF-8");
    }

    function exportarPng() {
        if (!datosPlano) {
            alert("No hay plano para exportar como imagen.");
            return;
        }

        const anchoOriginal = canvasWidth;
        const altoOriginal = canvasHeight;

        const margenExportacion = 45;
        const boundsExportacion = calcularBoundsPlanoAjustado(margenExportacion);

        const maxAncho = 2600;
        const maxAlto = 1800;

        let escalaExportacion = Math.min(
            maxAncho / boundsExportacion.width,
            maxAlto / boundsExportacion.height
        );

        escalaExportacion = Math.max(1.5, Math.min(4, escalaExportacion));

        const anchoExportacion = Math.round(boundsExportacion.width * escalaExportacion);
        const altoExportacion = Math.round(boundsExportacion.height * escalaExportacion);

        exportCanvas.width = anchoExportacion;
        exportCanvas.height = altoExportacion;

        exportCanvas.style.width = anchoOriginal + "px";
        exportCanvas.style.height = altoOriginal + "px";

        canvasWidth = anchoExportacion;
        canvasHeight = altoExportacion;

        ctx.setTransform(1, 0, 0, 1, 0, 0);

        dibujarPlanoExportacionAjustado(boundsExportacion);

        const enlace = document.createElement("a");
        enlace.download = generarNombreArchivo("png");
        enlace.href = exportCanvas.toDataURL("image/png", 1.0);
        enlace.click();

        canvasWidth = anchoOriginal;
        canvasHeight = altoOriginal;

        ajustarCanvas();
        dibujarPlano();
    }

    function imprimirVistaLimpia() {
        if (!datosPlano) {
            alert("No hay plano para imprimir.");
            return;
        }

        const imagenPlano = generarImagenPlanoParaImpresion();
        const areaImpresion = prepararAreaImpresion(imagenPlano);

        const imagen = areaImpresion.querySelector(".print-plano-img-limpia");

        if (imagen && !imagen.complete) {
            imagen.onload = function () {
                setTimeout(function () {
                    window.print();
                }, 150);
            };

            return;
        }

        setTimeout(function () {
            window.print();
        }, 150);
    }

    function generarImagenPlanoParaImpresion() {
        const anchoOriginal = canvasWidth;
        const altoOriginal = canvasHeight;

        const margenImpresion = 35;
        const boundsImpresion = calcularBoundsPlanoAjustado(margenImpresion);

        const maxAncho = 2600;
        const maxAlto = 1500;

        let escalaImpresion = Math.min(
            maxAncho / boundsImpresion.width,
            maxAlto / boundsImpresion.height
        );

        escalaImpresion = Math.max(1.5, Math.min(4, escalaImpresion));

        const anchoImpresion = Math.round(boundsImpresion.width * escalaImpresion);
        const altoImpresion = Math.round(boundsImpresion.height * escalaImpresion);

        canvasWidth = anchoImpresion;
        canvasHeight = altoImpresion;

        exportCanvas.width = anchoImpresion;
        exportCanvas.height = altoImpresion;

        ctx.setTransform(1, 0, 0, 1, 0, 0);

        dibujarPlanoExportacionAjustado(boundsImpresion);

        const imagen = exportCanvas.toDataURL("image/png", 1.0);

        canvasWidth = anchoOriginal;
        canvasHeight = altoOriginal;

        ajustarCanvas();
        dibujarPlano();

        return imagen;
    }

    function prepararAreaImpresion(imagenPlano) {
        let areaImpresion = document.getElementById("print-area");

        if (!areaImpresion) {
            areaImpresion = document.createElement("section");
            areaImpresion.id = "print-area";
            areaImpresion.className = "print-area";
            document.body.appendChild(areaImpresion);
        }

        const servicio = datosPlano.servicioImportado || {};
        const negocio = servicio.negocio || {};

        const nombreNegocio = negocio.nombre || "Kitcherry Service Map";
        const fecha = servicio.fecha_servicio_formateada || servicio.fecha_servicio || "Sin fecha";
        const turno = servicio.turno_texto || servicio.turno || "Sin turno";

        const colocadas = obtenerReservasColocadas();
        const pendientes = obtenerReservasPendientes();
        const mesasDobladas = obtenerMesasDobladasParaImpresion();

        areaImpresion.innerHTML = `
            <article class="print-page print-page-plano">
                <div class="print-page-header">
                    <div>
                        <h1>Plano de sala</h1>
                        <p>${escaparHTML(nombreNegocio)} · ${escaparHTML(fecha)} · ${escaparHTML(turno)}</p>
                    </div>
                </div>

                <div class="print-plano-box">
                    <img 
                        src="${imagenPlano}" 
                        alt="Plano de sala" 
                        class="print-plano-img-limpia"
                    >
                </div>
            </article>

            <article class="print-page print-page-reservas">
                <div class="print-page-header">
                    <div>
                        <h1>Reservas colocadas</h1>
                        <p>${colocadas.length} reservas colocadas en el plano.</p>
                    </div>
                </div>

                <div class="print-reservas-grid">
                    ${crearHtmlReservasImpresion(colocadas)}
                </div>

                <div class="print-bloque-secundario">
                    <h2>Reservas pendientes</h2>
                    ${crearHtmlReservasPendientesImpresion(pendientes)}
                </div>

                <div class="print-bloque-secundario print-bloque-dobladas">
                    <h2>Mesas dobladas</h2>
                    <p class="print-bloque-descripcion">
                        Información estática de mesas con más de una reserva asociada.
                    </p>

                    ${crearHtmlMesasDobladasImpresion(mesasDobladas)}
                </div>
            </article>
        `;

        return areaImpresion;
    }

    function crearHtmlReservasImpresion(reservas) {
        if (!reservas || reservas.length === 0) {
            return `
                <div class="print-empty">
                    No hay reservas colocadas.
                </div>
            `;
        }

        return reservas.map(function (reserva) {
            const mesa = obtenerMesaPorId(reserva.colocacion ? reserva.colocacion.mesa_canvas_id : null);

            let mesaTexto = "Sin mesa";

            if (mesa) {
                mesaTexto = mesa.nombre + " · " + mesa.zona;
            } else if (reserva.mesa_nombre) {
                mesaTexto = reserva.mesa_nombre;
            }

            const claseDoblada = reservaEstaEnMesaDoblada(reserva) ? " print-reserva-doblada" : "";

            return `
                <article class="print-reserva-card${claseDoblada}">
                    <strong>${escaparHTML(reserva.hora || "--:--")} · ${escaparHTML(reserva.cliente || "Cliente sin nombre")}</strong>

                    <p>
                        ${parseInt(reserva.personas, 10) || 0} personas ·
                        ${escaparHTML(reserva.estado_reserva_texto || "Reserva")}
                    </p>

                    <p>
                        Mesa: ${escaparHTML(mesaTexto)}
                    </p>

                    <p>
                        Tel: ${escaparHTML(reserva.telefono || "Sin teléfono")}
                    </p>

                    ${reservaEstaEnMesaDoblada(reserva) ? `<span class="print-pill-doblada">Mesa doblada</span>` : ""}
                </article>
            `;
        }).join("");
    }

    function crearHtmlReservasPendientesImpresion(reservas) {
        if (!reservas || reservas.length === 0) {
            return `
                <div class="print-empty">
                    No hay reservas pendientes.
                </div>
            `;
        }

        return `
            <div class="print-reservas-grid print-reservas-pendientes-grid">
                ${reservas.map(function (reserva) {
            return `
                        <article class="print-reserva-card print-reserva-pendiente">
                            <strong>${escaparHTML(reserva.hora || "--:--")} · ${escaparHTML(reserva.cliente || "Cliente sin nombre")}</strong>

                            <p>
                                ${parseInt(reserva.personas, 10) || 0} personas ·
                                ${escaparHTML(reserva.estado_reserva_texto || "Reserva")}
                            </p>

                            <p>
                                Tel: ${escaparHTML(reserva.telefono || "Sin teléfono")}
                            </p>
                        </article>
                    `;
        }).join("")}
            </div>
        `;
    }

    function obtenerMesasDobladasParaImpresion() {
        const mesas = datosPlano.mesas || [];

        return mesas.map(function (mesa) {
            const reservasMesa = obtenerReservasDeMesa(mesa.id);

            return {
                mesa: mesa,
                reservas: reservasMesa
            };
        }).filter(function (item) {
            return item.reservas.length > 1 || item.mesa.doblada === true;
        });
    }

    function crearHtmlMesasDobladasImpresion(mesasDobladas) {
        if (!mesasDobladas || mesasDobladas.length === 0) {
            return `
                <div class="print-empty">
                    No hay mesas dobladas en este plano.
                </div>
            `;
        }

        return `
            <div class="print-dobladas-list">
                ${mesasDobladas.map(function (item) {
            const mesa = item.mesa;
            const reservas = item.reservas;

            return `
                        <article class="print-doblada-card">
                            <h2>${escaparHTML(mesa.nombre || "Mesa")} · ${escaparHTML(mesa.zona || "Sin zona")}</h2>

                            <p class="print-doblada-resumen">
                                Mesa doblada: tiene ${reservas.length} reservas asociadas.
                            </p>

                            <div class="print-doblada-reservas">
                                ${reservas.map(function (reserva, index) {
                return `
                                        <div class="print-doblada-reserva">
                                            <strong>Reserva ${index + 1}: ${escaparHTML(reserva.hora || "--:--")} · ${escaparHTML(reserva.cliente || "Cliente sin nombre")}</strong>

                                            <p>
                                                ${parseInt(reserva.personas, 10) || 0} personas ·
                                                ${escaparHTML(reserva.estado_reserva_texto || "Reserva")}
                                            </p>

                                            <p>
                                                Tel: ${escaparHTML(reserva.telefono || "Sin teléfono")}
                                            </p>
                                        </div>
                                    `;
            }).join("")}
                            </div>
                        </article>
                    `;
        }).join("")}
            </div>
        `;
    }

    function generarNombreArchivo(extension) {
        let fecha = "sin-fecha";

        if (datosPlano && datosPlano.servicioImportado) {
            fecha = datosPlano.servicioImportado.fecha_servicio ||
                datosPlano.servicioImportado.fecha_servicio_formateada ||
                fecha;
        }

        fecha = String(fecha)
            .replaceAll("/", "-")
            .replaceAll(":", "-")
            .replaceAll(" ", "-");

        return "kitcherry-service-map-" + fecha + "." + extension;
    }

    function descargarBlob(blob, nombreArchivo) {
        const url = URL.createObjectURL(blob);
        const enlace = document.createElement("a");

        enlace.href = url;
        enlace.download = nombreArchivo;
        enlace.click();

        URL.revokeObjectURL(url);
    }

    function dibujarRectRedondeado(x, y, ancho, alto, radio) {
        ctx.beginPath();
        ctx.moveTo(x + radio, y);
        ctx.lineTo(x + ancho - radio, y);
        ctx.quadraticCurveTo(x + ancho, y, x + ancho, y + radio);
        ctx.lineTo(x + ancho, y + alto - radio);
        ctx.quadraticCurveTo(x + ancho, y + alto, x + ancho - radio, y + alto);
        ctx.lineTo(x + radio, y + alto);
        ctx.quadraticCurveTo(x, y + alto, x, y + alto - radio);
        ctx.lineTo(x, y + radio);
        ctx.quadraticCurveTo(x, y, x + radio, y);
        ctx.closePath();
    }

    function gradosARadianes(grados) {
        return grados * Math.PI / 180;
    }

    function acortarTexto(texto, maximo) {
        const valor = String(texto || "");

        if (valor.length <= maximo) {
            return valor;
        }

        return valor.substring(0, maximo - 1) + "…";
    }

    function escaparHTML(texto) {
        return String(texto ?? "")
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    function oscurecerColor(hex) {
        const color = normalizarHex(hex);

        const r = Math.max(0, parseInt(color.substring(1, 3), 16) - 45);
        const g = Math.max(0, parseInt(color.substring(3, 5), 16) - 45);
        const b = Math.max(0, parseInt(color.substring(5, 7), 16) - 45);

        return rgbAHex(r, g, b);
    }

    function colorTextoSobreFondo(hex) {
        const color = normalizarHex(hex);

        const r = parseInt(color.substring(1, 3), 16);
        const g = parseInt(color.substring(3, 5), 16);
        const b = parseInt(color.substring(5, 7), 16);

        const brillo = (r * 299 + g * 587 + b * 114) / 1000;

        return brillo > 150 ? "#171717" : "#ffffff";
    }

    function normalizarHex(hex) {
        const valor = String(hex || "#6d28d9").trim();

        if (/^#[0-9A-Fa-f]{6}$/.test(valor)) {
            return valor;
        }

        return "#6d28d9";
    }

    function rgbAHex(r, g, b) {
        return "#" + [r, g, b].map(function (valor) {
            const hex = valor.toString(16);
            return hex.length === 1 ? "0" + hex : hex;
        }).join("");
    }
});