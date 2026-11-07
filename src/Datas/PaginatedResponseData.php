<?php

declare(strict_types=1);

namespace AndyDefer\Support\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\Sequential;
use AndyDefer\PhpClient\ValueObjects\UrlVO;

final class PaginatedResponseData extends AbstractData
{
    public function __construct(
        public readonly Sequential $items,
        public readonly int $currentPage,
        public readonly int $perPage,
        public readonly int $total,
        public readonly int $lastPage,
        public readonly ?UrlVO $nextPageUrl = null,
        public readonly ?UrlVO $prevPageUrl = null,
    ) {}
}
