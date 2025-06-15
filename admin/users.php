<?php
define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/db.php';

if (!has_role('admin')) {
    set_message('Bu sayfaya erişim yetkiniz yok.', 'danger');
    redirect('index.php');
}

$page_title = "Kullanıcı Yönetimi";
include ROOT_PATH . '/includes/header.php';

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
?>

<div class="container-fluid">
    <div class="row">

        <?php include 'sidebar.php'; ?>
        

        <main role="main" class="col-md-10 ml-sm-auto px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Kullanıcı Yönetimi</h1>
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

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Ad Soyad</th>
                                    <th>E-posta</th>
                                    <th>Rol</th>
                                    <th>Durum</th>
                                    <th>Kayıt Tarihi</th>
                                    <th>Toplam Ödeme</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo match($user['role']) {
                                                    'admin' => 'bg-danger text-white',
                                                    'customer' => 'bg-info text-white',
                                                    default => 'bg-secondary text-white'
                                                };
                                            ?>">
                                                <?php 
                                                echo match($user['role']) {
                                                    'admin' => 'Admin',
                                                    'customer' => 'Müşteri',
                                                    default => 'Bilinmiyor'
                                                };
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php 
                                                echo ($user['status'] ?? 'active') === 'active' 
                                                    ? 'bg-success text-white' 
                                                    : 'bg-warning text-white'; 
                                            ?>">
                                                <?php echo ($user['status'] ?? 'active') === 'active' ? 'Aktif' : 'Pasif'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo format_date($user['created_at']); ?></td>
                                        <td>
                                            <?php if ($user['payment_count'] > 0): ?>
                                                <?php echo number_format($user['total_payment'], 2); ?> TL
                                                <small class="text-muted">(<?php echo $user['payment_count']; ?> ödeme)</small>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="user_edit.php?id=<?php echo $user['id']; ?>" 
                                               class="btn btn-sm btn-primary" 
                                               title="Düzenle">
                                               <i class="fas fa-edit"></i> Düzenle
                                            </a>
                                        </td>
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

<?php include ROOT_PATH . '/includes/footer.php'; ?> 