<?php

declare(strict_types=1);

namespace AndyDefer\Support\Tests\Feature\Services;

use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\Nemesis\Datas\ErrorResponseData;
use AndyDefer\Nemesis\Helpers\NemesisHelper;
use AndyDefer\Support\Contracts\Services\SupportServiceInterface;
use AndyDefer\Support\Enums\TicketPriority;
use AndyDefer\Support\Enums\TicketStatus;
use AndyDefer\Support\Models\Ticket;
use AndyDefer\Support\Models\TicketMessage;
use AndyDefer\Support\Records\CreateTicketRecord;
use AndyDefer\Support\Records\MarkAllMessagesReadRecord;
use AndyDefer\Support\Records\SendMessageRecord;
use AndyDefer\Support\Records\ShowTicketRecord;
use AndyDefer\Support\Records\UpdateTicketRecord;
use AndyDefer\Support\Services\TicketRouteManagerService;
use AndyDefer\Support\Tests\Fixtures\Models\TestUser;
use AndyDefer\Support\Tests\IntegrationTestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class TicketRouteManagerServiceHooksTest extends IntegrationTestCase
{
    private SpyTicketRouteManager $manager;

    private TestUser $creator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = TestUser::create([
            'name' => 'C', 'email' => 'c@example.com',
            'status' => 'active', 'role' => 'user', 'age' => 30, 'metadata' => null,
        ]);

        $this->mock(NemesisHelper::class, function ($mock): void {
            $mock->shouldReceive('getCurrentAuthenticatable')
                ->andReturn($this->creator);
        });

        $this->manager = new SpyTicketRouteManager(
            $this->app->make(SupportServiceInterface::class),
            $this->app->make(NemesisHelper::class),
        );
    }

    private function makeTicket(): Ticket
    {
        return Ticket::create([
            'id' => (string) Str::uuid(),
            'reference' => 'TKT-HOOK'.strtoupper(Str::random(6)),
            'title' => 'T', 'description' => 'D',
            'created_by_type' => $this->creator->getMorphClass(),
            'created_by_id' => (string) $this->creator->getKey(),
            'status' => TicketStatus::OPEN->value,
            'priority' => TicketPriority::NORMAL->value,
        ]);
    }

    public function test_store_ticket_invokes_before_and_after_hooks(): void
    {
        $response = $this->manager->storeTicket(new CreateTicketRecord(
            title: 'T',
            description: 'D',
        ));

        $this->assertInstanceOf(ResponseFactory::class, $response);
        $this->assertContains('beforeStoreTicket', $this->manager->calls);
        $this->assertContains('afterStoreTicket', $this->manager->calls);
    }

    public function test_update_ticket_invokes_hooks(): void
    {
        $ticket = $this->makeTicket();

        $response = $this->manager->updateTicket(new UpdateTicketRecord(
            ticket_id: $ticket->id,
            title: 'New',
        ));

        $this->assertInstanceOf(ResponseFactory::class, $response);
        $this->assertContains('beforeUpdateTicket', $this->manager->calls);
        $this->assertContains('afterUpdateTicket', $this->manager->calls);
    }

    public function test_show_ticket_invokes_hooks(): void
    {
        $ticket = $this->makeTicket();

        $response = $this->manager->showTicket(new ShowTicketRecord(
            ticket_id: $ticket->id,
        ));

        $this->assertInstanceOf(ResponseFactory::class, $response);
        $this->assertContains('beforeShowTicket', $this->manager->calls);
        $this->assertContains('afterShowTicket', $this->manager->calls);
    }

    public function test_store_message_invokes_hooks(): void
    {
        $ticket = $this->makeTicket();

        $response = $this->manager->storeMessage(new SendMessageRecord(
            ticket_id: $ticket->id,
            content: 'hello',
        ));

        $this->assertInstanceOf(ResponseFactory::class, $response);
        $this->assertContains('beforeStoreMessage', $this->manager->calls);
        $this->assertContains('afterStoreMessage', $this->manager->calls);
    }

    public function test_mark_all_read_invokes_hooks(): void
    {
        $ticket = $this->makeTicket();

        TicketMessage::create([
            'id' => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'sender_type' => 'creator',
            'content' => 'x',
            'status' => 'sent',
            'is_internal' => false,
        ]);

        $response = $this->manager->markAllMessagesRead(new MarkAllMessagesReadRecord(
            ticket_id: $ticket->id,
        ));

        $this->assertInstanceOf(ResponseFactory::class, $response);
        $this->assertContains('beforeMarkAllMessagesRead', $this->manager->calls);
        $this->assertContains('afterMarkAllMessagesRead', $this->manager->calls);
    }
}

final class SpyTicketRouteManager extends TicketRouteManagerService
{
    /** @var list<string> */
    public array $calls = [];

    protected function beforeStoreTicket(Model $creator, string $title, string $description, ?TicketPriority $priority, ?StrictAssociative $metadata): ?ErrorResponseData
    {
        $this->calls[] = 'beforeStoreTicket';

        return null;
    }

    protected function afterStoreTicket(Ticket $ticket): ?ErrorResponseData
    {
        $this->calls[] = 'afterStoreTicket';

        return null;
    }

    protected function beforeUpdateTicket(?string $ticketId, ?string $title, ?string $description, ?TicketPriority $priority): ?ErrorResponseData
    {
        $this->calls[] = 'beforeUpdateTicket';

        return null;
    }

    protected function afterUpdateTicket(Ticket $ticket): ?ErrorResponseData
    {
        $this->calls[] = 'afterUpdateTicket';

        return null;
    }

    protected function beforeShowTicket(string $ticketId): ?ErrorResponseData
    {
        $this->calls[] = 'beforeShowTicket';

        return null;
    }

    protected function afterShowTicket(Ticket $ticket): ?ErrorResponseData
    {
        $this->calls[] = 'afterShowTicket';

        return null;
    }

    protected function beforeStoreMessage(string $ticketId, Model $sender, string $content, ?string $replyToId, bool $isInternal, ?StrictAssociative $metadata): ?ErrorResponseData
    {
        $this->calls[] = 'beforeStoreMessage';

        return null;
    }

    protected function afterStoreMessage(TicketMessage $message): ?ErrorResponseData
    {
        $this->calls[] = 'afterStoreMessage';

        return null;
    }

    protected function beforeMarkAllMessagesRead(string $ticketId, Model $reader): ?ErrorResponseData
    {
        $this->calls[] = 'beforeMarkAllMessagesRead';

        return null;
    }

    protected function afterMarkAllMessagesRead(string $ticketId, Model $reader, int $count): ?ErrorResponseData
    {
        $this->calls[] = 'afterMarkAllMessagesRead';

        return null;
    }
}
