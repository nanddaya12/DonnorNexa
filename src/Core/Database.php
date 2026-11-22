<?php
declare(strict_types=1);

namespace DonorNexa\Core;

use DonorNexa\Exceptions\DatabaseException;
use PDO;
use PDOException;
use Throwable;

final class Config
{
    private static array $files = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $file = array_shift($parts);

        if (!isset(self::$files[$file])) {
            $path = BASE_PATH . '/config/' . $file . '.php';
            self::$files[$file] = is_file($path) ? require $path : [];
        }

        $value = self::$files[$file];
        foreach ($parts as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $c = Config::get('db');
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $c['host'],
                $c['port'],
                $c['name'],
                $c['charset']
            );
            try {
                self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                self::$pdo->exec("SET time_zone = '" . Config::get('app.tz_offset') . "'");
            } catch (PDOException $e) {
                Logger::error('DB connection failed: ' . $e->getMessage());
                throw new DatabaseException('The database is not available right now.', 0, $e);
            }
        }
        return self::$pdo;
    }

    public static function transaction(callable $work): mixed
    {
        $pdo = self::connection();
        if ($pdo->inTransaction()) {
            return $work($pdo);
        }
        $pdo->beginTransaction();
        try {
            $result = $work($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

namespace DonorNexa\Traits;

use DonorNexa\Core\Auth;
use DonorNexa\Repositories\AuditRepository;

trait Auditable
{
    protected function audit(string $action, string $entity, mixed $old = null, mixed $new = null, ?int $userId = null): void
    {
        $userId ??= Auth::id();
        (new AuditRepository())->log($userId, $action, $entity, $old, $new);
    }

    protected function auditSystem(string $action, string $entity, mixed $old = null, mixed $new = null): void
    {
        (new AuditRepository())->log(null, $action, $entity, $old, $new);
    }
}
