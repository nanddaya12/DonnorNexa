<?php
declare(strict_types=1);

namespace DonorNexa\Repositories;

use DonorNexa\Core\Database;
use DonorNexa\Core\Paginator;
use PDO;

/** Shared PDO helpers. Every query in the app goes through prepared statements. */
abstract class BaseRepository
{
    protected PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    protected function one(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    protected function all(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected function value(string $sql, array $params = []): mixed
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $v = $stmt->fetchColumn();
        return $v === false ? null : $v;
    }

    /** Runs INSERT/UPDATE/DELETE and returns the number of affected rows. */
    protected function run(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    protected function lastId(): int
    {
        return (int) $this->db->lastInsertId();
    }
}

class CityRepository extends BaseRepository
{
    public function allCities(): array
    {
        return $this->db->query('SELECT id, city_name, province FROM cities ORDER BY city_name')->fetchAll();
    }

    public function withUsage(): array
    {
        return $this->all(
            'SELECT c.id, c.city_name, c.province,
                    (SELECT COUNT(*) FROM donors d WHERE d.city_id = c.id) AS donors,
                    (SELECT COUNT(*) FROM hospitals h WHERE h.city_id = c.id) AS hospitals
               FROM cities c ORDER BY c.city_name'
        );
    }

    public function exists(int $id): bool
    {
        return (bool) $this->value('SELECT 1 FROM cities WHERE id = ?', [$id]);
    }

    public function add(string $name, string $province): int
    {
        $this->run('INSERT INTO cities (city_name, province) VALUES (?, ?)', [$name, $province]);
        return $this->lastId();
    }

    public function delete(int $id): int
    {
        return $this->run('DELETE FROM cities WHERE id = ?', [$id]);
    }

    public function nameTaken(string $name): bool
    {
        return (bool) $this->value('SELECT 1 FROM cities WHERE city_name = ?', [$name]);
    }
}

class SettingRepository extends BaseRepository
{
    private static ?array $cache = null;

    public function allSettings(): array
    {
        return $this->db->query('SELECT setting_key, setting_value, description FROM settings ORDER BY setting_key')->fetchAll();
    }

    public function get(string $key, int $default = 0): int
    {
        if (self::$cache === null) {
            self::$cache = array_column($this->allSettings(), 'setting_value', 'setting_key');
        }
        return isset(self::$cache[$key]) ? (int) self::$cache[$key] : $default;
    }

    public function set(string $key, int $value): int
    {
        self::$cache = null;
        return $this->run('UPDATE settings SET setting_value = ? WHERE setting_key = ?', [(string) $value, $key]);
    }
}

class NotificationRepository extends BaseRepository
{
    public function add(int $userId, string $message): void
    {
        $this->run('INSERT INTO notifications (user_id, message) VALUES (?, ?)', [$userId, mb_substr($message, 0, 255)]);
    }

    public function recent(int $userId, int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));
        return $this->all("SELECT id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT $limit", [$userId]);
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
    }

    public function markAllRead(int $userId): void
    {
        $this->run('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$userId]);
    }
}

class AuditRepository extends BaseRepository
{
    public function log(?int $userId, string $action, string $entity, mixed $old, mixed $new): void
    {
        $this->run(
            'INSERT INTO audit_logs (user_id, action, entity, old_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $action, $entity, $this->encode($old), $this->encode($new), $_SERVER['REMOTE_ADDR'] ?? 'system']
        );
    }

    private function encode(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        return is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
    }

    public function exists(string $action, string $entity): bool
    {
        return (bool) $this->value('SELECT 1 FROM audit_logs WHERE action = ? AND entity = ? LIMIT 1', [$action, $entity]);
    }

    public function paginate(array $f, int $page): array
    {
        $where = ['1=1'];
        $params = [];
        if (($f['q'] ?? '') !== '') {
            $where[] = '(a.entity LIKE ? OR a.new_value LIKE ? OR u.name LIKE ?)';
            array_push($params, ...array_fill(0, 3, '%' . $f['q'] . '%'));
        }
        if (($f['action'] ?? '') !== '') {
            $where[] = 'a.action = ?';
            $params[] = $f['action'];
        }
        return Paginator::paginate(
            'a.id, a.action, a.entity, a.old_value, a.new_value, a.ip_address, a.created_at, u.name AS user_name',
            'FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE ' . implode(' AND ', $where),
            $params,
            ['time' => 'a.id', 'action' => 'a.action', 'entity' => 'a.entity', 'user' => 'u.name'],
            $f['sort'] ?? null,
            $f['dir'] ?? null,
            $page
        );
    }

    public function actions(): array
    {
        return array_column($this->all('SELECT DISTINCT action FROM audit_logs ORDER BY action'), 'action');
    }
}
