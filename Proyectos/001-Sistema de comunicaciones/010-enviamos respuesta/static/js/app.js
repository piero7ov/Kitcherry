const loadingSection = document.getElementById("loading-section");
const errorSection = document.getElementById("error-section");
const errorMessage = document.getElementById("error-message");
const results = document.getElementById("results");
const refreshForm = document.getElementById("refresh-form");
const cantidadInput = document.getElementById("cantidad");

const detailView = document.getElementById("detail-view");
const btnBack = document.getElementById("btn-back");

const detailPriorityWrap = document.getElementById("detail-priority-wrap");
const detailSubject = document.getElementById("detail-subject");
const detailFrom = document.getElementById("detail-from");
const detailDate = document.getElementById("detail-date");
const detailSummary = document.getElementById("detail-summary");
const detailGeneralReason = document.getElementById("detail-general-reason");
const detailHostReason = document.getElementById("detail-host-reason");
const detailPriorityReason = document.getElementById("detail-priority-reason");
const detailBody = document.getElementById("detail-body");
const detailHostBlock = document.getElementById("detail-host-block");
const detailPriorityBlock = document.getElementById("detail-priority-block");
const detailReplyTextarea = document.getElementById("detail-reply-textarea");
const detailReplyStatus = document.getElementById("detail-reply-status");
const btnGenerateReply = document.getElementById("btn-generate-reply");
const btnSendReply = document.getElementById("btn-send-reply");

let correoActual = null;

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
    const p = Number(priority ?? 0);
    return `priority-${p}`;
}

function createPriorityBadge(priority) {
    if (priority === null || priority === undefined) return "";
    return `<div class="mail-priority ${priorityClass(priority)}">${priority}/10</div>`;
}

function createMailRow(email, options = {}) {
    const { important = false } = options;
    const encoded = encodeURIComponent(JSON.stringify(email));

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

function abrirDetalle(email) {
    correoActual = email;

    detailPriorityWrap.innerHTML =
        email.priority !== null && email.priority !== undefined
            ? createPriorityBadge(email.priority)
            : "";

    detailSubject.textContent = email.subject || "";
    detailFrom.textContent = email.from || "";
    detailDate.textContent = email.date || "";
    detailSummary.textContent = email.summary || "";
    detailGeneralReason.textContent = email.general_reason || "";

    if (email.host_reason) {
        detailHostBlock.classList.remove("hidden");
        detailHostReason.textContent = email.host_reason;
    } else {
        detailHostBlock.classList.add("hidden");
        detailHostReason.textContent = "";
    }

    if (email.priority_reason) {
        detailPriorityBlock.classList.remove("hidden");
        detailPriorityReason.textContent = email.priority_reason;
    } else {
        detailPriorityBlock.classList.add("hidden");
        detailPriorityReason.textContent = "";
    }

    detailBody.textContent = email.body || "(Sin contenido legible)";
    detailReplyTextarea.value = "";
    detailReplyStatus.textContent = "";

    results.classList.add("hidden");
    detailView.classList.remove("hidden");
    window.scrollTo({ top: 0, behavior: "smooth" });
}

function volverListado() {
    detailView.classList.add("hidden");
    results.classList.remove("hidden");
    correoActual = null;
}

function bindMailClicks() {
    document.querySelectorAll(".mail-row").forEach(row => {
        row.addEventListener("click", () => {
            const raw = row.getAttribute("data-email");
            const email = JSON.parse(decodeURIComponent(raw));
            abrirDetalle(email);
        });
    });
}

async function generarRespuestaActual() {
    if (!correoActual) return;

    detailReplyStatus.textContent = "Generando borrador con IA...";
    detailReplyTextarea.value = "";

    try {
        const response = await fetch("/api/generar_respuesta", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(correoActual)
        });

        const data = await response.json();

        if (!data.ok) {
            throw new Error(data.error || "No se pudo generar la respuesta.");
        }

        detailReplyTextarea.value = data.respuesta || "";
        detailReplyStatus.textContent = "Borrador generado. Puedes editarlo libremente.";
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
    if (!confirmar) {
        return;
    }

    detailReplyStatus.textContent = "Enviando correo...";

    try {
        const response = await fetch("/api/enviar_respuesta", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
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
    } catch (error) {
        detailReplyStatus.textContent = "Error al enviar el correo: " + error.message;
    }
}

async function cargarCorreos(n = 5) {
    loadingSection.classList.remove("hidden");
    errorSection.classList.add("hidden");
    results.classList.add("hidden");
    detailView.classList.add("hidden");

    try {
        const response = await fetch(`/api/correos?n=${encodeURIComponent(n)}`);
        const data = await response.json();

        if (!data.ok) {
            throw new Error("No se pudieron cargar los correos.");
        }

        const correos = data.datos;

        renderSection("list-importantes", "count-importantes", correos.importantes, {
            important: true
        });

        renderSection("list-automaticos", "count-automaticos", correos.automaticos, {
            important: false
        });

        renderSection("list-reservas", "count-reservas", correos.reservas, {
            important: false
        });

        renderSection("list-horario", "count-horario", correos.horario_ubicacion, {
            important: false
        });

        renderSection("list-carta", "count-carta", correos.carta_alergenos_servicios, {
            important: false
        });

        renderSection("list-otros", "count-otros", correos.otro_cliente, {
            important: false
        });

        bindMailClicks();

        loadingSection.classList.add("hidden");
        results.classList.remove("hidden");
    } catch (error) {
        loadingSection.classList.add("hidden");
        errorSection.classList.remove("hidden");
        errorMessage.textContent = error.message;
    }
}

refreshForm.addEventListener("submit", (e) => {
    e.preventDefault();
    const n = parseInt(cantidadInput.value || "5", 10);
    cargarCorreos(n);
});

btnBack.addEventListener("click", volverListado);
btnGenerateReply.addEventListener("click", generarRespuestaActual);
btnSendReply.addEventListener("click", enviarRespuestaActual);

document.addEventListener("DOMContentLoaded", () => {
    const n = parseInt(cantidadInput.value || "5", 10);
    cargarCorreos(n);
});