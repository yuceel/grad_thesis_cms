<?php
define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/db.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    set_message('Bu sayfaya erişim yetkiniz yok.', 'danger');
    header('Location: ' . SITE_URL . '/index.php?page=login');
    exit();
}

$conn = $db->getConnection();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Geçersiz seans ID'si.";
    header('Location: showtimes.php');
    exit();
}

$showtime_id = (int)$_GET['id'];

$query = "SELECT s.*, m.title as movie_title, m.duration 
          FROM showtimes s 
          JOIN movies m ON s.movie_id = m.id 
          WHERE s.id = ?";
$stmt = mysqli_prepare($conn, $query);

if (!$stmt) {
    $_SESSION['error_message'] = "Sorgu hazırlama hatası: " . mysqli_error($conn);
    header('Location: showtimes.php');
    exit();
}

mysqli_stmt_bind_param($stmt, "i", $showtime_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    $_SESSION['error_message'] = "Seans bulunamadı.";
    header('Location: showtimes.php');
    exit();
}

$showtime = mysqli_fetch_assoc($result);

$movies_query = "SELECT id, title FROM movies WHERE status = 'active' OR id = ? ORDER BY title ASC";
$movies_stmt = mysqli_prepare($conn, $movies_query);
mysqli_stmt_bind_param($movies_stmt, "i", $showtime['movie_id']);
mysqli_stmt_execute($movies_stmt);
$movies_result = mysqli_stmt_get_result($movies_stmt);
$movies = mysqli_fetch_all($movies_result, MYSQLI_ASSOC);

$screens_query = "SELECT id, name FROM screens WHERE status = 'active' OR id = ? ORDER BY name ASC";
$screens_stmt = mysqli_prepare($conn, $screens_query);
mysqli_stmt_bind_param($screens_stmt, "i", $showtime['screen_id']);
mysqli_stmt_execute($screens_stmt);
$screens_result = mysqli_stmt_get_result($screens_stmt);
$screens = mysqli_fetch_all($screens_result, MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $movie_id = (int)$_POST['movie_id'];
        $screen_id = (int)$_POST['screen_id'];
        $show_date = mysqli_real_escape_string($conn, $_POST['show_date']);
        $show_time = mysqli_real_escape_string($conn, $_POST['show_time']);
        $price = (float)$_POST['price'];
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        
        if ($movie_id <= 0) {
            throw new Exception("Lütfen bir film seçin.");
        }
        
        if ($screen_id <= 0) {
            throw new Exception("Lütfen bir salon seçin.");
        }
        
        if (empty($show_date) || empty($show_time)) {
            throw new Exception("Tarih ve saat alanları boş olamaz.");
        }
        
        if ($price <= 0) {
            throw new Exception("Fiyat 0'dan büyük olmalıdır.");
        }
        
        if (!in_array($status, ['active', 'cancelled', 'completed'])) {
            throw new Exception("Geçersiz durum değeri.");
        }
        
        $movie_query = "SELECT duration FROM movies WHERE id = ?";
        $movie_stmt = mysqli_prepare($conn, $movie_query);
        mysqli_stmt_bind_param($movie_stmt, "i", $movie_id);
        mysqli_stmt_execute($movie_stmt);
        $movie_result = mysqli_stmt_get_result($movie_stmt);
        $movie = mysqli_fetch_assoc($movie_result);
        
        if (!$movie) {
            throw new Exception("Film bulunamadı.");
        }
        
        $start_time = $show_date . ' ' . $show_time;
        $end_time = date('Y-m-d H:i:s', strtotime($start_time . ' + ' . $movie['duration'] . ' minutes'));
        
        $conflict_query = "SELECT s.*, m.title as movie_title, m.duration 
                          FROM showtimes s 
                          JOIN movies m ON s.movie_id = m.id 
                          WHERE s.screen_id = ? AND DATE(s.start_time) = ? AND s.status = 'active' AND s.id != ?";
        $conflict_stmt = mysqli_prepare($conn, $conflict_query);
        mysqli_stmt_bind_param($conflict_stmt, "isi", $screen_id, $show_date, $showtime_id);
        mysqli_stmt_execute($conflict_stmt);
        $conflict_result = mysqli_stmt_get_result($conflict_stmt);
        
        $new_show_start = strtotime($start_time);
        $new_show_end = strtotime($end_time);
        
        while ($existing_show = mysqli_fetch_assoc($conflict_result)) {
            $existing_start = strtotime($existing_show['start_time']);
            $existing_end = strtotime($existing_show['end_time']);
            
            if (($new_show_start >= $existing_start && $new_show_start < $existing_end) ||
                ($new_show_end > $existing_start && $new_show_end <= $existing_end) ||
                ($new_show_start <= $existing_start && $new_show_end >= $existing_end)) {
                throw new Exception("Bu seans, " . $existing_show['movie_title'] . " filmi için " . 
                                  date('H:i', $existing_start) . " seansı ile çakışıyor.");
            }
        }
        
        $db->beginTransaction();
        
        $update_query = "UPDATE showtimes SET movie_id = ?, screen_id = ?, start_time = ?, 
                        end_time = ?, price = ?, status = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        
        if (!$update_stmt) {
            throw new Exception("Güncelleme sorgusu hazırlama hatası: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($update_stmt, "iissssi", $movie_id, $screen_id, $start_time, 
                             $end_time, $price, $status, $showtime_id);
        
        if (!mysqli_stmt_execute($update_stmt)) {
            throw new Exception("Seans güncellenirken bir hata oluştu: " . mysqli_stmt_error($update_stmt));
        }
        
        $db->commit();
        
        $_SESSION['success_message'] = "Seans başarıyla güncellendi.";
        
        header('Location: showtimes.php?message=updated');
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        
        $_SESSION['error_message'] = $e->getMessage();
    }
}

