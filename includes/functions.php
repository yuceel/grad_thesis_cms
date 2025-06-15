<?php

function base_url($path = '') {
    $base = rtrim(SITE_URL, '/');
    $path = ltrim($path, '/');
    return $base . '/' . $path;
}

function page_url($page, $params = []) {
    if ($page === 'booking' && !isset($params['action'])) {
        $params['action'] = 'seats';
    }
    return base_url('index.php') . '?' . http_build_query(array_merge(['page' => $page], $params));
}

function redirect($page, $params = []) {
    header("Location: " . page_url($page, $params));
    exit();
}


function set_message($message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}


function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}


function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}


function format_date($date, $format = 'd.m.Y H:i') {
    return date($format, strtotime($date));
}

function generate_random_string($length = 10) {
    return bin2hex(random_bytes($length));
}


function is_post() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}


function get_post($key, $default = '') {
    return isset($_POST[$key]) ? sanitize($_POST[$key]) : $default;
}


function get_get($key, $default = '') {
    return isset($_GET[$key]) ? sanitize($_GET[$key]) : $default;
}


function require_login() {
    if (!is_logged_in()) {
        set_message('Please login to access this page', 'danger');
        redirect('login');
    }
}


function require_admin() {
    if (!is_admin()) {
        set_message('You do not have permission to access this page', 'danger');
        redirect('home');
    }
}


function has_role($required_role) {
    if (!is_logged_in()) {
        return false;
    }
    
    if ($_SESSION['user_role'] === 'admin') {
        return true;
    }
    
    return $_SESSION['user_role'] === $required_role;
}


function fix_line_breaks($text) {
    $text = str_replace(['\\r\\n', '\\n', '\\r'], "\n", $text);
    $text = preg_replace('/\n+/', "\n", $text);
    return trim($text);
}
?> 