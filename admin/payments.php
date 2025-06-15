<?php
define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/db.php';

if (!has_role('admin')) {
    set_message('Bu sayfaya erişim yetkiniz yok.', 'danger');
    redirect('index.php');
}

$page_title = "Ödeme Yönetimi";
include ROOT_PATH . '/includes/header.php';

if (isset($_POST['update_status']) && isset($_POST['payment_id']) && isset($_POST['status'])) {
    $payment_id = (int)$_POST['payment_id'];
    $status = $_POST['status'];
    
    try {
        $db->beginTransaction();
        
        $result = $db->update('payments', 
            ['status' => $status],
            'id = ?',
            [$payment_id]
        );
        
        if ($result) {
            if (in_array($status, ['failed', 'refunded'])) {
                $payment = $db->select("SELECT reservation_id FROM payments WHERE id = ?", [$payment_id])[0];
                $db->update('reservations',
                    ['status' => 'cancelled'],
                    'id = ?',
                    [$payment['reservation_id']]
                );
            }
            
            $db->commit();
            set_message('Ödeme durumu güncellendi.', 'success');
        } else {
            throw new Exception("Ödeme durumu güncellenirken bir hata oluştu.");
        }
    } catch (Exception $e) {
        $db->rollBack();
        set_message($e->getMessage(), 'danger');
    }
    
    header('Location: payments.php');
    exit;
}

$users = $db->select("
    SELECT u.*, 
           COUNT(DISTINCT p.id) as payment_count,
           SUM(p.amount) as total_payment
    FROM users u
    LEFT JOIN reservations r ON u.id = r.user_id
    LEFT JOIN payments p ON r.id = p.reservation_id AND p.status = 'completed'
    GROUP BY u.id
    ORDER BY u.created_at DESC
");

$status_filter = $_GET['status'] ?? '';
$date_start = $_GET['date_start'] ?? '';
$date_end = $_GET['date_end'] ?? '';

$sql = "
    SELECT p.*, 
           r.id as reservation_id,
           u.first_name, u.last_name, u.email,
           m.title as movie_title,
           s.name as screen_name,
           st.start_time
    FROM payments p
    JOIN reservations r ON p.reservation_id = r.id
    JOIN users u ON r.user_id = u.id
    JOIN showtimes st ON r.showtime_id = st.id
    JOIN movies m ON st.movie_id = m.id
    JOIN screens s ON st.screen_id = s.id
    WHERE 1=1
";

$params = [];

if ($status_filter) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
}

if ($date_start) {
    $sql .= " AND DATE(p.created_at) >= ?";
    $params[] = $date_start;
}

if ($date_end) {
    $sql .= " AND DATE(p.created_at) <= ?";
    $params[] = $date_end;
}

$sql .= " ORDER BY p.created_at DESC";

$payments = $db->select($sql, $params);

$stats = $db->select("
    SELECT 
        IFNULL(COUNT(*), 0) as total_count,
        IFNULL(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) as completed_count,
        IFNULL(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending_count,
        IFNULL(SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END), 0) as failed_count,
        IFNULL(SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END), 0) as refunded_count,
        IFNULL(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as total_amount
    FROM payments
")[0];
?>

<div class="container-fluid">
    <div class="row">

        <?php include 'sidebar.php'; ?>
        

        <main role="main" class="col-md-10 ml-sm-auto px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Ödeme Yönetimi</h1>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['message'];
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                    ?>
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Toplam Ödeme</h5>
                            <h3 class="mb-0"><?php echo number_format((int)$stats['total_count']); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Tamamlanan</h5>
                            <h3 class="mb-0"><?php echo number_format((int)$stats['completed_count']); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5 class="card-title">Bekleyen</h5>
                            <h3 class="mb-0"><?php echo number_format((int)$stats['pending_count']); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title">Toplam Tutar</h5>
                            <h3 class="mb-0"><?php echo number_format((float)$stats['total_amount'], 2); ?> TL</h3>
                        </div>
                    </div>
                </div>
            </div>


            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="status">Durum</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="">Tümü</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Bekliyor</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                                    <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Başarısız</option>
                                    <option value="refunded" <?php echo $status_filter === 'refunded' ? 'selected' : ''; ?>>İade Edildi</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="date_start">Başlangıç Tarihi</label>
                                <input type="date" class="form-control" id="date_start" name="date_start" value="<?php echo $date_start; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="date_end">Bitiş Tarihi</label>
                                <input type="date" class="form-control" id="date_end" name="date_end" value="<?php echo $date_end; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-filter"></i> Filtrele
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Müşteri</th>
                                    <th>Film</th>
                                    <th>Salon</th>
                                    <th>Seans</th>
                                    <th>Tutar</th>
                                    <th>Durum</th>
                                    <th>Tarih</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo $payment['id']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($payment['email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($payment['movie_title']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['screen_name']); ?></td>
                                        <td><?php echo format_date($payment['start_time']); ?></td>
                                        <td><?php echo number_format($payment['amount'], 2); ?> TL</td>
                                        <td>
                                            <span class="badge <?php 
                                                echo match($payment['status']) {
                                                    'completed' => 'bg-success text-white',
                                                    'pending' => 'bg-warning text-white',
                                                    'failed' => 'bg-danger text-white',
                                                    'refunded' => 'bg-info text-white',
                                                    default => 'bg-secondary text-white'
                                                };
                                            ?>">
                                                <?php
                                                echo match($payment['status']) {
                                                    'completed' => 'Tamamlandı',
                                                    'pending' => 'Bekliyor',
                                                    'failed' => 'Başarısız',
                                                    'refunded' => 'İade Edildi',
                                                    default => 'Bilinmiyor'
                                                };
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo format_date($payment['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>


<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ödeme Durumu Güncelle</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Ödeme durumunu değiştirmek istediğinizden emin misiniz?</p>
                <p class="text-warning">Bu işlem rezervasyon durumunu da etkileyebilir!</p>
            </div>
            <div class="modal-footer">
                <form method="post">
                    <input type="hidden" name="payment_id" id="updateStatusPaymentId">
                    <input type="hidden" name="status" id="updateStatusValue">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">İptal</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Güncelle</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function updatePaymentStatus(paymentId, newStatus) {
    document.getElementById('updateStatusPaymentId').value = paymentId;
    document.getElementById('updateStatusValue').value = newStatus;
    $('#updateStatusModal').modal('show');
}
</script>

<?php include ROOT_PATH . '/includes/footer.php'; ?> 