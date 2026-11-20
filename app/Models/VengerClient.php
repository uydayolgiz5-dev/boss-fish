<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VengerClient extends Model
{
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'address',
        'region',
        'district',
        'pond_area_ha',
        'phone',
        'phone2',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'pond_area_ha' => 'float',
        ];
    }

    public static function fullName(string $firstName, string $lastName): string
    {
        return trim($firstName.' '.$lastName);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(VengerResult::class)->latest('sold_on');
    }

    public function feedPlanRows(): HasMany
    {
        return $this->hasMany(VengerFeedPlanRow::class);
    }
}
