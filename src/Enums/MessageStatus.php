<?php

declare(strict_types=1);

namespace AndyDefer\Support\Enums;

use AndyDefer\Repository\Contracts\EnumerableInterface;

enum MessageStatus: string implements EnumerableInterface
{
    case SENT = 'sent';
    case DISPATCHED = 'dispatched';
    case READ = 'read';

    public function getValue(): string
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
