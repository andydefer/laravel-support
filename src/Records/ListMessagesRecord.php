<?php

declare(strict_types=1);

namespace AndyDefer\Support\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Support\Enums\MessageStatus;
use AndyDefer\Support\Enums\SenderType;

final class ListMessagesRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $ticket_id = null,
        public readonly ?string $reply_to_id = null,
        public readonly ?bool $is_reply = null,
        public readonly ?bool $is_internal = null,
        public readonly ?SenderType $sender_type = null,
        public readonly ?MessageStatus $status = null,
        public readonly ?string $search = null,
        public readonly ?string $from_date = null,
        public readonly ?string $to_date = null,
        public readonly ?int $per_page = null,
        public readonly ?int $page = null,
    ) {}
}
