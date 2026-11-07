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
        public readonly ?string $created_by_type = null,
        public readonly ?string $created_by_id = null,
        public readonly ?string $assigned_to_type = null,
        public readonly ?string $assigned_to_id = null,
        public readonly ?bool $is_assigned = null,
        public readonly ?string $from_date = null,
        public readonly ?string $to_date = null,
        public readonly ?string $cluster_query = null,
    ) {}
}
