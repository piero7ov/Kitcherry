// ==========================================================
// KITCHERRY SERVICE MAP
// Archivo: assets/js/script.js
// Plano editable con importación de reservas JSON y generador rápido de mesas
// ==========================================================

document.addEventListener("DOMContentLoaded", function () {
    const canvas = document.getElementById("service-map-canvas");
    const ctx = canvas.getContext("2d");

    const btnMesaRectangular = document.getElementById("btn-mesa-rectangular");
    const btnMesaRedonda = document.getElementById("btn-mesa-redonda");
    const btnImportarJson = document.getElementById("btn-importar-json");
    const inputImportarJson = document.getElementById("input-importar-json");
    const btnQuitarReservas = document.getElementById("btn-quitar-reservas");
    const btnGuardar = document.getElementById("btn-guardar");
    const btnEliminar = document.getElementById("btn-eliminar");
    const btnLimpiar = document.getElementById("btn-limpiar");

    const formGeneradorMesas = document.getElementById("form-generador-mesas");
    const inputMesasRedondas = document.getElementById("input-mesas-redondas");
    const inputMesasCuadradas = document.getElementById("input-mesas-cuadradas");
    const generadorTotal = document.getElementById("generador-total");

    const contadorMesas = document.getElementById("contador-mesas");
    const contadorReservas = document.getElementById("contador-reservas");
    const contadorPendientes = document.getElementById("contador-pendientes");

    const servicioInfo = document.getElementById("servicio-info");
    const reservasLista = document.getElementById("reservas-lista");
    const reservasPendientesLista = document.getElementById("reservas-pendientes-lista");
    const reservasMesaSeleccionada = document.getElementById("reservas-mesa-seleccionada");
    const reservasMesaDinamicas = document.getElementById("reservas-mesa-dinamicas");

    const formMesa = document.getElementById("form-mesa");
    const inputId = document.getElementById("mesa-id");
    const inputNombre = document.getElementById("mesa-nombre");
    const inputZona = document.getElementById("mesa-zona");
    const inputCapacidad = document.getElementById("mesa-capacidad");
    const inputTipo = document.getElementById("mesa-tipo");
    const inputEstado = document.getElementById("mesa-estado");
    const inputDoblada = document.getElementById("mesa-doblada");
    const inputAncho = document.getElementById("mesa-ancho");
    const inputAlto = document.getElementById("mesa-alto");
    const inputX = document.getElementById("mesa-x");
    const inputY = document.getElementById("mesa-y");
    const btnAplicarCambios = document.getElementById("btn-aplicar-cambios");

    const STORAGE_KEY = "kitcherry_service_map_plano_importacion";
    const STORAGE_KEY_ANTERIOR = "kitcherry_service_map_plano_editable";

    const MIN_ANCHO_RECTANGULAR = 60;
    const MIN_ALTO_RECTANGULAR = 44;
    const MIN_TAMANO_REDONDA = 46;

    let mesas = [];
    let mesaSeleccionadaId = null;

    let servicioImportado = null;
    let reservasImportadas = [];
    let reservasPendientes = [];

    let accionActual = null;
    let handleActivo = null;

    let offsetX = 0;
    let offsetY = 0;

    let resizeInicio = null;

    let canvasWidth = 0;
    let canvasHeight = 0;

    ajustarCanvas();
    cargarPlano();
    ajustarAlturaCanvasParaMesas(mesas.length);
    vincularReservasConMesas(false);
    dibujar();
    actualizarPanelFormulario();
    actualizarPanelReservas();
    actualizarTotalGenerador();

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

    inputMesasRedondas.addEventListener("input", actualizarTotalGenerador);
    inputMesasCuadradas.addEventListener("input", actualizarTotalGenerador);

    formGeneradorMesas.addEventListener("submit", function (evento) {
        evento.preventDefault();
        generarPlanoRapido();
    });

    btnImportarJson.addEventListener("click", function () {
        inputImportarJson.click();
    });

    inputImportarJson.addEventListener("change", function (evento) {
        const archivo = evento.target.files[0];

        if (!archivo) {
            return;
        }

        leerArchivoReservas(archivo);

        inputImportarJson.value = "";
    });

    btnQuitarReservas.addEventListener("click", function () {
        if (reservasImportadas.length === 0) {
            alert("No hay reservas importadas.");
            return;
        }

        const confirmar = confirm("¿Seguro que quieres quitar las reservas importadas del plano?");

        if (!confirmar) {
            return;
        }

        servicioImportado = null;
        reservasImportadas = [];
        reservasPendientes = [];

        mesas.forEach(function (mesa) {
            mesa.reservas = [];

            if (mesa.dobladaAutomatica === true && mesa.dobladaManual !== true) {
                mesa.doblada = false;
                mesa.dobladaAutomatica = false;
            }

            if (mesa.estado === "reservada") {
                mesa.estado = "libre";
            }
        });

        guardarPlano();
        dibujar();
        actualizarPanelFormulario();
        actualizarPanelReservas();
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

        ajustarAlturaCanvasParaMesas(0);
        guardarPlano();
        dibujar();
        actualizarPanelFormulario();
        actualizarPanelReservas();
    });

    formMesa.addEventListener("submit", function (evento) {
        evento.preventDefault();
        aplicarCambiosFormulario();
    });

    inputTipo.addEventListener("change", function () {
        if (inputTipo.value === "redonda") {
            inputAlto.value = inputAncho.value;
            inputAlto.disabled = true;
        } else if (mesaSeleccionadaId !== null) {
            inputAlto.disabled = false;
        }
    });

    canvas.addEventListener("dragover", function (evento) {
        evento.preventDefault();
        canvas.parentElement.classList.add("drag-over");
    });

    canvas.addEventListener("dragleave", function () {
        canvas.parentElement.classList.remove("drag-over");
    });

    canvas.addEventListener("drop", function (evento) {
        evento.preventDefault();
        canvas.parentElement.classList.remove("drag-over");

        const reservaId = evento.dataTransfer.getData("text/plain");

        if (!reservaId) {
            return;
        }

        const posicion = obtenerPosicionMouse(evento);
        const mesa = obtenerMesaEnPosicion(posicion.x, posicion.y);

        if (!mesa) {
            alert("Suelta la reserva encima de una mesa del plano.");
            return;
        }

        asignarReservaAMesa(reservaId, mesa.id);
    });

    canvas.addEventListener("mousedown", function (evento) {
        const posicion = obtenerPosicionMouse(evento);
        const mesaSeleccionada = obtenerMesaPorId(mesaSeleccionadaId);

        if (mesaSeleccionada) {
            const handle = obtenerHandleEnPosicion(mesaSeleccionada, posicion.x, posicion.y);

            if (handle) {
                accionActual = "redimensionar";
                handleActivo = handle;

                resizeInicio = {
                    mouseX: posicion.x,
                    mouseY: posicion.y,
                    izquierda: mesaSeleccionada.x - mesaSeleccionada.ancho / 2,
                    derecha: mesaSeleccionada.x + mesaSeleccionada.ancho / 2,
                    arriba: mesaSeleccionada.y - mesaSeleccionada.alto / 2,
                    abajo: mesaSeleccionada.y + mesaSeleccionada.alto / 2
                };

                canvas.style.cursor = cursorParaHandle(handle);
                return;
            }
        }

        const mesa = obtenerMesaEnPosicion(posicion.x, posicion.y);

        if (mesa) {
            mesaSeleccionadaId = mesa.id;
            accionActual = "mover";

            offsetX = posicion.x - mesa.x;
            offsetY = posicion.y - mesa.y;

            canvas.style.cursor = "grabbing";
        } else {
            mesaSeleccionadaId = null;
            accionActual = null;
            canvas.style.cursor = "default";
        }

        dibujar();
        actualizarPanelFormulario();
    });

    canvas.addEventListener("mousemove", function (evento) {
        const posicion = obtenerPosicionMouse(evento);

        if (accionActual === "mover" && mesaSeleccionadaId !== null) {
            const mesa = obtenerMesaPorId(mesaSeleccionadaId);

            if (!mesa) {
                return;
            }

            mesa.x = posicion.x - offsetX;
            mesa.y = posicion.y - offsetY;

            limitarMesaDentroCanvas(mesa);

            dibujar();
            actualizarPanelFormulario();
            return;
        }

        if (accionActual === "redimensionar" && mesaSeleccionadaId !== null) {
            const mesa = obtenerMesaPorId(mesaSeleccionadaId);

            if (!mesa || !resizeInicio) {
                return;
            }

            redimensionarMesa(mesa, posicion.x, posicion.y);

            limitarMesaDentroCanvas(mesa);

            dibujar();
            actualizarPanelFormulario();
            return;
        }

        actualizarCursorHover(posicion.x, posicion.y);
    });

    canvas.addEventListener("mouseup", function () {
        finalizarAccionCanvas();
    });

    canvas.addEventListener("mouseleave", function () {
        finalizarAccionCanvas();
    });

    function actualizarTotalGenerador() {
        const redondas = obtenerValorEntero(inputMesasRedondas.value);
        const cuadradas = obtenerValorEntero(inputMesasCuadradas.value);

        generadorTotal.textContent = redondas + cuadradas;
    }

    function generarPlanoRapido() {
        const totalRedondas = obtenerValorEntero(inputMesasRedondas.value);
        const totalCuadradas = obtenerValorEntero(inputMesasCuadradas.value);
        const total = totalRedondas + totalCuadradas;

        if (total <= 0) {
            alert("Indica al menos una mesa para generar el plano.");
            return;
        }

        if (mesas.length > 0) {
            const confirmar = confirm("Esto reemplazará las mesas actuales del plano. Las reservas importadas se mantendrán pendientes para poder colocarlas manualmente. ¿Continuar?");

            if (!confirmar) {
                return;
            }
        }

        mesas = [];
        mesaSeleccionadaId = null;

        ajustarAlturaCanvasParaMesas(total);

        const columnas = calcularColumnasPlano();
        const separacionX = 150;
        const separacionY = 120;
        const margenX = 85;
        const margenY = 80;

        let contador = 1;

        for (let i = 0; i < totalRedondas; i++) {
            const posicion = calcularPosicionMesa(contador, columnas, margenX, margenY, separacionX, separacionY);

            mesas.push({
                id: contador,
                nombre: "Mesa " + contador,
                tipo: "redonda",
                zona: "Interior",
                capacidad: 2,
                estado: "libre",
                doblada: false,
                dobladaManual: false,
                dobladaAutomatica: false,
                reservas: [],
                x: posicion.x,
                y: posicion.y,
                ancho: 86,
                alto: 86
            });

            contador++;
        }

        for (let i = 0; i < totalCuadradas; i++) {
            const posicion = calcularPosicionMesa(contador, columnas, margenX, margenY, separacionX, separacionY);

            mesas.push({
                id: contador,
                nombre: "Mesa " + contador,
                tipo: "rectangular",
                zona: "Interior",
                capacidad: 4,
                estado: "libre",
                doblada: false,
                dobladaManual: false,
                dobladaAutomatica: false,
                reservas: [],
                x: posicion.x,
                y: posicion.y,
                ancho: 95,
                alto: 95
            });

            contador++;
        }

        reservasImportadas.forEach(function (reserva) {
            reserva.colocacion = {
                colocada: false,
                mesa_canvas_id: null,
                pendiente: true,
                motivo_pendiente: "Pendiente de colocar manualmente",
                asignacion_manual: false
            };
        });

        vincularReservasConMesas(false);

        guardarPlano();
        dibujar();
        actualizarPanelFormulario();
        actualizarPanelReservas();
    }

    function calcularColumnasPlano() {
        const separacionX = 150;
        const anchoDisponible = Math.max(canvasWidth - 60, 300);

        return Math.max(1, Math.floor(anchoDisponible / separacionX));
    }

    function calcularPosicionMesa(numero, columnas, margenX, margenY, separacionX, separacionY) {
        const indice = numero - 1;
        const columna = indice % columnas;
        const fila = Math.floor(indice / columnas);

        return {
            x: margenX + columna * separacionX,
            y: margenY + fila * separacionY
        };
    }

    function ajustarAlturaCanvasParaMesas(totalMesas) {
        const columnas = calcularColumnasPlano();
        const filas = Math.ceil(Math.max(totalMesas, 1) / columnas);
        const alturaNecesaria = Math.max(650, filas * 120 + 120);

        canvas.parentElement.style.height = alturaNecesaria + "px";
        ajustarCanvas();
    }

    function obtenerValorEntero(valor) {
        const numero = parseInt(valor, 10);

        if (Number.isNaN(numero) || numero < 0) {
            return 0;
        }

        return numero;
    }

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
            zona: "Interior",
            capacidad: tipo === "rectangular" ? 4 : 2,
            estado: "libre",
            doblada: false,
            dobladaManual: false,
            dobladaAutomatica: false,
            reservas: [],
            x: 90 + (mesas.length * 34) % 420,
            y: 90 + (mesas.length * 28) % 260,
            ancho: tipo === "rectangular" ? 130 : 86,
            alto: tipo === "rectangular" ? 80 : 86
        };

        mesas.push(mesa);
        mesaSeleccionadaId = mesa.id;

        ajustarAlturaCanvasParaMesas(mesas.length);
        vincularReservasConMesas(false);
        guardarPlano();
        dibujar();
        actualizarPanelFormulario();
        actualizarPanelReservas();
    }

    function asignarReservaAMesa(reservaId, mesaId) {
        const reserva = obtenerReservaPorId(reservaId);
        const mesa = obtenerMesaPorId(mesaId);

        if (!reserva || !mesa) {
            alert("No se pudo asignar la reserva.");
            return;
        }

        if (mesa.estado === "bloqueada") {
            alert("No puedes asignar una reserva a una mesa bloqueada.");
            return;
        }

        reserva.colocacion = {
            colocada: true,
            mesa_canvas_id: mesa.id,
            pendiente: false,
            motivo_pendiente: "",
            asignacion_manual: true
        };

        if (mesa.estado === "libre") {
            mesa.estado = "reservada";
        }

        mesa.capacidad = reserva.personas;

        vincularReservasConMesas(false);

        mesaSeleccionadaId = mesa.id;

        guardarPlano();
        dibujar();
        actualizarPanelFormulario();
        actualizarPanelReservas();
    }

    function dibujar() {
        limpiarCanvas();
        dibujarFondoSuave();

        mesas.forEach(function (mesa) {
            dibujarMesa(mesa);
        });

        const mesaSeleccionada = obtenerMesaPorId(mesaSeleccionadaId);

        if (mesaSeleccionada) {
            dibujarHandles(mesaSeleccionada);
        }

        actualizarContadorMesas();
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
        const estadoVisual = obtenerEstadoVisualMesa(mesa);
        const colores = obtenerColoresEstado(estadoVisual);

        ctx.save();

        ctx.shadowColor = "rgba(0, 0, 0, 0.12)";
        ctx.shadowBlur = 12;
        ctx.shadowOffsetY = 5;

        ctx.fillStyle = colores.fondo;
        ctx.strokeStyle = seleccionada ? "#C2182B" : colores.borde;
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
        ctx.fillText(mesa.nombre, mesa.x, mesa.y - 15);

        ctx.fillStyle = "#555555";
        ctx.font = "bold 12px Arial";
        ctx.fillText(mesa.capacidad + " pax", mesa.x, mesa.y + 1);

        ctx.fillStyle = colores.texto;
        ctx.font = "bold 11px Arial";
        ctx.fillText(textoEstado(estadoVisual), mesa.x, mesa.y + 17);

        if (mesa.reservas && mesa.reservas.length > 0) {
            dibujarResumenReservasMesa(mesa);
        }

        if (mesaEstaDoblada(mesa)) {
            dibujarEtiquetaDoblada(mesa);
        }

        ctx.restore();
    }

    function dibujarMesaRectangular(mesa) {
        const x = mesa.x - mesa.ancho / 2;
        const y = mesa.y - mesa.alto / 2;
        const radio = 14;

        dibujarRectRedondeado(x, y, mesa.ancho, mesa.alto, radio);

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

    function dibujarResumenReservasMesa(mesa) {
        const total = mesa.reservas.length;
        const primera = mesa.reservas[0];

        const texto = total === 1
            ? primera.hora + " · " + acortarTexto(primera.cliente, 14)
            : total + " reservas";

        const anchoEtiqueta = Math.min(Math.max(texto.length * 7, 86), mesa.ancho + 30);
        const altoEtiqueta = 20;

        const x = mesa.x - anchoEtiqueta / 2;
        const y = mesa.y - mesa.alto / 2 + 6;

        ctx.save();

        ctx.shadowColor = "transparent";
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
        const y = mesa.y + mesa.alto / 2 - altoEtiqueta - 6;

        ctx.save();

        ctx.shadowColor = "transparent";
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

    function dibujarHandles(mesa) {
        const handles = obtenerHandlesMesa(mesa);

        ctx.save();

        handles.forEach(function (handle) {
            ctx.fillStyle = "#ffffff";
            ctx.strokeStyle = "#C2182B";
            ctx.lineWidth = 2;

            ctx.beginPath();
            ctx.arc(handle.x, handle.y, 6, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
        });

        ctx.restore();
    }

    function obtenerHandlesMesa(mesa) {
        const izquierda = mesa.x - mesa.ancho / 2;
        const derecha = mesa.x + mesa.ancho / 2;
        const arriba = mesa.y - mesa.alto / 2;
        const abajo = mesa.y + mesa.alto / 2;
        const centroX = mesa.x;
        const centroY = mesa.y;

        if (mesa.tipo === "redonda") {
            return [
                { nombre: "n", x: centroX, y: arriba },
                { nombre: "e", x: derecha, y: centroY },
                { nombre: "s", x: centroX, y: abajo },
                { nombre: "w", x: izquierda, y: centroY },
                { nombre: "se", x: derecha, y: abajo }
            ];
        }

        return [
            { nombre: "nw", x: izquierda, y: arriba },
            { nombre: "n", x: centroX, y: arriba },
            { nombre: "ne", x: derecha, y: arriba },
            { nombre: "e", x: derecha, y: centroY },
            { nombre: "se", x: derecha, y: abajo },
            { nombre: "s", x: centroX, y: abajo },
            { nombre: "sw", x: izquierda, y: abajo },
            { nombre: "w", x: izquierda, y: centroY }
        ];
    }

    function obtenerHandleEnPosicion(mesa, x, y) {
        const handles = obtenerHandlesMesa(mesa);

        for (let i = 0; i < handles.length; i++) {
            const handle = handles[i];
            const distancia = Math.sqrt(
                Math.pow(x - handle.x, 2) + Math.pow(y - handle.y, 2)
            );

            if (distancia <= 9) {
                return handle.nombre;
            }
        }

        return null;
    }

    function redimensionarMesa(mesa, mouseX, mouseY) {
        if (mesa.tipo === "redonda") {
            redimensionarMesaRedonda(mesa, mouseX, mouseY);
            return;
        }

        redimensionarMesaRectangular(mesa, mouseX, mouseY);
    }

    function redimensionarMesaRectangular(mesa, mouseX, mouseY) {
        let izquierda = resizeInicio.izquierda;
        let derecha = resizeInicio.derecha;
        let arriba = resizeInicio.arriba;
        let abajo = resizeInicio.abajo;

        const dx = mouseX - resizeInicio.mouseX;
        const dy = mouseY - resizeInicio.mouseY;

        if (handleActivo.includes("e")) {
            derecha = resizeInicio.derecha + dx;
        }

        if (handleActivo.includes("w")) {
            izquierda = resizeInicio.izquierda + dx;
        }

        if (handleActivo.includes("s")) {
            abajo = resizeInicio.abajo + dy;
        }

        if (handleActivo.includes("n")) {
            arriba = resizeInicio.arriba + dy;
        }

        if (derecha - izquierda < MIN_ANCHO_RECTANGULAR) {
            if (handleActivo.includes("w")) {
                izquierda = derecha - MIN_ANCHO_RECTANGULAR;
            } else {
                derecha = izquierda + MIN_ANCHO_RECTANGULAR;
            }
        }

        if (abajo - arriba < MIN_ALTO_RECTANGULAR) {
            if (handleActivo.includes("n")) {
                arriba = abajo - MIN_ALTO_RECTANGULAR;
            } else {
                abajo = arriba + MIN_ALTO_RECTANGULAR;
            }
        }

        izquierda = Math.max(10, izquierda);
        arriba = Math.max(10, arriba);
        derecha = Math.min(canvasWidth - 10, derecha);
        abajo = Math.min(canvasHeight - 10, abajo);

        mesa.ancho = derecha - izquierda;
        mesa.alto = abajo - arriba;
        mesa.x = izquierda + mesa.ancho / 2;
        mesa.y = arriba + mesa.alto / 2;
    }

    function redimensionarMesaRedonda(mesa, mouseX, mouseY) {
        let nuevoTamano = mesa.ancho;

        if (handleActivo === "e") {
            nuevoTamano = Math.abs(mouseX - mesa.x) * 2;
        } else if (handleActivo === "w") {
            nuevoTamano = Math.abs(mesa.x - mouseX) * 2;
        } else if (handleActivo === "s") {
            nuevoTamano = Math.abs(mouseY - mesa.y) * 2;
        } else if (handleActivo === "n") {
            nuevoTamano = Math.abs(mesa.y - mouseY) * 2;
        } else {
            const radio = Math.sqrt(
                Math.pow(mouseX - mesa.x, 2) + Math.pow(mouseY - mesa.y, 2)
            );

            nuevoTamano = radio * 2;
        }

        nuevoTamano = Math.max(MIN_TAMANO_REDONDA, nuevoTamano);

        const maximoPorAncho = Math.min(mesa.x * 2 - 20, (canvasWidth - mesa.x) * 2 - 20);
        const maximoPorAlto = Math.min(mesa.y * 2 - 20, (canvasHeight - mesa.y) * 2 - 20);
        const maximo = Math.max(MIN_TAMANO_REDONDA, Math.min(maximoPorAncho, maximoPorAlto));

        nuevoTamano = Math.min(nuevoTamano, maximo);

        mesa.ancho = nuevoTamano;
        mesa.alto = nuevoTamano;
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
        const margen = 10;
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

    function finalizarAccionCanvas() {
        if (accionActual) {
            guardarPlano();
        }

        accionActual = null;
        handleActivo = null;
        resizeInicio = null;

        canvas.style.cursor = "default";
    }

    function actualizarCursorHover(x, y) {
        const mesaSeleccionada = obtenerMesaPorId(mesaSeleccionadaId);

        if (mesaSeleccionada) {
            const handle = obtenerHandleEnPosicion(mesaSeleccionada, x, y);

            if (handle) {
                canvas.style.cursor = cursorParaHandle(handle);
                return;
            }
        }

        const mesaHover = obtenerMesaEnPosicion(x, y);
        canvas.style.cursor = mesaHover ? "grab" : "default";
    }

    function cursorParaHandle(handle) {
        const cursores = {
            n: "ns-resize",
            s: "ns-resize",
            e: "ew-resize",
            w: "ew-resize",
            ne: "nesw-resize",
            sw: "nesw-resize",
            nw: "nwse-resize",
            se: "nwse-resize"
        };

        return cursores[handle] || "default";
    }

    function aplicarCambiosFormulario() {
        const mesa = obtenerMesaPorId(mesaSeleccionadaId);

        if (!mesa) {
            alert("Primero selecciona una mesa.");
            return;
        }

        const nuevoTipo = inputTipo.value;
        const nuevaCapacidad = Math.max(1, parseInt(inputCapacidad.value, 10) || 1);
        const nuevoAncho = Math.max(40, parseInt(inputAncho.value, 10) || mesa.ancho);
        const nuevoAlto = Math.max(40, parseInt(inputAlto.value, 10) || mesa.alto);
        const nuevaX = parseInt(inputX.value, 10);
        const nuevaY = parseInt(inputY.value, 10);

        mesa.nombre = inputNombre.value.trim() || "Mesa " + mesa.id;
        mesa.zona = inputZona.value;
        mesa.capacidad = nuevaCapacidad;
        mesa.tipo = nuevoTipo;
        mesa.estado = inputEstado.value;

        mesa.dobladaManual = inputDoblada.checked;
        mesa.dobladaAutomatica = false;
        mesa.doblada = inputDoblada.checked;

        if (mesa.tipo === "redonda") {
            mesa.ancho = Math.max(MIN_TAMANO_REDONDA, nuevoAncho);
            mesa.alto = mesa.ancho;
        } else {
            mesa.ancho = Math.max(MIN_ANCHO_RECTANGULAR, nuevoAncho);
            mesa.alto = Math.max(MIN_ALTO_RECTANGULAR, nuevoAlto);
        }

        if (!Number.isNaN(nuevaX)) {
            mesa.x = nuevaX;
        }

        if (!Number.isNaN(nuevaY)) {
            mesa.y = nuevaY;
        }

        limitarMesaDentroCanvas(mesa);

        vincularReservasConMesas(false);
        guardarPlano();
        dibujar();
        actualizarPanelFormulario();
        actualizarPanelReservas();
    }

    function actualizarPanelFormulario() {
        const mesa = obtenerMesaPorId(mesaSeleccionadaId);

        if (!mesa) {
            cambiarEstadoFormulario(false);

            inputId.value = "";
            inputNombre.value = "";
            inputZona.value = "Interior";
            inputCapacidad.value = "";
            inputTipo.value = "rectangular";
            inputEstado.value = "libre";
            inputDoblada.checked = false;
            inputAncho.value = "";
            inputAlto.value = "";
            inputX.value = "";
            inputY.value = "";

            actualizarReservasMesaSeleccionada(null);
            actualizarReservasMesaDinamicas(null);

            return;
        }

        cambiarEstadoFormulario(true);

        inputId.value = mesa.id;
        inputNombre.value = mesa.nombre;
        inputZona.value = mesa.zona;
        inputCapacidad.value = mesa.capacidad;
        inputTipo.value = mesa.tipo;
        inputEstado.value = mesa.estado;
        inputDoblada.checked = mesa.doblada === true;
        inputAncho.value = Math.round(mesa.ancho);
        inputAlto.value = Math.round(mesa.alto);
        inputX.value = Math.round(mesa.x);
        inputY.value = Math.round(mesa.y);

        if (mesa.tipo === "redonda") {
            inputAlto.disabled = true;
        }

        actualizarReservasMesaSeleccionada(mesa);
        actualizarReservasMesaDinamicas(mesa);
    }

    function cambiarEstadoFormulario(activo) {
        const campos = [
            inputNombre,
            inputZona,
            inputCapacidad,
            inputTipo,
            inputEstado,
            inputDoblada,
            inputAncho,
            inputAlto,
            inputX,
            inputY,
            btnAplicarCambios
        ];

        campos.forEach(function (campo) {
            campo.disabled = !activo;
        });

        inputId.disabled = true;
    }

    function actualizarReservasMesaSeleccionada(mesa) {
        if (!mesa) {
            reservasMesaSeleccionada.className = "empty-state mesa-reservas-box";
            reservasMesaSeleccionada.innerHTML = "Selecciona una mesa para ver sus reservas vinculadas.";
            return;
        }

        if (!mesa.reservas || mesa.reservas.length === 0) {
            reservasMesaSeleccionada.className = "empty-state mesa-reservas-box";
            reservasMesaSeleccionada.innerHTML = "Esta mesa no tiene reservas vinculadas.";
            return;
        }

        const estaDoblada = mesa.reservas.length > 1 || mesa.doblada === true;

        let html = `
            <div class="mesa-detalle-card">
                <div class="mesa-detalle-header">
                    <strong>${escaparHTML(mesa.nombre)}</strong>
                    <span>${escaparHTML(mesa.zona)} · ${mesa.capacidad} pax · ${textoEstado(obtenerEstadoVisualMesa(mesa))}</span>
                </div>
        `;

        if (estaDoblada) {
            html += `
                <div class="mesa-doblada-aviso">
                    Mesa doblada: tiene ${mesa.reservas.length} reservas asociadas.
                </div>
            `;
        }

        mesa.reservas.forEach(function (reserva, index) {
            html += crearHtmlReservaMesaLateral(reserva, index + 1);
        });

        html += `</div>`;

        reservasMesaSeleccionada.className = "mesa-reservas-box";
        reservasMesaSeleccionada.innerHTML = html;
    }

    function actualizarReservasMesaDinamicas(mesa) {
        if (!reservasMesaDinamicas) {
            return;
        }

        if (!mesa) {
            reservasMesaDinamicas.className = "empty-state mesa-reservas-dinamicas-box";
            reservasMesaDinamicas.innerHTML = "Selecciona una mesa para ver sus reservas arrastrables.";
            return;
        }

        if (!mesa.reservas || mesa.reservas.length === 0) {
            reservasMesaDinamicas.className = "empty-state mesa-reservas-dinamicas-box";
            reservasMesaDinamicas.innerHTML = "Esta mesa no tiene reservas para mover.";
            return;
        }

        reservasMesaDinamicas.className = "mesa-reservas-dinamicas-box reservas-lista";
        reservasMesaDinamicas.innerHTML = mesa.reservas.map(function (reserva) {
            return crearHtmlReserva(reserva, true);
        }).join("");

        activarDragReservas();
    }

    function crearHtmlReservaMesaLateral(reserva, numero) {
        let extras = "";

        if (reserva.alergias) {
            extras += `<p><strong>Alergias:</strong> ${escaparHTML(reserva.alergias)}</p>`;
        }

        if (reserva.observaciones) {
            extras += `<p><strong>Notas:</strong> ${escaparHTML(reserva.observaciones)}</p>`;
        }

        if (reserva.preferencias) {
            extras += `<p><strong>Preferencias:</strong> ${escaparHTML(reserva.preferencias)}</p>`;
        }

        return `
            <article class="mesa-reserva-detalle">
                <strong>Reserva ${numero}: ${escaparHTML(reserva.hora)} · ${escaparHTML(reserva.cliente)}</strong>
                <p>${reserva.personas} personas · ${escaparHTML(reserva.estado_reserva_texto)}</p>
                <p>Tel: ${escaparHTML(reserva.telefono || "Sin teléfono")}</p>
                ${extras}
            </article>
        `;
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

        vincularReservasConMesas(false);
        guardarPlano();
        dibujar();
        actualizarPanelFormulario();
        actualizarPanelReservas();
    }

    function leerArchivoReservas(archivo) {
        const lector = new FileReader();

        lector.onload = function (evento) {
            try {
                const datos = JSON.parse(evento.target.result);
                importarReservas(datos);
            } catch (error) {
                alert("No se pudo leer el archivo JSON. Revisa que sea un archivo válido.");
            }
        };

        lector.readAsText(archivo, "UTF-8");
    }

    function importarReservas(datosOriginales) {
        let datosAdaptados;

        try {
            datosAdaptados = window.KitcherryReservasAdapter.adaptar(datosOriginales);
        } catch (error) {
            alert(error.message);
            return;
        }

        servicioImportado = {
            origen: datosAdaptados.origen_original,
            destino: datosAdaptados.destino_original,
            fecha_servicio: datosAdaptados.fecha_servicio,
            fecha_servicio_formateada: datosAdaptados.fecha_servicio_formateada,
            turno: datosAdaptados.turno,
            turno_texto: datosAdaptados.turno_texto,
            negocio: datosAdaptados.negocio,
            resumen: datosAdaptados.resumen_plano
        };

        reservasImportadas = datosAdaptados.reservas.map(function (reserva) {
            reserva.colocacion = {
                colocada: false,
                mesa_canvas_id: null,
                pendiente: true,
                motivo_pendiente: "Pendiente de colocar manualmente",
                asignacion_manual: false
            };

            return reserva;
        });

        vincularReservasConMesas(false);

        guardarPlano();
        dibujar();
        actualizarPanelFormulario();
        actualizarPanelReservas();

        alert("Reservas importadas correctamente: " + reservasImportadas.length + ".");
    }

    function normalizarReservaImportada(reserva) {
        if (reserva && reserva.estado_reserva !== undefined) {
            return {
                id: reserva.id,
                id_original: reserva.id_original || reserva.id,

                fecha: reserva.fecha || "",
                hora: reserva.hora || "",
                turno: reserva.turno || "",
                cliente: reserva.cliente || "Cliente sin nombre",
                telefono: reserva.telefono || "",
                email: reserva.email || "",
                personas: parseInt(reserva.personas, 10) || 0,

                estado_reserva: reserva.estado_reserva || "pendiente",
                estado_reserva_texto: reserva.estado_reserva_texto || reserva.estado_reserva || "Pendiente",

                estado_plano_sugerido: reserva.estado_plano_sugerido || "reservada",
                ocupa_mesa: reserva.ocupa_mesa !== false,
                visible_en_plano: reserva.visible_en_plano !== false,

                mesa_id: reserva.mesa_id !== null && reserva.mesa_id !== undefined ? parseInt(reserva.mesa_id, 10) : null,
                mesa_nombre: reserva.mesa_nombre || "Sin mesa",
                mesa_zona: reserva.mesa_zona || "",
                mesa_capacidad: reserva.mesa_capacidad || null,

                alergias: reserva.alergias || "",
                preferencias: reserva.preferencias || "",
                observaciones: reserva.observaciones || "",
                zona_preferida: reserva.zona_preferida || "",

                alertas: reserva.alertas || {
                    total: 0,
                    riesgo: 0,
                    criticas: 0
                },

                banderas: reserva.banderas || {
                    tiene_mesa: reserva.mesa_id !== null,
                    tiene_alergias: false,
                    tiene_observaciones: false,
                    tiene_alertas: false,
                    grupo_grande: false,
                    confirmacion_enviada: false,
                    recordatorio_enviado: false
                },

                colocacion: reserva.colocacion || {
                    colocada: false,
                    mesa_canvas_id: null,
                    pendiente: true,
                    motivo_pendiente: "Pendiente de colocar",
                    asignacion_manual: false
                },

                origen: reserva.origen || "manual"
            };
        }

        return window.KitcherryReservasAdapter.adaptar({
            origen: "Kitcherry Reservas",
            destino: "Kitcherry Service Map",
            reservas: [reserva]
        }).reservas[0];
    }

    function vincularReservasConMesas(actualizarEstadoMesas) {
        mesas.forEach(function (mesa) {
            mesa.reservas = [];

            if (mesa.dobladaAutomatica === true && mesa.dobladaManual !== true) {
                mesa.doblada = false;
            }

            mesa.dobladaAutomatica = false;
        });

        reservasPendientes = [];

        reservasImportadas.forEach(function (reserva) {
            if (reserva.ocupa_mesa === false) {
                reserva.colocacion = {
                    colocada: false,
                    mesa_canvas_id: null,
                    pendiente: false,
                    motivo_pendiente: "La reserva no ocupa mesa en el plano",
                    asignacion_manual: false
                };

                return;
            }

            let mesa = null;

            if (
                reserva.colocacion &&
                reserva.colocacion.mesa_canvas_id !== null &&
                reserva.colocacion.mesa_canvas_id !== undefined
            ) {
                mesa = obtenerMesaPorId(reserva.colocacion.mesa_canvas_id);
            }

            if (mesa) {
                mesa.reservas.push(reserva);

                reserva.colocacion = {
                    colocada: true,
                    mesa_canvas_id: mesa.id,
                    pendiente: false,
                    motivo_pendiente: "",
                    asignacion_manual: reserva.colocacion && reserva.colocacion.asignacion_manual === true
                };

                if (actualizarEstadoMesas && mesa.estado === "libre") {
                    mesa.estado = reserva.estado_plano_sugerido || "reservada";
                }
            } else {
                reserva.colocacion = {
                    colocada: false,
                    mesa_canvas_id: null,
                    pendiente: true,
                    motivo_pendiente: "Pendiente de colocar manualmente",
                    asignacion_manual: false
                };

                reservasPendientes.push(reserva);
            }
        });

        mesas.forEach(function (mesa) {
            mesa.reservas.sort(ordenarReservasPorHora);

            if (mesa.reservas.length > 0) {
                mesa.capacidad = calcularPersonasMesa(mesa);

                if (mesa.estado === "libre") {
                    mesa.estado = "reservada";
                }

                if (mesa.reservas.length > 1) {
                    mesa.doblada = true;
                    mesa.dobladaAutomatica = true;
                }
            }

            if (mesa.reservas.length <= 1 && mesa.dobladaManual === true) {
                mesa.doblada = true;
            }
        });

        reservasPendientes.sort(ordenarReservasPorHora);
    }

    function calcularPersonasMesa(mesa) {
        if (!mesa.reservas || mesa.reservas.length === 0) {
            return mesa.capacidad;
        }

        const personas = mesa.reservas.map(function (reserva) {
            return reserva.personas;
        });

        return Math.max(...personas);
    }

    function ordenarReservasPorHora(a, b) {
        return String(a.hora).localeCompare(String(b.hora));
    }

    function actualizarPanelReservas() {
        const totalReservas = reservasImportadas.length;
        const totalPendientes = reservasPendientes.length;

        contadorReservas.textContent = totalReservas === 1
            ? "1 reserva importada"
            : totalReservas + " reservas importadas";

        contadorPendientes.textContent = totalPendientes === 1
            ? "1 pendiente de colocar"
            : totalPendientes + " pendientes de colocar";

        if (!servicioImportado) {
            servicioInfo.className = "empty-state sidebar-service-info";
            servicioInfo.innerHTML = "No hay reservas importadas. Usa el botón “Importar reservas JSON”.";

            reservasLista.innerHTML = "";
            reservasPendientesLista.innerHTML = "";

            return;
        }

        const negocioNombre = servicioImportado.negocio && servicioImportado.negocio.nombre
            ? servicioImportado.negocio.nombre
            : "Negocio sin nombre";

        servicioInfo.className = "servicio-info-card sidebar-service-info";
        servicioInfo.innerHTML = `
            <strong>${escaparHTML(negocioNombre)}</strong>
            <span>Servicio: ${escaparHTML(servicioImportado.fecha_servicio_formateada)}</span>
            <span>Turno: ${escaparHTML(servicioImportado.turno_texto)}</span>
        `;

        const reservasAsignadas = reservasImportadas.filter(function (reserva) {
            return reserva.colocacion && reserva.colocacion.colocada === true;
        });

        reservasLista.innerHTML = reservasAsignadas.map(function (reserva) {
            return crearHtmlReserva(reserva, true);
        }).join("");

        if (reservasAsignadas.length === 0) {
            reservasLista.innerHTML = `
                <div class="empty-state">
                    No hay reservas colocadas en el plano.
                </div>
            `;
        }

        reservasPendientesLista.innerHTML = reservasPendientes.map(function (reserva) {
            return crearHtmlReserva(reserva, false);
        }).join("");

        if (reservasPendientes.length === 0) {
            reservasPendientesLista.innerHTML = `
                <div class="empty-state">
                    No hay reservas pendientes de colocar.
                </div>
            `;
        }

        activarDragReservas();
    }

    function crearHtmlReserva(reserva, asignada) {
        const clase = asignada ? "reserva-asignada" : "reserva-pendiente";

        const mesaColocada = reserva.colocacion && reserva.colocacion.mesa_canvas_id
            ? obtenerMesaPorId(reserva.colocacion.mesa_canvas_id)
            : null;

        let mesaTexto = reserva.mesa_id
            ? "Mesa original " + reserva.mesa_id + " · " + reserva.mesa_nombre
            : "Sin mesa asignada en origen";

        if (asignada && mesaColocada) {
            mesaTexto = "Colocada en " + mesaColocada.nombre + " · ID " + mesaColocada.id;
        }

        const alertasTotal = reserva.alertas && reserva.alertas.total
            ? parseInt(reserva.alertas.total, 10)
            : 0;

        let pills = `
            <span class="reserva-pill">${escaparHTML(reserva.estado_reserva_texto)}</span>
            <span class="reserva-pill">${reserva.personas} pax</span>
        `;

        if (reserva.banderas && reserva.banderas.tiene_alergias) {
            pills += `<span class="reserva-pill reserva-alergia">Alergias</span>`;
        }

        if (alertasTotal > 0) {
            pills += `<span class="reserva-pill reserva-alerta">${alertasTotal} alerta</span>`;
        }

        if (reserva.colocacion && reserva.colocacion.asignacion_manual === true) {
            pills += `<span class="reserva-pill reserva-manual">Asignación manual</span>`;
        }

        if (!asignada && reserva.colocacion && reserva.colocacion.motivo_pendiente) {
            pills += `<span class="reserva-pill">${escaparHTML(reserva.colocacion.motivo_pendiente)}</span>`;
        }

        return `
            <article class="reserva-item ${clase}" draggable="true" data-reserva-id="${escaparHTML(reserva.id)}">
                <strong>${escaparHTML(reserva.hora)} · ${escaparHTML(reserva.cliente)}</strong>
                <p>${escaparHTML(mesaTexto)}</p>
                <p>${escaparHTML(reserva.telefono || "Sin teléfono")}</p>

                <div class="reserva-item-meta">
                    ${pills}
                </div>
            </article>
        `;
    }

    function activarDragReservas() {
        const tarjetas = document.querySelectorAll(".reserva-item[data-reserva-id]");

        tarjetas.forEach(function (tarjeta) {
            tarjeta.addEventListener("dragstart", function (evento) {
                evento.dataTransfer.setData("text/plain", tarjeta.dataset.reservaId);
                tarjeta.classList.add("dragging");
            });

            tarjeta.addEventListener("dragend", function () {
                tarjeta.classList.remove("dragging");
                canvas.parentElement.classList.remove("drag-over");
            });
        });
    }

    function guardarPlano() {
        const datos = {
            modulo: "Kitcherry Service Map",
            tipo: "plano_con_reservas_importadas",
            guardado_en: new Date().toISOString(),
            mesas: mesas.map(function (mesa) {
                return {
                    id: mesa.id,
                    nombre: mesa.nombre,
                    tipo: mesa.tipo,
                    zona: mesa.zona,
                    capacidad: mesa.capacidad,
                    estado: mesa.estado,
                    doblada: mesa.doblada === true,
                    dobladaManual: mesa.dobladaManual === true,
                    dobladaAutomatica: mesa.dobladaAutomatica === true,
                    x: mesa.x,
                    y: mesa.y,
                    ancho: mesa.ancho,
                    alto: mesa.alto
                };
            }),
            servicioImportado: servicioImportado,
            reservasImportadas: reservasImportadas
        };

        localStorage.setItem(STORAGE_KEY, JSON.stringify(datos));
    }

    function cargarPlano() {
        const datosGuardados = localStorage.getItem(STORAGE_KEY) || localStorage.getItem(STORAGE_KEY_ANTERIOR);

        if (!datosGuardados) {
            return;
        }

        try {
            const datos = JSON.parse(datosGuardados);

            if (Array.isArray(datos.mesas)) {
                mesas = datos.mesas.map(function (mesa) {
                    return normalizarMesa(mesa);
                });
            }

            servicioImportado = datos.servicioImportado || null;

            if (Array.isArray(datos.reservasImportadas)) {
                reservasImportadas = datos.reservasImportadas.map(function (reserva) {
                    return normalizarReservaImportada(reserva);
                });
            }
        } catch (error) {
            console.error("No se pudo cargar el plano guardado:", error);
            mesas = [];
            reservasImportadas = [];
            reservasPendientes = [];
            servicioImportado = null;
        }
    }

    function normalizarMesa(mesa) {
        const tipo = mesa.tipo || "rectangular";
        const ancho = mesa.ancho || (tipo === "rectangular" ? 130 : 86);
        const alto = tipo === "redonda" ? ancho : (mesa.alto || 80);

        let estado = mesa.estado || "libre";
        let dobladaManual = mesa.dobladaManual === true;
        let dobladaAutomatica = mesa.dobladaAutomatica === true;
        let doblada = mesa.doblada === true;

        const tieneFlagsDoblada = mesa.dobladaManual !== undefined || mesa.dobladaAutomatica !== undefined;

        if (!tieneFlagsDoblada && doblada === true) {
            dobladaAutomatica = true;
        }

        if (estado === "doblada") {
            estado = "reservada";
            doblada = true;
            dobladaAutomatica = true;
        }

        return {
            id: parseInt(mesa.id, 10),
            nombre: mesa.nombre || "Mesa " + mesa.id,
            tipo: tipo,
            zona: mesa.zona || "Interior",
            capacidad: mesa.capacidad || (tipo === "rectangular" ? 4 : 2),
            estado: estado,
            doblada: doblada,
            dobladaManual: dobladaManual,
            dobladaAutomatica: dobladaAutomatica,
            reservas: [],
            x: mesa.x || 100,
            y: mesa.y || 100,
            ancho: ancho,
            alto: alto
        };
    }

    function obtenerMesaPorId(id) {
        const idNumerico = parseInt(id, 10);

        return mesas.find(function (mesa) {
            return mesa.id === idNumerico;
        });
    }

    function obtenerReservaPorId(id) {
        const idNumerico = parseInt(id, 10);

        return reservasImportadas.find(function (reserva) {
            return parseInt(reserva.id, 10) === idNumerico;
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

    function actualizarContadorMesas() {
        const total = mesas.length;

        contadorMesas.textContent = total === 1
            ? "1 mesa"
            : total + " mesas";
    }

    function obtenerEstadoVisualMesa(mesa) {
        if (mesa.estado === "libre" && mesa.reservas && mesa.reservas.length > 0) {
            return "reservada";
        }

        return mesa.estado;
    }

    function mesaEstaDoblada(mesa) {
        if (mesa.doblada === true) {
            return true;
        }

        return mesa.reservas && mesa.reservas.length > 1;
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
});