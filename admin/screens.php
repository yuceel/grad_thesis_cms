<?php
define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/db.php';

$conn = $db->getConnection();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    set_message('Bu sayfaya erişim yetkiniz yok.', 'danger');
    header('Location: ' . SITE_URL . '/index.php?page=login');
    exit();
}

$pageTitle = "Salon Yönetimi";
include ROOT_PATH . '/includes/header.php';

$message = '';
$message_type = 'success';

if (isset($_SESSION['error_message'])) {
    $message = $_SESSION['error_message'];
    $message_type = 'danger';
    unset($_SESSION['error_message']);
} elseif (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
} elseif (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'added':
            $message = "Salon başarıyla eklendi.";
            break;
        case 'updated':
            $message = "Salon başarıyla güncellendi.";
            break;
        case 'deleted':
            $message = "Salon başarıyla silindi.";
            break;
    }
}
?>

<div class="container-fluid">
    <div class="row">
      
        <?php include __DIR__ . '/sidebar.php'; ?>

        <main role="main" class="col-md-10 ml-sm-auto px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Salon Yönetimi</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="screen_add.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Yeni Salon Ekle
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

   
            <div class="card shadow mb-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="screensTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Salon Adı</th>
                                    <th>Kapasite</th>
                                    <th>Durum</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $query = "SELECT * FROM screens ORDER BY name ASC";
                                $screens = $db->select($query);
                                
                                foreach ($screens as $screen) {
                                    echo "<tr>";
                                    echo "<td>" . $screen['id'] . "</td>";
                                    echo "<td>" . htmlspecialchars($screen['name']) . "</td>";
                                    echo "<td>" . $screen['capacity'] . " koltuk</td>";
                                    echo "<td>";
                                    echo "<span class='badge " . ($screen['status'] === 'active' ? 'bg-success text-white' : 'bg-warning text-white') . "'>";
                                    echo $screen['status'] === 'active' ? 'Aktif' : 'Pasif';
                                    echo "</span>";
                                    echo "</td>";
                                    echo "<td>";
                                    echo "<div class='btn-group' role='group'>";
                                    echo "<a href='screen_edit.php?id=" . $screen['id'] . "' class='btn btn-sm btn-primary'>";
                                    echo "<i class='fas fa-edit'></i> Düzenle";
                                    echo "</a>";
                                    echo "<button type='button' class='btn btn-sm btn-danger' data-bs-toggle='modal' data-bs-target='#deleteModal" . $screen['id'] . "'>";
                                    echo "<i class='fas fa-trash'></i> Sil";
                                    echo "</button>";
                                    echo "</div>";
                                    echo "</td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php
$screens = $db->select("SELECT * FROM screens ORDER BY name ASC");
foreach ($screens as $screen) {
?>
<div class="modal fade" id="deleteModal<?php echo $screen['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $screen['id']; ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel<?php echo $screen['id']; ?>">
                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                    Salon Sil
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <i class="fas fa-trash-alt text-danger mb-3" style="font-size: 3rem;"></i>
                    <h6 class="mb-3">Silme İşlemini Onaylayın</h6>
                    <p class="mb-2">
                        <strong><?php echo htmlspecialchars($screen['name']); ?></strong> salonunu silmek istediğinizden emin misiniz?
                    </p>
                    <div class="alert alert-warning border-0" role="alert">
                        <small><i class="fas fa-info-circle me-1"></i>Bu işlem geri alınamaz!</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>İptal
                </button>
                <form action="screen_delete.php" method="POST" style="display: inline;">
                    <input type="hidden" name="screen_id" value="<?php echo $screen['id']; ?>">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Sil
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php } ?>


<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/admin.css">

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $ !== 'undefined') {
        loadDataTables();
    } else {
        const script = document.createElement('script');
        script.src = 'https://code.jquery.com/jquery-3.6.0.min.js';
        script.onload = loadDataTables;
        document.head.appendChild(script);
    }
    
    function loadDataTables() {
        const dtScript = document.createElement('script');
        dtScript.src = 'https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js';
        dtScript.onload = function() {
            const dtBootstrapScript = document.createElement('script');
            dtBootstrapScript.src = 'https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js';
            dtBootstrapScript.onload = function() {
                $('#screensTable').DataTable({
                    "language": {
                        "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/tr.json"
                    },
                    "order": [[0, "desc"]], 
                    "columnDefs": [
                        { "orderable": false, "targets": [4] } 
                    ]
                });
            };
            document.head.appendChild(dtBootstrapScript);
        };
        document.head.appendChild(dtScript);
    }

    const alerts = document.querySelectorAll('.alert-success');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    document.querySelectorAll('.modal').forEach(function(modal) {
        modal.addEventListener('show.bs.modal', function (e) {
            document.body.classList.add('modal-open');
        });
        
        modal.addEventListener('hidden.bs.modal', function (e) {
            document.body.classList.remove('modal-open');
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
        });
    });

    document.querySelectorAll('[data-bs-toggle="modal"]').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            console.log('Modal button clicked:', this.getAttribute('data-bs-target'));
        });
    });

    document.querySelectorAll('.btn').forEach(function(btn) {
        btn.style.pointerEvents = 'auto';
        btn.style.cursor = 'pointer';
    });
});
</script>

<?php include ROOT_PATH . '/includes/footer.php'; ?> 