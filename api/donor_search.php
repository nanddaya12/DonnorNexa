<?php
declare(strict_types=1);

// GET /api/donor_search.php?q=&blood_group=&city_id= - live search of eligible donors (hospital/admin only)
require dirname(__DIR__) . '/src/bootstrap.php';

use DonorNexa\Core\Api;
use DonorNexa\Repositories\DonorRepository;

Api::requireRole(['hospital', 'admin']);

$group = $_GET['blood_group'] ?? '';
$group = in_array($group, blood_groups(), true) ? $group : null;
$city = filter_var($_GET['city_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);

$rows = (new DonorRepository())->searchEligible($group, $city, $q, 20);
Api::json(['donors' => array_map(static function (array $r): array {
    // Privacy: first name + initial only, and never a phone number
    $parts = preg_split('/\s+/', trim($r['name']));
    $short = $parts[0] . (isset($parts[1]) ? ' ' . mb_substr(end($parts), 0, 1) . '.' : '');
    return [
        'name'        => $short,
        'blood_group' => $r['blood_group'],
        'city'        => $r['city_name'],
        'days_rested' => min((int) $r['days_since_donation'], 999),
    ];
}, $rows)]);
