<?php
define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/db.php';

if (!has_role('admin')) {
    set_message('Bu sayfaya erişim yetkiniz yok.', 'danger');
    redirect('index.php');
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$user = $db->select("SELECT * FROM users WHERE id = ?", [$user_id]);

if (empty($user)) {
    set_message('Kullanıcı bulunamadı.', 'danger');
    redirect('users.php');
}

$user = $user[0];

$page_title = "Kullanıcı Düzenle";
include ROOT_PATH . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $role = $_POST['role'] ?? 'user';
    $status = $_POST['status'] ?? 'active';
    
    $errors = [];
    
    $name_regex = '/^[a-zA-ZğüşıöçĞÜŞİÖÇ\s]+$/u';
    
    if (empty($first_name)) {
        $errors[] = "Ad alanı zorunludur.";
    } elseif (!preg_match($name_regex, $first_name)) {
        $errors[] = "Ad sadece harflerden oluşmalıdır. Rakam, noktalama işareti veya özel karakter kullanılamaz.";
    }
    
    if (empty($last_name)) {
        $errors[] = "Soyad alanı zorunludur.";
    } elseif (!preg_match($name_regex, $last_name)) {
        $errors[] = "Soyad sadece harflerden oluşmalıdır. Rakam, noktalama işareti veya özel karakter kullanılamaz.";
    }
    
    if (empty($email)) {
        $errors[] = "E-posta alanı zorunludur.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Geçerli bir e-posta adresi giriniz.";
    } else {
        $existing_user = $db->select("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $user_id]);
        if (!empty($existing_user)) {
            $errors[] = "Bu e-posta adresi zaten kullanılıyor.";
        }
    }
    
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = "Şifre en az 6 karakter olmalıdır.";
        } elseif ($password !== $password_confirm) {
            $errors[] = "Şifreler eşleşmiyor.";
        }
    }
    
    if (!in_array($role, ['admin', 'customer'])) {
        $errors[] = "Geçersiz rol seçimi.";
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        $errors[] = "Geçersiz durum seçimi.";
    }
    
    if ($user_id === $_SESSION['user_id'] && $status === 'inactive') {
        $errors[] = "Kendi hesabınızı pasif yapamazsınız.";
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            $update_data = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'role' => $role,
                'status' => $status
            ];
            
            if (!empty($password)) {
                $update_data['password'] = password_hash($password, PASSWORD_DEFAULT);
            }
            
            $result = $db->update('users', 
                $update_data,
                'id = ?',
                [$user_id]
            );
            
            if ($result) {
                $db->commit();
                set_message('Kullanıcı başarıyla güncellendi.', 'success');
                redirect('users.php');
            } else {
                throw new Exception("Kullanıcı güncellenirken bir hata oluştu.");
            }
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="row">
 
        <?php include 'sidebar.php'; ?>
        

        <main role="main" class="col-md-10 ml-sm-auto px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Kullanıcı Düzenle</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="users.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Geri Dön
                    </a>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="post" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="first_name">Ad <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="first_name" 
                                           name="first_name" 
                                           pattern="[a-zA-ZğüşıöçĞÜŞİÖÇ\s]+"
                                           onkeypress="return onlyLetters(event)"
                                           oninput="this.value = this.value.replace(/[^a-zA-ZğüşıöçĞÜŞİÖÇ\s]/g, '')"
                                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : htmlspecialchars($user['first_name']); ?>" 
                                           required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="last_name">Soyad <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="last_name" 
                                           name="last_name" 
                                           pattern="[a-zA-ZğüşıöçĞÜŞİÖÇ\s]+"
                                           onkeypress="return onlyLetters(event)"
                                           oninput="this.value = this.value.replace(/[^a-zA-ZğüşıöçĞÜŞİÖÇ\s]/g, '')"
                                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : htmlspecialchars($user['last_name']); ?>" 
                                           required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">E-posta <span class="text-danger">*</span></label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : htmlspecialchars($user['email']); ?>" 
                                   required>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="password">Şifre</label>
                                    <input type="password" 
                                           class="form-control" 
                                           id="password" 
                                           name="password" 
                                           minlength="6">
                                    <small class="form-text text-muted">Değiştirmek istemiyorsanız boş bırakın.</small>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="password_confirm">Şifre Tekrar</label>
                                    <input type="password" 
                                           class="form-control" 
                                           id="password_confirm" 
                                           name="password_confirm" 
                                           minlength="6">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="role">Rol <span class="text-danger">*</span></label>
                                    <select class="form-control" id="role" name="role" required>
                                        <option value="customer" <?php echo (isset($_POST['role']) ? $_POST['role'] : $user['role']) === 'customer' ? 'selected' : ''; ?>>Müşteri</option>
                                        <option value="admin" <?php echo (isset($_POST['role']) ? $_POST['role'] : $user['role']) === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status">Durum <span class="text-danger">*</span></label>
                                    <select class="form-control" id="status" name="status" required <?php echo $user_id === $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                        <option value="active" <?php echo (isset($_POST['status']) ? $_POST['status'] : $user['status']) === 'active' ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="inactive" <?php echo (isset($_POST['status']) ? $_POST['status'] : $user['status']) === 'inactive' ? 'selected' : ''; ?>>Pasif</option>
                                    </select>
                                    <?php if ($user_id === $_SESSION['user_id']): ?>
                                        <small class="form-text text-muted">Kendi hesabınızın durumunu değiştiremezsiniz.</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Kaydet
                            </button>
                            <a href="users.php" class="btn btn-secondary">İptal</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

function onlyLetters(event) {
    const regex = /[a-zA-ZğüşıöçĞÜŞİÖÇ\s]/;
    if (!regex.test(event.key)) {
        event.preventDefault();
        return false;
    }
    return true;
}
</script>

<?php include ROOT_PATH . '/includes/footer.php'; ?> 