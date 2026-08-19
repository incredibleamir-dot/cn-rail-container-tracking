<?php
namespace App\Controllers;
use App\Core\Request;
use App\Core\View;
use App\Models\User;
use App\Models\Settings;

class AdminController {

    public static function users() {
        $users = User::findAll(true);
        $totalUsers = User::countAll();
        $activeUsers = User::countActive();
        View::page('admin/users', compact('users', 'totalUsers', 'activeUsers'), 'Admin - Users');
    }

    public static function createUser() {
        $name = Request::trim('name');
        $pin  = Request::trim('pin');
        $role = $_POST['role'] ?? 'user';
        $autoGenerate = isset($_POST['auto_generate']);

        if ($autoGenerate || empty($pin)) $pin = self::generatePin();

        if (empty($name)) { $_SESSION['flash_error'] = 'Name is required.'; header('Location: /admin'); return; }
        if (strlen($pin) < 4) { $_SESSION['flash_error'] = 'PIN must be at least 4 characters.'; header('Location: /admin'); return; }
        if (!preg_match('/^[A-Za-z0-9]+$/', $pin)) { $_SESSION['flash_error'] = 'PIN must be alphanumeric.'; header('Location: /admin'); return; }
        if (User::pinExists($pin)) { $_SESSION['flash_error'] = "PIN \"{$pin}\" is already taken."; header('Location: /admin'); return; }
        if (!in_array($role, ['admin', 'user'])) $role = 'user';

        User::create($name, $pin, $role);
        $_SESSION['flash_success'] = "User \"{$name}\" created. PIN: {$pin}";
        header('Location: /admin');
        exit;
    }

    public static function editUser() {
        $userId = Request::int('user_id');
        $name = Request::trim('name');
        $pin  = Request::trim('pin');
        $role = $_POST['role'] ?? 'user';

        $user = User::findById($userId);
        if (!$user) { $_SESSION['flash_error'] = 'User not found.'; header('Location: /admin'); return; }
        if (empty($name)) { $_SESSION['flash_error'] = 'Name is required.'; header('Location: /admin'); return; }

        if (!empty($pin)) {
            if (strlen($pin) < 4) { $_SESSION['flash_error'] = 'PIN must be at least 4 characters.'; header('Location: /admin'); return; }
            if (!preg_match('/^[A-Za-z0-9]+$/', $pin)) { $_SESSION['flash_error'] = 'PIN must be alphanumeric.'; header('Location: /admin'); return; }
            if (User::pinExists($pin, $userId)) { $_SESSION['flash_error'] = "PIN \"{$pin}\" is already taken."; header('Location: /admin'); return; }
        }
        if (!in_array($role, ['admin', 'user'])) $role = 'user';

        User::update($userId, $name, !empty($pin) ? $pin : null, $role);
        $_SESSION['flash_success'] = "User \"{$name}\" updated.";
        header('Location: /admin');
        exit;
    }

    public static function toggleUser() {
        $userId = Request::int('user_id');
        $user = User::findById($userId);
        if ($user && $userId != $_SESSION['user_id']) {
            User::toggleActive($userId);
            $status = $user['is_active'] ? 'deactivated' : 'activated';
            $_SESSION['flash_success'] = "User \"{$user['name']}\" {$status}.";
        } else {
            $_SESSION['flash_error'] = 'Cannot toggle your own account.';
        }
        header('Location: /admin');
        exit;
    }

    public static function settings() {
        $settings = Settings::getAll();
        View::page('admin/settings', compact('settings'), 'Admin - API Settings');
    }

    public static function saveSettings() {
        $fields = ['cn_api_key', 'cn_auth_key', 'timezone', 'data_source', 'gas_url'];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) Settings::set($f, trim($_POST[$f]));
        }
        $_SESSION['flash_success'] = 'Settings saved.';
        header('Location: /admin/settings');
        exit;
    }

    private static function generatePin(int $length = 8): string {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $pin = '';
        for ($i = 0; $i < $length; $i++) { $pin .= $chars[random_int(0, strlen($chars) - 1)]; }
        return $pin;
    }
}