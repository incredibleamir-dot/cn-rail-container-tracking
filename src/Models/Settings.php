<?php
namespace App\Models;
use App\Core\Database;

class Settings {
    private static function db(): Database { return Database::getInstance(); }

    public static function get(string $key, $default = null) {
        $row = self::db()->fetch('SELECT value FROM app_settings WHERE key = ?', [$key]);
        return $row ? $row['value'] : $default;
    }

    public static function set(string $key, string $value): void {
        self::db()->query(
            'INSERT OR REPLACE INTO app_settings (key, value, updated_at) VALUES (?, ?, datetime("now"))',
            [$key, $value]
        );
        \Debug::logAction('SETTING_UPDATED', "key={$key}");
    }

    public static function getAll(): array {
        $rows = self::db()->fetchAll('SELECT * FROM app_settings ORDER BY key');
        $settings = [];
        foreach ($rows as $row) { $settings[$row['key']] = $row['value']; }
        return $settings;
    }

    public static function getApiKey(): string { return self::get('cn_api_key', DEFAULT_CN_API_KEY); }
    public static function getAuthKey(): string { return self::get('cn_auth_key', DEFAULT_CN_AUTH_KEY); }
}