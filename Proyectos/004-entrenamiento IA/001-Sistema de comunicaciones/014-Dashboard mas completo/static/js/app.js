const loadingSection = document.getElementById("loading-section");
const errorSection = document.getElementById("error-section");
const errorMessage = document.getElementById("error-message");

const results = document.getElementById("results");
const dashboardView = document.getElementById("dashboard-view");
const trashView = document.getElementById("trash-view");
const detailView = document.getElementById("detail-view");

const refreshForm = document.getElementById("refresh-form");
const cantidadInput = document.getElementById("cantidad");

const goDashboard = document.getElementById("go-dashboard");
const navDashboard = document.getElementById("nav-dashboard");
const navTrash = document.getElementById("nav-papelera");
const btnEmptyTrash = document.getElementById("btn-empty-trash");
const btnBack = document.getElementById("btn-back");

const dashTotal = document.getElementById("dash-total");
const dashPendiente = document.getElementById("dash-pendiente");
const dashRespondido = document.getElementById("dash-respondido");
const dashArchivado = document.getElementById("dash-archivado");
const dashPapelera = document.getElementById("dash-papelera");

const quickHighPriority = document.getElementById("quick-high-priority");
const quickPendingBookings = document.getElementById("quick-pending-bookings");
const quickAttachments = document.getElementById("quick-attachments");
const quickAllergens = document.getElementById("quick-allergens");
const dashboardMovements = document.getElementById("dashboard-movements");
const dashboardChart = document.getElementById("dashboard-chart");

const trashScreenCount = document.getElementById("count-trash-screen");
const trashScreenList = document.getElementById("list-trash-screen");

const detailPriorityWrap = document.getElementById("detail-priority-wrap");
const detailSubject = document.getElementById("detail-subject");
const detailFrom = document.getElementById("detail-from");
const detailDate = document.getElementById("detail-date");
const detailSummary = document.getElementById("detail-summary");
const detailGeneralReason = document.getElementById("detail-general-reason");
const detailHostReason = document.getElementById("detail-host-reason");
const detailPriorityReason = document.getElementById("detail-priority-reason");
const detailBody = document.getElementById("detail-body");
const detailBodySource = document.getElementById("detail-body-source");

const detailHostBlock = document.getElementById("detail-host-block");
const detailPriorityBlock = document.getElementById("detail-priority-block");
const detailDetectedBlock = document.getElementById("detail-detected-block");
const detailDetectedData = document.getElementById("detail-detected-data");
const detailMissingBlock = document.getElementById("detail-missing-block");
const detailMissingData = document.getElementById("detail-missing-data");
const detailExtractionBlock = document.getElementById("detail-extraction-block");
const detailExtractionNotes = document.getElementById("detail-extraction-notes");
const detailAttachmentsBlock = document.getElementById("detail-attachments-block");
const detailAttachmentsData = document.getElementById("detail-attachments-data");

const detailState = document.getElementById("detail-state");
const detailCreated = document.getElementById("detail-created");
const detailDraftDate = document.getElementById("detail-draft-date");
const detailAnsweredDate = document.getElementById("detail-answered-date");
const detailArchivedDate = document.getElementById("detail-archived-date");
const detailTrashDate = document.getElementById("detail-trash-date");
const detailHistory = document.getElementById("detail-history");

const detailSentReplyBlock = document.getElementById("detail-sent-reply-block");
const detailSentReply = document.getElementById("detail-sent-reply");

const detailReplyTextarea = document.getElementById("detail-reply-textarea");
const detailReplyStatus = document.getElementById("detail-reply-status");
const btnGenerateReply = document.getElementById("btn-generate-reply");
const btnSendReply = document.getElementById("btn-send-reply");

const btnMarkPending = document.getElementById("btn-mark-pending");
const btnArchive = document.getElementById("btn-archive");
const btnTrash = document.getElementById("btn-trash");
const btnRestore = document.getElementById("btn-restore");

const filterButtons = document.querySelectorAll(".filter-btn");
const sideNavLinks = document.querySelectorAll('.side-nav a[href]');

let correoActual = null;
let filtroActual = "todos";
let ultimoDataset = null;
let currentScreen = "dashboard";
let lastResultsSection = "#importantes";

