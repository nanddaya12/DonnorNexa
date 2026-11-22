<?php
declare(strict_types=1);

// GET /api/active_requests.php - public emergency board (blood group, city, urgency, deadline only)
require dirname(__DIR__) . '/src/bootstrap.php';

use DonorNexa\Core\Api;
use DonorNexa\Repositories\RequestRepository;

run_maintenance();
$rows = (new RequestRepository())->publicActive(50);
Api::json(['requests' => array_map(static fn(array $r) => [
    'id'          => (int) $r['id'],
    'blood_group' => $r['blood_group'],
    'city'        => $r['city_name'],
    'urgency'     => $r['urgency'],
    'required_by' => iso_dt($r['required_by']),
], $rows)]);
