<?php
// ==========================================================
// KITCHERRY VOICE TASKS
// Archivo: enviar_lista.php
// ==========================================================

require_once __DIR__ . "/includes/conexion.php";
require_once __DIR__ . "/includes/generador_txt.php";
require_once __DIR__ . "/includes/smtp_mailer.php";

function limpiarTextoEnvio($valor) {
    return trim($valor ?? "");
}

function redirigirVerLista($listaId, $estado, $codigo = "", $total = 0) {
    $url = "ver_lista.php?id=" . (int)$listaId . "&envio=" . urlencode($estado);

    if ($codigo !== "") {
        $url .= "&codigo=" . urlencode($codigo);
    }

    if ($total > 0) {
        $url .= "&total=" . (int)$total;
    }

    header("Location: " . $url);
    exit;
}

function obtenerDestinatariosPorGrupos(PDO $pdo, array $nombresGrupos) {
    if (empty($nombresGrupos)) {
        return [];
    }

    $placeholders = implode(",", array_fill(0, count($nombresGrupos), "?"));

    $sql = "
        SELECT DISTINCT
            p.id,
            p.nombre,
            p.email,
            p.puesto
        FROM personal p
        INNER JOIN personal_grupos pg ON p.id = pg.personal_id
        INNER JOIN grupos g ON pg.grupo_id = g.id
        WHERE p.activo = 1
        AND g.nombre IN ($placeholders)
        ORDER BY p.nombre
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($nombresGrupos);

    return $stmt->fetchAll();
}

function obtenerTodosLosDestinatarios(PDO $pdo) {
    $stmt = $pdo->query("
        SELECT DISTINCT
            id,
            nombre,
            email,
            puesto
        FROM personal
        WHERE activo = 1
        ORDER BY nombre
    ");

    return $stmt->fetchAll();
}

function obtenerDestinatarioIndividual(PDO $pdo, int $personalId) {
    $stmt = $pdo->prepare("
        SELECT
            id,
            nombre,
            email,
            puesto
        FROM personal
        WHERE id = :id
        AND activo = 1
        LIMIT 1
    ");

    $stmt->execute([
        ":id" => $personalId
    ]);

    $persona = $stmt->fetch();

    if (!$persona) {
        return [];
    }

    return [$persona];
}

function obtenerDestinatariosSegunDestino(PDO $pdo, array $lista) {
    $destino = mb_strtolower(limpiarTextoEnvio($lista["destino"] ?? ""), "UTF-8");

    if ($destino === "cocina") {
        return obtenerDestinatariosPorGrupos($pdo, ["Cocina", "Managers"]);
    }

    if ($destino === "sala") {
        return obtenerDestinatariosPorGrupos($pdo, ["Sala", "Managers"]);
    }

    return obtenerTodosLosDestinatarios($pdo);
}

function obtenerDestinatariosSeleccionados(PDO $pdo, array $lista, string $opcion) {
    if ($opcion === "destino") {
        return obtenerDestinatariosSegunDestino($pdo, $lista);
    }

    if ($opcion === "cocina") {
        return obtenerDestinatariosPorGrupos($pdo, ["Cocina", "Managers"]);
    }

    if ($opcion === "sala") {
        return obtenerDestinatariosPorGrupos($pdo, ["Sala", "Managers"]);
    }

    if ($opcion === "managers") {
        return obtenerDestinatariosPorGrupos($pdo, ["Managers"]);
    }

    if ($opcion === "todos") {
        return obtenerTodosLosDestinatarios($pdo);
    }

    if (str_starts_with($opcion, "personal_")) {
        $personalId = (int)str_replace("personal_", "", $opcion);
        return obtenerDestinatarioIndividual($pdo, $personalId);
    }

    return [];
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$listaId = (int)($_POST["lista_id"] ?? 0);
$destinatarioSeleccionado = limpiarTextoEnvio($_POST["destinatario"] ?? "");

if ($listaId <= 0) {
    header("Location: index.php");
    exit;
}

if ($destinatarioSeleccionado === "") {
    redirigirVerLista($listaId, "error", "destinatario");
}

try {
    $resultadoTxt = guardarTxtLista($pdo, $listaId);

    if (!$resultadoTxt["ok"]) {
        redirigirVerLista($listaId, "error", "lista");
    }

    $lista = $resultadoTxt["lista"];
    $contenidoTxt = $resultadoTxt["contenido_txt"];
    $nombreArchivo = generarNombreArchivoTxt($lista);

    $destinatarios = obtenerDestinatariosSeleccionados(
        $pdo,
        $lista,
        $destinatarioSeleccionado
    );

    if (empty($destinatarios)) {
        redirigirVerLista($listaId, "error", "destinatarios");
    }

    $tituloLista = limpiarTextoEnvio($lista["titulo"] ?? "Lista");
    $destino = ucfirst(limpiarTextoEnvio($lista["destino"] ?? ""));
    $turno = limpiarTextoEnvio($lista["turno"] ?? "");

    $asunto = "KITCHERRY - " . $tituloLista;

    $cuerpo = "Hola,\n\n";
    $cuerpo .= "Te enviamos la lista preparada desde KITCHERRY Voice Tasks.\n\n";
    $cuerpo .= "Destino: " . $destino . "\n";
    $cuerpo .= "Turno: " . $turno . "\n\n";
    $cuerpo .= "También se adjunta la lista en formato TXT.\n\n";
    $cuerpo .= $contenidoTxt . "\n";

    $resultadoEnvio = smtpEnviarListaConAdjunto(
        $destinatarios,
        $asunto,
        $cuerpo,
        $nombreArchivo,
        $contenidoTxt
    );

    if (!$resultadoEnvio["ok"]) {
        redirigirVerLista($listaId, "error", "smtp");
    }

    redirigirVerLista(
        $listaId,
        "ok",
        "",
        (int)($resultadoEnvio["total_destinatarios"] ?? 0)
    );

} catch (PDOException $e) {
    redirigirVerLista($listaId, "error", "bd");
}