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

final class SupportService implements SupportServiceInterface
{
    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly TicketMessageRepository $messages,
        private readonly NotificationService $notifier,
    ) {}

    // ══════════════════════════════════════════════════════════════════
    // TICKETS — cycle de vie
    // ══════════════════════════════════════════════════════════════════

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

    public function assignTicket(Ticket $ticket, Model $resolver): Ticket
    {
        $previousResolver = $ticket->assignedTo;

        /** @var Ticket $ticket */
        $ticket = $this->tickets->updateRaw($ticket->id, [
            'assigned_to_type' => $resolver->getMorphClass(),
            'assigned_to_id' => (string) $resolver->getKey(),
            'assigned_at' => now(),
        ]);

        $this->runIsolated(
            channel: 'assigned',
            callback: fn () => $this->notify(
                $resolver,
                NotificationType::TICKET_ASSIGNED,
                "Ticket {$ticket->reference} vous a été assigné",
                $ticket,
            ),
        );

        if ($previousResolver !== null && ! $this->isSameModel($previousResolver, $resolver)) {
            $this->runIsolated(
                channel: 'reassigned',
                callback: fn () => $this->notify(
                    $previousResolver,
                    NotificationType::TICKET_REASSIGNED,
                    "Ticket {$ticket->reference} a changé de résolveur",
                    $ticket,
                ),
            );
        }

        return $ticket;
    }

    public function changeResolver(Ticket $ticket, Model $newResolver): Ticket
    {
        $previousResolver = $ticket->assignedTo;

        /** @var Ticket $ticket */
        $ticket = $this->tickets->updateRaw($ticket->id, [
            'assigned_to_type' => $newResolver->getMorphClass(),
            'assigned_to_id' => (string) $newResolver->getKey(),
            'assigned_at' => now(),
        ]);

        if ($previousResolver !== null && ! $this->isSameModel($previousResolver, $newResolver)) {
            $this->runIsolated(
                channel: 'reassigned',
                callback: fn () => $this->notify(
                    $previousResolver,
                    NotificationType::TICKET_REASSIGNED,
                    "Ticket {$ticket->reference} ne vous est plus assigné",
                    $ticket,
                ),
            );
        }

        $this->runIsolated(
            channel: 'assigned',
            callback: fn () => $this->notify(
                $newResolver,
                NotificationType::TICKET_ASSIGNED,
                "Ticket {$ticket->reference} vous a été assigné",
                $ticket,
            ),
        );

        return $ticket;
    }

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
                    $previousResolver,
                    NotificationType::TICKET_UNASSIGNED,
                    "Ticket {$ticket->reference} vous a été retiré",
                    $ticket,
                ),
            );
        }

        return $ticket;
    }

    public function updateStatus(Ticket $ticket, TicketStatus $status): Ticket
    {
        /** @var Ticket $ticket */
        $ticket = $this->tickets->updateRaw($ticket->id, ['status' => $status]);

        $this->runIsolated(
            channel: 'status',
            callback: fn () => $this->notifyParticipants(
                $ticket,
                NotificationType::TICKET_STATUS_CHANGED,
                "Statut du ticket {$ticket->reference} : {$status->getLabel()}",
            ),
        );

        return $ticket;
    }

    public function updatePriority(Ticket $ticket, TicketPriority $priority): Ticket
    {
        /** @var Ticket $ticket */
        $ticket = $this->tickets->updateRaw($ticket->id, ['priority' => $priority]);

        $this->runIsolated(
            channel: 'priority',
            callback: fn () => $this->notifyParticipants(
                $ticket,
                NotificationType::TICKET_PRIORITY_CHANGED,
                "Priorité du ticket {$ticket->reference} : {$priority->getLabel()}",
            ),
        );

        return $ticket;
    }

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
                $ticket,
                NotificationType::TICKET_RESOLVED,
                "Ticket {$ticket->reference} résolu",
            ),
        );

        return $ticket;
    }

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
                $ticket,
                NotificationType::TICKET_CLOSED,
                "Ticket {$ticket->reference} fermé",
            ),
        );

        return $ticket;
    }

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
                $ticket,
                NotificationType::TICKET_REOPENED,
                "Ticket {$ticket->reference} rouvert",
            ),
        );

        return $ticket;
    }

    // ══════════════════════════════════════════════════════════════════
    // TICKETS — lecture
    // ══════════════════════════════════════════════════════════════════

    public function listTickets(TicketFiltersRecord $filters, int $limit = 50): Collection
    {
        return $this->tickets->findBy(new FindByRecord(
            filters: $filters,
            sortBy: new SortColumns('created_at:desc'),
            limit: $limit,
        ));
    }

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

    public function findTicket(string $ticketId): ?Ticket
    {
        /** @var Ticket|null $ticket */
        $ticket = $this->tickets->find($ticketId);

        return $ticket;
    }

    public function findTicketByReference(string $reference): ?Ticket
    {
        return Ticket::query()->where('reference', $reference)->first();
    }

    public function countTickets(TicketFiltersRecord $filters): int
    {
        return $this->tickets->count($filters);
    }

    // ══════════════════════════════════════════════════════════════════
    // MESSAGES — création
    // ══════════════════════════════════════════════════════════════════

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
        $message = DB::transaction(function () use ($ticket, $senderType, $content, $replyTo, $isInternal, $metadata): TicketMessage {
            return $this->messages->create(new TicketMessageRecord(
                ticket_id: $ticket->id,
                reply_to_id: $replyTo?->id,
                sender_type: $senderType,
                content: $content,
                status: MessageStatus::SENT,
                is_internal: $isInternal,
                metadata: $metadata,
            ));
        });

        $type = $replyTo !== null
            ? NotificationType::TICKET_MESSAGE_REPLY
            : NotificationType::TICKET_MESSAGE;

        $this->runIsolated(
            channel: 'message',
            callback: fn () => $this->notifyMessageParticipants($ticket, $sender, $message, $type),
        );

        return $message;
    }

    // ══════════════════════════════════════════════════════════════════
    // MESSAGES — lecture
    // ══════════════════════════════════════════════════════════════════

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

    public function findMessage(string $messageId): ?TicketMessage
    {
        /** @var TicketMessage|null $message */
        $message = $this->messages->find($messageId);

        return $message;
    }

    public function countMessages(TicketMessageFiltersRecord $filters): int
    {
        return $this->messages->count($filters);
    }

    // ══════════════════════════════════════════════════════════════════
    // MESSAGES — statut
    // ══════════════════════════════════════════════════════════════════

    public function markMessageDispatched(TicketMessage $message): TicketMessage
    {
        /** @var TicketMessage $message */
        $message = $this->messages->updateRaw($message->id, [
            'status' => MessageStatus::DISPATCHED,
        ]);

        return $message;
    }

    public function markMessageRead(TicketMessage $message): TicketMessage
    {
        /** @var TicketMessage $message */
        $message = $this->messages->updateRaw($message->id, [
            'status' => MessageStatus::READ,
        ]);

        return $message;
    }

    public function markAllMessagesRead(Ticket $ticket, Model $reader): int
    {
        return TicketMessage::query()
            ->where('ticket_id', $ticket->id)
            ->where('status', '!=', MessageStatus::READ->value)
            ->update(['status' => MessageStatus::READ->value]);
    }

    // ══════════════════════════════════════════════════════════════════
    // NOTIFICATIONS — DatabaseChannel uniquement
    // ══════════════════════════════════════════════════════════════════

    private function notify(
        Model $recipient,
        NotificationType $type,
        string $body,
        Ticket $ticket,
    ): void {
        if (! $recipient instanceof NotifiableInterface) {
            return;
        }

        $data = [
            'ticket_id' => $ticket->id,
            'ticket_reference' => $ticket->reference,
            'ticket_title' => $ticket->title,
            'notification_type' => $type->value,
        ];

        $this->notifier->sendNow(
            $recipient,
            NotificationMessageVO::from([
                'body' => $body,
                'subject' => $ticket->title,
                'type' => $type->value,
                'data' => $data,
            ]),
            SendNowRecord::from([
                'channels' => [DatabaseChannel::class],
                'limit_per_channel' => 1,
            ]),
        );
    }

    private function notifyParticipants(
        Ticket $ticket,
        NotificationType $type,
        string $body,
    ): void {
        $participants = array_filter([
            $ticket->createdBy,
            $ticket->assignedTo,
        ]);

        foreach ($participants as $participant) {
            $this->notify($participant, $type, $body, $ticket);
        }
    }

    private function notifyMessageParticipants(
        Ticket $ticket,
        Model $sender,
        TicketMessage $message,
        NotificationType $type,
    ): void {
        $participants = array_filter([
            $ticket->createdBy,
            $ticket->assignedTo,
        ]);

        foreach ($participants as $participant) {
            if ($this->isSameModel($participant, $sender)) {
                continue;
            }

            $body = sprintf(
                'Nouveau message de %s sur le ticket %s : %s',
                $sender->getKey(),
                $ticket->reference,
                $this->truncate($message->content, 120),
            );

            $this->notify($participant, $type, $body, $ticket);
        }
    }

    // ══════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════

    private function runIsolated(string $channel, callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            // swallow — notification is best-effort, DB write already done
        }
    }

    private function resolveSenderType(Ticket $ticket, Model $sender): SenderType
    {
        $creator = $ticket->createdBy;

        if ($creator !== null && $this->isSameModel($creator, $sender)) {
            return SenderType::CREATOR;
        }

        return SenderType::RESOLVER;
    }

    private function isSameModel(Model $a, Model $b): bool
    {
        return $a->getMorphClass() === $b->getMorphClass()
            && (string) $a->getKey() === (string) $b->getKey();
    }

    private function generateReference(): string
    {
        do {
            $reference = 'TKT-'.strtoupper(Str::random(10));
        } while (Ticket::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function truncate(string $value, int $max): string
    {
        return mb_strlen($value) <= $max
            ? $value
            : mb_substr($value, 0, $max - 3).'...';
    }
}
