<?php
namespace App\Core;

class Request {
    public static function get(string $key, $default = null) {
        return $_GET[$key] ?? $default;
    }

    public static function post(string $key, $default = null) {
        return $_POST[$key] ?? $default;
    }

    public static function input(string $key, $default = null) {
        return $_REQUEST[$key] ?? $default;
    }

    public static function isAjax(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    public static function isPost(): bool {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    public static function method(): string {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public static function uri(): string {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    public static function path(): string {
        return parse_url(self::uri(), PHP_URL_PATH) ?: '/';
    }

    public static function referer(string $default = '/'): string {
        return $_SERVER['HTTP_REFERER'] ?? $default;
    }

    public static function int(string $key, int $default = 0): int {
        return (int)($_REQUEST[$key] ?? $default);
    }

    public static function trim(string $key, string $default = ''): string {
        return trim($_REQUEST[$key] ?? $default);
    }
}
