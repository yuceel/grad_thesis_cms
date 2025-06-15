<?php
require_login();

if (!is_post()) {
    redirect('home');
}

$showtime_id = isset($_POST['showtime_id']) ? intval($_POST['showtime_id']) : 0;
$selected_seats = isset($_POST['selected_seats']) ? $_POST['selected_seats'] : '';

error_log('POST data: ' . print_r($_POST, true));
error_log('Showtime ID: ' . $showtime_id);
error_log('Selected seats (raw): ' . $selected_seats);

$selected_seats = json_decode($selected_seats, true);
error_log('Selected seats (decoded): ' . print_r($selected_seats, true));

if (empty($selected_seats) || !is_array($selected_seats)) {
    error_log('Invalid seats data');
    set_message('Lütfen en az bir koltuk seçiniz.', 'danger');
    redirect('booking', ['action' => 'seats', 'showtime_id' => $showtime_id]);
}

if ($showtime_id <= 0) {
    error_log('Invalid showtime ID');
    set_message('Geçersiz gösterim.', 'danger');
    redirect('home');
}

$showtime = $db->select("
    SELECT st.*, m.title as movie_title, m.duration, m.poster,
           sc.name as screen_name, sc.screen_type
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
    SELECT s.*, 
           CASE WHEN rs.seat_id IS NOT NULL THEN 'reserved' ELSE 'available' END as status
    FROM seats s
    LEFT JOIN (
        SELECT DISTINCT rs.seat_id 
        FROM reservation_seats rs
        JOIN reservations r ON rs.reservation_id = r.id
        WHERE r.showtime_id = ? AND r.status IN ('pending', 'confirmed')
    ) rs ON s.id = rs.seat_id
    WHERE s.id IN (" . implode(',', array_fill(0, count($selected_seats), '?')) . ")",
    array_merge([$showtime_id], $selected_seats)
);

$unavailable_seats = array_filter($seats, function($seat) {
    return $seat['status'] === 'reserved';
});

if (!empty($unavailable_seats)) {
    set_message('Seçtiğiniz koltuklardan bazıları başka bir kullanıcı tarafından rezerve edilmiş.', 'danger');
    redirect('booking', ['action' => 'seats', 'showtime_id' => $showtime_id]);
}

$total_price = 0;
foreach ($seats as $seat) {
    $price = $showtime['price'];
    if ($seat['seat_type'] === 'premium') $price *= 1.5;
    if ($seat['seat_type'] === 'vip') $price *= 2;
    $total_price += $price;
}

