<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Faqat asosiy BOSS (superadmin) — platform admin kira olmaydi. */
class EnsureRootSuperadmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isSuperadmin()) {
            abort(403, 'Faqat asosiy BOSS kira oladi');
        }

        if (! $user->is_active) {
            abort(403, 'Hisobingiz faol emas.');
        }

        return $next($request);
    }
}
