<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\FinanceSection;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceCustomSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_can_create_custom_section_and_add_expense(): void
    {
        [$director, $farm] = $this->createDirectorWithFarmAndPond();

        $section = $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/sections", [
                'name' => 'Elektr energiya',
            ])
            ->assertCreated()
            ->json();

        $category = $section['category'];

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                'category' => $category,
                'title' => 'Avgust oyi',
                'expense_date' => '2026-08-01',
                'amount' => 1500000,
            ])
            ->assertCreated();

        $overview = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview")
            ->assertOk()
            ->json();

        $this->assertSame(1500000.0, (float) $overview['custom_sections'][0]['total']);
    }

    public function test_custom_section_expense_does_not_sync_to_warehouse(): void
    {
        [$director, $farm] = $this->createDirectorWithFarmAndPond();

        $section = $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/sections", [
                'name' => 'Transport',
            ])
            ->assertCreated()
            ->json();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                'category' => $section['category'],
                'title' => 'Yoqilg‘i',
                'expense_date' => '2026-08-01',
                'quantity' => 100,
                'unit_price' => 1000,
            ])
            ->assertCreated();

        $this->assertSame(0, WarehouseItem::count());
    }

    public function test_cannot_delete_custom_section_with_expenses(): void
    {
        [$director, $farm] = $this->createDirectorWithFarmAndPond();

        $section = FinanceSection::create([
            'farm_id' => $farm->id,
            'name' => 'Ijara',
            'created_by' => $director->id,
        ]);

        Expense::create([
            'farm_id' => $farm->id,
            'user_id' => $director->id,
            'category' => $section->categoryKey(),
            'title' => 'Oy',
            'expense_date' => '2026-08-01',
            'amount' => 500000,
        ]);

        $this->actingAs($director, 'sanctum')
            ->deleteJson("/api/director/farms/{$farm->id}/finance/sections/{$section->id}")
            ->assertStatus(422);
    }
}
