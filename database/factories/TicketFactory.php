<?php

declare(strict_types=1);

namespace AndyDefer\Support\Database\Factories;

use AndyDefer\Support\Enums\MessageStatus;
use AndyDefer\Support\Enums\SenderType;
use AndyDefer\Support\Models\Ticket;
use AndyDefer\Support\Models\TicketMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketMessage>
 */
final class TicketMessageFactory extends Factory
{
    protected $model = TicketMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'reply_to_id' => null,
            'sender_type' => SenderType::CREATOR,
            'content' => $this->faker->paragraph(2),
            'status' => MessageStatus::SENT,
            'is_internal' => false,
            'metadata' => null,
        ];
    }

    public function fromCreator(): self
    {
        return $this->state(['sender_type' => SenderType::CREATOR]);
    }

    public function fromResolver(): self
    {
        return $this->state(['sender_type' => SenderType::RESOLVER]);
    }

    public function sent(): self
    {
        return $this->state(['status' => MessageStatus::SENT]);
    }

    public function dispatched(): self
    {
        return $this->state(['status' => MessageStatus::DISPATCHED]);
    }

    public function read(): self
    {
        return $this->state(['status' => MessageStatus::READ]);
    }

    public function internal(): self
    {
        return $this->state(['is_internal' => true]);
    }

    public function public(): self
    {
        return $this->state(['is_internal' => false]);
    }

    public function forTicket(Ticket $ticket): self
    {
        return $this->state(['ticket_id' => $ticket->id]);
    }

    public function replyingTo(TicketMessage $parent): self
    {
        return $this->state([
            'ticket_id' => $parent->ticket_id,
            'reply_to_id' => $parent->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return $this->state(['metadata' => $metadata]);
    }
}
