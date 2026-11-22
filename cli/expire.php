<?php
declare(strict_types=1);

/**
 * Cron-friendly auto-expiry job.
 * Run:  php cli/expire.php        (Windows Task Scheduler / Linux cron every few minutes)
 */
require dirname(__DIR__) . '/src/bootstrap.php';

$result = (new DonorNexa\Services\ExpiryService())->run();
echo sprintf("[%s] expired requests: %d, no-response matches: %d\n", date('Y-m-d H:i:s'), $result['expired'], $result['no_response']);
