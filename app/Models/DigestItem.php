<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An email alert held back for the person's next digest.
 *
 * @property int $id
 * @property int $user_id
 * @property string $category
 * @property string $title
 * @property string $body
 * @property string|null $url
 * @property Carbon|null $digested_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['category', 'title', 'body', 'url'])]
class DigestItem extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'digested_at' => 'datetime',
        ];
    }

    /**
     * Get the person the item is for.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
