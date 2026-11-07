<?php

declare(strict_types=1);

namespace AndyDefer\Support\Services;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\Repository\Enums\SortDirection;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\Records\PaginateRecord;
use AndyDefer\Repository\ValueObjects\SortColumns;
use AndyDefer\Support\Contracts\Services\SupportServiceInterface;
use AndyDefer\Support\Enums\MessageStatus;
use AndyDefer\Support\Enums\NotificationType;
use AndyDefer\Support\Enums\SenderType;
use AndyDefer\Support\Enums\TicketPriority;
use AndyDefer\Support\Enums\TicketStatus;
use AndyDefer\Support\Models\Ticket;
use AndyDefer\Support\Models\TicketMessage;
use AndyDefer\Support\Records\TicketFiltersRecord;
use AndyDefer\Support\Records\TicketMessageFiltersRecord;
use AndyDefer\Support\Records\TicketMessageRecord;
use AndyDefer\Support\Repositories\TicketMessageRepository;
use AndyDefer\Support\Repositories\TicketRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Default implementation of {@see SupportServiceInterface}.
 *
 * Owns the entire ticket lifecycle: creation, resolver assignment, status
 * transitions, message threading, and best-effort notification delivery to
 * the involved participants.
 *
 * Notifications are intentionally dispatched inside {@see self::runIsolated()}
 * so that a transport failure never rolls back an already-persisted domain
 * change.
 */
class SupportService implements SupportServiceInterface
{
    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly TicketMessageRepository $messages,
        private readonly NotificationService $notifier,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function createTicket(
        Model $creator,
        string $title,
        string $description,
        ?TicketPriority $priority = null,
        ?StrictAssociative $metadata = null,
    ): Ticket {
        /** @var Ticket $ticket */
        $ticket = $this->tickets->createRaw([
            'reference' => $this->generateReference(),
            'title' => $title,
            'description' => $description,
            'created_by_type' => $creator->getMorphClass(),
            'created_by_id' => (string) $creator->getKey(),
            'assigned_to_type' => null,
            'assigned_to_id' => null,
            'status' => TicketStatus::OPEN,
            'priority' => $priority ?? TicketPriority::NORMAL,
            'assigned_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'metadata' => $metadata?->toArray(),
        ]);

        return $ticket;
    }

    /**
     * {@inheritDoc}
     */
    public function assignTicket(Ticket $ticket, Model $resolver): Ticket
    {
        $previousResolver = $ticket->assignedTo;

        $ticket = $this->replaceResolver($ticket, $resolver);

        $this->runIsolated(
            channel: 'assigned',
            callback: fn () => $this->notify(
                recipient: $resolver,
                type: NotificationType::TICKET_ASSIGNED,
                body: "Ticket {$ticket->reference} vous a été assigné",
                ticket: $ticket,
            ),
        );

        if ($previousResolver !== null && ! $this->isSameModel($previousResolver, $resolver)) {
            $this->runIsolated(
                channel: 'reassigned',
                callback: fn () => $this->notify(
                    recipient: $previousResolver,
                    type: NotificationType::TICKET_REASSIGNED,
                    body: "Ticket {$ticket->reference} a changé de résolveur",
                    ticket: $ticket,
                ),
            );
        }

        return $ticket;
    }

    /**
     * {@inheritDoc}
     */
    public function changeResolver(Ticket $ticket, Model $newResolver): Ticket
    {
        $previousResolver = $ticket->assignedTo;

        $ticket = $this->replaceResolver($ticket, $newResolver);

        if ($previousResolver !== null && ! $this->isSameModel($previousResolver, $newResolver)) {
            $this->runIsolated(
                channel: 'reassigned',
                callback: fn () => $this->notify(
                    recipient: $previousResolver,
                    type: NotificationType::TICKET_REASSIGNED,
                    body: "Ticket {$ticket->reference} ne vous est plus assigné",
                    ticket: $ticket,
                ),
            );
        }

        $this->runIsolated(
            channel: 'assigned',
            callback: fn () => $this->notify(
                recipient: $newResolver,
                type: NotificationType::TICKET_ASSIGNED,
                body: "Ticket {$ticket->reference} vous a été assigné",
                ticket: $ticket,
            ),
        );

        return $ticket;
    }

