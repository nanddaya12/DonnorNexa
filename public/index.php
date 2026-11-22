<?php
declare(strict_types=1);

/**
 * Front controller: every page request enters here and is routed by ?r=route.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use DonorNexa\Core\Router;

// Security headers for every page
header_remove('X-Powered-By');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; font-src 'self' data:; script-src 'self' 'unsafe-inline'; frame-ancestors 'none'");

run_maintenance();      // auto-expiry (also available as cli/expire.php for cron)

$router = new Router();
foreach (require BASE_PATH . '/config/routes.php' as $route => [$class, $method, $verbs, $roles]) {
    $router->add($route, [$class, $method], $verbs, $roles);
}

$route = trim((string) ($_GET['r'] ?? 'home'), '/');
$router->dispatch($route === '' ? 'home' : $route, $_SERVER['REQUEST_METHOD'] ?? 'GET');
