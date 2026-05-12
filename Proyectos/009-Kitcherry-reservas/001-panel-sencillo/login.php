<?php
// ==========================================================
// KITCHERRY RESERVAS
// Archivo: login.php
// Pantalla de inicio de sesión
// ==========================================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (estaLogueado()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($usuario === '' || $password === '') {
        $error = 'Introduce usuario y contraseña.';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT id, nombre, email, password_hash, rol, activo
                FROM usuarios
                WHERE email = :email
                LIMIT 1
            ");

            $stmt->execute([
                ':email' => $usuario
            ]);

            $usuarioBD = $stmt->fetch();

            if (!$usuarioBD) {
                $error = 'Usuario o contraseña incorrectos.';
            } elseif ((int)$usuarioBD['activo'] !== 1) {
                $error = 'Este usuario está desactivado.';
            } elseif (!password_verify($password, $usuarioBD['password_hash'])) {
                $error = 'Usuario o contraseña incorrectos.';
            } else {
                iniciarSesionUsuario($usuarioBD);
                header("Location: dashboard.php");
                exit;
            }

        } catch (PDOException $e) {
            $error = 'Error al iniciar sesión.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kitcherry Reservas | Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- CSS principal -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">

    <main class="login-wrapper">

        <section class="login-card">

            <div class="login-brand">
                <img src="assets/img/logo.png" alt="Kitcherry" class="login-logo">

                <div class="marca-texto">
                    <strong class="marca-nombre">
                        <span class="kit">KIT</span><span class="cherry">CHERRY</span>
                    </strong>
                    <span class="marca-producto">Reservas</span>
                </div>
            </div>

            <div class="login-intro">
                <h1>Panel de reservas</h1>
                <p>
                    Accede al sistema interno para organizar reservas, mesas y clientes habituales.
                </p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alerta alerta-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="form-login">

                <div class="form-group">
                    <label for="usuario">Usuario</label>
                    <input 
                        type="text" 
                        id="usuario" 
                        name="usuario" 
                        placeholder="kitcherryadmin"
                        autocomplete="username"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Introduce tu contraseña"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary">
                    Entrar al panel
                </button>

            </form>

            <p class="login-footer">
                Kitcherry Reservas · Herramientas para hostelería
            </p>

        </section>

    </main>

    <script src="assets/js/script.js"></script>
</body>
</html>