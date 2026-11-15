<?php

namespace App\Models;

use App\Support\CompanyPermissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'permissions',
        'job_roles',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'job_roles' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Role $role) {
            $role->permissions = CompanyPermissions::normalize($role->permissions ?? []);
            $role->job_roles = CompanyPermissions::normalizeJobs($role->job_roles ?? []);
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function allows(string $section): bool
    {
        return in_array($section, $this->permissions ?? [], true);
    }
}