/* =========================
   HELPERS
========================= */
function escapeHtml(text) {
    if (text === null || text === undefined) return "";
    return String(text)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function priorityClass(priority) {
    return `priority-${Number(priority ?? 0)}`;
}

function createPriorityBadge(priority) {
    if (priority === null || priority === undefined) return "";
    return `<div class="mail-priority ${priorityClass(priority)}">${priority}/10</div>`;
}

function formatBytes(bytes) {
    const n = Number(bytes || 0);
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
    return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

function getBodySourceLabel(source) {
    if (source === "text/plain") return "Origen del contenido: texto plano";
    if (source === "text/html") return "Origen del contenido: HTML convertido a texto";
    return "Origen del contenido: desconocido";
}

function formatDateField(value) {
    return value || "—";
}

function stateLabel(value) {
    const map = {
        pendiente: "Pendiente",
        respondido: "Respondido",
        archivado: "Archivado",
        papelera: "Papelera"
    };
    return map[value] || value || "Pendiente";
}

function actionLabel(value) {
    const map = {
        correo_detectado: "Correo detectado",
        borrador_generado: "Borrador generado",
        correo_enviado: "Correo enviado",
        correo_archivado: "Correo archivado",
        enviado_a_papelera: "Enviado a papelera",
        recuperado_de_papelera: "Recuperado de papelera",
        marcado_como_pendiente: "Marcado como pendiente",
        marcado_como_respondido: "Marcado como respondido"
    };
    return map[value] || value || "Movimiento";
}

function hideAllViews() {
    results.classList.add("hidden");
    dashboardView.classList.add("hidden");
    trashView.classList.add("hidden");
    detailView.classList.add("hidden");
}

function setActiveNav(targetId) {
    sideNavLinks.forEach(link => {
        link.classList.toggle("active", link.id === targetId);
    });
}

function filterByState(emails, state) {
    if (state === "todos") return emails;
    return emails.filter(email => (email.tracking?.estado || "pendiente") === state);
}

function getAllDatasetKeys() {
    return [
        "automaticos",
        "importantes",
        "reservas",
        "horario_ubicacion",
        "carta_alergenos_servicios",
        "otro_cliente",
        "papelera"
    ];
}

function cloneEmail(email) {
    return JSON.parse(JSON.stringify(email));
}

/* =========================
   DASHBOARD LOCAL
========================= */
function getUniqueEmailsFromDataset() {
    if (!ultimoDataset) return [];
    const allEmails = [];

    getAllDatasetKeys().forEach(key => {
        const list = ultimoDataset[key] || [];
        list.forEach(item => allEmails.push(item));
    });

    const map = new Map();
    allEmails.forEach(email => {
        if (!map.has(email.uid)) {
            map.set(email.uid, email);
        }
    });

    return Array.from(map.values());
}

function computeDashboardStatsFromDataset() {
    const emails = getUniqueEmailsFromDataset();
    const stats = {
        total: 0,
        pendiente: 0,
        respondido: 0,
        archivado: 0,
        papelera: 0
    };

    emails.forEach(email => {
        stats.total += 1;
        const estado = email.tracking?.estado || "pendiente";
        if (estado in stats) {
            stats[estado] += 1;
        }
    });

    return stats;
}

function computeAttentionStatsFromDataset() {
    const emails = getUniqueEmailsFromDataset();

    const visibles = emails.filter(email => (email.tracking?.estado || "pendiente") !== "papelera");

    return {
        pendientesAltaPrioridad: visibles.filter(email =>
            (email.tracking?.estado || "pendiente") === "pendiente" &&
            Number(email.priority || 0) >= 8
        ).length,

        reservasPendientes: visibles.filter(email =>
            (email.tracking?.estado || "pendiente") === "pendiente" &&
            email.category === "reserva"
        ).length,

        correosConAdjuntos: visibles.filter(email =>
            Array.isArray(email.attachments) && email.attachments.length > 0
        ).length,

        consultasAlergenos: visibles.filter(email =>
            email.category === "carta_alergenos_servicios"
        ).length
    };
}

function computeMovementsFromDataset() {
    const emails = getUniqueEmailsFromDataset();
    const movements = [];

    emails.forEach(email => {
        const history = Array.isArray(email.tracking?.historial) ? email.tracking.historial : [];
        history.forEach(item => {
            movements.push({
                accion: item.accion || "",
                fecha: item.fecha || "",
                subject: email.subject || ""
            });
        });
    });

    movements.sort((a, b) => (b.fecha || "").localeCompare(a.fecha || ""));
    return movements.slice(0, 5);
}

function computeTypeDistributionFromDataset() {
    return {
        "Reservas": (ultimoDataset?.reservas || []).length,
        "Horario y ubicación": (ultimoDataset?.horario_ubicacion || []).length,
        "Carta / alérgenos / servicios": (ultimoDataset?.carta_alergenos_servicios || []).length,
        "Otros": (ultimoDataset?.otro_cliente || []).length,
        "Automáticos": (ultimoDataset?.automaticos || []).length
    };
}

function renderDashboardLocally() {
    if (!ultimoDataset) return;

    const stats = computeDashboardStatsFromDataset();
    ultimoDataset.dashboard = stats;
    updateDashboard(stats);

    const attention = computeAttentionStatsFromDataset();
    quickHighPriority.textContent = attention.pendientesAltaPrioridad;
    quickPendingBookings.textContent = attention.reservasPendientes;
    quickAttachments.textContent = attention.correosConAdjuntos;
    quickAllergens.textContent = attention.consultasAlergenos;

    const movements = computeMovementsFromDataset();
    if (!movements.length) {
        dashboardMovements.innerHTML = `<div class="empty-box">Sin movimientos recientes.</div>`;
    } else {
        dashboardMovements.innerHTML = movements.map(item => `
            <div class="movement-item">
                <strong>${escapeHtml(actionLabel(item.accion))}</strong>
                <span>${escapeHtml(item.fecha)}</span>
            </div>
        `).join("");
    }

    const distribution = computeTypeDistributionFromDataset();
    const values = Object.values(distribution);
    const max = Math.max(...values, 1);

    if (!values.some(v => v > 0)) {
        dashboardChart.innerHTML = `<div class="empty-box">Sin datos para mostrar.</div>`;
    } else {
        dashboardChart.innerHTML = Object.entries(distribution).map(([label, value]) => {
            const width = Math.max((value / max) * 100, value > 0 ? 8 : 0);
            return `
                <div class="chart-row">
                    <div class="chart-row-head">
                        <span>${escapeHtml(label)}</span>
                        <strong>${value}</strong>
                    </div>
                    <div class="chart-track">
                        <div class="chart-fill" style="width:${width}%"></div>
                    </div>
                </div>
            `;
        }).join("");
    }
}

/* =========================
   RENDER LISTAS
========================= */
function createMailRow(email, { important = false } = {}) {
    const encoded = encodeURIComponent(JSON.stringify(email));
    const attachmentCount = Array.isArray(email.attachments) ? email.attachments.length : 0;
    const estado = stateLabel(email.tracking?.estado);

    return `
        <article class="mail-row ${important ? "important" : ""}" data-email="${encoded}">
            <div class="mail-row-top">
                <div class="mail-row-main">
                    ${email.priority !== null && email.priority !== undefined ? createPriorityBadge(email.priority) : ""}
                    <div class="mail-row-meta">
                        <h4>${escapeHtml(email.subject)}</h4>
                        <p class="from">${escapeHtml(email.from)}</p>
                    </div>
                </div>
                <div class="mail-row-date">${escapeHtml(email.date)}</div>
            </div>
            <div class="mail-row-summary">${escapeHtml(email.summary)}</div>
            <div class="mail-row-extra">
                Estado: ${escapeHtml(estado)}
                ${attachmentCount ? ` · Adjuntos: ${attachmentCount}` : ""}
            </div>
        </article>
    `;
}

function renderSection(listId, countId, emails, options = {}) {
    const list = document.getElementById(listId);
    const count = document.getElementById(countId);

    count.textContent = emails.length;

    if (!emails.length) {
        list.innerHTML = `<div class="empty-box">No hay correos en esta categoría.</div>`;
        return;
    }

    list.innerHTML = emails.map(email => createMailRow(email, options)).join("");
}

function renderTrashScreen(emails) {
    trashScreenCount.textContent = emails.length;

    if (!emails.length) {
        trashScreenList.innerHTML = `<div class="empty-box">La papelera está vacía.</div>`;
        return;
    }

    trashScreenList.innerHTML = emails.map(email => createMailRow(email)).join("");
}

/* =========================
   DETAIL HELPERS
========================= */
function renderDetectedData(extracted) {
    if (!extracted) {
        return `<p class="muted-text">No hay datos detectados.</p>`;
    }

    const rows = [];
    const singleFields = [
        ["fecha", "Fecha"],
        ["hora", "Hora"],
        ["personas", "Personas"],
        ["nombre", "Nombre"],
        ["telefono", "Teléfono"]
    ];

    singleFields.forEach(([key, label]) => {
        if (extracted[key]) {
            rows.push(`
                <div class="data-row">
                    <span class="data-label">${label}</span>
                    <span class="data-value">${escapeHtml(extracted[key])}</span>
                </div>
            `);
        }
    });

    const listFields = [
        ["alergenos", "Alérgenos"],
        ["restricciones", "Restricciones"],
        ["servicios", "Servicios"],
        ["notas", "Notas"]
    ];

    listFields.forEach(([key, label]) => {
        if (Array.isArray(extracted[key]) && extracted[key].length) {
            rows.push(`
                <div class="data-row">
                    <span class="data-label">${label}</span>
                    <span class="data-value">${escapeHtml(extracted[key].join(", "))}</span>
                </div>
            `);
        }
    });

    if (!rows.length) {
        return `<p class="muted-text">No se detectaron datos útiles claros.</p>`;
    }

    return `<div class="data-grid">${rows.join("")}</div>`;
}

function renderMissingData(missing) {
    if (!missing || !missing.length) {
        return `<p class="muted-text">No se detectan datos faltantes relevantes.</p>`;
    }

    return `<ul class="missing-list">${missing.map(item => `<li>${escapeHtml(item)}</li>`).join("")}</ul>`;
}

function renderAttachments(email) {
    const attachments = Array.isArray(email.attachments) ? email.attachments : [];

    if (!attachments.length) {
        return `<p class="muted-text">Este correo no tiene adjuntos.</p>`;
    }

    return `
        <div class="attachments-list">
            ${attachments.map(att => `
                <div class="attachment-item">
                    <div class="attachment-meta">
                        <strong>${escapeHtml(att.filename || "adjunto")}</strong>
                        <span>${escapeHtml(att.content_type || "archivo")} · ${escapeHtml(formatBytes(att.size))}</span>
                    </div>
                    <a class="attachment-download"
                       href="/api/adjunto?uid=${encodeURIComponent(email.uid)}&index=${encodeURIComponent(att.index)}"
                       target="_blank"
                       rel="noopener">
                        Descargar
                    </a>
                </div>
            `).join("")}
        </div>
    `;
}

function renderHistory(tracking) {
    const historial = Array.isArray(tracking?.historial) ? tracking.historial : [];

    if (!historial.length) {
        return `<p class="muted-text">Sin historial disponible.</p>`;
    }

    return `
        <div class="history-list">
            ${historial.slice().reverse().map(item => `
                <div class="history-item">
                    <strong>${escapeHtml(actionLabel(item.accion || ""))}</strong>
                    <span>${escapeHtml(item.fecha || "")}</span>
                </div>
            `).join("")}
        </div>
    `;
}

/* =========================
   PANTALLAS
========================= */
function updateDashboard(stats) {
    dashTotal.textContent = stats.total ?? 0;
    dashPendiente.textContent = stats.pendiente ?? 0;
    dashRespondido.textContent = stats.respondido ?? 0;
    dashArchivado.textContent = stats.archivado ?? 0;
    dashPapelera.textContent = stats.papelera ?? 0;
}

function showDashboard() {
    currentScreen = "dashboard";
    correoActual = null;
    hideAllViews();
    renderDashboardLocally();
    dashboardView.classList.remove("hidden");
    setActiveNav("nav-dashboard");
    window.scrollTo({ top: 0, behavior: "smooth" });
}

function showResultsSection(targetId = null, navId = null, scroll = true) {
    currentScreen = "results";
    correoActual = null;
    hideAllViews();
    results.classList.remove("hidden");

    if (navId) {
        setActiveNav(navId);
    }

    if (targetId) {
        lastResultsSection = targetId;
        if (scroll) {
            const section = document.querySelector(targetId);
            if (section) {
                setTimeout(() => {
                    section.scrollIntoView({ behavior: "smooth", block: "start" });
                }, 50);
            }
        }
    } else if (scroll) {
        window.scrollTo({ top: 0, behavior: "smooth" });
    }
}

function showTrashView(scroll = true) {
    currentScreen = "trash";
    correoActual = null;
    hideAllViews();
    trashView.classList.remove("hidden");
    setActiveNav("nav-papelera");

    if (scroll) {
        window.scrollTo({ top: 0, behavior: "smooth" });
    }
}

/* =========================
   DATASET LOCAL
========================= */
function removeEmailFromAllCollections(uid) {
    if (!ultimoDataset) return null;

    let found = null;

    getAllDatasetKeys().forEach(key => {
        if (!Array.isArray(ultimoDataset[key])) return;

        ultimoDataset[key] = ultimoDataset[key].filter(email => {
            if (email.uid === uid) {
                found = email;
                return false;
            }
            return true;
        });
    });

    return found;
}

function insertEmailIntoCollection(key, email, { sortByPriority = false } = {}) {
    if (!ultimoDataset || !Array.isArray(ultimoDataset[key])) return;

    const exists = ultimoDataset[key].some(item => item.uid === email.uid);
    if (exists) return;

    ultimoDataset[key].push(email);

    if (sortByPriority) {
        ultimoDataset[key].sort((a, b) => (b.priority ?? -1) - (a.priority ?? -1));
    }
}

function addEmailBackToNormalCollections(email) {
    if (!ultimoDataset || !email) return;

    if (email.category === "automatico_o_publicidad") {
        insertEmailIntoCollection("automaticos", email);
        return;
    }

    insertEmailIntoCollection("importantes", email, { sortByPriority: true });

    if (email.category === "reserva") {
        insertEmailIntoCollection("reservas", email, { sortByPriority: true });
    } else if (email.category === "horario_ubicacion") {
        insertEmailIntoCollection("horario_ubicacion", email, { sortByPriority: true });
    } else if (email.category === "carta_alergenos_servicios") {
        insertEmailIntoCollection("carta_alergenos_servicios", email, { sortByPriority: true });
    } else {
        insertEmailIntoCollection("otro_cliente", email, { sortByPriority: true });
    }
}

function moveEmailToTrash(uid, tracking) {
    const found = removeEmailFromAllCollections(uid);
    if (!found) return null;

    const moved = cloneEmail(found);
    moved.tracking = tracking;
    insertEmailIntoCollection("papelera", moved);

    return moved;
}

function restoreEmailFromTrash(uid, tracking) {
    const found = removeEmailFromAllCollections(uid);
    if (!found) return null;

    const restored = cloneEmail(found);
    restored.tracking = tracking;
    addEmailBackToNormalCollections(restored);

    return restored;
}

function updateTrackingInAllCollections(uid, tracking) {
    if (!ultimoDataset) return null;

    let updated = null;

    getAllDatasetKeys().forEach(key => {
        if (!Array.isArray(ultimoDataset[key])) return;

        ultimoDataset[key] = ultimoDataset[key].map(email => {
            if (email.uid !== uid) return email;
            const next = { ...email, tracking };
            updated = next;
            return next;
        });
    });

    return updated;
}

function refreshLocalView() {
    if (!ultimoDataset) return;
    applyFilter(ultimoDataset);
    renderTrashScreen(ultimoDataset.papelera || []);
    renderDashboardLocally();
}

function updateCurrentEmailReference() {
    if (!correoActual || !ultimoDataset) return;

    for (const key of getAllDatasetKeys()) {
        const found = (ultimoDataset[key] || []).find(email => email.uid === correoActual.uid);
        if (found) {
            correoActual = found;
            return;
        }
    }
}

/* =========================
   FILTRO Y DETALLE
========================= */
function applyFilter(dataset) {
    if (!dataset) return;

    renderSection("list-importantes", "count-importantes", filterByState(dataset.importantes, filtroActual), { important: true });
    renderSection("list-automaticos", "count-automaticos", filterByState(dataset.automaticos, filtroActual));
    renderSection("list-reservas", "count-reservas", filterByState(dataset.reservas, filtroActual));
    renderSection("list-horario", "count-horario", filterByState(dataset.horario_ubicacion, filtroActual));
    renderSection("list-carta", "count-carta", filterByState(dataset.carta_alergenos_servicios, filtroActual));
    renderSection("list-otros", "count-otros", filterByState(dataset.otro_cliente, filtroActual));

    bindMailClicks();
}

function limpiarZonaRespuesta() {
    detailReplyTextarea.value = "";
    detailReplyStatus.textContent = "";
}

function abrirDetalle(email, doScroll = true) {
    correoActual = email;
    hideAllViews();
    detailView.classList.remove("hidden");

    detailPriorityWrap.innerHTML =
        email.priority !== null && email.priority !== undefined
            ? createPriorityBadge(email.priority)
            : "";

    detailSubject.textContent = email.subject || "";
    detailFrom.textContent = email.from || "";
    detailDate.textContent = email.date || "";
    detailSummary.textContent = email.summary || "";
    detailGeneralReason.textContent = email.general_reason || "";
    detailBodySource.textContent = getBodySourceLabel(email.body_source);

    detailHostBlock.classList.toggle("hidden", !email.host_reason);
    detailPriorityBlock.classList.toggle("hidden", !email.priority_reason);

    detailHostReason.textContent = email.host_reason || "";
    detailPriorityReason.textContent = email.priority_reason || "";

    const detectedHasContent =
        email.extracted &&
        (
            email.extracted.fecha ||
            email.extracted.hora ||
            email.extracted.personas ||
            email.extracted.nombre ||
            email.extracted.telefono ||
            (Array.isArray(email.extracted.alergenos) && email.extracted.alergenos.length) ||
            (Array.isArray(email.extracted.restricciones) && email.extracted.restricciones.length) ||
            (Array.isArray(email.extracted.servicios) && email.extracted.servicios.length) ||
            (Array.isArray(email.extracted.notas) && email.extracted.notas.length)
        );

    detailDetectedBlock.classList.toggle("hidden", !detectedHasContent);
    detailDetectedData.innerHTML = detectedHasContent ? renderDetectedData(email.extracted || {}) : "";

    const hasMissing = Array.isArray(email.missing) && email.missing.length > 0;
    detailMissingBlock.classList.toggle("hidden", !hasMissing);
    detailMissingData.innerHTML = hasMissing ? renderMissingData(email.missing || []) : "";

    const hasExtractionNotes = !!(email.extraction_notes && email.extraction_notes.trim());
    detailExtractionBlock.classList.toggle("hidden", !hasExtractionNotes);
    detailExtractionNotes.textContent = hasExtractionNotes ? email.extraction_notes : "";

    detailAttachmentsBlock.classList.remove("hidden");
    detailAttachmentsData.innerHTML = renderAttachments(email);

    detailState.textContent = stateLabel(email.tracking?.estado);
    detailCreated.textContent = formatDateField(email.tracking?.creado_en);
    detailDraftDate.textContent = formatDateField(email.tracking?.borrador_generado_en);
    detailAnsweredDate.textContent = formatDateField(email.tracking?.respondido_en);
    detailArchivedDate.textContent = formatDateField(email.tracking?.archivado_en);
    detailTrashDate.textContent = formatDateField(email.tracking?.enviado_a_papelera_en);
    detailHistory.innerHTML = renderHistory(email.tracking);

    const respuestaEnviada = email.tracking?.respuesta_enviada || "";
    const hasSentReply = respuestaEnviada.trim().length > 0;
    detailSentReplyBlock.classList.toggle("hidden", !hasSentReply);
    detailSentReply.textContent = hasSentReply ? respuestaEnviada : "";

    const enPapelera = email.tracking?.estado === "papelera";
    btnRestore.classList.toggle("hidden", !enPapelera);
    btnTrash.classList.toggle("hidden", enPapelera);

    detailBody.textContent = email.body || "(Sin contenido legible)";
    limpiarZonaRespuesta();

    if (doScroll) {
        window.scrollTo({ top: 0, behavior: "smooth" });
    }
}

function volverListado() {
    correoActual = null;
    detailView.classList.add("hidden");

    if (currentScreen === "trash") {
        trashView.classList.remove("hidden");
        return;
    }

    if (currentScreen === "results") {
        results.classList.remove("hidden");
        return;
    }

    dashboardView.classList.remove("hidden");
}

function bindMailClicks() {
    document.querySelectorAll(".mail-row").forEach(row => {
        row.addEventListener("click", () => {
            const raw = row.getAttribute("data-email");
            const email = JSON.parse(decodeURIComponent(raw));
            abrirDetalle(email, true);
        });
    });
}

/* =========================
   API ACTIONS
========================= */
async function generarRespuestaActual() {
    if (!correoActual) return;

    detailReplyStatus.textContent = "Generando borrador con IA...";
    detailReplyTextarea.value = "";

    try {
        const response = await fetch("/api/generar_respuesta", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(correoActual)
        });

        const data = await response.json();
        if (!data.ok) {
            throw new Error(data.error || "No se pudo generar la respuesta.");
        }

        detailReplyTextarea.value = data.respuesta || "";
        detailReplyStatus.textContent = "Borrador generado. Puedes editarlo libremente.";

        if (data.tracking && correoActual.uid) {
            updateTrackingInAllCollections(correoActual.uid, data.tracking);
            refreshLocalView();
            updateCurrentEmailReference();

            detailDraftDate.textContent = formatDateField(data.tracking?.borrador_generado_en);
            detailHistory.innerHTML = renderHistory(data.tracking);
        }
    } catch (error) {
        detailReplyStatus.textContent = "Error al generar la respuesta: " + error.message;
    }
}

async function enviarRespuestaActual() {
    if (!correoActual) return;

    const cuerpo = detailReplyTextarea.value.trim();
    if (!cuerpo) {
        detailReplyStatus.textContent = "No puedes enviar una respuesta vacía.";
        return;
    }

    const destino = correoActual.from_email || "";
    if (!destino) {
        detailReplyStatus.textContent = "No se ha detectado un correo destinatario válido.";
        return;
    }

    const confirmar = window.confirm(`¿Seguro que quieres enviar esta respuesta a ${destino}?`);
    if (!confirmar) return;

    detailReplyStatus.textContent = "Enviando correo...";

    try {
        const response = await fetch("/api/enviar_respuesta", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                uid: correoActual.uid,
                to: destino,
                subject: correoActual.subject || "",
                body: cuerpo
            })
        });

        const data = await response.json();
        if (!data.ok) {
            throw new Error(data.error || "No se pudo enviar el correo.");
        }

        detailReplyStatus.textContent = "Correo enviado correctamente.";

        if (data.tracking && correoActual.uid) {
            updateTrackingInAllCollections(correoActual.uid, data.tracking);
            refreshLocalView();
            updateCurrentEmailReference();

            const respuestaEnviada = data.respuesta_enviada || cuerpo;
            correoActual.tracking.respuesta_enviada = respuestaEnviada;

            detailState.textContent = stateLabel(correoActual.tracking?.estado);
            detailAnsweredDate.textContent = formatDateField(correoActual.tracking?.respondido_en);
            detailHistory.innerHTML = renderHistory(correoActual.tracking);
            detailSentReplyBlock.classList.remove("hidden");
            detailSentReply.textContent = respuestaEnviada;
        }
    } catch (error) {
        detailReplyStatus.textContent = "Error al enviar el correo: " + error.message;
    }
}

