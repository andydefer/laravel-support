<?php

declare(strict_types=1);

namespace AndyDefer\Support\Models;

use AndyDefer\Support\Database\Factories\TicketMessageFactory;
use AndyDefer\Support\Enums\MessageStatus;
use AndyDefer\Support\Enums\SenderType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $ticket_id
 * @property string|null $reply_to_id
 * @property SenderType $sender_type
 * @property string $author_type
 * @property string $author_id
 * @property string $content
 * @property MessageStatus $status
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Ticket $ticket
 * @property-read TicketMessage|null $replyTo
 * @property-read Collection<int, TicketMessage> $replies
 * @property-read Model|null $author
 */
final class TicketMessage extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'support_ticket_messages';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'ticket_id',
        'reply_to_id',
        'sender_type',
        'author_type',
        'author_id',
        'content',
        'status',
        'metadata',
    ];

    protected $casts = [
        'sender_type' => SenderType::class,
        'status' => MessageStatus::class,
        'metadata' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'reply_to_id');
    }

    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): TicketMessageFactory
    {
        return TicketMessageFactory::new();
    }
}
