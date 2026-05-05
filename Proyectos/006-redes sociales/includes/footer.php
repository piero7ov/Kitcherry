<?php
// ==========================================================
// KITCHERRY - FOOTER
// Archivo: includes/footer.php
// ==========================================================

$emailContactoFooter = "pieroolivaresdev@gmail.com";
$githubUrlFooter = "https://github.com/piero7ov";
$linkedinUrlFooter = "https://www.linkedin.com/in/piero7ov/";
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
                    <li><strong>Email:</strong> <?php echo e($emailContactoFooter); ?></li>
                    <li><strong>Sector:</strong> Hostelería y restauración</li>
                    <li><strong>Zona:</strong> Comunitat Valenciana</li>
                </ul>

                <div class="footer-redes footer-redes-iconos" aria-label="Canales de contacto">
                    <a href="mailto:<?php echo e($emailContactoFooter); ?>" aria-label="Enviar email a Kitcherry">
                        <img src="img/redes/email.png" alt="Email">
                    </a>

                    <a href="<?php echo e($githubUrlFooter); ?>" target="_blank" rel="noopener" aria-label="GitHub de Piero Olivares">
                        <img src="img/redes/github.png" alt="GitHub">
                    </a>

                    <a href="<?php echo e($linkedinUrlFooter); ?>" target="_blank" rel="noopener" aria-label="LinkedIn de Kitcherry">
                        <img src="img/redes/linkedin.png" alt="LinkedIn">
                    </a>
                </div>
            </div>

            <div class="footer-col">
                <h3>Legal</h3>
                <nav class="footer-nav-col">
                    <a href="aviso-legal.php">Aviso legal</a>
                    <a href="privacidad.php">Política de privacidad</a>
                    <a href="cookies.php">Política de cookies</a>
                </nav>
            </div>

        </div>
    </footer>

</body>
</html>