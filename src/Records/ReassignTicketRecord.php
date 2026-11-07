<?php

declare(strict_types=1);

namespace AndyDefer\Support\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class ReassignTicketRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $ticket_id = null,
        public readonly ?string $resolver_type = null,
        public readonly ?string $resolver_id = null,
    ) {}
}
