<?php
declare(strict_types=1);

// GET /api/stats.php - public live statistics for the landing page
require dirname(__DIR__) . '/src/bootstrap.php';

use DonorNexa\Core\Api;
use DonorNexa\Services\ReportService;

run_maintenance();
Api::json((new ReportService())->publicStats());
