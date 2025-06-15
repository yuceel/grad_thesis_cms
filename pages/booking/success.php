<?php
require_login();

$reservation_id = isset($_GET['reservation_id']) ? intval($_GET['reservation_id']) : 0;

$reservation = $db->select("
    SELECT r.*, m.title as movie_title, m.poster,
           st.start_time, sc.name as screen_name, sc.screen_type,
           GROUP_CONCAT(s.seat_number ORDER BY s.seat_number SEPARATOR ', ') as seats
    FROM reservations r
    JOIN showtimes st ON r.showtime_id = st.id
    JOIN movies m ON st.movie_id = m.id
    JOIN screens sc ON st.screen_id = sc.id
    JOIN reservation_seats rs ON r.id = rs.reservation_id
    JOIN seats s ON rs.seat_id = s.id
    WHERE r.id = ? AND r.user_id = ? AND r.status = 'confirmed'
    GROUP BY r.id",
    [$reservation_id, $_SESSION['user_id']]
);

if (!$reservation) {
    set_message('Rezervasyon bulunamadı.', 'danger');
    redirect('home');
}
$reservation = $reservation[0];

$qr_data = json_encode([
    'reservation_id' => $reservation_id,
    'movie' => $reservation['movie_title'],
    'showtime' => format_date($reservation['start_time'], 'd.m.Y H:i'),
    'seats' => $reservation['seats']
]);
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body text-center">
                <div class="mb-4">
                    <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                </div>
                <h2 class="card-title mb-4">Rezervasyonunuz Başarıyla Oluşturuldu!</h2>
                
                <div class="alert alert-info">
                    Rezervasyon detayları e-posta adresinize gönderilmiştir. 
                    Biletinizi göstermek için QR kodu kullanabilirsiniz.
                </div>

                <div class="row mt-4">
                    <div class="col-md-4">
                        <img src="<?php echo filter_var($reservation['poster'], FILTER_VALIDATE_URL) ? $reservation['poster'] : base_url('assets/images/movies/' . $reservation['poster']); ?>" 
                             class="img-fluid rounded" 
                             alt="<?php echo $reservation['movie_title']; ?>"
                             onerror="this.src='<?php echo base_url('assets/images/no-poster.jpg'); ?>'">
                    </div>
                    <div class="col-md-8 text-start">
                        <h3><?php echo $reservation['movie_title']; ?></h3>
                        <p>
                            <strong>Rezervasyon No:</strong> <?php echo str_pad($reservation_id, 8, '0', STR_PAD_LEFT); ?><br>
                            <strong>Gösterim:</strong> <?php echo format_date($reservation['start_time'], 'd.m.Y H:i'); ?><br>
                            <strong>Salon:</strong> <?php echo $reservation['screen_name']; ?> 
                            (<?php echo ucfirst($reservation['screen_type']); ?>)<br>
                            <strong>Koltuklar:</strong> <?php echo $reservation['seats']; ?><br>
                            <strong>Toplam Tutar:</strong> <?php echo number_format($reservation['total_amount'], 2); ?> ₺
                        </p>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6 offset-md-3">
                        <div id="qrcode" class="mb-3"></div>
                        <p class="text-muted">QR Kodu görevliye gösteriniz.</p>
                    </div>
                </div>

                <div class="mt-4">
                    <a href="<?php echo page_url('home'); ?>" class="btn btn-primary">
                        Ana Sayfaya Dön
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include QR Code library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    new QRCode(document.getElementById("qrcode"), {
        text: <?php echo json_encode($qr_data); ?>,
        width: 200,
        height: 200,
        colorDark: "#000000",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });
});
</script> 