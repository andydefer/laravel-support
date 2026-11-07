<?php

declare(strict_types=1);

namespace AndyDefer\Support\Models;

use AndyDefer\Support\Enums\TicketPriority;
use AndyDefer\Support\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $reference
 * @property string $title
 * @property string $description
 * @property string $created_by_type
 * @property string $created_by_id
 * @property string|null $assigned_to_type
 * @property string|null $assigned_to_id
 * @property TicketStatus $status
 * @property TicketPriority $priority
 * @property Carbon|null $assigned_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property array|null $metadata
 * @property-read Model|null $createdBy
 * @property-read Model|null $assignedTo
 */
final class Ticket extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'support_tickets';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'reference',
        'title',
        'description',
        'created_by_type',
        'created_by_id',
        'assigned_to_type',
        'assigned_to_id',
        'status',
        'priority',
        'assigned_at',
        'resolved_at',
        'closed_at',
        'metadata',
    ];

    protected $casts = [
        'status' => TicketStatus::class,
        'priority' => TicketPriority::class,
        'assigned_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignedTo(): MorphTo
    {
        return $this->morphTo();
    }
}
