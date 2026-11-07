<?php

declare(strict_types=1);

namespace AndyDefer\Support\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Traits\Hydratable;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use AndyDefer\Support\Enums\TicketPriority;
use AndyDefer\Support\Enums\TicketStatus;

final class TicketData extends AbstractData
{
    use Hydratable;

    public function __construct(
        public readonly string $id,
        public readonly string $reference,
        public readonly string $title,
        public readonly string $description,
        public readonly string $createdByType,
        public readonly string $createdById,
        public readonly ?string $assignedToType,
        public readonly ?string $assignedToId,
        public readonly TicketStatus $status,
        public readonly TicketPriority $priority,
        public readonly ?DateTimeVO $assignedAt,
        public readonly ?DateTimeVO $resolvedAt,
        public readonly ?DateTimeVO $closedAt,
        public readonly ?StrictAssociative $metadata,
        public readonly ?DateTimeVO $createdAt,
        public readonly ?DateTimeVO $updatedAt,
    ) {}
}
