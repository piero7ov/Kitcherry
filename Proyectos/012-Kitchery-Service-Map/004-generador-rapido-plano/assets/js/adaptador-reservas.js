// ==========================================================
// KITCHERRY SERVICE MAP
// Archivo: assets/js/adaptador-reservas.js
// Adapta el JSON original de Kitcherry Reservas al formato interno del plano
// ==========================================================

window.KitcherryReservasAdapter = (function () {

    function adaptar(datosOriginales) {
        if (!datosOriginales || typeof datosOriginales !== "object") {
            throw new Error("El archivo no contiene un objeto JSON válido.");
        }

        if (datosOriginales.origen !== "Kitcherry Reservas") {
            throw new Error("El archivo no procede de Kitcherry Reservas.");
        }

        if (datosOriginales.destino !== "Kitcherry Service Map") {
            throw new Error("El archivo no está destinado a Kitcherry Service Map.");
        }

        if (!Array.isArray(datosOriginales.reservas)) {
            throw new Error("El archivo no contiene una lista de reservas válida.");
        }

        const reservasAdaptadas = datosOriginales.reservas.map(function (reserva) {
            return adaptarReserva(reserva);
        });

        return {
            formato_interno: "kitcherry_service_map_reservas",
            version: "1.0",

            origen_original: datosOriginales.origen,
            destino_original: datosOriginales.destino,
            fecha_exportacion: datosOriginales.fecha_exportacion || "",

            fecha_servicio: datosOriginales.fecha_servicio || "",
            fecha_servicio_formateada: datosOriginales.fecha_servicio_formateada || datosOriginales.fecha_servicio || "",
            turno: datosOriginales.turno || "todos",
            turno_texto: datosOriginales.turno_texto || "Todos los turnos",

            negocio: datosOriginales.negocio || {},
            resumen_original: datosOriginales.resumen || {},
            resumen_plano: crearResumenPlano(reservasAdaptadas),

            reservas: reservasAdaptadas
        };
    }

    function adaptarReserva(reserva) {
        const mesaOriginal = reserva.mesa || null;
        const mesaId = obtenerMesaId(reserva);

        const estadoReserva = reserva.estado || "pendiente";
        const ocupaMesa = reservaOcupaMesa(estadoReserva);

        return {
            id: reserva.id,
            id_original: reserva.id,

            fecha: reserva.fecha || "",
            hora: reserva.hora || "",
            turno: reserva.turno || "",
            cliente: reserva.cliente || "Cliente sin nombre",
            telefono: reserva.telefono || "",
            email: reserva.email || "",
            personas: parseInt(reserva.personas, 10) || 0,

            estado_reserva: estadoReserva,
            estado_reserva_texto: reserva.estado_texto || textoEstadoReserva(estadoReserva),

            estado_plano_sugerido: ocupaMesa ? "reservada" : "libre",
            ocupa_mesa: ocupaMesa,
            visible_en_plano: ocupaMesa,

            mesa_id: mesaId,
            mesa_nombre: mesaOriginal && mesaOriginal.nombre ? mesaOriginal.nombre : "Sin mesa",
            mesa_zona: mesaOriginal && mesaOriginal.zona ? mesaOriginal.zona : "",
            mesa_capacidad: mesaOriginal && mesaOriginal.capacidad ? parseInt(mesaOriginal.capacidad, 10) : null,

            alergias: reserva.alergias || "",
            preferencias: reserva.preferencias || "",
            observaciones: reserva.observaciones || "",
            zona_preferida: reserva.zona_preferida || "",

            alertas: {
                total: reserva.alertas && reserva.alertas.total ? parseInt(reserva.alertas.total, 10) : 0,
                riesgo: reserva.alertas && reserva.alertas.riesgo ? parseInt(reserva.alertas.riesgo, 10) : 0,
                criticas: reserva.alertas && reserva.alertas.criticas ? parseInt(reserva.alertas.criticas, 10) : 0
            },

            banderas: {
                tiene_mesa: mesaId !== null,
                tiene_alergias: (reserva.alergias || "").trim() !== "",
                tiene_observaciones: (reserva.observaciones || "").trim() !== "",
                tiene_alertas: reserva.alertas && parseInt(reserva.alertas.total, 10) > 0,
                grupo_grande: (parseInt(reserva.personas, 10) || 0) >= 6,
                confirmacion_enviada: reserva.confirmacion_enviada === true,
                recordatorio_enviado: reserva.recordatorio_enviado === true
            },

            colocacion: {
                colocada: false,
                mesa_canvas_id: null,
                pendiente: true,
                motivo_pendiente: mesaId === null ? "Reserva sin mesa asignada" : "Mesa no encontrada en el plano"
            },

            origen: reserva.origen || "manual"
        };
    }

    function obtenerMesaId(reserva) {
        if (reserva.mesa_asignada !== null && reserva.mesa_asignada !== undefined && reserva.mesa_asignada !== "") {
            return parseInt(reserva.mesa_asignada, 10);
        }

        if (reserva.mesa && reserva.mesa.id !== null && reserva.mesa.id !== undefined && reserva.mesa.id !== "") {
            return parseInt(reserva.mesa.id, 10);
        }

        return null;
    }

    function reservaOcupaMesa(estadoReserva) {
        const estadosQueNoOcupanMesa = [
            "cancelada",
            "no_presentada",
            "completada"
        ];

        return !estadosQueNoOcupanMesa.includes(estadoReserva);
    }

    function textoEstadoReserva(estado) {
        const textos = {
            pendiente: "Pendiente",
            confirmada: "Confirmada",
            modificada: "Modificada",
            cancelada: "Cancelada",
            no_presentada: "No presentada",
            completada: "Completada"
        };

        return textos[estado] || estado;
    }

    function crearResumenPlano(reservas) {
        let totalComensales = 0;
        let conMesa = 0;
        let sinMesa = 0;
        let conAlertas = 0;
        let conAlergias = 0;
        let activas = 0;
        let noActivas = 0;

        reservas.forEach(function (reserva) {
            totalComensales += reserva.personas;

            if (reserva.mesa_id !== null) {
                conMesa++;
            } else {
                sinMesa++;
            }

            if (reserva.banderas.tiene_alertas) {
                conAlertas++;
            }

            if (reserva.banderas.tiene_alergias) {
                conAlergias++;
            }

            if (reserva.ocupa_mesa) {
                activas++;
            } else {
                noActivas++;
            }
        });

        return {
            total_reservas: reservas.length,
            total_comensales: totalComensales,
            reservas_activas_en_plano: activas,
            reservas_no_activas: noActivas,
            con_mesa_asignada: conMesa,
            sin_mesa_asignada: sinMesa,
            con_alertas: conAlertas,
            con_alergias: conAlergias
        };
    }

    return {
        adaptar: adaptar
    };

})();