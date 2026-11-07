<?php

declare(strict_types=1);

namespace AndyDefer\Support\Requests\Tickets;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Support\Enums\TicketPriority;
use AndyDefer\Support\Records\UpdateTicketRecord;
use Illuminate\Validation\Rule;

final class UpdateTicketRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:191'],
            'description' => ['sometimes', 'string', 'max:10000'],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return UpdateTicketRecord::from([
            'ticket_id' => (string) $this->route('id'),
            'title' => $this->input('title'),
            'description' => $this->input('description'),
            'priority' => $this->input('priority') !== null
                ? TicketPriority::from((string) $this->input('priority'))
                : null,
        ]);
    }
}
