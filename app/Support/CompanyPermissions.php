<?php

namespace App\Support;

class CompanyPermissions
{
    /** Ichki bo‘lim kalitlari — auth.can / route meta uchun. */
    public const ALL = [
        'dashboard',
        'farms',
        'ponds',
        'daily_reports',
        'main_reports',
        'warehouse',
        'finance',
        'finance_reports',
        'fish_ops',
        'activities',
        'pond_close',
    ];

    /** Faqat kompaniya admini — hodim rollariga berilmaydi. */
    public const ADMIN_ONLY = [
        'employees',
        'roles',
    ];

    /** Eski rol kalitlari → yangi. */
    private const JOB_ALIASES = [
        'water_weigh' => 'water_test',
        'farm_manage' => 'full_manage',
    ];

    /**
     * Hodimga tayinlanadigan aniq vazifa rollari (UI ptichkalari).
     *
     * @return list<array{key: string, permissions: list<string>}>
     */
    public static function jobRoles(): array
    {
        return [
            [
                'key' => 'full_manage',
                'permissions' => self::ALL,
            ],
            [
                // Faqat yem/kunlik hisobot — bosh sahifa, arxiv, moliya yo‘q
                'key' => 'feed_report',
                'permissions' => ['ponds', 'daily_reports'],
            ],
            [
                'key' => 'fish_stock',
                'permissions' => ['ponds', 'fish_ops'],
            ],
            [
                'key' => 'water_test',
                'permissions' => ['ponds', 'main_reports'],
            ],
            [
                'key' => 'warehouse',
                'permissions' => ['warehouse'],
            ],
            [
                'key' => 'finance_expense',
                'permissions' => ['finance'],
            ],
            [
                // Ochiq/yopiq havzalar + baliqchilik va moliya hisobotlari
                'key' => 'reports_view',
                'permissions' => ['ponds', 'activities', 'finance_reports'],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function jobRoleKeys(): array
    {
        return array_column(self::jobRoles(), 'key');
    }

    /**
     * @param  list<string>|null  $jobKeys
     * @return list<string>
     */
    public static function normalizeJobs(?array $jobKeys): array
    {
        $allowed = array_flip(self::jobRoleKeys());
        $out = [];
        foreach ($jobKeys ?? [] as $key) {
            $key = (string) $key;
            $key = self::JOB_ALIASES[$key] ?? $key;
            if (isset($allowed[$key])) {
                $out[] = $key;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<string>  $jobKeys
     * @return list<string>
     */
    public static function permissionsForJobs(array $jobKeys): array
    {
        $jobs = self::normalizeJobs($jobKeys);
        if ($jobs === []) {
            return [];
        }

        if (in_array('full_manage', $jobs, true)) {
            return self::ALL;
        }

        $map = [];
        foreach (self::jobRoles() as $job) {
            $map[$job['key']] = $job['permissions'];
        }

        $out = [];
        foreach ($jobs as $key) {
            foreach ($map[$key] ?? [] as $perm) {
                $out[] = $perm;
            }
        }

        return self::normalize($out);
    }

    /**
     * @param  list<string>|null  $permissions
     * @return list<string>
     */
    public static function inferJobsFromPermissions(?array $permissions): array
    {
        $perms = self::normalize($permissions);
        if ($perms === []) {
            return [];
        }

        if (count(array_diff(self::ALL, $perms)) === 0) {
            return ['full_manage'];
        }

        $matched = [];
        foreach (self::jobRoles() as $job) {
            if ($job['key'] === 'full_manage') {
                continue;
            }
            if (count(array_diff($job['permissions'], $perms)) === 0) {
                $matched[] = $job['key'];
            }
        }

        return $matched;
    }

    /**
     * @return list<array{name: string, permissions: list<string>, job_roles?: list<string>}>
     */
    public static function defaultRoles(): array
    {
        $out = [];
        foreach (self::jobRoles() as $job) {
            $out[] = [
                'name' => $job['key'],
                'permissions' => self::normalize($job['permissions']),
                'job_roles' => [$job['key']],
            ];
        }

        return $out;
    }

    public static function normalize(?array $permissions): array
    {
        $allowed = array_flip(self::ALL);
        $out = [];
        foreach ($permissions ?? [] as $key) {
            $key = (string) $key;
            if (isset($allowed[$key])) {
                $out[] = $key;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return list<array{key: string, group: string}>
     */
    public static function sectionMeta(): array
    {
        return [
            ['key' => 'dashboard', 'group' => 'general'],
            ['key' => 'farms', 'group' => 'general'],
            ['key' => 'ponds', 'group' => 'fishery'],
            ['key' => 'daily_reports', 'group' => 'fishery'],
            ['key' => 'main_reports', 'group' => 'fishery'],
            ['key' => 'fish_ops', 'group' => 'fishery'],
            ['key' => 'activities', 'group' => 'fishery'],
            ['key' => 'pond_close', 'group' => 'fishery'],
            ['key' => 'warehouse', 'group' => 'ops'],
            ['key' => 'finance', 'group' => 'ops'],
            ['key' => 'finance_reports', 'group' => 'ops'],
        ];
    }
}
