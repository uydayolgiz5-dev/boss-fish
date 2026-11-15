<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Farm;
use App\Models\Pond;
use App\Models\User;
use Illuminate\Http\Request;

class OwnerAccess
{
    public static function actingCompanyId(?Request $request = null): ?int
    {
        $request ??= request();

        if (! $request) {
            return null;
        }

        $raw = $request->header('X-Acting-Company-Id')
            ?: $request->header('X-Acting-Director-Id')
            ?: $request->query('company_id')
            ?: $request->query('director_id');

        if ($raw === null || $raw === '') {
            return null;
        }

        return (int) $raw;
    }

    /** @deprecated use actingCompanyId */
    public static function actingDirectorId(?Request $request = null): ?int
    {
        return self::actingCompanyId($request);
    }

    public static function actingCompany(?Request $request = null): ?Company
    {
        $companyId = self::actingCompanyId($request);

        if (! $companyId) {
            return null;
        }

        return Company::query()->whereKey($companyId)->first();
    }

    public static function actingDirector(?Request $request = null): ?User
    {
        $company = self::actingCompany($request);

        return $company?->admin;
    }

    public static function requireActingDirector(?Request $request = null): User
    {
        $director = self::actingDirector($request);

        if (! $director) {
            abort(403, 'Kompaniya tanlanmagan. Superadmin kabinetidan kompaniyani tanlang.');
        }

        return $director;
    }

    public static function farmBelongsToDirector(Farm $farm, int $directorId): bool
    {
        $admin = User::query()->whereKey($directorId)->first();
        if ($admin?->company_id) {
            return (int) $farm->company_id === (int) $admin->company_id;
        }

        return (int) $farm->director_id === $directorId;
    }

    public static function pondBelongsToDirector(Pond $pond, int $directorId): bool
    {
        $admin = User::query()->whereKey($directorId)->first();
        if ($admin?->company_id) {
            if ((int) $pond->company_id === (int) $admin->company_id) {
                return true;
            }
            $pond->loadMissing('farm');

            return $pond->farm && (int) $pond->farm->company_id === (int) $admin->company_id;
        }

        if ((int) $pond->director_id === $directorId) {
            return true;
        }

        $pond->loadMissing('farm');

        return $pond->farm && (int) $pond->farm->director_id === $directorId;
    }

    public static function authorizeFarmForOwner(User $user, Farm $farm, ?Request $request = null): void
    {
        if (! $user->isOwnerCabinet()) {
            return;
        }

        $request ??= request();

        if ($request->is('api/owner/*') || $request->is('api/superadmin/*')) {
            return;
        }

        $companyId = self::actingCompanyId($request);
        if ($companyId && (int) $farm->company_id === $companyId) {
            return;
        }

        $director = self::requireActingDirector($request);

        if (! self::farmBelongsToDirector($farm, $director->id)) {
            abort(403, 'Bu hojalik tanlangan kompaniyaga tegishli emas.');
        }
    }

    public static function authorizePondForOwner(User $user, Pond $pond, ?Request $request = null): void
    {
        if (! $user->isOwnerCabinet()) {
            return;
        }

        $request ??= request();

        if ($request->is('api/owner/*') || $request->is('api/superadmin/*') || $request->is('api/director/ponds/*/close') || $request->is('api/company/ponds/*/close')) {
            return;
        }

        $companyId = self::actingCompanyId($request);
        if ($companyId && (int) $pond->company_id === $companyId) {
            return;
        }

        $director = self::requireActingDirector($request);

        if (! self::pondBelongsToDirector($pond, $director->id)) {
            abort(403, 'Bu havza tanlangan kompaniyaga tegishli emas.');
        }
    }
}
