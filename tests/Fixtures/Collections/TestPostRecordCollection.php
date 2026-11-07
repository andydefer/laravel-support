<?php

declare(strict_types=1);

namespace AndyDefer\Support\Tests\Fixtures\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\Support\Tests\Fixtures\Records\TestPostRecord;

final class TestPostRecordCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(TestPostRecord::class);
    }
}
