<?php
require_login();

$showtime_id = isset($_GET['showtime_id']) ? intval($_GET['showtime_id']) : 0;

$showtime = $db->select("
    SELECT st.*, m.title as movie_title, m.duration, m.poster,
           sc.name as screen_name, sc.screen_type, sc.capacity
    FROM showtimes st
    JOIN movies m ON st.movie_id = m.id
    JOIN screens sc ON st.screen_id = sc.id
    WHERE st.id = ? AND st.status = 'active' AND st.start_time > NOW()",
    [$showtime_id]
);

if (!$showtime) {
    set_message('Gösterim bulunamadı.', 'danger');
    redirect('home');
}
$showtime = $showtime[0];

$seats = $db->select("
    SELECT s.id, s.screen_id, s.seat_number, s.status,
           CASE 
               WHEN s.seat_number = '1' THEN 'vip'
               WHEN CAST(REGEXP_REPLACE(s.seat_number, '[^0-9]', '') AS UNSIGNED) <= 18 THEN 'vip'
               WHEN CAST(REGEXP_REPLACE(s.seat_number, '[^0-9]', '') AS UNSIGNED) <= 36 THEN 'premium'
               ELSE 'standard'
           END as seat_type,
           CASE WHEN rs.seat_id IS NOT NULL THEN 'reserved' ELSE 'available' END as status
    FROM seats s
    LEFT JOIN (
        SELECT DISTINCT rs.seat_id 
        FROM reservation_seats rs
        JOIN reservations r ON rs.reservation_id = r.id
        WHERE r.showtime_id = ? AND r.status IN ('pending', 'confirmed')
    ) rs ON s.id = rs.seat_id
    WHERE s.screen_id = ? AND s.status = 'active'
    ORDER BY CAST(REGEXP_REPLACE(s.seat_number, '[^0-9]', '') AS UNSIGNED),
             REGEXP_REPLACE(s.seat_number, '[0-9]', '')",
    [$showtime_id, $showtime['screen_id']]
);

error_log('Seats data: ' . print_r($seats, true));

$seats_by_row = [];
foreach ($seats as $seat) {
    $row = preg_replace('/[0-9]/', '', $seat['seat_number']);
    if (!isset($seats_by_row[$row])) {
        $seats_by_row[$row] = [];
    }
    $seats_by_row[$row][] = $seat;
}

error_log('Seats by row: ' . print_r($seats_by_row, true));

$seat_prices = [
    'standard' => floatval($showtime['price']),
    'premium' => floatval($showtime['price']) * 1.5,
    'vip' => floatval($showtime['price']) * 2
];
?>

