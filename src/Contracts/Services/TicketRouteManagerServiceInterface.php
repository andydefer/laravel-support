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

interface TicketRouteManagerServiceInterface
{
    // TICKETS — cycle de vie
    public function storeTicket(CreateTicketRecord $record): ResponseFactory;

    public function updateTicket(UpdateTicketRecord $record): ResponseFactory;

    public function destroyTicket(DestroyTicketRecord $record): ResponseFactory;

    public function assignTicket(AssignTicketRecord $record): ResponseFactory;

    public function reassignTicket(ReassignTicketRecord $record): ResponseFactory;

    public function unassignTicket(UnassignTicketRecord $record): ResponseFactory;

    public function resolveTicket(ResolveTicketRecord $record): ResponseFactory;

    public function closeTicket(CloseTicketRecord $record): ResponseFactory;

    public function reopenTicket(ReopenTicketRecord $record): ResponseFactory;

    public function updateStatus(UpdateTicketStatusRecord $record): ResponseFactory;

    public function updatePriority(UpdateTicketPriorityRecord $record): ResponseFactory;

    // TICKETS — lecture
    public function indexTickets(ListTicketsRecord $record): ResponseFactory;

    public function showTicket(ShowTicketRecord $record): ResponseFactory;

    // MESSAGES — création
    public function storeMessage(SendMessageRecord $record): ResponseFactory;

    // MESSAGES — lecture
    public function indexMessages(ListMessagesRecord $record): ResponseFactory;

    public function threadMessages(ThreadMessagesRecord $record): ResponseFactory;

    public function showMessage(ShowMessageRecord $record): ResponseFactory;

    // MESSAGES — statut
    public function markMessageDispatched(MarkMessageDispatchedRecord $record): ResponseFactory;

    public function markMessageRead(MarkMessageReadRecord $record): ResponseFactory;

    public function markAllMessagesRead(MarkAllMessagesReadRecord $record): ResponseFactory;
}
