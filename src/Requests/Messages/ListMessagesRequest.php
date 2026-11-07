<?php

declare(strict_types=1);

namespace AndyDefer\Support\Requests\Messages;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Support\Enums\MessageStatus;
use AndyDefer\Support\Enums\SenderType;
use AndyDefer\Support\Records\ListMessagesRecord;
use Illuminate\Validation\Rule;

final class ListMessagesRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'reply_to_id' => ['nullable', 'uuid'],
            'is_reply' => ['nullable', 'boolean'],
            'is_internal' => ['nullable', 'boolean'],
            'sender_type' => ['nullable', Rule::enum(SenderType::class)],
            'status' => ['nullable', Rule::enum(MessageStatus::class)],
            'search' => ['nullable', 'string', 'max:191'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return ListMessagesRecord::from([
            'ticket_id' => (string) $this->route('ticketId'),
            'reply_to_id' => $this->input('reply_to_id'),
            'is_reply' => $this->has('is_reply') ? (bool) $this->input('is_reply') : null,
            'is_internal' => $this->has('is_internal') ? (bool) $this->input('is_internal') : null,
            'sender_type' => $this->input('sender_type'),
            'status' => $this->input('status'),
            'search' => $this->input('search'),
            'from_date' => $this->input('from_date'),
            'to_date' => $this->input('to_date'),
            'per_page' => $this->input('per_page') !== null ? (int) $this->input('per_page') : null,
            'page' => $this->input('page') !== null ? (int) $this->input('page') : null,
        ]);
    }
}
