<?php

declare(strict_types=1);

namespace AndyDefer\Support\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\Support\Enums\TicketPriority;

final class CreateTicketRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?TicketPriority $priority = null,
        public readonly ?StrictAssociative $metadata = null,
    ) {}
}
