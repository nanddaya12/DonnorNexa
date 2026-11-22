<?php
declare(strict_types=1);

namespace DonorNexa\Models;

use DateTimeImmutable;

/** Base model: wraps a database row and exposes columns as properties. */
abstract class Model
{
    public function __construct(protected array $attributes = [])
    {
    }

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}

/** A login account (admin, hospital or donor). */
class User extends Model
{
    public function __get(string $name): mixed
    {
        return $name === 'id' ? (int) ($this->attributes['id'] ?? 0) : parent::__get($name);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isDonor(): bool
    {
        return $this->role === 'donor';
    }

    public function isHospital(): bool
    {
        return $this->role === 'hospital';
    }

    public function homeRoute(): string
    {
        return $this->role . '/dashboard';
    }
}

/** Donor profile joined with the user and city rows. */
class Donor extends Model
{
    public function age(): int
    {
        return (new DateTimeImmutable($this->dob))->diff(new DateTimeImmutable('today'))->y;
    }

    public function eligibleOn(int $cooldownDays): ?DateTimeImmutable
    {
        if (!$this->last_donation_date) {
            return null;
        }
        return (new DateTimeImmutable($this->last_donation_date))->modify("+{$cooldownDays} days");
    }

    public function daysSinceDonation(): ?int
    {
        if (!$this->last_donation_date) {
            return null;
        }
        return (new DateTimeImmutable($this->last_donation_date))->diff(new DateTimeImmutable('today'))->days;
    }
}

/** A hospital's request for blood. */
class BloodRequest extends Model
{
    public function remainingUnits(): int
    {
        return max(0, (int) $this->units_needed - (int) $this->units_fulfilled);
    }

    public function deadline(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->required_by);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open' && $this->deadline() > new DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['open', 'fulfilled'], true);
    }
}
