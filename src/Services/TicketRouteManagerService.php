<?php

declare(strict_types=1);

namespace AndyDefer\Support\Services;

use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\Nemesis\Datas\ErrorResponseData;
use AndyDefer\Nemesis\Helpers\NemesisHelper;
use AndyDefer\Support\Contracts\Services\SupportServiceInterface;
use AndyDefer\Support\Contracts\Services\TicketRouteManagerServiceInterface;
use AndyDefer\Support\Datas\PaginatedResponseData;
use AndyDefer\Support\Enums\ErrorCode;
use AndyDefer\Support\Enums\TicketPriority;
use AndyDefer\Support\Enums\TicketStatus;
use AndyDefer\Support\Models\Ticket;
use AndyDefer\Support\Models\TicketMessage;
use AndyDefer\Support\Records\AssignTicketRecord;
use AndyDefer\Support\Records\CloseTicketRecord;
use AndyDefer\Support\Records\CreateTicketRecord;
use AndyDefer\Support\Records\DestroyTicketRecord;
use AndyDefer\Support\Records\ListMessagesRecord;
use AndyDefer\Support\Records\ListTicketsRecord;
use AndyDefer\Support\Records\MarkAllMessagesReadRecord;
use AndyDefer\Support\Records\MarkMessageDispatchedRecord;
use AndyDefer\Support\Records\MarkMessageReadRecord;
use AndyDefer\Support\Records\ReassignTicketRecord;
use AndyDefer\Support\Records\ReopenTicketRecord;
use AndyDefer\Support\Records\ResolveTicketRecord;
use AndyDefer\Support\Records\SendMessageRecord;
use AndyDefer\Support\Records\ShowMessageRecord;
use AndyDefer\Support\Records\ShowTicketRecord;
use AndyDefer\Support\Records\ThreadMessagesRecord;
use AndyDefer\Support\Records\TicketFiltersRecord;
use AndyDefer\Support\Records\TicketMessageFiltersRecord;
use AndyDefer\Support\Records\UnassignTicketRecord;
use AndyDefer\Support\Records\UpdateTicketPriorityRecord;
use AndyDefer\Support\Records\UpdateTicketRecord;
use AndyDefer\Support\Records\UpdateTicketStatusRecord;
use AndyDefer\Support\Support\DataBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

/**
 * HTTP-facing orchestration of the ticket feature.
 *
 * Each public method corresponds to a single route action. It validates the
 * inbound request against the current authenticated actor, delegates the
 * business operation to {@see SupportServiceInterface}, runs the corresponding
 * lifecycle hooks (before/after), and shapes the final JSON response through
 * {@see DataBuilder}.
 *
 * Hooks are intentionally declared as protected methods returning a nullable
 * {@see ErrorResponseData}: subclasses may override any of them to inject
 * cross-cutting concerns (authorization, logging, metrics…) without touching
 * the orchestration flow.
 */
