<?php
declare(strict_types=1);

namespace DonorNexa\Core;

use DateTimeImmutable;
use DonorNexa\Exceptions\ValidationException;

/**
 * Server-side validation. Rules are written as 'required|email|max:120'.
 * The same patterns are mirrored in public/assets/js/app.js for inline checks.
 */
final class Validator
{
    public const CNIC = '/^\d{5}-\d{7}-\d$/';
    public const MOBILE = '/^03\d{2}-\d{7}$/';
    public const NAME = "/^[\\p{L}][\\p{L} .'-]{1,99}$/u";
    public const PASSWORD = '/^(?=.*[A-Za-z])(?=.*\d).{8,72}$/';

    private array $errors = [];
    private array $clean = [];

    public function __construct(private array $data, private array $labels = [])
    {
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        return (new self($data, $labels))->validate($rules);
    }

    public function validate(array $rules): self
    {
        foreach ($rules as $field => $ruleString) {
            $value = trim((string) ($this->data[$field] ?? ''));
            $this->clean[$field] = $value;
            $list = explode('|', $ruleString);

            if ($value === '') {
                if (in_array('required', $list, true)) {
                    $this->add($field, $this->label($field) . ' is required.');
                }
                continue;
            }
            foreach ($list as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $this->check($field, $value, $name, $arg);
            }
        }
        return $this;
    }

    private function check(string $field, string $value, string $rule, ?string $arg): void
    {
        $label = $this->label($field);
        switch ($rule) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->add($field, 'Enter a valid email address.');
                }
                break;
            case 'cnic':
                if (!preg_match(self::CNIC, $value)) {
                    $this->add($field, 'CNIC must look like 12345-1234567-1.');
                }
                break;
            case 'mobile':
                if (!preg_match(self::MOBILE, $value)) {
                    $this->add($field, 'Mobile must look like 03XX-XXXXXXX.');
                }
                break;
            case 'name':
                if (!preg_match(self::NAME, $value)) {
                    $this->add($field, "$label may only contain letters, spaces and . ' -");
                }
                break;
            case 'password':
                if (!preg_match(self::PASSWORD, $value)) {
                    $this->add($field, 'Password needs 8+ characters with at least one letter and one number.');
                }
                break;
            case 'min':
                if (mb_strlen($value) < (int) $arg) {
                    $this->add($field, "$label must be at least $arg characters.");
                }
                break;
            case 'max':
                if (mb_strlen($value) > (int) $arg) {
                    $this->add($field, "$label must be at most $arg characters.");
                }
                break;
            case 'int':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->add($field, "$label must be a whole number.");
                }
                break;
            case 'between':
                [$lo, $hi] = array_map('floatval', explode(',', (string) $arg));
                if (!is_numeric($value) || (float) $value < $lo || (float) $value > $hi) {
                    $this->add($field, "$label must be between $lo and $hi.");
                }
                break;
            case 'in':
                if (!in_array($value, explode(',', (string) $arg), true)) {
                    $this->add($field, "Choose a valid $label.");
                }
                break;
            case 'date':
                if (!$this->parseDate($value, 'Y-m-d')) {
                    $this->add($field, "$label must be a valid date.");
                }
                break;
            case 'datetime':
                if (!$this->parseDate($value, 'Y-m-d\TH:i')) {
                    $this->add($field, "$label must be a valid date and time.");
                }
                break;
            case 'dob':
                $d = $this->parseDate($value, 'Y-m-d');
                $age = $d ? $d->diff(new DateTimeImmutable('today'))->y : -1;
                if (!$d || $age < 18 || $age > 60) {
                    $this->add($field, 'Donors must be between 18 and 60 years old.');
                }
                break;
            case 'matches':
                if ($value !== trim((string) ($this->data[$arg] ?? ''))) {
                    $this->add($field, "$label does not match.");
                }
                break;
        }
    }

    private function parseDate(string $value, string $format): ?DateTimeImmutable
    {
        $d = DateTimeImmutable::createFromFormat($format, $value);
        return ($d && $d->format($format) === $value) ? $d : null;
    }

    public function add(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function clean(): array
    {
        return $this->clean;
    }

    /** Throw if anything failed, otherwise return the trimmed values. */
    public function validated(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }
        return $this->clean;
    }
}
