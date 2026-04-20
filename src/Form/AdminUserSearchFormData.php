<?php

declare(strict_types=1);

namespace App\Form;

final class AdminUserSearchFormData
{
    public const string VERIFIED_ALL = 'all';
    public const string VERIFIED_VERIFIED = 'verified';
    public const string VERIFIED_UNVERIFIED = 'unverified';

    public const string ACTIVE_ALL = 'all';
    public const string ACTIVE_ACTIVE = 'active';
    public const string ACTIVE_BLOCKED = 'blocked';

    public ?string $search = null;

    public string $verified = self::VERIFIED_ALL;

    public string $active = self::ACTIVE_ALL;

    public function verifiedFilter(): ?bool
    {
        return match ($this->verified) {
            self::VERIFIED_VERIFIED => true,
            self::VERIFIED_UNVERIFIED => false,
            default => null,
        };
    }

    public function activeFilter(): ?bool
    {
        return match ($this->active) {
            self::ACTIVE_ACTIVE => true,
            self::ACTIVE_BLOCKED => false,
            default => null,
        };
    }
}
