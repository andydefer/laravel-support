<?php

declare(strict_types=1);

namespace AndyDefer\Support\Contracts\Services;

use AndyDefer\Actions\Http\ResponseFactory;
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
use AndyDefer\Support\Records\UnassignTicketRecord;
use AndyDefer\Support\Records\UpdateTicketPriorityRecord;
use AndyDefer\Support\Records\UpdateTicketRecord;
use AndyDefer\Support\Records\UpdateTicketStatusRecord;

/**
 * Contract for the HTTP-facing orchestration layer of support tickets.
 *
 * Exposes one method per route action, each consuming a strongly-typed
 * record and returning a fully-built {@see ResponseFactory}. The interface
 * intentionally hides business logic: implementations are responsible for
 * delegating to the domain service and shaping the HTTP response.
 */
interface TicketRouteManagerServiceInterface
{
    /**
     * Open a new support ticket.
     */
    public function storeTicket(CreateTicketRecord $record): ResponseFactory;

    /**
     * Update the editable fields of an existing ticket.
     */
    public function updateTicket(UpdateTicketRecord $record): ResponseFactory;

    /**
     * Permanently delete a ticket.
     */
    public function destroyTicket(DestroyTicketRecord $record): ResponseFactory;

    /**
     * Assign an unassigned ticket to a resolver.
     */
    public function assignTicket(AssignTicketRecord $record): ResponseFactory;

    /**
     * Reassign a ticket to another resolver, replacing the current one.
     */
    public function reassignTicket(ReassignTicketRecord $record): ResponseFactory;

    /**
     * Remove the current resolver from a ticket.
     */
    public function unassignTicket(UnassignTicketRecord $record): ResponseFactory;

    /**
     * Mark a ticket as resolved.
     */
    public function resolveTicket(ResolveTicketRecord $record): ResponseFactory;

    /**
     * Mark a ticket as closed.
     */
    public function closeTicket(CloseTicketRecord $record): ResponseFactory;

    /**
     * Reopen a previously resolved or closed ticket.
     */
    public function reopenTicket(ReopenTicketRecord $record): ResponseFactory;

    /**
     * Change the status of a ticket.
     */
    public function updateStatus(UpdateTicketStatusRecord $record): ResponseFactory;

    /**
     * Change the priority of a ticket.
     */
    public function updatePriority(UpdateTicketPriorityRecord $record): ResponseFactory;

    /**
     * List tickets matching the given filters.
     */
    public function indexTickets(ListTicketsRecord $record): ResponseFactory;

    /**
     * Show a single ticket by identifier.
     */
    public function showTicket(ShowTicketRecord $record): ResponseFactory;

    /**
     * Append a message to a ticket.
     */
    public function storeMessage(SendMessageRecord $record): ResponseFactory;

    /**
     * List messages matching the given filters.
     */
    public function indexMessages(ListMessagesRecord $record): ResponseFactory;

    /**
     * Return the full chronological thread of a ticket.
     */
    public function threadMessages(ThreadMessagesRecord $record): ResponseFactory;

    /**
     * Show a single message by identifier.
     */
    public function showMessage(ShowMessageRecord $record): ResponseFactory;

    /**
     * Mark a message as dispatched by the notification transport.
     */
    public function markMessageDispatched(MarkMessageDispatchedRecord $record): ResponseFactory;

    /**
     * Mark a message as read.
     */
    public function markMessageRead(MarkMessageReadRecord $record): ResponseFactory;

    /**
     * Mark every non-read message of a ticket as read.
     */
    public function markAllMessagesRead(MarkAllMessagesReadRecord $record): ResponseFactory;
}
