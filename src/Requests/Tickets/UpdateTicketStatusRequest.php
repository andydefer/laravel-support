<?php

declare(strict_types=1);

namespace AndyDefer\Support\Requests\Tickets;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Support\Enums\TicketStatus;
use AndyDefer\Support\Records\UpdateTicketStatusRecord;
use Illuminate\Validation\Rule;

final class UpdateTicketStatusRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TicketStatus::class)],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return UpdateTicketStatusRecord::from([
            'ticket_id' => (string) $this->route('id'),
            'status' => (string) $this->input('status'),
        ]);
    }
}
