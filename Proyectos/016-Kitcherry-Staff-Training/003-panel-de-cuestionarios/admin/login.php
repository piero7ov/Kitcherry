<?php
// ==========================================================
// KITCHERRY STAFF TRAINING
// Panel del responsable
// Archivo: admin/login.php
// ==========================================================

declare(strict_types=1);

session_start();

require_once __DIR__ . '/panel_funciones.php';

if (!empty($_SESSION['kitcherry_staff_admin']['id'])) {
    header('Location: index.php');
    exit;
}

$errorLogin = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim((string) ($_POST['usuario'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($usuario === '' || $password === '') {
        $errorLogin = 'Introduce usuario y contraseña.';
    } else {
        try {
            $conexion = obtenerConexionPanel();
            $admin = verificarLoginAdmin($conexion, $usuario, $password);

            if ($admin === null) {
                $errorLogin = 'Usuario o contraseña incorrectos.';
            } else {
                session_regenerate_id(true);

                $_SESSION['kitcherry_staff_admin'] = [
                    'id' => (int) $admin['id'],
                    'usuario' => $admin['usuario'],
                    'rol' => $admin['rol']
                ];

                header('Location: index.php');
                exit;
            }
        } catch (Throwable $e) {
            $errorLogin = 'No se pudo iniciar sesión.';
            error_log('Kitcherry Staff Training - login admin: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry Staff Training | Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

<header class="topbar">
    <a href="login.php" class="brand">
        <img src="../assets/img/logo.png" alt="Logo Kitcherry" class="brand-logo">

        <span class="brand-text">
            <span class="brand-name">
                <span class="brand-kit">KIT</span><span class="brand-cherry">CHERRY</span>
            </span>
            <span class="brand-product">Staff Training</span>
        </span>
    </a>
</header>

<main class="page admin-login-page">

    <section class="section admin-login-card">
        <div class="section-header">
            <h1>Panel del responsable</h1>
            <p>Acceso privado para consultar el progreso de formación del equipo.</p>
        </div>

        <?php if ($errorLogin !== ''): ?>
            <div class="inline-error">
                <?php echo limpiarTexto($errorLogin); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="access-form">
            <div class="field-group">
                <label for="usuario">Usuario</label>
                <input
                    type="text"
                    id="usuario"
                    name="usuario"
                    placeholder="Usuario administrador"
                    required
                >
            </div>

            <div class="field-group">
                <label for="password">Contraseña</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Contraseña"
                    required
                >
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Entrar al panel</button>
            </div>
        </form>
    </section>

</main>

<footer class="footer">
    <p>Kitcherry Staff Training · Panel del responsable</p>
</footer>

</body>
</html>