<?php
declare(strict_types=1);

namespace DonorNexa\Exceptions;

use RuntimeException;
use Throwable;

/** Base application exception. */
class AppException extends RuntimeException
{
    protected int $httpStatus = 500;

    public function status(): int
    {
        return $this->httpStatus;
    }
}

class ConflictException extends AppException
{
    protected int $httpStatus = 409;

    public function __construct(string $message = 'That action conflicts with the current state.', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

class DatabaseException extends AppException
{
    protected int $httpStatus = 503;

    public function __construct(string $message = 'The database is not available right now.', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

class ForbiddenException extends AppException
{
    protected int $httpStatus = 403;

    public function __construct(string $message = 'You do not have permission to do that.', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

class NotFoundException extends AppException
{
    protected int $httpStatus = 404;

    public function __construct(string $message = 'The page you asked for could not be found.', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

class UnauthorizedException extends AppException
{
    protected int $httpStatus = 401;

    public function __construct(string $message = 'Please log in to continue.', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

class ValidationException extends AppException
{
    protected int $httpStatus = 422;

    /** @param array<string,string> $errors */
    public function __construct(private array $errors, string $message = 'Please fix the highlighted fields.')
    {
        parent::__construct($message);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
