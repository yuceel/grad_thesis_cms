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

$pageTitle = "Admin Panel";
include ROOT_PATH . '/includes/header.php';
?>


<div class="container-fluid">
    <div class="row">
       
        <?php include __DIR__ . '/sidebar.php'; ?>

    
        <main role="main" class="col-md-10 ml-sm-auto px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard</h1>
            </div>

 
            <div class="row">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Toplam Film</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php
                                        $result = $db->select("SELECT COUNT(*) as count FROM movies");
                                        echo $result[0]['count'];
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-film fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Aktif Seanslar</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php
                                        $result = $db->select("SELECT COUNT(*) as count FROM showtimes WHERE status = 'active'");
                                        echo $result[0]['count'];
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Bugünkü Rezervasyonlar</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php
                                        $result = $db->select("SELECT COUNT(*) as count FROM reservations WHERE DATE(created_at) = CURDATE()");
                                        echo $result[0]['count'];
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-ticket-alt fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Toplam Gelir</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php
                                        $result = $db->select("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'");
                                        echo number_format($result[0]['total'] ?? 0, 2) . ' ₺';
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Son Aktiviteler</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Tarih</th>
                                    <th>İşlem</th>
                                    <th>Kullanıcı</th>
                                    <th>Tutar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $query = "SELECT r.*, u.first_name, u.last_name 
                                         FROM reservations r 
                                         JOIN users u ON r.user_id = u.id 
                                         ORDER BY r.created_at DESC 
                                         LIMIT 5";
                                $reservations = $db->select($query);
                                
                                foreach ($reservations as $reservation) {
                                    echo "<tr>";
                                    echo "<td>" . date('d.m.Y H:i', strtotime($reservation['created_at'])) . "</td>";
                                    echo "<td>Rezervasyon #" . $reservation['id'] . "</td>";
                                    echo "<td>" . htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']) . "</td>";
                                    echo "<td>" . number_format($reservation['total_amount'], 2) . " ₺</td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?> 