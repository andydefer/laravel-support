<?php

declare(strict_types=1);

namespace AndyDefer\Support\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Traits\Hydratable;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use AndyDefer\Support\Enums\MessageStatus;
use AndyDefer\Support\Enums\SenderType;

final class TicketMessageData extends AbstractData
{
    use Hydratable;

    public function __construct(
        public readonly string $id,
        public readonly string $ticketId,
        public readonly ?string $replyToId,
        public readonly SenderType $senderType,
        public readonly string $content,
        public readonly MessageStatus $status,
        public readonly bool $isInternal,
        public readonly ?StrictAssociative $metadata,
        public readonly ?DateTimeVO $createdAt,
        public readonly ?DateTimeVO $updatedAt,
    ) {}
}
