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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['movie_id'])) {
    $movie_id = filter_var($_POST['movie_id'], FILTER_VALIDATE_INT);
    
    if ($movie_id) {
        try {
            $conn = $db->getConnection();
            
            $db->beginTransaction();
            
            $check_query = "SELECT COUNT(*) as count FROM showtimes WHERE movie_id = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            
            if (!$check_stmt) {
                throw new Exception("Seans kontrolü için sorgu hazırlama hatası: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($check_stmt, "i", $movie_id);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);
            $row = mysqli_fetch_assoc($result);
            
            if ($row['count'] > 0) {
                throw new Exception("Bu filme ait seanslar bulunduğu için silinemez.");
            }
            
            $check_movie_query = "SELECT id, title FROM movies WHERE id = ?";
            $check_movie_stmt = mysqli_prepare($conn, $check_movie_query);
            
            if (!$check_movie_stmt) {
                throw new Exception("Film kontrolü için sorgu hazırlama hatası: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($check_movie_stmt, "i", $movie_id);
            mysqli_stmt_execute($check_movie_stmt);
            $movie_result = mysqli_stmt_get_result($check_movie_stmt);
            
            if (mysqli_num_rows($movie_result) === 0) {
                throw new Exception("Film bulunamadı.");
            }
            
            $movie = mysqli_fetch_assoc($movie_result);
            
            $delete_query = "DELETE FROM movies WHERE id = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_query);
            
            if (!$delete_stmt) {
                throw new Exception("Silme sorgusu hazırlama hatası: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($delete_stmt, "i", $movie_id);
            
            if (!mysqli_stmt_execute($delete_stmt)) {
                throw new Exception("Film silinirken bir hata oluştu: " . mysqli_stmt_error($delete_stmt));
            }
            
            if (mysqli_stmt_affected_rows($delete_stmt) === 0) {
                throw new Exception("Film silinemedi.");
            }
            
            $verify_query = "SELECT COUNT(*) as count FROM movies WHERE id = ?";
            $verify_stmt = mysqli_prepare($conn, $verify_query);
            mysqli_stmt_bind_param($verify_stmt, "i", $movie_id);
            mysqli_stmt_execute($verify_stmt);
            $verify_result = mysqli_stmt_get_result($verify_stmt);
            $verify_row = mysqli_fetch_assoc($verify_result);
            
            if ($verify_row['count'] > 0) {
                throw new Exception("Film silme işlemi başarısız oldu.");
            }
            
            $db->commit();
            
            $_SESSION['success_message'] = "Film başarıyla silindi: " . htmlspecialchars($movie['title']);
            
            header('Location: movies.php?message=deleted');
            exit();
            
        } catch (Exception $e) {
            $db->rollBack();
            
            $_SESSION['error_message'] = $e->getMessage();
            
            header('Location: movies.php');
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Geçersiz film ID'si.";
        header('Location: movies.php');
        exit();
    }
} else {
    $_SESSION['error_message'] = "Geçersiz istek.";
    header('Location: movies.php');
    exit();
}
?> 