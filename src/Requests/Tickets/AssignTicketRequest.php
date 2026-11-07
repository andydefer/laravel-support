<?php

declare(strict_types=1);

namespace AndyDefer\Support\Requests\Tickets;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Support\Records\AssignTicketRecord;

final class AssignTicketRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'resolver_type' => ['required', 'string', 'max:191'],
            'resolver_id' => ['required', 'string', 'max:191'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return AssignTicketRecord::from([
            'ticket_id' => (string) $this->route('id'),
            'resolver_type' => $this->input('resolver_type'),
            'resolver_id' => $this->input('resolver_id'),
        ]);
    }
}
