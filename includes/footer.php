<?php


?>
    </main> 

    <footer class="site-footer"> 
        <div class="container"> 
            <div class="footer-content">
                <div class="footer-links">
                    <a href="<?php echo htmlspecialchars(BASE_URL . 'privacy_policy.php'); ?>" class="ab">Privacy Policy</a>
                    <span class="footer-link-separator">|</span>
                    <a href="<?php echo htmlspecialchars(BASE_URL . 'contact_support.php'); ?>" class="ab">Contact Support</a>
                    
                    
                    
                </div>
                <div class="footer-copyright">
                    <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars(SITE_NAME ?? 'AMU Survey System'); ?>. All Rights Reserved.</p>
                    <?php  ?>
                    
                </div>
            </div>
        </div>
    </footer>

    
    
    
    
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const navToggleBtn = document.querySelector('.public-nav-toggle');
        const publicNavMenu = document.getElementById('publicNavMenuGlobal'); // ID of the <nav> element

        if (navToggleBtn && publicNavMenu) {
            navToggleBtn.addEventListener('click', function() {
                const isExpanded = publicNavMenu.classList.toggle('is-open'); // Toggle the .is-open class
                this.setAttribute('aria-expanded', isExpanded); // Update ARIA attribute
            });
        } else {
            if (!navToggleBtn) console.warn("Mobile navigation toggle button (.public-nav-toggle) not found.");
            if (!publicNavMenu) console.warn("Public navigation menu (#publicNavMenuGlobal) not found.");
        }
    });
    </script>

</body>
</html>