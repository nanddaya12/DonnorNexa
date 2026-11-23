<?php
declare(strict_types=1);

/**
 * Shared start-up file: constants, autoloader, config, error handling, session.
 * Included by public/index.php, every api/*.php endpoint and the CLI scripts.
 */

use DonorNexa\Core\Config;
use DonorNexa\Core\ErrorHandler;
use DonorNexa\Core\Session;
use DonorNexa\Services\ExpiryService;

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'DonorNexa\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));

    $file = BASE_PATH . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
        return;
    }

    static $map = [
        'Exceptions\\'                         => 'src/Exceptions/AppException.php',
        'Models\\'                             => 'src/Models/Model.php',
        'Repositories\\BaseRepository'         => 'src/Repositories/BaseRepository.php',
        'Repositories\\CityRepository'         => 'src/Repositories/BaseRepository.php',
        'Repositories\\SettingRepository'      => 'src/Repositories/BaseRepository.php',
        'Repositories\\NotificationRepository' => 'src/Repositories/BaseRepository.php',
        'Repositories\\AuditRepository'        => 'src/Repositories/BaseRepository.php',
        'Repositories\\UserRepository'         => 'src/Repositories/UserRepository.php',
        'Repositories\\DonorRepository'        => 'src/Repositories/UserRepository.php',
        'Repositories\\HospitalRepository'     => 'src/Repositories/UserRepository.php',
        'Repositories\\RequestRepository'      => 'src/Repositories/RequestRepository.php',
        'Repositories\\MatchRepository'        => 'src/Repositories/RequestRepository.php',
        'Contracts\\ScoringStrategy'           => 'src/Services/MatchingService.php',
        'Services\\DefaultScoringStrategy'     => 'src/Services/MatchingService.php',
        'Services\\CompatibilityService'       => 'src/Services/MatchingService.php',
        'Services\\EligibilityService'         => 'src/Services/MatchingService.php',
        'Services\\MatchingService'            => 'src/Services/MatchingService.php',
        'Services\\ExpiryService'              => 'src/Services/RequestService.php',
        'Services\\RequestService'             => 'src/Services/RequestService.php',
        'Services\\UploadService'              => 'src/Services/RegistrationService.php',
        'Services\\RegistrationService'        => 'src/Services/RegistrationService.php',
        'Core\\Config'                         => 'src/Core/Database.php',
        'Core\\Database'                       => 'src/Core/Database.php',
        'Traits\\Auditable'                    => 'src/Core/Database.php',
        'Core\\Logger'                         => 'src/Core/ErrorHandler.php',
        'Core\\ErrorHandler'                   => 'src/Core/ErrorHandler.php',
        'Core\\Csrf'                           => 'src/Core/Session.php',
        'Core\\Session'                        => 'src/Core/Session.php',
        'Core\\Api'                            => 'src/Core/Router.php',
        'Core\\Paginator'                      => 'src/Core/Router.php',
        'Core\\Router'                         => 'src/Core/Router.php',
        'Controllers\\BaseController'          => 'src/Controllers/BaseController.php',
        'Controllers\\PublicController'        => 'src/Controllers/BaseController.php',
        'Controllers\\NotificationController'  => 'src/Controllers/BaseController.php',
    ];

    foreach ($map as $key => $target) {
        if ($relative === $key || (str_ends_with($key, '\\') && str_starts_with($relative, $key))) {
            require_once BASE_PATH . '/' . $target;
            return;
        }
    }
});

require BASE_PATH . '/src/helpers.php';

date_default_timezone_set(Config::get('app.timezone', 'Asia/Karachi'));
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/logs/error.log');
set_exception_handler([ErrorHandler::class, 'handle']);

if (PHP_SAPI !== 'cli') {
    Session::start();
}

function run_maintenance(): void
{
    $stamp = BASE_PATH . '/logs/.last_expiry';
    $last = is_file($stamp) ? (int) file_get_contents($stamp) : 0;
    if (time() - $last < 60) {
        return;
    }
    file_put_contents($stamp, (string) time(), LOCK_EX);
    try {
        (new ExpiryService())->run();
    } catch (Throwable $e) {
        DonorNexa\Core\Logger::error('Maintenance failed: ' . $e->getMessage());
    }
}
