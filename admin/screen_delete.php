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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['screen_id'])) {
    $screen_id = filter_var($_POST['screen_id'], FILTER_VALIDATE_INT);
    
    if ($screen_id) {
        try {
            $conn = $db->getConnection();
            
            $db->beginTransaction();
            
            $check_query = "SELECT COUNT(*) as count FROM showtimes WHERE screen_id = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            
            if (!$check_stmt) {
                throw new Exception("Seans kontrolü için sorgu hazırlama hatası: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($check_stmt, "i", $screen_id);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);
            $row = mysqli_fetch_assoc($result);
            
            if ($row['count'] > 0) {
                throw new Exception("Bu salona ait seanslar bulunduğu için silinemez.");
            }
            
            $check_screen_query = "SELECT id, name FROM screens WHERE id = ?";
            $check_screen_stmt = mysqli_prepare($conn, $check_screen_query);
            
            if (!$check_screen_stmt) {
                throw new Exception("Salon kontrolü için sorgu hazırlama hatası: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($check_screen_stmt, "i", $screen_id);
            mysqli_stmt_execute($check_screen_stmt);
            $screen_result = mysqli_stmt_get_result($check_screen_stmt);
            
            if (mysqli_num_rows($screen_result) === 0) {
                throw new Exception("Salon bulunamadı.");
            }
            
            $screen = mysqli_fetch_assoc($screen_result);
            
            $delete_seats_query = "DELETE FROM seats WHERE screen_id = ?";
            $delete_seats_stmt = mysqli_prepare($conn, $delete_seats_query);
            
            if (!$delete_seats_stmt) {
                throw new Exception("Koltuk silme sorgusu hazırlama hatası: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($delete_seats_stmt, "i", $screen_id);
            
            if (!mysqli_stmt_execute($delete_seats_stmt)) {
                throw new Exception("Koltuklar silinirken bir hata oluştu: " . mysqli_stmt_error($delete_seats_stmt));
            }
            
            $delete_screen_query = "DELETE FROM screens WHERE id = ?";
            $delete_screen_stmt = mysqli_prepare($conn, $delete_screen_query);
            
            if (!$delete_screen_stmt) {
                throw new Exception("Salon silme sorgusu hazırlama hatası: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($delete_screen_stmt, "i", $screen_id);
            
            if (!mysqli_stmt_execute($delete_screen_stmt)) {
                throw new Exception("Salon silinirken bir hata oluştu: " . mysqli_stmt_error($delete_screen_stmt));
            }
            
            if (mysqli_stmt_affected_rows($delete_screen_stmt) === 0) {
                throw new Exception("Salon silinemedi.");
            }
            
            $verify_query = "SELECT COUNT(*) as count FROM screens WHERE id = ?";
            $verify_stmt = mysqli_prepare($conn, $verify_query);
            mysqli_stmt_bind_param($verify_stmt, "i", $screen_id);
            mysqli_stmt_execute($verify_stmt);
            $verify_result = mysqli_stmt_get_result($verify_stmt);
            $verify_row = mysqli_fetch_assoc($verify_result);
            
            if ($verify_row['count'] > 0) {
                throw new Exception("Salon silme işlemi başarısız oldu.");
            }
            
            $db->commit();
            
            $_SESSION['success_message'] = "Salon başarıyla silindi: " . htmlspecialchars($screen['name']);
            
            header('Location: screens.php?message=deleted');
            exit();
            
        } catch (Exception $e) {
            $db->rollBack();
            
            $_SESSION['error_message'] = $e->getMessage();
            
            header('Location: screens.php');
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Geçersiz salon ID'si.";
        header('Location: screens.php');
        exit();
    }
} else {
    $_SESSION['error_message'] = "Geçersiz istek.";
    header('Location: screens.php');
    exit();
}
?> 