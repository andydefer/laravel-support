<?php

declare(strict_types=1);

namespace AndyDefer\Support\Database\Factories;

use AndyDefer\Support\Enums\TicketPriority;
use AndyDefer\Support\Enums\TicketStatus;
use AndyDefer\Support\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Ticket>
 */
final class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'TKT-'.strtoupper($this->faker->unique()->bothify('##########')),
            'title' => $this->faker->sentence(6),
            'description' => $this->faker->paragraph(3),
            'created_by_type' => 'App\\Models\\User',
            'created_by_id' => (string) $this->faker->unique()->numberBetween(1, 999999),
            'assigned_to_type' => null,
            'assigned_to_id' => null,
            'status' => TicketStatus::OPEN,
            'priority' => TicketPriority::NORMAL,
            'assigned_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'metadata' => null,
        ];
    }

    // ────────────────────────────────────────────────────────────────
    // Statuts
    // ────────────────────────────────────────────────────────────────

    public function open(): self
    {
        return $this->state([
            'status' => TicketStatus::OPEN,
            'resolved_at' => null,
            'closed_at' => null,
        ]);
    }

    public function pending(): self
    {
        return $this->state([
            'status' => TicketStatus::PENDING,
            'resolved_at' => null,
            'closed_at' => null,
        ]);
    }

    public function resolved(): self
    {
        return $this->state([
            'status' => TicketStatus::RESOLVED,
            'resolved_at' => now(),
            'closed_at' => null,
        ]);
    }

    public function closed(): self
    {
        return $this->state([
            'status' => TicketStatus::CLOSED,
            'resolved_at' => now()->subHour(),
            'closed_at' => now(),
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    // Priorités
    // ────────────────────────────────────────────────────────────────

    public function low(): self
    {
        return $this->state(['priority' => TicketPriority::LOW]);
    }

    public function normal(): self
    {
        return $this->state(['priority' => TicketPriority::NORMAL]);
    }

    public function high(): self
    {
        return $this->state(['priority' => TicketPriority::HIGH]);
    }

    public function urgent(): self
    {
        return $this->state(['priority' => TicketPriority::URGENT]);
    }

    // ────────────────────────────────────────────────────────────────
    // Polymorphisme
    // ────────────────────────────────────────────────────────────────

    public function createdBy(Model $model): self
    {
        return $this->state([
            'created_by_type' => $model->getMorphClass(),
            'created_by_id' => (string) $model->getKey(),
        ]);
    }

    public function assignedTo(Model $model): self
    {
        return $this->state([
            'assigned_to_type' => $model->getMorphClass(),
            'assigned_to_id' => (string) $model->getKey(),
            'assigned_at' => now(),
        ]);
    }

    public function unassigned(): self
    {
        return $this->state([
            'assigned_to_type' => null,
            'assigned_to_id' => null,
            'assigned_at' => null,
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    // Metadata
    // ────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return $this->state(['metadata' => $metadata]);
    }

    public function withoutMetadata(): self
    {
        return $this->state(['metadata' => null]);
    }
}
