<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo base_url('assets/css/style.css'); ?>" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo base_url(); ?>">
                <i class="fas fa-film"></i>
                <span><?php echo SITE_NAME; ?></span>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="fas fa-bars"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2" href="<?php echo page_url('home'); ?>">
                            <i class="fas fa-home"></i>
                            <span>Ana Sayfa</span>
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['user_role'] === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link d-flex align-items-center gap-2" href="<?php echo page_url('admin'); ?>">
                                    <i class="fas fa-cog"></i>
                                    <span>Admin Panel</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle"></i>
                                <span><?php echo $_SESSION['user_name']; ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-2" href="<?php echo page_url('profile'); ?>">
                                        <i class="fas fa-user"></i>
                                        <span>Profilim</span>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-2 text-danger" href="<?php echo page_url('logout'); ?>">
                                        <i class="fas fa-sign-out-alt"></i>
                                        <span>Çıkış Yap</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-2" href="<?php echo page_url('login'); ?>">
                                <i class="fas fa-sign-in-alt"></i>
                                <span>Giriş Yap</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-2" href="<?php echo page_url('register'); ?>">
                                <i class="fas fa-user-plus"></i>
                                <span>Kayıt Ol</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <?php if (isset($_SESSION['message'])): ?>
            <?php if (isset($_SESSION['show_popup']) && $_SESSION['show_popup']): ?>
  
                <div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title" id="messageModalLabel">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    Hesap Durumu
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="text-center mb-3">
                                    <i class="fas fa-user-lock text-danger" style="font-size: 3rem;"></i>
                                </div>
                                <p class="text-center"><?php echo $_SESSION['message']; ?></p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tamam</button>
                            </div>
                        </div>
                    </div>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var messageModal = new bootstrap.Modal(document.getElementById('messageModal'));
                        messageModal.show();
                    });
                </script>
                <?php 
                    unset($_SESSION['show_popup']);
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                ?>
            <?php else: ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show shadow-sm">
                    <div class="d-flex align-items-center gap-2">
                        <?php if ($_SESSION['message_type'] === 'success'): ?>
                            <i class="fas fa-check-circle"></i>
                        <?php elseif ($_SESSION['message_type'] === 'danger'): ?>
                            <i class="fas fa-exclamation-circle"></i>
                        <?php elseif ($_SESSION['message_type'] === 'warning'): ?>
                            <i class="fas fa-exclamation-triangle"></i>
                        <?php elseif ($_SESSION['message_type'] === 'info'): ?>
                            <i class="fas fa-info-circle"></i>
                        <?php endif; ?>
                        <span><?php 
                            echo $_SESSION['message'];
                            unset($_SESSION['message']);
                            unset($_SESSION['message_type']);
                        ?></span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        <?php endif; ?> 