<?php

declare(strict_types=1);

namespace AndyDefer\Support\Enums;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\Support\Requests\Messages\ListMessagesRequest;
use AndyDefer\Support\Requests\Messages\MarkAllMessagesReadRequest;
use AndyDefer\Support\Requests\Messages\MarkMessageDispatchedRequest;
use AndyDefer\Support\Requests\Messages\MarkMessageReadRequest;
use AndyDefer\Support\Requests\Messages\SendMessageRequest;
use AndyDefer\Support\Requests\Messages\ShowMessageRequest;
use AndyDefer\Support\Requests\Messages\ThreadMessagesRequest;
use AndyDefer\Support\Requests\Tickets\AssignTicketRequest;
use AndyDefer\Support\Requests\Tickets\CloseTicketRequest;
use AndyDefer\Support\Requests\Tickets\CreateTicketRequest;
use AndyDefer\Support\Requests\Tickets\DestroyTicketRequest;
use AndyDefer\Support\Requests\Tickets\ListTicketsRequest;
use AndyDefer\Support\Requests\Tickets\ReassignTicketRequest;
use AndyDefer\Support\Requests\Tickets\ReopenTicketRequest;
use AndyDefer\Support\Requests\Tickets\ResolveTicketRequest;
use AndyDefer\Support\Requests\Tickets\ShowTicketRequest;
use AndyDefer\Support\Requests\Tickets\UnassignTicketRequest;
use AndyDefer\Support\Requests\Tickets\UpdateTicketPriorityRequest;
use AndyDefer\Support\Requests\Tickets\UpdateTicketRequest;
use AndyDefer\Support\Requests\Tickets\UpdateTicketStatusRequest;

/**
 * Chaque case associe :
 *  - une Request (validation + Record),
 *  - une méthode du TicketRouteManagerService à invoquer.
 *
 * Le helper `support_route(TicketRoute::X)` génère une Closure
 * utilisable directement dans une route Laravel.
 */
enum TicketRoute: string
{
    // ══════════════════════════════════════════════════════════════════
    // TICKETS — cycle de vie
    // ══════════════════════════════════════════════════════════════════

    case STORE_TICKET = 'store_ticket';
    case UPDATE_TICKET = 'update_ticket';
    case DESTROY_TICKET = 'destroy_ticket';
    case ASSIGN_TICKET = 'assign_ticket';
    case REASSIGN_TICKET = 'reassign_ticket';
    case UNASSIGN_TICKET = 'unassign_ticket';
    case RESOLVE_TICKET = 'resolve_ticket';
    case CLOSE_TICKET = 'close_ticket';
    case REOPEN_TICKET = 'reopen_ticket';
    case UPDATE_STATUS = 'update_status';
    case UPDATE_PRIORITY = 'update_priority';

    // ══════════════════════════════════════════════════════════════════
    // TICKETS — lecture
    // ══════════════════════════════════════════════════════════════════

    case INDEX_TICKETS = 'index_tickets';
    case SHOW_TICKET = 'show_ticket';

    // ══════════════════════════════════════════════════════════════════
    // MESSAGES — création
    // ══════════════════════════════════════════════════════════════════

    case STORE_MESSAGE = 'store_message';

    // ══════════════════════════════════════════════════════════════════
    // MESSAGES — lecture
    // ══════════════════════════════════════════════════════════════════

    case INDEX_MESSAGES = 'index_messages';
    case THREAD_MESSAGES = 'thread_messages';
    case SHOW_MESSAGE = 'show_message';

    // ══════════════════════════════════════════════════════════════════
    // MESSAGES — statut
    // ══════════════════════════════════════════════════════════════════

    case MARK_MESSAGE_DISPATCHED = 'mark_message_dispatched';
    case MARK_MESSAGE_READ = 'mark_message_read';
    case MARK_ALL_MESSAGES_READ = 'mark_all_messages_read';

    /**
     * FQCN de la Request associée.
     *
     * @return class-string<AbstractRequest>
     */
    public function requestClass(): string
    {
        return match ($this) {
            self::STORE_TICKET => CreateTicketRequest::class,
            self::UPDATE_TICKET => UpdateTicketRequest::class,
            self::DESTROY_TICKET => DestroyTicketRequest::class,
            self::ASSIGN_TICKET => AssignTicketRequest::class,
            self::REASSIGN_TICKET => ReassignTicketRequest::class,
            self::UNASSIGN_TICKET => UnassignTicketRequest::class,
            self::RESOLVE_TICKET => ResolveTicketRequest::class,
            self::CLOSE_TICKET => CloseTicketRequest::class,
            self::REOPEN_TICKET => ReopenTicketRequest::class,
            self::UPDATE_STATUS => UpdateTicketStatusRequest::class,
            self::UPDATE_PRIORITY => UpdateTicketPriorityRequest::class,

            self::INDEX_TICKETS => ListTicketsRequest::class,
            self::SHOW_TICKET => ShowTicketRequest::class,

            self::STORE_MESSAGE => SendMessageRequest::class,

            self::INDEX_MESSAGES => ListMessagesRequest::class,
            self::THREAD_MESSAGES => ThreadMessagesRequest::class,
            self::SHOW_MESSAGE => ShowMessageRequest::class,

            self::MARK_MESSAGE_DISPATCHED => MarkMessageDispatchedRequest::class,
            self::MARK_MESSAGE_READ => MarkMessageReadRequest::class,
            self::MARK_ALL_MESSAGES_READ => MarkAllMessagesReadRequest::class,
        };
    }

    /**
     * Méthode du TicketRouteManagerService à invoquer.
     */
    public function serviceMethod(): string
    {
        return match ($this) {
            self::STORE_TICKET => 'storeTicket',
            self::UPDATE_TICKET => 'updateTicket',
            self::DESTROY_TICKET => 'destroyTicket',
            self::ASSIGN_TICKET => 'assignTicket',
            self::REASSIGN_TICKET => 'reassignTicket',
            self::UNASSIGN_TICKET => 'unassignTicket',
            self::RESOLVE_TICKET => 'resolveTicket',
            self::CLOSE_TICKET => 'closeTicket',
            self::REOPEN_TICKET => 'reopenTicket',
            self::UPDATE_STATUS => 'updateStatus',
            self::UPDATE_PRIORITY => 'updatePriority',

            self::INDEX_TICKETS => 'indexTickets',
            self::SHOW_TICKET => 'showTicket',

            self::STORE_MESSAGE => 'storeMessage',

            self::INDEX_MESSAGES => 'indexMessages',
            self::THREAD_MESSAGES => 'threadMessages',
            self::SHOW_MESSAGE => 'showMessage',

            self::MARK_MESSAGE_DISPATCHED => 'markMessageDispatched',
            self::MARK_MESSAGE_READ => 'markMessageRead',
            self::MARK_ALL_MESSAGES_READ => 'markAllMessagesRead',
        };
    }
}
