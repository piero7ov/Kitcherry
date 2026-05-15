// ==========================================================
// KITCHERRY WEB ANALYTICS
// Archivo: assets/js/modal-graficas.js
// Permite abrir las gráficas del informe en una ventana modal.
// ==========================================================

document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("chartModal");
    const modalImg = document.getElementById("modalChartImg");
    const modalTitle = document.getElementById("modalChartTitle");
    const openButtons = document.querySelectorAll(".chart-open");
    const closeElements = document.querySelectorAll("[data-close-modal]");

    if (!modal || !modalImg || !modalTitle) {
        return;
    }

    function openModal(src, title) {
        modalImg.src = src;
        modalImg.alt = title;
        modalTitle.textContent = title;

        modal.classList.add("is-open");
        modal.setAttribute("aria-hidden", "false");

        document.body.style.overflow = "hidden";
    }

    function closeModal() {
        modal.classList.remove("is-open");
        modal.setAttribute("aria-hidden", "true");

        modalImg.src = "";
        modalImg.alt = "";

        document.body.style.overflow = "";
    }

    openButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            const src = button.getAttribute("data-src");
            const title = button.getAttribute("data-title") || "Gráfica";

            if (src) {
                openModal(src, title);
            }
        });
    });

    closeElements.forEach(function (element) {
        element.addEventListener("click", closeModal);
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && modal.classList.contains("is-open")) {
            closeModal();
        }
    });
});