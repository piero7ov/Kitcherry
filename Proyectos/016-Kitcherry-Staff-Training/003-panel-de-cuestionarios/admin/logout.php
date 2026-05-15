<?php
// ==========================================================
// KITCHERRY STAFF TRAINING
// Panel del responsable
// Archivo: admin/logout.php
// ==========================================================

declare(strict_types=1);

session_start();

unset($_SESSION['kitcherry_staff_admin']);

header('Location: login.php');
exit;