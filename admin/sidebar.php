
<button class="btn btn-link d-md-none" id="sidebarToggle" type="button">
    <i class="fas fa-bars"></i>
</button>

<nav id="sidebar" class="col-md-2 bg-dark sidebar">
    <div class="sidebar-sticky">
        <div class="sidebar-header">
            <h5 class="text-white mb-0">Admin Panel</h5>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'movies.php' || basename($_SERVER['PHP_SELF']) == 'movie_add.php' || basename($_SERVER['PHP_SELF']) == 'movie_edit.php' ? 'active' : ''; ?>" href="movies.php">
                    <i class="fas fa-film"></i>
                    Film Yönetimi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'screens.php' || basename($_SERVER['PHP_SELF']) == 'screen_add.php' || basename($_SERVER['PHP_SELF']) == 'screen_edit.php' ? 'active' : ''; ?>" href="screens.php">
                    <i class="fas fa-door-open"></i>
                    Salon Yönetimi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'showtimes.php' ? 'active' : ''; ?>" href="showtimes.php">
                    <i class="fas fa-clock"></i>
                    Seans Yönetimi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
                    <i class="fas fa-users"></i>
                    Kullanıcılar
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>" href="payments.php">
                    <i class="fas fa-money-bill-wave"></i>
                    Ödemeler
                </a>
            </li>
            <li class="nav-item mt-3">
                <a class="nav-link text-danger" href="<?php echo SITE_URL; ?>/index.php?page=logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Çıkış Yap
                </a>
            </li>
        </ul>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    
    sidebarToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        sidebar.classList.toggle('show');
    });
    
    document.addEventListener('click', function(event) {
        const isClickInsideSidebar = sidebar.contains(event.target);
        const isClickOnToggle = sidebarToggle.contains(event.target);
        
        if (!isClickInsideSidebar && !isClickOnToggle && window.innerWidth < 768) {
            sidebar.classList.remove('show');
        }
    });
    
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) {
            sidebar.classList.remove('show');
        }
    });
});
</script> 