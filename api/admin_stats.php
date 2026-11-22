<?php
declare(strict_types=1);

// GET /api/admin_stats.php - chart data for the admin dashboard (admin only)
require dirname(__DIR__) . '/src/bootstrap.php';

use DonorNexa\Core\Api;
use DonorNexa\Services\ReportService;

Api::requireRole(['admin']);
$service = new ReportService();
Api::json(['summary' => $service->adminSummary()] + $service->chartData());
