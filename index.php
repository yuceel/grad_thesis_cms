<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

$page = isset($_GET['page']) ? $_GET['page'] : 'home';

include 'includes/header.php';

switch ($page) {
    case 'home':
        include 'pages/movies/list.php';
        break;
    case 'movie':
        include 'pages/movies/details.php';
        break;
    case 'login':
        include 'pages/auth/login.php';
        break;
    case 'register':
        include 'pages/auth/register.php';
        break;
    case 'logout':
        include 'pages/auth/logout.php';
        break;
    case 'profile':
        include 'pages/profile.php';
        break;
    case 'profile_edit':
        include 'pages/profile_edit.php';
        break;
    case 'profile_password':
        include 'pages/profile_password.php';
        break;
    case 'booking':
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'seats':
                    include 'pages/booking/seats.php';
                    break;
                case 'confirm':
                    include 'pages/booking/confirm.php';
                    break;
                case 'success':
                    include 'pages/booking/success.php';
                    break;
                default:
                    redirect('home');
            }
        } else {
            redirect('home');
        }
        break;
    case 'admin':
        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
            header('Location: admin/index.php');
            exit();
        } else {
            header('Location: index.php?page=login');
            exit();
        }
        break;
    default:
        include 'pages/movies/list.php';
}

include 'includes/footer.php';
?> 