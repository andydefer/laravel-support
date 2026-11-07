<?php

declare(strict_types=1);

namespace AndyDefer\Support\Requests\Messages;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\Support\Records\SendMessageRecord;

final class SendMessageRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:20000'],
            'reply_to_id' => ['nullable', 'uuid'],
            'is_internal' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return SendMessageRecord::from([
            'ticket_id' => (string) $this->route('ticketId'),
            'content' => $this->input('content'),
            'reply_to_id' => $this->input('reply_to_id'),
            'is_internal' => (bool) $this->input('is_internal', false),
            'metadata' => $this->input('metadata') !== null
                ? StrictAssociative::from($this->input('metadata'))
                : null,
        ]);
    }
}
