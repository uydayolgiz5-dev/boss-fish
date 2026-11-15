<?php

namespace App\Models;

use App\Support\CompanyPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const TYPE_SUPERADMIN = 'superadmin';
    /** BOSS yordamchi admini — kompaniya/ariza (kundalik va Venger yo‘q) */
    public const TYPE_PLATFORM_ADMIN = 'platform_admin';
    public const TYPE_SUPERADMIN_STAFF = 'superadmin_staff';
    public const TYPE_COMPANY_ADMIN = 'company_admin';
    public const TYPE_EMPLOYEE = 'employee';

    /** @deprecated kept for transitional test helpers */
    public const ROLE_OWNER = self::TYPE_SUPERADMIN;
    public const ROLE_DIRECTOR = self::TYPE_COMPANY_ADMIN;
    public const ROLE_WORKER = self::TYPE_EMPLOYEE;
    public const ROLE_VENGER = self::TYPE_SUPERADMIN_STAFF;

    protected $fillable = [
        'name',
        'email',
        'login',
        'password',
        'password_plain',
        'type',
        'phone',
        'is_active',
        'access_expires_at',
        'created_by',
        'company_id',
        'role_id',
        'director_id',
    ];

    protected $hidden = [
        'password',
        'password_plain',
        'remember_token',
    ];

    protected $appends = [
        'access_days_left',
        'access_expiring_soon',
        'access_expired',
        'permissions',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_plain' => 'encrypted',
            'is_active' => 'boolean',
            'access_expires_at' => 'datetime',
        ];
    }

    public function getRoleAttribute(): ?string
    {
        return match ($this->type) {
            self::TYPE_SUPERADMIN => 'owner',
            self::TYPE_PLATFORM_ADMIN => 'admin',
            self::TYPE_SUPERADMIN_STAFF => 'venger',
            self::TYPE_COMPANY_ADMIN => 'director',
            self::TYPE_EMPLOYEE => 'worker',
            default => $this->type,
        };
    }

    public function getPermissionsAttribute(): array
    {
        if ($this->isSuperadmin() || $this->isPlatformAdmin() || $this->isCompanyAdmin()) {
            return array_merge(CompanyPermissions::ALL, CompanyPermissions::ADMIN_ONLY);
        }

        if ($this->isSuperadminStaff()) {
            return CompanyPermissions::ALL;
        }

        if ($this->relationLoaded('companyRole') || $this->role_id) {
            $role = $this->relationLoaded('companyRole')
                ? $this->companyRole
                : $this->companyRole()->first();

            if (! $role || $role->is_active === false) {
                return [];
            }

            return CompanyPermissions::normalize($role->permissions ?? []);
        }

        return [];
    }

    public function getAccessDaysLeftAttribute(): ?int
    {
        $expires = $this->access_expires_at;
        if (! $expires && $this->company_id) {
            $company = $this->relationLoaded('company') ? $this->company : $this->company()->first();
            $expires = $company?->access_expires_at;
        }

        if (! $expires) {
            return null;
        }

        $seconds = now()->diffInSeconds($expires, false);

        return (int) floor($seconds / 86400);
    }

    public function getAccessExpiredAttribute(): bool
    {
        $expires = $this->access_expires_at;
        if (! $expires && $this->company_id) {
            $company = $this->relationLoaded('company') ? $this->company : $this->company()->first();
            $expires = $company?->access_expires_at;
        }

        return $expires !== null && $expires->isPast();
    }

    public const ACCESS_EXPIRING_DAYS = 10;

    public function getAccessExpiringSoonAttribute(): bool
    {
        $left = $this->access_days_left;

        return $left !== null && $left >= 0 && $left <= self::ACCESS_EXPIRING_DAYS;
    }

    public function grantAccessDays(int $days, bool $extend = false): void
    {
        if ($this->company_id) {
            $company = $this->company ?: Company::find($this->company_id);
            if ($company) {
                if ($extend && $company->access_expires_at && $company->access_expires_at->isFuture()) {
                    $company->access_expires_at = $company->access_expires_at->copy()->addDays($days);
                } else {
                    $company->access_expires_at = now()->addDays($days);
                }
                $company->save();

                return;
            }
        }

        if ($extend && $this->access_expires_at && $this->access_expires_at->isFuture()) {
            $this->access_expires_at = $this->access_expires_at->copy()->addDays($days);
        } else {
            $this->access_expires_at = now()->addDays($days);
        }

        $this->save();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Hodimning biriktirilgan kompaniya admini (legacy director_id) */
    public function director(): BelongsTo
    {
        return $this->belongsTo(self::class, 'director_id');
    }

    public function companyRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function workers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by')->where('type', self::TYPE_EMPLOYEE);
    }

    public function directedFarms(): HasMany
    {
        return $this->hasMany(Farm::class, 'company_id', 'company_id');
    }

    public function clientSubscription(): HasOne
    {
        return $this->hasOne(ClientSubscription::class, 'company_id', 'company_id');
    }

    public function directedPonds(): HasMany
    {
        return $this->hasMany(Pond::class, 'company_id', 'company_id');
    }

    public function assignedFarms(): BelongsToMany
    {
        return $this->belongsToMany(Farm::class, 'farm_worker')
            ->withPivot(['position', 'hired_at'])
            ->withTimestamps();
    }

    public function assignedPonds(): BelongsToMany
    {
        return $this->belongsToMany(Pond::class, 'pond_worker')
            ->withPivot(['position', 'hired_at'])
            ->withTimestamps();
    }

    public function isPlatformUser(): bool
    {
        return $this->isSuperadmin() || $this->isPlatformAdmin() || $this->isSuperadminStaff();
    }

    public function isSuperadmin(): bool
    {
        return $this->type === self::TYPE_SUPERADMIN;
    }

    public function isPlatformAdmin(): bool
    {
        return $this->type === self::TYPE_PLATFORM_ADMIN;
    }

    /** BOSS kabineti: asosiy superadmin yoki yordamchi admin */
    public function isOwnerCabinet(): bool
    {
        return $this->isSuperadmin() || $this->isPlatformAdmin();
    }

    public function isSuperadminStaff(): bool
    {
        return $this->type === self::TYPE_SUPERADMIN_STAFF;
    }

    public function isCompanyAdmin(): bool
    {
        return $this->type === self::TYPE_COMPANY_ADMIN;
    }

    public function isEmployee(): bool
    {
        return $this->type === self::TYPE_EMPLOYEE;
    }

    public function isOwner(): bool
    {
        return $this->isOwnerCabinet();
    }

    public function isDirector(): bool
    {
        return $this->isCompanyAdmin();
    }

    public function isWorker(): bool
    {
        return $this->isEmployee();
    }

    public function isVenger(): bool
    {
        return $this->isSuperadminStaff();
    }

    public function canAccess(string $section): bool
    {
        if ($this->isSuperadmin() || $this->isPlatformAdmin() || $this->isCompanyAdmin()) {
            return true;
        }

        if (in_array($section, CompanyPermissions::ADMIN_ONLY, true)) {
            return false;
        }

        return in_array($section, $this->permissions, true);
    }
}
