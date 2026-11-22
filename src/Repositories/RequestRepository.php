<?php
declare(strict_types=1);

namespace DonorNexa\Repositories;

use DonorNexa\Core\Paginator;
use DonorNexa\Models\BloodRequest;

class RequestRepository extends BaseRepository
{
    public function find(int $id): ?BloodRequest
    {
        $row = $this->one(
            'SELECT r.*, h.hospital_name, h.city_id, c.city_name, c.province
               FROM blood_requests r
               JOIN hospitals h ON h.user_id = r.hospital_id
               JOIN cities c ON c.id = h.city_id
              WHERE r.id = ?',
            [$id]
        );
        return $row ? new BloodRequest($row) : null;
    }

    public function lockById(int $id): ?array
    {
        return $this->one('SELECT * FROM blood_requests WHERE id = ? FOR UPDATE', [$id]);
    }

    public function create(int $hospitalId, array $d): int
    {
        $this->run(
            'INSERT INTO blood_requests (hospital_id, blood_group, units_needed, urgency, required_by, ward_notes)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$hospitalId, $d['blood_group'], $d['units_needed'], $d['urgency'], $d['required_by'], $d['ward_notes'] ?: null]
        );
        return $this->lastId();
    }

    public function countOpenCritical(int $hospitalId): int
    {
        return (int) $this->value(
            "SELECT COUNT(*) FROM blood_requests WHERE hospital_id = ? AND urgency = 'Critical' AND status = 'open'",
            [$hospitalId]
        );
    }

    public function setStatus(int $id, string $status): void
    {
        $this->run('UPDATE blood_requests SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function allocateUnit(int $id): void
    {
        $this->run(
            "UPDATE blood_requests
                SET units_fulfilled = units_fulfilled + 1,
                    status = IF(units_fulfilled + 1 >= units_needed, 'fulfilled', status)
              WHERE id = ?",
            [$id]
        );
    }

    public function publicActive(int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));
        return $this->all(
            "SELECT r.id, r.blood_group, c.city_name, r.urgency, r.required_by
               FROM blood_requests r
               JOIN hospitals h ON h.user_id = r.hospital_id
               JOIN cities c ON c.id = h.city_id
              WHERE r.status = 'open' AND r.required_by > NOW()
              ORDER BY FIELD(r.urgency, 'Critical', 'Urgent', 'Normal'), r.required_by
              LIMIT $limit"
        );
    }

    public function paginate(array $f, int $page, ?int $hospitalId = null): array
    {
        $where = ['1=1'];
        $params = [];
        if ($hospitalId !== null) {
            $where[] = 'r.hospital_id = ?';
            $params[] = $hospitalId;
        }
        if (in_array($f['status'] ?? '', ['open', 'fulfilled', 'closed', 'cancelled', 'expired'], true)) {
            $where[] = 'r.status = ?';
            $params[] = $f['status'];
        }
        if (in_array($f['urgency'] ?? '', ['Normal', 'Urgent', 'Critical'], true)) {
            $where[] = 'r.urgency = ?';
            $params[] = $f['urgency'];
        }
        if (in_array($f['blood_group'] ?? '', blood_groups(), true)) {
            $where[] = 'r.blood_group = ?';
            $params[] = $f['blood_group'];
        }
        if (($f['q'] ?? '') !== '') {
            $where[] = '(h.hospital_name LIKE ? OR r.ward_notes LIKE ?)';
            $params[] = $params[] = '%' . $f['q'] . '%';
        }
        return Paginator::paginate(
            'r.id, r.blood_group, r.units_needed, r.units_fulfilled, r.urgency, r.required_by, r.status, r.created_at, h.hospital_name, c.city_name',
            'FROM blood_requests r JOIN hospitals h ON h.user_id = r.hospital_id JOIN cities c ON c.id = h.city_id WHERE ' . implode(' AND ', $where),
            $params,
            ['created' => 'r.created_at', 'deadline' => 'r.required_by', 'group' => 'r.blood_group', 'urgency' => 'r.urgency', 'status' => 'r.status', 'hospital' => 'h.hospital_name'],
            $f['sort'] ?? null,
            $f['dir'] ?? null,
            $page
        );
    }

