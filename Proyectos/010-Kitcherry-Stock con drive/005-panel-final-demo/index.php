<?php
// ==========================================================
// KITCHERRY STOCK
// Archivo: index.php
// Panel final organizado por secciones
// ==========================================================

require_once "config.php";
require_once "funciones.php";

$error = null;

$productos = [];
$solicitudes = [];
$proveedores = [];
$categorias = [];
$proveedoresFiltro = [];
$productosStockBajo = [];

$resumenStock = [
    "total" => 0,
    "correcto" => 0,
    "stock_bajo" => 0,
    "sobrestock" => 0,
    "inactivos" => 0,
];

$resumenSolicitudes = [
    "total" => 0,
    "pendientes" => 0,
    "urgentes" => 0,
    "coste_estimado" => 0,
];

$graficaSolicitudesEstado = [
    "Pendiente" => 0,
    "Enviada" => 0,
    "Gestionada" => 0,
    "Cancelada" => 0,
];

$graficaSolicitudesPrioridad = [
    "Normal" => 0,
    "Alta" => 0,
    "Urgente" => 0,
];

$zonasSolicitudes = [];

try {
    $proveedores = obtenerProveedoresDesdeDrive($CSV_PROVEEDORES);
    $mapaProveedores = crearMapaProveedores($proveedores);

    $productos = obtenerProductosDesdeDrive($CSV_PRODUCTOS);
    $productos = enriquecerProductosConProveedores($productos, $mapaProveedores);

    $solicitudes = obtenerSolicitudesDesdeDrive($CSV_SOLICITUDES);

    $resumenStock = calcularResumenStock($productos);
    $resumenSolicitudes = calcularResumenSolicitudes($solicitudes);

    $categorias = obtenerCategoriasUnicas($productos);
    $proveedoresFiltro = obtenerProveedoresUnicosProductos($productos);
    $productosStockBajo = obtenerProductosStockBajo($productos);

    foreach ($solicitudes as $solicitud) {
        $estadoSolicitud = trim($solicitud["estado"] ?? "");
        $prioridadSolicitud = trim($solicitud["prioridad"] ?? "");
        $zonaSolicitud = trim($solicitud["zona"] ?? "");

        if (isset($graficaSolicitudesEstado[$estadoSolicitud])) {
            $graficaSolicitudesEstado[$estadoSolicitud]++;
        }

        if (isset($graficaSolicitudesPrioridad[$prioridadSolicitud])) {
            $graficaSolicitudesPrioridad[$prioridadSolicitud]++;
        }

        if ($zonaSolicitud !== "" && !in_array($zonaSolicitud, $zonasSolicitudes)) {
            $zonasSolicitudes[] = $zonaSolicitud;
        }
    }

    sort($zonasSolicitudes);

} catch (Exception $e) {
    $error = $e->getMessage();
}

