<?php
/**
 * MarketLink - Shared Footer
 */
?>
</main><!-- /.main-content -->

<!-- ── Footer ──────────────────────────────────────────────────── -->
<footer class="ml-footer">
    <div class="container">
        <div class="row g-5">

            <!-- Brand Column -->
            <div class="col-lg-4 col-md-6">
                <div class="footer-brand mb-3">
                    <span class="brand-icon"><i class="bi bi-flower1"></i></span>
                    Market<span style="color:var(--accent)">Link</span>
                </div>
                <p class="mb-4" style="max-width:300px; line-height:1.7;">
                    Connecting local farmers with community shoppers. Farm-fresh produce, pre-ordered and picked up directly at the weekend stall.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="tech-pill"><i class="bi bi-shield-check" style="color:var(--primary-mid)"></i> Verified Stalls</span>
                    <span class="tech-pill"><i class="bi bi-geo-alt" style="color:#7CD4FD"></i> OpenStreetMap</span>
                    <span class="tech-pill"><i class="bi bi-lock" style="color:#FEC84B"></i> Secure Checkout</span>
                </div>
            </div>

            <!-- Discover -->
            <div class="col-lg-2 col-md-3 col-6 footer-col">
                <h6>Discover</h6>
                <ul class="footer-links">
                    <li><a href="<?= BASE_URL ?>">Home</a></li>
                    <li><a href="<?= BASE_URL ?>customer/browse-markets.php">Find Markets</a></li>
                    <li><a href="<?= BASE_URL ?>customer/browse-products.php">Fresh Produce</a></li>
                    <li><a href="<?= BASE_URL ?>about.php">About Us</a></li>
                    <li><a href="<?= BASE_URL ?>contact.php">Contact</a></li>
                </ul>
            </div>

            <!-- For Farmers -->
            <div class="col-lg-2 col-md-3 col-6 footer-col">
                <h6>For Farmers</h6>
                <ul class="footer-links">
                    <li><a href="<?= BASE_URL ?>auth/register.php?role=farmer">Register Stall</a></li>
                    <li><a href="<?= BASE_URL ?>auth/login.php">Farmer Sign In</a></li>
                    <li><a href="<?= BASE_URL ?>farmer/dashboard.php">Manage Stock</a></li>
                    <li><a href="<?= BASE_URL ?>farmer/orders.php">View Orders</a></li>
                </ul>
            </div>

            <!-- Project Info -->
            <div class="col-lg-4 col-md-6 footer-col">
                <h6>About MarketLink</h6>
                <p class="mb-3">
                    A digital community platform connecting local sustainable growers directly with neighborhood shoppers. Built with a zero-food-waste mission.
                </p>
                <a href="<?= BASE_URL ?>auth/admin-login.php"
                   style="display:inline-flex;align-items:center;gap:6px;color:rgba(255,255,255,.55);font-size:.8rem;border:1px solid rgba(255,255,255,.12);padding:.35rem .75rem;border-radius:var(--radius-sm);transition:all var(--transition);"
                   onmouseover="this.style.color='#fff';this.style.borderColor='rgba(255,255,255,.35)';"
                   onmouseout="this.style.color='rgba(255,255,255,.55)';this.style.borderColor='rgba(255,255,255,.12)';">
                    <i class="bi bi-lock-fill"></i> Admin Portal
                </a>
            </div>
        </div>

        <!-- Bottom Bar -->
        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> MarketLink. All rights reserved.</span>
            <div class="footer-tech-stack">
                <span class="tech-pill">PHP 8</span>
                <span class="tech-pill">MySQL</span>
                <span class="tech-pill">Bootstrap 5</span>
                <span class="tech-pill">Leaflet.js</span>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<!-- Custom Vanilla JS -->
<script src="<?= ASSETS_URL ?>js/main.js"></script>
</body>
</html>
