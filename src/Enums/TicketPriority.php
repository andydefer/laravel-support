<?php

declare(strict_types=1);

namespace AndyDefer\Support\Enums;

use AndyDefer\Repository\Contracts\EnumerableInterface;

enum TicketPriority: int implements EnumerableInterface
{
    case LOW = 0;
    case NORMAL = 1;
    case HIGH = 2;
    case URGENT = 3;

    public function getValue(): int
    {
        return $this->value;
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
