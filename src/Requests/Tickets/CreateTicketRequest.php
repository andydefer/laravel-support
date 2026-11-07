<?php

declare(strict_types=1);

namespace AndyDefer\Support\Requests\Tickets;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\Support\Enums\TicketPriority;
use AndyDefer\Support\Records\CreateTicketRecord;
use Illuminate\Validation\Rule;

final class CreateTicketRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string', 'max:10000'],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return CreateTicketRecord::from([
            'title' => $this->input('title'),
            'description' => $this->input('description'),
            'priority' => $this->input('priority') !== null
                ? (string) $this->input('priority')
                : null,
            'metadata' => $this->input('metadata') !== null
                ? StrictAssociative::from($this->input('metadata'))
                : null,
        ]);
    }
}
