<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Farm;
use App\Models\Pond;
use App\Models\User;

class FarmAccess
{
    /**
     * 1 kompaniya = 1 xo‘jalik. Yo‘q bo‘lsa yaratadi.
     */
    public static function ensureForCompany(Company|int $company, ?User $admin = null, ?User $creator = null): Farm
    {
        $companyId = $company instanceof Company ? $company->id : (int) $company;
        $companyModel = $company instanceof Company ? $company : Company::query()->findOrFail($companyId);

        $farm = Farm::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->first();

        if ($farm) {
            return $farm;
        }

        $admin ??= User::query()
            ->where('company_id', $companyId)
            ->where('type', User::TYPE_COMPANY_ADMIN)
            ->orderBy('id')
            ->first();

        $region = Regions::all()[0] ?? 'Toshkent viloyati';

        return Farm::withoutGlobalScopes()->create([
            'name' => $companyModel->name,
            'region' => $region,
            'company_id' => $companyId,
            'director_id' => $admin?->id,
            'created_by' => $creator?->id ?? $admin?->id,
            'status' => 'active',
        ]);
    }

    public static function forCompanyId(?int $companyId): ?Farm
    {
        if (! $companyId) {
            return null;
        }

        return Farm::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->first();
    }

    public static function forUser(User $user): ?Farm
    {
        if ($user->company_id) {
            return self::ensureForCompany($user->company_id, $user->isCompanyAdmin() ? $user : null, $user);
        }

        return null;
    }

    public static function authorizeDirectorOrOwner(User $user, Farm $farm): void
    {
        if ($user->isOwnerCabinet()) {
            OwnerAccess::authorizeFarmForOwner($user, $farm);

            return;
        }

        if ($user->company_id && (int) $farm->company_id === (int) $user->company_id) {
            return;
        }

        abort(403, 'Bu kompaniya resursi sizniki emas');
    }

    public static function authorizeWorker(User $user, Farm $farm): void
    {
        if (! $user->isEmployee()) {
            abort(403);
        }

        if ($user->company_id && (int) $farm->company_id === (int) $user->company_id) {
            return;
        }

        if ($user->assignedFarms()->where('farms.id', $farm->id)->exists()) {
            return;
        }

        abort(403, 'Bu kompaniyaga biriktirilmagansiz');
    }

    public static function workerFarm(User $user): ?Farm
    {
        if ($user->company_id) {
            return self::forCompanyId((int) $user->company_id)
                ?? self::ensureForCompany((int) $user->company_id, null, $user);
        }

        return $user->assignedFarms()->orderBy('farms.id')->first();
    }

    public static function farmForPond(Pond $pond): Farm
    {
        $farm = $pond->farm;

        if (! $farm && $pond->company_id) {
            $farm = self::forCompanyId((int) $pond->company_id);
        }

        if (! $farm) {
            abort(422, 'Havza kompaniyaga biriktirilmagan');
        }

        return $farm;
    }
}
