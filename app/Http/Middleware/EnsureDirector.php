<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDirector
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || (! $user->isCompanyAdmin() && ! $user->isEmployee() && ! $user->isOwnerCabinet())) {
            abort(403, 'Faqat kompaniya foydalanuvchisi kira oladi');
        }

        if ($user->isOwnerCabinet()) {
            return $next($request);
        }

        if (! $user->is_active) {
            abort(403, 'Hisobingiz faol emas.');
        }

        $company = $user->company;
        if ($company) {
            $company->deactivateIfExpired();
        }
        if (! $company || ! $company->is_active) {
            abort(403, 'Kompaniya nofaol (mavsum yakunlangan yoki to‘lov). BOSS bilan bog‘laning.');
        }

        return $next($request);
    }
}
