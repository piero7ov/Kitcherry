<?php
// ==========================================================
// KITCHERRY RESERVAS
// Archivo: configuracion.php
// Configuración del negocio
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

function valorCampo($datos, $campo, $default = '') {
    return htmlspecialchars($datos[$campo] ?? $default);
}

function seleccionado($valorActual, $valorOpcion) {
    return ($valorActual === $valorOpcion) ? 'selected' : '';
}

function marcado($valor) {
    return ((int)$valor === 1) ? 'checked' : '';
}

$diasSemana = [
    'lunes' => 'Lunes',
    'martes' => 'Martes',
    'miercoles' => 'Miércoles',
    'jueves' => 'Jueves',
    'viernes' => 'Viernes',
    'sabado' => 'Sábado',
    'domingo' => 'Domingo'
];

$horarios = [];

foreach ($diasSemana as $clave => $nombre) {
    $horarios[$clave] = [
        'cerrado' => 0,
        'hora_apertura' => '',
        'hora_cierre' => ''
    ];
}

$datos = [
    'id' => null,
    'nombre' => '',
    'tipo_negocio' => '',
    'tipo_cocina' => '',
    'telefono' => '',
    'email' => '',
    'direccion' => '',
    'ciudad' => '',
    'capacidad_maxima' => 0,
    'tono_comunicacion' => 'cercano',

    'duracion_media_reserva' => 90,
    'margen_limpieza_mesa' => 15,
    'tamano_grupo_grande' => 6,
    'permite_terraza' => 0
];

