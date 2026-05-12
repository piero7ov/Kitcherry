<?php
// ==========================================================
// KITCHERRY RESERVAS
// Archivo: mesas.php
// Gestión de mesas del negocio
// ==========================================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

protegerPagina();

$usuario = usuarioActual();

$mensaje = '';
$error = '';

function limpiarTexto($valor) {
    return trim($valor ?? '');
}

function seleccionado($valorActual, $valorOpcion) {
    return ($valorActual === $valorOpcion) ? 'selected' : '';
}

$zonasDisponibles = [
    'Interior',
    'Terraza',
    'Barra',
    'Salón principal',
    'Privado'
];

$negocio = null;
$mesas = [];

try {
    $stmt = $pdo->query("
        SELECT id, nombre 
        FROM negocios 
        WHERE activo = 1 
        ORDER BY id ASC 
        LIMIT 1
    ");

    $negocio = $stmt->fetch();

} catch (PDOException $e) {
    $error = 'No se pudo cargar el negocio configurado.';
}

// ----------------------------------------------------------
// Procesar acciones
// ----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $negocio) {

    // ------------------------------------------------------
    // Crear nueva mesa individual
    // ------------------------------------------------------
    if (($_POST['accion'] ?? '') === 'crear') {
        $nombre = limpiarTexto($_POST['nombre'] ?? '');
        $capacidad = (int)($_POST['capacidad'] ?? 0);
        $zona = limpiarTexto($_POST['zona'] ?? 'Interior');
        $orden = (int)($_POST['orden'] ?? 0);

        if ($nombre === '') {
            $error = 'El nombre de la mesa es obligatorio.';
        } elseif ($capacidad <= 0) {
            $error = 'La capacidad debe ser mayor que 0.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO mesas (
                        negocio_id,
                        nombre,
                        capacidad,
                        zona,
                        activa,
                        orden
                    )
                    VALUES (
                        :negocio_id,
                        :nombre,
                        :capacidad,
                        :zona,
                        1,
                        :orden
                    )
                ");

                $stmt->execute([
                    ':negocio_id' => $negocio['id'],
                    ':nombre' => $nombre,
                    ':capacidad' => $capacidad,
                    ':zona' => $zona,
                    ':orden' => $orden
                ]);

                $mensaje = 'Mesa creada correctamente.';

            } catch (PDOException $e) {
                $error = 'No se pudo crear la mesa.';
            }
        }
    }

    // ------------------------------------------------------
    // Crear varias mesas
    // ------------------------------------------------------
    if (($_POST['accion'] ?? '') === 'crear_masa') {
        $prefijo = limpiarTexto($_POST['prefijo'] ?? '');
        $cantidad = (int)($_POST['cantidad'] ?? 0);
        $capacidad = (int)($_POST['capacidad_masa'] ?? 0);
        $zona = limpiarTexto($_POST['zona_masa'] ?? 'Interior');
        $numeroInicial = (int)($_POST['numero_inicial'] ?? 1);
        $ordenInicial = (int)($_POST['orden_inicial'] ?? 1);

        if ($prefijo === '') {
            $error = 'Indica un prefijo para las mesas.';
        } elseif ($cantidad <= 0) {
            $error = 'La cantidad de mesas debe ser mayor que 0.';
        } elseif ($cantidad > 50) {
            $error = 'Puedes crear un máximo de 50 mesas a la vez.';
        } elseif ($capacidad <= 0) {
            $error = 'La capacidad debe ser mayor que 0.';
        } elseif (!in_array($zona, $zonasDisponibles, true)) {
            $error = 'La zona seleccionada no es válida.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO mesas (
                        negocio_id,
                        nombre,
                        capacidad,
                        zona,
                        activa,
                        orden
                    )
                    VALUES (
                        :negocio_id,
                        :nombre,
                        :capacidad,
                        :zona,
                        1,
                        :orden
                    )
                ");

                for ($i = 0; $i < $cantidad; $i++) {
                    $numeroMesa = $numeroInicial + $i;
                    $ordenMesa = $ordenInicial + $i;
                    $nombreMesa = $prefijo . ' ' . $numeroMesa;

                    $stmt->execute([
                        ':negocio_id' => $negocio['id'],
                        ':nombre' => $nombreMesa,
                        ':capacidad' => $capacidad,
                        ':zona' => $zona,
                        ':orden' => $ordenMesa
                    ]);
                }

                $pdo->commit();

                $mensaje = 'Mesas creadas correctamente.';

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = 'No se pudieron crear las mesas.';
            }
        }
    }

    // ------------------------------------------------------
    // Guardar cambios de todas las mesas
    // ------------------------------------------------------
    if (($_POST['accion'] ?? '') === 'guardar_todo') {
        $mesasPost = $_POST['mesas'] ?? [];
        $mesasValidadas = [];

        if (empty($mesasPost)) {
            $error = 'No hay mesas para actualizar.';
        } else {
            foreach ($mesasPost as $mesaId => $mesaDatos) {
                $mesaId = (int)$mesaId;
                $nombre = limpiarTexto($mesaDatos['nombre'] ?? '');
                $capacidad = (int)($mesaDatos['capacidad'] ?? 0);
                $zona = limpiarTexto($mesaDatos['zona'] ?? 'Interior');
                $orden = (int)($mesaDatos['orden'] ?? 0);

                if ($mesaId <= 0) {
                    $error = 'Hay una mesa no válida en el listado.';
                    break;
                }

                if ($nombre === '') {
                    $error = 'Todas las mesas deben tener nombre.';
                    break;
                }

                if ($capacidad <= 0) {
                    $error = 'Todas las mesas deben tener una capacidad mayor que 0.';
                    break;
                }

                if (!in_array($zona, $zonasDisponibles, true)) {
                    $zona = 'Interior';
                }

                $mesasValidadas[] = [
                    'id' => $mesaId,
                    'nombre' => $nombre,
                    'capacidad' => $capacidad,
                    'zona' => $zona,
                    'orden' => $orden
                ];
            }
        }

        if ($error === '') {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    UPDATE mesas
                    SET
                        nombre = :nombre,
                        capacidad = :capacidad,
                        zona = :zona,
                        orden = :orden,
                        actualizado_en = CURRENT_TIMESTAMP
                    WHERE id = :id
                    AND negocio_id = :negocio_id
                ");

                foreach ($mesasValidadas as $mesaValidada) {
                    $stmt->execute([
                        ':nombre' => $mesaValidada['nombre'],
                        ':capacidad' => $mesaValidada['capacidad'],
                        ':zona' => $mesaValidada['zona'],
                        ':orden' => $mesaValidada['orden'],
                        ':id' => $mesaValidada['id'],
                        ':negocio_id' => $negocio['id']
                    ]);
                }

                $pdo->commit();

                $mensaje = 'Cambios de mesas guardados correctamente.';

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = 'No se pudieron guardar los cambios de las mesas.';
            }
        }
    }

    // ------------------------------------------------------
    // Desactivar o reactivar mesa
    // ------------------------------------------------------
    if (isset($_POST['accion_estado'])) {
        $accionEstado = limpiarTexto($_POST['accion_estado']);
        $partes = explode(':', $accionEstado);

        $tipoAccion = $partes[0] ?? '';
        $mesaId = (int)($partes[1] ?? 0);

        if ($mesaId <= 0) {
            $error = 'La mesa seleccionada no es válida.';
        } elseif (!in_array($tipoAccion, ['desactivar', 'reactivar'], true)) {
            $error = 'La acción seleccionada no es válida.';
        } else {
            $nuevoEstado = ($tipoAccion === 'reactivar') ? 1 : 0;

            try {
                $stmt = $pdo->prepare("
                    UPDATE mesas
                    SET 
                        activa = :activa,
                        actualizado_en = CURRENT_TIMESTAMP
                    WHERE id = :id
                    AND negocio_id = :negocio_id
                ");

                $stmt->execute([
                    ':activa' => $nuevoEstado,
                    ':id' => $mesaId,
                    ':negocio_id' => $negocio['id']
                ]);

                $mensaje = ($nuevoEstado === 1)
                    ? 'Mesa reactivada correctamente.'
                    : 'Mesa desactivada correctamente.';

            } catch (PDOException $e) {
                $error = 'No se pudo cambiar el estado de la mesa.';
            }
        }
    }
}

