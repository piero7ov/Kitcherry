<?php
// ==========================================================
// KITCHERRY STAFF TRAINING
// Archivo: config.php
// ==========================================================

declare(strict_types=1);

date_default_timezone_set('Europe/Madrid');

// URL pública del CSV publicado desde Google Sheets.
const CSV_URL = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSmf9zGJXBTiPXxDdJiOpqwKV8Qy9z4GreNgz1y-SHwOG13rEuSi-ng22044qynou1QobD1OyRQLgcq/pub?gid=311359590&single=true&output=csv';

// Ruta de la base de datos SQLite.
const DB_PATH = __DIR__ . '/data/staff_training.sqlite';

// Nota mínima para aprobar el bloque.
const NOTA_MINIMA_APROBADO = 70;

// Nombre del proyecto.
const APP_NAME = 'Kitcherry Staff Training';

// Contexto del cuestionario.
const CONTEXTO_RESTAURANTE = 'Restaurante vegano';