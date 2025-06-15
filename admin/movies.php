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

$pageTitle = "Film Yönetimi";
include ROOT_PATH . '/includes/header.php';

if (isset($_POST['delete_movie']) && isset($_POST['movie_id'])) {
    $movie_id = (int)$_POST['movie_id'];
    
    $check_query = "SELECT COUNT(*) as count FROM showtimes WHERE movie_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "i", $movie_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    $row = mysqli_fetch_assoc($result);

    if ($row['count'] > 0) {
        $error = "Bu filme ait seanslar bulunduğu için silinemez.";
    } else {
        $poster_query = "SELECT poster FROM movies WHERE id = ?";
        $poster_stmt = mysqli_prepare($conn, $poster_query);
        mysqli_stmt_bind_param($poster_stmt, "i", $movie_id);
        mysqli_stmt_execute($poster_stmt);
        $poster_result = mysqli_stmt_get_result($poster_stmt);
        $poster = mysqli_fetch_assoc($poster_result);
        
        if ($poster && $poster['poster']) {
            $poster_path = UPLOAD_PATH . '/posters/' . $poster['poster'];
            if (file_exists($poster_path)) {
                unlink($poster_path);
            }
        }

        $delete_query = "DELETE FROM movies WHERE id = ?";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, "i", $movie_id);
        
        if (mysqli_stmt_execute($stmt)) {
            header('Location: movies.php?message=deleted');
            exit();
        } else {
            $error = "Film silinirken bir hata oluştu.";
        }
    }
}

if (isset($_POST['update_status']) && isset($_POST['movie_id']) && isset($_POST['status'])) {
    $movie_id = (int)$_POST['movie_id'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    try {
        $db->beginTransaction();
        $query = "UPDATE movies SET status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "si", $status, $movie_id);
        mysqli_stmt_execute($stmt);
        $db->commit();
        header('Location: movies.php?message=updated');
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Status güncellenirken bir hata oluştu: " . $e->getMessage();
    }
}
?>

<div class="container-fluid">
    <div class="row">

        <?php include __DIR__ . '/sidebar.php'; ?>


        <main role="main" class="col-md-10 ml-sm-auto px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Film Yönetimi</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="movie_add.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Yeni Film Ekle
                    </a>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php 
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
                        $last_movie = $db->select("SELECT * FROM movies ORDER BY id DESC LIMIT 1");
                        if (empty($last_movie)) {
                            $message = "Film eklenirken bir hata oluştu. Lütfen tekrar deneyin.";
                            $message_type = 'danger';
                        } else {
                            $message = "Film başarıyla eklendi: " . htmlspecialchars($last_movie[0]['title']);
                        }
                        break;
                    case 'updated':
                        $message = "Film başarıyla güncellendi.";
                        break;
                    case 'deleted':
                        $message = "Film başarıyla silindi.";
                        break;
                }
            }
            
            if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

        
            <div class="card shadow mb-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="moviesTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Poster</th>
                                    <th>Film Adı</th>
                                    <th>Tür</th>
                                    <th>Süre</th>
                                    <th>Yayın Tarihi</th>
                                    <th>Durum</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $query = "SELECT * FROM movies ORDER BY release_date DESC";
                                $movies = $db->select($query);
                                
                                foreach ($movies as $movie) {
                                    echo "<tr>";
                                    echo "<td>" . $movie['id'] . "</td>";
                                    echo "<td>";
                                    if (!empty($movie['poster'])) {
                                        echo "<img src='" . htmlspecialchars($movie['poster']) . "' 
                                              alt='" . htmlspecialchars($movie['title']) . "' 
                                              class='img-thumbnail' style='max-width: 100px;'>";
                                    } else {
                                        echo "<span class='text-muted'>Poster yok</span>";
                                    }
                                    echo "</td>";
                                    echo "<td>" . htmlspecialchars($movie['title']) . "</td>";
                                    echo "<td>" . htmlspecialchars($movie['genre']) . "</td>";
                                    echo "<td>" . $movie['duration'] . " dk</td>";
                                    echo "<td>" . date('d.m.Y', strtotime($movie['release_date'])) . "</td>";
                                    echo "<td>";
                                    echo "<span class='badge " . match($movie['status']) {
                                        'active' => 'bg-success text-white',
                                        'upcoming' => 'bg-warning text-white',
                                        'inactive' => 'bg-secondary text-white',
                                        default => 'bg-secondary text-white'
                                    } . "'>";
                                    echo match($movie['status']) {
                                        'active' => 'Aktif',
                                        'upcoming' => 'Yakında',
                                        'inactive' => 'Pasif',
                                        default => 'Bilinmiyor'
                                    };
                                    echo "</span>";
                                    echo "</td>";
                                    echo "<td>";
                                    echo "<div class='btn-group' role='group'>";
                                    echo "<a href='movie_edit.php?id=" . $movie['id'] . "' class='btn btn-sm btn-primary'>";
                                    echo "<i class='fas fa-edit'></i> Düzenle";
                                    echo "</a>";
                                    echo "<button type='button' class='btn btn-sm btn-danger' data-bs-toggle='modal' data-bs-target='#deleteModal" . $movie['id'] . "'>";
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
$movies = $db->select("SELECT * FROM movies ORDER BY release_date DESC");
foreach ($movies as $movie) {
?>
<div class="modal fade" id="deleteModal<?php echo $movie['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $movie['id']; ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel<?php echo $movie['id']; ?>">
                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                    Film Sil
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <i class="fas fa-trash-alt text-danger mb-3" style="font-size: 3rem;"></i>
                    <h6 class="mb-3">Silme İşlemini Onaylayın</h6>
                    <p class="mb-2">
                        <strong><?php echo htmlspecialchars($movie['title']); ?></strong> filmini silmek istediğinizden emin misiniz?
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
                <form action="movie_delete.php" method="POST" style="display: inline;">
                    <input type="hidden" name="movie_id" value="<?php echo $movie['id']; ?>">
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
                $('#moviesTable').DataTable({
                    "language": {
                        "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/tr.json"
                    },
                    "order": [[0, "desc"]], 
                    "columnDefs": [
                        { "orderable": false, "targets": [1, 7] } 
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