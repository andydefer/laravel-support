<?php

declare(strict_types=1);

namespace AndyDefer\Support\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\Support\Datas\TicketData;

final class TicketDataCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(TicketData::class);
    }
}
