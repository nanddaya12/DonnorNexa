<?php
declare(strict_types=1);

namespace DonorNexa\Core;

use DonorNexa\Exceptions\AppException;
use DonorNexa\Models\User;
use DonorNexa\Repositories\AuditRepository;
use DonorNexa\Repositories\UserRepository;

/** Login, logout, "remember me" cookie and current-user lookup. */
final class Auth
{
    private static ?User $user = null;
    private const MAX_FAILS = 5;
    private const LOCK_SECONDS = 60;

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function user(): ?User
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $id = self::id() ?? self::userIdFromRememberCookie();
        if ($id === null) {
            return null;
        }
        $user = (new UserRepository())->find($id);
        if ($user === null || $user->status !== 'active') {
            self::forget();
            return null;
        }
        return self::$user = $user;
    }

    public static function attempt(string $email, string $password, bool $remember): User
    {
        // Simple brute-force throttle kept in the session
        $fails = $_SESSION['_login_fails'] ?? ['count' => 0, 'since' => time()];
        if ($fails['count'] >= self::MAX_FAILS && time() - $fails['since'] < self::LOCK_SECONDS) {
            throw new AppException('Too many failed attempts. Please wait a minute and try again.');
        }

        $users = new UserRepository();
        $user = $users->findByEmail($email);
        // Always run password_verify so response time does not reveal whether the email exists
        $hash = $user?->password_hash ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        $ok = password_verify($password, $hash);

        if (!$user || !$ok) {
            $count = ($fails['count'] >= self::MAX_FAILS) ? 1 : $fails['count'] + 1;
            $_SESSION['_login_fails'] = ['count' => $count, 'since' => $count === 1 ? time() : $fails['since']];
            throw new AppException('Incorrect email or password.');
        }
        if ($user->status !== 'active') {
            throw new AppException('This account has been blocked. Please contact the administrator.');
        }

        unset($_SESSION['_login_fails']);
        Session::regenerate();                     // new session id on login
        $_SESSION['user_id'] = $user->id;
        self::$user = $user;

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $users->updatePassword($user->id, password_hash($password, PASSWORD_DEFAULT));
        }
        if ($remember) {
            self::issueRememberCookie($user->id, $users);
        }
        (new AuditRepository())->log($user->id, 'login', 'users:' . $user->id, null, null);
        return $user;
    }

    public static function logout(): void
    {
        $id = self::id();
        if ($id !== null) {
            (new AuditRepository())->log($id, 'logout', 'users:' . $id, null, null);
            (new UserRepository())->deleteRememberTokens($id);
        }
        self::forget();
        Session::destroy();
    }

    private static function forget(): void
    {
        self::$user = null;
        unset($_SESSION['user_id']);
        self::clearRememberCookie();
    }

    private static function issueRememberCookie(int $userId, UserRepository $users): void
    {
        $token = bin2hex(random_bytes(32));
        $days = (int) Config::get('app.remember_days');
        $users->saveRememberToken($userId, hash('sha256', $token), $days);
        self::setRememberCookie($userId . ':' . $token, time() + $days * 86400);
    }

    private static function userIdFromRememberCookie(): ?int
    {
        $cookie = $_COOKIE['dn_remember'] ?? '';
        if (!preg_match('/^(\d+):([a-f0-9]{64})$/', $cookie, $m)) {
            return null;
        }
        $userId = (new UserRepository())->findRememberToken((int) $m[1], hash('sha256', $m[2]));
        if ($userId === null) {
            self::clearRememberCookie();
            return null;
        }
        Session::regenerate();
        $_SESSION['user_id'] = $userId;
        return $userId;
    }

    private static function setRememberCookie(string $value, int $expires): void
    {
        setcookie('dn_remember', $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => Session::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function clearRememberCookie(): void
    {
        if (isset($_COOKIE['dn_remember'])) {
            self::setRememberCookie('', time() - 3600);
        }
    }
}
