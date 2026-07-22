<?php

namespace App\Models;

use Database\Factories\SavedFilterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A named, user-owned, persisted filter Definition. May be shared with everyone.
 *
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property array<string, mixed> $definition
 * @property bool $is_shared
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $owner
 */
#[Fillable(['name', 'definition', 'is_shared'])]
class SavedFilter extends Model
{
    /** @use HasFactory<SavedFilterFactory> */
    use HasFactory, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'is_shared' => 'boolean',
        ];
    }

    /**
     * The user who owns this filter.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope to the filters a user may see: their own, plus anyone's shared ones.
     *
     * @param  Builder<SavedFilter>  $query
     * @return Builder<SavedFilter>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query->where($query->qualifyColumn('user_id'), $user->id)
                ->orWhere($query->qualifyColumn('is_shared'), true);
        });
    }
}