class TicketRouteManagerService implements TicketRouteManagerServiceInterface
{
    public function __construct(
        protected readonly SupportServiceInterface $support,
        protected readonly NemesisHelper $helper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function storeTicket(CreateTicketRecord $record): ResponseFactory
    {
        $creator = $this->helper->getCurrentAuthenticatable();

        if (! $creator instanceof NotifiableInterface) {
            return $this->error(ErrorCode::CREATOR_TYPE_NOT_NOTIFIABLE);
        }

        $before = $this->beforeStoreTicket(
            creator: $creator,
            title: $record->title,
            description: $record->description,
            priority: $record->priority,
            metadata: $record->metadata,
        );

        if ($before instanceof ErrorResponseData) {
            return $this->errorResponse($before);
        }

        $ticket = $this->support->createTicket(
            creator: $creator,
            title: $record->title,
            description: $record->description,
            priority: $record->priority,
            metadata: $record->metadata,
        );

        $after = $this->afterStoreTicket($ticket);

        if ($after instanceof ErrorResponseData) {
            return $this->errorResponse($after);
        }

        return ResponseFactory::json(DataBuilder::ticket($ticket), 201);
    }

    /**
     * {@inheritDoc}
     */
    public function updateTicket(UpdateTicketRecord $record): ResponseFactory
    {
        $before = $this->beforeUpdateTicket(
            ticketId: $record->ticket_id,
            title: $record->title,
            description: $record->description,
            priority: $record->priority,
        );

        if ($before instanceof ErrorResponseData) {
            return $this->errorResponse($before);
        }

        $ticket = $this->support->findTicket((string) $record->ticket_id);

        if ($ticket === null) {
            return $this->error(ErrorCode::TICKET_NOT_FOUND);
        }

        $editableFields = array_filter(
            [
                'title' => $record->title,
                'description' => $record->description,
            ],
            fn ($value) => $value !== null,
        );

        if ($editableFields !== []) {
            $ticket->update($editableFields);
        }

        if ($record->priority !== null) {
            $ticket = $this->support->updatePriority($ticket, $record->priority);
        }

        $after = $this->afterUpdateTicket($ticket);

        if ($after instanceof ErrorResponseData) {
            return $this->errorResponse($after);
        }

        return ResponseFactory::json(DataBuilder::ticket($ticket));
    }

    /**
     * {@inheritDoc}
     */
    public function destroyTicket(DestroyTicketRecord $record): ResponseFactory
    {
        $ticketId = (string) $record->ticket_id;

        $before = $this->beforeDestroyTicket($ticketId);

        if ($before instanceof ErrorResponseData) {
            return $this->errorResponse($before);
        }

        $ticket = $this->support->findTicket($ticketId);

        if ($ticket === null) {
            return $this->error(ErrorCode::TICKET_NOT_FOUND);
        }

        $ticket->delete();

        $after = $this->afterDestroyTicket($ticketId);

        if ($after instanceof ErrorResponseData) {
            return $this->errorResponse($after);
        }

        return ResponseFactory::json(DataBuilder::ticketDeleted($ticketId));
    }

    /**
     * {@inheritDoc}
     */
    public function assignTicket(AssignTicketRecord $record): ResponseFactory
    {
        $resolver = $this->resolveResolver((string) $record->resolver_type, (string) $record->resolver_id);

        if ($resolver === null) {
            return $this->error(ErrorCode::RESOLVER_NOT_FOUND);
        }

        if (! $resolver instanceof NotifiableInterface) {
            return $this->error(ErrorCode::RESOLVER_TYPE_NOT_NOTIFIABLE);
        }

        $before = $this->beforeAssignTicket((string) $record->ticket_id, $resolver);

        if ($before instanceof ErrorResponseData) {
            return $this->errorResponse($before);
        }

        $ticket = $this->support->findTicket((string) $record->ticket_id);

        if ($ticket === null) {
            return $this->error(ErrorCode::TICKET_NOT_FOUND);
        }

        $ticket = $this->support->assignTicket($ticket, $resolver);

        $after = $this->afterAssignTicket($ticket, $resolver);

        if ($after instanceof ErrorResponseData) {
            return $this->errorResponse($after);
        }

        return ResponseFactory::json(DataBuilder::ticket($ticket));
    }

    /**
     * {@inheritDoc}
     */
    public function reassignTicket(ReassignTicketRecord $record): ResponseFactory
    {
        $resolver = $this->resolveResolver((string) $record->resolver_type, (string) $record->resolver_id);

        if ($resolver === null) {
            return $this->error(ErrorCode::RESOLVER_NOT_FOUND);
        }

        if (! $resolver instanceof NotifiableInterface) {
            return $this->error(ErrorCode::RESOLVER_TYPE_NOT_NOTIFIABLE);
        }

        $before = $this->beforeReassignTicket((string) $record->ticket_id, $resolver);

        if ($before instanceof ErrorResponseData) {
            return $this->errorResponse($before);
        }

        $ticket = $this->support->findTicket((string) $record->ticket_id);

        if ($ticket === null) {
            return $this->error(ErrorCode::TICKET_NOT_FOUND);
        }

        $ticket = $this->support->changeResolver($ticket, $resolver);

        $after = $this->afterReassignTicket($ticket, $resolver);

        if ($after instanceof ErrorResponseData) {
            return $this->errorResponse($after);
        }

        return ResponseFactory::json(DataBuilder::ticket($ticket));
    }

    /**
     * {@inheritDoc}
     */
    public function unassignTicket(UnassignTicketRecord $record): ResponseFactory
    {
        $before = $this->beforeUnassignTicket((string) $record->ticket_id);

        if ($before instanceof ErrorResponseData) {
            return $this->errorResponse($before);
        }

        $ticket = $this->support->findTicket((string) $record->ticket_id);

        if ($ticket === null) {
            return $this->error(ErrorCode::TICKET_NOT_FOUND);
        }

        $ticket = $this->support->unassignTicket($ticket);

        $after = $this->afterUnassignTicket($ticket);

        if ($after instanceof ErrorResponseData) {
            return $this->errorResponse($after);
        }

        return ResponseFactory::json(DataBuilder::ticket($ticket));
    }

    /**
     * {@inheritDoc}
     */
    public function resolveTicket(ResolveTicketRecord $record): ResponseFactory
    {
        $before = $this->beforeResolveTicket((string) $record->ticket_id);

        if ($before instanceof ErrorResponseData) {
            return $this->errorResponse($before);
        }

        $ticket = $this->support->findTicket((string) $record->ticket_id);

        if ($ticket === null) {
            return $this->error(ErrorCode::TICKET_NOT_FOUND);
        }

        $ticket = $this->support->resolveTicket($ticket);

        $after = $this->afterResolveTicket($ticket);

        if ($after instanceof ErrorResponseData) {
            return $this->errorResponse($after);
        }

        return ResponseFactory::json(DataBuilder::ticket($ticket));
    }

    /**
     * {@inheritDoc}
     */
    public function closeTicket(CloseTicketRecord $record): ResponseFactory
    {
        $before = $this->beforeCloseTicket((string) $record->ticket_id);

        if ($before instanceof ErrorResponseData) {
            return $this->errorResponse($before);
        }

        $ticket = $this->support->findTicket((string) $record->ticket_id);

        if ($ticket === null) {
            return $this->error(ErrorCode::TICKET_NOT_FOUND);
        }

        $ticket = $this->support->closeTicket($ticket);

        $after = $this->afterCloseTicket($ticket);

        if ($after instanceof ErrorResponseData) {
            return $this->errorResponse($after);
        }

        return ResponseFactory::json(DataBuilder::ticket($ticket));
    }

    /**
     * {@inheritDoc}
     */
    public function reopenTicket(ReopenTicketRecord $record): ResponseFactory
    {
        $before = $this->beforeReopenTicket((string) $record->ticket_id);

        if ($before instanceof ErrorResponseData) {
            return $this->errorResponse($before);
        }

        $ticket = $this->support->findTicket((string) $record->ticket_id);

        if ($ticket === null) {
            return $this->error(ErrorCode::TICKET_NOT_FOUND);
        }

        $ticket = $this->support->reopenTicket($ticket);

        $after = $this->afterReopenTicket($ticket);

        if ($after instanceof ErrorResponseData) {
            return $this->errorResponse($after);
        }

        return ResponseFactory::json(DataBuilder::ticket($ticket));
    }

    /**
     * {@inheritDoc}
     */
    public function updateStatus(UpdateTicketStatusRecord $record): ResponseFactory
    {
        $before = $this->beforeUpdateStatus((string) $record->ticket_id, $record->status);

        if ($before instanceof ErrorResponseData) {
            return $this->errorResponse($before);
        }

        $ticket = $this->support->findTicket((string) $record->ticket_id);

        if ($ticket === null) {
            return $this->error(ErrorCode::TICKET_NOT_FOUND);
        }

        $ticket = $this->support->updateStatus($ticket, $record->status);

        $after = $this->afterUpdateStatus($ticket, $record->status);

        if ($after instanceof ErrorResponseData) {
            return $this->errorResponse($after);
        }

        return ResponseFactory::json(DataBuilder::ticket($ticket));
    }

    /**
     * {@inheritDoc}
     */
    public function updatePriority(UpdateTicketPriorityRecord $record): ResponseFactory
    {
        $before = $this->beforeUpdatePriority((string) $record->ticket_id, $record->priority);

        if ($before instanceof ErrorResponseData) {
            return $this->errorResponse($before);
        }

        $ticket = $this->support->findTicket((string) $record->ticket_id);

        if ($ticket === null) {
            return $this->error(ErrorCode::TICKET_NOT_FOUND);
        }

        $ticket = $this->support->updatePriority($ticket, $record->priority);

        $after = $this->afterUpdatePriority($ticket, $record->priority);

        if ($after instanceof ErrorResponseData) {
            return $this->errorResponse($after);
        }

        return ResponseFactory::json(DataBuilder::ticket($ticket));
    }

    /**
     * {@inheritDoc}
     */
    public function indexTickets(ListTicketsRecord $record): ResponseFactory
    {
        $filters = new TicketFiltersRecord(
            search: $record->search,
            status: $record->status,
            priority: $record->priority,
            created_by_type: $record->created_by_type,
            created_by_id: $record->created_by_id,
            assigned_to_type: $record->assigned_to_type,
            assigned_to_id: $record->assigned_to_id,
            is_assigned: $record->is_assigned,
            from_date: $record->from_date,
            to_date: $record->to_date,
        );

        $before = $this->beforePaginateTickets($filters, $record->per_page, $record->page);

        if ($before instanceof ErrorResponseData) {
            return $this->errorResponse($before);
        }

        $paginator = $this->support->paginateTickets($filters, $record->per_page, $record->page);

        $after = $this->afterPaginateTickets($paginator);

        if ($after instanceof ErrorResponseData) {
            return $this->errorResponse($after);
        }

        return ResponseFactory::json(DataBuilder::ticketPaginated($paginator));
    }

    /**
     * {@inheritDoc}
     */
    public function showTicket(ShowTicketRecord $record): ResponseFactory
    {
        $before = $this->beforeShowTicket((string) $record->ticket_id);

        if ($before instanceof ErrorResponseData) {
            return $this->errorResponse($before);
        }

        $ticket = $this->support->findTicket((string) $record->ticket_id);

        if ($ticket === null) {
            return $this->error(ErrorCode::TICKET_NOT_FOUND);
        }

        $after = $this->afterShowTicket($ticket);

        if ($after instanceof ErrorResponseData) {
            return $this->errorResponse($after);
        }

        return ResponseFactory::json(DataBuilder::ticket($ticket));
    }

    /**
     * {@inheritDoc}
     */
    public function storeMessage(SendMessageRecord $record): ResponseFactory
    {
        $sender = $this->helper->getCurrentAuthenticatable();

        if (! $sender instanceof NotifiableInterface) {
            return $this->error(ErrorCode::NOT_A_PARTICIPANT);
        }

        $before = $this->beforeStoreMessage(
            ticketId: (string) $record->ticket_id,
            sender: $sender,
            content: (string) $record->content,
            replyToId: $record->reply_to_id,
            isInternal: (bool) $record->is_internal,
            metadata: $record->metadata,
        );

        if ($before instanceof ErrorResponseData) {
            return $this->errorResponse($before);
        }

        $ticket = $this->support->findTicket((string) $record->ticket_id);

        if ($ticket === null) {
            return $this->error(ErrorCode::TICKET_NOT_FOUND);
        }

        $replyTo = $record->reply_to_id !== null
            ? $this->support->findMessage($record->reply_to_id)
            : null;

        try {
            $message = $this->support->addMessage(
                ticket: $ticket,
                sender: $sender,
                content: (string) $record->content,
                replyTo: $replyTo,
                isInternal: (bool) $record->is_internal,
                metadata: $record->metadata,
            );
        } catch (Throwable $e) {
            return $this->error(ErrorCode::MESSAGE_CREATION_FAILED, $e->getMessage());
        }

        $after = $this->afterStoreMessage($message);

        if ($after instanceof ErrorResponseData) {
            return $this->errorResponse($after);
        }

        return ResponseFactory::json(DataBuilder::ticketMessage($message), 201);
    }

    /**
     * {@inheritDoc}
     */
    public function indexMessages(ListMessagesRecord $record): ResponseFactory
    {
        $filters = new TicketMessageFiltersRecord(
            ticket_id: (string) $record->ticket_id,
            reply_to_id: $record->reply_to_id,
            is_reply: $record->is_reply,
            is_internal: $record->is_internal,
            sender_type: $record->sender_type,
            status: $record->status,
            search: $record->search,
            from_date: $record->from_date,
            to_date: $record->to_date,
        );

        $before = $this->beforePaginateMessages($filters, $record->per_page, $record->page);

        if ($before instanceof ErrorResponseData) {
            return $this->errorResponse($before);
        }

        $paginator = $this->support->paginateMessages($filters, $record->per_page, $record->page);

        $after = $this->afterPaginateMessages($paginator);

        if ($after instanceof ErrorResponseData) {
            return $this->errorResponse($after);
        }

        return ResponseFactory::json(DataBuilder::ticketMessagePaginated($paginator));
    }

    /**
     * {@inheritDoc}
     */
    public function threadMessages(ThreadMessagesRecord $record): ResponseFactory
    {
        $before = $this->beforeThreadMessages((string) $record->ticket_id, (bool) $record->include_internal);

        if ($before instanceof ErrorResponseData) {
            return $this->errorResponse($before);
        }

        $ticket = $this->support->findTicket((string) $record->ticket_id);

        if ($ticket === null) {
            return $this->error(ErrorCode::TICKET_NOT_FOUND);
        }

        $messages = $this->support->threadMessages($ticket, (bool) $record->include_internal);

        $after = $this->afterThreadMessages($ticket, $messages);

        if ($after instanceof ErrorResponseData) {
            return $this->errorResponse($after);
        }

        return ResponseFactory::json($this->paginateInMemory($messages, $record->per_page, $record->page));
    }

    /**
     * {@inheritDoc}
     */
    public function showMessage(ShowMessageRecord $record): ResponseFactory
    {
        $before = $this->beforeShowMessage((string) $record->ticket_id, (string) $record->message_id);

        if ($before instanceof ErrorResponseData) {
            return $this->errorResponse($before);
        }

        $message = $this->support->findMessage((string) $record->message_id);

        if ($message === null) {
            return $this->error(ErrorCode::MESSAGE_NOT_FOUND);
        }

        if ($message->ticket_id !== $record->ticket_id) {
            return $this->error(ErrorCode::MESSAGE_NOT_IN_TICKET);
        }

        $after = $this->afterShowMessage($message);

        if ($after instanceof ErrorResponseData) {
            return $this->errorResponse($after);
        }

        return ResponseFactory::json(DataBuilder::ticketMessage($message));
    }

    /**
     * {@inheritDoc}
     */
    public function markMessageDispatched(MarkMessageDispatchedRecord $record): ResponseFactory
    {
        $before = $this->beforeMarkMessageDispatched((string) $record->ticket_id, (string) $record->message_id);

        if ($before instanceof ErrorResponseData) {
            return $this->errorResponse($before);
        }

        $message = $this->support->findMessage((string) $record->message_id);

        if ($message === null) {
            return $this->error(ErrorCode::MESSAGE_NOT_FOUND);
        }

        if ($message->ticket_id !== $record->ticket_id) {
            return $this->error(ErrorCode::MESSAGE_NOT_IN_TICKET);
        }

        $message = $this->support->markMessageDispatched($message);

        $after = $this->afterMarkMessageDispatched($message);

        if ($after instanceof ErrorResponseData) {
            return $this->errorResponse($after);
        }

        return ResponseFactory::json(DataBuilder::ticketMessage($message));
    }

    /**
     * {@inheritDoc}
     */
    public function markMessageRead(MarkMessageReadRecord $record): ResponseFactory
    {
        $before = $this->beforeMarkMessageRead((string) $record->ticket_id, (string) $record->message_id);

        if ($before instanceof ErrorResponseData) {
            return $this->errorResponse($before);
        }

        $message = $this->support->findMessage((string) $record->message_id);

        if ($message === null) {
            return $this->error(ErrorCode::MESSAGE_NOT_FOUND);
        }

        if ($message->ticket_id !== $record->ticket_id) {
            return $this->error(ErrorCode::MESSAGE_NOT_IN_TICKET);
        }

        $message = $this->support->markMessageRead($message);

        $after = $this->afterMarkMessageRead($message);

        if ($after instanceof ErrorResponseData) {
            return $this->errorResponse($after);
        }

        return ResponseFactory::json(DataBuilder::ticketMessage($message));
    }

    /**
     * {@inheritDoc}
     */
    public function markAllMessagesRead(MarkAllMessagesReadRecord $record): ResponseFactory
    {
        $reader = $this->helper->getCurrentAuthenticatable();

        if ($reader === null) {
            return $this->error(ErrorCode::UNAUTHORIZED_ACTION);
        }

        $before = $this->beforeMarkAllMessagesRead((string) $record->ticket_id, $reader);

        if ($before instanceof ErrorResponseData) {
            return $this->errorResponse($before);
        }

        $ticket = $this->support->findTicket((string) $record->ticket_id);

        if ($ticket === null) {
            return $this->error(ErrorCode::TICKET_NOT_FOUND);
        }

        $count = $this->support->markAllMessagesRead($ticket, $reader);

        $after = $this->afterMarkAllMessagesRead((string) $record->ticket_id, $reader, $count);

        if ($after instanceof ErrorResponseData) {
            return $this->errorResponse($after);
        }

        return ResponseFactory::json(DataBuilder::messagesReadResult((string) $record->ticket_id, $count));
    }

    /**
     * Build an error response from an application error code.
     */
    private function error(ErrorCode $code, ?string $message = null): ResponseFactory
    {
        return $code->toJsonResponseFactory($message);
    }

    /**
     * Build a response from a pre-built error payload.
     */
    private function errorResponse(ErrorResponseData $data): ResponseFactory
    {
        return ResponseFactory::json($data, $data->status);
    }

    /**
     * Resolve a model reference from its morph type and identifier.
     *
     * Returns null when the type is not an Eloquent model class or when no
     * row matches the given identifier.
     */
    private function resolveResolver(string $type, string $id): ?Model
    {
        if (! class_exists($type) || ! is_subclass_of($type, Model::class)) {
            return null;
        }

        return $type::query()->find($id);
    }

    /**
     * Paginate an in-memory collection using the given page size.
     *
     * Wraps the result in a {@see PaginatedResponseData} compatible with the
     * standard paginator output. `next` and `previous` URLs are left null
     * because the thread endpoint has no pagination links.
     *
     * @param  Collection<int, TicketMessage>  $messages
     */
    private function paginateInMemory(Collection $messages, int $perPage, int $page): PaginatedResponseData
    {
        $all = $messages->all();
        $total = count($all);
        $lastPage = (int) max(1, (int) ceil($total / $perPage));
        $slice = array_slice($all, ($page - 1) * $perPage, $perPage);

        return PaginatedResponseData::from([
            'items' => DataBuilder::ticketMessageCollection(collect($slice)),
            'currentPage' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'lastPage' => $lastPage,
            'nextPageUrl' => null,
            'prevPageUrl' => null,
        ]);
    }

    /**
     * Hook executed before {@see self::storeTicket()}.
     *
     * @param  Model&NotifiableInterface  $creator
     */
    protected function beforeStoreTicket(Model $creator, string $title, string $description, ?TicketPriority $priority, ?StrictAssociative $metadata): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed after {@see self::storeTicket()}.
     */
    protected function afterStoreTicket(Ticket $ticket): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed before {@see self::updateTicket()}.
     */
    protected function beforeUpdateTicket(?string $ticketId, ?string $title, ?string $description, ?TicketPriority $priority): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed after {@see self::updateTicket()}.
     */
    protected function afterUpdateTicket(Ticket $ticket): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed before {@see self::destroyTicket()}.
     */
    protected function beforeDestroyTicket(string $ticketId): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed after {@see self::destroyTicket()}.
     */
    protected function afterDestroyTicket(string $ticketId): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed before {@see self::assignTicket()}.
     */
    protected function beforeAssignTicket(string $ticketId, Model $resolver): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed after {@see self::assignTicket()}.
     */
    protected function afterAssignTicket(Ticket $ticket, Model $resolver): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed before {@see self::reassignTicket()}.
     */
    protected function beforeReassignTicket(string $ticketId, Model $newResolver): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed after {@see self::reassignTicket()}.
     */
    protected function afterReassignTicket(Ticket $ticket, Model $newResolver): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed before {@see self::unassignTicket()}.
     */
    protected function beforeUnassignTicket(string $ticketId): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed after {@see self::unassignTicket()}.
     */
    protected function afterUnassignTicket(Ticket $ticket): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed before {@see self::resolveTicket()}.
     */
    protected function beforeResolveTicket(string $ticketId): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed after {@see self::resolveTicket()}.
     */
    protected function afterResolveTicket(Ticket $ticket): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed before {@see self::closeTicket()}.
     */
    protected function beforeCloseTicket(string $ticketId): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed after {@see self::closeTicket()}.
     */
    protected function afterCloseTicket(Ticket $ticket): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed before {@see self::reopenTicket()}.
     */
    protected function beforeReopenTicket(string $ticketId): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed after {@see self::reopenTicket()}.
     */
    protected function afterReopenTicket(Ticket $ticket): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed before {@see self::updateStatus()}.
     */
    protected function beforeUpdateStatus(string $ticketId, TicketStatus $status): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed after {@see self::updateStatus()}.
     */
    protected function afterUpdateStatus(Ticket $ticket, TicketStatus $status): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed before {@see self::updatePriority()}.
     */
    protected function beforeUpdatePriority(string $ticketId, TicketPriority $priority): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed after {@see self::updatePriority()}.
     */
    protected function afterUpdatePriority(Ticket $ticket, TicketPriority $priority): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed before {@see self::indexTickets()}.
     */
    protected function beforePaginateTickets(TicketFiltersRecord $filters, int $perPage, int $page): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed after {@see self::indexTickets()}.
     */
    protected function afterPaginateTickets(LengthAwarePaginator $paginator): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed before {@see self::showTicket()}.
     */
    protected function beforeShowTicket(string $ticketId): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed after {@see self::showTicket()}.
     */
    protected function afterShowTicket(Ticket $ticket): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed before {@see self::storeMessage()}.
     */
    protected function beforeStoreMessage(string $ticketId, Model $sender, string $content, ?string $replyToId, bool $isInternal, ?StrictAssociative $metadata): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed after {@see self::storeMessage()}.
     */
    protected function afterStoreMessage(TicketMessage $message): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed before {@see self::indexMessages()}.
     */
    protected function beforePaginateMessages(TicketMessageFiltersRecord $filters, int $perPage, int $page): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed after {@see self::indexMessages()}.
     */
    protected function afterPaginateMessages(LengthAwarePaginator $paginator): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed before {@see self::threadMessages()}.
     */
    protected function beforeThreadMessages(string $ticketId, bool $includeInternal): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed after {@see self::threadMessages()}.
     *
     * @param  Collection<int, TicketMessage>  $result
     */
    protected function afterThreadMessages(Ticket $ticket, Collection $result): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed before {@see self::showMessage()}.
     */
    protected function beforeShowMessage(string $ticketId, string $messageId): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed after {@see self::showMessage()}.
     */
    protected function afterShowMessage(TicketMessage $message): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed before {@see self::markMessageDispatched()}.
     */
    protected function beforeMarkMessageDispatched(string $ticketId, string $messageId): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed after {@see self::markMessageDispatched()}.
     */
    protected function afterMarkMessageDispatched(TicketMessage $message): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed before {@see self::markMessageRead()}.
     */
    protected function beforeMarkMessageRead(string $ticketId, string $messageId): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed after {@see self::markMessageRead()}.
     */
    protected function afterMarkMessageRead(TicketMessage $message): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed before {@see self::markAllMessagesRead()}.
     */
    protected function beforeMarkAllMessagesRead(string $ticketId, Model $reader): ?ErrorResponseData
    {
        return null;
    }

    /**
     * Hook executed after {@see self::markAllMessagesRead()}.
     */
    protected function afterMarkAllMessagesRead(string $ticketId, Model $reader, int $count): ?ErrorResponseData
    {
        return null;
    }
}
