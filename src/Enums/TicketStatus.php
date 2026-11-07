<?php

declare(strict_types=1);

namespace AndyDefer\Support\Enums;

use AndyDefer\Repository\Contracts\EnumerableInterface;

enum TicketStatus: int implements EnumerableInterface
{
    case OPEN = 0;
    case PENDING = 1;
    case RESOLVED = 2;
    case CLOSED = 3;

    public function getValue(): int
    {
        return $this->value;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::OPEN => 'Ouvert',
            self::PENDING => 'En attente',
            self::RESOLVED => 'Résolu',
            self::CLOSED => 'Fermé',
        };
    }
}
