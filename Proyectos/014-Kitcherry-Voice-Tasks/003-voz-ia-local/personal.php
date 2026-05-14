<?php
// ==========================================================
// KITCHERRY VOICE TASKS
// Archivo: personal.php
// ==========================================================

require_once __DIR__ . "/includes/conexion.php";

$mensaje = "";
$error = "";
$personaEditar = null;
$gruposPersonaEditar = [];

function limpiarTexto($valor) {
    return trim($valor ?? "");
}

try {
    $grupos = $pdo->query("
        SELECT *
        FROM grupos
        ORDER BY nombre
    ")->fetchAll();

    if (isset($_GET["toggle"])) {
        $idToggle = (int)$_GET["toggle"];

        $stmt = $pdo->prepare("
            UPDATE personal
            SET activo = CASE WHEN activo = 1 THEN 0 ELSE 1 END
            WHERE id = :id
        ");

        $stmt->execute([":id" => $idToggle]);

        header("Location: personal.php");
        exit;
    }

    if (isset($_GET["eliminar"])) {
        $idEliminar = (int)$_GET["eliminar"];

        $stmt = $pdo->prepare("DELETE FROM personal WHERE id = :id");
        $stmt->execute([":id" => $idEliminar]);

        header("Location: personal.php");
        exit;
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $id = (int)($_POST["id"] ?? 0);
        $nombre = limpiarTexto($_POST["nombre"] ?? "");
        $email = limpiarTexto($_POST["email"] ?? "");
        $puesto = limpiarTexto($_POST["puesto"] ?? "");
        $activo = isset($_POST["activo"]) ? 1 : 0;
        $gruposSeleccionados = $_POST["grupos"] ?? [];

        if ($nombre === "" || $email === "" || $puesto === "") {
            $error = "Completa nombre, email y puesto.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "El email no tiene un formato válido.";
        } elseif (empty($gruposSeleccionados)) {
            $error = "Selecciona al menos un grupo.";
        } else {
            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE personal
                    SET nombre = :nombre,
                        email = :email,
                        puesto = :puesto,
                        activo = :activo
                    WHERE id = :id
                ");

                $stmt->execute([
                    ":nombre" => $nombre,
                    ":email" => $email,
                    ":puesto" => $puesto,
                    ":activo" => $activo,
                    ":id" => $id
                ]);

                $personalId = $id;

            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO personal (nombre, email, puesto, activo)
                    VALUES (:nombre, :email, :puesto, :activo)
                ");

                $stmt->execute([
                    ":nombre" => $nombre,
                    ":email" => $email,
                    ":puesto" => $puesto,
                    ":activo" => $activo
                ]);

                $personalId = (int)$pdo->lastInsertId();
            }

            $stmtBorrarGrupos = $pdo->prepare("
                DELETE FROM personal_grupos
                WHERE personal_id = :personal_id
            ");

            $stmtBorrarGrupos->execute([
                ":personal_id" => $personalId
            ]);

            $stmtAsignarGrupo = $pdo->prepare("
                INSERT OR IGNORE INTO personal_grupos (personal_id, grupo_id)
                VALUES (:personal_id, :grupo_id)
            ");

            foreach ($gruposSeleccionados as $grupoId) {
                $stmtAsignarGrupo->execute([
                    ":personal_id" => $personalId,
                    ":grupo_id" => (int)$grupoId
                ]);
            }

            header("Location: personal.php");
            exit;
        }
    }

    if (isset($_GET["editar"])) {
        $idEditar = (int)$_GET["editar"];

        $stmt = $pdo->prepare("SELECT * FROM personal WHERE id = :id");
        $stmt->execute([":id" => $idEditar]);
        $personaEditar = $stmt->fetch();

        $stmtGrupos = $pdo->prepare("
            SELECT grupo_id
            FROM personal_grupos
            WHERE personal_id = :personal_id
        ");

        $stmtGrupos->execute([":personal_id" => $idEditar]);
        $gruposPersonaEditar = array_column($stmtGrupos->fetchAll(), "grupo_id");
    }

    $personal = $pdo->query("
        SELECT 
            p.id,
            p.nombre,
            p.email,
            p.puesto,
            p.activo,
            GROUP_CONCAT(g.nombre, ', ') AS grupos
        FROM personal p
        LEFT JOIN personal_grupos pg ON p.id = pg.personal_id
        LEFT JOIN grupos g ON pg.grupo_id = g.id
        GROUP BY p.id
        ORDER BY 
            CASE p.puesto
                WHEN 'Manager' THEN 1
                WHEN 'Cocina' THEN 2
                WHEN 'Sala' THEN 3
                ELSE 4
            END,
            p.nombre
    ")->fetchAll();

} catch (PDOException $e) {
    $error = $e->getMessage();
    $grupos = [];
    $personal = [];
}

$tituloPagina = "Personal";
require_once __DIR__ . "/includes/header.php";
?>

<section class="intro">
    <h2>Gestión de personal</h2>
    <p>
        Desde esta pantalla se pueden añadir trabajadores, modificar sus datos,
        asignarlos a cocina, sala o managers, y activar o desactivar su disponibilidad.
    </p>
</section>

<?php if ($error !== ""): ?>
    <p class="aviso error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<section class="personal-layout">

    <div class="card card-form-personal">
        <div class="card-header">
            <h2><?php echo $personaEditar ? "Editar trabajador" : "Añadir trabajador"; ?></h2>
        </div>

        <form method="POST" class="formulario formulario-personal">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($personaEditar["id"] ?? "0"); ?>">

            <div class="form-personal-grid">
                <div class="campo">
                    <label for="nombre">Nombre</label>
                    <input 
                        type="text" 
                        id="nombre" 
                        name="nombre" 
                        value="<?php echo htmlspecialchars($personaEditar["nombre"] ?? ""); ?>"
                        required
                    >
                </div>

                <div class="campo">
                    <label for="email">Correo electrónico</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?php echo htmlspecialchars($personaEditar["email"] ?? ""); ?>"
                        required
                    >
                </div>

                <div class="campo">
                    <label for="puesto">Puesto</label>
                    <select id="puesto" name="puesto" required>
                        <?php
                        $puestos = ["Manager", "Cocina", "Sala", "Otro"];
                        $puestoActual = $personaEditar["puesto"] ?? "";
                        ?>
                        <option value="">Seleccionar puesto</option>
                        <?php foreach ($puestos as $puesto): ?>
                            <option 
                                value="<?php echo $puesto; ?>"
                                <?php echo $puestoActual === $puesto ? "selected" : ""; ?>
                            >
                                <?php echo $puesto; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="personal-opciones">
                <div class="campo campo-grupos">
                    <label>Grupos</label>

                    <div class="checks">
                        <?php foreach ($grupos as $grupo): ?>
                            <label>
                                <input 
                                    type="checkbox" 
                                    name="grupos[]" 
                                    value="<?php echo $grupo["id"]; ?>"
                                    <?php echo in_array($grupo["id"], $gruposPersonaEditar) ? "checked" : ""; ?>
                                >
                                <?php echo htmlspecialchars($grupo["nombre"]); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="campo check-simple">
                    <label>
                        <input 
                            type="checkbox" 
                            name="activo"
                            <?php echo !isset($personaEditar["activo"]) || (int)$personaEditar["activo"] === 1 ? "checked" : ""; ?>
                        >
                        Trabajador activo
                    </label>
                </div>

                <div class="acciones-form acciones-personal">
                    <button type="submit" class="btn">
                        <?php echo $personaEditar ? "Guardar cambios" : "Añadir trabajador"; ?>
                    </button>

                    <?php if ($personaEditar): ?>
                        <a href="personal.php" class="btn btn-secundario">Cancelar edición</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <div class="card card-tabla-personal">
        <div class="card-header">
            <h2>Personal registrado</h2>
        </div>

        <?php if (!empty($personal)): ?>
            <div class="tabla-contenedor tabla-contenedor-personal">
                <table class="tabla-personal">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Puesto</th>
                            <th>Grupos</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($personal as $persona): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($persona["nombre"]); ?></td>
                                <td><?php echo htmlspecialchars($persona["email"]); ?></td>
                                <td><?php echo htmlspecialchars($persona["puesto"]); ?></td>
                                <td><?php echo htmlspecialchars($persona["grupos"] ?? ""); ?></td>
                                <td>
                                    <span class="<?php echo $persona["activo"] ? "estado-activo" : "estado-inactivo"; ?>">
                                        <?php echo $persona["activo"] ? "Activo" : "Inactivo"; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="acciones-tabla">
                                        <a class="btn-tabla btn-editar" href="personal.php?editar=<?php echo $persona["id"]; ?>">
                                            Editar
                                        </a>

                                        <a class="btn-tabla btn-estado" href="personal.php?toggle=<?php echo $persona["id"]; ?>">
                                            <?php echo $persona["activo"] ? "Desactivar" : "Activar"; ?>
                                        </a>

                                        <a 
                                            class="btn-tabla btn-eliminar"
                                            href="personal.php?eliminar=<?php echo $persona["id"]; ?>"
                                            onclick="return confirm('¿Seguro que quieres eliminar este trabajador?')"
                                        >
                                            Eliminar
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No hay personal registrado.</p>
        <?php endif; ?>
    </div>

</section>

<?php require_once __DIR__ . "/includes/footer.php"; ?>