$datosGraficas = [
    "stockEstados" => [
        ["label" => "Correcto", "value" => (int)$resumenStock["correcto"]],
        ["label" => "Stock bajo", "value" => (int)$resumenStock["stock_bajo"]],
        ["label" => "Sobrestock", "value" => (int)$resumenStock["sobrestock"]],
        ["label" => "Inactivos", "value" => (int)$resumenStock["inactivos"]],
    ],
    "solicitudesPrioridad" => [
        ["label" => "Normal", "value" => (int)$graficaSolicitudesPrioridad["Normal"]],
        ["label" => "Alta", "value" => (int)$graficaSolicitudesPrioridad["Alta"]],
        ["label" => "Urgente", "value" => (int)$graficaSolicitudesPrioridad["Urgente"]],
    ],
    "solicitudesEstado" => [
        ["label" => "Pendiente", "value" => (int)$graficaSolicitudesEstado["Pendiente"]],
        ["label" => "Enviada", "value" => (int)$graficaSolicitudesEstado["Enviada"]],
        ["label" => "Gestionada", "value" => (int)$graficaSolicitudesEstado["Gestionada"]],
        ["label" => "Cancelada", "value" => (int)$graficaSolicitudesEstado["Cancelada"]],
    ],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kitcherry Stock</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="layout">

    <aside class="sidebar">

        <div class="marca-panel">
            <img src="assets/img/logo.png" alt="Logotipo Kitcherry" class="logo">

            <div class="marca-texto">
                <div class="nombre-kitcherry">
                    <span class="kit">KIT</span><span class="cherry">CHERRY</span>
                </div>

                <div class="nombre-stock">
                    Stock
                </div>
            </div>
        </div>

        <nav class="menu-lateral">
            <a href="#" class="activo" data-seccion="dashboard">
                <span class="menu-icono">▦</span>
                Dashboard
            </a>

            <a href="#" data-seccion="productos">
                <span class="menu-icono">▤</span>
                Productos
            </a>

            <a href="#" data-seccion="solicitudes">
                <span class="menu-icono">+</span>
                Solicitudes
            </a>

            <a href="#" data-seccion="proveedores">
                <span class="menu-icono">↗</span>
                Proveedores
            </a>

            <a href="#" data-seccion="exportacion">
                <span class="menu-icono">⇩</span>
                Exportación
            </a>
        </nav>

        <div class="sidebar-info">
            <span class="estado-conexion"></span>
            Panel interno activo
        </div>

    </aside>

    <main class="panel">

        <header class="topbar">
            <div>
                <h1>Panel de stock</h1>
                <p><?php echo e($DESCRIPCION_PROYECTO); ?></p>
            </div>

            <div class="topbar-dato">
                <span>Restaurante demo</span>
                <strong>Hamburguesería</strong>
            </div>
        </header>

        <?php if ($error): ?>

            <section class="bloque error">
                <h2>Error al cargar los datos</h2>

                <p><?php echo e($error); ?></p>

                <p class="ayuda-error">
                    Revisa que las hojas de Google Sheets estén publicadas como CSV.
                </p>
            </section>

        <?php else: ?>

            <script id="datosGraficasDashboard" type="application/json">
                <?php echo json_encode($datosGraficas, JSON_UNESCAPED_UNICODE); ?>
            </script>

            <!-- ======================================================
            DASHBOARD
            ======================================================= -->

            <section class="seccion-panel activa" id="seccion-dashboard">

                <section class="resumen-grid">

                    <article class="tarjeta-resumen">
                        <span class="label">Productos totales</span>
                        <strong><?php echo e($resumenStock["total"]); ?></strong>
                        <small>Productos registrados</small>
                    </article>

                    <article class="tarjeta-resumen alerta">
                        <span class="label">Stock bajo</span>
                        <strong><?php echo e($resumenStock["stock_bajo"]); ?></strong>
                        <small>Requieren revisión</small>
                    </article>

                    <article class="tarjeta-resumen">
                        <span class="label">Solicitudes</span>
                        <strong><?php echo e($resumenSolicitudes["total"]); ?></strong>
                        <small>Registradas en Drive</small>
                    </article>

                    <article class="tarjeta-resumen alerta">
                        <span class="label">Pendientes</span>
                        <strong><?php echo e($resumenSolicitudes["pendientes"]); ?></strong>
                        <small>Solicitudes por revisar</small>
                    </article>

                </section>

                <section class="dashboard-grid">

                    <article class="bloque">
                        <div class="bloque-cabecera simple">
                            <h2>Últimas solicitudes</h2>
                            <p>Resumen de las solicitudes más recientes.</p>
                        </div>

                        <div class="lista-solicitudes-guardadas">
                            <?php if (count($solicitudes) > 0): ?>

                                <?php foreach (array_slice($solicitudes, 0, 5) as $solicitudGuardada): ?>
                                    <?php
                                        $prioridad = $solicitudGuardada["prioridad"] ?? "";
                                        $clasePrioridad = clasePrioridad($prioridad);
                                    ?>

                                    <article class="item-solicitud-guardada">
                                        <div class="solicitud-guardada-cabecera">
                                            <strong><?php echo e($solicitudGuardada["id_solicitud"] ?? ""); ?></strong>
                                            <span class="<?php echo e($clasePrioridad); ?>">
                                                <?php echo e($prioridad); ?>
                                            </span>
                                        </div>

                                        <p>
                                            <?php echo e($solicitudGuardada["empleado"] ?? ""); ?>
                                            ·
                                            <?php echo e($solicitudGuardada["zona"] ?? ""); ?>
                                            ·
                                            <?php echo e($solicitudGuardada["estado"] ?? ""); ?>
                                        </p>

                                        <small>
                                            <?php echo e($solicitudGuardada["fecha"] ?? ""); ?>
                                            ·
                                            <?php echo e($solicitudGuardada["total_unidades"] ?? ""); ?> uds
                                            ·
                                            <?php echo formatoEuros($solicitudGuardada["coste_estimado"] ?? ""); ?>
                                        </small>
                                    </article>
                                <?php endforeach; ?>

                            <?php else: ?>

                                <p class="texto-vacio">Todavía no hay solicitudes registradas.</p>

                            <?php endif; ?>
                        </div>
                    </article>

                    <article class="bloque">
                        <div class="bloque-cabecera simple">
                            <h2>Productos con stock bajo</h2>
                            <p>Productos que necesitan revisión.</p>
                        </div>

                        <?php if (count($productosStockBajo) > 0): ?>

                            <div class="lista-alertas">
                                <?php foreach (array_slice($productosStockBajo, 0, 8) as $productoBajo): ?>
                                    <div class="item-alerta">
                                        <div>
                                            <strong><?php echo e($productoBajo["nombre"] ?? ""); ?></strong>
                                            <span>
                                                <?php echo e($productoBajo["categoria"] ?? ""); ?>
                                                ·
                                                <?php echo e($productoBajo["nombre_proveedor"] ?? ""); ?>
                                            </span>
                                        </div>

                                        <em>
                                            <?php echo e($productoBajo["stock_actual"] ?? ""); ?>
                                            <?php echo e($productoBajo["unidad_medida"] ?? ""); ?>
                                        </em>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        <?php else: ?>

                            <p class="texto-vacio">No hay productos con stock bajo.</p>

                        <?php endif; ?>
                    </article>

                </section>

                <section class="graficas-dashboard">

                    <article class="bloque grafica-card">
                        <div class="bloque-cabecera simple">
                            <h2>Stock por estado</h2>
                            <p>Resumen visual del estado actual del inventario.</p>
                        </div>

                        <div class="grafica-contenedor">
                            <canvas id="graficaStockEstados" width="520" height="280"></canvas>
                        </div>
                    </article>

                    <article class="bloque grafica-card">
                        <div class="bloque-cabecera simple">
                            <h2>Solicitudes por prioridad</h2>
                            <p>Distribución de solicitudes según urgencia operativa.</p>
                        </div>

                        <div class="grafica-contenedor">
                            <canvas id="graficaSolicitudesPrioridad" width="520" height="280"></canvas>
                        </div>
                    </article>

                    <article class="bloque grafica-card">
                        <div class="bloque-cabecera simple">
                            <h2>Solicitudes por estado</h2>
                            <p>Seguimiento general de solicitudes internas.</p>
                        </div>

                        <div class="grafica-contenedor">
                            <canvas id="graficaSolicitudesEstado" width="520" height="280"></canvas>
                        </div>
                    </article>

                </section>

            </section>

            <!-- ======================================================
            PRODUCTOS
            ======================================================= -->

            <section class="seccion-panel" id="seccion-productos">

                <article class="bloque bloque-principal">

                    <div class="bloque-cabecera">
                        <div>
                            <h2>Productos</h2>
                            <p>Consulta del stock disponible y selección de productos para solicitud.</p>
                        </div>

                        <span class="contador" id="contadorProductos">
                            <?php echo count($productos); ?> registros
                        </span>
                    </div>

                    <div class="filtros filtros-productos">

                        <div class="campo-filtro campo-busqueda">
                            <label for="buscador">Buscar producto</label>
                            <input type="text" id="buscador" placeholder="Ej. queso, pan, salsa...">
                        </div>

                        <div class="campo-filtro">
                            <label for="filtroCategoria">Categoría</label>
                            <select id="filtroCategoria">
                                <option value="">Todas</option>

                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo e($categoria); ?>">
                                        <?php echo e($categoria); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="campo-filtro">
                            <label for="filtroProveedor">Proveedor</label>
                            <select id="filtroProveedor">
                                <option value="">Todos</option>

                                <?php foreach ($proveedoresFiltro as $proveedorFiltro): ?>
                                    <option value="<?php echo e($proveedorFiltro); ?>">
                                        <?php echo e($proveedorFiltro); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="campo-filtro">
                            <label for="filtroEstado">Estado</label>
                            <select id="filtroEstado">
                                <option value="">Todos</option>
                                <option value="Correcto">Correcto</option>
                                <option value="Stock bajo">Stock bajo</option>
                                <option value="Sobrestock">Sobrestock</option>
                            </select>
                        </div>

                        <button type="button" id="btnLimpiar" class="btn-limpiar">
                            Limpiar
                        </button>

                    </div>

                    <div class="resumen-rapido-solicitud" id="resumenRapidoSolicitud">
                        <div class="resumen-rapido-cabecera">
                            <div>
                                <h3>Resumen rápido de solicitud</h3>
                                <p>Productos añadidos antes de generar la solicitud final.</p>
                            </div>

                            <div class="resumen-rapido-acciones">
                                <button type="button" id="btnVerSolicitudMini" class="btn-principal">
                                    Ver solicitud
                                </button>

                                <button type="button" id="btnVaciarSolicitudMini" class="btn-secundario">
                                    Vaciar
                                </button>
                            </div>
                        </div>

                        <div class="resumen-rapido-datos">
                            <div>
                                <span>Productos</span>
                                <strong id="miniTotalProductosSolicitud">0</strong>
                            </div>

                            <div>
                                <span>Unidades</span>
                                <strong id="miniTotalUnidadesSolicitud">0</strong>
                            </div>

                            <div>
                                <span>Coste estimado</span>
                                <strong id="miniTotalCosteSolicitud">0,00 €</strong>
                            </div>
                        </div>

                        <div class="mini-lista-solicitud" id="miniListaSolicitud">
                            No hay productos añadidos todavía.
                        </div>
                    </div>

                    <div class="tabla-contenedor">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Categoría</th>
                                    <th>Producto</th>
                                    <th>Unidad</th>
                                    <th>Actual</th>
                                    <th>Mínimo</th>
                                    <th>Máximo</th>
                                    <th>Coste</th>
                                    <th>Proveedor</th>
                                    <th>Zona</th>
                                    <th>Estado</th>
                                    <th>Solicitud</th>
                                </tr>
                            </thead>

                            <tbody id="tablaProductos">
                                <?php foreach ($productos as $producto): ?>
                                    <?php
                                        $id = $producto["id_producto"] ?? "";
                                        $categoria = $producto["categoria"] ?? "";
                                        $subcategoria = $producto["subcategoria"] ?? "";
                                        $nombre = $producto["nombre"] ?? "";
                                        $unidad = $producto["unidad_medida"] ?? "";
                                        $stockActual = $producto["stock_actual"] ?? "";
                                        $stockMinimo = $producto["stock_minimo"] ?? "";
                                        $stockMaximo = $producto["stock_maximo"] ?? "";
                                        $coste = $producto["coste_unitario"] ?? "";
                                        $zona = $producto["zona_almacen"] ?? "";
                                        $estado = $producto["estado_stock"] ?? "Desconocido";
                                        $proveedorNombre = $producto["nombre_proveedor"] ?? "";
                                        $claseEstado = claseEstadoStock($estado);
                                    ?>

                                    <tr
                                        data-id="<?php echo e($id); ?>"
                                        data-nombre="<?php echo e($nombre); ?>"
                                        data-categoria="<?php echo e($categoria); ?>"
                                        data-proveedor="<?php echo e($proveedorNombre); ?>"
                                        data-estado="<?php echo e($estado); ?>"
                                        data-unidad="<?php echo e($unidad); ?>"
                                        data-coste="<?php echo e($coste); ?>"
                                    >
                                        <td><?php echo e($id); ?></td>

                                        <td>
                                            <span class="categoria">
                                                <?php echo e($categoria); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <strong><?php echo e($nombre); ?></strong>
                                            <small><?php echo e($subcategoria); ?></small>
                                        </td>

                                        <td><?php echo e($unidad); ?></td>
                                        <td><?php echo e($stockActual); ?></td>
                                        <td><?php echo e($stockMinimo); ?></td>
                                        <td><?php echo e($stockMaximo); ?></td>
                                        <td><?php echo formatoEuros($coste); ?></td>

                                        <td>
                                            <strong><?php echo e($proveedorNombre); ?></strong>
                                            <small><?php echo e($producto["id_proveedor"] ?? ""); ?></small>
                                        </td>

                                        <td><?php echo e($zona); ?></td>

                                        <td>
                                            <span class="estado <?php echo e($claseEstado); ?>">
                                                <?php echo e($estado); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="solicitud-rapida">
                                                <input
                                                    type="number"
                                                    class="input-cantidad"
                                                    min="1"
                                                    value="1"
                                                    aria-label="Cantidad a solicitar"
                                                >

                                                <button
                                                    type="button"
                                                    class="btn-solicitar"
                                                    data-id="<?php echo e($id); ?>"
                                                    data-nombre="<?php echo e($nombre); ?>"
                                                    data-categoria="<?php echo e($categoria); ?>"
                                                    data-unidad="<?php echo e($unidad); ?>"
                                                    data-coste="<?php echo e($coste); ?>"
                                                >
                                                    Añadir
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="sin-resultados" id="sinResultados">
                            No se han encontrado productos con los filtros seleccionados.
                        </div>
                    </div>

                </article>

            </section>

            <!-- ======================================================
            SOLICITUDES
            ======================================================= -->

            <section class="seccion-panel" id="seccion-solicitudes">

                <section class="solicitudes-grid">

                    <article class="bloque solicitud-panel" id="solicitud">
                        <div class="bloque-cabecera simple">
                            <h2>Nueva solicitud</h2>
                            <p>Revisa los productos seleccionados y genera la solicitud interna.</p>
                        </div>

                        <div class="form-solicitud">

                            <div class="campo-form">
                                <label for="empleadoSolicitud">Empleado</label>
                                <input type="text" id="empleadoSolicitud" placeholder="Nombre del empleado">
                            </div>

                            <div class="campo-form">
                                <label for="zonaSolicitud">Zona solicitante</label>
                                <select id="zonaSolicitud">
                                    <option value="">Seleccionar zona</option>
                                    <option value="Cocina">Cocina</option>
                                    <option value="Sala">Sala</option>
                                    <option value="Barra">Barra</option>
                                    <option value="Almacén">Almacén</option>
                                    <option value="Delivery">Delivery</option>
                                </select>
                            </div>

                            <div class="campo-form">
                                <label for="prioridadSolicitud">Prioridad</label>
                                <select id="prioridadSolicitud">
                                    <option value="Normal">Normal</option>
                                    <option value="Alta">Alta</option>
                                    <option value="Urgente">Urgente</option>
                                </select>
                            </div>

                            <div class="resumen-datos">
                                <div>
                                    <span>Productos</span>
                                    <strong id="totalProductosSolicitud">0</strong>
                                </div>

                                <div>
                                    <span>Unidades</span>
                                    <strong id="totalUnidadesSolicitud">0</strong>
                                </div>

                                <div>
                                    <span>Coste estimado</span>
                                    <strong id="totalCosteSolicitud">0,00 €</strong>
                                </div>

                                <div>
                                    <span>Estado</span>
                                    <strong>Pendiente</strong>
                                </div>
                            </div>

                            <div class="lista-solicitud" id="listaSolicitud">
                                <p class="texto-vacio">
                                    Añade productos desde la sección Productos.
                                </p>
                            </div>

                            <div class="campo-form">
                                <label for="observacionesSolicitud">Observaciones</label>
                                <textarea id="observacionesSolicitud" rows="4" placeholder="Ej. Reponer antes del servicio de noche..."></textarea>
                            </div>

                            <div class="acciones-solicitud">
                                <button type="button" id="btnGenerarSolicitud" class="btn-principal">
                                    Generar solicitud
                                </button>

                                <button type="button" id="btnVaciarSolicitud" class="btn-secundario">
                                    Vaciar
                                </button>
                            </div>

                            <div class="resultado-solicitud" id="resultadoSolicitud"></div>

                        </div>
                    </article>

                    <article class="bloque">
                        <div class="bloque-cabecera">
                            <div>
                                <h2>Solicitudes registradas</h2>
                                <p>Historial guardado en Google Sheets.</p>
                            </div>

                            <div class="acciones-cabecera">
                                <span class="contador" id="contadorSolicitudes">
                                    <?php echo count($solicitudes); ?> solicitudes
                                </span>

                                <button type="button" id="btnActualizarSolicitudes" class="btn-secundario btn-actualizar">
                                    Actualizar solicitudes
                                </button>
                            </div>
                        </div>

                        <div class="filtros filtros-solicitudes">

                            <div class="campo-filtro campo-busqueda">
                                <label for="buscadorSolicitudes">Buscar solicitud</label>
                                <input type="text" id="buscadorSolicitudes" placeholder="ID, empleado o zona...">
                            </div>

                            <div class="campo-filtro">
                                <label for="filtroEstadoSolicitud">Estado</label>
                                <select id="filtroEstadoSolicitud">
                                    <option value="">Todos</option>
                                    <option value="Pendiente">Pendiente</option>
                                    <option value="Enviada">Enviada</option>
                                    <option value="Gestionada">Gestionada</option>
                                    <option value="Cancelada">Cancelada</option>
                                </select>
                            </div>

                            <div class="campo-filtro">
                                <label for="filtroPrioridadSolicitud">Prioridad</label>
                                <select id="filtroPrioridadSolicitud">
                                    <option value="">Todas</option>
                                    <option value="Normal">Normal</option>
                                    <option value="Alta">Alta</option>
                                    <option value="Urgente">Urgente</option>
                                </select>
                            </div>

                            <div class="campo-filtro">
                                <label for="filtroZonaSolicitud">Zona</label>
                                <select id="filtroZonaSolicitud">
                                    <option value="">Todas</option>

                                    <?php foreach ($zonasSolicitudes as $zonaSolicitudFiltro): ?>
                                        <option value="<?php echo e($zonaSolicitudFiltro); ?>">
                                            <?php echo e($zonaSolicitudFiltro); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="button" id="btnLimpiarFiltrosSolicitudes" class="btn-limpiar">
                                Limpiar
                            </button>

                        </div>

                        <div class="tabla-contenedor">
                            <table class="tabla-solicitudes">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Fecha</th>
                                        <th>Empleado</th>
                                        <th>Zona</th>
                                        <th>Prioridad</th>
                                        <th>Estado</th>
                                        <th>Productos</th>
                                        <th>Unidades</th>
                                        <th>Coste</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>

                                <tbody id="tablaSolicitudes">
                                    <?php foreach ($solicitudes as $solicitudGuardada): ?>
                                        <?php
                                            $prioridad = $solicitudGuardada["prioridad"] ?? "";
                                            $clasePrioridad = clasePrioridad($prioridad);
                                            $idSolicitud = $solicitudGuardada["id_solicitud"] ?? "";
                                            $estadoSolicitud = $solicitudGuardada["estado"] ?? "Pendiente";
                                            $empleadoSolicitud = $solicitudGuardada["empleado"] ?? "";
                                            $zonaSolicitud = $solicitudGuardada["zona"] ?? "";
                                        ?>

                                        <tr
                                            data-id-solicitud="<?php echo e($idSolicitud); ?>"
                                            data-empleado="<?php echo e($empleadoSolicitud); ?>"
                                            data-zona="<?php echo e($zonaSolicitud); ?>"
                                            data-prioridad="<?php echo e($prioridad); ?>"
                                            data-estado="<?php echo e($estadoSolicitud); ?>"
                                        >
                                            <td><?php echo e($idSolicitud); ?></td>
                                            <td><?php echo e($solicitudGuardada["fecha"] ?? ""); ?></td>
                                            <td><?php echo e($empleadoSolicitud); ?></td>
                                            <td><?php echo e($zonaSolicitud); ?></td>

                                            <td>
                                                <span class="<?php echo e($clasePrioridad); ?>">
                                                    <?php echo e($prioridad); ?>
                                                </span>
                                            </td>

                                            <td>
                                                <select class="select-estado-solicitud">
                                                    <option value="Pendiente" <?php echo $estadoSolicitud === "Pendiente" ? "selected" : ""; ?>>
                                                        Pendiente
                                                    </option>

                                                    <option value="Enviada" <?php echo $estadoSolicitud === "Enviada" ? "selected" : ""; ?>>
                                                        Enviada
                                                    </option>

                                                    <option value="Gestionada" <?php echo $estadoSolicitud === "Gestionada" ? "selected" : ""; ?>>
                                                        Gestionada
                                                    </option>

                                                    <option value="Cancelada" <?php echo $estadoSolicitud === "Cancelada" ? "selected" : ""; ?>>
                                                        Cancelada
                                                    </option>
                                                </select>
                                            </td>

                                            <td><?php echo e($solicitudGuardada["total_productos"] ?? ""); ?></td>
                                            <td><?php echo e($solicitudGuardada["total_unidades"] ?? ""); ?></td>
                                            <td><?php echo formatoEuros($solicitudGuardada["coste_estimado"] ?? ""); ?></td>

                                            <td>
                                                <div class="acciones-tabla">
                                                    <button
                                                        type="button"
                                                        class="btn-tabla btn-actualizar-estado-solicitud"
                                                        data-id-solicitud="<?php echo e($idSolicitud); ?>"
                                                    >
                                                        Actualizar estado
                                                    </button>

                                                    <a
                                                        href="exportar_solicitud.php?id=<?php echo urlencode($idSolicitud); ?>"
                                                        target="_blank"
                                                        class="btn-tabla btn-link-tabla"
                                                    >
                                                        Ver / imprimir
                                                    </a>

                                                    <button
                                                        type="button"
                                                        class="btn-tabla btn-enviar-solicitud"
                                                        data-id-solicitud="<?php echo e($idSolicitud); ?>"
                                                    >
                                                        Enviar email
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <div class="sin-resultados" id="sinResultadosSolicitudes">
                                No se han encontrado solicitudes con los filtros seleccionados.
                            </div>
                        </div>
                    </article>

                </section>

            </section>

            <!-- ======================================================
            PROVEEDORES
            ======================================================= -->

            <section class="seccion-panel" id="seccion-proveedores">

                <section class="proveedores-grid">

                    <article class="bloque proveedor-form-panel">

                        <div class="bloque-cabecera simple">
                            <h2 id="tituloFormularioProveedor">Nuevo proveedor</h2>
                            <p>Crea o modifica proveedores guardados en Google Sheets.</p>
                        </div>

                        <form class="form-proveedor" id="formProveedor">

                            <input type="hidden" id="modoProveedor" value="crear">

                            <div class="campo-form">
                                <label for="idProveedor">ID proveedor</label>
                                <input type="text" id="idProveedor" placeholder="Automático al crear" readonly>
                            </div>

                            <div class="campo-form">
                                <label for="nombreProveedor">Nombre proveedor</label>
                                <input type="text" id="nombreProveedor" placeholder="Ej. Distribuciones Levante" required>
                            </div>

                            <div class="campo-form">
                                <label for="tipoProveedor">Tipo</label>
                                <input type="text" id="tipoProveedor" placeholder="Ej. carnes, panes, bebidas...">
                            </div>

                            <div class="campo-form">
                                <label for="emailProveedor">Email</label>
                                <input type="email" id="emailProveedor" placeholder="contacto@proveedor.com">
                            </div>

                            <div class="campo-form">
                                <label for="telefonoProveedor">Teléfono</label>
                                <input type="text" id="telefonoProveedor" placeholder="Ej. 963000000">
                            </div>

                            <div class="campo-form">
                                <label for="ubicacionProveedor">Ubicación</label>
                                <input type="text" id="ubicacionProveedor" placeholder="Ej. Valencia">
                            </div>

                            <div class="campo-form">
                                <label for="entregaProveedor">Tiempo de entrega</label>
                                <input type="text" id="entregaProveedor" placeholder="Ej. 24-48h">
                            </div>

                            <div class="campo-form">
                                <label for="conservacionProveedor">Tipo de conservación</label>
                                <input type="text" id="conservacionProveedor" placeholder="Ej. Refrigerado, ambiente...">
                            </div>

                            <div class="campo-form">
                                <label for="activoProveedor">Activo</label>
                                <select id="activoProveedor">
                                    <option value="Sí">Sí</option>
                                    <option value="No">No</option>
                                </select>
                            </div>

                            <div class="campo-form">
                                <label for="observacionesProveedor">Observaciones</label>
                                <textarea id="observacionesProveedor" rows="4" placeholder="Notas internas sobre el proveedor..."></textarea>
                            </div>

                            <div class="acciones-proveedor">
                                <button type="submit" id="btnGuardarProveedor" class="btn-principal">
                                    Guardar proveedor
                                </button>

                                <button type="button" id="btnCancelarEdicionProveedor" class="btn-secundario">
                                    Cancelar
                                </button>
                            </div>

                            <div class="resultado-proveedor" id="resultadoProveedor"></div>

                        </form>

                    </article>

                    <article class="bloque">

                        <div class="bloque-cabecera">
                            <div>
                                <h2>Proveedores</h2>
                                <p>Listado de proveedores conectado desde Google Sheets.</p>
                            </div>

                            <div class="acciones-cabecera">
                                <span class="contador">
                                    <?php echo count($proveedores); ?> proveedores
                                </span>

                                <button type="button" id="btnActualizarProveedores" class="btn-secundario btn-actualizar">
                                    Actualizar proveedores
                                </button>
                            </div>
                        </div>

                        <div class="tabla-contenedor">
                            <table class="tabla-proveedores">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Proveedor</th>
                                        <th>Tipo</th>
                                        <th>Email</th>
                                        <th>Teléfono</th>
                                        <th>Ubicación</th>
                                        <th>Entrega</th>
                                        <th>Conservación</th>
                                        <th>Activo</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($proveedores as $proveedor): ?>
                                        <?php
                                            $idProveedor = $proveedor["id_proveedor"] ?? "";
                                            $nombreProveedor = $proveedor["nombre_proveedor"] ?? "";
                                            $tipoProveedor = $proveedor["tipo"] ?? "";
                                            $emailProveedor = $proveedor["email"] ?? "";
                                            $telefonoProveedor = $proveedor["telefono"] ?? "";
                                            $ubicacionProveedor = $proveedor["ubicacion"] ?? "";
                                            $entregaProveedor = $proveedor["tiempo_entrega_estimado"] ?? "";
                                            $conservacionProveedor = $proveedor["tipo_conservacion"] ?? "";
                                            $activoProveedor = $proveedor["activo"] ?? "";
                                            $observacionesProveedor = $proveedor["observaciones"] ?? "";
                                        ?>

                                        <tr
                                            data-id-proveedor="<?php echo e($idProveedor); ?>"
                                            data-nombre-proveedor="<?php echo e($nombreProveedor); ?>"
                                            data-tipo="<?php echo e($tipoProveedor); ?>"
                                            data-email="<?php echo e($emailProveedor); ?>"
                                            data-telefono="<?php echo e($telefonoProveedor); ?>"
                                            data-ubicacion="<?php echo e($ubicacionProveedor); ?>"
                                            data-entrega="<?php echo e($entregaProveedor); ?>"
                                            data-conservacion="<?php echo e($conservacionProveedor); ?>"
                                            data-activo="<?php echo e($activoProveedor); ?>"
                                            data-observaciones="<?php echo e($observacionesProveedor); ?>"
                                        >
                                            <td><?php echo e($idProveedor); ?></td>

                                            <td>
                                                <strong><?php echo e($nombreProveedor); ?></strong>
                                            </td>

                                            <td><?php echo e($tipoProveedor); ?></td>
                                            <td><?php echo e($emailProveedor); ?></td>
                                            <td><?php echo e($telefonoProveedor); ?></td>
                                            <td><?php echo e($ubicacionProveedor); ?></td>
                                            <td><?php echo e($entregaProveedor); ?></td>
                                            <td><?php echo e($conservacionProveedor); ?></td>

                                            <td>
                                                <span class="<?php echo $activoProveedor === 'No' ? 'estado-inactivo' : 'estado-activo'; ?>">
                                                    <?php echo e($activoProveedor); ?>
                                                </span>
                                            </td>

                                            <td>
                                                <div class="acciones-tabla">
                                                    <button type="button" class="btn-tabla btn-editar-proveedor">
                                                        Editar
                                                    </button>

                                                    <button type="button" class="btn-tabla btn-desactivar-proveedor">
                                                        Desactivar
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    </article>

                </section>

            </section>

            <!-- ======================================================
            EXPORTACIÓN
            ======================================================= -->

            <section class="seccion-panel" id="seccion-exportacion">

                <section class="exportacion-grid">

                    <article class="bloque tarjeta-exportacion">
                        <div class="bloque-cabecera simple">
                            <h2>Productos</h2>
                            <p>Exporta o imprime el listado general de productos del stock.</p>
                        </div>

                        <div class="contenido-exportacion">
                            <a href="exportar_productos.php" target="_blank" class="btn-principal enlace-exportacion">
                                Ver / imprimir productos
                            </a>

                            <a href="<?php echo e($CSV_PRODUCTOS); ?>" target="_blank" class="btn-secundario enlace-exportacion">
                                Descargar CSV
                            </a>
                        </div>
                    </article>

                    <article class="bloque tarjeta-exportacion">
                        <div class="bloque-cabecera simple">
                            <h2>Stock bajo</h2>
                            <p>Exporta solo los productos que requieren revisión o reposición.</p>
                        </div>

                        <div class="contenido-exportacion">
                            <a href="exportar_stock_bajo.php" target="_blank" class="btn-principal enlace-exportacion">
                                Ver / imprimir stock bajo
                            </a>
                        </div>
                    </article>

                    <article class="bloque tarjeta-exportacion">
                        <div class="bloque-cabecera simple">
                            <h2>Solicitudes</h2>
                            <p>Exporta el historial de solicitudes o imprime una solicitud concreta desde su tabla.</p>
                        </div>

                        <div class="contenido-exportacion">
                            <a href="exportar_solicitudes.php" target="_blank" class="btn-principal enlace-exportacion">
                                Ver / imprimir historial
                            </a>

                            <a href="<?php echo e($CSV_SOLICITUDES); ?>" target="_blank" class="btn-secundario enlace-exportacion">
                                Descargar CSV
                            </a>
                        </div>
                    </article>

                    <article class="bloque tarjeta-exportacion">
                        <div class="bloque-cabecera simple">
                            <h2>Proveedores</h2>
                            <p>Exporta o imprime la lista de proveedores registrados.</p>
                        </div>

                        <div class="contenido-exportacion">
                            <a href="exportar_proveedores.php" target="_blank" class="btn-principal enlace-exportacion">
                                Ver / imprimir proveedores
                            </a>

                            <a href="<?php echo e($CSV_PROVEEDORES); ?>" target="_blank" class="btn-secundario enlace-exportacion">
                                Descargar CSV
                            </a>
                        </div>
                    </article>

                </section>

            </section>

        <?php endif; ?>

    </main>

</div>

<script src="assets/js/app.js"></script>

</body>
</html>