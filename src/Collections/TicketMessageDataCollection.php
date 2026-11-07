<?php

declare(strict_types=1);

namespace AndyDefer\Support\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\Support\Datas\TicketMessageData;

final class TicketMessageDataCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(TicketMessageData::class);
    }
}
