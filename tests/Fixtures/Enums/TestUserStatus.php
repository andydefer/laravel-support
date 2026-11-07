<?php

declare(strict_types=1);

namespace AndyDefer\LaravelRatings\Tests\Fixtures\Enums;

enum TestUserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => 'Actif',
            self::INACTIVE => 'Inactif',
            self::SUSPENDED => 'Suspendu',
        };
    }
}
