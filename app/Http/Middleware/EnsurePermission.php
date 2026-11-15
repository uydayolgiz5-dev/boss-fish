<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Bo‘lim ruxsati. Bir nechta kalit: permission:finance|finance_reports (OR).
     */
    public function handle(Request $request, Closure $next, string ...$sections): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Bu bo‘limga ruxsat yo‘q.');
        }

        $allowed = [];
        foreach ($sections as $section) {
            foreach (explode('|', $section) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $allowed[] = $part;
                }
            }
        }

        foreach ($allowed as $section) {
            if ($user->canAccess($section)) {
                return $next($request);
            }
        }

        abort(403, 'Bu bo‘limga ruxsat yo‘q.');
    }
}
