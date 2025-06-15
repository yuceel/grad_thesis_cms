<?php
require_once __DIR__ . '/../../includes/functions.php';

if (is_post()) {
    $email = get_post('email');
    $password = get_post('password');
    $errors = [];
    if (!validate_email($email)) $errors[] = 'Geçerli bir e-posta giriniz.';
    if (!$password) $errors[] = 'Şifre giriniz.';
    if (empty($errors)) {
        $user = $db->select("SELECT * FROM users WHERE email = ?", [$email]);
        if ($user && password_verify($password, $user[0]['password'])) {
            if ($user[0]['status'] !== 'active') {
                set_message('Hesabınız aktif değildir. Yöneticiyle iletişime geçin.<br><strong>İletişim:</strong> admin@cinema.com', 'danger');
                $_SESSION['show_popup'] = true;
                redirect('login');
            } else {
                $_SESSION['user_id'] = $user[0]['id'];
                $_SESSION['user_role'] = $user[0]['role'];
                $_SESSION['user_name'] = $user[0]['first_name'] . ' ' . $user[0]['last_name'];
                set_message('Hoş geldiniz, ' . $_SESSION['user_name'] . '!', 'success');
                redirect('home');
            }
        } else {
            set_message('E-posta veya şifre hatalı.', 'danger');
            redirect('login'); 
        }
    } else {
        set_message(implode('<br>', $errors), 'danger');
        redirect('login');
    }
}
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <h2>Giriş Yap</h2>
        <form method="post" autocomplete="off">
            <div class="mb-3">
                <label for="email" class="form-label">E-posta</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Şifre</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Giriş Yap</button>
            <a href="<?php echo page_url('register'); ?>" class="btn btn-link">Hesabın yok mu? Kayıt ol</a>
        </form>
    </div>
</div> 