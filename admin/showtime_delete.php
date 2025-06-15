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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['showtime_id'])) {
    $showtime_id = filter_var($_POST['showtime_id'], FILTER_VALIDATE_INT);
    
    if ($showtime_id) {
        try {
            $conn = $db->getConnection();
            
            $db->beginTransaction();
            
            $check_query = "SELECT COUNT(*) as count FROM reservations WHERE showtime_id = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            
            if (!$check_stmt) {
                throw new Exception("Rezervasyon kontrolü için sorgu hazırlama hatası: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($check_stmt, "i", $showtime_id);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);
            $row = mysqli_fetch_assoc($result);
            
            if ($row['count'] > 0) {
                throw new Exception("Bu seansa ait rezervasyonlar bulunduğu için silinemez.");
            }
            
            $check_showtime_query = "SELECT s.*, m.title as movie_title 
                                   FROM showtimes s 
                                   JOIN movies m ON s.movie_id = m.id 
                                   WHERE s.id = ?";
            $check_showtime_stmt = mysqli_prepare($conn, $check_showtime_query);
            
            if (!$check_showtime_stmt) {
                throw new Exception("Seans kontrolü için sorgu hazırlama hatası: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($check_showtime_stmt, "i", $showtime_id);
            mysqli_stmt_execute($check_showtime_stmt);
            $showtime_result = mysqli_stmt_get_result($check_showtime_stmt);
            
            if (mysqli_num_rows($showtime_result) === 0) {
                throw new Exception("Seans bulunamadı.");
            }
            
            $showtime = mysqli_fetch_assoc($showtime_result);
            
            $delete_query = "DELETE FROM showtimes WHERE id = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_query);
            
            if (!$delete_stmt) {
                throw new Exception("Seans silme sorgusu hazırlama hatası: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($delete_stmt, "i", $showtime_id);
            
            if (!mysqli_stmt_execute($delete_stmt)) {
                throw new Exception("Seans silinirken bir hata oluştu: " . mysqli_stmt_error($delete_stmt));
            }
            
            if (mysqli_stmt_affected_rows($delete_stmt) === 0) {
                throw new Exception("Seans silinemedi.");
            }
            
            $verify_query = "SELECT COUNT(*) as count FROM showtimes WHERE id = ?";
            $verify_stmt = mysqli_prepare($conn, $verify_query);
            mysqli_stmt_bind_param($verify_stmt, "i", $showtime_id);
            mysqli_stmt_execute($verify_stmt);
            $verify_result = mysqli_stmt_get_result($verify_stmt);
            $verify_row = mysqli_fetch_assoc($verify_result);
            
            if ($verify_row['count'] > 0) {
                throw new Exception("Seans silme işlemi başarısız oldu.");
            }
            
            $db->commit();
            
            $_SESSION['success_message'] = "Seans başarıyla silindi: " . htmlspecialchars($showtime['movie_title']) . 
                                         " (" . date('d.m.Y H:i', strtotime($showtime['show_date'] . ' ' . $showtime['show_time'])) . ")";
            
            header('Location: showtimes.php?message=deleted');
            exit();
            
        } catch (Exception $e) {
            $db->rollBack();
            
            $_SESSION['error_message'] = $e->getMessage();
            
            header('Location: showtimes.php');
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Geçersiz seans ID'si.";
        header('Location: showtimes.php');
        exit();
    }
} else {
    $_SESSION['error_message'] = "Geçersiz istek.";
    header('Location: showtimes.php');
    exit();
}
?> 