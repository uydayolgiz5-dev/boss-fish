<?php

namespace App\Models;

use App\Support\PricingPlans;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    protected $fillable = [
        'name',
        'is_active',
        'quiet_mode',
        'plan',
        'billing_period',
        'plan_starts_at',
        'plan_price',
        'access_expires_at',
        'next_check_at',
        'last_checked_at',
        'check_interval_days',
        'attention_note',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'quiet_mode' => 'boolean',
            'plan_starts_at' => 'datetime',
            'access_expires_at' => 'datetime',
            'plan_price' => 'integer',
            'next_check_at' => 'date',
            'last_checked_at' => 'datetime',
            'check_interval_days' => 'integer',
        ];
    }

    public function getAccessExpiredAttribute(): bool
    {
        return $this->access_expires_at !== null && $this->access_expires_at->isPast();
    }

    public function getAccessDaysLeftAttribute(): ?int
    {
        if (! $this->access_expires_at) {
            return null;
        }

        $seconds = now()->diffInSeconds($this->access_expires_at, false);

        return (int) floor($seconds / 86400);
    }

    public function getExpiringSoonAttribute(): bool
    {
        if (! $this->is_active || ! $this->access_expires_at || $this->access_expired) {
            return false;
        }

        $days = $this->access_days_left;

        return $days !== null
            && $days >= 0
            && $days <= PricingPlans::EXPIRING_SOON_DAYS;
    }

    /**
     * Tarif + davr bo‘yicha muddatni hisoblash (bosh sahifa tariflaridan).
     * Agar joriy muddat hali tugamagan bo‘lsa — yangi kunlar shu muddatga qo‘shiladi.
     */
    public function applyPlan(string $plan, string $period, bool $activate = true, ?int $checkIntervalDays = null): void
    {
        if (! PricingPlans::isValidPlan($plan) || ! PricingPlans::isValidPeriod($period)) {
            throw new \InvalidArgumentException('Noto‘g‘ri tarif yoki davr.');
        }

        $now = now();
        $days = PricingPlans::periodDays($period);
        $base = $now->copy();

        // Qolgan muddat bo‘lsa — yangi oy/yil shu sanadan keyin qo‘shiladi
        if ($this->access_expires_at && $this->access_expires_at->greaterThan($now)) {
            $base = $this->access_expires_at->copy();
        } else {
            $this->plan_starts_at = $now;
        }

        if (! $this->plan_starts_at) {
            $this->plan_starts_at = $now;
        }

        $this->plan = $plan;
        $this->billing_period = $period;
        $this->access_expires_at = $base->copy()->addDays($days);
        $this->plan_price = PricingPlans::priceFor($plan, $period);
        if ($activate) {
            $this->is_active = true;
        }
        if ($checkIntervalDays !== null) {
            $this->check_interval_days = PricingPlans::normalizeCheckInterval($checkIntervalDays);
        }
        $this->syncCheckScheduleForPlan($plan, resetSchedule: true);
    }

    public function requiresChecks(): bool
    {
        return PricingPlans::requiresChecks((string) $this->plan);
    }

    public function needsCheck(): bool
    {
        // Tinch rejim: mijoz ishlayveradi, egada tekshiruv chiqmaydi
        if (! $this->requiresChecks() || ! $this->is_active || $this->quiet_mode) {
            return false;
        }

        if (! $this->next_check_at) {
            return true;
        }

        return $this->next_check_at->lte(now()->startOfDay());
    }

    public function checkApproaching(?int $days = null): bool
    {
        if (! $this->requiresChecks() || ! $this->is_active || $this->quiet_mode || $this->needsCheck()) {
            return false;
        }

        if (! $this->next_check_at) {
            return false;
        }

        $days ??= PricingPlans::CHECK_APPROACHING_DAYS;

        return $this->next_check_at->lte(now()->addDays($days)->startOfDay())
            && $this->next_check_at->gt(now()->startOfDay());
    }

    /**
     * Tinch rejim: egada tekshiruv / mijozga kunlik ogohlantirishlar o‘chadi.
     * Kabinet va barcha ishlar ochiq qoladi.
     */
    public function isQuiet(): bool
    {
        return (bool) $this->quiet_mode;
    }

    public function setQuietMode(bool $quiet): void
    {
        $wasQuiet = (bool) $this->quiet_mode;
        $this->quiet_mode = $quiet;

        if ($quiet) {
            return;
        }

        // Tinch rejimdan chiqganda tekshiruv jadvalini tiklash
        if ($wasQuiet && $this->is_active && $this->requiresChecks()) {
            $this->syncCheckScheduleForPlan(resetSchedule: true);
        }
    }

    public function syncCheckScheduleForPlan(?string $plan = null, bool $resetSchedule = false): void
    {
        $plan ??= (string) $this->plan;
        $interval = PricingPlans::normalizeCheckInterval(
            $this->check_interval_days ?: PricingPlans::defaultCheckInterval($plan)
        );
        $this->check_interval_days = $interval;

        if ($resetSchedule || ! $this->next_check_at) {
            $this->next_check_at = now()->addDays($interval)->toDateString();
        }
    }

    public function markChecked(?string $note = null): void
    {
        if (! $this->requiresChecks()) {
            return;
        }

        $interval = PricingPlans::normalizeCheckInterval(
            $this->check_interval_days ?: PricingPlans::defaultCheckInterval((string) $this->plan)
        );
        $this->check_interval_days = $interval;
        $this->last_checked_at = now();
        $this->next_check_at = now()->addDays($interval)->toDateString();
        if ($note !== null && $note !== '') {
            $this->attention_note = trim($note);
        } else {
            $this->attention_note = null;
        }
        $this->save();
    }

    /** Muddat tugagan bo‘lsa passiv qilish. */
    public function deactivateIfExpired(): bool
    {
        if (! $this->access_expires_at || ! $this->access_expires_at->isPast()) {
            return false;
        }

        if (! $this->is_active) {
            return false;
        }

        $this->is_active = false;
        $this->save();

        return true;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function admin(): HasOne
    {
        return $this->hasOne(User::class)->where('type', User::TYPE_COMPANY_ADMIN);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function farms(): HasMany
    {
        return $this->hasMany(Farm::class);
    }

    /** 1 kompaniya = 1 xo‘jalik */
    public function farm(): HasOne
    {
        return $this->hasOne(Farm::class)->oldestOfMany();
    }

    public function ponds(): HasMany
    {
        return $this->hasMany(Pond::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CompanyMessage::class);
    }
}