async function cambiarEstadoActual(nuevoEstado) {
    if (!correoActual) return;

    try {
        const response = await fetch("/api/cambiar_estado", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                uid: correoActual.uid,
                estado: nuevoEstado
            })
        });

        const data = await response.json();
        if (!data.ok) {
            throw new Error(data.error || "No se pudo cambiar el estado.");
        }

        if (!data.tracking) return;

        if (nuevoEstado === "papelera") {
            moveEmailToTrash(correoActual.uid, data.tracking);
            refreshLocalView();
            showTrashView(false);
            return;
        }

        if (correoActual.tracking?.estado === "papelera" && nuevoEstado === "pendiente") {
            restoreEmailFromTrash(correoActual.uid, data.tracking);
            refreshLocalView();
            showResultsSection(lastResultsSection, activeNavIdFromSection(lastResultsSection), false);
            return;
        }

        updateTrackingInAllCollections(correoActual.uid, data.tracking);
        refreshLocalView();
        showDashboard();
    } catch (error) {
        alert("Error: " + error.message);
    }
}

function activeNavIdFromSection(sectionId) {
    const map = {
        "#importantes": "nav-importantes",
        "#automaticos": "nav-automaticos",
        "#reservas": "nav-reservas",
        "#horario": "nav-horario",
        "#carta": "nav-carta",
        "#otros": "nav-otros"
    };
    return map[sectionId] || "nav-importantes";
}

