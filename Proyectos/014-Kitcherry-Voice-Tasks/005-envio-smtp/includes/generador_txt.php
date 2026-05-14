<?php
// ==========================================================
// KITCHERRY VOICE TASKS
// Archivo: includes/generador_txt.php
// ==========================================================

function limpiarTextoTxt($valor) {
    return trim($valor ?? "");
}

function textoMayusculasTxt($valor) {
    $valor = limpiarTextoTxt($valor);

    if ($valor === "") {
        return "";
    }

    return mb_strtoupper($valor, "UTF-8");
}

function obtenerListaConItems(PDO $pdo, int $listaId) {
    $stmtLista = $pdo->prepare("
        SELECT *
        FROM listas
        WHERE id = :id
    ");

    $stmtLista->execute([
        ":id" => $listaId
    ]);

    $lista = $stmtLista->fetch();

    if (!$lista) {
        return [
            "ok" => false,
            "lista" => null,
            "items" => []
        ];
    }

    $stmtItems = $pdo->prepare("
        SELECT *
        FROM items_lista
        WHERE lista_id = :lista_id
        ORDER BY orden, id
    ");

    $stmtItems->execute([
        ":lista_id" => $listaId
    ]);

    $items = $stmtItems->fetchAll();

    return [
        "ok" => true,
        "lista" => $lista,
        "items" => $items
    ];
}

function obtenerTipoListaDesdeItems($items) {
    if (!empty($items) && isset($items[0]["tipo"])) {
        return limpiarTextoTxt($items[0]["tipo"]);
    }

    return "";
}

function obtenerPrioridadListaDesdeItems($items) {
    if (!empty($items) && isset($items[0]["prioridad"])) {
        return limpiarTextoTxt($items[0]["prioridad"]);
    }

    return "";
}

function generarContenidoTxtLista($lista, $items) {
    $titulo = textoMayusculasTxt($lista["titulo"] ?? "LISTA");
    $tipoLista = obtenerTipoListaDesdeItems($items);
    $prioridadLista = obtenerPrioridadListaDesdeItems($items);

    $lineas = [];

    // El título va directo, sin escribir "Título:"
    $lineas[] = $titulo;
    $lineas[] = "";
    $lineas[] = "Destino: " . ucfirst(limpiarTextoTxt($lista["destino"] ?? ""));
    $lineas[] = "Turno: " . limpiarTextoTxt($lista["turno"] ?? "");

    if ($tipoLista !== "") {
        $lineas[] = "Tipo: " . $tipoLista;
    }

    if ($prioridadLista !== "") {
        $lineas[] = "Prioridad: " . ucfirst($prioridadLista);
    }

    $lineas[] = "Fecha: " . limpiarTextoTxt($lista["fecha_creacion"] ?? "");
    $lineas[] = "";
    $lineas[] = "----------------------------------------";
    $lineas[] = "LISTA";
    $lineas[] = "----------------------------------------";
    $lineas[] = "";

    if (empty($items)) {
        $lineas[] = "No hay elementos en esta lista.";
    } else {
        foreach ($items as $item) {
            $descripcion = limpiarTextoTxt($item["descripcion"] ?? "");
            $cantidad = limpiarTextoTxt($item["cantidad"] ?? "");

            if ($descripcion === "") {
                continue;
            }

            if ($cantidad !== "") {
                $lineas[] = "[ ] " . $descripcion . " — " . $cantidad;
            } else {
                $lineas[] = "[ ] " . $descripcion;
            }
        }
    }

    $lineas[] = "";
    $lineas[] = "----------------------------------------";
    $lineas[] = "Generado desde KITCHERRY Voice Tasks";
    $lineas[] = "----------------------------------------";

    return implode("\n", $lineas);
}

function guardarTxtLista(PDO $pdo, int $listaId) {
    $datos = obtenerListaConItems($pdo, $listaId);

    if (!$datos["ok"]) {
        return [
            "ok" => false,
            "mensaje" => "Lista no encontrada.",
            "lista" => null,
            "items" => [],
            "contenido_txt" => ""
        ];
    }

    $contenidoTxt = generarContenidoTxtLista($datos["lista"], $datos["items"]);

    $stmt = $pdo->prepare("
        UPDATE listas
        SET contenido_txt = :contenido_txt,
            estado = 'txt_generado'
        WHERE id = :id
    ");

    $stmt->execute([
        ":contenido_txt" => $contenidoTxt,
        ":id" => $listaId
    ]);

    $datos["lista"]["contenido_txt"] = $contenidoTxt;
    $datos["lista"]["estado"] = "txt_generado";

    return [
        "ok" => true,
        "mensaje" => "TXT preparado correctamente.",
        "lista" => $datos["lista"],
        "items" => $datos["items"],
        "contenido_txt" => $contenidoTxt
    ];
}

function generarNombreArchivoTxt($lista) {
    $titulo = limpiarTextoTxt($lista["titulo"] ?? "lista");
    $titulo = mb_strtolower($titulo, "UTF-8");

    $titulo = preg_replace('/[^a-z0-9áéíóúñü]+/iu', '-', $titulo);
    $titulo = trim($titulo, "-");

    if ($titulo === "") {
        $titulo = "lista";
    }

    return "kitcherry-" . $titulo . ".txt";
}