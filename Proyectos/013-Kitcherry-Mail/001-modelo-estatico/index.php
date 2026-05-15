<?php
// ==========================================================
// KITCHERRY MAIL
// Cliente de correo web con interfaz personalizada.
// ==========================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitcherry Mail</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-shell">
        <!-- ======================================================
        CABECERA SUPERIOR
        ======================================================= -->
        <header class="topbar">
            <div class="brand">
                <img src="assets/img/logo.png" alt="Logo Kitcherry Mail" class="brand-logo">

                <div class="brand-text">
                    <h1 class="brand-title">
                        <span class="brand-kit">KIT</span><span class="brand-cherry">CHERRY</span>
                        <span class="brand-mail">Mail</span>
                    </h1>
                </div>
            </div>

            <form class="search-box" id="searchForm">
                <input type="search" id="searchInput" placeholder="Buscar por asunto, remitente o contenido...">
                <button type="submit">Buscar</button>
            </form>

            <div class="topbar-actions">
                <span class="sync-pill" id="syncStatus">Bandeja actualizada</span>
                <button class="btn btn-light" id="btnSync" type="button">Sincronizar</button>
            </div>
        </header>

        <!-- ======================================================
        CONTENEDOR PRINCIPAL
        ======================================================= -->
        <main class="mail-layout">
            <!-- ==================================================
            SIDEBAR / BANDEJAS
            =================================================== -->
            <aside class="sidebar">
                <button class="btn btn-primary btn-compose" id="btnOpenCompose" type="button">
                    + Redactar
                </button>

                <nav class="folder-nav" aria-label="Bandejas de correo">
                    <button class="folder-item active" data-folder="inbox" type="button">
                        <span>📥 Entrada</span>
                        <strong id="countInbox">0</strong>
                    </button>

                    <button class="folder-item" data-folder="sent" type="button">
                        <span>📤 Enviados</span>
                        <strong id="countSent">0</strong>
                    </button>

                    <button class="folder-item" data-folder="drafts" type="button">
                        <span>📝 Borradores</span>
                        <strong id="countDrafts">0</strong>
                    </button>

                    <button class="folder-item" data-folder="archived" type="button">
                        <span>🗄️ Archivados</span>
                        <strong id="countArchived">0</strong>
                    </button>

                    <button class="folder-item" data-folder="trash" type="button">
                        <span>🗑️ Papelera</span>
                        <strong id="countTrash">0</strong>
                    </button>
                </nav>

                <section class="sidebar-card">
                    <h2>Estados</h2>
                    <button class="status-filter active" data-status="all" type="button">Todos</button>
                    <button class="status-filter" data-status="pendiente" type="button">Pendientes</button>
                    <button class="status-filter" data-status="revision" type="button">En revisión</button>
                    <button class="status-filter" data-status="respondido" type="button">Respondidos</button>
                    <button class="status-filter" data-status="importante" type="button">Importantes</button>
                </section>

                <section class="sidebar-card">
                    <h2>Vista</h2>
                    <div class="view-switch">
                        <button class="view-btn active" id="btnListView" type="button">Lista</button>
                        <button class="view-btn" id="btnKanbanView" type="button">Kanban</button>
                    </div>
                </section>

                <section class="sidebar-card muted-card">
                    <h2>Organización</h2>
                    <p>Gestiona correos por estado, añade notas internas y revisa el historial de acciones.</p>
                </section>
            </aside>

            <!-- ==================================================
            COLUMNA CENTRAL / LISTA Y KANBAN
            =================================================== -->
            <section class="mail-list-panel">
                <div class="panel-header">
                    <div>
                        <h2 id="currentFolderTitle">Bandeja de entrada</h2>
                        <p id="currentFolderSubtitle">Correos organizados para una gestión más clara.</p>
                    </div>

                    <select id="quickFilter" aria-label="Filtro rápido">
                        <option value="all">Todos</option>
                        <option value="unread">No leídos</option>
                        <option value="withNotes">Con notas</option>
                        <option value="highPriority">Prioridad alta</option>
                    </select>
                </div>

                <div class="mail-list" id="mailList"></div>

                <div class="kanban-board hidden" id="kanbanBoard" aria-label="Vista Kanban de correos">
                    <article class="kanban-column" data-kanban-status="pendiente">
                        <h3>Pendientes</h3>
                        <div class="kanban-dropzone" id="kanbanPendiente"></div>
                    </article>

                    <article class="kanban-column" data-kanban-status="revision">
                        <h3>En revisión</h3>
                        <div class="kanban-dropzone" id="kanbanRevision"></div>
                    </article>

                    <article class="kanban-column" data-kanban-status="respondido">
                        <h3>Respondidos</h3>
                        <div class="kanban-dropzone" id="kanbanRespondido"></div>
                    </article>

                    <article class="kanban-column" data-kanban-status="archivado">
                        <h3>Archivados</h3>
                        <div class="kanban-dropzone" id="kanbanArchivado"></div>
                    </article>
                </div>
            </section>

            <!-- ==================================================
            PANEL DERECHO / VISTA DEL CORREO
            =================================================== -->
            <section class="mail-detail-panel" id="mailDetail">
                <div class="empty-state" id="emptyState">
                    <div class="empty-icon">✉️</div>
                    <h2>Selecciona un correo</h2>
                    <p>El contenido del mensaje aparecerá aquí.</p>
                </div>

                <article class="mail-detail hidden" id="detailContent">
                    <div class="detail-top-actions">
                        <button class="btn btn-light" id="btnMarkRead" type="button">Marcar leído/no leído</button>
                        <button class="btn btn-light" id="btnArchive" type="button">Archivar</button>
                        <button class="btn btn-danger" id="btnTrash" type="button">Papelera</button>
                    </div>

                    <header class="detail-header">
                        <div class="detail-badges" id="detailBadges"></div>

                        <h2 id="detailSubject"></h2>

                        <p class="detail-meta">
                            <strong id="detailFrom"></strong>
                            <span id="detailEmail"></span>
                        </p>

                        <p class="detail-date" id="detailDate"></p>
                    </header>

                    <div class="detail-body" id="detailBody"></div>

                    <div class="detail-actions">
                        <button class="btn btn-primary" id="btnReply" type="button">Responder</button>
                        <button class="btn btn-light" id="btnForward" type="button">Reenviar</button>

                        <select id="statusSelect" aria-label="Cambiar estado del correo">
                            <option value="pendiente">Pendiente</option>
                            <option value="revision">En revisión</option>
                            <option value="respondido">Respondido</option>
                            <option value="importante">Importante</option>
                            <option value="archivado">Archivado</option>
                        </select>
                    </div>

                    <section class="notes-panel">
                        <h3>Notas internas</h3>

                        <div class="note-form">
                            <input type="text" id="noteInput" placeholder="Añadir nota privada para este correo...">
                            <button class="btn btn-light" id="btnAddNote" type="button">Añadir</button>
                        </div>

                        <ul class="note-list" id="noteList"></ul>
                    </section>

                    <section class="history-panel">
                        <h3>Historial de acciones</h3>
                        <ul class="history-list" id="historyList"></ul>
                    </section>
                </article>
            </section>
        </main>
    </div>

    <!-- ==========================================================
    MODAL DE REDACCIÓN / RESPUESTA / REENVÍO
    =========================================================== -->
    <section class="compose-modal hidden" id="composeModal" aria-label="Panel de redacción">
        <div class="compose-card">
            <header class="compose-header">
                <div>
                    <h2 id="composeTitle">Nuevo correo</h2>
                    <p>Redacta, responde o reenvía mensajes desde el editor visual.</p>
                </div>

                <button class="icon-btn" id="btnCloseCompose" type="button" aria-label="Cerrar redacción">×</button>
            </header>

            <form id="composeForm" class="compose-form">
                <label>
                    Para
                    <input type="email" id="composeTo" placeholder="cliente@ejemplo.com">
                </label>

                <label>
                    Asunto
                    <input type="text" id="composeSubject" placeholder="Asunto del correo">
                </label>

                <div class="templates-row">
                    <label for="templateSelect">Plantilla rápida</label>

                    <select id="templateSelect">
                        <option value="">Seleccionar plantilla...</option>
                        <option value="recibido">Confirmación de recepción</option>
                        <option value="info">Solicitud de más información</option>
                        <option value="gracias">Agradecimiento</option>
                    </select>
                </div>

                <div class="wysiwyg-box">
                    <div class="wysiwyg-toolbar" aria-label="Barra del editor">
                        <button type="button" data-command="bold"><strong>B</strong></button>
                        <button type="button" data-command="italic"><em>I</em></button>
                        <button type="button" data-command="underline"><u>U</u></button>
                        <button type="button" data-command="insertUnorderedList">Lista</button>
                        <button type="button" data-command="insertOrderedList">1. Lista</button>
                        <button type="button" id="btnAddLink">Enlace</button>
                        <button type="button" id="btnClearFormat">Limpiar</button>
                    </div>

                    <div class="wysiwyg-editor" id="composeEditor" contenteditable="true">
                        <p>Escribe aquí tu mensaje...</p>
                    </div>

                    <textarea id="composeHtml" name="composeHtml" hidden></textarea>
                </div>

                <footer class="compose-footer">
                    <button class="btn btn-light" id="btnSaveDraft" type="button">Guardar borrador</button>
                    <button class="btn btn-primary" type="submit">Enviar</button>
                </footer>
            </form>
        </div>
    </section>

    <div class="toast hidden" id="toast"></div>

    <script src="assets/js/script.js"></script>
</body>
</html>