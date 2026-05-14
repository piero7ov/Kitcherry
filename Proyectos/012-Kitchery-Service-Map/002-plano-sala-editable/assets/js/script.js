// ==========================================================
// KITCHERRY SERVICE MAP
// Archivo: assets/js/script.js
// Plano editable con mesas, estados, marca doblada y redimensionado
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

    const STORAGE_KEY = "kitcherry_service_map_plano_editable";
    const STORAGE_KEY_ANTERIOR = "kitcherry_service_map_plano_base";

    const MIN_ANCHO_RECTANGULAR = 60;
    const MIN_ALTO_RECTANGULAR = 44;
    const MIN_TAMANO_REDONDA = 46;

    let mesas = [];
    let mesaSeleccionadaId = null;

    let accionActual = null;
    let handleActivo = null;

    let offsetX = 0;
    let offsetY = 0;

    let resizeInicio = null;

    let canvasWidth = 0;
    let canvasHeight = 0;

    ajustarCanvas();
    cargarPlano();
    dibujar();
    actualizarPanelFormulario();

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
        actualizarPanelFormulario();
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
                    x: mesaSeleccionada.x,
                    y: mesaSeleccionada.y,
                    ancho: mesaSeleccionada.ancho,
                    alto: mesaSeleccionada.alto,
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
            x: 90 + (mesas.length * 34) % 420,
            y: 90 + (mesas.length * 28) % 260,
            ancho: tipo === "rectangular" ? 130 : 86,
            alto: tipo === "rectangular" ? 80 : 86
        };

        mesas.push(mesa);
        mesaSeleccionadaId = mesa.id;

        guardarPlano();
        dibujar();
        actualizarPanelFormulario();
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
        const colores = obtenerColoresEstado(mesa.estado);

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
        ctx.fillText(mesa.nombre, mesa.x, mesa.y - 12);

        ctx.fillStyle = "#555555";
        ctx.font = "bold 12px Arial";
        ctx.fillText(mesa.capacidad + " pax", mesa.x, mesa.y + 6);

        ctx.fillStyle = colores.texto;
        ctx.font = "bold 11px Arial";
        ctx.fillText(textoEstado(mesa.estado), mesa.x, mesa.y + 24);

        if (mesa.doblada === true) {
            dibujarEtiquetaDoblada(mesa);
        }

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

        guardarPlano();
        dibujar();
        actualizarPanelFormulario();
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
        actualizarPanelFormulario();
    }

    function guardarPlano() {
        const datos = {
            modulo: "Kitcherry Service Map",
            tipo: "plano_editable",
            guardado_en: new Date().toISOString(),
            mesas: mesas
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
        } catch (error) {
            console.error("No se pudo cargar el plano guardado:", error);
            mesas = [];
        }
    }

    function normalizarMesa(mesa) {
        const tipo = mesa.tipo || "rectangular";
        const ancho = mesa.ancho || (tipo === "rectangular" ? 130 : 86);
        const alto = tipo === "redonda" ? ancho : (mesa.alto || 80);

        let estado = mesa.estado || "libre";
        let doblada = mesa.doblada === true;

        // Compatibilidad por si venías de una prueba anterior donde "doblada" era estado.
        if (estado === "doblada") {
            estado = "reservada";
            doblada = true;
        }

        return {
            id: mesa.id,
            nombre: mesa.nombre || "Mesa " + mesa.id,
            tipo: tipo,
            zona: mesa.zona || "Interior",
            capacidad: mesa.capacidad || (tipo === "rectangular" ? 4 : 2),
            estado: estado,
            doblada: doblada,
            x: mesa.x || 100,
            y: mesa.y || 100,
            ancho: ancho,
            alto: alto
        };
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
});