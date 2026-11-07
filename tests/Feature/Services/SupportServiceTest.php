<?php

declare(strict_types=1);

namespace AndyDefer\Support\Tests\Feature\Services;

use AndyDefer\Support\Contracts\Services\SupportServiceInterface;
use AndyDefer\Support\Enums\MessageStatus;
use AndyDefer\Support\Enums\SenderType;
use AndyDefer\Support\Enums\TicketPriority;
use AndyDefer\Support\Enums\TicketStatus;
use AndyDefer\Support\Models\Ticket;
use AndyDefer\Support\Models\TicketMessage;
use AndyDefer\Support\Records\TicketFiltersRecord;
use AndyDefer\Support\Records\TicketMessageFiltersRecord;
use AndyDefer\Support\Tests\Fixtures\Models\TestUser;
use AndyDefer\Support\Tests\IntegrationTestCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

final class SupportServiceTest extends IntegrationTestCase
{
    use RefreshDatabase;

    private SupportServiceInterface $service;

    private TestUser $creator;

    private TestUser $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(SupportServiceInterface::class);

        $this->creator = TestUser::query()->create([
            'name' => 'Creator',
            'email' => 'creator@example.com',
            'status' => 'active',
            'role' => 'user',
            'age' => 30,
            'metadata' => null,
        ]);

        $this->resolver = TestUser::query()->create([
            'name' => 'Resolver',
            'email' => 'resolver@example.com',
            'status' => 'active',
            'role' => 'admin',
            'age' => 35,
            'metadata' => null,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // TICKETS — cycle de vie
    // ══════════════════════════════════════════════════════════════════

    public function test_create_ticket_persists_with_open_status_and_reference(): void
    {
        $ticket = $this->service->createTicket(
            creator: $this->creator,
            title: 'Cannot login',
            description: 'I forgot my password',
            priority: TicketPriority::HIGH,
        );

        $this->assertSame(TicketStatus::OPEN, $ticket->status);
        $this->assertSame(TicketPriority::HIGH, $ticket->priority);
        $this->assertNull($ticket->assigned_to_id);
        $this->assertMatchesRegularExpression('/^TKT-[A-Z0-9]{10}$/', $ticket->reference);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'created_by_type' => $this->creator->getMorphClass(),
            'created_by_id' => (string) $this->creator->getKey(),
            'status' => TicketStatus::OPEN->value,
            'priority' => TicketPriority::HIGH->value,
        ]);
    }