    public function openForHospital(int $hospitalId): int
    {
        return (int) $this->value(
            "SELECT COUNT(*) FROM blood_requests WHERE hospital_id = ? AND status = 'open' AND required_by > NOW()",
            [$hospitalId]
        );
    }
}

class MatchRepository extends BaseRepository
{
    public function insert(int $requestId, int $donorId, float $score): int
    {
        return $this->run(
            'INSERT IGNORE INTO request_matches (request_id, donor_id, score) VALUES (?, ?, ?)',
            [$requestId, $donorId, $score]
        );
    }

    public function lockForDonor(int $matchId, int $donorId): ?array
    {
        return $this->one('SELECT * FROM request_matches WHERE id = ? AND donor_id = ? FOR UPDATE', [$matchId, $donorId]);
    }

    public function lockWithRequest(int $matchId): ?array
    {
        return $this->one(
            'SELECT m.*, r.hospital_id, r.id AS req_id FROM request_matches m
               JOIN blood_requests r ON r.id = m.request_id
              WHERE m.id = ? FOR UPDATE',
            [$matchId]
        );
    }

    public function find(int $id): ?array
    {
        return $this->one('SELECT * FROM request_matches WHERE id = ?', [$id]);
    }

    public function setStatus(int $id, string $status, ?string $reason = null, bool $responded = true): void
    {
        $this->run(
            'UPDATE request_matches SET status = ?, decline_reason = ?, responded_at = IF(?, NOW(), responded_at) WHERE id = ?',
            [$status, $reason, (int) $responded, $id]
        );
    }

    public function alreadyMatchedDonorIds(int $requestId): array
    {
        return array_map('intval', array_column(
            $this->all('SELECT donor_id FROM request_matches WHERE request_id = ?', [$requestId]),
            'donor_id'
        ));
    }

    public function forRequest(int $requestId): array
    {
        return $this->all(
            'SELECT m.id, m.donor_id, m.score, m.status, m.decline_reason, m.responded_at,
                    u.name, d.blood_group, d.phone, c.city_name
               FROM request_matches m
               JOIN donors d ON d.user_id = m.donor_id
               JOIN users u  ON u.id = d.user_id
               JOIN cities c ON c.id = d.city_id
              WHERE m.request_id = ?
              ORDER BY m.score DESC, m.id',
            [$requestId]
        );
    }

    public function forDonor(int $donorId): array
    {
        return $this->all(
            "SELECT m.id, m.status AS match_status, m.score, m.notified_at, r.id AS request_id, r.blood_group,
                    r.units_needed, r.units_fulfilled, r.urgency, r.required_by, r.status AS request_status,
                    r.ward_notes, h.hospital_name, c.city_name
               FROM request_matches m
               JOIN blood_requests r ON r.id = m.request_id
               JOIN hospitals h ON h.user_id = r.hospital_id
               JOIN cities c ON c.id = h.city_id
              WHERE m.donor_id = ?
              ORDER BY (m.status = 'notified' AND r.status = 'open') DESC, m.notified_at DESC
              LIMIT 30",
            [$donorId]
        );
    }

    public function responseRate(int $donorId): float
    {
        $row = $this->one(
            "SELECT SUM(status IN ('accepted','donated')) AS yes,
                    SUM(status IN ('accepted','donated','declined','no_response')) AS total
               FROM request_matches WHERE donor_id = ?",
            [$donorId]
        );
        $total = (int) ($row['total'] ?? 0);
        return $total === 0 ? 0.5 : (int) $row['yes'] / $total;
    }

    public function acceptedUnfinished(int $requestId): int
    {
        return (int) $this->value(
            "SELECT COUNT(*) FROM request_matches WHERE request_id = ? AND status = 'accepted'",
            [$requestId]
        );
    }

    public function staleNotifiedIds(int $responseHours): array
    {
        return array_map('intval', array_column($this->all(
            "SELECT m.id FROM request_matches m JOIN blood_requests r ON r.id = m.request_id
              WHERE m.status = 'notified'
                AND (r.status IN ('expired','cancelled','closed','fulfilled')
                     OR m.notified_at < DATE_SUB(NOW(), INTERVAL ? HOUR))",
            [$responseHours]
        ), 'id'));
    }
}