if (isset($_POST['confirm_booking']) || isset($_POST['card_number'])) {
    error_log('=== PAYMENT FORM SUBMISSION START ===');
    error_log('POST data: ' . print_r($_POST, true));
    error_log('Session data: ' . print_r($_SESSION, true));

    $card_number = isset($_POST['card_number']) ? trim($_POST['card_number']) : '';
    $expiry = isset($_POST['expiry']) ? trim($_POST['expiry']) : '';
    $cvv = isset($_POST['cvv']) ? trim($_POST['cvv']) : '';
    $card_name = isset($_POST['card_name']) ? trim($_POST['card_name']) : '';

    error_log('Payment details:');
    error_log('Card number: ' . substr($card_number, 0, 4) . '****' . substr($card_number, -4));
    error_log('Expiry: ' . $expiry);
    error_log('CVV: ***');
    error_log('Card name: ' . $card_name);

    if (empty($card_number) || empty($expiry) || empty($cvv) || empty($card_name)) {
        error_log('Validation failed: Missing payment details');
        set_message('Lütfen tüm ödeme bilgilerini doldurunuz.', 'danger');
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . page_url('booking', ['action' => 'confirm', 'showtime_id' => $showtime_id, 'selected_seats' => json_encode($selected_seats)]));
        exit();
    }

    if (!preg_match('/^[0-9]{16}$/', $card_number)) {
        set_message('Geçerli bir kart numarası giriniz.', 'danger');
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . page_url('booking', ['action' => 'confirm', 'showtime_id' => $showtime_id, 'selected_seats' => json_encode($selected_seats)]));
        exit();
    }

    if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $expiry)) {
        set_message('Geçerli bir son kullanma tarihi giriniz (MM/YY).', 'danger');
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . page_url('booking', ['action' => 'confirm', 'showtime_id' => $showtime_id, 'selected_seats' => json_encode($selected_seats)]));
        exit();
    }

    $expiry_parts = explode('/', $expiry);
    $expiry_month = (int)$expiry_parts[0];
    $expiry_year = (int)('20' . $expiry_parts[1]);
    $current_month = (int)date('n');
    $current_year = (int)date('Y');
    
    if ($expiry_year < $current_year || ($expiry_year === $current_year && $expiry_month < $current_month)) {
        set_message('Kartın son kullanma tarihi geçmiş bir tarih olamaz.', 'danger');
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . page_url('booking', ['action' => 'confirm', 'showtime_id' => $showtime_id, 'selected_seats' => json_encode($selected_seats)]));
        exit();
    }

    if (!preg_match('/^[0-9]{3}$/', $cvv)) {
        set_message('Geçerli bir CVV giriniz.', 'danger');
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . page_url('booking', ['action' => 'confirm', 'showtime_id' => $showtime_id, 'selected_seats' => json_encode($selected_seats)]));
        exit();
    }

    if (!preg_match('/^[A-Za-z\s]+$/', $card_name) || strlen(trim($card_name)) < 3) {
        set_message('Kart üzerindeki isim sadece harflerden oluşmalı ve en az 3 karakter olmalıdır.', 'danger');
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . page_url('booking', ['action' => 'confirm', 'showtime_id' => $showtime_id, 'selected_seats' => json_encode($selected_seats)]));
        exit();
    }

    try {
        error_log('Starting database transaction');
        $db->beginTransaction();
        error_log('Transaction started successfully');

        error_log('Creating reservation...');
        $reservation_data = [
            'user_id' => $_SESSION['user_id'],
            'showtime_id' => $showtime_id,
            'total_amount' => $total_price,
            'status' => 'confirmed'
        ];
        error_log('Reservation data: ' . print_r($reservation_data, true));
        
        $reservation_id = $db->insert('reservations', $reservation_data);
        error_log('Reservation created with ID: ' . $reservation_id);

        if (!$reservation_id) {
            throw new Exception('Rezervasyon oluşturulamadı.');
        }

        error_log('Adding seats to reservation...');
        foreach ($seats as $seat) {
            $price = $showtime['price'];
            if ($seat['seat_type'] === 'premium') $price *= 1.5;
            if ($seat['seat_type'] === 'vip') $price *= 2;

            $seat_data = [
                'reservation_id' => $reservation_id,
                'seat_id' => $seat['id'],
                'price' => $price
            ];
            error_log('Adding seat: ' . print_r($seat_data, true));

            $seat_result = $db->insert('reservation_seats', $seat_data);

            if (!$seat_result) {
                throw new Exception('Koltuk rezervasyonu yapılamadı. Seat ID: ' . $seat['id']);
            }
        }
        error_log('All seats added successfully');

        error_log('Creating payment record...');
        $payment_data = [
            'reservation_id' => $reservation_id,
            'amount' => $total_price,
            'payment_method' => 'credit_card',
            'status' => 'completed',
            'transaction_id' => generate_random_string()
        ];
        error_log('Payment data: ' . print_r($payment_data, true));

        $payment_id = $db->insert('payments', $payment_data);

        if (!$payment_id) {
            throw new Exception('Ödeme kaydı oluşturulamadı.');
        }
        error_log('Payment record created successfully');

        error_log('Committing transaction...');
        $db->commit();
        error_log('Transaction committed successfully');
        
        unset($_SESSION['form_data']);
        
        set_message('Rezervasyonunuz başarıyla tamamlandı!', 'success');
        
        error_log('Redirecting to success page with reservation ID: ' . $reservation_id);
        header('Location: ' . page_url('booking', ['action' => 'success', 'reservation_id' => $reservation_id]));
        exit();
    } catch (Exception $e) {
        error_log('Transaction failed: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        $db->rollBack();
        set_message('Rezervasyon oluşturulurken bir hata oluştu: ' . $e->getMessage(), 'danger');
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . page_url('booking', ['action' => 'confirm', 'showtime_id' => $showtime_id, 'selected_seats' => json_encode($selected_seats)]));
        exit();
    }
    error_log('=== PAYMENT FORM SUBMISSION END ===');
}
?>

