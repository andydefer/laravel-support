<?php

declare(strict_types=1);

namespace AndyDefer\Support\Enums;

use AndyDefer\Repository\Contracts\EnumerableInterface;

enum TicketPriority: string implements EnumerableInterface
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public function getValue(): string
    {
        return $this->value;
    }

    public function toInt(): int
    {
        return match ($this) {
            self::LOW => 0,
            self::NORMAL => 1,
            self::HIGH => 2,
            self::URGENT => 3,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::LOW => 'Basse',
            self::NORMAL => 'Normale',
            self::HIGH => 'Haute',
            self::URGENT => 'Urgente',
        };
    }
}
