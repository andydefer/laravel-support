<?php

declare(strict_types=1);

namespace AndyDefer\Support\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Repository\AbstractRepository;
use AndyDefer\Support\Models\TicketMessage;
use AndyDefer\Support\Records\TicketMessageFiltersRecord;
use AndyDefer\Support\Records\TicketMessageRecord;
use Illuminate\Database\Eloquent\Builder;

final class TicketMessageRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct(TicketMessage::class, TicketMessageRecord::class);
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof TicketMessageFiltersRecord) {
            return;
        }

        if ($filters->ticket_id !== null) {
            $query->where('ticket_id', $filters->ticket_id);
        }

        if ($filters->reply_to_id !== null) {
            $query->where('reply_to_id', $filters->reply_to_id);
        }

        if ($filters->is_reply !== null) {
            $filters->is_reply
                ? $query->whereNotNull('reply_to_id')
                : $query->whereNull('reply_to_id');
        }

        if ($filters->is_internal !== null) {
            $query->where('is_internal', $filters->is_internal);
        }

        if ($filters->sender_type !== null) {
            $query->where('sender_type', $filters->sender_type->value);
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status->value);
        }

        if ($filters->search !== null) {
            $query->where('content', 'like', '%'.$filters->search.'%');
        }

        if ($filters->from_date !== null) {
            $query->whereDate('created_at', '>=', $filters->from_date);
        }

        if ($filters->to_date !== null) {
            $query->whereDate('created_at', '<=', $filters->to_date);
        }

        if ($filters->cluster_query !== null) {
            $query->whereCluster('metadata', $filters->cluster_query);
        }
    }
}
