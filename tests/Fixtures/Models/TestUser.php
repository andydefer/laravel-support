<?php

declare(strict_types=1);

namespace AndyDefer\LaravelRatings\Tests\Fixtures\Models;

use AndyDefer\LaravelRatings\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\LaravelRatings\Tests\Fixtures\Enums\TestUserStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestUser extends Model
{
    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
        'status',
        'role',
        'age',
        'metadata',
    ];

    protected $casts = [
        'status' => TestUserStatus::class,
        'role' => TestUserRole::class,
        'metadata' => 'array',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(TestPost::class, 'user_id');
    }
}
