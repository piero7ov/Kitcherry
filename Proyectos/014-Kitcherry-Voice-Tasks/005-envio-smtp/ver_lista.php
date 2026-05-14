<?php
// ==========================================================
// KITCHERRY VOICE TASKS
// Archivo: ver_lista.php
// ==========================================================

require_once __DIR__ . "/includes/conexion.php";
require_once __DIR__ . "/includes/generador_txt.php";

$id = (int)($_GET["id"] ?? 0);
$lista = null;
$items = [];
$tipoLista = "";
$prioridadLista = "";
$contenidoTxt = "";
$personalEnvio = [];

try {
    if ($id > 0) {
        $resultadoTxt = guardarTxtLista($pdo, $id);

        if ($resultadoTxt["ok"]) {
            $lista = $resultadoTxt["lista"];
            $items = $resultadoTxt["items"];
            $contenidoTxt = $resultadoTxt["contenido_txt"];

            if (!empty($items)) {
                $tipoLista = $items[0]["tipo"] ?? "";
                $prioridadLista = $items[0]["prioridad"] ?? "";
            }
        }

        $stmtPersonal = $pdo->query("
            SELECT
                id,
                nombre,
                email,
                puesto
            FROM personal
            WHERE activo = 1
            ORDER BY
                CASE puesto
                    WHEN 'Manager' THEN 1
                    WHEN 'Cocina' THEN 2
                    WHEN 'Sala' THEN 3
                    ELSE 4
                END,
                nombre
        ");

        $personalEnvio = $stmtPersonal->fetchAll();
    }

} catch (PDOException $e) {
    $error = $e->getMessage();
}

$tituloPagina = "Ver lista";
require_once __DIR__ . "/includes/header.php";
?>

<?php if (!$lista): ?>

    <section class="card">
        <div class="card-header">
            <h2>Lista no encontrada</h2>
        </div>

        <p>No se ha encontrado la lista solicitada.</p>

        <a href="index.php" class="btn">Volver al panel</a>
    </section>

<?php else: ?>

    <?php if (isset($_GET["envio"]) && $_GET["envio"] === "ok"): ?>
        <p class="aviso ok">
            Lista enviada correctamente
            <?php if (!empty($_GET["total"])): ?>
                a <?php echo (int)$_GET["total"]; ?> destinatario(s).
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if (isset($_GET["envio"]) && $_GET["envio"] === "error"): ?>
        <?php
        $codigoError = $_GET["codigo"] ?? "";

        $mensajeError = "No se pudo enviar la lista.";

        if ($codigoError === "destinatario") {
            $mensajeError = "Selecciona a quién quieres enviar la lista.";
        } elseif ($codigoError === "destinatarios") {
            $mensajeError = "No hay destinatarios activos para esa selección.";
        } elseif ($codigoError === "smtp") {
            $mensajeError = "No se pudo enviar el correo. Revisa las variables de entorno SMTP.";
        } elseif ($codigoError === "lista") {
            $mensajeError = "No se encontró la lista para enviar.";
        }
        ?>

        <p class="aviso error">
            <?php echo htmlspecialchars($mensajeError); ?>
        </p>
    <?php endif; ?>

    <section class="modal-vista-lista">

        <div class="modal-lista">

            <div class="modal-lista-header">
                <div class="modal-titulo-bloque">
                    <div class="modal-titulo-linea">
                        <h2><?php echo htmlspecialchars($lista["titulo"]); ?></h2>

                        <div class="badges-lista">
                            <?php if ($tipoLista !== ""): ?>
                                <span class="badge-lista badge-tipo">
                                    <?php echo htmlspecialchars($tipoLista); ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($prioridadLista !== ""): ?>
                                <span class="badge-lista prioridad prioridad-<?php echo htmlspecialchars(strtolower($prioridadLista)); ?>">
                                    Prioridad: <?php echo htmlspecialchars($prioridadLista); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <p>
                        <?php echo htmlspecialchars(ucfirst($lista["destino"])); ?> ·
                        <?php echo htmlspecialchars($lista["turno"]); ?> ·
                        <?php echo htmlspecialchars($lista["fecha_creacion"]); ?>
                    </p>
                </div>

                <a href="index.php" class="cerrar-modal">×</a>
            </div>

            <?php if (!empty($lista["texto_original"])): ?>
                <details class="modal-nota">
                    <summary>Ver dictado original</summary>
                    <p><?php echo nl2br(htmlspecialchars($lista["texto_original"])); ?></p>
                </details>
            <?php endif; ?>

            <div class="ayuda-edicion">
                Doble clic sobre la descripción o la cantidad para editar rápidamente.
            </div>

            <?php if (!empty($items)): ?>

                <ul class="lista-modal-items">
                    <?php foreach ($items as $item): ?>
                        <li>
                            <div class="item-modal-linea">
                                <strong 
                                    data-editable="1"
                                    data-item-id="<?php echo (int)$item["id"]; ?>"
                                    data-campo="descripcion"
                                >
                                    <?php echo htmlspecialchars($item["descripcion"]); ?>
                                </strong>

                                <span 
                                    class="<?php echo empty($item["cantidad"]) ? 'cantidad-vacia' : ''; ?>"
                                    data-editable="1"
                                    data-item-id="<?php echo (int)$item["id"]; ?>"
                                    data-campo="cantidad"
                                >
                                    <?php echo !empty($item["cantidad"]) ? htmlspecialchars($item["cantidad"]) : "Sin cantidad"; ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

            <?php else: ?>
                <p class="modal-sin-items">Esta lista no tiene elementos.</p>
            <?php endif; ?>

            <form method="POST" action="enviar_lista.php" class="modal-nota">
                <input type="hidden" name="lista_id" value="<?php echo (int)$lista["id"]; ?>">

                <div class="campo">
                    <label for="destinatario">Enviar lista a</label>

                    <select id="destinatario" name="destinatario" required>
                        <option value="destino">Según destino de la lista</option>
                        <option value="cocina">Cocina + managers</option>
                        <option value="sala">Sala + managers</option>
                        <option value="managers">Solo managers</option>
                        <option value="todos">Todo el personal activo</option>

                        <?php if (!empty($personalEnvio)): ?>
                            <option disabled>──────────</option>
                            <?php foreach ($personalEnvio as $persona): ?>
                                <option value="personal_<?php echo (int)$persona["id"]; ?>">
                                    <?php echo htmlspecialchars($persona["nombre"] . " · " . $persona["puesto"]); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="acciones-form">
                    <button type="submit" class="btn">
                        Enviar lista
                    </button>
                </div>
            </form>

            <details class="modal-nota">
                <summary>Ver previsualización de la lista</summary>
                <pre data-preview-txt style="white-space: pre-wrap; margin-top: 12px; font-family: Consolas, monospace;"><?php echo htmlspecialchars($contenidoTxt); ?></pre>
            </details>

            <div class="acciones-form acciones-modal">
                <a href="nueva_lista.php" class="btn">Crear nueva lista</a>

                <a href="descargar_txt.php?id=<?php echo (int)$lista["id"]; ?>" class="btn btn-secundario">
                    Descargar lista
                </a>

                <a href="index.php" class="btn btn-secundario">Volver al panel</a>

                <a 
                    href="eliminar_lista.php?id=<?php echo (int)$lista["id"]; ?>" 
                    class="btn btn-peligro"
                    onclick="return confirm('¿Seguro que quieres eliminar esta lista?')"
                >
                    Eliminar lista
                </a>
            </div>

        </div>

    </section>

<?php endif; ?>

<?php require_once __DIR__ . "/includes/footer.php"; ?>