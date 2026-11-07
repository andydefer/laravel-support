<?php

declare(strict_types=1);

namespace AndyDefer\LaravelRatings\Tests\Fixtures\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelRatings\Tests\Fixtures\Data\TestUserData;

final class TestUserDataCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(TestUserData::class);
    }
}
