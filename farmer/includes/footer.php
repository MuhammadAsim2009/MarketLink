        </main>
    </div>
</div>

<!-- Bootstrap 5.3.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom Vanilla JS & Modal System -->
<script src="<?= ASSETS_URL ?>js/main.js?v=<?= time() ?>"></script>

<script>
// Mobile Sidebar Toggle and Backdrop Handlers
(function() {
    const toggleBtn = document.getElementById('farmerSidebarToggle');
    const sidebar = document.getElementById('farmerSidebar');
    const backdrop = document.getElementById('farmerSidebarBackdrop');

    function openSidebar() {
        if (sidebar && backdrop) {
            sidebar.classList.add('open');
            backdrop.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeSidebar() {
        if (sidebar && backdrop) {
            sidebar.classList.remove('open');
            backdrop.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (sidebar && sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }

    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
            closeSidebar();
        }
    });
})();
</script>
</body>
</html>
