<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerProject extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'client_name',
        'region',
        'area_ha',
        'recommended_method',
        'site_score',
        'status',
        'inputs',
        'results',
    ];

    protected function casts(): array
    {
        return [
            'area_ha' => 'float',
            'site_score' => 'integer',
            'inputs' => 'array',
            'results' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function toPayload(bool $full = false): array
    {
        $payload = [
            'id' => $this->id,
            'title' => $this->title,
            'client_name' => $this->client_name,
            'region' => $this->region,
            'area_ha' => $this->area_ha,
            'recommended_method' => $this->recommended_method,
            'site_score' => $this->site_score,
            'status' => $this->status,
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];

        if ($full) {
            $payload['inputs'] = $this->inputs ?? [];
            $payload['results'] = $this->results ?? null;
        } else {
            $summary = is_array($this->results) ? ($this->results['summary'] ?? null) : null;
            $payload['summary'] = $summary;
        }

        return $payload;
    }
}
