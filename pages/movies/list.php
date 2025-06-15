<?php
$search = get_get('search', '');
$genre = get_get('genre', '');
$status = get_get('status', 'active');
$sort = get_get('sort', 'release_date');
$order = get_get('order', 'DESC');

$is_home = !isset($_GET['page']) || $_GET['page'] === 'home';

$query = "SELECT * FROM movies WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (title LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($genre) {
    $query .= " AND genre = ?";
    $params[] = $genre;
}

if ($status) {
    $query .= " AND status = ?";
    $params[] = $status;
}

$allowed_sorts = ['title', 'release_date', 'rating'];
$allowed_orders = ['ASC', 'DESC'];
$sort = in_array($sort, $allowed_sorts) ? $sort : 'release_date';
$order = in_array(strtoupper($order), $allowed_orders) ? strtoupper($order) : 'DESC';
$query .= " ORDER BY $sort $order";

if ($is_home) {
    $query .= " LIMIT 8";
}

$movies = $db->select($query, $params);

$genres = $db->select("SELECT DISTINCT genre FROM movies ORDER BY genre");
?>

<?php if ($is_home): ?>

    <div class="row mb-5">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body">
                    <h1 class="card-title mb-4">Cinema Management System</h1>
                    <p class="card-text lead">
                        Cinema Management System, modern sinema salonları için geliştirilmiş kapsamlı bir yönetim sistemidir. 
                        Film gösterimlerini, salon yönetimini, bilet satışlarını ve müşteri ilişkilerini tek bir platformda 
                        birleştirerek sinema işletmenizi verimli bir şekilde yönetmenizi sağlar. Kullanıcı dostu arayüzü ve 
                        güçlü özellikleriyle sinema deneyimini hem işletme hem de müşteriler için daha keyifli hale getirir.
                    </p>
                </div>
            </div>
        </div>
    </div>


    <div class="row mb-4">
        <div class="col-12">
            <h2>Filmler</h2>
        </div>
    </div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
        <?php foreach ($movies as $movie): ?>
            <div class="col">
                <div class="card h-100 movie-card">
                    <img src="<?php echo filter_var($movie['poster'], FILTER_VALIDATE_URL) ? $movie['poster'] : base_url('assets/images/movies/' . $movie['poster']); ?>" 
                         class="card-img-top" 
                         alt="<?php echo $movie['title']; ?>"
                         onerror="this.src='<?php echo base_url('assets/images/no-poster.jpg'); ?>'">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $movie['title']; ?></h5>
                        <p class="card-text">
                            <small class="text-muted">
                                <?php echo format_date($movie['release_date'], 'd.m.Y'); ?> | 
                                <?php echo $movie['duration']; ?> dk
                            </small>
                        </p>
                        <p class="card-text">
                            <span class="badge bg-primary"><?php echo $movie['genre']; ?></span>
                            <?php if (!empty($movie['rating']) && is_numeric($movie['rating'])): ?>
                                <span class="badge bg-warning text-dark">
                                    ⭐ <?php echo number_format((float)$movie['rating'], 1); ?>
                                </span>
                            <?php endif; ?>
                        </p>
                        <p class="card-text movie-description"><?php echo nl2br(htmlspecialchars(fix_line_breaks($movie['description']))); ?></p>
                        <a href="<?php echo page_url('movie', ['id' => $movie['id']]); ?>" 
                           class="btn btn-primary">Detaylar</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php else: ?>

    <div class="row mb-4">
        <div class="col-12">
            <h1 class="mb-4">Filmler</h1>
            

            <form method="get" class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Film ara..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="genre">
                                <option value="">Tüm Türler</option>
                                <?php foreach ($genres as $g): ?>
                                    <option value="<?php echo $g['genre']; ?>" 
                                            <?php echo $genre === $g['genre'] ? 'selected' : ''; ?>>
                                        <?php echo $g['genre']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="status">
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Vizyonda</option>
                                <option value="upcoming" <?php echo $status === 'upcoming' ? 'selected' : ''; ?>>Yakında</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="sort">
                                <option value="release_date" <?php echo $sort === 'release_date' ? 'selected' : ''; ?>>Tarihe Göre</option>
                                <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Puana Göre</option>
                                <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>Ada Göre</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="order">
                                <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Azalan</option>
                                <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Artan</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Filtrele</button>
                            <a href="<?php echo page_url('home'); ?>" class="btn btn-secondary">Sıfırla</a>
                        </div>
                    </div>
                </div>
            </form>

            <?php if (empty($movies)): ?>
                <div class="alert alert-info">
                    Arama kriterlerinize uygun film bulunamadı.
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
                    <?php foreach ($movies as $movie): ?>
                        <div class="col">
                            <div class="card h-100 movie-card">
                                <img src="<?php echo filter_var($movie['poster'], FILTER_VALIDATE_URL) ? $movie['poster'] : base_url('assets/images/movies/' . $movie['poster']); ?>" 
                                     class="card-img-top" 
                                     alt="<?php echo $movie['title']; ?>"
                                     onerror="this.src='<?php echo base_url('assets/images/no-poster.jpg'); ?>'">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo $movie['title']; ?></h5>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            <?php echo format_date($movie['release_date'], 'd.m.Y'); ?> | 
                                            <?php echo $movie['duration']; ?> dk
                                        </small>
                                    </p>
                                    <p class="card-text">
                                        <span class="badge bg-primary"><?php echo $movie['genre']; ?></span>
                                        <?php if (!empty($movie['rating']) && is_numeric($movie['rating'])): ?>
                                            <span class="badge bg-warning text-dark">
                                                ⭐ <?php echo number_format((float)$movie['rating'], 1); ?>
                                            </span>
                                        <?php endif; ?>
                                    </p>
                                    <p class="card-text movie-description"><?php echo nl2br(htmlspecialchars(fix_line_breaks($movie['description']))); ?></p>
                                    <a href="<?php echo page_url('movie', ['id' => $movie['id']]); ?>" 
                                       class="btn btn-primary">Detaylar</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?> 