<?php

declare(strict_types=1);

namespace AndyDefer\Support\Tests\Feature\Services;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\Nemesis\Helpers\NemesisHelper;
use AndyDefer\Support\Contracts\Services\SupportServiceInterface;
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
use AndyDefer\Support\Records\UnassignTicketRecord;
use AndyDefer\Support\Records\UpdateTicketPriorityRecord;
use AndyDefer\Support\Records\UpdateTicketRecord;
use AndyDefer\Support\Records\UpdateTicketStatusRecord;
use AndyDefer\Support\Services\TicketRouteManagerService;
use AndyDefer\Support\Tests\Fixtures\Models\TestUser;
use AndyDefer\Support\Tests\IntegrationTestCase;
use Illuminate\Support\Str;

final class TicketRouteManagerServiceTest extends IntegrationTestCase
{
    private TicketRouteManagerService $manager;

    private SupportServiceInterface $support;

    private TestUser $creator;

    private TestUser $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->support = $this->app->make(SupportServiceInterface::class);

        $this->creator = TestUser::create([
            'name' => 'Creator', 'email' => 'creator@example.com',
            'status' => 'active', 'role' => 'user', 'age' => 30, 'metadata' => null,
        ]);

        $this->resolver = TestUser::create([
            'name' => 'Resolver', 'email' => 'resolver@example.com',
            'status' => 'active', 'role' => 'admin', 'age' => 35, 'metadata' => null,
        ]);

        $this->mock(NemesisHelper::class, function ($mock): void {
            $mock->shouldReceive('getCurrentAuthenticatable')
                ->andReturn($this->creator);
        });

