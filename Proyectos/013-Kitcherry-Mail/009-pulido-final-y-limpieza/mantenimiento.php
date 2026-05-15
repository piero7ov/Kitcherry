<?php
// ==========================================================
// KITCHERRY MAIL
// Pantalla de mantenimiento local y diagnóstico IMAP.
// ==========================================================

declare(strict_types=1);

session_start();

if (empty($_SESSION['kitcherry_mail']['account_id'])) {
    header('Location: login.php');
    exit;
}

$correoSesion = $_SESSION['kitcherry_mail']['email'] ?? 'Cuenta conectada';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry Mail | Mantenimiento</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        @font-face {
            font-family: "Coolvetica";
            src: url("assets/fuente/Coolvetica Rg.otf") format("opentype");
            font-weight: normal;
            font-style: normal;
        }

        :root {
            --cherry: #C2182B;
            --cherry-soft: #fff0f2;
            --black: #111111;
            --text: #25252b;
            --muted: #73737f;
            --line: #e6e6ea;
            --line-strong: #d6d6dc;
            --white: #ffffff;
            --bg: #f5f5f7;
            --green: #168a4a;
            --green-soft: #eaf8f0;
            --orange: #b46510;
            --orange-soft: #fff4e5;
            --blue: #1d4ed8;
            --blue-soft: #eef4ff;
            --radius: 14px;
            --radius-sm: 9px;
            --shadow: 0 14px 35px rgba(17, 17, 17, 0.08);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            padding: 24px;
            background:
                radial-gradient(circle at top left, rgba(194, 24, 43, 0.09), transparent 34%),
                var(--bg);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
        }

        .page {
            width: min(1180px, 100%);
            margin: 0 auto;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 18px;
            padding: 16px;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: var(--white);
            box-shadow: var(--shadow);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand img {
            width: 46px;
            height: 46px;
            object-fit: contain;
        }

        .brand h1 {
            font-family: "Coolvetica", Arial, Helvetica, sans-serif;
            font-size: 30px;
            font-weight: 400;
            letter-spacing: 0.5px;
            color: var(--black);
        }

        .brand-kit,
        .brand-cherry {
            font-weight: bold;
        }

        .brand-kit {
            color: var(--black);
        }

        .brand-cherry {
            color: var(--cherry);
        }

        .brand small {
            display: block;
            margin-top: 2px;
            color: var(--muted);
            font-size: 12.5px;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 9px;
            flex-wrap: wrap;
        }

        .btn {
            border: 1px solid var(--line-strong);
            border-radius: var(--radius-sm);
            padding: 10px 13px;
            background: var(--white);
            color: var(--black);
            font-weight: 800;
            font-size: 13px;
            text-decoration: none;
            cursor: pointer;
            transition: 0.18s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(17, 17, 17, 0.08);
        }

        .btn-primary {
            border-color: var(--cherry);
            background: var(--cherry);
            color: var(--white);
        }

        .btn-danger {
            border-color: rgba(194, 24, 43, 0.28);
            background: var(--cherry-soft);
            color: var(--cherry);
        }

        .btn-green {
            border-color: rgba(22, 138, 74, 0.25);
            background: var(--green-soft);
            color: var(--green);
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 18px;
        }

        .card {
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: var(--white);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-header {
            padding: 16px;
            border-bottom: 1px solid var(--line);
            background: #fbfbfc;
        }

        .card-header h2 {
            font-family: "Coolvetica", Arial, Helvetica, sans-serif;
            font-size: 23px;
            font-weight: 400;
            color: var(--black);
        }

        .card-header p {
            margin-top: 4px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
        }

        .card-body {
            padding: 16px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .stat {
            padding: 13px;
            border: 1px solid var(--line);
            border-radius: var(--radius-sm);
            background: #fcfcfd;
        }

        .stat span {
            display: block;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
        }

        .stat strong {
            display: block;
            margin-top: 4px;
            font-size: 24px;
            color: var(--black);
        }

        .stat.local strong {
            color: var(--orange);
        }

        .stat.synced strong {
            color: var(--green);
        }

        .remote-list {
            display: grid;
            gap: 10px;
        }

        .remote-item {
            padding: 13px;
            border: 1px solid var(--line);
            border-radius: var(--radius-sm);
            background: #fcfcfd;
        }

        .remote-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 5px;
        }

        .remote-top strong {
            color: var(--black);
            font-size: 14px;
        }

        .remote-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 28px;
            padding: 0 9px;
            border-radius: 999px;
            background: var(--blue-soft);
            color: var(--blue);
            font-weight: 900;
            font-size: 13px;
        }

        .remote-item code {
            display: block;
            margin-top: 4px;
            padding: 7px;
            border-radius: 7px;
            background: #f2f2f4;
            color: var(--text);
            font-size: 12px;
            overflow-wrap: anywhere;
        }

        .remote-item p {
            margin-top: 6px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.4;
        }

        .actions-list {
            display: grid;
            gap: 12px;
        }

        .action-box {
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: var(--radius-sm);
            background: #fcfcfd;
        }

        .action-box h3 {
            font-size: 16px;
            color: var(--black);
        }

        .action-box p {
            margin: 6px 0 12px 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
        }

        .notice {
            margin-top: 18px;
            padding: 14px;
            border: 1px solid rgba(180, 101, 16, 0.22);
            border-radius: var(--radius);
            background: var(--orange-soft);
            color: #5a3507;
            font-size: 13px;
            line-height: 1.5;
        }

        .notice strong {
            color: var(--black);
        }

        .result {
            margin-top: 14px;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: var(--radius-sm);
            background: #fbfbfc;
            color: var(--text);
            font-weight: 700;
            font-size: 13px;
            min-height: 44px;
        }

        .result.ok {
            border-color: rgba(22, 138, 74, 0.25);
            background: var(--green-soft);
            color: var(--green);
        }

        .result.error {
            border-color: rgba(194, 24, 43, 0.25);
            background: var(--cherry-soft);
            color: var(--cherry);
        }

        .section-gap {
            margin-top: 18px;
        }

        @media (max-width: 850px) {
            body {
                padding: 14px;
            }

            .topbar,
            .grid {
                grid-template-columns: 1fr;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <header class="topbar">
            <div class="brand">
                <img src="assets/img/logo.png" alt="Logo Kitcherry Mail">
                <div>
                    <h1><span class="brand-kit">KIT</span><span class="brand-cherry">CHERRY</span> Mail</h1>
                    <small><?php echo htmlspecialchars((string) $correoSesion, ENT_QUOTES, 'UTF-8'); ?></small>
                </div>
            </div>

            <div class="top-actions">
                <a href="index.php" class="btn">Volver al correo</a>
                <button type="button" class="btn btn-primary" id="btnRefreshStats">Actualizar datos</button>
            </div>
        </header>

        <section class="grid">
            <article class="card">
                <header class="card-header">
                    <h2>Base local SQLite</h2>
                    <p>Mensajes guardados localmente para que el cliente cargue más rápido.</p>
                </header>

                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat">
                            <span>Total local</span>
                            <strong id="statTotal">0</strong>
                        </div>
                        <div class="stat synced">
                            <span>Sincronizados con IMAP</span>
                            <strong id="statSynced">0</strong>
                        </div>
                        <div class="stat local">
                            <span>Solo SQLite</span>
                            <strong id="statLocal">0</strong>
                        </div>
                        <div class="stat">
                            <span>Entrada local</span>
                            <strong id="statInbox">0</strong>
                        </div>
                        <div class="stat">
                            <span>Enviados local</span>
                            <strong id="statSent">0</strong>
                        </div>
                        <div class="stat">
                            <span>Papelera local</span>
                            <strong id="statTrash">0</strong>
                        </div>
                    </div>

                    <div class="notice">
                        <strong>Importante:</strong> SQLite guarda mensajes individuales. Gmail puede mostrar menos elementos porque agrupa correos en conversaciones.
                    </div>

                    <div class="result" id="resultBox">Listo para comprobar la base.</div>
                </div>
            </article>

            <article class="card">
                <header class="card-header">
                    <h2>Diagnóstico IMAP</h2>
                    <p>Carpetas reales detectadas en el servidor y número de mensajes que devuelve IMAP.</p>
                </header>

                <div class="card-body">
                    <div class="remote-list" id="remoteFolders">
                        <div class="remote-item">
                            <div class="remote-top">
                                <strong>Cargando carpetas...</strong>
                                <span class="remote-count">...</span>
                            </div>
                        </div>
                    </div>

                    <div class="notice section-gap" id="remoteNote">
                        Cargando diagnóstico IMAP...
                    </div>
                </div>
            </article>
        </section>

        <section class="card section-gap">
            <header class="card-header">
                <h2>Mantenimiento</h2>
                <p>Acciones para limpiar la base local antes de la entrega final.</p>
            </header>

            <div class="card-body">
                <div class="actions-list">
                    <div class="action-box">
                        <h3>Eliminar correos solo locales</h3>
                        <p>Elimina borradores, pruebas y enviados guardados solo en SQLite que no tienen UID real de IMAP.</p>
                        <button type="button" class="btn btn-danger" id="btnCleanLocal">Eliminar solo locales</button>
                    </div>

                    <div class="action-box">
                        <h3>Vaciar papelera local</h3>
                        <p>Elimina de SQLite los mensajes que están en la papelera local. Si siguen en el servidor IMAP, volverán cuando reconstruyas o sincronices.</p>
                        <button type="button" class="btn btn-danger" id="btnEmptyTrash">Vaciar papelera local</button>
                    </div>

                    <div class="action-box">
                        <h3>Reconstruir desde servidor</h3>
                        <p>Borra todos los mensajes locales de esta cuenta y vuelve a sincronizar Entrada, Papelera y Enviados desde IMAP.</p>
                        <button type="button" class="btn btn-green" id="btnRebuild">Reconstruir desde servidor</button>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        const resultBox = document.getElementById("resultBox");

        const statTotal = document.getElementById("statTotal");
        const statSynced = document.getElementById("statSynced");
        const statLocal = document.getElementById("statLocal");
        const statInbox = document.getElementById("statInbox");
        const statSent = document.getElementById("statSent");
        const statTrash = document.getElementById("statTrash");

        const remoteFolders = document.getElementById("remoteFolders");
        const remoteNote = document.getElementById("remoteNote");

        const btnRefreshStats = document.getElementById("btnRefreshStats");
        const btnCleanLocal = document.getElementById("btnCleanLocal");
        const btnEmptyTrash = document.getElementById("btnEmptyTrash");
        const btnRebuild = document.getElementById("btnRebuild");

        async function apiPost(action) {
            const respuesta = await fetch("api_mantenimiento.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({ action })
            });

            const datos = await respuesta.json();

            if (!respuesta.ok || !datos.ok) {
                throw new Error(datos.message || "No se pudo completar la acción");
            }

            return datos;
        }

        function pintarStats(stats) {
            statTotal.textContent = stats.total ?? 0;
            statSynced.textContent = stats.synced ?? 0;
            statLocal.textContent = stats.local_only ?? 0;
            statInbox.textContent = stats.inbox ?? 0;
            statSent.textContent = stats.sent ?? 0;
            statTrash.textContent = stats.trash ?? 0;
        }

        function escaparHtml(texto) {
            return String(texto)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function pintarDiagnostico(remote) {
            const carpetas = remote?.carpetas || [];

            remoteNote.textContent = remote?.nota || "Diagnóstico IMAP no disponible.";

            if (carpetas.length === 0) {
                remoteFolders.innerHTML = `
                    <div class="remote-item">
                        <div class="remote-top">
                            <strong>No hay carpetas detectadas</strong>
                            <span class="remote-count">0</span>
                        </div>
                        <p>No se pudo leer la información de IMAP.</p>
                    </div>
                `;
                return;
            }

            remoteFolders.innerHTML = "";

            carpetas.forEach(function (carpeta) {
                const item = document.createElement("div");
                item.className = "remote-item";

                item.innerHTML = `
                    <div class="remote-top">
                        <strong>${escaparHtml(carpeta.nombre)}</strong>
                        <span class="remote-count">${escaparHtml(carpeta.mensajes)}</span>
                    </div>
                    <code>${escaparHtml(carpeta.folder)}</code>
                    <p>${escaparHtml(carpeta.estado)} · Mensajes individuales devueltos por IMAP.</p>
                `;

                remoteFolders.appendChild(item);
            });
        }

        function mostrarResultado(mensaje, tipo = "ok") {
            resultBox.textContent = mensaje;
            resultBox.classList.remove("ok", "error");
            resultBox.classList.add(tipo);
        }

        async function cargarStats() {
            try {
                mostrarResultado("Cargando estadísticas y diagnóstico IMAP...", "ok");

                const datos = await apiPost("maintenance_stats");

                pintarStats(datos.stats || {});
                pintarDiagnostico(datos.remote || {});
                mostrarResultado("Estadísticas actualizadas.", "ok");
            } catch (error) {
                mostrarResultado(error.message, "error");
            }
        }

        btnRefreshStats.addEventListener("click", cargarStats);

        btnCleanLocal.addEventListener("click", async function () {
            const ok = confirm("¿Seguro que quieres eliminar los correos que solo existen en SQLite?");

            if (!ok) {
                return;
            }

            try {
                mostrarResultado("Eliminando correos solo locales...", "ok");

                const datos = await apiPost("clean_local_only");

                pintarStats(datos.stats || {});
                pintarDiagnostico(datos.remote || {});
                mostrarResultado(datos.message, "ok");
            } catch (error) {
                mostrarResultado(error.message, "error");
            }
        });

        btnEmptyTrash.addEventListener("click", async function () {
            const ok = confirm("Esto solo vacía la papelera de SQLite. Si los correos siguen en el servidor, volverán al sincronizar o reconstruir. ¿Continuar?");

            if (!ok) {
                return;
            }

            try {
                mostrarResultado("Vaciando papelera local...", "ok");

                const datos = await apiPost("empty_local_trash");

                pintarStats(datos.stats || {});
                pintarDiagnostico(datos.remote || {});
                mostrarResultado(datos.message, "ok");
            } catch (error) {
                mostrarResultado(error.message, "error");
            }
        });

        btnRebuild.addEventListener("click", async function () {
            const ok = confirm("Esto borrará todos los mensajes locales y volverá a sincronizar desde IMAP. ¿Continuar?");

            if (!ok) {
                return;
            }

            try {
                mostrarResultado("Reconstruyendo base desde IMAP...", "ok");

                const datos = await apiPost("rebuild_from_server");

                pintarStats(datos.stats || {});
                pintarDiagnostico(datos.remote || {});
                mostrarResultado(datos.message, "ok");
            } catch (error) {
                mostrarResultado(error.message, "error");
            }
        });

        cargarStats();
    </script>
</body>
</html>