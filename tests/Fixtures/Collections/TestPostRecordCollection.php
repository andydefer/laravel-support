<?php

declare(strict_types=1);

namespace AndyDefer\LaravelRatings\Tests\Fixtures\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelRatings\Tests\Fixtures\Records\TestPostRecord;

final class TestPostRecordCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(TestPostRecord::class);
    }
}
