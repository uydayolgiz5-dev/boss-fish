<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Support\PricingPlans;
use Illuminate\Console\Command;

class SyncCompanySubscriptions extends Command
{
    protected $signature = 'companies:sync-subscriptions';

    protected $description = 'Muddati tugagan kompaniyalarni passiv qiladi; 10 kun ichida tugaydiganlarni hisoblaydi';

    public function handle(): int
    {
        $deactivated = 0;
        Company::query()
            ->where('is_active', true)
            ->whereNotNull('access_expires_at')
            ->where('access_expires_at', '<=', now())
            ->each(function (Company $company) use (&$deactivated) {
                if ($company->deactivateIfExpired()) {
                    $deactivated++;
                    $this->line("Passiv: #{$company->id} {$company->name}");
                }
            });

        $until = now()->addDays(PricingPlans::EXPIRING_SOON_DAYS);
        $expiring = Company::query()
            ->where('is_active', true)
            ->whereNotNull('access_expires_at')
            ->where('access_expires_at', '>', now())
            ->where('access_expires_at', '<=', $until)
            ->count();

        $this->info("Passiv qilindi: {$deactivated}. Ogohlantirish (≤10 kun): {$expiring}");

        return self::SUCCESS;
    }
}
