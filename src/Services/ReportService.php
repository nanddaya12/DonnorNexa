<?php
declare(strict_types=1);

namespace DonorNexa\Services;

use DonorNexa\Core\Database;
use DonorNexa\Exceptions\NotFoundException;
use PDO;

/** Statistics for the landing page and admin dashboard, plus CSV exports. */
class ReportService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** Numbers shown to everyone on the landing page. */
    public function publicStats(): array
    {
        return [
            'active_requests'  => (int) $this->db->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'open' AND required_by > NOW()")->fetchColumn(),
            'registered_donors' => (int) $this->db->query("SELECT COUNT(*) FROM donors d JOIN users u ON u.id = d.user_id WHERE u.status = 'active'")->fetchColumn(),
            'units_fulfilled'  => (int) $this->db->query('SELECT COALESCE(SUM(units), 0) FROM donations')->fetchColumn(),
        ];
    }

    public function adminSummary(): array
    {
        $q = fn(string $sql) => (int) $this->db->query($sql)->fetchColumn();
        $totalClosed = $q("SELECT COUNT(*) FROM blood_requests WHERE status IN ('fulfilled','closed','expired','cancelled')");
        $good = $q("SELECT COUNT(*) FROM blood_requests WHERE status IN ('fulfilled','closed')");
        return [
            'users'            => $q("SELECT COUNT(*) FROM users WHERE status = 'active'"),
            'eligible_donors'  => $q('SELECT COUNT(*) FROM v_eligible_donors'),
            'pending_hospitals' => $q("SELECT COUNT(*) FROM hospitals WHERE verification_status = 'pending'"),
            'open_requests'    => $q("SELECT COUNT(*) FROM blood_requests WHERE status = 'open'"),
            'fulfilment_rate'  => $totalClosed > 0 ? (int) round(100 * $good / $totalClosed) : 0,
        ];
    }

    /** Data for the three dashboard charts. */
    public function chartData(): array
    {
        // Requests created per week for the last 8 weeks (GROUP BY on a computed week start)
        $weekRows = $this->db->query(
            "SELECT DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(created_at) DAY) AS week_start, COUNT(*) AS total
               FROM blood_requests
              WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
              GROUP BY week_start ORDER BY week_start"
        )->fetchAll();
        $byWeek = array_column($weekRows, 'total', 'week_start');
        $weeks = [];
        for ($i = 7; $i >= 0; $i--) {
            $start = date('Y-m-d', strtotime('monday this week -' . $i . ' week'));
            $weeks[] = ['label' => date('d M', strtotime($start)), 'total' => (int) ($byWeek[$start] ?? 0)];
        }

        // Demand (units requested) vs supply (eligible donors) by blood group, via LEFT JOIN on derived tables
        $groups = $this->db->query(
            "SELECT g.blood_group,
                    COALESCE(dem.units, 0) AS demand,
                    COALESCE(sup.donors, 0) AS supply
               FROM (SELECT DISTINCT donor_group AS blood_group FROM blood_compatibility) g
               LEFT JOIN (SELECT blood_group, SUM(units_needed) AS units FROM blood_requests GROUP BY blood_group) dem
                      ON dem.blood_group = g.blood_group
               LEFT JOIN (SELECT blood_group, COUNT(*) AS donors FROM v_eligible_donors GROUP BY blood_group) sup
                      ON sup.blood_group = g.blood_group
              ORDER BY FIELD(g.blood_group,'A+','A-','B+','B-','AB+','AB-','O+','O-')"
        )->fetchAll();

        $status = $this->db->query('SELECT status, COUNT(*) AS total FROM blood_requests GROUP BY status')->fetchAll();

        return [
            'weekly' => $weeks,
            'groups' => array_map(fn($r) => ['group' => $r['blood_group'], 'demand' => (int) $r['demand'], 'supply' => (int) $r['supply']], $groups),
            'status' => array_map(fn($r) => ['status' => $r['status'], 'total' => (int) $r['total']], $status),
        ];
    }

    /** Stream a CSV download. $type is one of requests | donors | audit. */
    public function exportCsv(string $type): void
    {
        $queries = [
            'requests' => [
                'SELECT r.id, h.hospital_name, c.city_name, r.blood_group, r.units_needed, r.units_fulfilled, r.urgency, r.required_by, r.status, r.created_at
                   FROM blood_requests r JOIN hospitals h ON h.user_id = r.hospital_id JOIN cities c ON c.id = h.city_id ORDER BY r.id',
            ],
            'donors' => [
                'SELECT u.id, u.name, u.email, d.blood_group, c.city_name, d.last_donation_date, d.is_available, u.status
                   FROM donors d JOIN users u ON u.id = d.user_id JOIN cities c ON c.id = d.city_id ORDER BY u.id',
            ],
            'audit' => [
                "SELECT a.id, COALESCE(u.name, 'system') AS user_name, a.action, a.entity, a.old_value, a.new_value, a.ip_address, a.created_at
                   FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id",
            ],
        ];
        if (!isset($queries[$type])) {
            throw new NotFoundException('Unknown report.');
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="donornexa_' . $type . '_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");                       // BOM so Excel reads UTF-8 correctly
        $stmt = $this->db->query($queries[$type][0]);
        $first = true;
        while ($row = $stmt->fetch()) {
            if ($first) {
                fputcsv($out, array_keys($row));
                $first = false;
            }
            fputcsv($out, array_map([$this, 'csvSafe'], $row));
        }
        fclose($out);
        exit;
    }

    /** Stop spreadsheet formula injection: cells starting with = + - @ get a leading apostrophe. */
    private function csvSafe(mixed $v): string
    {
        $s = (string) $v;
        return ($s !== '' && str_contains('=+-@', $s[0])) ? "'" . $s : $s;
    }
}
