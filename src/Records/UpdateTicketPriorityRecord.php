<?php

declare(strict_types=1);

namespace AndyDefer\Support\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Support\Enums\TicketPriority;

final class UpdateTicketPriorityRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $ticket_id = null,
        public readonly ?TicketPriority $priority = null,
    ) {}
}
