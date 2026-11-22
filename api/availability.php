<?php
declare(strict_types=1);

// POST /api/availability.php (X-CSRF-Token header) - donor switches availability without a page reload
require dirname(__DIR__) . '/src/bootstrap.php';

use DonorNexa\Core\Api;
use DonorNexa\Core\Auth;
use DonorNexa\Repositories\AuditRepository;
use DonorNexa\Repositories\DonorRepository;

Api::requireRole(['donor']);
Api::requirePost();

$on = filter_var($_POST['available'] ?? '0', FILTER_VALIDATE_BOOLEAN);
$id = Auth::id();
(new DonorRepository())->setAvailability($id, $on);
(new AuditRepository())->log($id, 'availability_changed', 'donors:' . $id, $on ? 'off' : 'on', $on ? 'on' : 'off');
Api::json(['available' => $on]);
