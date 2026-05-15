// ==========================================================
// KITCHERRY MAIL - SCRIPT PRINCIPAL
// Datos cargados desde SQLite mediante PHP.
// ==========================================================

document.addEventListener("DOMContentLoaded", function () {
    if (!window.KITCHERRY_MAIL_DATA) {
        return;
    }

    let correos = window.KITCHERRY_MAIL_DATA.correos || [];
    const plantillas = window.KITCHERRY_MAIL_DATA.plantillas || {};

    const nombresCarpetas = {
        inbox: "Bandeja de entrada",
        sent: "Enviados",
        drafts: "Borradores",
        archived: "Archivados",
        trash: "Papelera"
    };

    const subtitulosCarpetas = {
        inbox: "Correos organizados para una gestión más clara.",
        sent: "Mensajes enviados desde la cuenta.",
        drafts: "Correos guardados para continuar después.",
        archived: "Mensajes guardados fuera de la bandeja principal.",
        trash: "Correos enviados a papelera."
    };

    const mailList = document.getElementById("mailList");
    const kanbanBoard = document.getElementById("kanbanBoard");

    const currentFolderTitle = document.getElementById("currentFolderTitle");
    const currentFolderSubtitle = document.getElementById("currentFolderSubtitle");

    const folderButtons = document.querySelectorAll(".folder-item");
    const statusButtons = document.querySelectorAll(".status-filter");

    const quickFilter = document.getElementById("quickFilter");
    const searchForm = document.getElementById("searchForm");
    const searchInput = document.getElementById("searchInput");

    const btnListView = document.getElementById("btnListView");
    const btnKanbanView = document.getElementById("btnKanbanView");

    const emptyState = document.getElementById("emptyState");
    const detailContent = document.getElementById("detailContent");
    const detailBadges = document.getElementById("detailBadges");
    const detailSubject = document.getElementById("detailSubject");
    const detailFrom = document.getElementById("detailFrom");
    const detailEmail = document.getElementById("detailEmail");
    const detailDate = document.getElementById("detailDate");
    const detailBody = document.getElementById("detailBody");
    const statusSelect = document.getElementById("statusSelect");

    const noteInput = document.getElementById("noteInput");
    const noteList = document.getElementById("noteList");
    const historyList = document.getElementById("historyList");

    const btnMarkRead = document.getElementById("btnMarkRead");
    const btnArchive = document.getElementById("btnArchive");
    const btnTrash = document.getElementById("btnTrash");
    const btnReply = document.getElementById("btnReply");
    const btnForward = document.getElementById("btnForward");
    const btnAddNote = document.getElementById("btnAddNote");
    const btnSync = document.getElementById("btnSync");
    const syncStatus = document.getElementById("syncStatus");

    const countInbox = document.getElementById("countInbox");
    const countSent = document.getElementById("countSent");
    const countDrafts = document.getElementById("countDrafts");
    const countArchived = document.getElementById("countArchived");
    const countTrash = document.getElementById("countTrash");

    const composeModal = document.getElementById("composeModal");
    const composeTitle = document.getElementById("composeTitle");
    const composeForm = document.getElementById("composeForm");
    const composeTo = document.getElementById("composeTo");
    const composeSubject = document.getElementById("composeSubject");
    const composeEditor = document.getElementById("composeEditor");
    const composeHtml = document.getElementById("composeHtml");
    const templateSelect = document.getElementById("templateSelect");

    const btnOpenCompose = document.getElementById("btnOpenCompose");
    const btnCloseCompose = document.getElementById("btnCloseCompose");
    const btnSaveDraft = document.getElementById("btnSaveDraft");
    const btnAddLink = document.getElementById("btnAddLink");
    const btnClearFormat = document.getElementById("btnClearFormat");
    const toolbarButtons = document.querySelectorAll(".wysiwyg-toolbar button[data-command]");

    const toast = document.getElementById("toast");

    let carpetaActual = "inbox";
    let estadoActual = "all";
    let busquedaActual = "";
    let vistaActual = "lista";
    let correoSeleccionadoId = null;

    async function apiPost(action, payload = {}) {
        try {
            const respuesta = await fetch("api.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    action: action,
                    ...payload
                })
            });

            const datos = await respuesta.json();

            if (!respuesta.ok || !datos.ok) {
                throw new Error(datos.message || "No se pudo completar la acción");
            }

            if (Array.isArray(datos.correos)) {
                correos = datos.correos;
            }

            return datos;
        } catch (error) {
            mostrarToast(error.message);
            return null;
        }
    }

    function normalizarTexto(texto) {
        return texto
            .toString()
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "");
    }

    function obtenerCorreoSeleccionado() {
        return correos.find(function (correo) {
            return correo.id === correoSeleccionadoId;
        });
    }

    function obtenerCorreosFiltrados() {
        let resultado = correos.filter(function (correo) {
            return correo.carpeta === carpetaActual;
        });

        if (estadoActual !== "all") {
            resultado = resultado.filter(function (correo) {
                return correo.estado === estadoActual;
            });
        }

        if (quickFilter.value === "unread") {
            resultado = resultado.filter(function (correo) {
                return !correo.leido;
            });
        }

        if (quickFilter.value === "withNotes") {
            resultado = resultado.filter(function (correo) {
                return correo.notas.length > 0;
            });
        }

        if (quickFilter.value === "highPriority") {
            resultado = resultado.filter(function (correo) {
                return correo.prioridad === "alta";
            });
        }

        if (busquedaActual.trim() !== "") {
            const busqueda = normalizarTexto(busquedaActual);

            resultado = resultado.filter(function (correo) {
                const textoCorreo = [
                    correo.remitente,
                    correo.email,
                    correo.destinatario,
                    correo.asunto,
                    correo.resumen,
                    correo.cuerpo,
                    correo.tipo,
                    correo.estado,
                    correo.prioridad
                ].join(" ");

                return normalizarTexto(textoCorreo).includes(busqueda);
            });
        }

        return resultado;
    }

    function textoEstado(estado) {
        const estados = {
            pendiente: "Pendiente",
            revision: "Revisión",
            respondido: "Respondido",
            importante: "Importante",
            archivado: "Archivado"
        };

        return estados[estado] || estado;
    }

    function textoPrioridad(prioridad) {
        const prioridades = {
            alta: "Alta",
            media: "Media",
            baja: "Baja"
        };

        return prioridades[prioridad] || prioridad;
    }

    function textoTipo(tipo) {
        const tipos = {
            cliente: "Cliente",
            proveedor: "Proveedor",
            comercial: "Comercial",
            interno: "Interno"
        };

        return tipos[tipo] || tipo;
    }

    function mostrarToast(mensaje) {
        toast.textContent = mensaje;
        toast.classList.remove("hidden");

        setTimeout(function () {
            toast.classList.add("hidden");
        }, 2400);
    }

    function actualizarContadores() {
        countInbox.textContent = correos.filter(c => c.carpeta === "inbox").length;
        countSent.textContent = correos.filter(c => c.carpeta === "sent").length;
        countDrafts.textContent = correos.filter(c => c.carpeta === "drafts").length;
        countArchived.textContent = correos.filter(c => c.carpeta === "archived").length;
        countTrash.textContent = correos.filter(c => c.carpeta === "trash").length;
    }

    function renderizarLista() {
        const lista = obtenerCorreosFiltrados();

        mailList.innerHTML = "";

        if (lista.length === 0) {
            mailList.innerHTML = `
                <div class="mail-list-empty">
                    <h3>No hay correos en esta vista</h3>
                    <p>Cuando se conecte la sincronización IMAP aparecerán aquí los mensajes reales.</p>
                </div>
            `;
            return;
        }

        lista.forEach(function (correo) {
            const boton = document.createElement("button");

            boton.type = "button";
            boton.className = "mail-item";
            boton.dataset.id = correo.id;

            if (!correo.leido) {
                boton.classList.add("unread");
            }

            if (correo.id === correoSeleccionadoId) {
                boton.classList.add("active");
            }

            boton.innerHTML = `
                <div class="mail-card-top">
                    <div class="mail-card-user">
                        <span class="mail-sender">${correo.remitente}</span>
                        <span class="mail-date">${correo.fecha}</span>
                    </div>
                    ${!correo.leido ? '<span class="unread-dot" title="No leído"></span>' : ""}
                </div>

                <strong class="mail-subject">${correo.asunto}</strong>

                <p class="mail-preview">${correo.resumen}</p>

                <div class="mail-card-badges">
                    <span class="badge badge-${correo.estado}">${textoEstado(correo.estado)}</span>
                    <span class="badge priority-${correo.prioridad}">${textoPrioridad(correo.prioridad)}</span>
                    <span class="badge type-${correo.tipo}">${textoTipo(correo.tipo)}</span>
                </div>
            `;

            boton.addEventListener("click", function () {
                seleccionarCorreo(correo.id);
            });

            mailList.appendChild(boton);
        });
    }

    function renderizarKanban() {
        const columnas = {
            pendiente: document.getElementById("kanbanPendiente"),
            revision: document.getElementById("kanbanRevision"),
            respondido: document.getElementById("kanbanRespondido"),
            archivado: document.getElementById("kanbanArchivado")
        };

        Object.values(columnas).forEach(function (columna) {
            columna.innerHTML = "";
        });

        correos.forEach(function (correo) {
            let estadoKanban = correo.estado;

            if (correo.carpeta === "archived") {
                estadoKanban = "archivado";
            }

            if (!columnas[estadoKanban]) {
                return;
            }

            const tarjeta = document.createElement("article");
            tarjeta.className = "kanban-card";
            tarjeta.draggable = true;
            tarjeta.dataset.id = correo.id;

            if (correo.id === correoSeleccionadoId) {
                tarjeta.classList.add("active");
            }

            tarjeta.innerHTML = `
                <div class="kanban-badges">
                    <span>${textoTipo(correo.tipo)}</span>
                    <span>${textoPrioridad(correo.prioridad)}</span>
                </div>
                <h4>${correo.asunto}</h4>
                <p>${correo.remitente}</p>
            `;

            tarjeta.addEventListener("click", function () {
                seleccionarCorreo(correo.id);
            });

            tarjeta.addEventListener("dragstart", function (evento) {
                evento.dataTransfer.setData("text/plain", correo.id);
            });

            columnas[estadoKanban].appendChild(tarjeta);
        });
    }

    function prepararKanbanDragDrop() {
        const zonas = document.querySelectorAll(".kanban-dropzone");

        zonas.forEach(function (zona) {
            zona.addEventListener("dragover", function (evento) {
                evento.preventDefault();
                zona.classList.add("drag-over");
            });

            zona.addEventListener("dragleave", function () {
                zona.classList.remove("drag-over");
            });

            zona.addEventListener("drop", async function (evento) {
                evento.preventDefault();
                zona.classList.remove("drag-over");

                const correoId = Number(evento.dataTransfer.getData("text/plain"));
                const columna = zona.closest(".kanban-column");
                const nuevoEstado = columna.dataset.kanbanStatus;

                if (nuevoEstado === "archivado") {
                    await apiPost("move_folder", {
                        id: correoId,
                        folder: "archived",
                        status: "archivado"
                    });
                } else {
                    await apiPost("update_status", {
                        id: correoId,
                        status: nuevoEstado
                    });
                }

                actualizarInterfaz();
                mostrarToast("Correo actualizado");
            });
        });
    }

    async function seleccionarCorreo(id) {
        correoSeleccionadoId = id;

        const correo = obtenerCorreoSeleccionado();

        if (!correo) {
            return;
        }

        const estabaSinLeer = !correo.leido;

        correo.leido = true;

        emptyState.classList.add("hidden");
        detailContent.classList.remove("hidden");

        renderizarDetalle(correo);
        actualizarInterfaz();

        if (estabaSinLeer) {
            await apiPost("update_read", {
                id: correo.id,
                is_read: 1,
                silent: true
            });

            actualizarInterfaz();
        }
    }

    function renderizarDetalle(correo) {
        detailBadges.innerHTML = `
            <span class="badge badge-${correo.estado}">${textoEstado(correo.estado)}</span>
            <span class="badge priority-${correo.prioridad}">${textoPrioridad(correo.prioridad)}</span>
            <span class="badge type-${correo.tipo}">${textoTipo(correo.tipo)}</span>
        `;

        detailSubject.textContent = correo.asunto;
        detailFrom.textContent = correo.remitente;
        detailEmail.textContent = "<" + correo.email + ">";
        detailDate.textContent = correo.vistaFecha;
        detailBody.innerHTML = correo.cuerpo;

        statusSelect.value = correo.estado;

        renderizarNotas(correo);
        renderizarHistorial(correo);
    }

    function renderizarNotas(correo) {
        noteList.innerHTML = "";

        if (correo.notas.length === 0) {
            noteList.innerHTML = "<li>No hay notas internas para este correo.</li>";
            return;
        }

        correo.notas.forEach(function (nota) {
            const li = document.createElement("li");
            li.textContent = nota;
            noteList.appendChild(li);
        });
    }

    function renderizarHistorial(correo) {
        historyList.innerHTML = "";

        correo.historial.forEach(function (accion) {
            const li = document.createElement("li");
            li.textContent = accion;
            historyList.appendChild(li);
        });
    }

    btnMarkRead.addEventListener("click", async function () {
        const correo = obtenerCorreoSeleccionado();

        if (!correo) {
            return;
        }

        const nuevoEstadoLectura = !correo.leido;

        await apiPost("update_read", {
            id: correo.id,
            is_read: nuevoEstadoLectura ? 1 : 0
        });

        actualizarInterfaz();
        mostrarToast(nuevoEstadoLectura ? "Correo marcado como leído" : "Correo marcado como no leído");
    });

    btnArchive.addEventListener("click", async function () {
        const correo = obtenerCorreoSeleccionado();

        if (!correo) {
            return;
        }

        await apiPost("move_folder", {
            id: correo.id,
            folder: "archived",
            status: "archivado"
        });

        actualizarInterfaz();
        mostrarToast("Correo archivado");
    });

    btnTrash.addEventListener("click", async function () {
        const correo = obtenerCorreoSeleccionado();

        if (!correo) {
            return;
        }

        await apiPost("move_folder", {
            id: correo.id,
            folder: "trash"
        });

        actualizarInterfaz();
        mostrarToast("Correo enviado a papelera");
    });

    statusSelect.addEventListener("change", async function () {
        const correo = obtenerCorreoSeleccionado();

        if (!correo) {
            return;
        }

        await apiPost("update_status", {
            id: correo.id,
            status: statusSelect.value
        });

        actualizarInterfaz();
        mostrarToast("Estado actualizado");
    });

    btnAddNote.addEventListener("click", async function () {
        const correo = obtenerCorreoSeleccionado();

        if (!correo || noteInput.value.trim() === "") {
            return;
        }

        await apiPost("add_note", {
            id: correo.id,
            note: noteInput.value.trim()
        });

        noteInput.value = "";

        actualizarInterfaz();
        mostrarToast("Nota añadida");
    });

    function abrirModalRedaccion(modo, correo = null) {
        composeModal.classList.remove("hidden");
        templateSelect.value = "";

        if (modo === "nuevo") {
            composeTitle.textContent = "Nuevo correo";
            composeTo.value = "";
            composeSubject.value = "";
            composeEditor.innerHTML = "<p>Escribe aquí tu mensaje...</p>";
        }

        if (modo === "responder" && correo) {
            composeTitle.textContent = "Responder correo";
            composeTo.value = correo.email;
            composeSubject.value = "Re: " + correo.asunto;
            composeEditor.innerHTML = `
                <p>Hola ${correo.remitente},</p>
                <p></p>
                <p>Un saludo,<br>Equipo Kitcherry</p>
            `;
        }

        if (modo === "reenviar" && correo) {
            composeTitle.textContent = "Reenviar correo";
            composeTo.value = "";
            composeSubject.value = "Fwd: " + correo.asunto;
            composeEditor.innerHTML = `
                <p></p>
                <hr>
                <p><strong>Mensaje reenviado:</strong></p>
                ${correo.cuerpo}
            `;
        }

        composeEditor.focus();
    }

    function cerrarModalRedaccion() {
        composeModal.classList.add("hidden");
    }

    btnOpenCompose.addEventListener("click", function () {
        abrirModalRedaccion("nuevo");
    });

    btnCloseCompose.addEventListener("click", cerrarModalRedaccion);

    composeModal.addEventListener("click", function (evento) {
        if (evento.target === composeModal) {
            cerrarModalRedaccion();
        }
    });

    btnReply.addEventListener("click", function () {
        const correo = obtenerCorreoSeleccionado();

        if (!correo) {
            return;
        }

        abrirModalRedaccion("responder", correo);
    });

    btnForward.addEventListener("click", function () {
        const correo = obtenerCorreoSeleccionado();

        if (!correo) {
            return;
        }

        abrirModalRedaccion("reenviar", correo);
    });

    templateSelect.addEventListener("change", function () {
        const plantilla = plantillas[templateSelect.value];

        if (plantilla) {
            composeEditor.innerHTML = plantilla;
        }
    });

    toolbarButtons.forEach(function (boton) {
        boton.addEventListener("click", function () {
            document.execCommand(boton.dataset.command, false, null);
            composeEditor.focus();
        });
    });

    btnAddLink.addEventListener("click", function () {
        const url = prompt("Introduce la URL del enlace:");

        if (url) {
            document.execCommand("createLink", false, url);
            composeEditor.focus();
        }
    });

    btnClearFormat.addEventListener("click", function () {
        document.execCommand("removeFormat", false, null);
        composeEditor.focus();
    });

    btnSaveDraft.addEventListener("click", async function () {
        await apiPost("save_draft", {
            to: composeTo.value,
            subject: composeSubject.value,
            body_html: composeEditor.innerHTML
        });

        cerrarModalRedaccion();
        actualizarInterfaz();
        mostrarToast("Borrador guardado");
    });

    composeForm.addEventListener("submit", async function (evento) {
        evento.preventDefault();

        composeHtml.value = composeEditor.innerHTML;

        await apiPost("save_sent_local", {
            to: composeTo.value,
            subject: composeSubject.value,
            body_html: composeHtml.value
        });

        cerrarModalRedaccion();
        carpetaActual = "sent";
        cambiarCarpetaVisual("sent");
        actualizarInterfaz();
        mostrarToast("Correo guardado en enviados");
    });

    folderButtons.forEach(function (boton) {
        boton.addEventListener("click", function () {
            cambiarCarpetaVisual(boton.dataset.folder);
            ocultarDetalle();
            actualizarInterfaz();
        });
    });

    function cambiarCarpetaVisual(carpeta) {
        folderButtons.forEach(function (item) {
            item.classList.remove("active");

            if (item.dataset.folder === carpeta) {
                item.classList.add("active");
            }
        });

        carpetaActual = carpeta;
        correoSeleccionadoId = null;

        currentFolderTitle.textContent = nombresCarpetas[carpetaActual];
        currentFolderSubtitle.textContent = subtitulosCarpetas[carpetaActual];
    }

    statusButtons.forEach(function (boton) {
        boton.addEventListener("click", function () {
            statusButtons.forEach(function (item) {
                item.classList.remove("active");
            });

            boton.classList.add("active");

            estadoActual = boton.dataset.status;
            actualizarInterfaz();
        });
    });

    quickFilter.addEventListener("change", actualizarInterfaz);

    searchForm.addEventListener("submit", function (evento) {
        evento.preventDefault();

        busquedaActual = searchInput.value;
        actualizarInterfaz();
    });

    searchInput.addEventListener("input", function () {
        busquedaActual = searchInput.value;
        actualizarInterfaz();
    });

    btnListView.addEventListener("click", function () {
        vistaActual = "lista";

        btnListView.classList.add("active");
        btnKanbanView.classList.remove("active");

        mailList.classList.remove("hidden");
        kanbanBoard.classList.add("hidden");

        actualizarInterfaz();
    });

    btnKanbanView.addEventListener("click", function () {
        vistaActual = "kanban";

        btnKanbanView.classList.add("active");
        btnListView.classList.remove("active");

        mailList.classList.add("hidden");
        kanbanBoard.classList.remove("hidden");

        actualizarInterfaz();
    });

    btnSync.addEventListener("click", async function () {
        const textoOriginal = syncStatus.textContent;

        syncStatus.textContent = "Actualizando...";

        const respuesta = await apiPost("sync");

        syncStatus.textContent = textoOriginal;

        if (respuesta) {
            actualizarInterfaz();
            mostrarToast(respuesta.message);
        }
    });

    function ocultarDetalle() {
        detailContent.classList.add("hidden");
        emptyState.classList.remove("hidden");
    }

    function actualizarInterfaz() {
        actualizarContadores();

        if (vistaActual === "lista") {
            renderizarLista();
        } else {
            renderizarKanban();
        }

        const correo = obtenerCorreoSeleccionado();

        if (correo) {
            renderizarDetalle(correo);
        }
    }

    prepararKanbanDragDrop();
    actualizarInterfaz();
});