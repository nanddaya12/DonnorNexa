<?php
declare(strict_types=1);

namespace DonorNexa\Repositories;

use DonorNexa\Core\Paginator;
use DonorNexa\Models\Donor;
use DonorNexa\Models\Model;
use DonorNexa\Models\User;

class UserRepository extends BaseRepository
{
    public function find(int $id): ?User
    {
        $row = $this->one('SELECT * FROM users WHERE id = ?', [$id]);
        return $row ? new User($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->one('SELECT * FROM users WHERE email = ?', [strtolower($email)]);
        return $row ? new User($row) : null;
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        return (bool) $this->value(
            'SELECT 1 FROM users WHERE email = ? AND id <> ?',
            [strtolower($email), $exceptId ?? 0]
        );
    }

    public function create(string $name, string $email, string $hash, string $role): int
    {
        $this->run(
            'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
            [$name, strtolower($email), $hash, $role]
        );
        return $this->lastId();
    }

    public function updateName(int $id, string $name): void
    {
        $this->run('UPDATE users SET name = ? WHERE id = ?', [$name, $id]);
    }

    public function updatePassword(int $id, string $hash): void
    {
        $this->run('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->run('UPDATE users SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function saveRememberToken(int $userId, string $hash, int $days): void
    {
        $this->run('DELETE FROM remember_tokens WHERE expires_at < NOW()');
        $this->run(
            'INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))',
            [$userId, $hash, $days]
        );
    }

    public function findRememberToken(int $userId, string $hash): ?int
    {
        $found = $this->value(
            'SELECT user_id FROM remember_tokens WHERE user_id = ? AND token_hash = ? AND expires_at > NOW()',
            [$userId, $hash]
        );
        return $found === null ? null : (int) $found;
    }

    public function deleteRememberTokens(int $userId): void
    {
        $this->run('DELETE FROM remember_tokens WHERE user_id = ?', [$userId]);
    }

    public function paginate(array $f, int $page): array
    {
        $where = ['1=1'];
        $params = [];
        if (($f['q'] ?? '') !== '') {
            $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
            $params[] = $params[] = '%' . $f['q'] . '%';
        }
        if (in_array($f['role'] ?? '', ['admin', 'hospital', 'donor'], true)) {
            $where[] = 'u.role = ?';
            $params[] = $f['role'];
        }
        if (in_array($f['status'] ?? '', ['active', 'blocked'], true)) {
            $where[] = 'u.status = ?';
            $params[] = $f['status'];
        }
        return Paginator::paginate(
            'u.id, u.name, u.email, u.role, u.status, u.created_at',
            'FROM users u WHERE ' . implode(' AND ', $where),
            $params,
            ['created' => 'u.created_at', 'name' => 'u.name', 'email' => 'u.email', 'role' => 'u.role', 'status' => 'u.status'],
            $f['sort'] ?? null,
            $f['dir'] ?? null,
            $page
        );
    }
}

class DonorRepository extends BaseRepository
{
    public function find(int $userId): ?Donor
    {
        $row = $this->one(
            'SELECT d.*, u.name, u.email, u.status, c.city_name, c.province
               FROM donors d
               JOIN users u ON u.id = d.user_id
               JOIN cities c ON c.id = d.city_id
              WHERE d.user_id = ?',
            [$userId]
        );
        return $row ? new Donor($row) : null;
    }

    public function create(int $userId, array $d, ?string $photo): void
    {
        $this->run(
            'INSERT INTO donors (user_id, blood_group, dob, weight, city_id, phone, cnic, photo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $d['blood_group'], $d['dob'], $d['weight'], $d['city_id'], $d['phone'], $d['cnic'] ?: null, $photo]
        );
    }

    public function update(int $userId, array $d, ?string $photo): void
    {
        $sql = 'UPDATE donors SET blood_group = ?, dob = ?, weight = ?, city_id = ?, phone = ?, cnic = ?';
        $params = [$d['blood_group'], $d['dob'], $d['weight'], $d['city_id'], $d['phone'], $d['cnic'] ?: null];
        if ($photo !== null) {
            $sql .= ', photo = ?';
            $params[] = $photo;
        }
        $sql .= ' WHERE user_id = ?';
        $params[] = $userId;
        $this->run($sql, $params);
    }

    public function setAvailability(int $userId, bool $available): void
    {
        $this->run('UPDATE donors SET is_available = ? WHERE user_id = ?', [(int) $available, $userId]);
    }

    public function cnicTaken(string $cnic, ?int $exceptUserId = null): bool
    {
        return (bool) $this->value(
            'SELECT 1 FROM donors WHERE cnic = ? AND user_id <> ?',
            [$cnic, $exceptUserId ?? 0]
        );
    }

    public function searchEligible(?string $group = null, ?int $city = null, string $q = '', int $limit = 20): array
    {
        $where = ['1=1'];
        $params = [];
        if ($group !== null && in_array($group, blood_groups(), true)) {
            $where[] = 'blood_group = ?';
            $params[] = $group;
        }
        if ($city !== null && $city > 0) {
            $where[] = 'city_id = ?';
            $params[] = $city;
        }
        if ($q !== '') {
            $where[] = 'name LIKE ?';
            $params[] = '%' . $q . '%';
        }
        $limit = max(1, min($limit, 100));
        return $this->all(
            'SELECT donor_id, name, blood_group, city_name, province, days_since_donation, age
               FROM v_eligible_donors WHERE ' . implode(' AND ', $where) . "
              ORDER BY days_since_donation DESC, name LIMIT $limit",
            $params
        );
    }

    public function search(array $f, int $limit = 50): array
    {
        $where = ['1=1'];
        $params = [];
        if (in_array($f['blood_group'] ?? '', blood_groups(), true)) {
            $where[] = 'blood_group = ?';
            $params[] = $f['blood_group'];
        }
        if (!empty($f['city_id'])) {
            $where[] = 'city_id = ?';
            $params[] = (int) $f['city_id'];
        }
        if (!empty($f['available'])) {
            $where[] = '1=1';
        }
        $limit = max(1, min($limit, 100));
        return $this->all(
            'SELECT donor_id, name, blood_group, city_name, province, days_since_donation, age
               FROM v_eligible_donors WHERE ' . implode(' AND ', $where) . "
              ORDER BY days_since_donation DESC, name LIMIT $limit",
            $params
        );
    }

    public function history(int $userId): array
    {
        return $this->all(
            'SELECT dn.id, dn.donated_on, dn.units, h.hospital_name, c.city_name, r.blood_group
               FROM donations dn
               JOIN request_matches m ON m.id = dn.match_id
               JOIN blood_requests r  ON r.id = m.request_id
               JOIN hospitals h       ON h.user_id = r.hospital_id
               JOIN cities c          ON c.id = h.city_id
              WHERE m.donor_id = ?
              ORDER BY dn.donated_on DESC, dn.id DESC',
            [$userId]
        );
    }

    public function donationCount(int $userId): int
    {
        return (int) $this->value(
            'SELECT COUNT(*) FROM donations dn JOIN request_matches m ON m.id = dn.match_id WHERE m.donor_id = ?',
            [$userId]
        );
    }
}

class HospitalRepository extends BaseRepository
{
    public function find(int $userId): ?Model
    {
        $row = $this->one(
            'SELECT h.*, u.name, u.email, u.status, c.city_name, c.province
               FROM hospitals h
               JOIN users u ON u.id = h.user_id
               JOIN cities c ON c.id = h.city_id
              WHERE h.user_id = ?',
            [$userId]
        );
        return $row ? new class ($row) extends Model {
        } : null;
    }

    public function licenceNoTaken(string $licenceNo): bool
    {
        return (bool) $this->value('SELECT 1 FROM hospitals WHERE licence_no = ?', [$licenceNo]);
    }

    public function create(int $userId, array $d, string $licenceFile): void
    {
        $this->run(
            'INSERT INTO hospitals (user_id, hospital_name, licence_no, licence_file, city_id, phone)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $d['hospital_name'], $d['licence_no'], $licenceFile, $d['city_id'], $d['phone']]
        );
    }

    public function setVerification(int $userId, string $status): void
    {
        $this->run('UPDATE hospitals SET verification_status = ? WHERE user_id = ?', [$status, $userId]);
    }

    public function adjustTrust(int $userId, int $delta): void
    {
        $this->run(
            'UPDATE hospitals SET trust_score = GREATEST(0, LEAST(100, CAST(trust_score AS SIGNED) + ?)) WHERE user_id = ?',
            [$delta, $userId]
        );
    }

    public function inventory(int $hospitalId): array
    {
        $rows = $this->all('SELECT blood_group, units_available FROM blood_inventory WHERE hospital_id = ?', [$hospitalId]);
        $map = array_fill_keys(blood_groups(), 0);
        foreach ($rows as $r) {
            $map[$r['blood_group']] = (int) $r['units_available'];
        }
        return $map;
    }

    public function addInventory(int $hospitalId, string $group, int $units): void
    {
        $this->run(
            'INSERT INTO blood_inventory (hospital_id, blood_group, units_available) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE units_available = units_available + VALUES(units_available)',
            [$hospitalId, $group, $units]
        );
    }

    public function setInventory(int $hospitalId, string $group, int $units): void
    {
        $this->run(
            'INSERT INTO blood_inventory (hospital_id, blood_group, units_available) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE units_available = VALUES(units_available)',
            [$hospitalId, $group, $units]
        );
    }

    public function paginate(array $f, int $page): array
    {
        $where = ['1=1'];
        $params = [];
        if (($f['q'] ?? '') !== '') {
            $where[] = '(h.hospital_name LIKE ? OR h.licence_no LIKE ? OR u.email LIKE ?)';
            array_push($params, ...array_fill(0, 3, '%' . $f['q'] . '%'));
        }
        if (in_array($f['status'] ?? '', ['pending', 'verified', 'rejected'], true)) {
            $where[] = 'h.verification_status = ?';
            $params[] = $f['status'];
        }
        return Paginator::paginate(
            'h.user_id, h.hospital_name, h.licence_no, h.verification_status, h.trust_score, u.email, c.city_name',
            'FROM hospitals h JOIN users u ON u.id = h.user_id JOIN cities c ON c.id = h.city_id WHERE ' . implode(' AND ', $where),
            $params,
            ['name' => 'h.hospital_name', 'status' => 'h.verification_status', 'trust' => 'h.trust_score', 'city' => 'c.city_name'],
            $f['sort'] ?? null,
            $f['dir'] ?? null,
            $page
        );
    }
}
