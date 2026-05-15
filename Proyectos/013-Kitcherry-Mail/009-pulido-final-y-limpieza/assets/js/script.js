// ==========================================================
// KITCHERRY MAIL - SCRIPT PRINCIPAL
// Datos cargados desde SQLite mediante PHP.
// Incluye vista de conversación / hilos.
// Mejora: al responder, la respuesta aparece en el hilo sin sincronizar.
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

    const attachmentsPanel = document.getElementById("attachmentsPanel");
    const attachmentList = document.getElementById("attachmentList");

    const noteInput = document.getElementById("noteInput");
    const noteList = document.getElementById("noteList");
    const historyList = document.getElementById("historyList");

    const btnMarkRead = document.getElementById("btnMarkRead");
    const btnArchive = document.getElementById("btnArchive");
    const btnRestoreInbox = document.getElementById("btnRestoreInbox");
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
    const composeSubmitButton = composeForm.querySelector('button[type="submit"]');

    const toast = document.getElementById("toast");

    let carpetaActual = "inbox";
    let estadoActual = "all";
    let busquedaActual = "";
    let vistaActual = "lista";
    let correoSeleccionadoId = null;

    let composeMode = "nuevo";
    let composeSourceId = null;

    let threadPanel = null;
    let threadList = null;
    let threadTotal = null;
    let threadSubtitle = null;
    let ultimoHiloSolicitado = null;

    if (composeSubmitButton) {
        composeSubmitButton.textContent = "Enviar correo";
    }

    crearPanelHiloCorreo();

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

    function escaparHtml(texto) {
        return texto
            .toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function obtenerCorreoSeleccionado() {
        return correos.find(function (correo) {
            return correo.id === correoSeleccionadoId;
        });
    }

    function obtenerCorreosFiltrados() {
        let resultado = correos.filter(function (correo) {
            return String(correo.carpeta).trim() === carpetaActual;
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
        }, 2800);
    }

    function actualizarContadores() {
        countInbox.textContent = correos.filter(c => String(c.carpeta).trim() === "inbox").length;
        countSent.textContent = correos.filter(c => String(c.carpeta).trim() === "sent").length;
        countDrafts.textContent = correos.filter(c => String(c.carpeta).trim() === "drafts").length;
        countArchived.textContent = correos.filter(c => String(c.carpeta).trim() === "archived").length;
        countTrash.textContent = correos.filter(c => String(c.carpeta).trim() === "trash").length;
    }

    function renderizarLista() {
        const lista = obtenerCorreosFiltrados();

        mailList.innerHTML = "";

        if (lista.length === 0) {
            mailList.innerHTML = `
                <div class="mail-list-empty">
                    <h3>No hay correos en esta vista</h3>
                    <p>Pulsa sincronizar para descargar los últimos mensajes disponibles.</p>
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
                        <span class="mail-sender">${escaparHtml(correo.remitente)}</span>
                        <span class="mail-date">${escaparHtml(correo.fecha)}</span>
                        ${correo.tieneAdjuntos ? '<span class="mail-attachment-icon" title="Tiene adjuntos">📎</span>' : ""}
                    </div>
                    ${!correo.leido ? '<span class="unread-dot" title="No leído"></span>' : ""}
                </div>

                <strong class="mail-subject">${escaparHtml(correo.asunto)}</strong>

                <p class="mail-preview">${escaparHtml(correo.resumen)}</p>

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
            if (String(correo.carpeta).trim() === "trash") {
                return;
            }

            let estadoKanban = correo.estado;

            if (String(correo.carpeta).trim() === "archived") {
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
                    ${correo.tieneAdjuntos ? '<span>📎</span>' : ""}
                </div>
                <h4>${escaparHtml(correo.asunto)}</h4>
                <p>${escaparHtml(correo.remitente)}</p>
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

                resetearPanelHilo();
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

        resetearPanelHilo();
        actualizarInterfaz();

        if (estabaSinLeer) {
            await apiPost("update_read", {
                id: correo.id,
                is_read: 1,
                silent: true
            });

            resetearPanelHilo();
            actualizarInterfaz();
        }
    }

    function renderizarDetalle(correo) {
        detailBadges.innerHTML = `
            <span class="badge badge-${correo.estado}">${textoEstado(correo.estado)}</span>
            <span class="badge priority-${correo.prioridad}">${textoPrioridad(correo.prioridad)}</span>
            <span class="badge type-${correo.tipo}">${textoTipo(correo.tipo)}</span>
            ${correo.tieneAdjuntos ? '<span class="badge">📎 Adjuntos</span>' : ""}
        `;

        detailSubject.textContent = correo.asunto;
        detailFrom.textContent = correo.remitente;
        detailEmail.textContent = "<" + correo.email + ">";
        detailDate.textContent = correo.vistaFecha;
        detailBody.innerHTML = correo.cuerpo;

        statusSelect.value = correo.estado;

        actualizarBotonesMovimiento(correo);
        renderizarAdjuntos(correo);
        renderizarNotas(correo);
        renderizarHistorial(correo);
        cargarHiloCorreo(correo.id);
    }

    function actualizarBotonesMovimiento(correo) {
        btnArchive.classList.remove("hidden");
        btnTrash.classList.remove("hidden");
        btnRestoreInbox.classList.add("hidden");

        const carpetaCorreo = String(correo.carpeta).trim();

        if (carpetaCorreo === "archived") {
            btnArchive.classList.add("hidden");
            btnRestoreInbox.classList.remove("hidden");
            btnRestoreInbox.textContent = "Desarchivar";
        }

        if (carpetaCorreo === "trash") {
            btnArchive.classList.add("hidden");
            btnTrash.classList.add("hidden");
            btnRestoreInbox.classList.remove("hidden");
            btnRestoreInbox.textContent = "Restaurar";
        }
    }

    function renderizarAdjuntos(correo) {
        attachmentList.innerHTML = "";

        if (!correo.tieneAdjuntos || !Array.isArray(correo.adjuntos) || correo.adjuntos.length === 0) {
            attachmentsPanel.classList.add("hidden");
            return;
        }

        attachmentsPanel.classList.remove("hidden");

        correo.adjuntos.forEach(function (adjunto) {
            const li = document.createElement("li");

            li.innerHTML = `
                <strong>📎 ${escaparHtml(adjunto.name || "archivo-adjunto")}</strong>
                <span>${escaparHtml(adjunto.sizeLabel || "Tamaño desconocido")}</span>
            `;

            attachmentList.appendChild(li);
        });
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

    function crearPanelHiloCorreo() {
        if (!detailContent) {
            return;
        }

        threadPanel = document.createElement("section");
        threadPanel.className = "thread-panel hidden";
        threadPanel.id = "threadPanel";

        threadPanel.innerHTML = `
            <div class="thread-header">
                <div>
                    <h3>Conversación</h3>
                    <p id="threadSubtitle">Mensajes relacionados con este correo.</p>
                </div>
                <span class="thread-total" id="threadTotal">0</span>
            </div>
            <div class="thread-list" id="threadList">
                <div class="thread-loading">Selecciona un correo para cargar la conversación.</div>
            </div>
        `;

        const notesPanel = detailContent.querySelector(".notes-panel");

        if (notesPanel) {
            detailContent.insertBefore(threadPanel, notesPanel);
        } else {
            detailContent.appendChild(threadPanel);
        }

        threadList = document.getElementById("threadList");
        threadTotal = document.getElementById("threadTotal");
        threadSubtitle = document.getElementById("threadSubtitle");
    }

    async function cargarHiloCorreo(correoId) {
        if (!threadPanel || !threadList || !threadTotal) {
            return;
        }

        if (ultimoHiloSolicitado === correoId && threadPanel.dataset.loaded === "1") {
            return;
        }

        ultimoHiloSolicitado = correoId;
        threadPanel.dataset.loaded = "0";
        threadPanel.classList.remove("hidden");
        threadTotal.textContent = "...";
        threadSubtitle.textContent = "Cargando mensajes relacionados...";
        threadList.innerHTML = `<div class="thread-loading">Cargando conversación...</div>`;

        const respuesta = await apiPost("get_thread", {
            id: correoId
        });

        if (!respuesta || !Array.isArray(respuesta.hilo)) {
            threadTotal.textContent = "0";
            threadSubtitle.textContent = "No se pudo cargar la conversación.";
            threadList.innerHTML = `<div class="thread-empty">No se pudo cargar la conversación.</div>`;
            return;
        }

        if (correoSeleccionadoId !== correoId) {
            return;
        }

        threadPanel.dataset.loaded = "1";
        renderizarHilo(respuesta.hilo);
    }

    function renderizarHilo(hilo) {
        if (!threadPanel || !threadList || !threadTotal) {
            return;
        }

        threadPanel.classList.remove("hidden");
        threadTotal.textContent = hilo.length;

        if (hilo.length === 0) {
            threadSubtitle.textContent = "No hay mensajes relacionados todavía.";
            threadList.innerHTML = `<div class="thread-empty">No hay conversación para este correo.</div>`;
            return;
        }

        if (hilo.length === 1) {
            threadSubtitle.textContent = "Solo hay un mensaje en esta conversación.";
        } else {
            threadSubtitle.textContent = "Mensajes recibidos y enviados relacionados.";
        }

        threadList.innerHTML = "";

        hilo.forEach(function (mensaje) {
            const article = document.createElement("article");
            const direccion = mensaje.direccion === "enviado" ? "sent" : "received";
            const textoDireccion = mensaje.direccion === "enviado" ? "Enviado" : "Recibido";

            article.className = `thread-message is-${direccion}`;

            if (mensaje.actual) {
                article.classList.add("is-current");
            }

            const replyInfo = mensaje.respondeA
                ? `
                    <div class="thread-reply-info">
                        Responde a: <strong>${escaparHtml(mensaje.respondeA.asunto)}</strong>
                        · ${escaparHtml(mensaje.respondeA.remitente)}
                        · ${escaparHtml(mensaje.respondeA.fecha)}
                    </div>
                `
                : "";

            article.innerHTML = `
                <button class="thread-message-top" type="button" data-thread-id="${mensaje.id}">
                    <div class="thread-message-row">
                        <div class="thread-message-title">
                            <span class="thread-direction ${direccion}">${textoDireccion}</span>
                            <strong class="thread-subject">${escaparHtml(mensaje.asunto)}</strong>
                        </div>
                        <span class="thread-date">${escaparHtml(mensaje.fecha)}</span>
                    </div>

                    <div class="thread-meta">
                        <strong>${escaparHtml(mensaje.remitente)}</strong>
                        &lt;${escaparHtml(mensaje.email)}&gt;
                        <br>
                        Para: ${escaparHtml(mensaje.destinatario)}
                    </div>

                    ${replyInfo}
                </button>

                <div class="thread-message-body">
                    ${mensaje.cuerpo}
                </div>
            `;

            const botonTop = article.querySelector(".thread-message-top");

            botonTop.addEventListener("click", function () {
                const idMensaje = Number(botonTop.dataset.threadId);

                if (idMensaje && idMensaje !== correoSeleccionadoId) {
                    const existe = correos.some(function (correo) {
                        return correo.id === idMensaje;
                    });

                    if (existe) {
                        seleccionarCorreo(idMensaje);
                    }
                }
            });

            threadList.appendChild(article);
        });
    }

    function resetearPanelHilo() {
        ultimoHiloSolicitado = null;

        if (!threadPanel || !threadList || !threadTotal) {
            return;
        }

        threadPanel.dataset.loaded = "0";
        threadPanel.classList.add("hidden");
        threadTotal.textContent = "0";
        threadSubtitle.textContent = "Mensajes relacionados con este correo.";
        threadList.innerHTML = `<div class="thread-loading">Selecciona un correo para cargar la conversación.</div>`;
    }

    function recargarHiloActual() {
        const correo = obtenerCorreoSeleccionado();

        if (!correo) {
            return;
        }

        resetearPanelHilo();
        cargarHiloCorreo(correo.id);
    }

    btnMarkRead.addEventListener("click", async function () {
        const correo = obtenerCorreoSeleccionado();

        if (!correo) {
            return;
        }

        const nuevoEstadoLectura = !correo.leido;

        const respuesta = await apiPost("update_read", {
            id: correo.id,
            is_read: nuevoEstadoLectura ? 1 : 0
        });

        if (respuesta) {
            resetearPanelHilo();
            actualizarInterfaz();
            mostrarToast(nuevoEstadoLectura ? "Correo marcado como leído" : "Correo marcado como no leído");
        }
    });

    btnArchive.addEventListener("click", async function () {
        const correo = obtenerCorreoSeleccionado();

        if (!correo) {
            return;
        }

        const respuesta = await apiPost("move_folder", {
            id: correo.id,
            folder: "archived",
            status: "archivado"
        });

        if (respuesta) {
            correoSeleccionadoId = null;
            ocultarDetalle();
            actualizarInterfaz();
            mostrarToast("Correo archivado");
        }
    });

    btnRestoreInbox.addEventListener("click", async function () {
        const correo = obtenerCorreoSeleccionado();

        if (!correo) {
            return;
        }

        const respuesta = await apiPost("restore_inbox", {
            id: correo.id
        });

        if (respuesta) {
            correoSeleccionadoId = null;
            ocultarDetalle();
            actualizarInterfaz();
            mostrarToast(respuesta.message);
        }
    });

    btnTrash.addEventListener("click", async function () {
        const correo = obtenerCorreoSeleccionado();

        if (!correo) {
            return;
        }

        const respuesta = await apiPost("move_folder", {
            id: correo.id,
            folder: "trash"
        });

        if (respuesta) {
            correoSeleccionadoId = null;
            ocultarDetalle();
            actualizarInterfaz();
            mostrarToast("Correo enviado a papelera");
        }
    });

    statusSelect.addEventListener("change", async function () {
        const correo = obtenerCorreoSeleccionado();

        if (!correo) {
            return;
        }

        const respuesta = await apiPost("update_status", {
            id: correo.id,
            status: statusSelect.value
        });

        if (respuesta) {
            resetearPanelHilo();
            actualizarInterfaz();
            mostrarToast("Estado actualizado");
        }
    });

    btnAddNote.addEventListener("click", async function () {
        const correo = obtenerCorreoSeleccionado();

        if (!correo || noteInput.value.trim() === "") {
            return;
        }

        const respuesta = await apiPost("add_note", {
            id: correo.id,
            note: noteInput.value.trim()
        });

        if (respuesta) {
            noteInput.value = "";
            resetearPanelHilo();
            actualizarInterfaz();
            mostrarToast("Nota añadida");
        }
    });

    function abrirModalRedaccion(modo, correo = null) {
        composeModal.classList.remove("hidden");
        templateSelect.value = "";

        composeMode = modo;
        composeSourceId = correo ? correo.id : null;

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
                <p>Hola ${escaparHtml(correo.remitente)},</p>
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
        composeMode = "nuevo";
        composeSourceId = null;
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
        const respuesta = await apiPost("save_draft", {
            to: composeTo.value,
            subject: composeSubject.value,
            body_html: composeEditor.innerHTML
        });

        if (respuesta) {
            cerrarModalRedaccion();
            resetearPanelHilo();
            actualizarInterfaz();
            mostrarToast("Borrador guardado");
        }
    });

    composeForm.addEventListener("submit", async function (evento) {
        evento.preventDefault();

        composeHtml.value = composeEditor.innerHTML;

        const modoAntesDeEnviar = composeMode;
        const sourceIdAntesDeEnviar = composeSourceId;
        const carpetaAntesDeEnviar = carpetaActual;

        if (composeSubmitButton) {
            composeSubmitButton.disabled = true;
            composeSubmitButton.textContent = "Enviando...";
        }

        const respuesta = await apiPost("send_smtp", {
            to: composeTo.value,
            subject: composeSubject.value,
            body_html: composeHtml.value,
            mode: composeMode,
            source_id: composeSourceId
        });

        if (composeSubmitButton) {
            composeSubmitButton.disabled = false;
            composeSubmitButton.textContent = "Enviar correo";
        }

        if (!respuesta) {
            return;
        }

        cerrarModalRedaccion();

        if (modoAntesDeEnviar === "responder" && sourceIdAntesDeEnviar) {
            carpetaActual = carpetaAntesDeEnviar;
            correoSeleccionadoId = sourceIdAntesDeEnviar;

            cambiarCarpetaVisual(carpetaActual);
            resetearPanelHilo();
            actualizarInterfaz();
            recargarHiloActual();

            mostrarToast("Respuesta enviada y añadida a la conversación");
            return;
        }

        if (modoAntesDeEnviar === "reenviar" && sourceIdAntesDeEnviar) {
            carpetaActual = carpetaAntesDeEnviar;
            correoSeleccionadoId = sourceIdAntesDeEnviar;

            cambiarCarpetaVisual(carpetaActual);
            resetearPanelHilo();
            actualizarInterfaz();
            recargarHiloActual();

            mostrarToast("Correo reenviado y añadido a la conversación");
            return;
        }

        carpetaActual = "sent";
        cambiarCarpetaVisual("sent");
        resetearPanelHilo();
        actualizarInterfaz();
        mostrarToast("Correo enviado correctamente");
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
            resetearPanelHilo();
            actualizarInterfaz();
        });
    });

    quickFilter.addEventListener("change", function () {
        resetearPanelHilo();
        actualizarInterfaz();
    });

    searchForm.addEventListener("submit", function (evento) {
        evento.preventDefault();

        busquedaActual = searchInput.value;
        resetearPanelHilo();
        actualizarInterfaz();
    });

    searchInput.addEventListener("input", function () {
        busquedaActual = searchInput.value;
        resetearPanelHilo();
        actualizarInterfaz();
    });

    btnListView.addEventListener("click", function () {
        vistaActual = "lista";

        btnListView.classList.add("active");
        btnKanbanView.classList.remove("active");

        mailList.classList.remove("hidden");
        kanbanBoard.classList.add("hidden");

        resetearPanelHilo();
        actualizarInterfaz();
    });

    btnKanbanView.addEventListener("click", function () {
        vistaActual = "kanban";

        btnKanbanView.classList.add("active");
        btnListView.classList.remove("active");

        mailList.classList.add("hidden");
        kanbanBoard.classList.remove("hidden");

        resetearPanelHilo();
        actualizarInterfaz();
    });

    btnSync.addEventListener("click", async function () {
        const textoOriginal = syncStatus.textContent;

        syncStatus.textContent = "Sincronizando...";

        const respuesta = await apiPost("sync");

        syncStatus.textContent = textoOriginal;

        if (respuesta) {
            resetearPanelHilo();
            actualizarInterfaz();
            mostrarToast(respuesta.message);
        }
    });

    function ocultarDetalle() {
        detailContent.classList.add("hidden");
        emptyState.classList.remove("hidden");
        resetearPanelHilo();
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
            emptyState.classList.add("hidden");
            detailContent.classList.remove("hidden");
            renderizarDetalle(correo);
        }
    }

    prepararKanbanDragDrop();
    actualizarInterfaz();
});