<?php
require_login();

if (is_post()) {
    $current_password = get_post('current_password');
    $new_password = get_post('new_password');
    $confirm_password = get_post('confirm_password');
    $errors = [];

    if (!$current_password) {
        $errors[] = 'Mevcut şifrenizi giriniz.';
    }
    if (!$new_password) {
        $errors[] = 'Yeni şifrenizi giriniz.';
    } elseif (strlen($new_password) < 6) {
        $errors[] = 'Yeni şifre en az 6 karakter olmalıdır.';
    }
    if ($new_password !== $confirm_password) {
        $errors[] = 'Yeni şifreler eşleşmiyor.';
    }

    if (empty($errors)) {
        try {
            $user = $db->select("SELECT password FROM users WHERE id = ?", [$_SESSION['user_id']]);
            if (!$user || !password_verify($current_password, $user[0]['password'])) {
                throw new Exception("Mevcut şifreniz hatalı.");
            }

            $db->beginTransaction();
            
            $result = $db->update('users', 
                ['password' => password_hash($new_password, PASSWORD_DEFAULT)],
                'id = ?',
                [$_SESSION['user_id']]
            );
            
            if ($result) {
                $db->commit();
                set_message('Şifreniz başarıyla güncellendi.', 'success');
                redirect('profile');
            } else {
                throw new Exception("Şifre güncellenirken bir hata oluştu.");
            }
        } catch (Exception $e) {
            $db->rollBack();
            set_message($e->getMessage(), 'danger');
            redirect('profile_password'); 
        }
    } else {
        set_message(implode('<br>', $errors), 'danger');
        redirect('profile_password'); 
    }
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Şifre Değiştir</h5>
                </div>
                <div class="card-body">
                    <form method="post" autocomplete="off">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mevcut Şifre</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Yeni Şifre</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" 
                                   minlength="6" required>
                            <div class="form-text">En az 6 karakter olmalıdır.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Yeni Şifre (Tekrar)</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   minlength="6" required>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo page_url('profile'); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Geri Dön
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key me-2"></i>Şifreyi Değiştir
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div> 