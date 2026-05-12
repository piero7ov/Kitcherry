<?php
// ==========================================================
// KITCHERRY RESERVAS
// Archivo: index.php
// Redirección inicial
// ==========================================================

require_once __DIR__ . '/includes/auth.php';

if (estaLogueado()) {
    header("Location: dashboard.php");
    exit;
}

header("Location: login.php");
exit;