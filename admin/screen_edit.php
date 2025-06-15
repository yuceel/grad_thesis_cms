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
    $_SESSION['error_message'] = "Geçersiz salon ID'si.";
    header('Location: screens.php');
    exit();
}

$screen_id = (int)$_GET['id'];

$query = "SELECT * FROM screens WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);

if (!$stmt) {
    $_SESSION['error_message'] = "Sorgu hazırlama hatası: " . mysqli_error($conn);
    header('Location: screens.php');
    exit();
}

mysqli_stmt_bind_param($stmt, "i", $screen_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    $_SESSION['error_message'] = "Salon bulunamadı.";
    header('Location: screens.php');
    exit();
}

$screen = mysqli_fetch_assoc($result);

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
        
        $check_query = "SELECT COUNT(*) as count FROM screens WHERE name = ? AND id != ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        
        if (!$check_stmt) {
            throw new Exception("Kontrol sorgusu hazırlama hatası: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($check_stmt, "si", $name, $screen_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $check_row = mysqli_fetch_assoc($check_result);
        
        if ($check_row['count'] > 0) {
            throw new Exception("Bu salon adı zaten kullanılıyor.");
        }
        
        $db->beginTransaction();
        
        $update_query = "UPDATE screens SET name = ?, capacity = ?, status = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        
        if (!$update_stmt) {
            throw new Exception("Güncelleme sorgusu hazırlama hatası: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($update_stmt, "sisi", $name, $capacity, $status, $screen_id);
        
        if (!mysqli_stmt_execute($update_stmt)) {
            throw new Exception("Salon güncellenirken bir hata oluştu: " . mysqli_stmt_error($update_stmt));
        }
        
        $db->commit();
        
        $_SESSION['success_message'] = "Salon başarıyla güncellendi: " . htmlspecialchars($name);
        
        header('Location: screens.php?message=updated');
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        
        $_SESSION['error_message'] = $e->getMessage();
    }
}

$pageTitle = "Salon Düzenle";
include ROOT_PATH . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="row">

        <?php include __DIR__ . '/sidebar.php'; ?>


        <main role="main" class="col-md-10 ml-sm-auto px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Salon Düzenle</h1>
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
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($screen['name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Kapasite</label>
                            <div class="mt-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="capacity" id="capacity50" value="50" 
                                           <?php echo (int)$screen['capacity'] === 50 ? 'checked' : ''; ?> required>
                                    <label class="form-check-label" for="capacity50">50 Koltuk</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="capacity" id="capacity100" value="100"
                                           <?php echo (int)$screen['capacity'] === 100 ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="capacity100">100 Koltuk</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="capacity" id="capacity200" value="200"
                                           <?php echo (int)$screen['capacity'] === 200 ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="capacity200">200 Koltuk</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Durum</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="active" <?php echo $screen['status'] === 'active' ? 'selected' : ''; ?>>Aktif</option>
                                <option value="inactive" <?php echo $screen['status'] === 'inactive' ? 'selected' : ''; ?>>Pasif</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?> 