<?php

namespace App\Support;

use App\Models\Pond;
use App\Models\User;

class PondAccess
{
    public static function authorizeDirectorOrOwner(User $user, Pond $pond): void
    {
        if ($user->isOwnerCabinet()) {
            OwnerAccess::authorizePondForOwner($user, $pond);

            return;
        }

        if ($user->company_id && (int) $pond->company_id === (int) $user->company_id) {
            return;
        }

        $pond->loadMissing('farm');
        if ($user->company_id && $pond->farm && (int) $pond->farm->company_id === (int) $user->company_id) {
            return;
        }

        abort(403, 'Bu havza sizniki emas');
    }

    public static function authorizeWorker(User $user, Pond $pond): void
    {
        if (! $user->isEmployee()) {
            abort(403);
        }

        if ($user->company_id && (int) $pond->company_id === (int) $user->company_id) {
            return;
        }

        $pond->loadMissing('farm');

        if ($pond->farm && $user->assignedFarms()->where('farms.id', $pond->farm_id)->exists()) {
            return;
        }

        if ($user->assignedPonds()->where('ponds.id', $pond->id)->exists()) {
            return;
        }

        abort(403, 'Bu havzaga biriktirilmagansiz');
    }

    public static function workerPond(User $user): ?Pond
    {
        $farm = FarmAccess::workerFarm($user);
        if ($farm) {
            return $farm->ponds()->orderBy('id')->first();
        }

        return $user->assignedPonds()->first();
    }
}
