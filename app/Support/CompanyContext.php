<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CompanyContext
{
    public static function id(?User $user = null): ?int
    {
        $user = $user ?? auth()->user();

        if (! $user) {
            return null;
        }

        if ($user->company_id) {
            return (int) $user->company_id;
        }

        // BOSS / platform_admin kompaniya kabinetida (X-Acting-Company-Id yoki X-Acting-Director-Id)
        if ($user->isOwnerCabinet()) {
            $acting = OwnerAccess::actingCompanyId();
            if ($acting) {
                return $acting;
            }
        }

        return null;
    }

    /** Kompaniya konteksti majburiy — yo‘q bo‘lsa 403 */
    public static function requireId(?User $user = null): int
    {
        $companyId = self::id($user);
        if (! $companyId) {
            abort(403, 'Kompaniya tanlanmagan. Superadmin kabinetidan kompaniyani oching.');
        }

        return $companyId;
    }

    public static function isPlatform(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        return $user && $user->company_id === null && $user->isPlatformUser();
    }

    public static function scope(Builder $query, string $column = 'company_id', ?User $user = null): Builder
    {
        $companyId = self::id($user);
        if ($companyId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($column, $companyId);
    }

    public static function assertSameCompany(Model $model, ?User $user = null): void
    {
        $companyId = self::id($user);
        $modelCompanyId = (int) ($model->getAttribute('company_id') ?? 0);

        if (! $companyId || $modelCompanyId !== $companyId) {
            abort(403, 'Bu resurs boshqa kompaniyaga tegishli.');
        }
    }

    public static function applyCompanyId(array $payload, ?User $user = null): array
    {
        $companyId = self::id($user);
        if ($companyId) {
            $payload['company_id'] = $companyId;
        }

        return $payload;
    }
}
