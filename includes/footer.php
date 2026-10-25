<?php
// includes/footer.php - Global Footer, Mobile Navigation & Modal Handlers
?>
        </main>
        
        <!-- Desktop Footer -->
        <footer class="footer py-3 bg-white border-top text-center text-muted small no-print d-none d-lg-block">
            <div class="container-fluid">
                <span>&copy; <?= date('Y') ?> <strong><?= htmlspecialchars($shop_name) ?></strong> - Clothes Shop Retail Management ERP. All rights reserved.</span>
            </div>
        </footer>
    </div>
    <!-- /#page-content-wrapper -->
</div>
<!-- /#wrapper -->

<!-- Mobile Bottom Navigation Bar (Visible on Mobile & Tablet < 992px) -->
<nav class="mobile-bottom-nav d-lg-none no-print">
    <a href="index.php" class="nav-item-btn <?= $current_page == 'index.php' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i>
        <span>Home</span>
    </a>
    <a href="products.php" class="nav-item-btn <?= in_array($current_page, ['products.php', 'categories.php', 'brands.php']) ? 'active' : '' ?>">
        <i class="bi bi-tags"></i>
        <span>Catalog</span>
    </a>
    <!-- Elevated Center POS Button -->
    <a href="pos.php" class="nav-item-btn pos-center shadow" title="Quick POS Billing">
        <i class="bi bi-upc-scan"></i>
    </a>
    <a href="product_matrix.php" class="nav-item-btn <?= $current_page == 'product_matrix.php' ? 'active' : '' ?>">
        <i class="bi bi-grid-3x3-gap"></i>
        <span>Matrix</span>
    </a>
    <button type="button" class="nav-item-btn btn btn-link p-0 border-0" id="mobileMenuDrawerBtn">
        <i class="bi bi-list"></i>
        <span>Menu</span>
    </button>
</nav>

<!-- Global Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3 no-print" style="z-index: 1080; margin-bottom: 60px;">
    <div id="liveToast" class="toast align-items-center text-white bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="toastMessage">
                Notification message
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script>
// Mobile Sidebar Drawer Controller
document.addEventListener("DOMContentLoaded", function() {
    const sidebarToggle = document.getElementById("sidebarToggle");
    const mobileMenuDrawerBtn = document.getElementById("mobileMenuDrawerBtn");
    const sidebarCloseBtn = document.getElementById("sidebarCloseBtn");
    const sidebarBackdrop = document.getElementById("sidebarBackdrop");

    function openSidebar() {
        document.body.classList.add("sidebar-open");
    }

    function closeSidebar() {
        document.body.classList.remove("sidebar-open");
    }

    function toggleSidebar(e) {
        if (e) e.preventDefault();
        document.body.classList.toggle("sidebar-open");
    }

    if (sidebarToggle) sidebarToggle.addEventListener("click", toggleSidebar);
    if (mobileMenuDrawerBtn) mobileMenuDrawerBtn.addEventListener("click", toggleSidebar);
    if (sidebarCloseBtn) sidebarCloseBtn.addEventListener("click", closeSidebar);
    if (sidebarBackdrop) sidebarBackdrop.addEventListener("click", closeSidebar);

    // Close mobile sidebar on link click
    document.querySelectorAll("#sidebar-wrapper .nav-link").forEach(link => {
        link.addEventListener("click", function() {
            if (window.innerWidth < 992) {
                closeSidebar();
            }
        });
    });
});

// Toast notification trigger helper
function showToast(message, type = 'primary') {
    const toastEl = document.getElementById('liveToast');
    const msgEl = document.getElementById('toastMessage');
    if (toastEl && msgEl) {
        toastEl.className = 'toast align-items-center text-white border-0 bg-' + type;
        msgEl.innerHTML = message;
        const toast = new bootstrap.Toast(toastEl, { delay: 3500 });
        toast.show();
    }
}
</script>

</body>
</html>
