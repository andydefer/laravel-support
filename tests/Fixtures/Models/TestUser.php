<?php

declare(strict_types=1);

namespace AndyDefer\Support\Tests\Fixtures\Models;

use AndyDefer\LaravelNotification\Collections\NotificationRouteCollection;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use AndyDefer\Support\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\Support\Tests\Fixtures\Enums\TestUserStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestUser extends Model implements NotifiableInterface
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

    /**
     * Retourne les canaux de notification de cet utilisateur.
     *
     * Seul le canal `database` est utilisé par le package Support
     * (les canaux externes comme FCM/Mail sont gérés par l'application hôte).
     */
    public function getNotificationChannels(): NotificationRouteCollection
    {
        return NotificationRouteCollection::from([
            new NotificationRouteVO('database', (string) $this->getKey()),
        ]);
    }
}
