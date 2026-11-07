<?php

declare(strict_types=1);

namespace AndyDefer\Support\Contracts\Services;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\Support\Enums\TicketPriority;
use AndyDefer\Support\Enums\TicketStatus;
use AndyDefer\Support\Models\Ticket;
use AndyDefer\Support\Models\TicketMessage;
use AndyDefer\Support\Records\TicketFiltersRecord;
use AndyDefer\Support\Records\TicketMessageFiltersRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Contract for the ticket management domain service.
 *
 * Encapsulates the full lifecycle of support tickets and their messages,
 * from creation to closure, along with the associated notification side
 * effects targeted at the ticket participants.
 */
interface SupportServiceInterface
{
    /**
     * Open a new support ticket on behalf of the given creator.
     *
     * @param  Model&NotifiableInterface  $creator  The actor opening the ticket.
     * @param  string  $title  Short human-readable subject.
     * @param  string  $description  Detailed problem description.
     * @param  TicketPriority|null  $priority  Optional initial priority (defaults to normal).
     * @param  StrictAssociative|null  $metadata  Arbitrary contextual key/value payload.
     * @return Ticket The freshly persisted ticket.
     */
    public function createTicket(
        Model $creator,
        string $title,
        string $description,
        ?TicketPriority $priority = null,
        ?StrictAssociative $metadata = null,
    ): Ticket;

    /**
     * Assign an unassigned ticket to a resolver and notify both parties.
     *
     * @param  Model&NotifiableInterface  $resolver
     */
    public function assignTicket(Ticket $ticket, Model $resolver): Ticket;

    /**
     * Replace the current resolver with another one, notifying both.
     *
     * @param  Model&NotifiableInterface  $newResolver
     */
    public function changeResolver(Ticket $ticket, Model $newResolver): Ticket;

    /**
     * Clear the current resolver and notify them that they were removed.
     */
    public function unassignTicket(Ticket $ticket): Ticket;

    /**
     * Change the ticket status and notify all participants.
     */
    public function updateStatus(Ticket $ticket, TicketStatus $status): Ticket;

    /**
     * Change the ticket priority and notify all participants.
     */
    public function updatePriority(Ticket $ticket, TicketPriority $priority): Ticket;

    /**
     * Mark the ticket as resolved and notify all participants.
     */
    public function resolveTicket(Ticket $ticket): Ticket;

    /**
     * Mark the ticket as closed and notify all participants.
     */
    public function closeTicket(Ticket $ticket): Ticket;

    /**
     * Reopen a resolved or closed ticket and notify all participants.
     */
    public function reopenTicket(Ticket $ticket): Ticket;

    /**
     * Retrieve a flat, limited list of tickets matching the given filters.
     *
     * @return Collection<int, Ticket>
     */
    public function listTickets(TicketFiltersRecord $filters, int $limit = 50): Collection;

    /**
     * Retrieve a paginated set of tickets matching the given filters.
     */
    public function paginateTickets(
        TicketFiltersRecord $filters,
        int $perPage = 15,
        int $page = 1,
    ): LengthAwarePaginator;

    /**
     * Find a ticket by its primary key.
     */
    public function findTicket(string $ticketId): ?Ticket;

    /**
     * Find a ticket by its human-readable reference (e.g. "TKT-AB12CD34EF").
     */
    public function findTicketByReference(string $reference): ?Ticket;

    /**
     * Count tickets matching the given filters.
     */
    public function countTickets(TicketFiltersRecord $filters): int;

    /**
     * Append a message to a ticket and notify the other participants.
     *
     * @param  Model&NotifiableInterface  $sender
     */
    public function addMessage(
        Ticket $ticket,
        Model $sender,
        string $content,
        ?TicketMessage $replyTo = null,
        bool $isInternal = false,
        ?StrictAssociative $metadata = null,
    ): TicketMessage;

    /**
     * Retrieve a flat, limited list of messages matching the given filters.
     *
     * @return Collection<int, TicketMessage>
     */
    public function listMessages(
        TicketMessageFiltersRecord $filters,
        int $limit = 100,
    ): Collection;

    /**
     * Retrieve a paginated set of messages matching the given filters.
     */
    public function paginateMessages(
        TicketMessageFiltersRecord $filters,
        int $perPage = 30,
        int $page = 1,
    ): LengthAwarePaginator;

    /**
     * Retrieve the full chronological thread for a ticket.
     *
     * @param  bool  $includeInternal  Whether to include internal messages.
     * @return Collection<int, TicketMessage>
     */
    public function threadMessages(Ticket $ticket, bool $includeInternal = false): Collection;

    /**
     * Find a message by its primary key.
     */
    public function findMessage(string $messageId): ?TicketMessage;

    /**
     * Count messages matching the given filters.
     */
    public function countMessages(TicketMessageFiltersRecord $filters): int;

    /**
     * Mark the given message as dispatched by the notification transport.
     */
    public function markMessageDispatched(TicketMessage $message): TicketMessage;

    /**
     * Mark the given message as read.
     */
    public function markMessageRead(TicketMessage $message): TicketMessage;

    /**
     * Mark every non-read message of a ticket as read for the given reader.
     *
     * @return int Number of messages updated.
     */
    public function markAllMessagesRead(Ticket $ticket, Model $reader): int;
}
