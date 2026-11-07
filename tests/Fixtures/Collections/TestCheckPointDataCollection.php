<?php

declare(strict_types=1);

namespace AndyDefer\Support\Tests\Fixtures\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\Support\Tests\Fixtures\Data\TestCheckPointData;

final class TestCheckPointDataCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(TestCheckPointData::class);
    }

    public function getActive(): self
    {
        return $this->filter(fn (TestCheckPointData $checkpoint) => $checkpoint->is_active);
    }
}