// ----------------------------------------------------------
// Cargar configuración existente
// ----------------------------------------------------------
try {
    $stmt = $pdo->query("
        SELECT 
            n.id,
            n.nombre,
            n.tipo_negocio,
            n.tipo_cocina,
            n.telefono,
            n.email,
            n.direccion,
            n.ciudad,
            n.capacidad_maxima,
            n.tono_comunicacion,

            cr.duracion_media_reserva,
            cr.margen_limpieza_mesa,
            cr.tamano_grupo_grande,
            cr.permite_terraza

        FROM negocios n
        LEFT JOIN configuracion_reservas cr 
            ON cr.negocio_id = n.id
        WHERE n.activo = 1
        ORDER BY n.id ASC
        LIMIT 1
    ");

    $configuracionExistente = $stmt->fetch();

    if ($configuracionExistente) {
        $datos = array_merge($datos, $configuracionExistente);

        $stmtHorarios = $pdo->prepare("
            SELECT dia_semana, cerrado, hora_apertura, hora_cierre
            FROM horarios_negocio
            WHERE negocio_id = :negocio_id
        ");

        $stmtHorarios->execute([
            ':negocio_id' => $datos['id']
        ]);

        $horariosBD = $stmtHorarios->fetchAll();

        foreach ($horariosBD as $horarioBD) {
            $dia = $horarioBD['dia_semana'];

            if (isset($horarios[$dia])) {
                $horarios[$dia] = [
                    'cerrado' => (int)$horarioBD['cerrado'],
                    'hora_apertura' => $horarioBD['hora_apertura'] ?? '',
                    'hora_cierre' => $horarioBD['hora_cierre'] ?? ''
                ];
            }
        }
    }

} catch (PDOException $e) {
    $error = 'No se pudo cargar la configuración del negocio.';
}

// ----------------------------------------------------------
// Guardar configuración
// ----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = limpiarTexto($_POST['nombre'] ?? '');
    $tipoNegocio = limpiarTexto($_POST['tipo_negocio'] ?? '');
    $tipoCocina = limpiarTexto($_POST['tipo_cocina'] ?? '');
    $telefono = limpiarTexto($_POST['telefono'] ?? '');
    $email = limpiarTexto($_POST['email'] ?? '');
    $direccion = limpiarTexto($_POST['direccion'] ?? '');
    $ciudad = limpiarTexto($_POST['ciudad'] ?? '');
    $capacidadMaxima = (int)($_POST['capacidad_maxima'] ?? 0);
    $tonoComunicacion = limpiarTexto($_POST['tono_comunicacion'] ?? 'cercano');

    $duracionMediaReserva = (int)($_POST['duracion_media_reserva'] ?? 90);
    $margenLimpiezaMesa = (int)($_POST['margen_limpieza_mesa'] ?? 15);
    $tamanoGrupoGrande = (int)($_POST['tamano_grupo_grande'] ?? 6);
    $permiteTerraza = isset($_POST['permite_terraza']) ? 1 : 0;

    $horariosPost = $_POST['horarios'] ?? [];
    $horariosValidados = [];

    foreach ($diasSemana as $claveDia => $nombreDia) {
        $cerrado = isset($horariosPost[$claveDia]['cerrado']) ? 1 : 0;
        $horaApertura = limpiarTexto($horariosPost[$claveDia]['hora_apertura'] ?? '');
        $horaCierre = limpiarTexto($horariosPost[$claveDia]['hora_cierre'] ?? '');

        if ($cerrado === 1) {
            $horaApertura = '';
            $horaCierre = '';
        }

        $horariosValidados[$claveDia] = [
            'cerrado' => $cerrado,
            'hora_apertura' => $horaApertura,
            'hora_cierre' => $horaCierre
        ];
    }

    if ($nombre === '') {
        $error = 'El nombre del negocio es obligatorio.';
    }

    if ($error === '') {
        foreach ($diasSemana as $claveDia => $nombreDia) {
            $horarioDia = $horariosValidados[$claveDia];

            if ((int)$horarioDia['cerrado'] === 0) {
                if ($horarioDia['hora_apertura'] === '' || $horarioDia['hora_cierre'] === '') {
                    $error = 'Indica hora de apertura y cierre para ' . $nombreDia . ' o marca el día como cerrado.';
                    break;
                }
            }
        }
    }

    if ($error === '') {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->query("
                SELECT id 
                FROM negocios 
                WHERE activo = 1 
                ORDER BY id ASC 
                LIMIT 1
            ");

            $negocioActual = $stmt->fetch();

            if ($negocioActual) {
                $negocioId = (int)$negocioActual['id'];

                $stmt = $pdo->prepare("
                    UPDATE negocios
                    SET
                        nombre = :nombre,
                        tipo_negocio = :tipo_negocio,
                        tipo_cocina = :tipo_cocina,
                        telefono = :telefono,
                        email = :email,
                        direccion = :direccion,
                        ciudad = :ciudad,
                        capacidad_maxima = :capacidad_maxima,
                        tono_comunicacion = :tono_comunicacion,
                        actualizado_en = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':nombre' => $nombre,
                    ':tipo_negocio' => $tipoNegocio,
                    ':tipo_cocina' => $tipoCocina,
                    ':telefono' => $telefono,
                    ':email' => $email,
                    ':direccion' => $direccion,
                    ':ciudad' => $ciudad,
                    ':capacidad_maxima' => $capacidadMaxima,
                    ':tono_comunicacion' => $tonoComunicacion,
                    ':id' => $negocioId
                ]);

            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO negocios (
                        nombre,
                        tipo_negocio,
                        tipo_cocina,
                        telefono,
                        email,
                        direccion,
                        ciudad,
                        capacidad_maxima,
                        tono_comunicacion,
                        activo
                    )
                    VALUES (
                        :nombre,
                        :tipo_negocio,
                        :tipo_cocina,
                        :telefono,
                        :email,
                        :direccion,
                        :ciudad,
                        :capacidad_maxima,
                        :tono_comunicacion,
                        1
                    )
                ");

                $stmt->execute([
                    ':nombre' => $nombre,
                    ':tipo_negocio' => $tipoNegocio,
                    ':tipo_cocina' => $tipoCocina,
                    ':telefono' => $telefono,
                    ':email' => $email,
                    ':direccion' => $direccion,
                    ':ciudad' => $ciudad,
                    ':capacidad_maxima' => $capacidadMaxima,
                    ':tono_comunicacion' => $tonoComunicacion
                ]);

                $negocioId = (int)$pdo->lastInsertId();
            }

            $stmt = $pdo->prepare("
                SELECT id 
                FROM configuracion_reservas 
                WHERE negocio_id = :negocio_id
                LIMIT 1
            ");

            $stmt->execute([
                ':negocio_id' => $negocioId
            ]);

            $configActual = $stmt->fetch();

            if ($configActual) {
                $stmt = $pdo->prepare("
                    UPDATE configuracion_reservas
                    SET
                        duracion_media_reserva = :duracion_media_reserva,
                        margen_limpieza_mesa = :margen_limpieza_mesa,
                        tamano_grupo_grande = :tamano_grupo_grande,
                        trabaja_por_turnos = 0,
                        permite_terraza = :permite_terraza,
                        actualizado_en = CURRENT_TIMESTAMP
                    WHERE negocio_id = :negocio_id
                ");

                $stmt->execute([
                    ':duracion_media_reserva' => $duracionMediaReserva,
                    ':margen_limpieza_mesa' => $margenLimpiezaMesa,
                    ':tamano_grupo_grande' => $tamanoGrupoGrande,
                    ':permite_terraza' => $permiteTerraza,
                    ':negocio_id' => $negocioId
                ]);

            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO configuracion_reservas (
                        negocio_id,
                        duracion_media_reserva,
                        margen_limpieza_mesa,
                        tamano_grupo_grande,
                        trabaja_por_turnos,
                        permite_terraza
                    )
                    VALUES (
                        :negocio_id,
                        :duracion_media_reserva,
                        :margen_limpieza_mesa,
                        :tamano_grupo_grande,
                        0,
                        :permite_terraza
                    )
                ");

                $stmt->execute([
                    ':negocio_id' => $negocioId,
                    ':duracion_media_reserva' => $duracionMediaReserva,
                    ':margen_limpieza_mesa' => $margenLimpiezaMesa,
                    ':tamano_grupo_grande' => $tamanoGrupoGrande,
                    ':permite_terraza' => $permiteTerraza
                ]);
            }

            foreach ($diasSemana as $claveDia => $nombreDia) {
                $horarioDia = $horariosValidados[$claveDia];

                $stmt = $pdo->prepare("
                    SELECT id 
                    FROM horarios_negocio
                    WHERE negocio_id = :negocio_id
                    AND dia_semana = :dia_semana
                    LIMIT 1
                ");

                $stmt->execute([
                    ':negocio_id' => $negocioId,
                    ':dia_semana' => $claveDia
                ]);

                $horarioActual = $stmt->fetch();

                if ($horarioActual) {
                    $stmt = $pdo->prepare("
                        UPDATE horarios_negocio
                        SET
                            cerrado = :cerrado,
                            hora_apertura = :hora_apertura,
                            hora_cierre = :hora_cierre,
                            actualizado_en = CURRENT_TIMESTAMP
                        WHERE id = :id
                    ");

                    $stmt->execute([
                        ':cerrado' => $horarioDia['cerrado'],
                        ':hora_apertura' => $horarioDia['hora_apertura'],
                        ':hora_cierre' => $horarioDia['hora_cierre'],
                        ':id' => $horarioActual['id']
                    ]);

                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO horarios_negocio (
                            negocio_id,
                            dia_semana,
                            cerrado,
                            hora_apertura,
                            hora_cierre
                        )
                        VALUES (
                            :negocio_id,
                            :dia_semana,
                            :cerrado,
                            :hora_apertura,
                            :hora_cierre
                        )
                    ");

                    $stmt->execute([
                        ':negocio_id' => $negocioId,
                        ':dia_semana' => $claveDia,
                        ':cerrado' => $horarioDia['cerrado'],
                        ':hora_apertura' => $horarioDia['hora_apertura'],
                        ':hora_cierre' => $horarioDia['hora_cierre']
                    ]);
                }
            }

            $pdo->commit();

            $mensaje = 'Configuración guardada correctamente.';

            $datos = [
                'id' => $negocioId,
                'nombre' => $nombre,
                'tipo_negocio' => $tipoNegocio,
                'tipo_cocina' => $tipoCocina,
                'telefono' => $telefono,
                'email' => $email,
                'direccion' => $direccion,
                'ciudad' => $ciudad,
                'capacidad_maxima' => $capacidadMaxima,
                'tono_comunicacion' => $tonoComunicacion,

                'duracion_media_reserva' => $duracionMediaReserva,
                'margen_limpieza_mesa' => $margenLimpiezaMesa,
                'tamano_grupo_grande' => $tamanoGrupoGrande,
                'permite_terraza' => $permiteTerraza
            ];

            $horarios = $horariosValidados;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = 'No se pudo guardar la configuración.';
        }
    } else {
        $datos = [
            'id' => $datos['id'],
            'nombre' => $nombre,
            'tipo_negocio' => $tipoNegocio,
            'tipo_cocina' => $tipoCocina,
            'telefono' => $telefono,
            'email' => $email,
            'direccion' => $direccion,
            'ciudad' => $ciudad,
            'capacidad_maxima' => $capacidadMaxima,
            'tono_comunicacion' => $tonoComunicacion,

            'duracion_media_reserva' => $duracionMediaReserva,
            'margen_limpieza_mesa' => $margenLimpiezaMesa,
            'tamano_grupo_grande' => $tamanoGrupoGrande,
            'permite_terraza' => $permiteTerraza
        ];

        $horarios = $horariosValidados;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry Reservas | Configuración</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- CSS principal -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="app-layout">

        <aside class="sidebar">

            <div class="sidebar-brand">
                <img src="assets/img/logo.png" alt="Kitcherry" class="sidebar-logo">

                <div class="marca-texto">
                    <strong class="marca-nombre">
                        <span class="kit">KIT</span><span class="cherry">CHERRY</span>
                    </strong>
                    <span class="marca-producto">Reservas</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="reservas.php">Reservas</a>
                <a href="mesas.php">Mesas</a>
                <a href="#">Clientes</a>
                <a href="configuracion.php" class="active">Configuración</a>
            </nav>

            <div class="sidebar-footer">
                <span><?php echo htmlspecialchars($usuario['nombre']); ?></span>
                <a href="logout.php">Cerrar sesión</a>
            </div>

        </aside>

        <main class="main-content">

            <header class="topbar">
                <div>
                    <p class="eyebrow">Ajustes del sistema</p>
                    <h1>Configuración del negocio</h1>
                </div>
            </header>

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

            <section class="panel-card form-card">

                <form method="POST" class="config-form">

                    <div class="form-section">
                        <h3>Datos generales</h3>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nombre">Nombre del negocio *</label>
                                <input 
                                    type="text" 
                                    id="nombre" 
                                    name="nombre" 
                                    value="<?php echo valorCampo($datos, 'nombre'); ?>"
                                    placeholder="Ej: Restaurante Kitcherry"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="tipo_negocio">Tipo de negocio</label>
                                <select id="tipo_negocio" name="tipo_negocio">
                                    <option value="">Selecciona una opción</option>
                                    <option value="Restaurante" <?php echo seleccionado($datos['tipo_negocio'], 'Restaurante'); ?>>Restaurante</option>
                                    <option value="Bar" <?php echo seleccionado($datos['tipo_negocio'], 'Bar'); ?>>Bar</option>
                                    <option value="Cafetería" <?php echo seleccionado($datos['tipo_negocio'], 'Cafetería'); ?>>Cafetería</option>
                                    <option value="Bar de tapas" <?php echo seleccionado($datos['tipo_negocio'], 'Bar de tapas'); ?>>Bar de tapas</option>
                                    <option value="Restaurante familiar" <?php echo seleccionado($datos['tipo_negocio'], 'Restaurante familiar'); ?>>Restaurante familiar</option>
                                    <option value="Restaurante grande" <?php echo seleccionado($datos['tipo_negocio'], 'Restaurante grande'); ?>>Restaurante grande</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="tipo_cocina">Tipo de cocina</label>
                                <input 
                                    type="text" 
                                    id="tipo_cocina" 
                                    name="tipo_cocina" 
                                    value="<?php echo valorCampo($datos, 'tipo_cocina'); ?>"
                                    placeholder="Ej: Mediterránea, china, italiana..."
                                >
                            </div>

                            <div class="form-group">
                                <label for="capacidad_maxima">Capacidad máxima</label>
                                <input 
                                    type="number" 
                                    id="capacidad_maxima" 
                                    name="capacidad_maxima" 
                                    min="0"
                                    value="<?php echo valorCampo($datos, 'capacidad_maxima', 0); ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="telefono">Teléfono</label>
                                <input 
                                    type="text" 
                                    id="telefono" 
                                    name="telefono" 
                                    value="<?php echo valorCampo($datos, 'telefono'); ?>"
                                    placeholder="Ej: 600 000 000"
                                >
                            </div>

                            <div class="form-group">
                                <label for="email">Email del negocio</label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    value="<?php echo valorCampo($datos, 'email'); ?>"
                                    placeholder="info@restaurante.com"
                                >
                            </div>

                            <div class="form-group">
                                <label for="direccion">Dirección</label>
                                <input 
                                    type="text" 
                                    id="direccion" 
                                    name="direccion" 
                                    value="<?php echo valorCampo($datos, 'direccion'); ?>"
                                    placeholder="Dirección del local"
                                >
                            </div>

                            <div class="form-group">
                                <label for="ciudad">Ciudad</label>
                                <input 
                                    type="text" 
                                    id="ciudad" 
                                    name="ciudad" 
                                    value="<?php echo valorCampo($datos, 'ciudad'); ?>"
                                    placeholder="Ej: Valencia"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Reglas de reservas</h3>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="duracion_media_reserva">Duración media de reserva</label>
                                <input 
                                    type="number" 
                                    id="duracion_media_reserva" 
                                    name="duracion_media_reserva" 
                                    min="15"
                                    step="5"
                                    value="<?php echo valorCampo($datos, 'duracion_media_reserva', 90); ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="margen_limpieza_mesa">Margen entre reservas</label>
                                <input 
                                    type="number" 
                                    id="margen_limpieza_mesa" 
                                    name="margen_limpieza_mesa" 
                                    min="0"
                                    step="5"
                                    value="<?php echo valorCampo($datos, 'margen_limpieza_mesa', 15); ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="tamano_grupo_grande">Grupo grande desde</label>
                                <input 
                                    type="number" 
                                    id="tamano_grupo_grande" 
                                    name="tamano_grupo_grande" 
                                    min="1"
                                    value="<?php echo valorCampo($datos, 'tamano_grupo_grande', 6); ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="tono_comunicacion">Tono de comunicación</label>
                                <select id="tono_comunicacion" name="tono_comunicacion">
                                    <option value="formal" <?php echo seleccionado($datos['tono_comunicacion'], 'formal'); ?>>Formal</option>
                                    <option value="cercano" <?php echo seleccionado($datos['tono_comunicacion'], 'cercano'); ?>>Cercano</option>
                                    <option value="elegante" <?php echo seleccionado($datos['tono_comunicacion'], 'elegante'); ?>>Elegante</option>
                                    <option value="familiar" <?php echo seleccionado($datos['tono_comunicacion'], 'familiar'); ?>>Familiar</option>
                                    <option value="juvenil" <?php echo seleccionado($datos['tono_comunicacion'], 'juvenil'); ?>>Juvenil</option>
                                    <option value="tradicional" <?php echo seleccionado($datos['tono_comunicacion'], 'tradicional'); ?>>Tradicional</option>
                                </select>
                            </div>
                        </div>

                        <div class="check-options">
                            <label class="check-row">
                                <input 
                                    type="checkbox" 
                                    name="permite_terraza" 
                                    value="1"
                                    <?php echo marcado($datos['permite_terraza']); ?>
                                >
                                <span>El negocio dispone de terraza</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Horario semanal</h3>

                        <div class="horario-semanal">
                            <?php foreach ($diasSemana as $claveDia => $nombreDia): ?>
                                <div class="horario-row <?php echo ((int)$horarios[$claveDia]['cerrado'] === 1) ? 'is-closed' : ''; ?>">
                                    
                                    <div class="horario-dia">
                                        <strong><?php echo $nombreDia; ?></strong>
                                    </div>

                                    <label class="horario-cerrado">
                                        <input 
                                            type="checkbox"
                                            class="js-dia-cerrado"
                                            name="horarios[<?php echo $claveDia; ?>][cerrado]"
                                            value="1"
                                            <?php echo marcado($horarios[$claveDia]['cerrado']); ?>
                                        >
                                        <span>Cerrado</span>
                                    </label>

                                    <div class="form-group horario-input">
                                        <label>Apertura</label>
                                        <input 
                                            type="time"
                                            name="horarios[<?php echo $claveDia; ?>][hora_apertura]"
                                            value="<?php echo htmlspecialchars($horarios[$claveDia]['hora_apertura']); ?>"
                                        >
                                    </div>

                                    <div class="form-group horario-input">
                                        <label>Cierre</label>
                                        <input 
                                            type="time"
                                            name="horarios[<?php echo $claveDia; ?>][hora_cierre]"
                                            value="<?php echo htmlspecialchars($horarios[$claveDia]['hora_cierre']); ?>"
                                        >
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            Guardar configuración
                        </button>
                    </div>

                </form>

            </section>

        </main>

    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>