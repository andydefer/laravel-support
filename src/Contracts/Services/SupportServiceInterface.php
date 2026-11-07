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

interface SupportServiceInterface
{
    // ══════════════════════════════════════════════════════════════════
    // TICKETS — cycle de vie
    // ══════════════════════════════════════════════════════════════════

    /**
     * @param  Model&NotifiableInterface  $creator
     */
    public function createTicket(
        Model $creator,
        string $title,
        string $description,
        ?TicketPriority $priority = null,
        ?StrictAssociative $metadata = null,
    ): Ticket;

    /**
     * @param  Model&NotifiableInterface  $resolver
     */
    public function assignTicket(Ticket $ticket, Model $resolver): Ticket;

    /**
     * @param  Model&NotifiableInterface  $newResolver
     */
    public function changeResolver(Ticket $ticket, Model $newResolver): Ticket;

    public function unassignTicket(Ticket $ticket): Ticket;

    public function updateStatus(Ticket $ticket, TicketStatus $status): Ticket;

    public function updatePriority(Ticket $ticket, TicketPriority $priority): Ticket;

    public function resolveTicket(Ticket $ticket): Ticket;

    public function closeTicket(Ticket $ticket): Ticket;

    public function reopenTicket(Ticket $ticket): Ticket;

    // ══════════════════════════════════════════════════════════════════
    // TICKETS — lecture
    // ══════════════════════════════════════════════════════════════════

    /**
     * @return Collection<int, Ticket>
     */
    public function listTickets(TicketFiltersRecord $filters, int $limit = 50): Collection;

    public function paginateTickets(
        TicketFiltersRecord $filters,
        int $perPage = 15,
        int $page = 1,
    ): LengthAwarePaginator;

    public function findTicket(string $ticketId): ?Ticket;

    public function findTicketByReference(string $reference): ?Ticket;

    public function countTickets(TicketFiltersRecord $filters): int;

    // ══════════════════════════════════════════════════════════════════
    // MESSAGES — création
    // ══════════════════════════════════════════════════════════════════

    /**
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

    // ══════════════════════════════════════════════════════════════════
    // MESSAGES — lecture
    // ══════════════════════════════════════════════════════════════════

    /**
     * @return Collection<int, TicketMessage>
     */
    public function listMessages(
        TicketMessageFiltersRecord $filters,
        int $limit = 100,
    ): Collection;

    public function paginateMessages(
        TicketMessageFiltersRecord $filters,
        int $perPage = 30,
        int $page = 1,
    ): LengthAwarePaginator;

    /**
     * @return Collection<int, TicketMessage>
     */
    public function threadMessages(Ticket $ticket, bool $includeInternal = false): Collection;

    public function findMessage(string $messageId): ?TicketMessage;

    public function countMessages(TicketMessageFiltersRecord $filters): int;

    // ══════════════════════════════════════════════════════════════════
    // MESSAGES — statut
    // ══════════════════════════════════════════════════════════════════

    public function markMessageDispatched(TicketMessage $message): TicketMessage;

    public function markMessageRead(TicketMessage $message): TicketMessage;

    public function markAllMessagesRead(Ticket $ticket, Model $reader): int;
}
