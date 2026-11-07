<?php

declare(strict_types=1);

namespace AndyDefer\Support\Requests\Tickets;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Support\Records\CloseTicketRecord;

final class CloseTicketRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [];
    }

    public function getRecord(): AbstractRecord
    {
        return CloseTicketRecord::from([
            'ticket_id' => (string) $this->route('id'),
        ]);
    }
}
