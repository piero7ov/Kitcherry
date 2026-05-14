// ==========================================================
// KITCHERRY VOICE TASKS
// Archivo: assets/js/app.js
// ==========================================================

document.addEventListener("DOMContentLoaded", function () {
    const destinoSelect = document.querySelector("[data-destino-lista]");
    const contenedorItems = document.querySelector("#items-lista");
    const botonAgregar = document.querySelector("[data-agregar-item]");

    const botonIniciarDictado = document.querySelector("[data-btn-iniciar-dictado]");
    const botonDetenerDictado = document.querySelector("[data-btn-detener-dictado]");
    const botonProcesarIA = document.querySelector("[data-btn-procesar-ia]");

    const textoVoz = document.querySelector("[data-texto-voz]");
    const mensajeVoz = document.querySelector("[data-mensaje-voz]");

    let reconocimientoVoz = null;
    let dictadoActivo = false;
    let reinicioPendiente = false;

    const tiposPorDestino = {
        cocina: [
            "Reposición",
            "Elaboración",
            "Tarea del turno",
            "Incidencia"
        ],
        sala: [
            "Reposición",
            "Tarea del turno",
            "Incidencia",
            "Aviso de servicio"
        ],
        general: [
            "Incidencia general",
            "Aviso para siguiente turno"
        ]
    };

    function actualizarSelectTipos() {
        if (!destinoSelect) {
            return;
        }

        const destino = destinoSelect.value;
        const tipos = tiposPorDestino[destino] || tiposPorDestino.cocina;
        const selectsTipo = document.querySelectorAll("[data-select-tipo]");

        selectsTipo.forEach(function (select) {
            const valorActual = select.value;

            select.innerHTML = "";

            tipos.forEach(function (tipo) {
                const option = document.createElement("option");
                option.value = tipo;
                option.textContent = tipo;

                if (valorActual === tipo) {
                    option.selected = true;
                }

                select.appendChild(option);
            });
        });
    }

    function crearFilaItem(descripcion = "", cantidad = "") {
        const fila = document.createElement("div");
        fila.className = "item-row item-row-simple";

        fila.innerHTML = `
            <div class="campo campo-descripcion">
                <label>Descripción</label>
                <input 
                    type="text" 
                    name="descripcion[]" 
                    placeholder="Ej: Agua con gas"
                    required
                >
            </div>

            <div class="campo">
                <label>Cantidad</label>
                <input 
                    type="text" 
                    name="cantidad[]" 
                    placeholder="Ej: 2 cajas"
                >
            </div>

            <button type="button" class="btn-icono" data-eliminar-item>Eliminar</button>
        `;

        fila.querySelector('input[name="descripcion[]"]').value = descripcion;
        fila.querySelector('input[name="cantidad[]"]').value = cantidad;

        return fila;
    }

    function escribirMensajeVoz(texto, tipo = "") {
        if (!mensajeVoz) {
            return;
        }

        mensajeVoz.textContent = texto;
        mensajeVoz.className = "mensaje-voz";

        if (tipo !== "") {
            mensajeVoz.classList.add("mensaje-voz-" + tipo);
        }
    }

    function actualizarBotonesDictado() {
        if (!botonIniciarDictado || !botonDetenerDictado) {
            return;
        }

        botonIniciarDictado.disabled = dictadoActivo;
        botonDetenerDictado.disabled = !dictadoActivo;
    }

    function añadirLineaTextoDictado(texto) {
        if (!textoVoz) {
            return;
        }

        const textoLimpio = texto.trim();

        if (textoLimpio === "") {
            return;
        }

        const textoActual = textoVoz.value.trim();

        if (textoActual !== "") {
            textoVoz.value = textoActual + "\n" + textoLimpio;
        } else {
            textoVoz.value = textoLimpio;
        }
    }

    function rellenarItems(items) {
        if (!contenedorItems) {
            return;
        }

        contenedorItems.innerHTML = "";

        items.forEach(function (item) {
            const descripcion = item.descripcion || "";
            const cantidad = item.cantidad || "";

            if (descripcion.trim() === "") {
                return;
            }

            contenedorItems.appendChild(crearFilaItem(descripcion, cantidad));
        });

        if (contenedorItems.children.length === 0) {
            contenedorItems.appendChild(crearFilaItem());
        }
    }

    function crearReconocimientoVoz() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

        if (!SpeechRecognition) {
            return null;
        }

        const reconocimiento = new SpeechRecognition();

        reconocimiento.lang = "es-ES";
        reconocimiento.continuous = true;
        reconocimiento.interimResults = true;
        reconocimiento.maxAlternatives = 1;

        reconocimiento.onstart = function () {
            escribirMensajeVoz("Dictado activo. Puedes hablar seguido y detenerlo al finalizar.", "activo");
        };

        reconocimiento.onerror = function (evento) {
            if (evento.error === "no-speech") {
                escribirMensajeVoz("No se detectó voz. El dictado seguirá intentando escuchar.", "activo");
                return;
            }

            if (evento.error === "aborted") {
                return;
            }

            escribirMensajeVoz("Error en el reconocimiento de voz: " + evento.error, "error");
        };

        reconocimiento.onend = function () {
            reconocimientoVoz = null;

            if (dictadoActivo && !reinicioPendiente) {
                reinicioPendiente = true;

                setTimeout(function () {
                    reinicioPendiente = false;

                    if (dictadoActivo) {
                        iniciarDictado();
                    }
                }, 350);
            }

            if (!dictadoActivo) {
                escribirMensajeVoz("Dictado detenido. Puedes organizar el texto.", "ok");
                actualizarBotonesDictado();
            }
        };

        reconocimiento.onresult = function (evento) {
            let textoFinal = "";
            let textoIntermedio = "";

            for (let i = evento.resultIndex; i < evento.results.length; i++) {
                const fragmento = evento.results[i][0].transcript.trim();

                if (evento.results[i].isFinal) {
                    textoFinal += fragmento + " ";
                } else {
                    textoIntermedio += fragmento + " ";
                }
            }

            if (textoFinal.trim() !== "") {
                añadirLineaTextoDictado(textoFinal);
                escribirMensajeVoz("Texto añadido. Puedes seguir dictando o detener cuando termines.", "activo");
            } else if (textoIntermedio.trim() !== "") {
                escribirMensajeVoz("Escuchando: " + textoIntermedio.trim(), "activo");
            }
        };

        return reconocimiento;
    }

    function iniciarDictado() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

        if (!SpeechRecognition) {
            escribirMensajeVoz("Tu navegador no soporta reconocimiento de voz. Prueba con Chrome.", "error");
            return;
        }

        if (dictadoActivo && reconocimientoVoz) {
            return;
        }

        dictadoActivo = true;
        actualizarBotonesDictado();

        reconocimientoVoz = crearReconocimientoVoz();

        if (!reconocimientoVoz) {
            dictadoActivo = false;
            actualizarBotonesDictado();
            escribirMensajeVoz("Tu navegador no soporta reconocimiento de voz. Prueba con Chrome.", "error");
            return;
        }

        try {
            reconocimientoVoz.start();
        } catch (error) {
            dictadoActivo = false;
            reconocimientoVoz = null;
            actualizarBotonesDictado();
            escribirMensajeVoz("No se pudo iniciar el dictado.", "error");
        }
    }

    function detenerDictado() {
        dictadoActivo = false;
        actualizarBotonesDictado();

        if (reconocimientoVoz) {
            try {
                reconocimientoVoz.stop();
            } catch (error) {
                reconocimientoVoz = null;
            }
        }

        escribirMensajeVoz("Dictado detenido. Puedes organizar el texto.", "ok");
    }

    async function procesarConIA() {
        if (!textoVoz || !destinoSelect) {
            return;
        }

        const texto = textoVoz.value.trim();
        const tipoSelect = document.querySelector("#tipo_general");
        const prioridadSelect = document.querySelector("#prioridad_general");

        if (texto === "") {
            escribirMensajeVoz("Primero dicta o escribe un texto.", "error");
            return;
        }

        escribirMensajeVoz("Organizando lista...", "activo");

        try {
            const respuesta = await fetch("api/procesar_voz_ia.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    texto: texto,
                    destino: destinoSelect.value,
                    tipo: tipoSelect ? tipoSelect.value : "",
                    prioridad: prioridadSelect ? prioridadSelect.value : ""
                })
            });

            const datos = await respuesta.json();

            if (!datos.ok) {
                escribirMensajeVoz(datos.mensaje || "No se pudo organizar el texto.", "error");
                return;
            }

            rellenarItems(datos.items || []);
            escribirMensajeVoz("Lista organizada. Revisa antes de guardar.", "ok");

        } catch (error) {
            escribirMensajeVoz("Error al organizar el texto.", "error");
        }
    }

    async function guardarEdicionRapida(elemento, valorAnterior, input) {
        const itemId = elemento.dataset.itemId;
        const campo = elemento.dataset.campo;
        const valor = input.value.trim();

        if (campo === "descripcion" && valor === "") {
            alert("La descripción no puede quedar vacía.");
            elemento.textContent = valorAnterior;
            return;
        }

        try {
            const respuesta = await fetch("api/actualizar_item.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    item_id: itemId,
                    campo: campo,
                    valor: valor
                })
            });

            const datos = await respuesta.json();

            if (!datos.ok) {
                alert(datos.mensaje || "No se pudo actualizar.");
                elemento.textContent = valorAnterior;
                return;
            }

            if (campo === "cantidad" && valor === "") {
                elemento.textContent = "Sin cantidad";
                elemento.classList.add("cantidad-vacia");
            } else {
                elemento.textContent = valor;
                elemento.classList.remove("cantidad-vacia");
            }

            const previewTxt = document.querySelector("[data-preview-txt]");

            if (previewTxt && datos.contenido_txt) {
                previewTxt.textContent = datos.contenido_txt;
            }

        } catch (error) {
            alert("Error al guardar el cambio.");
            elemento.textContent = valorAnterior;
        }
    }

    function activarEdicionRapida(elemento) {
        if (elemento.dataset.editando === "1") {
            return;
        }

        const campo = elemento.dataset.campo;
        const valorAnterior = campo === "cantidad" && elemento.classList.contains("cantidad-vacia")
            ? ""
            : elemento.textContent.trim();

        elemento.dataset.editando = "1";

        const input = document.createElement("input");
        input.type = "text";
        input.className = "input-edicion-rapida";
        input.value = valorAnterior;

        elemento.textContent = "";
        elemento.appendChild(input);

        input.focus();
        input.select();

        let guardado = false;

        async function finalizar(guardar) {
            if (guardado) {
                return;
            }

            guardado = true;
            elemento.dataset.editando = "0";

            if (guardar) {
                await guardarEdicionRapida(elemento, valorAnterior, input);
            } else {
                if (campo === "cantidad" && valorAnterior === "") {
                    elemento.textContent = "Sin cantidad";
                    elemento.classList.add("cantidad-vacia");
                } else {
                    elemento.textContent = valorAnterior;
                }
            }
        }

        input.addEventListener("blur", function () {
            finalizar(true);
        });

        input.addEventListener("keydown", function (evento) {
            if (evento.key === "Enter") {
                evento.preventDefault();
                finalizar(true);
            }

            if (evento.key === "Escape") {
                evento.preventDefault();
                finalizar(false);
            }
        });
    }

    if (destinoSelect) {
        destinoSelect.addEventListener("change", actualizarSelectTipos);
        actualizarSelectTipos();
    }

    if (botonAgregar && contenedorItems) {
        botonAgregar.addEventListener("click", function () {
            contenedorItems.appendChild(crearFilaItem());
        });
    }

    if (botonIniciarDictado) {
        botonIniciarDictado.addEventListener("click", iniciarDictado);
    }

    if (botonDetenerDictado) {
        botonDetenerDictado.addEventListener("click", detenerDictado);
    }

    if (botonProcesarIA) {
        botonProcesarIA.addEventListener("click", procesarConIA);
    }

    document.addEventListener("click", function (evento) {
        const botonEliminar = evento.target.closest("[data-eliminar-item]");

        if (!botonEliminar || !contenedorItems) {
            return;
        }

        const filas = contenedorItems.querySelectorAll(".item-row");

        if (filas.length <= 1) {
            alert("La lista debe tener al menos un elemento.");
            return;
        }

        botonEliminar.closest(".item-row").remove();
    });

    document.addEventListener("dblclick", function (evento) {
        const editable = evento.target.closest('[data-editable="1"]');

        if (!editable) {
            return;
        }

        activarEdicionRapida(editable);
    });

    actualizarBotonesDictado();
});