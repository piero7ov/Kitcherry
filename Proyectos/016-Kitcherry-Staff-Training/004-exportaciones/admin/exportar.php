<?php
// ==========================================================
// KITCHERRY STAFF TRAINING
// Panel del responsable
// Archivo: admin/exportar.php
// Exportaciones PDF
// ==========================================================

declare(strict_types=1);

require_once __DIR__ . '/proteger.php';

class PdfSimple
{
    private array $pages = [];
    private string $content = '';
    private float $width;
    private float $height;
    private float $margin = 36;
    private float $y = 0;
    private string $title = '';

    public function __construct(string $orientation = 'P')
    {
        if ($orientation === 'L') {
            $this->width = 841.89;
            $this->height = 595.28;
        } else {
            $this->width = 595.28;
            $this->height = 841.89;
        }
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function addPage(): void
    {
        if ($this->content !== '') {
            $this->pages[] = [
                'content' => $this->content,
                'width' => $this->width,
                'height' => $this->height
            ];
        }

        $this->content = '';
        $this->y = $this->height - $this->margin;

        $this->text('KITCHERRY Staff Training', $this->margin, $this->height - 28, 10, true, [0.07, 0.07, 0.07]);
        $this->line($this->margin, $this->height - 38, $this->width - $this->margin, $this->height - 38, [0.85, 0.82, 0.80]);

        $this->y = $this->height - 62;
    }

    private function ensurePage(): void
    {
        if ($this->content === '') {
            $this->addPage();
        }
    }

    private function ensureSpace(float $height): void
    {
        $this->ensurePage();

        if (($this->y - $height) < $this->margin) {
            $this->addPage();
        }
    }

    private function pdfText(string $text): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

        if ($converted === false) {
            $converted = $text;
        }

        $converted = str_replace(["\\", "(", ")", "\r", "\n"], ["\\\\", "\\(", "\\)", " ", " "], $converted);

        return $converted;
    }

    private function color(array $rgb, string $mode): string
    {
        return sprintf('%.3F %.3F %.3F %s', $rgb[0], $rgb[1], $rgb[2], $mode);
    }

    private function textWidth(string $text, float $size): float
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

        if ($converted === false) {
            $converted = $text;
        }

