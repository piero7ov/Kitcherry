<?php
// ==========================================================
// KITCHERRY STAFF TRAINING
// Panel del responsable
// Archivo: admin/proteger.php
// ==========================================================

declare(strict_types=1);

session_start();

require_once __DIR__ . '/panel_funciones.php';

if (empty($_SESSION['kitcherry_staff_admin']['id'])) {
    header('Location: login.php');
    exit;
}

$adminSesion = $_SESSION['kitcherry_staff_admin'];