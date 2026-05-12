// ==========================================================
// KITCHERRY RESERVAS
// Archivo: assets/js/script.js
// JS inicial de la versión 002-configuracion-negocio
// ==========================================================

document.addEventListener("DOMContentLoaded", () => {
    const inputs = document.querySelectorAll("input, select, textarea");

    inputs.forEach((input) => {
        input.addEventListener("focus", () => {
            input.parentElement.classList.add("is-focused");
        });

        input.addEventListener("blur", () => {
            input.parentElement.classList.remove("is-focused");
        });
    });

    const filasHorario = document.querySelectorAll(".horario-row");

    function actualizarFilaHorario(fila) {
        const checkboxCerrado = fila.querySelector(".js-dia-cerrado");
        const inputsHora = fila.querySelectorAll('input[type="time"]');

        if (!checkboxCerrado) {
            return;
        }

        if (checkboxCerrado.checked) {
            fila.classList.add("is-closed");

            inputsHora.forEach((input) => {
                input.disabled = true;
                input.value = "";
            });
        } else {
            fila.classList.remove("is-closed");

            inputsHora.forEach((input) => {
                input.disabled = false;
            });
        }
    }

    filasHorario.forEach((fila) => {
        const checkboxCerrado = fila.querySelector(".js-dia-cerrado");

        actualizarFilaHorario(fila);

        if (checkboxCerrado) {
            checkboxCerrado.addEventListener("change", () => {
                actualizarFilaHorario(fila);
            });
        }
    });
});