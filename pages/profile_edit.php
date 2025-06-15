<?php
require_login();

$user = $db->select("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);

if (!$user) {
    set_message('Kullanıcı bilgileri bulunamadı.', 'danger');
    redirect('home');
}
$user = $user[0];

if (is_post()) {
    $first_name = get_post('first_name');
    $last_name = get_post('last_name');
    $email = get_post('email');
    $errors = [];

    if (!$first_name || !$last_name) {
        $errors[] = 'Ad ve soyad zorunludur.';
    }
    if (!validate_email($email)) {
        $errors[] = 'Geçerli bir e-posta adresi giriniz.';
    }
    
    if ($email !== $user['email']) {
        $existing_user = $db->select("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $_SESSION['user_id']]);
        if ($existing_user) {
            $errors[] = 'Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.';
        }
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            $result = $db->update('users', 
                [
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email
                ],
                'id = ?',
                [$_SESSION['user_id']]
            );
            
            if ($result) {
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                
                $db->commit();
                set_message('Profil bilgileriniz başarıyla güncellendi.', 'success');
                redirect('profile');
            } else {
                throw new Exception("Profil güncellenirken bir hata oluştu.");
            }
        } catch (Exception $e) {
            $db->rollBack();
            set_message($e->getMessage(), 'danger');
            redirect('profile_edit'); 
        }
    } else {
        set_message(implode('<br>', $errors), 'danger');
        redirect('profile_edit'); 
    }
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Profili Düzenle</h5>
                </div>
                <div class="card-body">
                    <form method="post" autocomplete="off">
                        <div class="mb-3">
                            <label for="first_name" class="form-label">Ad</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="last_name" class="form-label">Soyad</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">E-posta</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo page_url('profile'); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Geri Dön
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Değişiklikleri Kaydet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div> 