        $this->manager = $this->app->make(TicketRouteManagerService::class);
    }

    private function makeTicket(?TestUser $creator = null): Ticket
    {
        $creator ??= $this->creator;

        return Ticket::create([
            'id' => (string) Str::uuid(),
            'reference' => 'TKT-TEST'.strtoupper(Str::random(6)),
            'title' => 'Sample ticket',
            'description' => 'Sample description',
            'created_by_type' => $creator->getMorphClass(),
            'created_by_id' => (string) $creator->getKey(),
            'status' => TicketStatus::OPEN->value,
            'priority' => TicketPriority::NORMAL->value,
        ]);
    }

    private function makeMessage(Ticket $ticket, bool $internal = false): TicketMessage
    {
        return TicketMessage::create([
            'id' => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'sender_type' => 'creator',
            'content' => 'Sample message',
            'status' => 'sent',
            'is_internal' => $internal,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // TICKETS — cycle de vie
    // ══════════════════════════════════════════════════════════════════

    public function test_store_ticket_returns_created_ticket(): void
    {
        $response = $this->manager->storeTicket(new CreateTicketRecord(
            title: 'A ticket',
            description: 'Description',
        ));

        $this->assertSame(201, $response->getStatus());
        $this->assertSame('A ticket', $response->getContent()->title);
        $this->assertSame(TicketStatus::OPEN, $response->getContent()->status);
        $this->assertSame(TicketPriority::NORMAL, $response->getContent()->priority);
        $this->assertNotEmpty($response->getContent()->id);
        $this->assertNotEmpty($response->getContent()->reference);
    }

    public function test_store_ticket_with_explicit_priority(): void
    {
        $response = $this->manager->storeTicket(new CreateTicketRecord(
            title: 'Urgent',
            description: 'Desc',
            priority: TicketPriority::URGENT,
        ));

        $this->assertSame(TicketPriority::URGENT, $response->getContent()->priority);
    }

    public function test_store_ticket_with_metadata(): void
    {
        $response = $this->manager->storeTicket(new CreateTicketRecord(
            title: 'With meta',
            description: 'Desc',
            metadata: StrictAssociative::from(['source' => 'web']),
        ));

        $this->assertSame('web', $response->getContent()->metadata->get('source'));
    }

    public function test_update_ticket_title(): void
    {
        $ticket = $this->makeTicket();

        $response = $this->manager->updateTicket(new UpdateTicketRecord(
            ticket_id: $ticket->id,
            title: 'Updated',
        ));

        $this->assertSame('Updated', $response->getContent()->title);
    }

    public function test_update_ticket_priority(): void
    {
        $ticket = $this->makeTicket();

        $response = $this->manager->updateTicket(new UpdateTicketRecord(
            ticket_id: $ticket->id,
            priority: TicketPriority::HIGH,
        ));

        $this->assertSame(TicketPriority::HIGH, $response->getContent()->priority);
    }

    public function test_update_ticket_returns_404_when_not_found(): void
    {
        $response = $this->manager->updateTicket(new UpdateTicketRecord(
            ticket_id: '00000000-0000-0000-0000-000000000000',
            title: 'X',
        ));

        $this->assertSame(404, $response->getStatus());
    }

    public function test_destroy_ticket_soft_deletes(): void
    {
        $ticket = $this->makeTicket();

        $response = $this->manager->destroyTicket(new DestroyTicketRecord(
            ticket_id: $ticket->id,
        ));

        $this->assertSame(200, $response->getStatus());
        $this->assertSoftDeleted('support_tickets', ['id' => $ticket->id]);
    }

    public function test_assign_ticket_sets_resolver(): void
    {
        $ticket = $this->makeTicket();

        $response = $this->manager->assignTicket(new AssignTicketRecord(
            ticket_id: $ticket->id,
            resolver_type: $this->resolver->getMorphClass(),
            resolver_id: (string) $this->resolver->getKey(),
        ));

        $this->assertSame(200, $response->getStatus());
        $this->assertSame($this->resolver->getMorphClass(), $response->getContent()->assignedToType);
        $this->assertSame((string) $this->resolver->getKey(), $response->getContent()->assignedToId);
    }

    public function test_assign_ticket_returns_404_when_resolver_not_found(): void
    {
        $ticket = $this->makeTicket();

        $response = $this->manager->assignTicket(new AssignTicketRecord(
            ticket_id: $ticket->id,
            resolver_type: TestUser::class,
            resolver_id: '00000000-0000-0000-0000-000000000000',
        ));

        $this->assertSame(404, $response->getStatus());
    }

    public function test_reassign_ticket_changes_resolver(): void
    {
        $ticket = $this->makeTicket();

        $this->manager->assignTicket(new AssignTicketRecord(
            ticket_id: $ticket->id,
            resolver_type: $this->creator->getMorphClass(),
            resolver_id: (string) $this->creator->getKey(),
        ));

        $response = $this->manager->reassignTicket(new ReassignTicketRecord(
            ticket_id: $ticket->id,
            resolver_type: $this->resolver->getMorphClass(),
            resolver_id: (string) $this->resolver->getKey(),
        ));

        $this->assertSame((string) $this->resolver->getKey(), $response->getContent()->assignedToId);
    }

    public function test_unassign_ticket_clears_resolver(): void
    {
        $ticket = $this->makeTicket();

        $this->manager->assignTicket(new AssignTicketRecord(
            ticket_id: $ticket->id,
            resolver_type: $this->resolver->getMorphClass(),
            resolver_id: (string) $this->resolver->getKey(),
        ));

        $response = $this->manager->unassignTicket(new UnassignTicketRecord(
            ticket_id: $ticket->id,
        ));

        $this->assertNull($response->getContent()->assignedToType);
        $this->assertNull($response->getContent()->assignedToId);
    }

    public function test_resolve_ticket_sets_resolved(): void
    {
        $ticket = $this->makeTicket();

        $response = $this->manager->resolveTicket(new ResolveTicketRecord(
            ticket_id: $ticket->id,
        ));

        $this->assertSame(TicketStatus::RESOLVED, $response->getContent()->status);
        $this->assertNotNull($response->getContent()->resolvedAt);
    }

    public function test_close_ticket_sets_closed(): void
    {
        $ticket = $this->makeTicket();

        $response = $this->manager->closeTicket(new CloseTicketRecord(
            ticket_id: $ticket->id,
        ));

        $this->assertSame(TicketStatus::CLOSED, $response->getContent()->status);
        $this->assertNotNull($response->getContent()->closedAt);
    }

    public function test_reopen_ticket_resets_status(): void
    {
        $ticket = $this->makeTicket();

        $this->manager->closeTicket(new CloseTicketRecord(ticket_id: $ticket->id));

        $response = $this->manager->reopenTicket(new ReopenTicketRecord(
            ticket_id: $ticket->id,
        ));

        $this->assertSame(TicketStatus::OPEN, $response->getContent()->status);
        $this->assertNull($response->getContent()->closedAt);
        $this->assertNull($response->getContent()->resolvedAt);
    }

    public function test_update_status(): void
    {
        $ticket = $this->makeTicket();

        $response = $this->manager->updateStatus(new UpdateTicketStatusRecord(
            ticket_id: $ticket->id,
            status: TicketStatus::PENDING,
        ));

        $this->assertSame(TicketStatus::PENDING, $response->getContent()->status);
    }

    public function test_update_priority(): void
    {
        $ticket = $this->makeTicket();

        $response = $this->manager->updatePriority(new UpdateTicketPriorityRecord(
            ticket_id: $ticket->id,
            priority: TicketPriority::URGENT,
        ));

        $this->assertSame(TicketPriority::URGENT, $response->getContent()->priority);
    }

    public function test_update_status_returns_404_when_not_found(): void
    {
        $response = $this->manager->updateStatus(new UpdateTicketStatusRecord(
            ticket_id: '00000000-0000-0000-0000-000000000000',
            status: TicketStatus::PENDING,
        ));

        $this->assertSame(404, $response->getStatus());
    }

    // ══════════════════════════════════════════════════════════════════
    // TICKETS — lecture
    // ══════════════════════════════════════════════════════════════════

    public function test_index_tickets_returns_paginated_response(): void
    {
        $this->makeTicket();
        $this->makeTicket();

        $response = $this->manager->indexTickets(new ListTicketsRecord(
            per_page: 15,
            page: 1,
        ));

        $this->assertSame(200, $response->getStatus());

        $content = $response->getContent();
        $this->assertSame(2, $content->total);
        $this->assertSame(1, $content->currentPage);
        $this->assertSame(15, $content->perPage);
        $this->assertCount(2, $content->items);
    }

    public function test_show_ticket_returns_ticket(): void
    {
        $ticket = $this->makeTicket();

        $response = $this->manager->showTicket(new ShowTicketRecord(
            ticket_id: $ticket->id,
        ));

        $this->assertSame($ticket->id, $response->getContent()->id);
    }

    public function test_show_ticket_returns_404_when_not_found(): void
    {
        $response = $this->manager->showTicket(new ShowTicketRecord(
            ticket_id: '00000000-0000-0000-0000-000000000000',
        ));

        $this->assertSame(404, $response->getStatus());
    }

    // ══════════════════════════════════════════════════════════════════
    // MESSAGES — création
    // ══════════════════════════════════════════════════════════════════

    public function test_store_message_creates_message(): void
    {
        $ticket = $this->makeTicket();

        $response = $this->manager->storeMessage(new SendMessageRecord(
            ticket_id: $ticket->id,
            content: 'Hello',
        ));

        $this->assertSame(201, $response->getStatus());
        $this->assertSame('Hello', $response->getContent()->content);
        $this->assertSame($ticket->id, $response->getContent()->ticketId);
        $this->assertSame('sent', $response->getContent()->status->value);
    }

    public function test_store_message_with_reply(): void
    {
        $ticket = $this->makeTicket();
        $parent = $this->makeMessage($ticket);

        $response = $this->manager->storeMessage(new SendMessageRecord(
            ticket_id: $ticket->id,
            content: 'Reply',
            reply_to_id: $parent->id,
        ));

        $this->assertSame($parent->id, $response->getContent()->replyToId);
    }

    public function test_store_message_internal(): void
    {
        $ticket = $this->makeTicket();

        $response = $this->manager->storeMessage(new SendMessageRecord(
            ticket_id: $ticket->id,
            content: 'Internal note',
            is_internal: true,
        ));

        $this->assertTrue($response->getContent()->isInternal);
    }

    public function test_store_message_returns_404_when_ticket_not_found(): void
    {
        $response = $this->manager->storeMessage(new SendMessageRecord(
            ticket_id: '00000000-0000-0000-0000-000000000000',
            content: 'Hello',
        ));

        $this->assertSame(404, $response->getStatus());
    }

    // ══════════════════════════════════════════════════════════════════
    // MESSAGES — lecture
    // ══════════════════════════════════════════════════════════════════

    public function test_index_messages_returns_paginated_response(): void
    {
        $ticket = $this->makeTicket();
        $this->makeMessage($ticket);
        $this->makeMessage($ticket);

        $response = $this->manager->indexMessages(new ListMessagesRecord(
            ticket_id: $ticket->id,
            per_page: 30,
            page: 1,
        ));

        $this->assertSame(2, $response->getContent()->total);
    }

    public function test_thread_messages_excludes_internal_by_default(): void
    {
        $ticket = $this->makeTicket();
        $this->makeMessage($ticket, internal: false);
        $this->makeMessage($ticket, internal: true);

        $response = $this->manager->threadMessages(new ThreadMessagesRecord(
            ticket_id: $ticket->id,
            include_internal: false,
        ));

        $this->assertSame(1, $response->getContent()->total);
    }

    public function test_thread_messages_includes_internal_when_requested(): void
    {
        $ticket = $this->makeTicket();
        $this->makeMessage($ticket, internal: false);
        $this->makeMessage($ticket, internal: true);

        $response = $this->manager->threadMessages(new ThreadMessagesRecord(
            ticket_id: $ticket->id,
            include_internal: true,
        ));

        $this->assertSame(2, $response->getContent()->total);
    }

    public function test_thread_messages_returns_404_when_ticket_not_found(): void
    {
        $response = $this->manager->threadMessages(new ThreadMessagesRecord(
            ticket_id: '00000000-0000-0000-0000-000000000000',
        ));

        $this->assertSame(404, $response->getStatus());
    }

    public function test_show_message_returns_message(): void
    {
        $ticket = $this->makeTicket();
        $message = $this->makeMessage($ticket);

        $response = $this->manager->showMessage(new ShowMessageRecord(
            ticket_id: $ticket->id,
            message_id: $message->id,
        ));

        $this->assertSame($message->id, $response->getContent()->id);
    }

    public function test_show_message_returns_404_when_not_found(): void
    {
        $ticket = $this->makeTicket();

        $response = $this->manager->showMessage(new ShowMessageRecord(
            ticket_id: $ticket->id,
            message_id: '00000000-0000-0000-0000-000000000000',
        ));

        $this->assertSame(404, $response->getStatus());
    }

    public function test_show_message_returns_409_when_not_in_ticket(): void
    {
        $ticketA = $this->makeTicket();
        $ticketB = $this->makeTicket();
        $message = $this->makeMessage($ticketA);

        $response = $this->manager->showMessage(new ShowMessageRecord(
            ticket_id: $ticketB->id,
            message_id: $message->id,
        ));

        $this->assertSame(409, $response->getStatus());
    }

    // ══════════════════════════════════════════════════════════════════
    // MESSAGES — statut
    // ══════════════════════════════════════════════════════════════════

    public function test_mark_message_dispatched(): void
    {
        $ticket = $this->makeTicket();
        $message = $this->makeMessage($ticket);

        $response = $this->manager->markMessageDispatched(new MarkMessageDispatchedRecord(
            ticket_id: $ticket->id,
            message_id: $message->id,
        ));

        $this->assertSame('dispatched', $response->getContent()->status->value);
    }

    public function test_mark_message_read(): void
    {
        $ticket = $this->makeTicket();
        $message = $this->makeMessage($ticket);

        $response = $this->manager->markMessageRead(new MarkMessageReadRecord(
            ticket_id: $ticket->id,
            message_id: $message->id,
        ));

        $this->assertSame('read', $response->getContent()->status->value);
    }

    public function test_mark_all_messages_read(): void
    {
        $ticket = $this->makeTicket();
        for ($i = 0; $i < 3; $i++) {
            $this->makeMessage($ticket);
        }

        $response = $this->manager->markAllMessagesRead(new MarkAllMessagesReadRecord(
            ticket_id: $ticket->id,
        ));

        $this->assertSame(3, $response->getContent()->markedCount);

        $unread = TicketMessage::query()
            ->where('ticket_id', $ticket->id)
            ->where('status', '!=', 'read')
            ->count();

        $this->assertSame(0, $unread);
    }

    public function test_mark_all_messages_read_returns_404_when_ticket_not_found(): void
    {
        $response = $this->manager->markAllMessagesRead(new MarkAllMessagesReadRecord(
            ticket_id: '00000000-0000-0000-0000-000000000000',
        ));

        $this->assertSame(404, $response->getStatus());
    }
}
