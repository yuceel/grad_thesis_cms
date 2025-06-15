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


if (!isset($_GET['id'])) {
    header('Location: movies.php');
    exit();
}

$movie_id = (int)$_GET['id'];

$query = "SELECT * FROM movies WHERE id = ?";
$result = $db->select($query, [$movie_id]);

if (empty($result)) {
    header('Location: movies.php');
    exit();
}

$movie = $result[0];
$pageTitle = "Film Düzenle: " . ($movie['title'] ?? 'Bilinmeyen');
include ROOT_PATH . '/includes/header.php';


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $duration = (int)$_POST['duration'];
    $release_date = trim($_POST['release_date']);
    $genre = trim($_POST['genre']);
    $status = trim($_POST['status']);
    $poster_url = trim($_POST['poster_url']);

    $poster = $poster_url; 

    $data = [
        'title' => $title,
        'description' => $description,
        'duration' => $duration,
        'release_date' => $release_date,
        'genre' => $genre,
        'poster' => $poster,
        'status' => $status
    ];
    
    try {
        $db->beginTransaction();
        $affected = $db->update('movies', $data, 'id = ?', [$movie_id]);
        if ($affected > 0) {
            $db->commit();
            header('Location: movies.php?message=updated');
            exit();
        } else {
            $db->rollBack();
            $error = "Film güncellenirken bir hata oluştu.";
        }
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Film güncellenirken bir hata oluştu: " . $e->getMessage();
    }
}
?>

<div class="container-fluid">
    <div class="row">

        <?php include 'sidebar.php'; ?>


        <main role="main" class="col-md-10 ml-sm-auto px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Film Düzenle: <?php echo htmlspecialchars($movie['title'] ?? 'Bilinmeyen'); ?></h1>
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
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($movie['title'] ?? ''); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="description">Açıklama *</label>
                                    <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars(fix_line_breaks($movie['description'] ?? '')); ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="duration">Süre (Dakika) *</label>
                                    <input type="number" class="form-control" id="duration" name="duration" value="<?php echo $movie['duration'] ?? ''; ?>" required min="1">
                                </div>

                                <div class="form-group">
                                    <label for="release_date">Yayın Tarihi *</label>
                                    <input type="date" class="form-control" id="release_date" name="release_date" value="<?php echo $movie['release_date'] ?? ''; ?>" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="genre">Tür *</label>
                                    <input type="text" class="form-control" id="genre" name="genre" value="<?php echo htmlspecialchars($movie['genre'] ?? ''); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="status">Durum *</label>
                                    <select class="form-control" id="status" name="status" required>
                                        <option value="active" <?php echo ($movie['status'] ?? '') == 'active' ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="upcoming" <?php echo ($movie['status'] ?? '') == 'upcoming' ? 'selected' : ''; ?>>Yakında</option>
                                        <option value="inactive" <?php echo ($movie['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Pasif</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="poster_url">Poster URL</label>
                                    <input type="url" class="form-control" id="poster_url" name="poster_url" value="<?php echo htmlspecialchars($movie['poster'] ?? ''); ?>">
                                    <small class="form-text text-muted">Film posteri için resim URL'si</small>
                                    <?php if (!empty($movie['poster'])): ?>
                                        <div class="mt-2">
                                            <img src="<?php echo htmlspecialchars($movie['poster']); ?>" alt="Mevcut Poster" style="max-width: 200px; height: auto;">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                            <a href="movies.php" class="btn btn-secondary">İptal</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?> 