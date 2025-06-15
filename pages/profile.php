<?php
require_login();

$user = $db->select("
    SELECT u.*, 
           COUNT(DISTINCT r.id) as total_reservations,
           COUNT(DISTINCT CASE WHEN r.status = 'confirmed' THEN r.id END) as confirmed_reservations,
           COUNT(DISTINCT CASE WHEN r.status = 'cancelled' THEN r.id END) as cancelled_reservations,
           SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) as total_spent
    FROM users u
    LEFT JOIN reservations r ON u.id = r.user_id
    LEFT JOIN payments p ON r.id = p.reservation_id
    WHERE u.id = ?
    GROUP BY u.id, u.email, u.password, u.first_name, u.last_name, u.role, u.status, u.created_at",
    [$_SESSION['user_id']]
);

if (!$user) {
    set_message('Kullanıcı bilgileri bulunamadı.', 'danger');
    redirect('home');
}
$user = $user[0];

$reservations = $db->select("
    SELECT r.id, r.status, r.created_at,
           m.title as movie_title, m.poster,
           st.start_time, sc.name as screen_name,
           GROUP_CONCAT(s.seat_number ORDER BY s.seat_number SEPARATOR ', ') as seats,
           MAX(p.status) as payment_status, 
           MAX(p.amount) as payment_amount
    FROM reservations r
    JOIN showtimes st ON r.showtime_id = st.id
    JOIN movies m ON st.movie_id = m.id
    JOIN screens sc ON st.screen_id = sc.id
    JOIN reservation_seats rs ON r.id = rs.reservation_id
    JOIN seats s ON rs.seat_id = s.id
    LEFT JOIN payments p ON r.id = p.reservation_id
    WHERE r.user_id = ?
    GROUP BY r.id, r.status, r.created_at, m.title, m.poster, st.start_time, sc.name
    ORDER BY r.created_at DESC
    LIMIT 5",
    [$_SESSION['user_id']]
);
?>

<div class="container py-4">
    <div class="row">
     
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar-placeholder mb-3">
                            <i class="fas fa-user-circle fa-5x text-primary"></i>
                        </div>
                        <h4 class="card-title mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                        <p class="text-muted mb-3"><?php echo htmlspecialchars($user['email']); ?></p>
                        <span class="badge <?php echo $user['status'] === 'active' ? 'bg-success' : 'bg-warning'; ?> text-white">
                            <?php echo $user['status'] === 'active' ? 'Aktif' : 'Pasif'; ?>
                        </span>
                    </div>
                    
                    <hr>
                    
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h5 class="mb-1"><?php echo $user['total_reservations']; ?></h5>
                            <small class="text-muted">Toplam Rezervasyon</small>
                        </div>
                        <div class="col-6 mb-3">
                            <h5 class="mb-1"><?php echo number_format($user['total_spent'], 2); ?> ₺</h5>
                            <small class="text-muted">Toplam Harcama</small>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-grid gap-2">
                        <a href="<?php echo page_url('profile_edit'); ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-2"></i>Profili Düzenle
                        </a>
                        <a href="<?php echo page_url('profile_password'); ?>" class="btn btn-outline-primary">
                            <i class="fas fa-key me-2"></i>Şifre Değiştir
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Son Rezervasyonlar</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($reservations)): ?>
                        <div class="text-center py-4">
                            <p class="text-muted mb-0">Henüz rezervasyon yapmamışsınız.</p>
                            <a href="<?php echo page_url('movies'); ?>" class="btn btn-primary mt-3">
                                Filmleri Görüntüle
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($reservations as $reservation): ?>
                                <div class="list-group-item">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <img src="<?php echo filter_var($reservation['poster'], FILTER_VALIDATE_URL) ? $reservation['poster'] : base_url('assets/images/movies/' . $reservation['poster']); ?>" 
                                                 class="rounded" 
                                                 alt="<?php echo htmlspecialchars($reservation['movie_title']); ?>"
                                                 style="width: 80px;"
                                                 onerror="this.src='<?php echo base_url('assets/images/no-poster.jpg'); ?>'">
                                        </div>
                                        <div class="col">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($reservation['movie_title']); ?></h6>
                                            <p class="mb-1 small text-muted">
                                                <i class="fas fa-calendar-alt me-1"></i>
                                                <?php echo format_date($reservation['start_time'], 'd.m.Y H:i'); ?>
                                                <br>
                                                <i class="fas fa-door-open me-1"></i>
                                                <?php echo htmlspecialchars($reservation['screen_name']); ?>
                                                <br>
                                                <i class="fas fa-chair me-1"></i>
                                                <?php echo htmlspecialchars($reservation['seats']); ?>
                                            </p>
                                        </div>
                                        <div class="col-auto text-end">
                                            <span class="badge <?php 
                                                echo match($reservation['status']) {
                                                    'confirmed' => 'bg-success',
                                                    'cancelled' => 'bg-danger',
                                                    'pending' => 'bg-warning',
                                                    default => 'bg-secondary'
                                                };
                                            ?> text-white mb-2">
                                                <?php 
                                                echo match($reservation['status']) {
                                                    'confirmed' => 'Onaylandı',
                                                    'cancelled' => 'İptal Edildi',
                                                    'pending' => 'Bekliyor',
                                                    default => 'Bilinmiyor'
                                                };
                                                ?>
                                            </span>
                                            <br>
                                            <span class="text-muted small">
                                                <?php echo number_format($reservation['payment_amount'], 2); ?> ₺
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($user['total_reservations'] > 5): ?>
                            <div class="card-footer bg-white text-center">
                                <a href="<?php echo page_url('reservations'); ?>" class="btn btn-link">
                                    Tüm Rezervasyonları Görüntüle
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-placeholder {
    width: 100px;
    height: 100px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f8f9fa;
    border-radius: 50%;
}
</style> 