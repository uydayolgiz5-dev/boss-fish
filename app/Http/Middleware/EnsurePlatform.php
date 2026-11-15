<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Superadmin yoki uning yordamchi hodimi (platform). */
class EnsurePlatform
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isPlatformUser()) {
            abort(403, 'Faqat platforma foydalanuvchisi kira oladi');
        }

        if (! $user->is_active) {
            abort(403, 'Hisobingiz faol emas.');
        }

        return $next($request);
    }
}
