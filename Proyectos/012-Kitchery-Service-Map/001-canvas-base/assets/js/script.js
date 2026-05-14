// ==========================================================
// KITCHERRY SERVICE MAP
// Archivo: assets/js/script.js
// Crear, seleccionar, mover, eliminar y guardar mesas
// ==========================================================

document.addEventListener("DOMContentLoaded", function () {
    const canvas = document.getElementById("service-map-canvas");
    const ctx = canvas.getContext("2d");

    const btnMesaRectangular = document.getElementById("btn-mesa-rectangular");
    const btnMesaRedonda = document.getElementById("btn-mesa-redonda");
    const btnGuardar = document.getElementById("btn-guardar");
    const btnEliminar = document.getElementById("btn-eliminar");
    const btnLimpiar = document.getElementById("btn-limpiar");

    const contadorMesas = document.getElementById("contador-mesas");
    const detalleMesa = document.getElementById("detalle-mesa");

    const STORAGE_KEY = "kitcherry_service_map_plano_base";

    let mesas = [];
    let mesaSeleccionadaId = null;

    let arrastrando = false;
    let offsetX = 0;
    let offsetY = 0;

    let canvasWidth = 0;
    let canvasHeight = 0;

    ajustarCanvas();
    cargarPlano();
    dibujar();
    actualizarPanelDetalle();

    window.addEventListener("resize", function () {
        ajustarCanvas();
        dibujar();
    });

    btnMesaRectangular.addEventListener("click", function () {
        crearMesa("rectangular");
    });

    btnMesaRedonda.addEventListener("click", function () {
        crearMesa("redonda");
    });

    btnGuardar.addEventListener("click", function () {
        guardarPlano();
        alert("Plano guardado correctamente.");
    });

    btnEliminar.addEventListener("click", function () {
        eliminarMesaSeleccionada();
    });

    btnLimpiar.addEventListener("click", function () {
        const confirmar = confirm("¿Seguro que quieres limpiar todo el plano?");

        if (!confirmar) {
            return;
        }

        mesas = [];
        mesaSeleccionadaId = null;
        guardarPlano();
        dibujar();
        actualizarPanelDetalle();
    });

    canvas.addEventListener("mousedown", function (evento) {
        const posicion = obtenerPosicionMouse(evento);
        const mesa = obtenerMesaEnPosicion(posicion.x, posicion.y);

        if (mesa) {
            mesaSeleccionadaId = mesa.id;
            arrastrando = true;

            offsetX = posicion.x - mesa.x;
            offsetY = posicion.y - mesa.y;

            canvas.style.cursor = "grabbing";
        } else {
            mesaSeleccionadaId = null;
            arrastrando = false;
            canvas.style.cursor = "default";
        }

        dibujar();
        actualizarPanelDetalle();
    });

    canvas.addEventListener("mousemove", function (evento) {
        const posicion = obtenerPosicionMouse(evento);

        if (arrastrando && mesaSeleccionadaId !== null) {
            const mesa = obtenerMesaPorId(mesaSeleccionadaId);

            if (!mesa) {
                return;
            }

            mesa.x = posicion.x - offsetX;
            mesa.y = posicion.y - offsetY;

            limitarMesaDentroCanvas(mesa);

            dibujar();
            actualizarPanelDetalle();
            return;
        }

        const mesaHover = obtenerMesaEnPosicion(posicion.x, posicion.y);
        canvas.style.cursor = mesaHover ? "grab" : "default";
    });

    canvas.addEventListener("mouseup", function () {
        if (arrastrando) {
            guardarPlano();
        }

        arrastrando = false;
        canvas.style.cursor = "default";
    });

    canvas.addEventListener("mouseleave", function () {
        if (arrastrando) {
            guardarPlano();
        }

        arrastrando = false;
        canvas.style.cursor = "default";
    });

    function ajustarCanvas() {
        const rect = canvas.parentElement.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;

        canvasWidth = rect.width;
        canvasHeight = rect.height;

        canvas.width = Math.round(canvasWidth * dpr);
        canvas.height = Math.round(canvasHeight * dpr);

        canvas.style.width = canvasWidth + "px";
        canvas.style.height = canvasHeight + "px";

        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }

    function crearMesa(tipo) {
        const nuevoId = obtenerSiguienteId();

        const mesa = {
            id: nuevoId,
            nombre: "Mesa " + nuevoId,
            tipo: tipo,
            x: 90 + (mesas.length * 34) % 420,
            y: 90 + (mesas.length * 28) % 260,
            ancho: tipo === "rectangular" ? 120 : 86,
            alto: tipo === "rectangular" ? 76 : 86,
            capacidad: tipo === "rectangular" ? 4 : 2
        };

        mesas.push(mesa);
        mesaSeleccionadaId = mesa.id;

        guardarPlano();
        dibujar();
        actualizarPanelDetalle();
    }

    function dibujar() {
        limpiarCanvas();
        dibujarFondoSuave();

        mesas.forEach(function (mesa) {
            dibujarMesa(mesa);
        });

        actualizarContador();
    }

    function limpiarCanvas() {
        ctx.clearRect(0, 0, canvasWidth, canvasHeight);
    }

    function dibujarFondoSuave() {
        ctx.save();

        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, canvasWidth, canvasHeight);

        ctx.strokeStyle = "rgba(194, 24, 43, 0.06)";
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

    function dibujarMesa(mesa) {
        const seleccionada = mesa.id === mesaSeleccionadaId;

        ctx.save();

        ctx.shadowColor = "rgba(0, 0, 0, 0.12)";
        ctx.shadowBlur = 12;
        ctx.shadowOffsetY = 5;

        ctx.fillStyle = seleccionada ? "#fbeaec" : "#ffffff";
        ctx.strokeStyle = seleccionada ? "#C2182B" : "#d8d8d8";
        ctx.lineWidth = seleccionada ? 3 : 2;

        if (mesa.tipo === "redonda") {
            dibujarMesaRedonda(mesa);
        } else {
            dibujarMesaRectangular(mesa);
        }

        ctx.shadowColor = "transparent";

        ctx.fillStyle = "#171717";
        ctx.font = "bold 14px Arial";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText(mesa.nombre, mesa.x, mesa.y - 8);

        ctx.fillStyle = "#555555";
        ctx.font = "bold 12px Arial";
        ctx.fillText(mesa.capacidad + " pax", mesa.x, mesa.y + 12);

        ctx.restore();
    }

    function dibujarMesaRectangular(mesa) {
        const x = mesa.x - mesa.ancho / 2;
        const y = mesa.y - mesa.alto / 2;
        const radio = 14;

        ctx.beginPath();
        ctx.moveTo(x + radio, y);
        ctx.lineTo(x + mesa.ancho - radio, y);
        ctx.quadraticCurveTo(x + mesa.ancho, y, x + mesa.ancho, y + radio);
        ctx.lineTo(x + mesa.ancho, y + mesa.alto - radio);
        ctx.quadraticCurveTo(x + mesa.ancho, y + mesa.alto, x + mesa.ancho - radio, y + mesa.alto);
        ctx.lineTo(x + radio, y + mesa.alto);
        ctx.quadraticCurveTo(x, y + mesa.alto, x, y + mesa.alto - radio);
        ctx.lineTo(x, y + radio);
        ctx.quadraticCurveTo(x, y, x + radio, y);
        ctx.closePath();

        ctx.fill();
        ctx.stroke();
    }

    function dibujarMesaRedonda(mesa) {
        const radio = mesa.ancho / 2;

        ctx.beginPath();
        ctx.arc(mesa.x, mesa.y, radio, 0, Math.PI * 2);
        ctx.closePath();

        ctx.fill();
        ctx.stroke();
    }

    function obtenerPosicionMouse(evento) {
        const rect = canvas.getBoundingClientRect();

        return {
            x: evento.clientX - rect.left,
            y: evento.clientY - rect.top
        };
    }

    function obtenerMesaEnPosicion(x, y) {
        for (let i = mesas.length - 1; i >= 0; i--) {
            const mesa = mesas[i];

            if (puntoDentroMesa(mesa, x, y)) {
                return mesa;
            }
        }

        return null;
    }

    function puntoDentroMesa(mesa, x, y) {
        if (mesa.tipo === "redonda") {
            const radio = mesa.ancho / 2;
            const distancia = Math.sqrt(
                Math.pow(x - mesa.x, 2) + Math.pow(y - mesa.y, 2)
            );

            return distancia <= radio;
        }

        const izquierda = mesa.x - mesa.ancho / 2;
        const derecha = mesa.x + mesa.ancho / 2;
        const arriba = mesa.y - mesa.alto / 2;
        const abajo = mesa.y + mesa.alto / 2;

        return x >= izquierda && x <= derecha && y >= arriba && y <= abajo;
    }

    function limitarMesaDentroCanvas(mesa) {
        const margen = 20;
        const medioAncho = mesa.ancho / 2;
        const medioAlto = mesa.alto / 2;

        mesa.x = Math.max(
            medioAncho + margen,
            Math.min(canvasWidth - medioAncho - margen, mesa.x)
        );

        mesa.y = Math.max(
            medioAlto + margen,
            Math.min(canvasHeight - medioAlto - margen, mesa.y)
        );
    }

    function eliminarMesaSeleccionada() {
        if (mesaSeleccionadaId === null) {
            alert("Primero selecciona una mesa.");
            return;
        }

        mesas = mesas.filter(function (mesa) {
            return mesa.id !== mesaSeleccionadaId;
        });

        mesaSeleccionadaId = null;

        guardarPlano();
        dibujar();
        actualizarPanelDetalle();
    }

    function guardarPlano() {
        const datos = {
            modulo: "Kitcherry Service Map",
            tipo: "plano_base",
            guardado_en: new Date().toISOString(),
            mesas: mesas
        };

        localStorage.setItem(STORAGE_KEY, JSON.stringify(datos));
    }

    function cargarPlano() {
        const datosGuardados = localStorage.getItem(STORAGE_KEY);

        if (!datosGuardados) {
            return;
        }

        try {
            const datos = JSON.parse(datosGuardados);

            if (Array.isArray(datos.mesas)) {
                mesas = datos.mesas;
            }
        } catch (error) {
            console.error("No se pudo cargar el plano guardado:", error);
            mesas = [];
        }
    }

    function obtenerMesaPorId(id) {
        return mesas.find(function (mesa) {
            return mesa.id === id;
        });
    }

    function obtenerSiguienteId() {
        if (mesas.length === 0) {
            return 1;
        }

        const ids = mesas.map(function (mesa) {
            return mesa.id;
        });

        return Math.max(...ids) + 1;
    }

    function actualizarContador() {
        const total = mesas.length;

        contadorMesas.textContent = total === 1
            ? "1 mesa"
            : total + " mesas";
    }

    function actualizarPanelDetalle() {
        const mesa = obtenerMesaPorId(mesaSeleccionadaId);

        if (!mesa) {
            detalleMesa.className = "empty-state";
            detalleMesa.innerHTML = "No hay ninguna mesa seleccionada.";
            return;
        }

        detalleMesa.className = "detail-list";
        detalleMesa.innerHTML = `
            <div class="detail-row">
                <span>ID</span>
                <strong>${mesa.id}</strong>
            </div>

            <div class="detail-row">
                <span>Nombre</span>
                <strong>${mesa.nombre}</strong>
            </div>

            <div class="detail-row">
                <span>Tipo</span>
                <strong>${mesa.tipo}</strong>
            </div>

            <div class="detail-row">
                <span>Capacidad</span>
                <strong>${mesa.capacidad} pax</strong>
            </div>

            <div class="detail-row">
                <span>Posición</span>
                <strong>${Math.round(mesa.x)}, ${Math.round(mesa.y)}</strong>
            </div>
        `;
    }
});