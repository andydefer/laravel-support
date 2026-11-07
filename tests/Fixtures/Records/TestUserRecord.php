<?php

declare(strict_types=1);

namespace AndyDefer\Support\Tests\Fixtures\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Support\Tests\Fixtures\Collections\TestPostRecordCollection;
use AndyDefer\Support\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\Support\Tests\Fixtures\Enums\TestUserStatus;

final class TestUserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly TestUserStatus $status,
        public readonly TestUserRole $role,
        public readonly ?int $age,
        public readonly ?StrictDataObject $metadata,
        public readonly string $created_at,
        public readonly string $updated_at,
        public readonly ?TestPostRecordCollection $posts = null,
    ) {}
}
