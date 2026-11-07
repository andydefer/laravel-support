<?php

declare(strict_types=1);

namespace AndyDefer\Support\Enums;

use AndyDefer\Repository\Contracts\EnumerableInterface;

enum SenderType: int implements EnumerableInterface
{
    case CREATOR = 0;
    case RESOLVER = 1;

    public function getValue(): int
    {
        return $this->value;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::CREATOR => 'Créateur',
            self::RESOLVER => 'Résolveur',
        };
    }
}
