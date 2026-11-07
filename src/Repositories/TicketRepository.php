<?php

declare(strict_types=1);

namespace AndyDefer\Support\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Repository\AbstractRepository;
use AndyDefer\Support\Enums\TicketStatus;
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
                $q->where('reference', 'like', '%' . $filters->search . '%')
                  ->orWhere('title', 'like', '%' . $filters->search . '%')
                  ->orWhere('description', 'like', '%' . $filters->search . '%');
            });
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status->value);
        }

        if ($filters->priority !== null) {
            $query->where('priority', $filters->priority->value);
        }

        if ($filters->createdByType !== null) {
            $query->where('created_by_type', $filters->createdByType);
        }

        if ($filters->createdById !== null) {
            $query->where('created_by_id', $filters->createdById);
        }

        if ($filters->assignedToType !== null) {
            $query->where('assigned_to_type', $filters->assignedToType);
        }

        if ($filters->assignedToId !== null) {
            $query->where('assigned_to_id', $filters->assignedToId);
        }

        if ($filters->isAssigned !== null) {
            $filters->isAssigned
                ? $query->whereNotNull('assigned_to_id')
                : $query->whereNull('assigned_to_id');
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