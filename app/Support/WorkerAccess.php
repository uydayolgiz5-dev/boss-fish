<?php

namespace App\Support;

use App\Models\User;

class WorkerAccess
{
    public static function linkedDirector(User $worker): ?User
    {
        if (! $worker->isEmployee()) {
            return null;
        }

        if (! $worker->company_id) {
            return null;
        }

        return User::query()
            ->where('company_id', $worker->company_id)
            ->where('type', User::TYPE_COMPANY_ADMIN)
            ->first(['id', 'name', 'phone', 'access_expires_at', 'type', 'is_active', 'company_id']);
    }

    public static function isBlockedByDirectorAccess(User $worker): bool
    {
        return self::loginBlockMessage($worker) !== null;
    }

    public static function loginBlockMessage(User $worker): ?string
    {
        if (! $worker->isEmployee()) {
            return null;
        }

        $company = $worker->company;
        if (! $company) {
            return 'Hodim kompaniyaga biriktirilmagan. Superadmin bilan bog‘laning.';
        }

        if (! $company->is_active) {
            return 'Kompaniya nofaol (mavsum yakunlangan yoki to‘lov). BOSS bilan bog‘laning.';
        }

        return null;
    }
}
