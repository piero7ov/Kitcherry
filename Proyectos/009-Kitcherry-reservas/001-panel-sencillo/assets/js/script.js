// ==========================================================
// KITCHERRY RESERVAS
// Archivo: assets/js/script.js
// JS inicial de la versión 001-panel-sencillo
// ==========================================================

document.addEventListener("DOMContentLoaded", () => {
    const inputs = document.querySelectorAll("input");

    inputs.forEach((input) => {
        input.addEventListener("focus", () => {
            input.parentElement.classList.add("is-focused");
        });

        input.addEventListener("blur", () => {
            input.parentElement.classList.remove("is-focused");
        });
    });
});