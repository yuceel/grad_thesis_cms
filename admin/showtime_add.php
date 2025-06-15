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

$movies_query = "SELECT id, title FROM movies WHERE status = 'active' ORDER BY title ASC";
$movies = $db->select($movies_query);

$screens_query = "SELECT id, name FROM screens WHERE status = 'active' ORDER BY name ASC";
$screens = $db->select($screens_query);

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
                          WHERE s.screen_id = ? AND DATE(s.start_time) = ? AND s.status = 'active'";
        $conflict_stmt = mysqli_prepare($conn, $conflict_query);
        mysqli_stmt_bind_param($conflict_stmt, "is", $screen_id, $show_date);
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
        
        $insert_query = "INSERT INTO showtimes (movie_id, screen_id, start_time, end_time, price, status) 
                        VALUES (?, ?, ?, ?, ?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_query);
        
        if (!$insert_stmt) {
            throw new Exception("Sorgu hazırlama hatası: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($insert_stmt, "iissss", $movie_id, $screen_id, $start_time, $end_time, $price, $status);
        
        if (!mysqli_stmt_execute($insert_stmt)) {
            throw new Exception("Seans eklenirken bir hata oluştu: " . mysqli_stmt_error($insert_stmt));
        }
        
        $db->commit();
        
        $_SESSION['success_message'] = "Seans başarıyla eklendi.";
        
        header('Location: showtimes.php?message=added');
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        
        $_SESSION['error_message'] = $e->getMessage();
    }
}

$pageTitle = "Yeni Seans Ekle";
include ROOT_PATH . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="row">

        <?php include __DIR__ . '/sidebar.php'; ?>

   
        <main role="main" class="col-md-10 ml-sm-auto px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Yeni Seans Ekle</h1>
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
                                    <option value="<?php echo $movie['id']; ?>">
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
                                    <option value="<?php echo $screen['id']; ?>">
                                        <?php echo htmlspecialchars($screen['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="show_date">Tarih</label>
                            <input type="date" class="form-control" id="show_date" name="show_date" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="show_time">Saat</label>
                            <input type="time" class="form-control" id="show_time" name="show_time" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="price">Fiyat (₺)</label>
                            <input type="number" class="form-control" id="price" name="price" 
                                   min="0" step="0.01" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Durum</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="active">Aktif</option>
                                <option value="cancelled">İptal Edildi</option>
                                <option value="completed">Tamamlandı</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Seans Ekle</button>
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