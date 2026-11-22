<?php
declare(strict_types=1);

namespace DonorNexa\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verify(?string $submitted): bool
    {
        return is_string($submitted) && hash_equals(self::token(), $submitted);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }
}

final class Session
{
    private static array $flashNow = [];
    private static array $oldNow = [];

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name(Config::get('app.session_name'));
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $fingerprint = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $expired = isset($_SESSION['_last']) && (time() - $_SESSION['_last']) > Config::get('app.session_idle');
        if ($expired || (isset($_SESSION['_fp']) && !hash_equals($_SESSION['_fp'], $fingerprint))) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['_fp'] = $fingerprint;
        $_SESSION['_last'] = time();

        self::$flashNow = $_SESSION['_flash'] ?? [];
        self::$oldNow = $_SESSION['_old'] ?? [];
        unset($_SESSION['_flash'], $_SESSION['_old']);
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function messages(): array
    {
        return self::$flashNow;
    }

    public static function flashOld(array $input): void
    {
        unset($input['password'], $input['password_confirm'], $input['_csrf']);
        $_SESSION['_old'] = $input;
    }

    public static function old(string $key, mixed $default = ''): mixed
    {
        return self::$oldNow[$key] ?? $default;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
