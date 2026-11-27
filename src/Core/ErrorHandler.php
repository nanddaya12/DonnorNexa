<?php
declare(strict_types=1);

namespace DonorNexa\Core;

use DonorNexa\Exceptions\AppException;
use DonorNexa\Exceptions\UnauthorizedException;
use Throwable;

final class Logger
{
    public static function error(string $message): void
    {
        $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
        @file_put_contents(BASE_PATH . '/logs/error.log', $line, FILE_APPEND | LOCK_EX);
    }
}

final class ErrorHandler
{
    public static function handle(Throwable $e): void
    {
        $status = $e instanceof AppException ? $e->status() : 500;
        if ($status >= 500) {
            Logger::error(sprintf('%s: %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
        }
        $message = ($e instanceof AppException) ? $e->getMessage() : 'Something went wrong on our side. Please try again.';

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $message . PHP_EOL);
            return;
        }
        if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
            Api::json(['error' => $message], $status);
        }
        if ($e instanceof UnauthorizedException && !headers_sent()) {
            flash('error', 'Please log in to continue.');
            redirect('login');
        }

        if (!headers_sent()) {
            http_response_code($status);
        }
        $view = in_array($status, [401, 403, 404], true) ? (string) $status : '500';
        $currentView = 'errors/' . $view;
        $title = ['403' => 'Access denied', '404' => 'Page not found', '401' => 'Login required', '500' => 'Something went wrong'][$view];
        try {
            ob_start();
            require BASE_PATH . "/templates/errors/$view.php";
            $content = ob_get_clean();
            require BASE_PATH . '/templates/layout.php';
        } catch (Throwable) {
            echo '<h1>' . e($title) . '</h1><p>' . e($message) . '</p>';
        }
    }
}