async function vaciarPapelera() {
    const confirmar = window.confirm("¿Seguro que quieres vaciar la papelera? Esta acción eliminará esos registros de trazabilidad.");
    if (!confirmar) return;

    try {
        const response = await fetch("/api/vaciar_papelera", {
            method: "POST"
        });

        const data = await response.json();
        if (!data.ok) {
            throw new Error(data.error || "No se pudo vaciar la papelera.");
        }

        await cargarCorreos(parseInt(cantidadInput.value || "5", 10), false);
        showTrashView(false);
        alert(`Papelera vaciada. Registros eliminados: ${data.deleted}`);
    } catch (error) {
        alert("Error: " + error.message);
    }
}

async function cargarCorreos(n = 5, showDash = false) {
    loadingSection.classList.remove("hidden");
    errorSection.classList.add("hidden");
    hideAllViews();

    try {
        const response = await fetch(`/api/correos?n=${encodeURIComponent(n)}`);
        const data = await response.json();

        if (!data.ok) {
            throw new Error("No se pudieron cargar los correos.");
        }

        ultimoDataset = data.datos;

        applyFilter(ultimoDataset);
        renderTrashScreen(ultimoDataset.papelera || []);
        renderDashboardLocally();

        loadingSection.classList.add("hidden");

        if (showDash) {
            showDashboard();
        } else {
            currentScreen = "results";
            results.classList.remove("hidden");
        }
    } catch (error) {
        loadingSection.classList.add("hidden");
        errorSection.classList.remove("hidden");
        errorMessage.textContent = error.message;
    }
}

