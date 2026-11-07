<?php

declare(strict_types=1);

namespace AndyDefer\Support\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Support\Enums\TicketPriority;
use AndyDefer\Support\Enums\TicketStatus;

final class TicketFiltersRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?TicketStatus $status = null,
        public readonly ?TicketPriority $priority = null,
        public readonly ?string $createdByType = null,
        public readonly ?string $createdById = null,
        public readonly ?string $assignedToType = null,
        public readonly ?string $assignedToId = null,
        public readonly ?bool $isAssigned = null,
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null,
        public readonly ?string $clusterQuery = null,
    ) {}
}