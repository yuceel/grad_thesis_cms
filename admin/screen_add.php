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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $capacity = (int)$_POST['capacity'];
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        
        if (empty($name)) {
            throw new Exception("Salon adı boş olamaz.");
        }
        
        if ($capacity <= 0) {
            throw new Exception("Kapasite 0'dan büyük olmalıdır.");
        }
        
        if (!in_array($status, ['active', 'inactive'])) {
            throw new Exception("Geçersiz durum değeri.");
        }
        
        $db->beginTransaction();
        
        $insert_query = "INSERT INTO screens (name, capacity, status) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_query);
        
        if (!$stmt) {
            throw new Exception("Ekleme sorgusu hazırlama hatası: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "sis", $name, $capacity, $status);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Salon eklenirken bir hata oluştu: " . mysqli_stmt_error($stmt));
        }
        
        $screen_id = mysqli_insert_id($conn);
        for ($i = 1; $i <= $capacity; $i++) {
            if ($i <= 20) {
                $seat_type = 'vip';
            } elseif ($i <= 50) {
                $seat_type = 'premium';
            } else {
                $seat_type = 'standard';
            }
            $db->insert('seats', [
                'screen_id' => $screen_id,
                'seat_number' => $i,
                'seat_type' => $seat_type,
                'status' => 'active'
            ]);
        }
        
        $db->commit();
        
        $_SESSION['success_message'] = "Salon başarıyla eklendi: " . htmlspecialchars($name);
        
        header('Location: screens.php?message=added');
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        
        $_SESSION['error_message'] = $e->getMessage();
    }
}

$pageTitle = "Yeni Salon Ekle";
include ROOT_PATH . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="row">

        <?php include __DIR__ . '/sidebar.php'; ?>

        <main role="main" class="col-md-10 ml-sm-auto px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Yeni Salon Ekle</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="screens.php" class="btn btn-sm btn-secondary">
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
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="name">Salon Adı</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Kapasite</label>
                            <div class="mt-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="capacity" id="capacity50" value="50" required>
                                    <label class="form-check-label" for="capacity50">50 Koltuk</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="capacity" id="capacity100" value="100">
                                    <label class="form-check-label" for="capacity100">100 Koltuk</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="capacity" id="capacity200" value="200">
                                    <label class="form-check-label" for="capacity200">200 Koltuk</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Durum</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="active">Aktif</option>
                                <option value="inactive">Pasif</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Salon Ekle</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?> 