<?php
namespace App\Controllers;
use App\Core\Request;
use App\Models\User;

class AuthController {
    public static function loginForm() {
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);
        require __DIR__ . '/../../views/auth/login.php';
    }

    public static function doLogin() {
        $pin = trim($_POST['pin'] ?? '');
        if (empty($pin)) {
            $_SESSION['login_error'] = 'Please enter your PIN.';
            header('Location: /login');
            return;
        }

        $user = User::findByPin($pin);
        if (!$user) {
            \Debug::logAction('LOGIN_FAILED', 'pin=***');
            $_SESSION['login_error'] = 'Invalid PIN. Please try again.';
            header('Location: /login');
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['role']    = $user['role'];

        \Debug::logSession('LOGIN');
        \Debug::logAction('LOGIN_SUCCESS', "user={$user['name']} role={$user['role']}");
        header('Location: /');
        exit;
    }

    public static function logout() {
        \Debug::logSession('LOGOUT');
        \Debug::logAction('LOGOUT');
        session_unset();
        session_destroy();
        header('Location: /login');
        exit;
    }
}