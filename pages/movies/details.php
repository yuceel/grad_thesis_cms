<?php
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$movie = $db->select("SELECT * FROM movies WHERE id = ?", [$id]);

if (!$movie) {
    set_message('Film bulunamadı.', 'danger');
    redirect('home');
}
$movie = $movie[0];

$showtimes = $db->select("
    SELECT st.*, sc.name as screen_name, sc.screen_type,
           (SELECT COUNT(*) FROM seats s 
            WHERE s.screen_id = sc.id 
            AND s.status = 'active'
            AND s.id NOT IN (
                SELECT seat_id FROM reservation_seats rs 
                JOIN reservations r ON rs.reservation_id = r.id 
                WHERE r.showtime_id = st.id 
                AND r.status IN ('pending', 'confirmed')
            )) as available_seats,
           CASE 
               WHEN st.start_time < NOW() THEN 'past'
               WHEN st.start_time > NOW() THEN 'future'
               ELSE 'current'
           END as time_status
    FROM showtimes st 
    JOIN screens sc ON st.screen_id = sc.id 
    WHERE st.movie_id = ? 
    AND st.status = 'active'
    ORDER BY st.start_time DESC", 
    [$id]
);

$showtimes_by_date = [];
foreach ($showtimes as $show) {
    $date = date('Y-m-d', strtotime($show['start_time']));
    if (!isset($showtimes_by_date[$date])) {
        $showtimes_by_date[$date] = [];
    }
    $showtimes_by_date[$date][] = $show;
}
?>

<div class="row">
    <div class="col-md-4">
        <img src="<?php echo filter_var($movie['poster'], FILTER_VALIDATE_URL) ? $movie['poster'] : base_url('assets/images/movies/' . $movie['poster']); ?>" 
             class="img-fluid rounded mb-3" 
             alt="<?php echo $movie['title']; ?>"
             onerror="this.src='<?php echo base_url('assets/images/no-poster.jpg'); ?>'">
        <?php if (!empty($movie['trailer_url'])): ?>
            <div class="ratio ratio-16x9 mb-3">
                <iframe src="<?php echo htmlspecialchars($movie['trailer_url']); ?>" 
                        allowfullscreen 
                        class="rounded"></iframe>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-md-8">
        <h1 class="mb-3"><?php echo $movie['title']; ?></h1>
        
        <div class="mb-4">
            <span class="badge bg-primary me-2"><?php echo $movie['genre']; ?></span>
            <?php if (!empty($movie['rating']) && is_numeric($movie['rating'])): ?>
                <span class="badge bg-warning text-dark me-2">
                    ⭐ <?php echo number_format((float)$movie['rating'], 1); ?>
                </span>
            <?php endif; ?>
            <span class="badge bg-info me-2"><?php echo $movie['duration']; ?> dk</span>
            <span class="badge bg-secondary">
                <?php echo format_date($movie['release_date'], 'd.m.Y'); ?>
            </span>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Film Hakkında</h5>
                <p class="card-text"><?php echo nl2br(htmlspecialchars(fix_line_breaks($movie['description']))); ?></p>
            </div>
        </div>

        <h2 class="mb-4">Gösterimler</h2>
        <?php if (empty($showtimes_by_date)): ?>
            <div class="alert alert-info">
                Bu film için yakın zamanda gösterim bulunmuyor.
            </div>
        <?php else: ?>
            <?php foreach ($showtimes_by_date as $date => $shows): ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <?php echo format_date($date, 'd.m.Y (l)'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($shows as $show): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card h-100 <?php echo $show['time_status'] === 'past' ? 'border-secondary bg-light' : ''; ?>">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <?php echo format_date($show['start_time'], 'H:i'); ?>
                                                <?php if ($show['time_status'] === 'past'): ?>
                                                    <small class="text-muted">(Geçmiş)</small>
                                                <?php endif; ?>
                                            </h6>
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    <?php echo $show['screen_name']; ?> 
                                                    (<?php echo ucfirst($show['screen_type']); ?>)
                                                </small>
                                            </p>
                                            <p class="card-text">
                                                <strong><?php echo number_format($show['price'], 2); ?> ₺</strong>
                                            </p>
                                            <p class="card-text">
                                                <small>
                                                    <?php echo $show['available_seats']; ?> koltuk müsait
                                                </small>
                                            </p>
                                            <?php if ($show['time_status'] === 'past'): ?>
                                                <button class="btn btn-secondary btn-sm w-100" disabled>
                                                    Seans Gerçekleştirildi
                                                </button>
                                            <?php elseif ($show['available_seats'] > 0): ?>
                                                <?php if (is_logged_in()): ?>
                                                    <a href="<?php echo page_url('booking', ['showtime_id' => $show['id']]); ?>" 
                                                       class="btn btn-primary btn-sm w-100">
                                                        Rezervasyon Yap
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?php echo page_url('login'); ?>" 
                                                       class="btn btn-outline-primary btn-sm w-100">
                                                        Giriş Yap
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <button class="btn btn-secondary btn-sm w-100" disabled>
                                                    Dolu
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div> 