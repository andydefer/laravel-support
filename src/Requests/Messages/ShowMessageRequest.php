<?php

declare(strict_types=1);

namespace AndyDefer\Support\Requests\Messages;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Support\Records\ShowMessageRecord;

final class ShowMessageRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [];
    }

    public function getRecord(): AbstractRecord
    {
        return ShowMessageRecord::from([
            'ticket_id'  => (string) $this->route('ticketId'),
            'message_id' => (string) $this->route('id'),
        ]);
    }
}
