// ==========================================================
// KITCHERRY VOICE TASKS
// Archivo: assets/js/app.js
// ==========================================================

document.addEventListener("DOMContentLoaded", function () {
    const destinoSelect = document.querySelector("[data-destino-lista]");
    const contenedorItems = document.querySelector("#items-lista");
    const botonAgregar = document.querySelector("[data-agregar-item]");

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

    function crearFilaItem() {
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

        return fila;
    }

    if (destinoSelect) {
        destinoSelect.addEventListener("change", actualizarSelectTipos);
        actualizarSelectTipos();
    }

    if (botonAgregar && contenedorItems) {
        botonAgregar.addEventListener("click", function () {
            const nuevaFila = crearFilaItem();
            contenedorItems.appendChild(nuevaFila);
        });
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
});