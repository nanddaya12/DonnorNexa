<?php
declare(strict_types=1);

namespace DonorNexa\Controllers;

use DonorNexa\Core\Auth;
use DonorNexa\Core\Csrf;
use DonorNexa\Core\Session;
use DonorNexa\Exceptions\ForbiddenException;
use DonorNexa\Exceptions\ValidationException;
use DonorNexa\Models\User;
use DonorNexa\Repositories\NotificationRepository;
use DonorNexa\Repositories\RequestRepository;
use DonorNexa\Services\ReportService;

abstract class BaseController
{
    public function __construct()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if (!Csrf::verify($token)) {
                throw new ForbiddenException('Your form expired or the security token was invalid. Please go back, refresh the page and try again.');
            }
        }
    }

    protected function user(): User
    {
        return Auth::user();
    }

    protected function render(string $view, array $data = [], string $title = ''): void
    {
        $authUser = Auth::user();
        $unread = $authUser ? (new NotificationRepository())->unreadCount($authUser->id) : 0;
        $messages = Session::messages();
        $errors = $data['errors'] ?? [];
        extract($data, EXTR_SKIP);

        ob_start();
        require BASE_PATH . '/templates/' . $view . '.php';
        $content = ob_get_clean();
        require BASE_PATH . '/templates/layout.php';
    }

    protected function go(string $route, array $params = []): void
    {
        redirect($route, $params);
    }

    protected function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }

    protected function failValidation(ValidationException $e, string $route, array $params = []): void
    {
        Session::flashOld($_POST);
        $_SESSION['_errors'] = $e->errors();
        $this->flash('error', $e->getMessage());
        $this->go($route, $params);
    }

    protected function pullErrors(): array
    {
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);
        return $errors;
    }

    protected function input(string $key, string $default = ''): string
    {
        return trim((string) ($_POST[$key] ?? $_GET[$key] ?? $default));
    }

    protected function page(): int
    {
        return max(1, (int) ($_GET['page'] ?? 1));
    }

    protected function filters(array $keys): array
    {
        $f = [];
        foreach ($keys as $k) {
            $f[$k] = trim((string) ($_GET[$k] ?? ''));
        }
        return $f;
    }
}

class PublicController extends BaseController
{
    public function home(): void
    {
        $this->render('public/home', [
            'stats'       => (new ReportService())->publicStats(),
            'emergencies' => (new RequestRepository())->publicActive(6),
        ], 'Home');
    }

    public function emergencies(): void
    {
        $this->render('public/emergencies', ['emergencies' => (new RequestRepository())->publicActive(50)], 'Active emergencies');
    }
}

class NotificationController extends BaseController
{
    public function index(): void
    {
        $repo = new NotificationRepository();
        $id = $this->user()->id;
        $items = $repo->recent($id, 50);
        $this->render('partials/notifications', ['items' => $items], 'Notifications');
        $repo->markAllRead($id);
    }
}
