<?php
// ==========================================================
// KITCHERRY RESERVAS
// Archivo: logout.php
// Cierre de sesión
// ==========================================================

require_once __DIR__ . '/includes/auth.php';

cerrarSesionUsuario();

header("Location: login.php");
exit;