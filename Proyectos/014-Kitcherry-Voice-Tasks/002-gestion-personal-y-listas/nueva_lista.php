<?php
// ==========================================================
// KITCHERRY VOICE TASKS
// Archivo: nueva_lista.php
// ==========================================================

require_once __DIR__ . "/includes/conexion.php";

$error = "";

$tiposPermitidos = [
    "cocina" => [
        "Reposición",
        "Elaboración",
        "Tarea del turno",
        "Incidencia"
    ],
    "sala" => [
        "Reposición",
        "Tarea del turno",
        "Incidencia",
        "Aviso de servicio"
    ],
    "general" => [
        "Incidencia general",
        "Aviso para siguiente turno"
    ]
];

function limpiarTexto($valor) {
    return trim($valor ?? "");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $titulo = limpiarTexto($_POST["titulo"] ?? "");
    $destino = limpiarTexto($_POST["destino"] ?? "");
    $turno = limpiarTexto($_POST["turno"] ?? "");
    $tipoGeneral = limpiarTexto($_POST["tipo_general"] ?? "");
    $prioridadGeneral = limpiarTexto($_POST["prioridad_general"] ?? "normal");
    $textoOriginal = limpiarTexto($_POST["texto_original"] ?? "");

    $descripciones = $_POST["descripcion"] ?? [];
    $cantidades = $_POST["cantidad"] ?? [];

    if ($titulo === "") {
        $error = "Escribe un título para la lista.";
    } elseif (!array_key_exists($destino, $tiposPermitidos)) {
        $error = "Selecciona un destino válido.";
    } elseif ($turno === "") {
        $error = "Selecciona un turno.";
    } elseif (!in_array($tipoGeneral, $tiposPermitidos[$destino])) {
        $error = "Selecciona un tipo de lista válido.";
    } elseif (!in_array($prioridadGeneral, ["normal", "alta", "urgente"])) {
        $error = "Selecciona una prioridad válida.";
    } else {
        $itemsValidos = [];

        foreach ($descripciones as $indice => $descripcion) {
            $descripcionLimpia = limpiarTexto($descripcion);

            if ($descripcionLimpia === "") {
                continue;
            }

            $cantidad = limpiarTexto($cantidades[$indice] ?? "");

            $itemsValidos[] = [
                "descripcion" => $descripcionLimpia,
                "cantidad" => $cantidad
            ];
        }

        if (empty($itemsValidos)) {
            $error = "Añade al menos un elemento a la lista.";
        } else {
            try {
                $pdo->beginTransaction();

                $stmtLista = $pdo->prepare("
                    INSERT INTO listas (titulo, destino, turno, texto_original, estado)
                    VALUES (:titulo, :destino, :turno, :texto_original, 'creada')
                ");

                $stmtLista->execute([
                    ":titulo" => $titulo,
                    ":destino" => $destino,
                    ":turno" => $turno,
                    ":texto_original" => $textoOriginal
                ]);

                $listaId = (int)$pdo->lastInsertId();

                $stmtItem = $pdo->prepare("
                    INSERT INTO items_lista 
                    (lista_id, tipo, descripcion, cantidad, prioridad, orden)
                    VALUES
                    (:lista_id, :tipo, :descripcion, :cantidad, :prioridad, :orden)
                ");

                foreach ($itemsValidos as $orden => $item) {
                    $stmtItem->execute([
                        ":lista_id" => $listaId,
                        ":tipo" => $tipoGeneral,
                        ":descripcion" => $item["descripcion"],
                        ":cantidad" => $item["cantidad"],
                        ":prioridad" => $prioridadGeneral,
                        ":orden" => $orden + 1
                    ]);
                }

                $pdo->commit();

                header("Location: ver_lista.php?id=" . $listaId);
                exit;

            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

$tituloPagina = "Nueva lista";
require_once __DIR__ . "/includes/header.php";
?>

<section class="intro">
    <h2>Nueva lista manual</h2>
    <p>
        Crea una lista operativa para cocina, sala o avisos generales.
        El tipo y la prioridad se aplican a toda la lista para evitar repetir la misma información en cada elemento.
    </p>
</section>

<?php if ($error !== ""): ?>
    <p class="aviso error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<section class="card">
    <div class="card-header">
        <h2>Datos de la lista</h2>
    </div>

    <form method="POST" class="formulario formulario-lista">

        <div class="form-grid form-grid-lista">
            <div class="campo">
                <label for="titulo">Título</label>
                <input 
                    type="text" 
                    id="titulo" 
                    name="titulo" 
                    placeholder="Ej: Reposición turno noche"
                    required
                >
            </div>

            <div class="campo">
                <label for="destino">Destino</label>
                <select id="destino" name="destino" data-destino-lista required>
                    <option value="cocina">Cocina</option>
                    <option value="sala">Sala</option>
                    <option value="general">General</option>
                </select>
            </div>

            <div class="campo">
                <label for="turno">Turno</label>
                <select id="turno" name="turno" required>
                    <option value="">Seleccionar turno</option>
                    <option value="Mañana">Mañana</option>
                    <option value="Tarde">Tarde</option>
                    <option value="Noche">Noche</option>
                    <option value="Servicio fuerte">Servicio fuerte</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>

            <div class="campo">
                <label for="tipo_general">Tipo de lista</label>
                <select id="tipo_general" name="tipo_general" data-select-tipo required></select>
            </div>

            <div class="campo">
                <label for="prioridad_general">Prioridad</label>
                <select id="prioridad_general" name="prioridad_general" required>
                    <option value="normal">Normal</option>
                    <option value="alta">Alta</option>
                    <option value="urgente">Urgente</option>
                </select>
            </div>
        </div>

        <div class="campo">
            <label for="texto_original">Notas generales</label>
            <textarea 
                id="texto_original" 
                name="texto_original" 
                rows="4"
                placeholder="Notas opcionales sobre esta lista..."
            ></textarea>
        </div>

        <div class="bloque-items">
            <div class="bloque-items-header">
                <h2>Elementos de la lista</h2>
                <button type="button" class="btn btn-secundario" data-agregar-item>Añadir elemento</button>
            </div>

            <div id="items-lista">

                <div class="item-row item-row-simple">
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
                </div>

            </div>
        </div>

        <div class="acciones-form">
            <button type="submit" class="btn">Guardar lista</button>
            <a href="index.php" class="btn btn-secundario">Cancelar</a>
        </div>

    </form>
</section>

<?php require_once __DIR__ . "/includes/footer.php"; ?>