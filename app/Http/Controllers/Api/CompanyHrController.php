<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Support\CompanyContext;
use App\Support\CompanyPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompanyHrController extends Controller
{
    public function permissionKeys(): JsonResponse
    {
        return response()->json([
            'sections' => CompanyPermissions::ALL,
            'section_meta' => CompanyPermissions::sectionMeta(),
            'job_roles' => array_column(CompanyPermissions::jobRoles(), 'key'),
            'default_roles' => CompanyPermissions::defaultRoles(),
            'admin_only' => CompanyPermissions::ADMIN_ONLY,
        ]);
    }

    public function roles(Request $request): JsonResponse
    {
        $this->assertAdmin($request);
        $companyId = CompanyContext::requireId($request->user());
        $this->ensureDefaultRoles($companyId);

        $roles = Role::query()
            ->where('company_id', $companyId)
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return response()->json($roles);
    }

    public function ensureDefaults(Request $request): JsonResponse
    {
        $this->assertAdmin($request);
        $companyId = CompanyContext::requireId($request->user());
        $created = $this->ensureDefaultRoles($companyId);

        $roles = Role::query()
            ->where('company_id', $companyId)
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return response()->json([
            'created' => $created,
            'roles' => $roles,
        ]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        $this->assertAdmin($request);
        $companyId = CompanyContext::requireId($request->user());

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('roles', 'name')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'permissions' => 'required|array',
            'permissions.*' => Rule::in(CompanyPermissions::ALL),
            'is_active' => 'sometimes|boolean',
        ]);

        $role = Role::create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'permissions' => $data['permissions'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json($role, 201);
    }

    public function updateRole(Request $request, Role $role): JsonResponse
    {
        $this->assertAdmin($request);
        CompanyContext::assertSameCompany($role, $request->user());

        $companyId = CompanyContext::requireId($request->user());
        $data = $request->validate([
            'name' => [
                'sometimes', 'string', 'max:120',
                Rule::unique('roles', 'name')->where(fn ($q) => $q->where('company_id', $companyId))->ignore($role->id),
            ],
            'permissions' => 'sometimes|array',
            'permissions.*' => Rule::in(CompanyPermissions::ALL),
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($data['permissions'])) {
            $data['permissions'] = CompanyPermissions::normalize($data['permissions']);
        }

        $role->fill($data)->save();

        return response()->json($role->fresh());
    }

    public function destroyRole(Request $request, Role $role): JsonResponse
    {
        $this->assertAdmin($request);
        CompanyContext::assertSameCompany($role, $request->user());

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => ['Bu rolga biriktirilgan hodimlar bor. Avval hodimlarni o‘zgartiring.'],
            ]);
        }

        $role->delete();

        return response()->json(['message' => 'O‘chirildi']);
    }

    public function employees(Request $request): JsonResponse
    {
        $this->assertAdmin($request);

        $employees = User::query()
            ->where('company_id', CompanyContext::requireId($request->user()))
            ->where('type', User::TYPE_EMPLOYEE)
            ->with('companyRole:id,name,permissions,job_roles')
            ->latest('id')
            ->get();

        return response()->json($employees->map(fn (User $u) => $this->employeePayload($u, false)));
    }

    public function storeEmployee(Request $request): JsonResponse
    {
        $this->assertAdmin($request);
        $companyId = CompanyContext::requireId($request->user());

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'login' => [
                'required', 'string', 'max:191',
                Rule::unique('users', 'login')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'password' => 'required|string|min:4|max:100',
            'phone' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:191',
            'roles' => 'required|array|min:1',
            'roles.*' => Rule::in(CompanyPermissions::jobRoleKeys()),
            'is_active' => 'sometimes|boolean',
        ], [
            'login.unique' => 'Bu login kompaniyada band. Boshqa login yozing (direktor loginidan farq qilsin).',
            'login.required' => 'Login majburiy.',
            'name.required' => 'Ism majburiy.',
            'password.required' => 'Parol majburiy.',
            'password.min' => 'Parol kamida 4 belgidan iborat bo‘lsin.',
            'roles.required' => 'Kamida bitta ish rolini tanlang.',
            'roles.min' => 'Kamida bitta ish rolini tanlang.',
        ]);

        $roleId = $this->syncEmployeeJobRoles(
            $companyId,
            null,
            $data['roles'],
            $data['name']
        );

        $user = User::create([
            'company_id' => $companyId,
            'type' => User::TYPE_EMPLOYEE,
            'name' => $data['name'],
            'login' => mb_strtolower(trim($data['login'])),
            'phone' => $data['phone'] ?? null,
            'email' => isset($data['email']) ? mb_strtolower(trim($data['email'])) : null,
            'password' => $data['password'],
            'password_plain' => $data['password'],
            'role_id' => $roleId,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        $farm = \App\Support\FarmAccess::ensureForCompany($companyId, $request->user(), $request->user());
        $user->assignedFarms()->sync([$farm->id]);

        $this->logCompanyActivity(
            $companyId,
            $request->user()->id,
            'employee_created',
            'Hodim yaratildi',
            $request->user()->name.' «'.$user->name.'» hodimini yaratdi',
            ['employee_id' => $user->id, 'roles' => $data['roles']]
        );

        return response()->json($this->employeePayload($user->load('companyRole'), true), 201);
    }

    public function updateEmployee(Request $request, User $employee): JsonResponse
    {
        $this->assertAdmin($request);

        $companyId = CompanyContext::requireId($request->user());
        if ($employee->type !== User::TYPE_EMPLOYEE || (int) $employee->company_id !== $companyId) {
            abort(404);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'login' => [
                'sometimes', 'string', 'max:191',
                Rule::unique('users', 'login')->where(fn ($q) => $q->where('company_id', $companyId))->ignore($employee->id),
            ],
            'password' => 'nullable|string|min:4|max:100',
            'phone' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:191',
            'roles' => 'sometimes|array|min:1',
            'roles.*' => Rule::in(CompanyPermissions::jobRoleKeys()),
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

        if (array_key_exists('roles', $data)) {
            $payload['role_id'] = $this->syncEmployeeJobRoles(
                $companyId,
                $employee,
                $data['roles'],
                $data['name'] ?? $employee->name
            );
        }

        $employee->fill($payload)->save();

        if (! $employee->assignedFarms()->exists()) {
            $farm = \App\Support\FarmAccess::ensureForCompany($companyId, $request->user(), $request->user());
            $employee->assignedFarms()->sync([$farm->id]);
        }

        $includePassword = ! empty($data['password']);

        return response()->json($this->employeePayload($employee->fresh()->load('companyRole'), $includePassword));
    }

    public function destroyEmployee(Request $request, User $employee): JsonResponse
    {
        $this->assertAdmin($request);

        if ($employee->type !== User::TYPE_EMPLOYEE || (int) $employee->company_id !== CompanyContext::requireId($request->user())) {
            abort(404);
        }

        $companyId = (int) $employee->company_id;
        $name = $employee->name;
        $employeeId = $employee->id;

        $employee->tokens()->delete();
        $employee->delete();

        $this->logCompanyActivity(
            $companyId,
            $request->user()->id,
            'employee_deleted',
            'Hodim o‘chirildi',
            $request->user()->name.' «'.$name.'» hodimini o‘chirdi',
            ['employee_id' => $employeeId]
        );

        return response()->json(['message' => 'O‘chirildi']);
    }

    public function resetEmployeePassword(Request $request, User $employee): JsonResponse
    {
        $this->assertAdmin($request);

        if ($employee->type !== User::TYPE_EMPLOYEE || (int) $employee->company_id !== CompanyContext::requireId($request->user())) {
            abort(404);
        }

        $data = $request->validate([
            'password' => 'nullable|string|min:4|max:100',
        ]);

        $password = $data['password'] ?? Str::password(8, symbols: false);

        $employee->fill([
            'password' => $password,
            'password_plain' => $password,
        ])->save();
        $employee->tokens()->delete();

        $this->logCompanyActivity(
            (int) $employee->company_id,
            $request->user()->id,
            'employee_password_reset',
            'Hodim paroli yangilandi',
            $request->user()->name.' «'.$employee->name.'» parolini yangiladi',
            ['employee_id' => $employee->id]
        );

        return response()->json([
            'id' => $employee->id,
            'login' => $employee->login,
            'password_plain' => $password,
        ]);
    }

    private function assertAdmin(Request $request): void
    {
        if (! $request->user()?->isCompanyAdmin() && ! $request->user()?->isSuperadmin()) {
            abort(403, 'Faqat kompaniya admini');
        }
    }

    private function logCompanyActivity(
        int $companyId,
        int $userId,
        string $action,
        string $title,
        ?string $description = null,
        array $details = []
    ): void {
        $pondId = \App\Models\Pond::query()
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->value('id');

        if (! $pondId) {
            return;
        }

        ActivityLog::create([
            'company_id' => $companyId,
            'pond_id' => $pondId,
            'user_id' => $userId,
            'action' => $action,
            'title' => $title,
            'description' => $description,
            'details' => array_merge($details, ['company_event' => true]),
            'performed_at' => now(),
        ]);
    }

    /**
     * Hodim vazifa rollarini ichki Role yozuviga yozadi.
     *
     * @param  list<string>  $jobKeys
     */
    private function syncEmployeeJobRoles(int $companyId, ?User $employee, array $jobKeys, string $employeeName): int
    {
        $jobs = CompanyPermissions::normalizeJobs($jobKeys);
        $perms = CompanyPermissions::permissionsForJobs($jobs);
        if ($jobs === [] || $perms === []) {
            throw ValidationException::withMessages([
                'roles' => ['Kamida bitta rolni belgilang.'],
            ]);
        }

        $roleName = 'Hodim: '.(trim($employeeName) !== '' ? trim($employeeName) : 'yangi');

        if ($employee?->role_id) {
            $role = Role::query()
                ->where('company_id', $companyId)
                ->whereKey($employee->role_id)
                ->first();

            if ($role) {
                $shared = User::query()
                    ->where('role_id', $role->id)
                    ->when($employee->id, fn ($q) => $q->where('id', '!=', $employee->id))
                    ->exists();

                if (! $shared) {
                    $role->fill([
                        'name' => $roleName,
                        'permissions' => $perms,
                        'job_roles' => $jobs,
                        'is_active' => true,
                    ])->save();

                    return (int) $role->id;
                }
            }
        }

        $uniqueName = $roleName;
        $i = 2;
        while (Role::query()->where('company_id', $companyId)->where('name', $uniqueName)->exists()) {
            $uniqueName = $roleName.' ('.$i.')';
            $i++;
        }

        return (int) Role::create([
            'company_id' => $companyId,
            'name' => $uniqueName,
            'permissions' => $perms,
            'job_roles' => $jobs,
            'is_active' => true,
        ])->id;
    }

    /**
     * @return list<string> yaratilgan rol nomlari
     */
    private function ensureDefaultRoles(int $companyId): array
    {
        $created = [];
        foreach (CompanyPermissions::defaultRoles() as $template) {
            $exists = Role::query()
                ->where('company_id', $companyId)
                ->where('name', $template['name'])
                ->exists();
            if ($exists) {
                continue;
            }

            Role::create([
                'company_id' => $companyId,
                'name' => $template['name'],
                'permissions' => CompanyPermissions::normalize($template['permissions']),
                'job_roles' => CompanyPermissions::normalizeJobs($template['job_roles'] ?? []),
                'is_active' => true,
            ]);
            $created[] = $template['name'];
        }

        return $created;
    }

    private function employeePayload(User $user, bool $includePassword = false): array
    {
        $storedJobs = CompanyPermissions::normalizeJobs($user->companyRole?->job_roles ?? []);
        $roles = $storedJobs !== []
            ? $storedJobs
            : CompanyPermissions::inferJobsFromPermissions($user->permissions);

        $payload = [
            'id' => $user->id,
            'name' => $user->name,
            'login' => $user->login,
            'phone' => $user->phone,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'role_id' => $user->role_id,
            'role' => $user->companyRole ? [
                'id' => $user->companyRole->id,
                'name' => $user->companyRole->name,
                'permissions' => $user->companyRole->permissions,
                'job_roles' => $roles,
            ] : null,
            'roles' => $roles,
            'permissions' => $user->permissions,
            'company_id' => $user->company_id,
            'created_at' => optional($user->created_at)->toIso8601String(),
        ];

        if ($includePassword) {
            $payload['password_plain'] = $user->password_plain;
        }

        return $payload;
    }
}
