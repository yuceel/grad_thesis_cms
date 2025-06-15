<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

$pageTitle = "Yeni Film Ekle";
include ROOT_PATH . '/includes/header.php';

$genres_query = "SELECT DISTINCT genre FROM movies ORDER BY genre";
$genres = $db->select($genres_query);

$default_genres = [
    'Aksiyon',
    'Macera',
    'Animasyon',
    'Bilim Kurgu',
    'Biyografi',
    'Belgesel',
    'Dram',
    'Fantastik',
    'Gerilim',
    'Komedi',
    'Korku',
    'Macera',
    'Müzikal',
    'Polisiye',
    'Romantik',
    'Suç',
    'Tarih',
    'Western'
];

$all_genres = array_unique(array_merge(
    array_column($genres, 'genre'),
    $default_genres
));
sort($all_genres); 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $duration = filter_var($_POST['duration'], FILTER_VALIDATE_INT);
    $release_date = trim($_POST['release_date']);
    $genre = trim($_POST['genre']);
    
    $status = isset($_POST['status']) ? strtolower(trim($_POST['status'])) : '';
    $allowed_statuses = ['active', 'upcoming', 'inactive'];
    
    if (!in_array($status, $allowed_statuses, true)) {
        $error = "Geçersiz film durumu. Lütfen 'Aktif', 'Yakında' veya 'Pasif' seçeneklerinden birini seçin.";
    }
    
    $poster_url = trim($_POST['poster_url']);
    
    if (empty($title) || empty($description) || empty($duration) || empty($release_date) || empty($genre) || empty($status)) {
        $error = "Lütfen tüm zorunlu alanları doldurun.";
    }
    elseif ($duration < 1) {
        $error = "Film süresi en az 1 dakika olmalıdır.";
    }
    elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $release_date)) {
        $error = "Geçersiz tarih formatı.";
    }
    elseif (!empty($poster_url) && !filter_var($poster_url, FILTER_VALIDATE_URL)) {
        $error = "Geçersiz poster URL'si.";
    }
    
    if (!isset($error)) {
        try {
            $db->beginTransaction();
            
            if (!in_array($status, $allowed_statuses, true)) {
                throw new Exception("Geçersiz film durumu: " . $status);
            }

            $query = "INSERT INTO movies (title, description, duration, release_date, status, genre, poster) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $query);
            
            if (!$stmt) {
                throw new Exception("Sorgu hazırlama hatası: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($stmt, "ssissss", 
                $title, $description, $duration, $release_date, $status, $genre, $poster_url);
            
            if (!mysqli_stmt_execute($stmt)) {
                $error_msg = mysqli_stmt_error($stmt);
                throw new Exception("Sorgu çalıştırma hatası: " . $error_msg);
            }
            
            $insert_id = mysqli_stmt_insert_id($stmt);
            
            if (!$insert_id) {
                throw new Exception("Film eklenirken bir hata oluştu: Kayıt ID'si alınamadı.");
            }
            
            $check_query = "SELECT id FROM movies WHERE id = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, "i", $insert_id);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($result) === 0) {
                throw new Exception("Film eklenirken bir hata oluştu: Kayıt doğrulanamadı.");
            }
            
            $db->commit();
            
            header('Location: movies.php?message=added');
            exit();
            
        } catch (Exception $e) {
            $db->rollBack();
            
            $error = "Film eklenirken bir hata oluştu: " . $e->getMessage();
            error_log("Film ekleme hatası: " . $e->getMessage());
            

        }
    }
}
?>

<div class="container-fluid">
    <div class="row">

        <?php include __DIR__ . '/sidebar.php'; ?>


        <main role="main" class="col-md-10 ml-sm-auto px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Yeni Film Ekle</h1>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="title">Film Adı *</label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                </div>

                                <div class="form-group">
                                    <label for="description">Açıklama *</label>
                                    <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="duration">Süre (Dakika) *</label>
                                    <input type="number" class="form-control" id="duration" name="duration" required min="1">
                                </div>

                                <div class="form-group">
                                    <label for="release_date">Yayın Tarihi *</label>
                                    <input type="date" class="form-control" id="release_date" name="release_date" required>
                                </div>

                                <div class="form-group">
                                    <label for="status">Durum *</label>
                                    <select class="form-control" id="status" name="status" required>
                                        <option value="active">Aktif</option>
                                        <option value="upcoming">Yakında</option>
                                        <option value="inactive">Pasif</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="genre">Tür *</label>
                                    <select class="form-control" id="genre" name="genre" required>
                                        <option value="">Tür Seçin</option>
                                        <?php foreach ($all_genres as $genre): ?>
                                            <option value="<?php echo htmlspecialchars($genre); ?>">
                                                <?php echo htmlspecialchars($genre); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="poster_url">Poster URL</label>
                                    <input type="url" class="form-control" id="poster_url" name="poster_url">
                                    <small class="form-text text-muted">Film posteri için resim URL'si</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-primary">Film Ekle</button>
                            <a href="movies.php" class="btn btn-secondary">İptal</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const genreSelect = document.getElementById('genre');
    const newGenreInput = document.getElementById('new_genre');
    const addGenreBtn = document.getElementById('addGenreBtn');

    function addNewGenre() {
        const newGenre = newGenreInput.value.trim();
        if (newGenre) {
            const exists = Array.from(genreSelect.options).some(option => 
                option.value.toLowerCase() === newGenre.toLowerCase()
            );

            if (!exists) {
                const option = new Option(newGenre, newGenre);
                genreSelect.add(option);
                genreSelect.value = newGenre; 
                newGenreInput.value = ''; 
            } else {
                alert('Bu tür zaten listede mevcut!');
            }
        }
    }

    newGenreInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addNewGenre();
        }
    });

    addGenreBtn.addEventListener('click', addNewGenre);
});
</script>

<?php 