<div class="row">
    <div class="col-md-8">
        <!-- Showtime Info -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <img src="<?php echo filter_var($showtime['poster'], FILTER_VALIDATE_URL) ? $showtime['poster'] : base_url('assets/images/movies/' . $showtime['poster']); ?>" 
                             class="img-fluid rounded" 
                             alt="<?php echo $showtime['movie_title']; ?>"
                             onerror="this.src='<?php echo base_url('assets/images/no-poster.jpg'); ?>'">
                    </div>
                    <div class="col-md-9">
                        <h2 class="card-title"><?php echo $showtime['movie_title']; ?></h2>
                        <p class="card-text">
                            <strong>Gösterim:</strong> <?php echo format_date($showtime['start_time'], 'd.m.Y H:i'); ?><br>
                            <strong>Salon:</strong> <?php echo $showtime['screen_name']; ?> 
                            (<?php echo ucfirst($showtime['screen_type']); ?>)<br>
                            <strong>Süre:</strong> <?php echo $showtime['duration']; ?> dk
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Screen and Seats -->
        <div class="card mb-4">
            <div class="card-body">
                <h3 class="card-title mb-4">Koltuk Seçimi</h3>
                
                <!-- Screen -->
                <div class="text-center mb-4">
                    <div class="screen-container">
                        <div class="screen">PERDE</div>
                    </div>
                </div>

                <!-- Seat Legend -->
                <div class="seat-legend mb-4">
                    <div class="d-flex justify-content-center gap-4">
                        <div class="seat-legend-item">
                            <div class="seat standard available"></div>
                            <span>Standart (<?php echo number_format($seat_prices['standard'], 2); ?> ₺)</span>
                        </div>
                        <div class="seat-legend-item">
                            <div class="seat premium available"></div>
                            <span>Premium (<?php echo number_format($seat_prices['premium'], 2); ?> ₺)</span>
                        </div>
                        <div class="seat-legend-item">
                            <div class="seat vip available"></div>
                            <span>VIP (<?php echo number_format($seat_prices['vip'], 2); ?> ₺)</span>
                        </div>
                        <div class="seat-legend-item">
                            <div class="seat reserved"></div>
                            <span>Dolu</span>
                        </div>
                    </div>
                </div>

                <!-- Seats -->
                <form id="bookingForm" method="post" action="<?php echo page_url('booking', ['action' => 'confirm']); ?>" onsubmit="return validateForm()">
                    <input type="hidden" name="showtime_id" value="<?php echo $showtime_id; ?>">
                    <input type="hidden" name="selected_seats" id="selectedSeats" value="">
                    
                    <?php foreach ($seats_by_row as $row => $row_seats): ?>
                        <div class="seat-row mb-2">
                            <div class="row-label"><?php echo $row; ?></div>
                            <div class="seats-container">
                                <?php foreach ($row_seats as $seat): ?>
                                    <div class="seat-wrapper">
                                        <input type="checkbox" 
                                               class="seat-checkbox" 
                                               id="seat_<?php echo $seat['id']; ?>"
                                               name="seats[]" 
                                               value="<?php echo $seat['id']; ?>"
                                               data-price="<?php echo $seat_prices[$seat['seat_type']]; ?>"
                                               data-seat-number="<?php echo $seat['seat_number']; ?>"
                                               data-seat-type="<?php echo $seat['seat_type']; ?>"
                                               <?php echo $seat['status'] === 'reserved' ? 'disabled' : ''; ?>>
                                        <label for="seat_<?php echo $seat['id']; ?>" 
                                               class="seat <?php echo $seat['seat_type']; ?> <?php echo $seat['status']; ?>"
                                               title="<?php echo $seat['seat_number']; ?>">
                                            <?php echo preg_replace('/[^0-9]/', '', $seat['seat_number']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Booking Summary -->
        <div class="card">
            <div class="card-body">
                <h3 class="card-title mb-4">Rezervasyon Özeti</h3>
                
                <div id="bookingSummary">
                    <p class="text-muted">Lütfen koltuk seçiniz.</p>
                </div>

                <hr>

                <div class="d-grid gap-2">
                    <button type="submit" form="bookingForm" class="btn btn-primary" disabled id="confirmButton">
                        Rezervasyonu Onayla
                    </button>
                    <a href="<?php echo page_url('movie', ['id' => $showtime['movie_id']]); ?>" 
                       class="btn btn-outline-secondary">
                        Geri Dön
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let selectedSeats = new Set();

function validateForm() {
    console.log('Form validation started');
    console.log('Selected seats size:', selectedSeats.size);
    console.log('Selected seats value:', document.getElementById('selectedSeats').value);
    
    if (selectedSeats.size === 0) {
        alert('Lütfen en az bir koltuk seçiniz.');
        return false;
    }
    
    const formData = new FormData(document.getElementById('bookingForm'));
    console.log('Form data:');
    for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }
    
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('bookingForm');
    const checkboxes = form.querySelectorAll('.seat-checkbox');
    const summary = document.getElementById('bookingSummary');
    const confirmButton = document.getElementById('confirmButton');
    const selectedSeatsInput = document.getElementById('selectedSeats');
    
    function updateSummary() {
        const seats = Array.from(selectedSeats);
        console.log('Updating summary with seats:', seats);
        
        if (seats.length === 0) {
            summary.innerHTML = '<p class="text-muted">Lütfen koltuk seçiniz.</p>';
            confirmButton.disabled = true;
            selectedSeatsInput.value = '';
            return;
        }
        
        let total = 0;
        let html = '<div class="selected-seats">';
        seats.forEach(seatId => {
            const checkbox = document.querySelector(`input[value="${seatId}"]`);
            const seatType = checkbox.dataset.seatType;
            const price = parseFloat(checkbox.dataset.price);
            const seatNumber = checkbox.dataset.seatNumber;
            total += price;
            
            html += `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>${seatNumber} (${seatType.toUpperCase()})</span>
                    <span>${price.toFixed(2)} ₺</span>
                </div>
            `;
        });
        
        html += `
            <hr>
            <div class="d-flex justify-content-between align-items-center">
                <strong>Toplam</strong>
                <strong>${total.toFixed(2)} ₺</strong>
            </div>
        </div>`;
        
        summary.innerHTML = html;
        confirmButton.disabled = false;
        
        const seatsArray = Array.from(selectedSeats);
        selectedSeatsInput.value = JSON.stringify(seatsArray);
        console.log('Updated selected seats input:', selectedSeatsInput.value);
    }
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            console.log('Checkbox changed:', this.value, this.checked);
            if (this.checked) {
                selectedSeats.add(this.value);
            } else {
                selectedSeats.delete(this.value);
            }
            updateSummary();
        });
    });
    
    form.addEventListener('change', function(e) {
        if (e.target.type === 'checkbox' && e.target.checked && selectedSeats.size > 6) {
            e.target.checked = false;
            selectedSeats.delete(e.target.value);
            updateSummary();
            alert('En fazla 6 koltuk seçebilirsiniz.');
        }
    });
    
    form.addEventListener('submit', function(e) {
        console.log('Form submit event triggered');
        if (!validateForm()) {
            e.preventDefault();
            return false;
        }
        console.log('Form will be submitted');
    });
});
</script> 