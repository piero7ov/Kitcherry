// ==========================================================
// KITCHERRY MAIL - SCRIPT PRINCIPAL
// Interfaz estática con datos de ejemplo.
// ==========================================================

document.addEventListener("DOMContentLoaded", function () {
    // ======================================================
    // DATOS DE EJEMPLO
    // ======================================================

    let correos = [
        {
            id: 1,
            carpeta: "inbox",
            remitente: "Laura García",
            email: "laura.garcia@email.com",
            destinatario: "info@kitcherry.com",
            asunto: "Consulta para una reserva de grupo",
            fecha: "Hoy · 10:32",
            vistaFecha: "Hoy a las 10:32",
            resumen: "Hola, quería consultar disponibilidad para una cena de 12 personas este sábado...",
            cuerpo: `
                <p>Hola,</p>
                <p>Quería consultar disponibilidad para una cena de 12 personas este sábado por la noche.</p>
                <p>También nos gustaría saber si existe alguna opción de menú cerrado para grupos y si se pueden adaptar algunos platos por alergias.</p>
                <p>Gracias.</p>
            `,
            leido: false,
            estado: "pendiente",
            prioridad: "alta",
            tipo: "cliente",
            notas: [
                "Revisar disponibilidad antes de responder."
            ],
            historial: [
                "Correo recibido.",
                "Marcado como pendiente."
            ]
        },
        {
            id: 2,
            carpeta: "inbox",
            remitente: "Proveedor Central",
            email: "pedidos@proveedorcentral.com",
            destinatario: "info@kitcherry.com",
            asunto: "Factura y confirmación de entrega",
            fecha: "Hoy · 09:15",
            vistaFecha: "Hoy a las 09:15",
            resumen: "Adjuntamos factura correspondiente al último pedido y confirmamos entrega mañana...",
            cuerpo: `
                <p>Buenos días,</p>
                <p>Adjuntamos factura correspondiente al último pedido realizado.</p>
                <p>Confirmamos que la entrega está prevista para mañana por la mañana.</p>
                <p>Un saludo.</p>
            `,
            leido: true,
            estado: "revision",
            prioridad: "media",
            tipo: "proveedor",
            notas: [],
            historial: [
                "Correo recibido.",
                "Marcado en revisión."
            ]
        },
        {
            id: 3,
            carpeta: "inbox",
            remitente: "Contacto Web",
            email: "contacto@empresa-demo.com",
            destinatario: "info@kitcherry.com",
            asunto: "Solicitud de información sobre Kitcherry",
            fecha: "Ayer · 18:44",
            vistaFecha: "Ayer a las 18:44",
            resumen: "Estamos interesados en una herramienta para ordenar consultas de clientes...",
            cuerpo: `
                <p>Hola,</p>
                <p>Estamos interesados en una herramienta para ordenar consultas de clientes y mejorar la gestión de respuestas.</p>
                <p>Nos gustaría recibir más información sobre las funcionalidades principales.</p>
                <p>Gracias.</p>
            `,
            leido: false,
            estado: "importante",
            prioridad: "alta",
            tipo: "comercial",
            notas: [
                "Posible contacto comercial."
            ],
            historial: [
                "Correo recibido.",
                "Marcado como importante."
            ]
        },
        {
            id: 4,
            carpeta: "inbox",
            remitente: "Equipo Interno",
            email: "equipo@kitcherry.com",
            destinatario: "info@kitcherry.com",
            asunto: "Revisión de plantillas de respuesta",
            fecha: "Ayer · 12:20",
            vistaFecha: "Ayer a las 12:20",
            resumen: "Falta revisar las plantillas de confirmación, agradecimiento y solicitud de datos...",
            cuerpo: `
                <p>Buenas,</p>
                <p>Falta revisar las plantillas de confirmación, agradecimiento y solicitud de datos.</p>
                <p>Sería recomendable dejarlas preparadas para poder usarlas desde el editor.</p>
            `,
            leido: true,
            estado: "respondido",
            prioridad: "baja",
            tipo: "interno",
            notas: [],
            historial: [
                "Correo recibido.",
                "Marcado como respondido."
            ]
        },
        {
            id: 5,
            carpeta: "sent",
            remitente: "Kitcherry Mail",
            email: "info@kitcherry.com",
            destinatario: "contacto@empresa-demo.com",
            asunto: "Re: Solicitud de información sobre Kitcherry",
            fecha: "Hoy · 11:05",
            vistaFecha: "Hoy a las 11:05",
            resumen: "Gracias por contactar con Kitcherry. Te enviamos información sobre la herramienta...",
            cuerpo: `
                <p>Hola,</p>
                <p>Gracias por contactar con Kitcherry.</p>
                <p>Te enviamos información sobre nuestra herramienta de gestión de comunicaciones.</p>
                <p>Un saludo.</p>
            `,
            leido: true,
            estado: "respondido",
            prioridad: "media",
            tipo: "comercial",
            notas: [],
            historial: [
                "Correo enviado."
            ]
        },
        {
            id: 6,
            carpeta: "drafts",
            remitente: "Kitcherry Mail",
            email: "info@kitcherry.com",
            destinatario: "laura.garcia@email.com",
            asunto: "Respuesta pendiente sobre reserva",
            fecha: "Hoy · 11:30",
            vistaFecha: "Hoy a las 11:30",
            resumen: "Hola Laura, estamos revisando disponibilidad para el sábado...",
            cuerpo: `
                <p>Hola Laura,</p>
                <p>Estamos revisando disponibilidad para el sábado.</p>
                <p>Te confirmamos lo antes posible.</p>
            `,
            leido: true,
            estado: "revision",
            prioridad: "media",
            tipo: "cliente",
            notas: [],
            historial: [
                "Borrador creado."
            ]
        }
    ];

    const plantillas = {
        recibido: `
            <p>Hola,</p>
            <p>Gracias por contactar con nosotros. Hemos recibido tu mensaje correctamente y lo revisaremos lo antes posible.</p>
            <p>Un saludo,<br>Equipo Kitcherry</p>
        `,
        info: `
            <p>Hola,</p>
            <p>Gracias por tu mensaje. Para poder ayudarte mejor, necesitaríamos que nos facilites algunos datos adicionales.</p>
            <p>Un saludo,<br>Equipo Kitcherry</p>
        `,
        gracias: `
            <p>Hola,</p>
            <p>Muchas gracias por escribirnos. Quedamos atentos a cualquier otra consulta.</p>
            <p>Un saludo,<br>Equipo Kitcherry</p>
        `
    };

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

    // ======================================================
    // ELEMENTOS DEL DOM
    // ======================================================

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

    // ======================================================
    // ESTADO DE LA INTERFAZ
    // ======================================================

    let carpetaActual = "inbox";
    let estadoActual = "all";
    let busquedaActual = "";
    let vistaActual = "lista";
    let correoSeleccionadoId = null;

    // ======================================================
    // FUNCIONES DE UTILIDAD
    // ======================================================

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

    function anadirHistorial(correo, accion) {
        correo.historial.unshift(accion);
    }

    // ======================================================
    // CONTADORES
    // ======================================================

    function actualizarContadores() {
        countInbox.textContent = correos.filter(c => c.carpeta === "inbox").length;
        countSent.textContent = correos.filter(c => c.carpeta === "sent").length;
        countDrafts.textContent = correos.filter(c => c.carpeta === "drafts").length;
        countArchived.textContent = correos.filter(c => c.carpeta === "archived").length;
        countTrash.textContent = correos.filter(c => c.carpeta === "trash").length;
    }

    // ======================================================
    // RENDER DE LISTA
    // ======================================================

    function renderizarLista() {
        const lista = obtenerCorreosFiltrados();

        mailList.innerHTML = "";

        if (lista.length === 0) {
            mailList.innerHTML = `
                <div class="mail-list-empty">
                    <h3>No hay correos en esta vista</h3>
                    <p>Prueba cambiando de carpeta, estado o filtro.</p>
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

    // ======================================================
    // RENDER DE KANBAN
    // ======================================================

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

            zona.addEventListener("drop", function (evento) {
                evento.preventDefault();
                zona.classList.remove("drag-over");

                const correoId = Number(evento.dataTransfer.getData("text/plain"));
                const columna = zona.closest(".kanban-column");
                const nuevoEstado = columna.dataset.kanbanStatus;

                const correo = correos.find(function (item) {
                    return item.id === correoId;
                });

                if (!correo) {
                    return;
                }

                if (nuevoEstado === "archivado") {
                    correo.carpeta = "archived";
                    correo.estado = "archivado";
                    anadirHistorial(correo, "Correo archivado desde la vista Kanban.");
                } else {
                    correo.estado = nuevoEstado;
                    anadirHistorial(correo, "Estado cambiado a " + textoEstado(nuevoEstado) + ".");
                }

                actualizarInterfaz();
                mostrarToast("Correo actualizado");
            });
        });
    }

    // ======================================================
    // DETALLE DEL CORREO
    // ======================================================

    function seleccionarCorreo(id) {
        correoSeleccionadoId = id;

        const correo = obtenerCorreoSeleccionado();

        if (!correo) {
            return;
        }

        correo.leido = true;

        emptyState.classList.add("hidden");
        detailContent.classList.remove("hidden");

        renderizarDetalle(correo);
        actualizarInterfaz();
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

    // ======================================================
    // ACCIONES DEL CORREO
    // ======================================================

    btnMarkRead.addEventListener("click", function () {
        const correo = obtenerCorreoSeleccionado();

        if (!correo) {
            return;
        }

        correo.leido = !correo.leido;
        anadirHistorial(correo, correo.leido ? "Marcado como leído." : "Marcado como no leído.");

        renderizarDetalle(correo);
        actualizarInterfaz();

        mostrarToast(correo.leido ? "Correo marcado como leído" : "Correo marcado como no leído");
    });

    btnArchive.addEventListener("click", function () {
        const correo = obtenerCorreoSeleccionado();

        if (!correo) {
            return;
        }

        correo.carpeta = "archived";
        correo.estado = "archivado";
        anadirHistorial(correo, "Correo archivado.");

        actualizarInterfaz();
        renderizarDetalle(correo);

        mostrarToast("Correo archivado");
    });

    btnTrash.addEventListener("click", function () {
        const correo = obtenerCorreoSeleccionado();

        if (!correo) {
            return;
        }

        correo.carpeta = "trash";
        anadirHistorial(correo, "Correo enviado a papelera.");

        actualizarInterfaz();
        renderizarDetalle(correo);

        mostrarToast("Correo enviado a papelera");
    });

    statusSelect.addEventListener("change", function () {
        const correo = obtenerCorreoSeleccionado();

        if (!correo) {
            return;
        }

        correo.estado = statusSelect.value;

        if (statusSelect.value === "archivado") {
            correo.carpeta = "archived";
        }

        anadirHistorial(correo, "Estado cambiado a " + textoEstado(statusSelect.value) + ".");

        renderizarDetalle(correo);
        actualizarInterfaz();

        mostrarToast("Estado actualizado");
    });

    btnAddNote.addEventListener("click", function () {
        const correo = obtenerCorreoSeleccionado();

        if (!correo || noteInput.value.trim() === "") {
            return;
        }

        correo.notas.unshift(noteInput.value.trim());
        anadirHistorial(correo, "Nota interna añadida.");

        noteInput.value = "";

        renderizarDetalle(correo);
        actualizarInterfaz();

        mostrarToast("Nota añadida");
    });

    // ======================================================
    // MODAL DE REDACCIÓN
    // ======================================================

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

    btnSaveDraft.addEventListener("click", function () {
        const nuevoId = Date.now();

        correos.unshift({
            id: nuevoId,
            carpeta: "drafts",
            remitente: "Kitcherry Mail",
            email: "info@kitcherry.com",
            destinatario: composeTo.value || "Sin destinatario",
            asunto: composeSubject.value || "Sin asunto",
            fecha: "Ahora",
            vistaFecha: "Ahora",
            resumen: composeEditor.textContent.trim().slice(0, 90) || "Borrador sin contenido",
            cuerpo: composeEditor.innerHTML,
            leido: true,
            estado: "revision",
            prioridad: "media",
            tipo: "interno",
            notas: [],
            historial: [
                "Borrador guardado."
            ]
        });

        cerrarModalRedaccion();
        actualizarInterfaz();

        mostrarToast("Borrador guardado");
    });

    composeForm.addEventListener("submit", function (evento) {
        evento.preventDefault();

        composeHtml.value = composeEditor.innerHTML;

        const nuevoId = Date.now();

        correos.unshift({
            id: nuevoId,
            carpeta: "sent",
            remitente: "Kitcherry Mail",
            email: "info@kitcherry.com",
            destinatario: composeTo.value || "Sin destinatario",
            asunto: composeSubject.value || "Sin asunto",
            fecha: "Ahora",
            vistaFecha: "Ahora",
            resumen: composeEditor.textContent.trim().slice(0, 90) || "Mensaje sin contenido",
            cuerpo: composeEditor.innerHTML,
            leido: true,
            estado: "respondido",
            prioridad: "media",
            tipo: "interno",
            notas: [],
            historial: [
                "Correo enviado."
            ]
        });

        cerrarModalRedaccion();
        actualizarInterfaz();

        mostrarToast("Correo enviado");
    });

    // ======================================================
    // FILTROS Y NAVEGACIÓN
    // ======================================================

    folderButtons.forEach(function (boton) {
        boton.addEventListener("click", function () {
            folderButtons.forEach(function (item) {
                item.classList.remove("active");
            });

            boton.classList.add("active");

            carpetaActual = boton.dataset.folder;
            correoSeleccionadoId = null;

            currentFolderTitle.textContent = nombresCarpetas[carpetaActual];
            currentFolderSubtitle.textContent = subtitulosCarpetas[carpetaActual];

            ocultarDetalle();
            actualizarInterfaz();
        });
    });

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

    btnSync.addEventListener("click", function () {
        syncStatus.textContent = "Actualizando...";

        setTimeout(function () {
            syncStatus.textContent = "Bandeja actualizada";
            mostrarToast("Bandeja actualizada");
        }, 900);
    });

    function ocultarDetalle() {
        detailContent.classList.add("hidden");
        emptyState.classList.remove("hidden");
    }

    // ======================================================
    // ACTUALIZACIÓN GENERAL
    // ======================================================

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

    // ======================================================
    // INICIO
    // ======================================================

    prepararKanbanDragDrop();
    actualizarInterfaz();
});