/* =========================
   EVENTS
========================= */
refreshForm.addEventListener("submit", (e) => {
    e.preventDefault();
    const n = parseInt(cantidadInput.value || "5", 10);
    cargarCorreos(n, false);
});

btnBack.addEventListener("click", volverListado);
btnGenerateReply.addEventListener("click", generarRespuestaActual);
btnSendReply.addEventListener("click", enviarRespuestaActual);

btnMarkPending.addEventListener("click", () => cambiarEstadoActual("pendiente"));
btnArchive.addEventListener("click", () => cambiarEstadoActual("archivado"));
btnTrash.addEventListener("click", () => cambiarEstadoActual("papelera"));
btnRestore.addEventListener("click", () => cambiarEstadoActual("pendiente"));

btnEmptyTrash.addEventListener("click", vaciarPapelera);

goDashboard.addEventListener("click", showDashboard);
navDashboard.addEventListener("click", (e) => {
    e.preventDefault();
    showDashboard();
});

navTrash.addEventListener("click", (e) => {
    e.preventDefault();
    showTrashView(true);
});

sideNavLinks.forEach(link => {
    const href = link.getAttribute("href");
    if (href && href !== "#" && href.startsWith("#")) {
        link.addEventListener("click", (e) => {
            e.preventDefault();
            showResultsSection(href, link.id, true);
        });
    }
});

filterButtons.forEach(btn => {
    btn.addEventListener("click", () => {
        filterButtons.forEach(b => b.classList.remove("active"));
        btn.classList.add("active");
        filtroActual = btn.dataset.filter;

        if (!ultimoDataset) return;

        applyFilter(ultimoDataset);
        currentScreen = "results";
        hideAllViews();
        results.classList.remove("hidden");
    });
});

document.addEventListener("DOMContentLoaded", () => {
    const n = parseInt(cantidadInput.value || "5", 10);
    cargarCorreos(n, true);
});