<?php
require_once __DIR__ . '/../../includes/functions.php';

if (is_post()) {
    $first_name = get_post('first_name');
    $last_name = get_post('last_name');
    $email = get_post('email');
    $password = get_post('password');
    $password2 = get_post('password2');

    $errors = [];
    
  
    $name_regex = '/^[a-zA-ZğüşıöçĞÜŞİÖÇ\s]+$/u';
    
    if (!$first_name || !$last_name) {
        $errors[] = 'Ad ve soyad zorunlu.';
    } else {
        if (!preg_match($name_regex, $first_name)) {
            $errors[] = 'Ad sadece harflerden oluşmalıdır. Rakam, noktalama işareti veya özel karakter kullanılamaz.';
        }
        if (!preg_match($name_regex, $last_name)) {
            $errors[] = 'Soyad sadece harflerden oluşmalıdır. Rakam, noktalama işareti veya özel karakter kullanılamaz.';
        }
    }
    
    if (!validate_email($email)) $errors[] = 'Geçerli bir e-posta giriniz.';
    if (strlen($password) < 6) $errors[] = 'Şifre en az 6 karakter olmalı.';
    if ($password !== $password2) $errors[] = 'Şifreler eşleşmiyor.';


    $user = $db->select("SELECT id FROM users WHERE email = ?", [$email]);
    if ($user) $errors[] = 'Bu e-posta ile zaten kayıt olunmuş.';

    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $user_id = $db->insert('users', [
                'email' => $email,
                'password' => $hash,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'role' => 'customer',
                'status' => 'active'
            ]);
            
            if ($user_id) {
                $db->commit();
                set_message('Kayıt başarılı! Giriş yapabilirsiniz.', 'success');
                redirect('login');
            } else {
                throw new Exception("Kullanıcı kaydedilemedi.");
            }
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Kayıt hatası: " . $e->getMessage());
            set_message('Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.', 'danger');
            redirect('register'); 
        }
    } else {
        set_message(implode('<br>', $errors), 'danger');
        redirect('register'); 
    }
}
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <h2>Kayıt Ol</h2>
        <form method="post" autocomplete="off">
            <div class="mb-3">
                <label for="first_name" class="form-label">Ad</label>
                <input type="text" 
                       class="form-control" 
                       id="first_name" 
                       name="first_name" 
                       pattern="[a-zA-ZğüşıöçĞÜŞİÖÇ\s]+"
                       onkeypress="return onlyLetters(event)"
                       oninput="this.value = this.value.replace(/[^a-zA-ZğüşıöçĞÜŞİÖÇ\s]/g, '')"
                       value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" 
                       required>
            </div>
            <div class="mb-3">
                <label for="last_name" class="form-label">Soyad</label>
                <input type="text" 
                       class="form-control" 
                       id="last_name" 
                       name="last_name" 
                       pattern="[a-zA-ZğüşıöçĞÜŞİÖÇ\s]+"
                       onkeypress="return onlyLetters(event)"
                       oninput="this.value = this.value.replace(/[^a-zA-ZğüşıöçĞÜŞİÖÇ\s]/g, '')"
                       value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" 
                       required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">E-posta</label>
                <input type="email" class="form-control" id="email" name="email" 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                       required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Şifre</label>
                <input type="password" class="form-control" id="password" name="password" required minlength="6">
                <div class="form-text">En az 6 karakter olmalıdır.</div>
            </div>
            <div class="mb-3">
                <label for="password2" class="form-label">Şifre (Tekrar)</label>
                <input type="password" class="form-control" id="password2" name="password2" required minlength="6">
            </div>
            <button type="submit" class="btn btn-primary">Kayıt Ol</button>
            <a href="<?php echo page_url('login'); ?>" class="btn btn-link">Zaten hesabın var mı?</a>
        </form>
    </div>
</div>

<script>
function onlyLetters(event) {
    
    const regex = /[a-zA-ZğüşıöçĞÜŞİÖÇ\s]/;
  
    if (!regex.test(event.key)) {
        
        event.preventDefault();
        return false;
    }
    return true;
}
</script> 