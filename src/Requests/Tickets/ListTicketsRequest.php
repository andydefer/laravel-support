<?php

declare(strict_types=1);

namespace AndyDefer\Support\Requests\Tickets;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Support\Enums\TicketPriority;
use AndyDefer\Support\Enums\TicketStatus;
use AndyDefer\Support\Records\ListTicketsRecord;
use Illuminate\Validation\Rule;

final class ListTicketsRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'created_by_type' => ['nullable', 'string', 'max:191'],
            'created_by_id' => ['nullable', 'string', 'max:191'],
            'assigned_to_type' => ['nullable', 'string', 'max:191'],
            'assigned_to_id' => ['nullable', 'string', 'max:191'],
            'is_assigned' => ['nullable', 'boolean'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return ListTicketsRecord::from([
            'per_page' => $this->input('per_page') !== null ? (int) $this->input('per_page') : 15,
            'page' => $this->input('page') !== null ? (int) $this->input('page') : 1,
            'search' => $this->input('search'),
            'status' => $this->input('status'),
            'priority' => $this->input('priority'),
            'created_by_type' => $this->input('created_by_type'),
            'created_by_id' => $this->input('created_by_id'),
            'assigned_to_type' => $this->input('assigned_to_type'),
            'assigned_to_id' => $this->input('assigned_to_id'),
            'is_assigned' => $this->has('is_assigned') ? (bool) $this->input('is_assigned') : null,
            'from_date' => $this->input('from_date'),
            'to_date' => $this->input('to_date'),
        ]);
    }
}