$pageTitle = "Seans Düzenle";
include ROOT_PATH . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        
        <?php include __DIR__ . '/sidebar.php'; ?>

      
        <main role="main" class="col-md-10 ml-sm-auto px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Seans Düzenle</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="showtimes.php" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Geri Dön
                    </a>
                </div>
            </div>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="POST" action="" id="showtimeForm">
                        <div class="form-group">
                            <label for="movie_id">Film</label>
                            <select class="form-control" id="movie_id" name="movie_id" required>
                                <option value="">Film Seçin</option>
                                <?php foreach ($movies as $movie): ?>
                                    <option value="<?php echo $movie['id']; ?>" 
                                            <?php echo $movie['id'] == $showtime['movie_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($movie['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="screen_id">Salon</label>
                            <select class="form-control" id="screen_id" name="screen_id" required>
                                <option value="">Salon Seçin</option>
                                <?php foreach ($screens as $screen): ?>
                                    <option value="<?php echo $screen['id']; ?>"
                                            <?php echo $screen['id'] == $showtime['screen_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($screen['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="show_date">Tarih</label>
                            <input type="date" class="form-control" id="show_date" name="show_date" 
                                   value="<?php echo date('Y-m-d', strtotime($showtime['start_time'])); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="show_time">Saat</label>
                            <input type="time" class="form-control" id="show_time" name="show_time" 
                                   value="<?php echo date('H:i', strtotime($showtime['start_time'])); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="price">Fiyat (₺)</label>
                            <input type="number" class="form-control" id="price" name="price" 
                                   value="<?php echo $showtime['price']; ?>" min="0" step="0.01" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Durum</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="active" <?php echo $showtime['status'] === 'active' ? 'selected' : ''; ?>>Aktif</option>
                                <option value="cancelled" <?php echo $showtime['status'] === 'cancelled' ? 'selected' : ''; ?>>İptal Edildi</option>
                                <option value="completed" <?php echo $showtime['status'] === 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
$(document).ready(function() {
    $('#showtimeForm').on('submit', function(e) {
        var showDate = $('#show_date').val();
        var showTime = $('#show_time').val();
        var today = new Date();
        var selectedDateTime = new Date(showDate + 'T' + showTime);
        
        if (selectedDateTime < today) {
            e.preventDefault();
            alert('Geçmiş bir tarih seçemezsiniz.');
            return false;
        }
    });
    
    $('.alert .close').on('click', function() {
        $(this).closest('.alert').alert('close');
    });
});
</script>

<?php include ROOT_PATH . '/includes/footer.php'; ?> 