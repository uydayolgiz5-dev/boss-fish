<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OwnerExperienceFolder extends Model
{
    protected $fillable = [
        'user_id',
        'parent_id',
        'title',
        'body',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function toPayload(bool $withChildren = false, bool $includeBody = true): array
    {
        $body = $this->body ?? '';
        $payload = [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'title' => $this->title,
            'has_notes' => trim((string) $body) !== '',
            'sort_order' => $this->sort_order,
            'children_count' => $this->relationLoaded('children')
                ? $this->children->count()
                : ($this->children_count ?? $this->children()->count()),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];

        if ($includeBody) {
            $payload['body'] = $body;
        }

        if ($withChildren) {
            $payload['children'] = $this->children
                ->map(fn (self $child) => $child->toPayload(false, false))
                ->values()
                ->all();
        }

        return $payload;
    }
}
