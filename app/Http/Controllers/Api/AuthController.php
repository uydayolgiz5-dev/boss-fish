<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\WorkerAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Bitta login: company_id + login + password.
     * company_id = 0 → platform (superadmin / platform_admin / superadmin_staff)
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|integer|min:0',
            'login' => 'required|string|max:191',
            'password' => 'required|string',
        ]);

        $login = mb_strtolower(trim($data['login']));
        $companyId = (int) $data['company_id'];

        if ($companyId === 0) {
            $user = User::query()
                ->whereNull('company_id')
                ->where(function ($q) use ($login) {
                    $q->where('login', $login)->orWhere('email', $login);
                })
                ->whereIn('type', [
                    User::TYPE_SUPERADMIN,
                    User::TYPE_PLATFORM_ADMIN,
                    User::TYPE_SUPERADMIN_STAFF,
                ])
                ->first();
        } else {
            $user = User::query()
                ->where('company_id', $companyId)
                ->where(function ($q) use ($login) {
                    $q->where('login', $login)
                        ->orWhere('email', $login)
                        ->orWhere('phone', $login);
                })
                ->whereIn('type', [User::TYPE_COMPANY_ADMIN, User::TYPE_EMPLOYEE])
                ->first();
        }

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['ID, login yoki parol noto‘g‘ri.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => ['Hisobingiz faol emas.'],
            ]);
        }

        if ($user->company_id) {
            $company = $user->company;
            if ($company) {
                $company->deactivateIfExpired();
                $company->refresh();
            }
            if (! $company || ! $company->is_active) {
                throw ValidationException::withMessages([
                    'login' => ['Kompaniya nofaol (mavsum yakunlangan yoki to‘lov). BOSS bilan bog‘laning.'],
                ]);
            }
        }

        if ($user->isEmployee() && ($block = WorkerAccess::loginBlockMessage($user))) {
            throw ValidationException::withMessages([
                'login' => [$block],
            ]);
        }

        $tokenPayload = $this->issueSpaToken($user, 'spa');

        return response()->json($tokenPayload);
    }

    /** @deprecated use login with company_id=0 */
    public function ownerLogin(Request $request): JsonResponse
    {
        $request->merge([
            'company_id' => 0,
            'login' => $request->input('login'),
        ]);

        return $this->login($request);
    }

    /** @deprecated use login with company_id=0 */
    public function vengerLogin(Request $request): JsonResponse
    {
        $request->merge([
            'company_id' => 0,
            'login' => $request->input('login'),
        ]);

        return $this->login($request);
    }

    /** @return array{user: array, token: string, expires_at: string} */
    private function issueSpaToken(User $user, string $name): array
    {
        $days = max(1, (int) config('auth.spa_remember_days', 30));
        $expiresAt = now()->addDays($days);
        $token = $user->createToken($name, ['*'], $expiresAt)->plainTextToken;

        return [
            'user' => $this->userPayload($user),
            'token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    private function userPayload(User $user): array
    {
        $user->loadMissing(['company', 'companyRole']);
        $payload = $user->toArray();
        $payload['type'] = $user->type;
        $payload['company_id'] = $user->company_id;
        $payload['permissions'] = $user->permissions;
        $payload['company'] = $user->company ? [
            'id' => $user->company->id,
            'name' => $user->company->name,
            'is_active' => (bool) $user->company->is_active,
        ] : null;

        if ($user->isEmployee()) {
            $payload['director_access_blocked'] = WorkerAccess::isBlockedByDirectorAccess($user);
        }

        return $payload;
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json(['message' => 'Chiqildi']);
    }
}
