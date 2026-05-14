<?php
// ==========================================================
// KITCHERRY VOICE TASKS
// Archivo: db/init_db.php
// ==========================================================

require_once __DIR__ . "/../includes/conexion.php";

try {

    // ======================================================
    // TABLA: personal
    // Guarda todos los trabajadores del negocio
    // ======================================================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS personal (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            puesto TEXT,
            activo INTEGER DEFAULT 1,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // ======================================================
    // TABLA: grupos
    // Guarda los grupos principales de destinatarios
    // ======================================================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS grupos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL UNIQUE,
            descripcion TEXT
        );
    ");

    // ======================================================
    // TABLA: personal_grupos
    // Relaciona trabajadores con uno o varios grupos
    // ======================================================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS personal_grupos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            personal_id INTEGER NOT NULL,
            grupo_id INTEGER NOT NULL,

            FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE,
            FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,

            UNIQUE(personal_id, grupo_id)
        );
    ");

    // ======================================================
    // TABLA: listas
    // Guarda las listas creadas para cocina, sala o general
    // ======================================================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS listas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo TEXT NOT NULL,
            destino TEXT NOT NULL,
            turno TEXT,
            texto_original TEXT,
            contenido_txt TEXT,
            fecha_creacion TEXT DEFAULT CURRENT_TIMESTAMP,
            estado TEXT DEFAULT 'generada'
        );
    ");

    // ======================================================
    // TABLA: items_lista
    // Guarda los elementos individuales de cada lista
    // ======================================================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS items_lista (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            lista_id INTEGER NOT NULL,
            tipo TEXT NOT NULL,
            descripcion TEXT NOT NULL,
            cantidad TEXT,
            zona TEXT,
            prioridad TEXT DEFAULT 'normal',
            estado TEXT DEFAULT 'pendiente',
            orden INTEGER DEFAULT 0,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP,

            FOREIGN KEY (lista_id) REFERENCES listas(id) ON DELETE CASCADE
        );
    ");

    // ======================================================
    // TABLA: envios
    // Guarda el historial de envíos por correo
    // ======================================================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS envios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            lista_id INTEGER NOT NULL,
            personal_id INTEGER,
            email_destino TEXT NOT NULL,
            fecha_envio TEXT DEFAULT CURRENT_TIMESTAMP,
            estado_envio TEXT DEFAULT 'pendiente',
            mensaje_error TEXT,

            FOREIGN KEY (lista_id) REFERENCES listas(id) ON DELETE CASCADE,
            FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE SET NULL
        );
    ");

    // ======================================================
    // GRUPOS INICIALES
    // ======================================================
    $gruposIniciales = [
        [
            "nombre" => "Cocina",
            "descripcion" => "Personal de cocina que recibe listas de reposición, elaboración, tareas e incidencias."
        ],
        [
            "nombre" => "Sala",
            "descripcion" => "Personal de sala y barra que recibe listas de reposición, tareas e incidencias."
        ],
        [
            "nombre" => "Managers",
            "descripcion" => "Encargados o responsables que reciben las listas importantes del turno."
        ]
    ];

    $insertarGrupo = $pdo->prepare("
        INSERT OR IGNORE INTO grupos (nombre, descripcion)
        VALUES (:nombre, :descripcion)
    ");

    $actualizarGrupo = $pdo->prepare("
        UPDATE grupos
        SET descripcion = :descripcion
        WHERE nombre = :nombre
    ");

    foreach ($gruposIniciales as $grupo) {
        $insertarGrupo->execute([
            ":nombre" => $grupo["nombre"],
            ":descripcion" => $grupo["descripcion"]
        ]);

        $actualizarGrupo->execute([
            ":nombre" => $grupo["nombre"],
            ":descripcion" => $grupo["descripcion"]
        ]);
    }

    // ======================================================
    // OBTENER IDS DE LOS GRUPOS
    // ======================================================
    $grupoCocina = $pdo->query("SELECT id FROM grupos WHERE nombre = 'Cocina'")->fetch();
    $grupoSala = $pdo->query("SELECT id FROM grupos WHERE nombre = 'Sala'")->fetch();
    $grupoManagers = $pdo->query("SELECT id FROM grupos WHERE nombre = 'Managers'")->fetch();

    $idCocina = $grupoCocina["id"];
    $idSala = $grupoSala["id"];
    $idManagers = $grupoManagers["id"];

    // ======================================================
    // PERSONAL INICIAL
    // Incluye trabajadores reales para pruebas y ejemplos
    // ======================================================
    $personalInicial = [
        [
            "nombre" => "Piero Olivares",
            "email" => "piero7ov@gmail.com",
            "puesto" => "Manager",
            "grupos" => [$idManagers]
        ],
        [
            "nombre" => "Jose Velaszquez",
            "email" => "pollo1304@gmail.com",
            "puesto" => "Cocina",
            "grupos" => [$idCocina]
        ],
        [
            "nombre" => "Pocho Osan",
            "email" => "pieroolivaresdev@gmail.com",
            "puesto" => "Sala",
            "grupos" => [$idSala]
        ],

        // Personal ficticio de cocina
        [
            "nombre" => "Mario Torres",
            "email" => "mario.cocina@ejemplo.com",
            "puesto" => "Cocina",
            "grupos" => [$idCocina]
        ],
        [
            "nombre" => "Lucia Romero",
            "email" => "lucia.cocina@ejemplo.com",
            "puesto" => "Cocina",
            "grupos" => [$idCocina]
        ],
        [
            "nombre" => "Daniel Herrera",
            "email" => "daniel.cocina@ejemplo.com",
            "puesto" => "Cocina",
            "grupos" => [$idCocina]
        ],
        [
            "nombre" => "Sara Molina",
            "email" => "sara.cocina@ejemplo.com",
            "puesto" => "Cocina",
            "grupos" => [$idCocina]
        ],
        [
            "nombre" => "Raul Navarro",
            "email" => "raul.cocina@ejemplo.com",
            "puesto" => "Cocina",
            "grupos" => [$idCocina]
        ],

        // Personal ficticio de sala
        [
            "nombre" => "Andrea Ruiz",
            "email" => "andrea.sala@ejemplo.com",
            "puesto" => "Sala",
            "grupos" => [$idSala]
        ],
        [
            "nombre" => "Carlos Medina",
            "email" => "carlos.sala@ejemplo.com",
            "puesto" => "Sala",
            "grupos" => [$idSala]
        ],
        [
            "nombre" => "Nerea Castillo",
            "email" => "nerea.sala@ejemplo.com",
            "puesto" => "Sala",
            "grupos" => [$idSala]
        ],
        [
            "nombre" => "Ivan Serrano",
            "email" => "ivan.sala@ejemplo.com",
            "puesto" => "Sala",
            "grupos" => [$idSala]
        ],
        [
            "nombre" => "Paula Marin",
            "email" => "paula.sala@ejemplo.com",
            "puesto" => "Sala",
            "grupos" => [$idSala]
        ]
    ];

    $insertarPersonal = $pdo->prepare("
        INSERT OR IGNORE INTO personal (nombre, email, puesto, activo)
        VALUES (:nombre, :email, :puesto, 1)
    ");

    $actualizarPersonal = $pdo->prepare("
        UPDATE personal
        SET 
            nombre = :nombre,
            puesto = :puesto,
            activo = 1
        WHERE email = :email
    ");

    $buscarPersonal = $pdo->prepare("
        SELECT id FROM personal WHERE email = :email
    ");

    $asignarGrupo = $pdo->prepare("
        INSERT OR IGNORE INTO personal_grupos (personal_id, grupo_id)
        VALUES (:personal_id, :grupo_id)
    ");

    foreach ($personalInicial as $persona) {
        $insertarPersonal->execute([
            ":nombre" => $persona["nombre"],
            ":email" => $persona["email"],
            ":puesto" => $persona["puesto"]
        ]);

        $actualizarPersonal->execute([
            ":nombre" => $persona["nombre"],
            ":email" => $persona["email"],
            ":puesto" => $persona["puesto"]
        ]);

        $buscarPersonal->execute([
            ":email" => $persona["email"]
        ]);

        $personalBD = $buscarPersonal->fetch();

        if ($personalBD) {
            foreach ($persona["grupos"] as $grupoId) {
                $asignarGrupo->execute([
                    ":personal_id" => $personalBD["id"],
                    ":grupo_id" => $grupoId
                ]);
            }
        }
    }

    $mensaje = "Base de datos creada correctamente.";
    $detalle = "Se han preparado las tablas principales, los grupos iniciales y el personal de prueba.";

} catch (PDOException $e) {
    $mensaje = "Error al crear la base de datos.";
    $detalle = $e->getMessage();
    $error = true;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry Voice Tasks</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header class="header">
    <div class="contenedor header-contenido">

        <div class="marca">
            <img src="../assets/img/logo.png" alt="Logo Kitcherry" class="logo">

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
        <h2>Preparación de la base de datos</h2>
        <p>
            Este proceso crea la base de datos SQLite, prepara las tablas principales
            e inserta los grupos y trabajadores iniciales necesarios para organizar
            listas de cocina, sala y managers.
        </p>
    </section>

    <section class="card">
        <div class="card-header">
            <h2><?php echo htmlspecialchars($mensaje); ?></h2>
        </div>

        <p class="<?php echo isset($error) ? 'aviso error' : 'aviso ok'; ?>">
            <?php echo htmlspecialchars($detalle); ?>
        </p>

        <a class="btn" href="../index.php">Volver al panel</a>
    </section>

</main>

<footer class="footer">
    <p>
        <span>KIT</span><strong>CHERRY</strong> Voice Tasks · 2026
    </p>
</footer>

</body>
</html>