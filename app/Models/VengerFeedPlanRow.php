<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VengerFeedPlanRow extends Model
{
    protected $fillable = [
        'venger_feed_plan_id',
        'venger_client_id',
        'sort_order',
        'client_name',
        'phone',
        'location_text',
        'region',
        'district',
        'pond_area_ha',
        'last_year_feed_kg',
        'rating_10',
        'feedback',
        'next_year_carp_count',
        'next_year_extruder_kg',
        'next_year_granule_kg',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'pond_area_ha' => 'float',
            'last_year_feed_kg' => 'float',
            'rating_10' => 'integer',
            'next_year_carp_count' => 'integer',
            'next_year_extruder_kg' => 'float',
            'next_year_granule_kg' => 'float',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(VengerFeedPlan::class, 'venger_feed_plan_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(VengerClient::class, 'venger_client_id');
    }
}