    /**
     * {@inheritDoc}
     */
    public function unassignTicket(Ticket $ticket): Ticket
    {
        $previousResolver = $ticket->assignedTo;

        /** @var Ticket $ticket */
        $ticket = $this->tickets->updateRaw($ticket->id, [
            'assigned_to_type' => null,
            'assigned_to_id' => null,
            'assigned_at' => null,
        ]);

        if ($previousResolver !== null) {
            $this->runIsolated(
                channel: 'unassigned',
                callback: fn () => $this->notify(
                    recipient: $previousResolver,
                    type: NotificationType::TICKET_UNASSIGNED,
                    body: "Ticket {$ticket->reference} vous a été retiré",
                    ticket: $ticket,
                ),
            );
        }

        return $ticket;
    }

    /**
     * {@inheritDoc}
     */
    public function updateStatus(Ticket $ticket, TicketStatus $status): Ticket
    {
        /** @var Ticket $ticket */
        $ticket = $this->tickets->updateRaw($ticket->id, ['status' => $status]);

        $this->runIsolated(
            channel: 'status',
            callback: fn () => $this->notifyParticipants(
                ticket: $ticket,
                type: NotificationType::TICKET_STATUS_CHANGED,
                body: "Statut du ticket {$ticket->reference} : {$status->getLabel()}",
            ),
        );

        return $ticket;
    }

    /**
     * {@inheritDoc}
     */
    public function updatePriority(Ticket $ticket, TicketPriority $priority): Ticket
    {
        /** @var Ticket $ticket */
        $ticket = $this->tickets->updateRaw($ticket->id, ['priority' => $priority]);

        $this->runIsolated(
            channel: 'priority',
            callback: fn () => $this->notifyParticipants(
                ticket: $ticket,
                type: NotificationType::TICKET_PRIORITY_CHANGED,
                body: "Priorité du ticket {$ticket->reference} : {$priority->getLabel()}",
            ),
        );

        return $ticket;
    }

    /**
     * {@inheritDoc}
     */
    public function resolveTicket(Ticket $ticket): Ticket
    {
        /** @var Ticket $ticket */
        $ticket = $this->tickets->updateRaw($ticket->id, [
            'status' => TicketStatus::RESOLVED,
            'resolved_at' => now(),
        ]);

        $this->runIsolated(
            channel: 'resolved',
            callback: fn () => $this->notifyParticipants(
                ticket: $ticket,
                type: NotificationType::TICKET_RESOLVED,
                body: "Ticket {$ticket->reference} résolu",
            ),
        );

        return $ticket;
    }

    /**
     * {@inheritDoc}
     */
    public function closeTicket(Ticket $ticket): Ticket
    {
        /** @var Ticket $ticket */
        $ticket = $this->tickets->updateRaw($ticket->id, [
            'status' => TicketStatus::CLOSED,
            'closed_at' => now(),
        ]);

        $this->runIsolated(
            channel: 'closed',
            callback: fn () => $this->notifyParticipants(
                ticket: $ticket,
                type: NotificationType::TICKET_CLOSED,
                body: "Ticket {$ticket->reference} fermé",
            ),
        );

        return $ticket;
    }

    /**
     * {@inheritDoc}
     */
    public function reopenTicket(Ticket $ticket): Ticket
    {
        /** @var Ticket $ticket */
        $ticket = $this->tickets->updateRaw($ticket->id, [
            'status' => TicketStatus::OPEN,
            'resolved_at' => null,
            'closed_at' => null,
        ]);

        $this->runIsolated(
            channel: 'reopened',
            callback: fn () => $this->notifyParticipants(
                ticket: $ticket,
                type: NotificationType::TICKET_REOPENED,
                body: "Ticket {$ticket->reference} rouvert",
            ),
        );

        return $ticket;
    }

