<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyMessage;
use App\Models\Role;
use App\Models\User;
use App\Services\CompanyInspectionService;
use App\Support\CompanyPermissions;
use App\Support\PricingPlans;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SuperadminController extends Controller
{
    public function __construct(
        private readonly CompanyInspectionService $inspections,
    ) {
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->syncExpiredCompanies();

        $until = now()->addDays(PricingPlans::EXPIRING_SOON_DAYS);
        $expiring = Company::query()
            ->with(['admin:id,name,login,phone,company_id,type'])
            ->where('is_active', true)
            ->whereNotNull('access_expires_at')
            ->where('access_expires_at', '>', now())
            ->where('access_expires_at', '<=', $until)
            ->orderBy('access_expires_at')
            ->get()
            ->map(fn (Company $c) => $this->companyPayload($c));

        return response()->json([
            'companies_count' => Company::count(),
            'active_companies' => Company::where('is_active', true)->count(),
            'staff_count' => User::where('type', User::TYPE_SUPERADMIN_STAFF)->count(),
            'employees_count' => User::where('type', User::TYPE_EMPLOYEE)->count(),
            'expiring_soon_days' => PricingPlans::EXPIRING_SOON_DAYS,
            'expiring_companies' => $expiring,
            'expiring_count' => $expiring->count(),
            'pricing_plans' => PricingPlans::all(),
        ]);
    }

    public function pricingPlans(): JsonResponse
    {
        return response()->json([
            'plans' => PricingPlans::all(),
            'periods' => PricingPlans::PERIODS,
            'expiring_soon_days' => PricingPlans::EXPIRING_SOON_DAYS,
        ]);
    }

    public function settings(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'login' => $user->login,
            'email' => $user->email,
            'password_plain' => $user->password_plain,
            'company_id' => 0,
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'login' => [
                'sometimes',
                'string',
                'max:191',
                Rule::unique('users', 'login')->where(fn ($q) => $q->whereNull('company_id'))->ignore($user->id),
            ],
            'email' => ['nullable', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:4|max:64',
        ]);

        $payload = collect($data)->only(['name', 'email'])->all();
        if (isset($data['login'])) {
            $payload['login'] = mb_strtolower(trim($data['login']));
        }
        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
            $payload['password_plain'] = $data['password'];
        }

        $user->fill($payload)->save();
        $user = $user->fresh();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'login' => $user->login,
            'email' => $user->email,
            'password_plain' => $user->password_plain,
            'company_id' => 0,
        ]);
    }

    public function platformAdmins(): JsonResponse
    {
        $admins = User::query()
            ->where('type', User::TYPE_PLATFORM_ADMIN)
            ->whereNull('company_id')
            ->latest('id')
            ->get(['id', 'name', 'login', 'email', 'phone', 'is_active', 'created_at', 'password_plain']);

        return response()->json($admins->map(function (User $u) {
            $row = $u->toArray();
            $row['password_plain'] = $u->password_plain;
            $row['role'] = 'admin';

            return $row;
        }));
    }

    public function storePlatformAdmin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'login' => 'required|string|max:191',
            'password' => 'required|string|min:4|max:100',
            'email' => 'nullable|email|max:191',
            'phone' => 'nullable|string|max:32',
            'is_active' => 'sometimes|boolean',
        ]);

        $login = mb_strtolower(trim($data['login']));
        if (User::query()->whereNull('company_id')->where('login', $login)->exists()) {
            throw ValidationException::withMessages(['login' => ['Bu login band.']]);
        }

        $user = User::create([
            'company_id' => null,
            'type' => User::TYPE_PLATFORM_ADMIN,
            'name' => $data['name'],
            'login' => $login,
            'email' => isset($data['email']) ? mb_strtolower(trim($data['email'])) : null,
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'password_plain' => $data['password'],
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        $payload = $user->toArray();
        $payload['password_plain'] = $user->password_plain;
        $payload['role'] = 'admin';

        return response()->json($payload, 201);
    }

    public function updatePlatformAdmin(Request $request, User $user): JsonResponse
    {
        if ($user->type !== User::TYPE_PLATFORM_ADMIN || $user->company_id !== null) {
            abort(404);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'login' => [
                'sometimes',
                'string',
                'max:191',
                Rule::unique('users', 'login')->where(fn ($q) => $q->whereNull('company_id'))->ignore($user->id),
            ],
            'password' => 'nullable|string|min:4|max:100',
            'email' => 'nullable|email|max:191',
            'phone' => 'nullable|string|max:32',
            'is_active' => 'sometimes|boolean',
        ]);

        $payload = collect($data)->only(['name', 'phone', 'is_active'])->all();
        if (isset($data['login'])) {
            $payload['login'] = mb_strtolower(trim($data['login']));
        }
        if (array_key_exists('email', $data)) {
            $payload['email'] = $data['email'] ? mb_strtolower(trim($data['email'])) : null;
        }
        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
            $payload['password_plain'] = $data['password'];
        }

        $user->fill($payload)->save();
        if (array_key_exists('is_active', $payload) && ! $user->is_active) {
            $user->tokens()->delete();
        }

        $fresh = $user->fresh();
        $row = $fresh->toArray();
        $row['password_plain'] = $fresh->password_plain;
        $row['role'] = 'admin';

        return response()->json($row);
    }

    public function destroyPlatformAdmin(User $user): JsonResponse
    {
        if ($user->type !== User::TYPE_PLATFORM_ADMIN || $user->company_id !== null) {
            abort(404);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'O‘chirildi']);
    }

    public function companies(): JsonResponse
    {
        $this->syncExpiredCompanies();

        $companies = Company::query()
            ->with(['admin:id,name,login,email,phone,company_id,type,is_active'])
            ->withCount(['users', 'farms', 'ponds'])
            ->latest('id')
            ->get()
            ->map(fn (Company $c) => $this->companyPayload($c));

        return response()->json($companies);
    }

    public function storeCompany(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'admin_name' => 'required|string|max:255',
            'admin_login' => 'required|string|max:191',
            'admin_password' => 'required|string|min:4|max:100',
            'admin_phone' => 'required|string|max:32',
            'admin_email' => 'nullable|email|max:191',
            'plan' => ['required', 'string', Rule::in(PricingPlans::PLANS)],
            'billing_period' => ['required', 'string', Rule::in(PricingPlans::PERIODS)],
            'check_interval_days' => [
                'nullable',
                'integer',
                'min:'.PricingPlans::MIN_CHECK_INTERVAL,
                'max:'.PricingPlans::MAX_CHECK_INTERVAL,
            ],
        ]);

        $login = mb_strtolower(trim($data['admin_login']));
        $checkInterval = PricingPlans::normalizeCheckInterval(
            $data['check_interval_days'] ?? PricingPlans::DEFAULT_CHECK_INTERVAL
        );

        $company = DB::transaction(function () use ($data, $login, $request, $checkInterval) {
            $company = new Company(['name' => $data['name']]);
            $company->applyPlan($data['plan'], $data['billing_period'], true, $checkInterval);
            $company->save();

            if (User::query()->where('company_id', $company->id)->where('login', $login)->exists()) {
                throw ValidationException::withMessages([
                    'admin_login' => ['Bu login band.'],
                ]);
            }

            $admin = User::create([
                'company_id' => $company->id,
                'type' => User::TYPE_COMPANY_ADMIN,
                'name' => $data['admin_name'],
                'login' => $login,
                'email' => isset($data['admin_email']) ? mb_strtolower(trim($data['admin_email'])) : null,
                'phone' => $data['admin_phone'],
                'password' => $data['admin_password'],
                'password_plain' => $data['admin_password'],
                'is_active' => true,
                'access_expires_at' => $company->access_expires_at,
                'created_by' => $request->user()->id,
            ]);

            Role::create([
                'company_id' => $company->id,
                'name' => 'To‘liq ruxsat',
                'permissions' => CompanyPermissions::ALL,
                'is_active' => true,
            ]);

            \App\Support\FarmAccess::ensureForCompany($company, $admin, $request->user());

            return $company->fresh()->load(['admin'])->loadCount(['users', 'farms', 'ponds']);
        });

        return response()->json($this->companyPayload($company), 201);
    }

    public function updateCompany(Request $request, Company $company): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
            'quiet_mode' => 'sometimes|boolean',
            'admin_name' => 'sometimes|string|max:255',
            'admin_login' => 'sometimes|string|max:191',
            'admin_password' => 'nullable|string|min:4|max:100',
            'admin_phone' => 'nullable|string|max:32',
            'admin_email' => 'nullable|email|max:191',
            'admin_is_active' => 'sometimes|boolean',
            'plan' => ['sometimes', 'string', Rule::in(PricingPlans::PLANS)],
            'billing_period' => ['sometimes', 'string', Rule::in(PricingPlans::PERIODS)],
            'renew_plan' => 'sometimes|boolean',
            'next_check_at' => 'nullable|date',
            'check_interval_days' => [
                'nullable',
                'integer',
                'min:'.PricingPlans::MIN_CHECK_INTERVAL,
                'max:'.PricingPlans::MAX_CHECK_INTERVAL,
            ],
            'attention_note' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($data, $company) {
            if (isset($data['name'])) {
                $company->name = $data['name'];
            }

            $intervalProvided = array_key_exists('check_interval_days', $data) && $data['check_interval_days'] !== null;
            if ($intervalProvided) {
                $company->check_interval_days = PricingPlans::normalizeCheckInterval($data['check_interval_days']);
            }

            // Faqat aniq «muddat qo‘shish» — aks holda har saqlashda muddat o‘zgarib ketmasin
            $renew = ! empty($data['renew_plan']);

            if ($renew) {
                $plan = $data['plan'] ?? $company->plan;
                $period = $data['billing_period'] ?? $company->billing_period;
                if (! $plan || ! $period) {
                    throw ValidationException::withMessages([
                        'plan' => ['Tarif va davrni tanlang.'],
                    ]);
                }
                $company->applyPlan(
                    $plan,
                    $period,
                    true,
                    $intervalProvided ? (int) $company->check_interval_days : null
                );
            } else {
                if (isset($data['plan']) && PricingPlans::isValidPlan($data['plan'])) {
                    $company->plan = $data['plan'];
                    if ($company->billing_period) {
                        $company->plan_price = PricingPlans::priceFor(
                            $company->plan,
                            (string) $company->billing_period
                        );
                    }
                }
                if (isset($data['billing_period']) && PricingPlans::isValidPeriod($data['billing_period'])) {
                    $company->billing_period = $data['billing_period'];
                    if ($company->plan) {
                        $company->plan_price = PricingPlans::priceFor(
                            (string) $company->plan,
                            $company->billing_period
                        );
                    }
                }
            }

            if (! $renew && array_key_exists('is_active', $data)) {
                if ($data['is_active'] && $company->access_expired) {
                    throw ValidationException::withMessages([
                        'is_active' => ['Muddat tugagan. Avval tarifni yangilang.'],
                    ]);
                }
                $company->is_active = (bool) $data['is_active'];
            }

            if (array_key_exists('quiet_mode', $data)) {
                $company->setQuietMode((bool) $data['quiet_mode']);
            }

            if ($intervalProvided && ! $renew) {
                $company->syncCheckScheduleForPlan(resetSchedule: false);
                if (! $company->next_check_at) {
                    $company->next_check_at = now()->addDays((int) $company->check_interval_days)->toDateString();
                }
            }
            if (array_key_exists('next_check_at', $data)) {
                $company->next_check_at = $data['next_check_at'];
            }
            if (array_key_exists('attention_note', $data)) {
                $company->attention_note = $data['attention_note'];
            }

            $company->save();

            $admin = $company->admin;
            if ($admin) {
                $adminPayload = [];
                if (isset($data['admin_name'])) {
                    $adminPayload['name'] = $data['admin_name'];
                }
                if (isset($data['admin_login'])) {
                    $adminPayload['login'] = mb_strtolower(trim($data['admin_login']));
                }
                if (isset($data['admin_email'])) {
                    $adminPayload['email'] = $data['admin_email']
                        ? mb_strtolower(trim($data['admin_email']))
                        : null;
                }
                if (array_key_exists('admin_phone', $data)) {
                    $adminPayload['phone'] = $data['admin_phone'];
                }
                if (array_key_exists('admin_is_active', $data)) {
                    $adminPayload['is_active'] = $data['admin_is_active'];
                }
                if (! empty($data['admin_password'])) {
                    $adminPayload['password'] = $data['admin_password'];
                    $adminPayload['password_plain'] = $data['admin_password'];
                }
                if ($renew) {
                    $adminPayload['access_expires_at'] = $company->access_expires_at;
                }
                if ($adminPayload) {
                    $admin->fill($adminPayload)->save();
                }
            }
        });

        return response()->json(
            $this->companyPayload($company->fresh()->load(['admin'])->loadCount(['users', 'farms', 'ponds']))
        );
    }

    public function companyInspection(Request $request, Company $company): JsonResponse
    {
        if (! $company->requiresChecks()) {
            return response()->json(['message' => 'Bu tarifda tekshiruv yo‘q.'], 422);
        }
        if ($company->isQuiet()) {
            return response()->json([
                'message' => 'Tinch rejim yoqilgan — hozir tekshiruv kerak emas.',
            ], 422);
        }

        $data = $request->validate([
            'pond_id' => 'nullable|integer',
        ]);

        $pondId = isset($data['pond_id']) ? (int) $data['pond_id'] : null;

        return response()->json($this->inspections->inspect($company, $pondId));
    }

    public function markCompanyChecked(Request $request, Company $company): JsonResponse
    {
        if (! $company->requiresChecks()) {
            return response()->json(['message' => 'Bu tarifda tekshiruv yo‘q.'], 422);
        }
        if ($company->isQuiet()) {
            return response()->json([
                'message' => 'Tinch rejim yoqilgan — tekshiruv yakunlanmaydi.',
            ], 422);
        }

        $data = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $company->markChecked($data['note'] ?? null);

        return response()->json(
            $this->companyPayload($company->fresh()->load(['admin'])->loadCount(['users', 'farms', 'ponds']))
        );
    }


    public function staff(Request $request): JsonResponse
    {
        $staff = User::query()
            ->where('type', User::TYPE_SUPERADMIN_STAFF)
            ->whereNull('company_id')
            ->latest('id')
            ->get(['id', 'name', 'login', 'email', 'phone', 'is_active', 'created_at', 'password_plain']);

        return response()->json($staff->map(function (User $u) {
            $row = $u->toArray();
            $row['password_plain'] = $u->password_plain;

            return $row;
        }));
    }

    public function storeStaff(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'login' => 'required|string|max:191',
            'password' => 'required|string|min:4|max:100',
            'email' => 'nullable|email|max:191',
            'phone' => 'nullable|string|max:32',
            'is_active' => 'sometimes|boolean',
        ]);

        $login = mb_strtolower(trim($data['login']));
        if (User::query()->whereNull('company_id')->where('login', $login)->exists()) {
            throw ValidationException::withMessages(['login' => ['Bu login band.']]);
        }

        $user = User::create([
            'company_id' => null,
            'type' => User::TYPE_SUPERADMIN_STAFF,
            'name' => $data['name'],
            'login' => $login,
            'email' => isset($data['email']) ? mb_strtolower(trim($data['email'])) : null,
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'password_plain' => $data['password'],
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($user, 201);
    }

    public function updateStaff(Request $request, User $staff): JsonResponse
    {
        if ($staff->type !== User::TYPE_SUPERADMIN_STAFF || $staff->company_id !== null) {
            abort(404);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'login' => ['sometimes', 'string', 'max:191', Rule::unique('users', 'login')->where(fn ($q) => $q->whereNull('company_id'))->ignore($staff->id)],
            'password' => 'nullable|string|min:4|max:100',
            'email' => 'nullable|email|max:191',
            'phone' => 'nullable|string|max:32',
            'is_active' => 'sometimes|boolean',
        ]);

        $payload = collect($data)->only(['name', 'phone', 'is_active'])->all();
        if (isset($data['login'])) {
            $payload['login'] = mb_strtolower(trim($data['login']));
        }
        if (array_key_exists('email', $data)) {
            $payload['email'] = $data['email'] ? mb_strtolower(trim($data['email'])) : null;
        }
        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
            $payload['password_plain'] = $data['password'];
        }

        $staff->fill($payload)->save();

        return response()->json($staff->fresh());
    }

    public function destroyStaff(User $staff): JsonResponse
    {
        if ($staff->type !== User::TYPE_SUPERADMIN_STAFF || $staff->company_id !== null) {
            abort(404);
        }

        $staff->tokens()->delete();
        $staff->delete();

        return response()->json(['message' => 'O‘chirildi']);
    }

    public function companyChat(Company $company): JsonResponse
    {
        CompanyMessage::markSideRead($company->id, CompanyMessage::SIDE_CLIENT);

        $messages = CompanyMessage::forCompany($company->id)->map(function (CompanyMessage $row) {
            $payload = $row->toChatArray();
            $payload['mine'] = $row->isBoss();

            return $payload;
        })->values();

        $unreadFromClient = CompanyMessage::query()
            ->where('company_id', $company->id)
            ->where('sender_side', CompanyMessage::SIDE_CLIENT)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
            ],
            'messages' => $messages,
            'unread_from_client' => $unreadFromClient,
        ]);
    }

    public function sendCompanyChatMessage(Request $request, Company $company): JsonResponse
    {
        $data = $request->validate([
            'body' => 'nullable|string|max:2000',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        $body = trim((string) ($data['body'] ?? ''));
        $image = $request->file('image');
        if ($body === '' && ! $image) {
            abort(422, 'Matn yoki rasm yuboring');
        }

        $imagePath = null;
        if ($image) {
            $imagePath = $image->store('company-chat/'.$company->id, 'public');
        }

        $message = CompanyMessage::create([
            'company_id' => $company->id,
            'sender_id' => $request->user()?->id,
            'sender_side' => CompanyMessage::SIDE_BOSS,
            'body' => $body !== '' ? $body : null,
            'image_path' => $imagePath,
        ]);

        $payload = $message->fresh('sender:id,name')->toChatArray();
        $payload['mine'] = true;

        return response()->json([
            'message' => 'Yuborildi',
            'item' => $payload,
        ], 201);
    }

    private function syncExpiredCompanies(): void
    {
        Company::query()
            ->where('is_active', true)
            ->whereNotNull('access_expires_at')
            ->where('access_expires_at', '<=', now())
            ->each(fn (Company $c) => $c->deactivateIfExpired());
    }

    private function companyPayload(Company $company): array
    {
        $admin = $company->admin;
        $daysLeft = $company->access_days_left;

        return [
            'id' => $company->id,
            'name' => $company->name,
            'is_active' => (bool) $company->is_active,
            'quiet_mode' => (bool) $company->quiet_mode,
            'plan' => $company->plan,
            'billing_period' => $company->billing_period,
            'plan_price' => $company->plan_price,
            'plan_starts_at' => optional($company->plan_starts_at)?->toIso8601String(),
            'access_expires_at' => optional($company->access_expires_at)?->toIso8601String(),
            'access_expired' => $company->access_expired,
            'access_days_left' => $daysLeft,
            'expiring_soon' => $company->expiring_soon,
            'requires_checks' => $company->requiresChecks(),
            'next_check_at' => optional($company->next_check_at)?->toDateString(),
            'last_checked_at' => optional($company->last_checked_at)?->toIso8601String(),
            'check_interval_days' => (int) ($company->check_interval_days ?? 0),
            'needs_check' => $company->needsCheck(),
            'check_approaching' => $company->checkApproaching(),
            'attention_note' => $company->attention_note,
            'chat_unread' => CompanyMessage::query()
                ->where('company_id', $company->id)
                ->where('sender_side', CompanyMessage::SIDE_CLIENT)
                ->whereNull('read_at')
                ->count(),
            'users_count' => $company->users_count ?? $company->users()->count(),
            'farms_count' => $company->farms_count ?? null,
            'ponds_count' => $company->ponds_count ?? null,
            'admin' => $admin ? [
                'id' => $admin->id,
                'name' => $admin->name,
                'login' => $admin->login,
                'email' => $admin->email,
                'phone' => $admin->phone,
                'is_active' => $admin->is_active,
                'password_plain' => $admin->password_plain,
            ] : null,
            'login_hint' => [
                'company_id' => $company->id,
                'login' => $admin?->login,
            ],
            'created_at' => optional($company->created_at)->toIso8601String(),
        ];
    }
}
