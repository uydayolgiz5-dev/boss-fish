<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class CompanyMessage extends Model
{
    public const SIDE_BOSS = 'boss';

    public const SIDE_CLIENT = 'client';

    protected $fillable = [
        'company_id',
        'sender_id',
        'sender_side',
        'title',
        'body',
        'image_path',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function isBoss(): bool
    {
        return $this->sender_side === self::SIDE_BOSS;
    }

    public function isClient(): bool
    {
        return $this->sender_side === self::SIDE_CLIENT;
    }

    public static function forCompany(int $companyId, int $limit = 200): Collection
    {
        return static::query()
            ->with(['sender:id,name'])
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public static function markSideRead(int $companyId, string $senderSide): int
    {
        return static::query()
            ->where('company_id', $companyId)
            ->where('sender_side', $senderSide)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->image_path);
    }

    public function toChatArray(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'sender_side' => $this->sender_side,
            'sender_name' => $this->sender?->name,
            'body' => $this->body,
            'image_url' => $this->imageUrl(),
            'read_at' => optional($this->read_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'mine' => null,
        ];
    }
}
