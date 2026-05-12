<?php
// ==========================================================
// KITCHERRY STOCK
// Archivo: config.php
// Configuración general del proyecto
// ==========================================================

// URL pública del CSV publicado desde Google Sheets.
// Google Drive / Google Sheets actúa como base de datos principal.
$CSV_PRODUCTOS = "https://docs.google.com/spreadsheets/d/e/2PACX-1vSLQFa2Wftm_3Wh6Z79VqYotOIx2Q91rdhRfowCxnLbGfGQxpKRkGMdZb-CS0Ax6UW34M0fToSpYJ5r/pub?gid=1162449281&single=true&output=csv";

// URL del Apps Script que guarda solicitudes en la pestaña "solicitudes".
$URL_APPS_SCRIPT_SOLICITUDES = "https://script.google.com/macros/s/AKfycbzYUIUzICFHT0LFjhq_11XyJHUJT9Q_UdysxZM68FXAShLDwXU_w-RBrmP0V7Auu2Pw/exec";

// Nombre visible del proyecto.
$NOMBRE_PROYECTO = "Kitcherry Stock";

// Descripción breve para el panel.
$DESCRIPCION_PROYECTO = "Panel interno de consulta de stock y registro de solicitudes de reposición para hostelería.";