    /**
     * {@inheritDoc}
     */
    public function listTickets(TicketFiltersRecord $filters, int $limit = 50): Collection
    {
        return $this->tickets->findBy(new FindByRecord(
            filters: $filters,
            sortBy: new SortColumns('created_at:desc'),
            limit: $limit,
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function paginateTickets(
        TicketFiltersRecord $filters,
        int $perPage = 15,
        int $page = 1,
    ): LengthAwarePaginator {
        return $this->tickets->paginate(new PaginateRecord(
            perPage: $perPage,
            page: $page,
            sortBy: 'created_at',
            sortDir: SortDirection::DESC,
            filters: $filters,
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function findTicket(string $ticketId): ?Ticket
    {
        /** @var Ticket|null $ticket */
        $ticket = $this->tickets->find($ticketId);

        return $ticket;
    }

    /**
     * {@inheritDoc}
     */
    public function findTicketByReference(string $reference): ?Ticket
    {
        return Ticket::query()->where('reference', $reference)->first();
    }

    /**
     * {@inheritDoc}
     */
    public function countTickets(TicketFiltersRecord $filters): int
    {
        return $this->tickets->count($filters);
    }

    /**
     * {@inheritDoc}
     */
    public function addMessage(
        Ticket $ticket,
        Model $sender,
        string $content,
        ?TicketMessage $replyTo = null,
        bool $isInternal = false,
        ?StrictAssociative $metadata = null,
    ): TicketMessage {
        $senderType = $this->resolveSenderType($ticket, $sender);

        /** @var TicketMessage $message */
        $message = DB::transaction(
            fn (): TicketMessage => $this->messages->create(new TicketMessageRecord(
                ticket_id: $ticket->id,
                reply_to_id: $replyTo?->id,
                sender_type: $senderType,
                content: $content,
                status: MessageStatus::SENT,
                is_internal: $isInternal,
                metadata: $metadata,
            )),
        );

        $type = $replyTo !== null
            ? NotificationType::TICKET_MESSAGE_REPLY
            : NotificationType::TICKET_MESSAGE;

        $this->runIsolated(
            channel: 'message',
            callback: fn () => $this->notifyMessageParticipants(
                ticket: $ticket,
                sender: $sender,
                message: $message,
                type: $type,
            ),
        );

        return $message;
    }

    /**
     * {@inheritDoc}
     */
    public function listMessages(
        TicketMessageFiltersRecord $filters,
        int $limit = 100,
    ): Collection {
        return $this->messages->findBy(new FindByRecord(
            filters: $filters,
            sortBy: new SortColumns('created_at:asc'),
            limit: $limit,
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function paginateMessages(
        TicketMessageFiltersRecord $filters,
        int $perPage = 30,
        int $page = 1,
    ): LengthAwarePaginator {
        return $this->messages->paginate(new PaginateRecord(
            perPage: $perPage,
            page: $page,
            sortBy: 'created_at',
            sortDir: SortDirection::ASC,
            filters: $filters,
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function threadMessages(Ticket $ticket, bool $includeInternal = false): Collection
    {
        $query = TicketMessage::query()
            ->where('ticket_id', $ticket->id)
            ->orderBy('created_at');

        if (! $includeInternal) {
            $query->where('is_internal', false);
        }

        return $query->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findMessage(string $messageId): ?TicketMessage
    {
        /** @var TicketMessage|null $message */
        $message = $this->messages->find($messageId);

        return $message;
    }

    /**
     * {@inheritDoc}
     */
    public function countMessages(TicketMessageFiltersRecord $filters): int
    {
        return $this->messages->count($filters);
    }

    /**
     * {@inheritDoc}
     */
    public function markMessageDispatched(TicketMessage $message): TicketMessage
    {
        /** @var TicketMessage $message */
        $message = $this->messages->updateRaw($message->id, [
            'status' => MessageStatus::DISPATCHED,
        ]);

        return $message;
    }

    /**
     * {@inheritDoc}
     */
    public function markMessageRead(TicketMessage $message): TicketMessage
    {
        /** @var TicketMessage $message */
        $message = $this->messages->updateRaw($message->id, [
            'status' => MessageStatus::READ,
        ]);

        return $message;
    }

    /**
     * {@inheritDoc}
     */
    public function markAllMessagesRead(Ticket $ticket, Model $reader): int
    {
        return TicketMessage::query()
            ->where('ticket_id', $ticket->id)
            ->where('status', '!=', MessageStatus::READ->value)
            ->update(['status' => MessageStatus::READ->value]);
    }

    /**
     * Persist a new resolver on the ticket.
     *
     * Extracted to avoid duplicating the same raw update in
     * {@see self::assignTicket()} and {@see self::changeResolver()}.
     */
    private function replaceResolver(Ticket $ticket, Model $resolver): Ticket
    {
        /** @var Ticket $ticket */
        $ticket = $this->tickets->updateRaw($ticket->id, [
            'assigned_to_type' => $resolver->getMorphClass(),
            'assigned_to_id' => (string) $resolver->getKey(),
            'assigned_at' => now(),
        ]);

        return $ticket;
    }

    /**
     * Send a database notification about a ticket to a single recipient.
     *
     * Silently no-ops when the recipient is not notifiable.
     */
    private function notify(
        Model $recipient,
        NotificationType $type,
        string $body,
        Ticket $ticket,
    ): void {
        if (! $recipient instanceof NotifiableInterface) {
            return;
        }

        $this->notifier->sendNow(
            $recipient,
            NotificationMessageVO::from([
                'body' => $body,
                'subject' => $ticket->title,
                'type' => $type->value,
                'data' => [
                    'ticket_id' => $ticket->id,
                    'ticket_reference' => $ticket->reference,
                    'ticket_title' => $ticket->title,
                    'notification_type' => $type->value,
                ],
            ]),
            SendNowRecord::from([
                'channels' => [DatabaseChannel::class],
                'limit_per_channel' => 1,
            ]),
        );
    }

    /**
     * Notify every participant attached to the ticket.
     */
    private function notifyParticipants(
        Ticket $ticket,
        NotificationType $type,
        string $body,
    ): void {
        foreach ($this->participantsOf($ticket) as $participant) {
            $this->notify(
                recipient: $participant,
                type: $type,
                body: $body,
                ticket: $ticket,
            );
        }
    }

    /**
     * Notify every participant except the given sender about a new message.
     */
    private function notifyMessageParticipants(
        Ticket $ticket,
        Model $sender,
        TicketMessage $message,
        NotificationType $type,
    ): void {
        foreach ($this->participantsOf($ticket) as $participant) {
            if ($this->isSameModel($participant, $sender)) {
                continue;
            }

            $this->notify(
                recipient: $participant,
                type: $type,
                body: sprintf(
                    'Nouveau message de %s sur le ticket %s : %s',
                    $sender->getKey(),
                    $ticket->reference,
                    $this->truncate($message->content, 120),
                ),
                ticket: $ticket,
            );
        }
    }

    /**
     * Return the ticket participants that are actual models (creator + resolver).
     *
     * @return array<int, Model>
     */
    private function participantsOf(Ticket $ticket): array
    {
        return array_values(array_filter([
            $ticket->createdBy,
            $ticket->assignedTo,
        ]));
    }

    /**
     * Execute the given callback and swallow any thrown exception.
     *
     * Notifications are best-effort: a delivery failure must never
     * invalidate a successful domain write.
     */
    private function runIsolated(string $channel, callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable) {
            // Notification failures are not propagated by design.
        }
    }

    /**
     * Determine the sender role of a message relative to its ticket.
     */
    private function resolveSenderType(Ticket $ticket, Model $sender): SenderType
    {
        $creator = $ticket->createdBy;

        if ($creator !== null && $this->isSameModel($creator, $sender)) {
            return SenderType::CREATOR;
        }

        return SenderType::RESOLVER;
    }

    /**
     * Check whether two models point to the same underlying database row.
     */
    private function isSameModel(Model $a, Model $b): bool
    {
        return $a->getMorphClass() === $b->getMorphClass()
            && (string) $a->getKey() === (string) $b->getKey();
    }

    /**
     * Generate a unique human-readable ticket reference.
     */
    private function generateReference(): string
    {
        do {
            $reference = 'TKT-'.strtoupper(Str::random(10));
        } while (Ticket::query()->where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Truncate a string to the given maximum length, appending an ellipsis.
     */
    private function truncate(string $value, int $max): string
    {
        return mb_strlen($value) <= $max
            ? $value
            : mb_substr($value, 0, $max - 3).'...';
    }
}