    public function test_assign_ticket_sets_resolver_and_assigned_at(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');

        $ticket = $this->service->assignTicket($ticket, $this->resolver);

        $this->assertSame($this->resolver->getMorphClass(), $ticket->assigned_to_type);
        $this->assertSame((string) $this->resolver->getKey(), $ticket->assigned_to_id);
        $this->assertNotNull($ticket->assigned_at);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'assigned_to_type' => $this->resolver->getMorphClass(),
            'assigned_to_id' => (string) $this->resolver->getKey(),
        ]);
    }

    public function test_change_resolver_updates_assignee(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');
        $ticket = $this->service->assignTicket($ticket, $this->creator);

        $ticket = $this->service->changeResolver($ticket, $this->resolver);

        $this->assertSame($this->resolver->getMorphClass(), $ticket->assigned_to_type);
        $this->assertSame((string) $this->resolver->getKey(), $ticket->assigned_to_id);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'assigned_to_type' => $this->resolver->getMorphClass(),
            'assigned_to_id' => (string) $this->resolver->getKey(),
        ]);
    }

    public function test_unassign_ticket_clears_resolver(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');
        $ticket = $this->service->assignTicket($ticket, $this->resolver);

        $ticket = $this->service->unassignTicket($ticket);

        $this->assertNull($ticket->assigned_to_type);
        $this->assertNull($ticket->assigned_to_id);
        $this->assertNull($ticket->assigned_at);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'assigned_to_type' => null,
            'assigned_to_id' => null,
        ]);
    }

    public function test_update_status_persists(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');

        $ticket = $this->service->updateStatus($ticket, TicketStatus::PENDING);

        $this->assertSame(TicketStatus::PENDING, $ticket->status);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'status' => TicketStatus::PENDING->value,
        ]);
    }

    public function test_update_priority_persists(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');

        $ticket = $this->service->updatePriority($ticket, TicketPriority::URGENT);

        $this->assertSame(TicketPriority::URGENT, $ticket->priority);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'priority' => TicketPriority::URGENT->value,
        ]);
    }

    public function test_resolve_ticket_sets_status_and_resolved_at(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');

        $ticket = $this->service->resolveTicket($ticket);

        $this->assertSame(TicketStatus::RESOLVED, $ticket->status);
        $this->assertNotNull($ticket->resolved_at);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'status' => TicketStatus::RESOLVED->value,
        ]);
    }

    public function test_close_ticket_sets_status_and_closed_at(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');

        $ticket = $this->service->closeTicket($ticket);

        $this->assertSame(TicketStatus::CLOSED, $ticket->status);
        $this->assertNotNull($ticket->closed_at);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'status' => TicketStatus::CLOSED->value,
        ]);
    }

    public function test_reopen_ticket_resets_status_resolved_and_closed(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');
        $ticket = $this->service->resolveTicket($ticket);
        $ticket = $this->service->closeTicket($ticket);

        $ticket = $this->service->reopenTicket($ticket);

        $this->assertSame(TicketStatus::OPEN, $ticket->status);
        $this->assertNull($ticket->resolved_at);
        $this->assertNull($ticket->closed_at);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'status' => TicketStatus::OPEN->value,
            'resolved_at' => null,
            'closed_at' => null,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // TICKETS — lecture
    // ══════════════════════════════════════════════════════════════════

    public function test_list_tickets_returns_collection_with_filters(): void
    {
        $this->service->createTicket($this->creator, 'A', 'a', TicketPriority::LOW);
        $this->service->createTicket($this->creator, 'B', 'b', TicketPriority::HIGH);

        $result = $this->service->listTickets(new TicketFiltersRecord(
            priority: TicketPriority::HIGH,
        ));

        $this->assertCount(1, $result);
        $this->assertSame('B', $result->first()->title);
    }

    public function test_paginate_tickets_returns_length_aware_paginator(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->service->createTicket($this->creator, "Ticket {$i}", 'desc');
        }

        $paginator = $this->service->paginateTickets(
            filters: new TicketFiltersRecord,
            perPage: 2,
            page: 1,
        );

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertSame(5, $paginator->total());
        $this->assertCount(2, $paginator->items());
    }

    public function test_find_ticket_returns_ticket_by_id(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');

        $found = $this->service->findTicket($ticket->id);

        $this->assertNotNull($found);
        $this->assertSame($ticket->id, $found->id);
    }

    public function test_find_ticket_by_reference_returns_ticket(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');

        $found = $this->service->findTicketByReference($ticket->reference);

        $this->assertNotNull($found);
        $this->assertSame($ticket->id, $found->id);
    }

    public function test_count_tickets_returns_count_with_filters(): void
    {
        $this->service->createTicket($this->creator, 'A', 'a', TicketPriority::LOW);
        $this->service->createTicket($this->creator, 'B', 'b', TicketPriority::HIGH);

        $count = $this->service->countTickets(new TicketFiltersRecord(
            priority: TicketPriority::LOW,
        ));

        $this->assertSame(1, $count);
    }

    // ══════════════════════════════════════════════════════════════════
    // MESSAGES — création
    // ══════════════════════════════════════════════════════════════════

    public function test_add_message_from_creator_persists_with_sender_type_creator(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');

        $message = $this->service->addMessage(
            ticket: $ticket,
            sender: $this->creator,
            content: 'Hello',
        );

        $this->assertSame(SenderType::CREATOR, $message->sender_type);
        $this->assertSame(MessageStatus::SENT, $message->status);

        $this->assertDatabaseHas('support_ticket_messages', [
            'id' => $message->id,
            'ticket_id' => $ticket->id,
            'sender_type' => SenderType::CREATOR->value,
            'status' => MessageStatus::SENT->value,
        ]);
    }

    public function test_add_message_from_resolver_persists_with_sender_type_resolver(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');
        $ticket = $this->service->assignTicket($ticket, $this->resolver);

        $message = $this->service->addMessage(
            ticket: $ticket,
            sender: $this->resolver,
            content: 'I am on it',
        );

        $this->assertSame(SenderType::RESOLVER, $message->sender_type);

        $this->assertDatabaseHas('support_ticket_messages', [
            'id' => $message->id,
            'sender_type' => SenderType::RESOLVER->value,
        ]);
    }

    public function test_add_message_reply_links_to_parent(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');
        $parent = $this->service->addMessage($ticket, $this->creator, 'parent');

        $reply = $this->service->addMessage(
            ticket: $ticket,
            sender: $this->resolver,
            content: 'reply',
            replyTo: $parent,
        );

        $this->assertSame($parent->id, $reply->reply_to_id);

        $this->assertDatabaseHas('support_ticket_messages', [
            'id' => $reply->id,
            'reply_to_id' => $parent->id,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // MESSAGES — lecture
    // ══════════════════════════════════════════════════════════════════

    public function test_list_messages_with_filters(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');
        $this->service->addMessage($ticket, $this->creator, 'a');
        $this->service->addMessage($ticket, $this->creator, 'b');

        $result = $this->service->listMessages(new TicketMessageFiltersRecord(
            ticket_id: $ticket->id,
        ));

        $this->assertCount(2, $result);
    }

    public function test_paginate_messages_returns_length_aware_paginator(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');
        for ($i = 0; $i < 5; $i++) {
            $this->service->addMessage($ticket, $this->creator, "msg {$i}");
        }

        $paginator = $this->service->paginateMessages(
            filters: new TicketMessageFiltersRecord(ticket_id: $ticket->id),
            perPage: 2,
            page: 1,
        );

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertSame(5, $paginator->total());
        $this->assertCount(2, $paginator->items());
    }

    public function test_thread_messages_returns_all_messages_of_ticket(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');
        $this->service->addMessage($ticket, $this->creator, 'a');
        $this->service->addMessage($ticket, $this->creator, 'b');

        $thread = $this->service->threadMessages($ticket);

        $this->assertCount(2, $thread);
    }

    public function test_find_message_returns_message_by_id(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');
        $message = $this->service->addMessage($ticket, $this->creator, 'a');

        $found = $this->service->findMessage($message->id);

        $this->assertNotNull($found);
        $this->assertSame($message->id, $found->id);
    }

    public function test_count_messages_returns_count_with_filters(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');
        $this->service->addMessage($ticket, $this->creator, 'a');
        $this->service->addMessage($ticket, $this->creator, 'b');

        $count = $this->service->countMessages(new TicketMessageFiltersRecord(
            ticket_id: $ticket->id,
        ));

        $this->assertSame(2, $count);
    }

    // ══════════════════════════════════════════════════════════════════
    // MESSAGES — statut
    // ══════════════════════════════════════════════════════════════════

    public function test_mark_message_dispatched_updates_status(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');
        $message = $this->service->addMessage($ticket, $this->creator, 'a');

        $updated = $this->service->markMessageDispatched($message);

        $this->assertSame(MessageStatus::DISPATCHED, $updated->status);

        $this->assertDatabaseHas('support_ticket_messages', [
            'id' => $message->id,
            'status' => MessageStatus::DISPATCHED->value,
        ]);
    }

    public function test_mark_message_read_updates_status(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');
        $message = $this->service->addMessage($ticket, $this->creator, 'a');

        $updated = $this->service->markMessageRead($message);

        $this->assertSame(MessageStatus::READ, $updated->status);

        $this->assertDatabaseHas('support_ticket_messages', [
            'id' => $message->id,
            'status' => MessageStatus::READ->value,
        ]);
    }

    public function test_mark_all_messages_read_marks_only_unread(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');
        $this->service->addMessage($ticket, $this->creator, 'a');
        $this->service->addMessage($ticket, $this->creator, 'b');

        $updated = $this->service->markAllMessagesRead($ticket, $this->resolver);

        $this->assertSame(2, $updated);

        $unread = TicketMessage::query()
            ->where('ticket_id', $ticket->id)
            ->where('status', '!=', MessageStatus::READ->value)
            ->count();

        $this->assertSame(0, $unread);
    }

    // ══════════════════════════════════════════════════════════════════
    // TRANSACTIONS
    // ══════════════════════════════════════════════════════════════════

    public function test_add_message_rolls_back_on_persist_failure(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');

        $initialCount = TicketMessage::query()
            ->where('ticket_id', $ticket->id)
            ->count();

        $this->assertSame(0, $initialCount);

        $this->expectException(QueryException::class);

        try {
            DB::transaction(function () use ($ticket): void {
                $this->service->addMessage(
                    ticket: $ticket,
                    sender: $this->creator,
                    content: 'will rollback',
                );

                // Provoque une exception pour rollback
                throw new QueryException(
                    'sqlite',
                    'select 1',
                    [],
                    new \Exception('Simulated failure'),
                );
            });
        } finally {
            $finalCount = TicketMessage::query()
                ->where('ticket_id', $ticket->id)
                ->count();

            $this->assertSame(
                0,
                $finalCount,
                'Le message aurait dû être rollback après l\'échec de la transaction.'
            );
        }
    }

    // ══════════════════════════════════════════════════════════════════
    // UNICITÉ
    // ══════════════════════════════════════════════════════════════════

    public function test_create_ticket_generates_unique_reference(): void
    {
        $refs = [];

        for ($i = 0; $i < 5; $i++) {
            $ticket = $this->service->createTicket($this->creator, "T {$i}", 'desc');
            $refs[] = $ticket->reference;
        }

        $this->assertCount(5, array_unique($refs));
    }

    public function test_ticket_reference_is_uppercase(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');

        $this->assertSame(
            strtoupper($ticket->reference),
            $ticket->reference,
        );
    }

    // ══════════════════════════════════════════════════════════════════
    // POLYMORPHISME
    // ══════════════════════════════════════════════════════════════════

    public function test_created_by_polymorphic_relation_resolves_creator(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');

        $fresh = Ticket::query()->find($ticket->id);

        $this->assertNotNull($fresh);
        $this->assertNotNull($fresh->createdBy);
        $this->assertSame($this->creator->id, $fresh->createdBy->id);
        $this->assertInstanceOf(TestUser::class, $fresh->createdBy);
    }

    public function test_assigned_to_polymorphic_relation_resolves_resolver(): void
    {
        $ticket = $this->service->createTicket($this->creator, 'Help', 'desc');
        $ticket = $this->service->assignTicket($ticket, $this->resolver);

        $fresh = Ticket::query()->find($ticket->id);

        $this->assertNotNull($fresh);
        $this->assertNotNull($fresh->assignedTo);
        $this->assertSame($this->resolver->id, $fresh->assignedTo->id);
        $this->assertInstanceOf(TestUser::class, $fresh->assignedTo);
    }
}
