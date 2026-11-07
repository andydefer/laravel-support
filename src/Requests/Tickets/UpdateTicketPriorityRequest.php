<?php

declare(strict_types=1);

namespace AndyDefer\Support\Requests\Tickets;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Support\Enums\TicketPriority;
use AndyDefer\Support\Records\UpdateTicketPriorityRecord;
use Illuminate\Validation\Rule;

final class UpdateTicketPriorityRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'priority' => ['required', Rule::enum(TicketPriority::class)],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return UpdateTicketPriorityRecord::from([
            'ticket_id' => (string) $this->route('id'),
            'priority' => (string) $this->input('priority'),
        ]);
    }
}
