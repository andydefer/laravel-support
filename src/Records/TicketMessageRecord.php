<?php

declare(strict_types=1);

namespace AndyDefer\Support\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Support\Enums\MessageStatus;
use AndyDefer\Support\Enums\SenderType;

final class TicketMessageRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $ticket_id = null,
        public readonly ?string $reply_to_id = null,
        public readonly ?SenderType $sender_type = null,
        public readonly ?string $author_type = null,
        public readonly ?string $author_id = null,
        public readonly ?string $content = null,
        public readonly ?MessageStatus $status = null,
        public readonly ?array $metadata = null,
    ) {}
}
