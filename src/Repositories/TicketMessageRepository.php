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

        if ($filters->ticketId !== null) {
            $query->where('ticket_id', $filters->ticketId);
        }

        if ($filters->replyToId !== null) {
            $query->where('reply_to_id', $filters->replyToId);
        }

        if ($filters->isReply !== null) {
            $filters->isReply
                ? $query->whereNotNull('reply_to_id')
                : $query->whereNull('reply_to_id');
        }

        if ($filters->senderType !== null) {
            $query->where('sender_type', $filters->senderType->value);
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status->value);
        }

        if ($filters->authorType !== null) {
            $query->where('author_type', $filters->authorType);
        }

        if ($filters->authorId !== null) {
            $query->where('author_id', $filters->authorId);
        }

        if ($filters->search !== null) {
            $query->where('content', 'like', '%'.$filters->search.'%');
        }

        if ($filters->fromDate !== null) {
            $query->whereDate('created_at', '>=', $filters->fromDate);
        }

        if ($filters->toDate !== null) {
            $query->whereDate('created_at', '<=', $filters->toDate);
        }

        if ($filters->clusterQuery !== null) {
            $query->whereCluster('metadata', $filters->clusterQuery);
        }
    }
}
