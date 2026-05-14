<?php
// ==========================================================
// KITCHERRY VOICE TASKS
// Archivo: index.php
// ==========================================================

require_once __DIR__ . "/includes/conexion.php";

// Comprobar si existen tablas en la base de datos
$tablas = $pdo->query("
    SELECT name 
    FROM sqlite_master 
    WHERE type = 'table'
    AND name NOT LIKE 'sqlite_%'
    ORDER BY name
")->fetchAll();

$grupos = [];
$personal = [];

try {
    $grupos = $pdo->query("
        SELECT * 
        FROM grupos 
        ORDER BY nombre
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
    ")->fetchAll();

} catch (PDOException $e) {
    // Si todavía no se han creado las tablas, evitamos que se rompa la pantalla
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry Voice Tasks</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="header">
    <div class="contenedor header-contenido">

        <div class="marca">
            <img src="assets/img/logo.png" alt="Logo Kitcherry" class="logo">

            <div class="marca-texto">
                <h1>
                    <span class="kit">KIT</span><span class="cherry">CHERRY</span>
                </h1>
                <p>Voice Tasks</p>
            </div>
        </div>

    </div>
</header>

<main class="contenedor">

    <section class="intro">
        <h2>Panel inicial del proyecto</h2>
        <p>
            Kitcherry Voice Tasks es una herramienta interna para hostelería pensada
            para crear listas operativas de cocina, sala o avisos generales. Esta base
            prepara la estructura SQLite necesaria para gestionar personal, grupos,
            listas, elementos y futuros envíos por correo.
        </p>
    </section>

    <section class="card">
        <div class="card-header">
            <h2>Estado de la base de datos</h2>
        </div>

        <?php if (empty($tablas)): ?>
            <p class="aviso error">
                Todavía no hay tablas creadas.
            </p>

            <a class="btn" href="db/init_db.php">
                Crear base de datos
            </a>
        <?php else: ?>
            <p class="aviso ok">
                La base de datos existe y tiene tablas creadas.
            </p>

            <a class="btn" href="db/init_db.php">
                Ejecutar creación o actualización de tablas
            </a>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-header">
            <h2>Tablas detectadas</h2>
        </div>

        <?php if (!empty($tablas)): ?>
            <ul class="lista-simple">
                <?php foreach ($tablas as $tabla): ?>
                    <li><?php echo htmlspecialchars($tabla["name"]); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No hay tablas todavía.</p>
        <?php endif; ?>
    </section>

    <section class="grid">

        <div class="card">
            <div class="card-header">
                <h2>Grupos</h2>
            </div>

            <?php if (!empty($grupos)): ?>
                <div class="tabla-contenedor">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Grupo</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grupos as $grupo): ?>
                                <tr>
                                    <td><?php echo $grupo["id"]; ?></td>
                                    <td><?php echo htmlspecialchars($grupo["nombre"]); ?></td>
                                    <td><?php echo htmlspecialchars($grupo["descripcion"]); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No hay grupos creados.</p>
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
                                <th>Email</th>
                                <th>Puesto</th>
                                <th>Grupos</th>
                                <th>Estado</th>
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
                                        <?php echo $persona["activo"] ? "Activo" : "Inactivo"; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No hay personal creado.</p>
            <?php endif; ?>
        </div>

    </section>

</main>

<footer class="footer">
    <p>
        <span>KIT</span><strong>CHERRY</strong> Voice Tasks · 2026
    </p>
</footer>

</body>
</html>