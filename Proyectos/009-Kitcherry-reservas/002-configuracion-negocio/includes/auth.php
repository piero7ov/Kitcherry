<?php
// ==========================================================
// KITCHERRY RESERVAS
// Archivo: includes/auth.php
// Funciones de autenticación y sesiones
// ==========================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----------------------------------------------------------
// Comprobar si el usuario ha iniciado sesión
// ----------------------------------------------------------
function estaLogueado() {
    return isset($_SESSION['usuario_id']);
}

// ----------------------------------------------------------
// Proteger páginas privadas
// ----------------------------------------------------------
function protegerPagina() {
    if (!estaLogueado()) {
        header("Location: login.php");
        exit;
    }
}

// ----------------------------------------------------------
// Obtener datos básicos del usuario actual
// ----------------------------------------------------------
function usuarioActual() {
    return [
        'id' => $_SESSION['usuario_id'] ?? null,
        'nombre' => $_SESSION['usuario_nombre'] ?? '',
        'email' => $_SESSION['usuario_email'] ?? '',
        'rol' => $_SESSION['usuario_rol'] ?? ''
    ];
}

// ----------------------------------------------------------
// Crear sesión de usuario
// ----------------------------------------------------------
function iniciarSesionUsuario($usuario) {
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario_nombre'] = $usuario['nombre'];
    $_SESSION['usuario_email'] = $usuario['email'];
    $_SESSION['usuario_rol'] = $usuario['rol'];
}

// ----------------------------------------------------------
// Cerrar sesión
// ----------------------------------------------------------
function cerrarSesionUsuario() {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}