        return strlen($converted) * $size * 0.48;
    }

    private function wrapText(string $text, float $maxWidth, float $size): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        if ($text === '') {
            return [''];
        }

        $words = preg_split('/\s+/', $text);
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $test = $line === '' ? $word : $line . ' ' . $word;

            if ($this->textWidth($test, $size) <= $maxWidth) {
                $line = $test;
            } else {
                if ($line !== '') {
                    $lines[] = $line;
                }

                $line = $word;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    public function text(
        string $text,
        float $x,
        float $y,
        float $size = 10,
        bool $bold = false,
        array $color = [0, 0, 0]
    ): void {
        $font = $bold ? '/F2' : '/F1';
        $safe = $this->pdfText($text);

        $this->content .= $this->color($color, 'rg') . "\n";
        $this->content .= "BT {$font} {$size} Tf {$x} {$y} Td ({$safe}) Tj ET\n";
    }

    public function line(float $x1, float $y1, float $x2, float $y2, array $color = [0, 0, 0]): void
    {
        $this->content .= $this->color($color, 'RG') . "\n";
        $this->content .= "{$x1} {$y1} m {$x2} {$y2} l S\n";
    }

    public function rect(float $x, float $y, float $w, float $h, array $fill, ?array $stroke = null): void
    {
        $this->content .= "q\n";
        $this->content .= $this->color($fill, 'rg') . "\n";
        $this->content .= "{$x} {$y} {$w} {$h} re f\n";
        $this->content .= "Q\n";

        if ($stroke !== null) {
            $this->content .= $this->color($stroke, 'RG') . "\n";
            $this->content .= "{$x} {$y} {$w} {$h} re S\n";
        }
    }

    public function title(string $text): void
    {
        $this->ensureSpace(42);
        $this->text($text, $this->margin, $this->y, 22, true, [0.07, 0.07, 0.07]);
        $this->y -= 30;
    }

    public function subtitle(string $text): void
    {
        $this->paragraph($text, 10, false, [0.35, 0.35, 0.38], 8);
    }

    public function section(string $text): void
    {
        $this->ensureSpace(32);
        $this->y -= 6;
        $this->text($text, $this->margin, $this->y, 15, true, [0.76, 0.09, 0.16]);
        $this->y -= 22;
    }

    public function paragraph(
        string $text,
        float $size = 10,
        bool $bold = false,
        array $color = [0, 0, 0],
        float $after = 10
    ): void {
        $maxWidth = $this->width - ($this->margin * 2);
        $lineHeight = $size + 4;
        $lines = $this->wrapText($text, $maxWidth, $size);

        foreach ($lines as $line) {
            $this->ensureSpace($lineHeight);
            $this->text($line, $this->margin, $this->y, $size, $bold, $color);
            $this->y -= $lineHeight;
        }

        $this->y -= $after;
    }

    public function keyValueRows(array $rows): void
    {
        $labelWidth = 150;
        $valueWidth = $this->width - ($this->margin * 2) - $labelWidth;

        foreach ($rows as $row) {
            $this->ensureSpace(22);

            $this->rect($this->margin, $this->y - 17, $labelWidth, 22, [1, 0.95, 0.96], [0.88, 0.84, 0.82]);
            $this->rect($this->margin + $labelWidth, $this->y - 17, $valueWidth, 22, [1, 1, 1], [0.88, 0.84, 0.82]);

            $this->text((string) $row[0], $this->margin + 6, $this->y - 10, 9, true, [0.07, 0.07, 0.07]);
            $this->text((string) $row[1], $this->margin + $labelWidth + 6, $this->y - 10, 9, false, [0.18, 0.18, 0.20]);

            $this->y -= 22;
        }

        $this->y -= 10;
    }

    public function table(array $headers, array $rows, array $widths, float $fontSize = 8): void
    {
        $this->ensurePage();

        $drawHeader = function () use ($headers, $widths, $fontSize) {
            $height = 24;
            $x = $this->margin;

            $this->ensureSpace($height);

            foreach ($headers as $i => $header) {
                $w = $widths[$i];
                $this->rect($x, $this->y - $height, $w, $height, [1, 0.95, 0.96], [0.82, 0.78, 0.76]);
                $this->text((string) $header, $x + 4, $this->y - 15, $fontSize, true, [0.07, 0.07, 0.07]);
                $x += $w;
            }

            $this->y -= $height;
        };

        $drawHeader();

        foreach ($rows as $row) {
            $lineHeight = $fontSize + 3;
            $wrappedCells = [];
            $maxLines = 1;

            foreach ($row as $i => $cell) {
                $w = $widths[$i] - 8;
                $lines = $this->wrapText((string) $cell, $w, $fontSize);
                $wrappedCells[$i] = $lines;
                $maxLines = max($maxLines, count($lines));
            }

            $rowHeight = max(22, ($maxLines * $lineHeight) + 10);

            if (($this->y - $rowHeight) < $this->margin) {
                $this->addPage();
                $drawHeader();
            }

            $x = $this->margin;

            foreach ($row as $i => $cell) {
                $w = $widths[$i];
                $this->rect($x, $this->y - $rowHeight, $w, $rowHeight, [1, 1, 1], [0.88, 0.84, 0.82]);

                $textY = $this->y - 13;

                foreach ($wrappedCells[$i] as $line) {
                    $this->text($line, $x + 4, $textY, $fontSize, false, [0.16, 0.16, 0.18]);
                    $textY -= $lineHeight;
                }

                $x += $w;
            }

            $this->y -= $rowHeight;
        }

        $this->y -= 14;
    }

    public function output(): string
    {
        if ($this->content !== '') {
            $this->pages[] = [
                'content' => $this->content,
                'width' => $this->width,
                'height' => $this->height
            ];
            $this->content = '';
        }

        $objects = [];

        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';

        $fontRegularObject = 3;
        $fontBoldObject = 4;

        $objects[] = '';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $pageObjectNumbers = [];

        foreach ($this->pages as $page) {
            $contentObjectNumber = count($objects) + 1;
            $stream = $page['content'];

            $objects[] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream";

            $pageObjectNumber = count($objects) + 1;
            $pageObjectNumbers[] = $pageObjectNumber;

            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$page['width']} {$page['height']}] /Resources << /Font << /F1 {$fontRegularObject} 0 R /F2 {$fontBoldObject} 0 R >> >> /Contents {$contentObjectNumber} 0 R >>";
        }

        $kids = implode(' ', array_map(function ($number) {
            return $number . ' 0 R';
        }, $pageObjectNumbers));

        $objects[1] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($pageObjectNumbers) . " >>";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $objectNumber = $index + 1;
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= "{$objectNumber} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $totalObjects = count($objects) + 1;

        $pdf .= "xref\n0 {$totalObjects}\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i < $totalObjects; $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size {$totalObjects} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}

function limpiarNombreArchivoPdf(string $texto): string
{
    $texto = strtolower(trim($texto));
    $texto = preg_replace('/[^a-z0-9áéíóúñü@._-]+/iu', '-', $texto);
    $texto = trim((string) $texto, '-');

    return $texto !== '' ? $texto : 'exportacion';
}