<div class="row">
    <div class="col-md-8">
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

        <div class="card mb-4">
            <div class="card-body">
                <h3 class="card-title mb-4">Seçilen Koltuklar</h3>
                <div class="selected-seats">
                    <?php foreach ($seats as $seat): 
                        $price = $showtime['price'];
                        if ($seat['seat_type'] === 'premium') $price *= 1.5;
                        if ($seat['seat_type'] === 'vip') $price *= 2;
                    ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?php echo $seat['seat_number']; ?> (<?php echo strtoupper($seat['seat_type']); ?>)</span>
                            <span><?php echo number_format($price, 2); ?> ₺</span>
                        </div>
                    <?php endforeach; ?>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <strong>Toplam</strong>
                        <strong><?php echo number_format($total_price, 2); ?> ₺</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="card-title mb-4">Ödeme Bilgileri</h3>
                <form method="post" id="paymentForm">
                    <input type="hidden" name="showtime_id" value="<?php echo $showtime_id; ?>">
                    <input type="hidden" name="selected_seats" value="<?php echo htmlspecialchars(json_encode($selected_seats)); ?>">
                    
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                            <?php 
                            echo $_SESSION['message'];
                            unset($_SESSION['message']);
                            unset($_SESSION['message_type']);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="card_number" class="form-label">Kart Numarası</label>
                        <input type="text" class="form-control" id="card_number" name="card_number" 
                               required pattern="[0-9]{16}" maxlength="16" placeholder="1234 5678 9012 3456"
                               value="<?php echo isset($_SESSION['form_data']['card_number']) ? htmlspecialchars($_SESSION['form_data']['card_number']) : ''; ?>">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="expiry" class="form-label">Son Kullanma Tarihi</label>
                                <input type="text" class="form-control" id="expiry" name="expiry" 
                                       required pattern="(0[1-9]|1[0-2])\/([0-9]{2})" maxlength="5" 
                                       placeholder="MM/YY"
                                       value="<?php echo isset($_SESSION['form_data']['expiry']) ? htmlspecialchars($_SESSION['form_data']['expiry']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="cvv" class="form-label">CVV</label>
                                <input type="text" class="form-control" id="cvv" name="cvv" 
                                       required pattern="[0-9]{3}" maxlength="3" placeholder="123"
                                       value="<?php echo isset($_SESSION['form_data']['cvv']) ? htmlspecialchars($_SESSION['form_data']['cvv']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="card_name" class="form-label">Kart Üzerindeki İsim</label>
                        <input type="text" class="form-control" id="card_name" name="card_name" 
                               required placeholder="JOHN DOE"
                               value="<?php echo isset($_SESSION['form_data']['card_name']) ? htmlspecialchars($_SESSION['form_data']['card_name']) : ''; ?>">
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="confirm_booking" class="btn btn-primary">
                            Ödemeyi Tamamla (<?php echo number_format($total_price, 2); ?> ₺)
                        </button>
                        <a href="<?php echo page_url('booking', ['action' => 'seats', 'showtime_id' => $showtime_id]); ?>" 
                           class="btn btn-outline-secondary">
                            Geri Dön
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Payment form script loaded');
    
    const form = document.getElementById('paymentForm');
    const cardNumber = document.getElementById('card_number');
    const expiry = document.getElementById('expiry');
    const cvv = document.getElementById('cvv');
    const cardName = document.getElementById('card_name');

    form.addEventListener('submit', function(e) {
        console.log('Form submit event triggered');
        
        console.log('Form data:');
        const formData = new FormData(form);
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }
        
        if (!/^[0-9]{16}$/.test(cardNumber.value)) {
            console.log('Invalid card number');
            alert('Geçerli bir kart numarası giriniz.');
            e.preventDefault();
            return;
        }
        
        if (!/^(0[1-9]|1[0-2])\/([0-9]{2})$/.test(expiry.value)) {
            console.log('Invalid expiry date format');
            alert('Geçerli bir son kullanma tarihi giriniz (MM/YY).');
            e.preventDefault();
            return;
        }
        
        const expiryParts = expiry.value.split('/');
        const expiryMonth = parseInt(expiryParts[0]);
        const expiryYear = parseInt('20' + expiryParts[1]);
        const currentDate = new Date();
        const currentMonth = currentDate.getMonth() + 1;
        const currentYear = currentDate.getFullYear();
        
        if (expiryYear < currentYear || (expiryYear === currentYear && expiryMonth < currentMonth)) {
            console.log('Expiry date is in the past');
            alert('Kartın son kullanma tarihi geçmiş bir tarih olamaz.');
            e.preventDefault();
            return;
        }
        
        if (!/^[0-9]{3}$/.test(cvv.value)) {
            console.log('Invalid CVV');
            alert('Geçerli bir CVV giriniz.');
            e.preventDefault();
            return;
        }
        
        if (!/^[A-Za-z\s]+$/.test(cardName.value.trim()) || cardName.value.trim().length < 3) {
            console.log('Invalid card name');
            alert('Kart üzerindeki isim sadece harflerden oluşmalı ve en az 3 karakter olmalıdır.');
            e.preventDefault();
            return;
        }
        
        const confirmInput = document.createElement('input');
        confirmInput.type = 'hidden';
        confirmInput.name = 'confirm_booking';
        confirmInput.value = '1';
        form.appendChild(confirmInput);
        
        console.log('Form validation passed, submitting...');
        return true;
    });

    cardNumber.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 16) value = value.substr(0, 16);
        e.target.value = value;
        console.log('Card number updated:', value);
    });

    expiry.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        
        if (value.length >= 2) {
            let month = parseInt(value.substr(0, 2));
            if (month > 12) {
                value = '12' + value.substr(2);
            } else if (month === 0) {
                value = '01' + value.substr(2);
            }
            value = value.substr(0, 2) + '/' + value.substr(2);
        }
        if (value.length > 5) value = value.substr(0, 5);
        e.target.value = value;
        console.log('Expiry date updated:', value);
    });

    cvv.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 3) value = value.substr(0, 3);
        e.target.value = value;
        console.log('CVV updated:', value);
    });

    cardName.addEventListener('input', function(e) {
        let value = e.target.value.replace(/[^A-Za-z\s]/g, '');
        e.target.value = value.toUpperCase();
        console.log('Card name updated:', e.target.value);
    });
});
</script> 