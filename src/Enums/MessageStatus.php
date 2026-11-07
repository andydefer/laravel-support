<?php

declare(strict_types=1);

namespace AndyDefer\Support\Enums;

use AndyDefer\Repository\Contracts\EnumerableInterface;

enum MessageStatus: int implements EnumerableInterface
{
    case SENT = 0;
    case DISPATCHED = 1;
    case READ = 2;

    public function getValue(): int
    {
        return $this->value;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::SENT => 'Envoyé',
            self::DISPATCHED => 'Distribué',
            self::READ => 'Lu',
        };
    }
}
