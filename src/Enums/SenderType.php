<?php

declare(strict_types=1);

namespace AndyDefer\Support\Enums;

use AndyDefer\Repository\Contracts\EnumerableInterface;

enum SenderType: string implements EnumerableInterface
{
    case CREATOR = 'creator';
    case RESOLVER = 'resolver';

    public function getValue(): string
    {
        return $this->value;
    }

    public function toInt(): int
    {
        return match ($this) {
            self::CREATOR => 0,
            self::RESOLVER => 1,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::CREATOR => 'Créateur',
            self::RESOLVER => 'Résolveur',
        };
    }
}
