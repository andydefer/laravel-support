<?php

declare(strict_types=1);

namespace AndyDefer\Support\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Traits\Hydratable;

final class MessagesReadResultData extends AbstractData
{
    use Hydratable;

    public function __construct(
        public readonly string $ticketId,
        public readonly int $markedCount,
    ) {}
}
