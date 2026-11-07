<?php

declare(strict_types=1);

namespace AndyDefer\Support\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Repository\AbstractRepository;
use AndyDefer\Support\Models\Ticket;
use AndyDefer\Support\Records\TicketFiltersRecord;
use AndyDefer\Support\Records\TicketRecord;
use Illuminate\Database\Eloquent\Builder;

final class TicketRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct(Ticket::class, TicketRecord::class);
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof TicketFiltersRecord) {
            return;
        }

        if ($filters->search !== null) {
            $query->where(function (Builder $q) use ($filters): void {
                $q->where('reference', 'like', '%'.$filters->search.'%')
                    ->orWhere('title', 'like', '%'.$filters->search.'%')
                    ->orWhere('description', 'like', '%'.$filters->search.'%');
            });
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status->value);
        }

        if ($filters->priority !== null) {
            $query->where('priority', $filters->priority->value);
        }

        if ($filters->created_by_type !== null) {
            $query->where('created_by_type', $filters->created_by_type);
        }

        if ($filters->created_by_id !== null) {
            $query->where('created_by_id', $filters->created_by_id);
        }

        if ($filters->assigned_to_type !== null) {
            $query->where('assigned_to_type', $filters->assigned_to_type);
        }

        if ($filters->assigned_to_id !== null) {
            $query->where('assigned_to_id', $filters->assigned_to_id);
        }

        if ($filters->is_assigned !== null) {
            $filters->is_assigned
                ? $query->whereNotNull('assigned_to_id')
                : $query->whereNull('assigned_to_id');
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
