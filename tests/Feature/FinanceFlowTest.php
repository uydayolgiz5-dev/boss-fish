<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Farm;
use App\Models\FishOperation;
use App\Models\Pond;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceFlowTest extends TestCase
{
    use RefreshDatabase;

    private function directorWithFarm(): array
    {
        [$director, $farm, $pond] = $this->createDirectorWithFarmAndPond([
            'farm' => ['name' => '7 Gektar', 'region' => 'Sirdaryo'],
            'pond' => ['name' => 'Havza 1', 'area_m2' => 70000],
            'director' => [
                'phone' => '+998901114455',
                'login' => 'dir_finflow_'.uniqid(),
                'access_expires_at' => now()->addDays(300),
            ],
        ]);

        return [$director, $farm, $pond];
    }

    public function test_director_can_manage_finance_entries_and_see_overview(): void
    {
        [$director, $farm, $pond] = $this->directorWithFarm();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                'category' => 'main',
                'title' => 'Kol ijarasi',
                'expense_date' => '2026-06-22',
                'quantity' => 1,
                'unit_price' => 174000000,
            ])
            ->assertCreated()
            ->assertJsonPath('amount', '174000000.00');

        $this->warehouseIntake($director, $farm, [
            'category' => 'medicine',
            'name' => 'AquaJoy',
            'quantity' => 24,
            'unit_price' => 170000,
        ]);

        FishOperation::create([
            'pond_id' => $pond->id,
            'user_id' => $director->id,
            'type' => 'sale',
            'fish_type' => 'sazan',
            'quantity' => 1000,
            'average_weight_kg' => 0.2,
            'total_weight_kg' => 200,
            'amount' => 5000000,
            'title' => 'Sotuv',
            'operated_at' => '2026-08-01',
        ]);

        $overview = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview")
            ->assertOk()
            ->json();

        $this->assertSame(178080000.0, (float) $overview['total_expenses']);
        $this->assertSame(5000000.0, (float) $overview['total_sales']);
        $this->assertSame(-173080000.0, (float) $overview['profit']);

        $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/expenses?category=main")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $expense = Expense::first();
        $this->actingAs($director, 'sanctum')
            ->deleteJson("/api/director/farms/{$farm->id}/finance/expenses/{$expense->id}")
            ->assertOk();

        $this->assertSame(1, Expense::count());
    }
}
