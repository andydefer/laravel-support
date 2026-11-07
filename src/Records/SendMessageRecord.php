<?php

declare(strict_types=1);

namespace AndyDefer\Support\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

final class SendMessageRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $ticket_id = null,
        public readonly ?string $content = null,
        public readonly ?string $reply_to_id = null,
        public readonly ?bool $is_internal = null,
        public readonly ?StrictAssociative $metadata = null,
    ) {}
}
