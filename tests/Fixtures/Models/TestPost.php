<?php

declare(strict_types=1);

namespace AndyDefer\LaravelRatings\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestPost extends Model
{
    protected $table = 'test_posts';

    protected $fillable = [
        'user_id',
        'title',
        'body',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(TestUser::class, 'user_id');
    }
}
