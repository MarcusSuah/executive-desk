<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PushSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $endpoint
 * @property string $endpoint_hash
 * @property string $public_key
 * @property string $auth_token
 * @property string $content_encoding
 * @property string|null $device_name
 * @property string|null $user_agent
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['endpoint', 'endpoint_hash', 'public_key', 'auth_token', 'content_encoding', 'device_name', 'user_agent'])]
#[Hidden(['endpoint', 'endpoint_hash', 'public_key', 'auth_token'])]
class PushSubscription extends Model
{
    /** @use HasFactory<PushSubscriptionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'endpoint' => 'encrypted',
            'public_key' => 'encrypted',
            'auth_token' => 'encrypted',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Hash an endpoint so it can be looked up without decrypting stored values.
     */
    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    /**
     * Get the user who registered the subscription.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
