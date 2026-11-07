<?php

declare(strict_types=1);

namespace AndyDefer\Support\Requests\Messages;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Support\Records\ThreadMessagesRecord;

final class ThreadMessagesRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'include_internal' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return ThreadMessagesRecord::from([
            'ticket_id' => (string) $this->route('ticketId'),
            'include_internal' => $this->has('include_internal') ? (bool) $this->input('include_internal') : false,
            'per_page' => $this->input('per_page') !== null ? (int) $this->input('per_page') : 50,
            'page' => $this->input('page') !== null ? (int) $this->input('page') : 1,
        ]);
    }
}
