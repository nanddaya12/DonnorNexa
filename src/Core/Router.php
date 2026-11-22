<?php
declare(strict_types=1);

namespace DonorNexa\Core;

use DonorNexa\Exceptions\AppException;
use DonorNexa\Exceptions\ForbiddenException;
use DonorNexa\Exceptions\NotFoundException;
use DonorNexa\Exceptions\UnauthorizedException;

final class Api
{
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function requireRole(array $roles): void
    {
        $user = Auth::user();
        if (!$user) {
            throw new UnauthorizedException('Login required.');
        }
        if (!in_array($user->role, $roles, true)) {
            throw new ForbiddenException('Not allowed.');
        }
    }

    public static function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            self::json(['error' => 'Method not allowed'], 405);
        }
        if (!Csrf::verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? null)) {
            throw new ForbiddenException('Invalid security token.');
        }
    }
}

final class Paginator
{
    public static function paginate(
        string $select,
        string $from,
        array $params,
        array $sortMap,
        ?string $sort,
        ?string $dir,
        int $page,
        ?int $perPage = null
    ): array {
        $db = Database::connection();
        $perPage ??= (int) Config::get('app.per_page', 10);

        $stmt = $db->prepare("SELECT COUNT(*) $from");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $sortKey = isset($sortMap[$sort ?? '']) ? $sort : array_key_first($sortMap);
        $direction = strtolower((string) $dir) === 'asc' ? 'ASC' : 'DESC';

        $sql = "SELECT $select $from ORDER BY {$sortMap[$sortKey]} $direction LIMIT $perPage OFFSET $offset";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return [
            'rows'    => $stmt->fetchAll(),
            'page'    => $page,
            'pages'   => $pages,
            'total'   => $total,
            'sort'    => $sortKey,
            'dir'     => strtolower($direction),
            'perPage' => $perPage,
        ];
    }
}

final class Router
{
    private array $routes = [];

    public function add(string $route, array $handler, array $methods = ['GET'], ?array $roles = null): self
    {
        $this->routes[$route] = compact('handler', 'methods', 'roles');
        return $this;
    }

    public function dispatch(string $route, string $httpMethod): void
    {
        if (!isset($this->routes[$route])) {
            throw new NotFoundException();
        }
        ['handler' => [$class, $method], 'methods' => $methods, 'roles' => $roles] = $this->routes[$route];

        if (!in_array($httpMethod, $methods, true)) {
            header('Allow: ' . implode(', ', $methods));
            throw new class ('That action is not allowed with this request type.') extends AppException {
                protected int $httpStatus = 405;
            };
        }
        if ($roles !== null) {
            $user = Auth::user();
            if (!$user) {
                throw new UnauthorizedException();
            }
            if ($roles !== [] && !in_array($user->role, $roles, true)) {
                throw new ForbiddenException();
            }
        }
        (new $class())->$method();
    }
}
