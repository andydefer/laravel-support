<?php

declare(strict_types=1);

namespace AndyDefer\Support\Support;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\Support\Collections\TicketDataCollection;
use AndyDefer\Support\Collections\TicketMessageDataCollection;
use AndyDefer\Support\Datas\MessagesReadResultData;
use AndyDefer\Support\Datas\PaginatedResponseData;
use AndyDefer\Support\Datas\TicketData;
use AndyDefer\Support\Datas\TicketDeletedData;
use AndyDefer\Support\Datas\TicketMessageData;
use AndyDefer\Support\Models\Ticket;
use AndyDefer\Support\Models\TicketMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class DataBuilder
{
    // ══════════════════════════════════════════════════════════════════
    // TICKETS
    // ══════════════════════════════════════════════════════════════════

    public static function ticket(Ticket $ticket): TicketData
    {
        return TicketData::from([
            'id' => $ticket->id,
            'reference' => $ticket->reference,
            'title' => $ticket->title,
            'description' => $ticket->description,
            'createdByType' => $ticket->created_by_type,
            'createdById' => (string) $ticket->created_by_id,
            'assignedToType' => $ticket->assigned_to_type,
            'assignedToId' => $ticket->assigned_to_id !== null
                ? (string) $ticket->assigned_to_id
                : null,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'assignedAt' => $ticket->assigned_at,
            'resolvedAt' => $ticket->resolved_at,
            'closedAt' => $ticket->closed_at,
            'metadata' => self::strictAssociative($ticket->metadata),
            'createdAt' => $ticket->created_at,
            'updatedAt' => $ticket->updated_at,
        ]);
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     */
    public static function ticketCollection(Collection $tickets): TicketDataCollection
    {
        $collection = new TicketDataCollection;

        foreach ($tickets as $ticket) {
            $collection->add(self::ticket($ticket));
        }

        return $collection;
    }

    public static function ticketPaginated(LengthAwarePaginator $paginator): PaginatedResponseData
    {
        return PaginatedResponseData::from([
            'items' => self::ticketCollection(collect($paginator->items())),
            'currentPage' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'lastPage' => $paginator->lastPage(),
            'nextPageUrl' => $paginator->nextPageUrl(),
            'prevPageUrl' => $paginator->previousPageUrl(),
        ]);
    }

    public static function ticketDeleted(string $ticketId): TicketDeletedData
    {
        return TicketDeletedData::from([
            'ticketId' => $ticketId,
            'deleted' => true,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // MESSAGES
    // ══════════════════════════════════════════════════════════════════

    public static function ticketMessage(TicketMessage $message): TicketMessageData
    {
        return TicketMessageData::from([
            'id' => $message->id,
            'ticketId' => $message->ticket_id,
            'replyToId' => $message->reply_to_id,
            'senderType' => $message->sender_type,
            'content' => $message->content,
            'status' => $message->status,
            'isInternal' => $message->is_internal,
            'metadata' => self::strictAssociative($message->metadata),
            'createdAt' => $message->created_at,
            'updatedAt' => $message->updated_at,
        ]);
    }

    /**
     * @param  Collection<int, TicketMessage>  $messages
     */
    public static function ticketMessageCollection(Collection $messages): TicketMessageDataCollection
    {
        $collection = new TicketMessageDataCollection;

        foreach ($messages as $message) {
            $collection->add(self::ticketMessage($message));
        }

        return $collection;
    }

    public static function ticketMessagePaginated(LengthAwarePaginator $paginator): PaginatedResponseData
    {
        return PaginatedResponseData::from([
            'items' => self::ticketMessageCollection(collect($paginator->items())),
            'currentPage' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'lastPage' => $paginator->lastPage(),
            'nextPageUrl' => $paginator->nextPageUrl(),
            'prevPageUrl' => $paginator->previousPageUrl(),
        ]);
    }

    public static function messagesReadResult(string $ticketId, int $markedCount): MessagesReadResultData
    {
        return MessagesReadResultData::from([
            'ticketId' => $ticketId,
            'markedCount' => $markedCount,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════

    /**
     * @param  array<string, mixed>|null  $data
     */
    private static function strictAssociative(?array $data): ?StrictAssociative
    {
        return $data !== null
            ? StrictAssociative::from($data)
            : null;
    }
}
