<?php

declare(strict_types=1);

namespace AndyDefer\Support\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\Support\Enums\TicketPriority;
use AndyDefer\Support\Enums\TicketStatus;

final class TicketRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $reference = null,
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $created_by_type = null,
        public readonly ?string $created_by_id = null,
        public readonly ?string $assigned_to_type = null,
        public readonly ?string $assigned_to_id = null,
        public readonly ?TicketStatus $status = null,
        public readonly ?TicketPriority $priority = null,
        public readonly ?string $assigned_at = null,
        public readonly ?string $resolved_at = null,
        public readonly ?string $closed_at = null,
        public readonly ?StrictAssociative $metadata = null,
    ) {}
}