// ----------------------------------------------------------
// Cargar mesas
// ----------------------------------------------------------
if ($negocio) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                nombre,
                capacidad,
                zona,
                activa,
                orden,
                creado_en
            FROM mesas
            WHERE negocio_id = :negocio_id
            ORDER BY activa DESC, zona ASC, orden ASC, nombre ASC
        ");

        $stmt->execute([
            ':negocio_id' => $negocio['id']
        ]);

        $mesas = $stmt->fetchAll();

    } catch (PDOException $e) {
        $error = 'No se pudieron cargar las mesas.';
    }
}

$totalMesas = count($mesas);
$mesasActivas = 0;
$capacidadTotal = 0;

foreach ($mesas as $mesa) {
    if ((int)$mesa['activa'] === 1) {
        $mesasActivas++;
        $capacidadTotal += (int)$mesa['capacidad'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry Reservas | Mesas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- CSS principal -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="app-layout">

        <aside class="sidebar">

        <a href="dashboard.php" class="sidebar-brand sidebar-brand-link">
            <img src="assets/img/logo.png" alt="Kitcherry" class="sidebar-logo">

            <div class="marca-texto">
                <strong class="marca-nombre">
                    <span class="kit">KIT</span><span class="cherry">CHERRY</span>
                </strong>
                <span class="marca-producto">Reservas</span>
            </div>
        </a>

            <nav class="sidebar-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="reservas.php">Reservas</a>
                <a href="mesas.php" class="active">Mesas</a>
                <a href="clientes.php">Clientes</a>
                <a href="configuracion.php">Configuración</a>
            </nav>

            <div class="sidebar-footer">
                <span><?php echo htmlspecialchars($usuario['nombre']); ?></span>
                <a href="logout.php">Cerrar sesión</a>
            </div>

        </aside>

        <main class="main-content">

            <header class="topbar">
                <div>
                    <p class="eyebrow">Distribución del local</p>
                    <h1>Gestión de mesas</h1>
                </div>
            </header>

            <?php if (!$negocio): ?>
                <section class="panel-card aviso-configuracion">
                    <h3>Primero configura el negocio</h3>
                    <p>
                        Antes de crear mesas, debes completar la configuración principal del restaurante.
                    </p>
                    <a href="configuracion.php" class="btn btn-primary btn-inline">
                        Ir a configuración
                    </a>
                </section>
            <?php endif; ?>

            <?php if ($mensaje !== ''): ?>
                <div class="alerta alerta-exito">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alerta alerta-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($negocio): ?>

                <section class="stats-grid">

                    <article class="stat-card">
                        <span class="stat-label">Mesas totales</span>
                        <strong><?php echo $totalMesas; ?></strong>
                        <p>Mesas registradas en el sistema.</p>
                    </article>

                    <article class="stat-card">
                        <span class="stat-label">Mesas activas</span>
                        <strong><?php echo $mesasActivas; ?></strong>
                        <p>Mesas disponibles para reservas.</p>
                    </article>

                    <article class="stat-card">
                        <span class="stat-label">Capacidad activa</span>
                        <strong><?php echo $capacidadTotal; ?></strong>
                        <p>Comensales máximos según mesas activas.</p>
                    </article>

                    <article class="stat-card">
                        <span class="stat-label">Negocio</span>
                        <strong class="stat-small"><?php echo htmlspecialchars($negocio['nombre']); ?></strong>
                        <p>Configuración actual del local.</p>
                    </article>

                </section>

                <section class="content-grid mesas-layout">

                    <article class="panel-card form-card">
                        <h3>Nueva mesa</h3>

                        <form method="POST" class="config-form mesa-form">
                            <input type="hidden" name="accion" value="crear">

                            <div class="form-group">
                                <label for="nombre">Nombre de la mesa</label>
                                <input 
                                    type="text" 
                                    id="nombre" 
                                    name="nombre" 
                                    placeholder="Ej: Mesa 1, Terraza 2..."
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="capacidad">Capacidad</label>
                                <input 
                                    type="number" 
                                    id="capacidad" 
                                    name="capacidad" 
                                    min="1"
                                    placeholder="Ej: 4"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="zona">Zona</label>
                                <select id="zona" name="zona">
                                    <?php foreach ($zonasDisponibles as $zona): ?>
                                        <option value="<?php echo htmlspecialchars($zona); ?>">
                                            <?php echo htmlspecialchars($zona); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="orden">Orden visual</label>
                                <input 
                                    type="number" 
                                    id="orden" 
                                    name="orden" 
                                    min="0"
                                    value="0"
                                >
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    Crear mesa
                                </button>
                            </div>
                        </form>

                        <div class="form-separator"></div>

                        <h3>Crear varias mesas</h3>

                        <form method="POST" class="config-form mesa-form">
                            <input type="hidden" name="accion" value="crear_masa">

                            <div class="form-group">
                                <label for="prefijo">Prefijo</label>
                                <input 
                                    type="text" 
                                    id="prefijo" 
                                    name="prefijo" 
                                    placeholder="Ej: Mesa, Terraza, Barra..."
                                    required
                                >
                            </div>

                            <div class="form-grid form-grid-small">
                                <div class="form-group">
                                    <label for="cantidad">Cantidad</label>
                                    <input 
                                        type="number" 
                                        id="cantidad" 
                                        name="cantidad" 
                                        min="1"
                                        max="50"
                                        placeholder="Ej: 6"
                                        required
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="capacidad_masa">Capacidad</label>
                                    <input 
                                        type="number" 
                                        id="capacidad_masa" 
                                        name="capacidad_masa" 
                                        min="1"
                                        placeholder="Ej: 4"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="zona_masa">Zona</label>
                                <select id="zona_masa" name="zona_masa">
                                    <?php foreach ($zonasDisponibles as $zona): ?>
                                        <option value="<?php echo htmlspecialchars($zona); ?>">
                                            <?php echo htmlspecialchars($zona); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-grid form-grid-small">
                                <div class="form-group">
                                    <label for="numero_inicial">Número inicial</label>
                                    <input 
                                        type="number" 
                                        id="numero_inicial" 
                                        name="numero_inicial" 
                                        min="1"
                                        value="1"
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="orden_inicial">Orden inicial</label>
                                    <input 
                                        type="number" 
                                        id="orden_inicial" 
                                        name="orden_inicial" 
                                        min="0"
                                        value="1"
                                    >
                                </div>
                            </div>

                            <p class="form-help">
                                Ejemplo: prefijo “Terraza”, cantidad 6 y número inicial 1 creará Terraza 1, Terraza 2, Terraza 3...
                            </p>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    Añadir mesas
                                </button>
                            </div>
                        </form>
                    </article>

                    <article class="panel-card">
                        <h3>Mesas registradas</h3>

                        <?php if (empty($mesas)): ?>
                            <p>
                                Todavía no hay mesas creadas. Añade la primera mesa del negocio
                                para empezar a preparar el sistema de reservas.
                            </p>
                        <?php else: ?>

                            <form method="POST" class="table-bulk-form">

                                <div class="table-wrapper">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Mesa</th>
                                                <th>Capacidad</th>
                                                <th>Zona</th>
                                                <th>Estado</th>
                                                <th>Orden</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($mesas as $mesa): ?>
                                                <tr class="<?php echo ((int)$mesa['activa'] === 0) ? 'fila-inactiva' : ''; ?>">
                                                    <td>
                                                        <input 
                                                            type="text" 
                                                            name="mesas[<?php echo (int)$mesa['id']; ?>][nombre]" 
                                                            value="<?php echo htmlspecialchars($mesa['nombre']); ?>"
                                                            required
                                                        >
                                                    </td>

                                                    <td>
                                                        <input 
                                                            type="number" 
                                                            name="mesas[<?php echo (int)$mesa['id']; ?>][capacidad]" 
                                                            min="1"
                                                            value="<?php echo (int)$mesa['capacidad']; ?>"
                                                            required
                                                        >
                                                    </td>

                                                    <td>
                                                        <select name="mesas[<?php echo (int)$mesa['id']; ?>][zona]">
                                                            <?php foreach ($zonasDisponibles as $zona): ?>
                                                                <option 
                                                                    value="<?php echo htmlspecialchars($zona); ?>"
                                                                    <?php echo seleccionado($mesa['zona'], $zona); ?>
                                                                >
                                                                    <?php echo htmlspecialchars($zona); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>

                                                    <td>
                                                        <?php if ((int)$mesa['activa'] === 1): ?>
                                                            <span class="estado-pill estado-activa">Activa</span>
                                                        <?php else: ?>
                                                            <span class="estado-pill estado-inactiva">Inactiva</span>
                                                        <?php endif; ?>
                                                    </td>

                                                    <td>
                                                        <input 
                                                            type="number" 
                                                            name="mesas[<?php echo (int)$mesa['id']; ?>][orden]" 
                                                            min="0"
                                                            value="<?php echo (int)$mesa['orden']; ?>"
                                                        >
                                                    </td>

                                                    <td>
                                                        <div class="table-actions">
                                                            <?php if ((int)$mesa['activa'] === 1): ?>
                                                                <button 
                                                                    type="submit" 
                                                                    name="accion_estado" 
                                                                    value="desactivar:<?php echo (int)$mesa['id']; ?>"
                                                                    class="btn-mini btn-muted"
                                                                >
                                                                    Desactivar
                                                                </button>
                                                            <?php else: ?>
                                                                <button 
                                                                    type="submit" 
                                                                    name="accion_estado" 
                                                                    value="reactivar:<?php echo (int)$mesa['id']; ?>"
                                                                    class="btn-mini btn-save"
                                                                >
                                                                    Reactivar
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="form-actions" style="margin-top: 18px;">
                                    <button 
                                        type="submit" 
                                        name="accion" 
                                        value="guardar_todo" 
                                        class="btn btn-primary btn-inline"
                                    >
                                        Guardar cambios de mesas
                                    </button>
                                </div>

                            </form>

                        <?php endif; ?>

                    </article>

                </section>

            <?php endif; ?>

        </main>

    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>