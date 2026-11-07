<?php

declare(strict_types=1);

namespace AndyDefer\Support\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Support\Enums\MessageStatus;
use AndyDefer\Support\Enums\SenderType;

final class TicketMessageFiltersRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $ticketId = null,
        public readonly ?string $replyToId = null,
        public readonly ?bool $isReply = null,
        public readonly ?SenderType $senderType = null,
        public readonly ?MessageStatus $status = null,
        public readonly ?string $authorType = null,
        public readonly ?string $authorId = null,
        public readonly ?string $search = null,
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null,
        public readonly ?string $clusterQuery = null,
    ) {}
}
