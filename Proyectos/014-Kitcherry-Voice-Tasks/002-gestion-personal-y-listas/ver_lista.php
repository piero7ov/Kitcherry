<?php
// ==========================================================
// KITCHERRY VOICE TASKS
// Archivo: ver_lista.php
// ==========================================================

require_once __DIR__ . "/includes/conexion.php";

$id = (int)($_GET["id"] ?? 0);
$lista = null;
$items = [];
$tipoLista = "";
$prioridadLista = "";

try {
    $stmtLista = $pdo->prepare("
        SELECT *
        FROM listas
        WHERE id = :id
    ");

    $stmtLista->execute([":id" => $id]);
    $lista = $stmtLista->fetch();

    if ($lista) {
        $stmtItems = $pdo->prepare("
            SELECT *
            FROM items_lista
            WHERE lista_id = :lista_id
            ORDER BY orden, id
        ");

        $stmtItems->execute([":lista_id" => $id]);
        $items = $stmtItems->fetchAll();

        if (!empty($items)) {
            $tipoLista = $items[0]["tipo"] ?? "";
            $prioridadLista = $items[0]["prioridad"] ?? "";
        }
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
                <div class="modal-nota">
                    <?php echo nl2br(htmlspecialchars($lista["texto_original"])); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($items)): ?>

                <ul class="lista-modal-items">
                    <?php foreach ($items as $item): ?>
                        <li>
                            <div class="item-modal-linea">
                                <strong><?php echo htmlspecialchars($item["descripcion"]); ?></strong>

                                <?php if (!empty($item["cantidad"])): ?>
                                    <span><?php echo htmlspecialchars($item["cantidad"]); ?></span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

            <?php else: ?>
                <p class="modal-sin-items">Esta lista no tiene elementos.</p>
            <?php endif; ?>

            <div class="acciones-form acciones-modal">
                <a href="nueva_lista.php" class="btn">Crear otra lista</a>
                <a href="index.php" class="btn btn-secundario">Volver al panel</a>
                <a 
                    href="eliminar_lista.php?id=<?php echo $lista["id"]; ?>" 
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