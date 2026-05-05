<?php
// ==========================================================
// KITCHERRY - FOOTER
// Archivo: includes/footer.php
// ==========================================================
?>

    <footer class="footer">
        <div class="contenedor footer-contenido">

            <div class="footer-col footer-marca">
                <?php if ($logoExiste): ?>
                    <img src="<?php echo e($logoPath); ?>" alt="Kitcherry" class="footer-logo">
                <?php endif; ?>

                <div>
                    <strong>
                        <span class="kit">KIT</span><span class="cherry">CHERRY</span>
                    </strong>
                    <p>Herramientas de software para hostelería.</p>
                    <p class="footer-copy">© <?php echo date("Y"); ?> Kitcherry. Todos los derechos reservados.</p>
                </div>
            </div>

            <div class="footer-col">
                <h3>Empresa</h3>
                <nav class="footer-nav-col">
                    <a href="index.php">Inicio</a>
                    <a href="nosotros.php">Qué es Kitcherry</a>
                    <a href="index.php#servicios">Soluciones</a>
                    <a href="index.php#ia-practica">IA práctica</a>
                    <a href="index.php#contacto">Contacto</a>
                </nav>
            </div>

            <div class="footer-col">
                <h3>Contacto</h3>
                <ul class="footer-lista">
                    <li><strong>Email:</strong> contacto@kitcherry.com</li>
                    <li><strong>Sector:</strong> Hostelería y restauración</li>
                    <li><strong>Zona:</strong> Comunitat Valenciana</li>
                </ul>
            </div>

            <div class="footer-col">
                <h3>Legal</h3>
                <nav class="footer-nav-col">
                    <a href="aviso-legal.php">Aviso legal</a>
                    <a href="privacidad.php">Política de privacidad</a>
                    <a href="cookies.php">Política de cookies</a>
                </nav>

                <div class="footer-redes">
                    <a href="#" aria-label="LinkedIn de Kitcherry">LinkedIn</a>
                </div>
            </div>

        </div>
    </footer>

</body>
</html>