<?php

declare(strict_types=1);

namespace AndyDefer\Support\Enums;

use AndyDefer\Repository\Contracts\EnumerableInterface;

enum TicketStatus: string implements EnumerableInterface
{
    case OPEN = 'open';
    case PENDING = 'pending';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

    public function getValue(): string
    {
        return $this->value;
    }

    public function toInt(): int
    {
        return match ($this) {
            self::OPEN => 0,
            self::PENDING => 1,
            self::RESOLVED => 2,
            self::CLOSED => 3,
        };
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
