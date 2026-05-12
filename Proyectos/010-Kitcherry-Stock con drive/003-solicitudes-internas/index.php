<?php
// ==========================================================
// KITCHERRY STOCK
// Archivo: index.php
// Panel de consulta de stock y solicitudes internas
// ==========================================================

require_once "config.php";
require_once "funciones.php";

$error = null;
$productos = [];
$categorias = [];
$productosStockBajo = [];

$resumen = [
    "total" => 0,
    "correcto" => 0,
    "stock_bajo" => 0,
    "sobrestock" => 0,
    "inactivos" => 0,
];

try {
    $productos = obtenerProductosDesdeDrive($CSV_PRODUCTOS);
    $resumen = calcularResumenStock($productos);
    $categorias = obtenerCategoriasUnicas($productos);
    $productosStockBajo = obtenerProductosStockBajo($productos);
} catch (Exception $e) {
    $error = $e->getMessage();
}
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
            <a href="#" class="activo">
                <span class="menu-icono">▦</span>
                Dashboard
            </a>

            <a href="#productos">
                <span class="menu-icono">▤</span>
                Productos
            </a>

            <a href="#stock-bajo">
                <span class="menu-icono">!</span>
                Stock bajo
            </a>

            <a href="#solicitud">
                <span class="menu-icono">+</span>
                Solicitud
            </a>

            <a href="#categorias">
                <span class="menu-icono">#</span>
                Categorías
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
                    Revisa que la hoja esté publicada como CSV y que la URL termine en
                    <strong>output=csv</strong>.
                </p>
            </section>

        <?php else: ?>

            <section class="resumen-grid">

                <article class="tarjeta-resumen">
                    <span class="label">Productos totales</span>
                    <strong><?php echo e($resumen["total"]); ?></strong>
                    <small>Registros disponibles</small>
                </article>

                <article class="tarjeta-resumen">
                    <span class="label">Stock correcto</span>
                    <strong><?php echo e($resumen["correcto"]); ?></strong>
                    <small>Productos controlados</small>
                </article>

                <article class="tarjeta-resumen alerta">
                    <span class="label">Stock bajo</span>
                    <strong><?php echo e($resumen["stock_bajo"]); ?></strong>
                    <small>Requieren revisión</small>
                </article>

                <article class="tarjeta-resumen">
                    <span class="label">Sobrestock</span>
                    <strong><?php echo e($resumen["sobrestock"]); ?></strong>
                    <small>Por encima del máximo</small>
                </article>

            </section>

            <section class="panel-grid">

                <article class="bloque bloque-principal" id="productos">

                    <div class="bloque-cabecera">
                        <div>
                            <h2>Productos</h2>
                            <p>Consulta de productos disponibles y creación de solicitudes internas.</p>
                        </div>

                        <span class="contador" id="contadorProductos">
                            <?php echo count($productos); ?> registros
                        </span>
                    </div>

                    <div class="filtros">

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
                                        $claseEstado = claseEstadoStock($estado);
                                    ?>

                                    <tr
                                        data-id="<?php echo e($id); ?>"
                                        data-nombre="<?php echo e($nombre); ?>"
                                        data-categoria="<?php echo e($categoria); ?>"
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

                <aside class="columna-secundaria">

                    <section class="bloque bloque-lateral solicitud-panel" id="solicitud">
                        <div class="bloque-cabecera simple">
                            <h2>Resumen de solicitud</h2>
                            <p>Productos seleccionados para reposición interna.</p>
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
                                    Todavía no se han añadido productos.
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
                    </section>

                    <section class="bloque bloque-lateral" id="stock-bajo">
                        <div class="bloque-cabecera simple">
                            <h2>Stock bajo</h2>
                        </div>

                        <?php if (count($productosStockBajo) > 0): ?>

                            <div class="lista-alertas">
                                <?php foreach (array_slice($productosStockBajo, 0, 8) as $productoBajo): ?>
                                    <div class="item-alerta">
                                        <div>
                                            <strong><?php echo e($productoBajo["nombre"] ?? ""); ?></strong>
                                            <span><?php echo e($productoBajo["categoria"] ?? ""); ?></span>
                                        </div>

                                        <em>
                                            <?php echo e($productoBajo["stock_actual"] ?? ""); ?>
                                            <?php echo e($productoBajo["unidad_medida"] ?? ""); ?>
                                        </em>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        <?php else: ?>

                            <p class="texto-vacio">
                                No hay productos con stock bajo.
                            </p>

                        <?php endif; ?>
                    </section>

                    <section class="bloque bloque-lateral" id="categorias">
                        <div class="bloque-cabecera simple">
                            <h2>Categorías</h2>
                        </div>

                        <div class="lista-categorias">
                            <?php foreach ($categorias as $categoria): ?>
                                <span><?php echo e($categoria); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </section>

                </aside>

            </section>

        <?php endif; ?>

    </main>

</div>

<script src="assets/js/app.js"></script>

</body>
</html>