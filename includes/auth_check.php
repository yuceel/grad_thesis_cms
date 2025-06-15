<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';

if (!is_logged_in()) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Oturum süresi dolmuş. Lütfen tekrar giriş yapın.']);
        exit;
    }
    
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

function update_user_session() {
    global $db;
    
    if (!is_logged_in()) {
        return false;
    }
    
    try {
        $user = $db->select("
            SELECT id, email, first_name, last_name, role, status
            FROM users 
            WHERE id = ?",
            [$_SESSION['user_id']]
        );
        
        if (!$user || $user[0]['status'] !== 'active') {
            session_destroy();
            header('Location: ' . SITE_URL . '/login.php');
            exit;
        }
        
        $user = $user[0];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_role'] = $user['role'];
        
        return true;
        
    } catch (Exception $e) {
        error_log('Kullanıcı oturumu güncellenirken hata: ' . $e->getMessage());
        return false;
    }
}

update_user_session(); 