function enviarPdf(string $nombreArchivo, string $contenido): void
{
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Content-Length: ' . strlen($contenido));
    header('Pragma: no-cache');
    header('Expires: 0');

    echo $contenido;
    exit;
}

$tipo = trim((string) ($_GET['tipo'] ?? ''));

try {
    $conexion = obtenerConexionPanel();

    $preguntas = cargarPreguntasDesdeCsvUrl(CSV_URL);
    $bloques = obtenerBloques($preguntas);

    if ($tipo === 'general') {
        $trabajadores = obtenerResumenTrabajadores($conexion, $bloques);
        $resumenGeneral = obtenerResumenGeneralPanel($conexion, $bloques);

        $pdf = new PdfSimple('L');
        $pdf->setTitle('Resumen general por correo electrónico');
        $pdf->addPage();

        $pdf->title('Resumen general por correo electrónico');
        $pdf->subtitle('Exportación generada el ' . date('d/m/Y H:i') . '. Este informe resume el progreso de formación de cada trabajador usando el correo electrónico como identificador principal.');

        $pdf->section('Resumen global');
        $pdf->keyValueRows([
            ['Trabajadores registrados', (string) ($resumenGeneral['total_trabajadores'] ?? 0)],
            ['Intentos realizados', (string) ($resumenGeneral['total_intentos'] ?? 0)],
            ['Bloques disponibles', (string) ($resumenGeneral['total_bloques'] ?? 0)],
            ['Formaciones completas', (string) ($resumenGeneral['formaciones_completas'] ?? 0)],
            ['Formaciones incompletas', (string) ($resumenGeneral['formaciones_incompletas'] ?? 0)],
            ['Trabajadores con bloques a repasar', (string) ($resumenGeneral['necesitan_repasar'] ?? 0)]
        ]);

        $pdf->section('Resumen por trabajador');

        if (empty($trabajadores)) {
            $pdf->paragraph('Todavía no hay trabajadores con resultados guardados.', 10, false, [0.35, 0.35, 0.38]);
        } else {
            $rows = [];

            foreach ($trabajadores as $trabajador) {
                $rows[] = [
                    $trabajador['trabajador'] ?? '',
                    $trabajador['email'] ?? '',
                    $trabajador['estado_general'] ?? '',
                    (int) ($trabajador['porcentaje_general'] ?? 0) . '%',
                    (int) ($trabajador['bloques_aprobados'] ?? 0) . '/' . (int) ($trabajador['total_bloques'] ?? 0),
                    (string) ((int) ($trabajador['bloques_pendientes'] ?? 0)),
                    (string) ((int) ($trabajador['bloques_para_repasar'] ?? 0)),
                    (string) ((int) ($trabajador['total_intentos'] ?? 0)),
                    formatearFechaPanel($trabajador['ultimo_intento'] ?? '')
                ];
            }

            $pdf->table(
                ['Trabajador', 'Email', 'Estado', 'General', 'Aprobados', 'Pend.', 'Rep.', 'Intentos', 'Último'],
                $rows,
                [105, 165, 110, 58, 65, 45, 45, 55, 90],
                7.5
            );
        }

        enviarPdf(
            'kitcherry_staff_training_resumen_general_por_email_' . date('Ymd_His') . '.pdf',
            $pdf->output()
        );
    }

    if ($tipo === 'trabajador') {
        $email = normalizarEmail((string) ($_GET['email'] ?? ''));

        if ($email === '') {
            header('Location: index.php');
            exit;
        }

        $trabajador = obtenerDatosTrabajador($conexion, $email);

        if ($trabajador === null) {
            header('Location: index.php');
            exit;
        }

        $progreso = obtenerProgresoDetalladoTrabajador($conexion, $email, $bloques);
        $progresoSimple = obtenerMejoresIntentosPorEmail($conexion, $email);
        $resumen = obtenerResumenProgreso($bloques, $progresoSimple);
        $intentos = obtenerIntentosPorEmail($conexion, $email);
        $bloquesClasificados = obtenerBloquesPendientesYRepasoTrabajador($progreso);
        $preguntasFalladas = obtenerPreguntasFalladasPorTrabajador($conexion, $email, 100);

        $pdf = new PdfSimple('P');
        $pdf->setTitle('Resumen individual del trabajador');
        $pdf->addPage();

        $pdf->title('Resumen individual del trabajador');
        $pdf->subtitle('Exportación generada el ' . date('d/m/Y H:i') . '.');

        $pdf->section('Datos del trabajador');
        $pdf->keyValueRows([
            ['Trabajador', $trabajador['trabajador'] ?? ''],
            ['Email', $email],
            ['Estado general', $resumen['estado_general'] ?? ''],
            ['Porcentaje general', (int) ($resumen['porcentaje_general'] ?? 0) . '%'],
            ['Bloques aprobados', (int) ($resumen['bloques_aprobados'] ?? 0) . ' de ' . (int) ($resumen['total_bloques'] ?? 0)],
            ['Bloques pendientes', (string) ((int) ($resumen['bloques_pendientes'] ?? 0))],
            ['Bloques a repasar', (string) ((int) ($resumen['bloques_para_repasar'] ?? 0))]
        ]);

        $pdf->section('Progreso por bloque');

        $progresoRows = [];

        foreach ($progreso as $bloque) {
            $progresoRows[] = [
                $bloque['bloque'] ?? '',
                $bloque['estado'] ?? '',
                $bloque['mejor_porcentaje'] !== null ? (int) $bloque['mejor_porcentaje'] . '%' : 'Pendiente',
                (string) ((int) ($bloque['total_intentos'] ?? 0)),
                formatearFechaPanel($bloque['ultimo_intento'] ?? '')
            ];
        }

        $pdf->table(
            ['Bloque', 'Estado', 'Mejor', 'Intentos', 'Último intento'],
            $progresoRows,
            [145, 115, 60, 60, 140],
            8
        );

        $pdf->section('Bloques pendientes');

        if (empty($bloquesClasificados['pendientes'])) {
            $pdf->paragraph('No tiene bloques pendientes.', 10, false, [0.35, 0.35, 0.38]);
        } else {
            foreach ($bloquesClasificados['pendientes'] as $bloque) {
                $pdf->paragraph('- ' . ($bloque['bloque'] ?? ''), 10, false, [0.18, 0.18, 0.20], 2);
            }
        }

        $pdf->section('Bloques a repasar');

        if (empty($bloquesClasificados['repasar'])) {
            $pdf->paragraph('No tiene bloques a repasar.', 10, false, [0.35, 0.35, 0.38]);
        } else {
            foreach ($bloquesClasificados['repasar'] as $bloque) {
                $pdf->paragraph(
                    '- ' . ($bloque['bloque'] ?? '') . ' - mejor resultado: ' . (int) ($bloque['mejor_porcentaje'] ?? 0) . '%',
                    10,
                    false,
                    [0.18, 0.18, 0.20],
                    2
                );
            }
        }

        $pdf->section('Historial de intentos');

        if (empty($intentos)) {
            $pdf->paragraph('No hay intentos registrados.', 10, false, [0.35, 0.35, 0.38]);
        } else {
            $historialRows = [];

            foreach ($intentos as $intento) {
                $historialRows[] = [
                    formatearFechaPanel($intento['fecha_fin'] ?? ''),
                    $intento['bloque'] ?? '',
                    (int) ($intento['porcentaje'] ?? 0) . '%',
                    (int) ($intento['aciertos'] ?? 0) . '/' . (int) ($intento['total_preguntas'] ?? 0),
                    $intento['estado'] ?? '',
                    formatearDuracion((int) ($intento['duracion_segundos'] ?? 0))
                ];
            }

            $pdf->table(
                ['Fecha', 'Bloque', 'Resultado', 'Aciertos', 'Estado', 'Duración'],
                $historialRows,
                [92, 120, 60, 60, 110, 78],
                7.5
            );
        }

        $pdf->section('Preguntas falladas');

        if (empty($preguntasFalladas)) {
            $pdf->paragraph('No tiene preguntas falladas registradas.', 10, false, [0.35, 0.35, 0.38]);
        } else {
            $fallosRows = [];

            foreach ($preguntasFalladas as $pregunta) {
                $fallosRows[] = [
                    $pregunta['bloque'] ?? '',
                    $pregunta['pregunta'] ?? '',
                    $pregunta['respuesta_correcta'] ?? '',
                    (string) ((int) ($pregunta['total_fallos'] ?? 0)),
                    formatearFechaPanel($pregunta['ultimo_fallo'] ?? '')
                ];
            }

            $pdf->table(
                ['Bloque', 'Pregunta', 'Correcta', 'Fallos', 'Último fallo'],
                $fallosRows,
                [95, 215, 65, 45, 100],
                7
            );
        }

        $nombreTrabajador = limpiarNombreArchivoPdf((string) ($trabajador['trabajador'] ?? 'trabajador'));

        enviarPdf(
            'kitcherry_staff_training_' . $nombreTrabajador . '_' . date('Ymd_His') . '.pdf',
            $pdf->output()
        );
    }

    header('Location: index.php');
    exit;
} catch (Throwable $e) {
    error_log('Kitcherry Staff Training - exportar PDF: ' . $e->getMessage());

    header('Location: index.php');
    exit;
}