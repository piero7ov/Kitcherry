<?php
// ==========================================================
// KITCHERRY RESERVAS
// Archivo: dashboard.php
// Panel principal inicial
// ==========================================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

protegerPagina();

$usuario = usuarioActual();

$hoy = date('Y-m-d');

$totalReservas = 0;
$totalClientes = 0;
$totalMesas = 0;
$totalComensalesHoy = 0;
$reservasHoy = 0;
$reservasPendientesHoy = 0;

try {
    $totalReservas = (int)$pdo->query("SELECT COUNT(*) FROM reservas")->fetchColumn();
    $totalClientes = (int)$pdo->query("SELECT COUNT(*) FROM clientes WHERE archivado = 0")->fetchColumn();
    $totalMesas = (int)$pdo->query("SELECT COUNT(*) FROM mesas WHERE activa = 1")->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE fecha = :fecha");
    $stmt->execute([':fecha' => $hoy]);
    $reservasHoy = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(personas), 0) FROM reservas WHERE fecha = :fecha");
    $stmt->execute([':fecha' => $hoy]);
    $totalComensalesHoy = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM reservas 
        WHERE fecha = :fecha 
        AND estado = 'pendiente'
    ");
    $stmt->execute([':fecha' => $hoy]);
    $reservasPendientesHoy = (int)$stmt->fetchColumn();

} catch (PDOException $e) {
    // En esta primera versión evitamos romper el panel si aún faltan datos.
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry Reservas | Dashboard</title>
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
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="#">Reservas</a>
                <a href="#">Mesas</a>
                <a href="#">Clientes</a>
                <a href="#">Configuración</a>
            </nav>

            <div class="sidebar-footer">
                <span><?php echo htmlspecialchars($usuario['nombre']); ?></span>
                <a href="logout.php">Cerrar sesión</a>
            </div>

        </aside>

        <main class="main-content">

            <header class="topbar">
                <div>
                    <p class="eyebrow">Panel interno</p>
                    <h1>Dashboard</h1>
                </div>
            </header>

            <section class="hero-panel">
                <div>
                    <h2>Bienvenido a Kitcherry Reservas</h2>
                    <p>
                        Esta primera versión prepara la base del sistema para organizar reservas,
                        controlar mesas y conocer mejor a los clientes habituales del negocio.
                    </p>
                </div>
            </section>

            <section class="stats-grid">

                <article class="stat-card">
                    <span class="stat-label">Reservas hoy</span>
                    <strong><?php echo $reservasHoy; ?></strong>
                    <p>Reservas registradas para la fecha actual.</p>
                </article>

                <article class="stat-card">
                    <span class="stat-label">Comensales hoy</span>
                    <strong><?php echo $totalComensalesHoy; ?></strong>
                    <p>Total previsto de personas para el servicio.</p>
                </article>

                <article class="stat-card">
                    <span class="stat-label">Pendientes hoy</span>
                    <strong><?php echo $reservasPendientesHoy; ?></strong>
                    <p>Reservas que todavía requieren revisión.</p>
                </article>

                <article class="stat-card">
                    <span class="stat-label">Mesas activas</span>
                    <strong><?php echo $totalMesas; ?></strong>
                    <p>Mesas disponibles en la configuración actual.</p>
                </article>

            </section>

            <section class="content-grid">

                <article class="panel-card">
                    <h3>Estado del proyecto</h3>
                    <p>
                        El sistema ya cuenta con base de datos, conexión SQLite, login,
                        sesiones PHP y una primera pantalla privada.
                    </p>

                    <ul class="check-list">
                        <li>Base de datos creada</li>
                        <li>Usuario administrador preparado</li>
                        <li>Conexión PHP con SQLite</li>
                        <li>Panel privado inicial</li>
                    </ul>
                </article>

                <article class="panel-card">
                    <h3>Próximos módulos</h3>
                    <p>
                        En las siguientes versiones se añadirán configuración del negocio,
                        gestión de mesas, reservas, clientes y alertas internas.
                    </p>

                    <div class="mini-tags">
                        <span>Configuración</span>
                        <span>Mesas</span>
                        <span>Reservas</span>
                        <span>Clientes</span>
                    </div>
                </article>

            </section>

        </main>

    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>