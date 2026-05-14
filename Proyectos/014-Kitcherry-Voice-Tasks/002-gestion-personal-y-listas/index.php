<?php
// ==========================================================
// KITCHERRY VOICE TASKS
// Archivo: index.php
// ==========================================================

require_once __DIR__ . "/includes/conexion.php";

$totalPersonal = 0;
$totalListas = 0;
$totalItems = 0;
$ultimasListas = [];
$personal = [];

try {
    $totalPersonal = $pdo->query("SELECT COUNT(*) AS total FROM personal")->fetch()["total"] ?? 0;
    $totalListas = $pdo->query("SELECT COUNT(*) AS total FROM listas")->fetch()["total"] ?? 0;
    $totalItems = $pdo->query("SELECT COUNT(*) AS total FROM items_lista")->fetch()["total"] ?? 0;

    $ultimasListas = $pdo->query("
        SELECT *
        FROM listas
        ORDER BY fecha_creacion DESC
        LIMIT 6
    ")->fetchAll();

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
        LIMIT 8
    ")->fetchAll();

} catch (PDOException $e) {
    // Si la base de datos no está preparada, se evitará romper la pantalla.
}

$tituloPagina = "Kitcherry Voice Tasks";
require_once __DIR__ . "/includes/header.php";
?>

<section class="intro">
    <h2>Panel principal</h2>
    <p>
        Herramienta interna para crear listas operativas de cocina, sala o avisos generales.
        En esta fase se puede gestionar el personal y crear listas manuales antes de incorporar
        dictado por voz e inteligencia artificial local.
    </p>
</section>

<section class="resumen-grid">

    <div class="resumen-card">
        <span>Personal</span>
        <strong><?php echo (int)$totalPersonal; ?></strong>
    </div>

    <div class="resumen-card">
        <span>Listas</span>
        <strong><?php echo (int)$totalListas; ?></strong>
    </div>

    <div class="resumen-card">
        <span>Elementos</span>
        <strong><?php echo (int)$totalItems; ?></strong>
    </div>

</section>

<section class="acciones-grid">

    <a href="personal.php" class="accion-card">
        <h2>Gestionar personal</h2>
        <p>Añadir trabajadores, editar correos, asignar grupos y activar o desactivar personal.</p>
    </a>

    <a href="nueva_lista.php" class="accion-card">
        <h2>Crear lista manual</h2>
        <p>Crear una lista para cocina, sala o avisos generales clasificando cada elemento.</p>
    </a>

</section>

<section class="grid">

    <div class="card">
        <div class="card-header">
            <h2>Últimas listas</h2>
        </div>

        <?php if (!empty($ultimasListas)): ?>
            <div class="tabla-contenedor">
                <table>
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Destino</th>
                            <th>Turno</th>
                            <th>Fecha</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimasListas as $lista): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($lista["titulo"]); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($lista["destino"])); ?></td>
                                <td><?php echo htmlspecialchars($lista["turno"]); ?></td>
                                <td><?php echo htmlspecialchars($lista["fecha_creacion"]); ?></td>
                                <td>
                                    <a class="btn-tabla btn-ver" href="ver_lista.php?id=<?php echo $lista["id"]; ?>">
                                        Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No hay listas creadas todavía.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Personal registrado</h2>
        </div>

        <?php if (!empty($personal)): ?>
            <div class="tabla-contenedor">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Puesto</th>
                            <th>Grupos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($personal as $persona): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($persona["nombre"]); ?></td>
                                <td><?php echo htmlspecialchars($persona["puesto"]); ?></td>
                                <td><?php echo htmlspecialchars($persona["grupos"] ?? ""); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <a href="personal.php" class="btn btn-secundario btn-mt">Ver todo el personal</a>
        <?php else: ?>
            <p>No hay personal registrado.</p>
        <?php endif; ?>
    </div>

</section>

<?php require_once __DIR__ . "/includes/footer.php"; ?>