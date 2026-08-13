<?php

namespace Tests\Feature\Api;

use App\Models\FinancialCategory;
use App\Models\ManualCashEntry;
use App\Models\User;
use Database\Seeders\FinancialCategorySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinanceApiTest extends TestCase
{
    private User $admin;

    private string $token;

    private FinancialCategory $incomeCategory;

    private FinancialCategory $expenseCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(FinancialCategorySeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
        $this->token = $this->admin->createToken('test')->plainTextToken;
        $this->incomeCategory = FinancialCategory::income()->active()->firstOrFail();
        $this->expenseCategory = FinancialCategory::expense()->active()->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function api(string $method, string $uri, array $data = [])
    {
        return $this->withHeaders([
            'X-API-Key' => (string) config('api.key'),
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
        ])->json($method, $uri, $data);
    }

    #[Test]
    public function financial_report_returns_kpis(): void
    {
        $this->api('GET', '/api/financial-reports')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'sales',
                    'manual_income',
                    'manual_expense',
                    'purchases',
                    'totals',
                    'profit',
                    'payment_sources',
                ],
            ]);
    }

    #[Test]
    public function can_create_list_and_cancel_manual_income(): void
    {
        $this->api('POST', '/api/manual-incomes', [
            'category_id' => $this->incomeCategory->id,
            'amount' => 150000,
            'occurred_at' => now()->toDateTimeString(),
            'payment_method' => 'cash',
            'description' => 'Scrap besi',
        ])
            ->assertCreated()
            ->assertJsonPath('data.entry.type', 'income')
            ->assertJsonPath('data.entry.status', 'completed');

        $this->api('GET', '/api/manual-incomes')
            ->assertOk()
            ->assertJsonPath('data.summary.completed_count', 1);

        $entry = ManualCashEntry::query()->first();
        $this->api('DELETE', '/api/manual-incomes/'.$entry->id)
            ->assertOk()
            ->assertJsonPath('data.entry.status', 'cancelled');

        $this->api('DELETE', '/api/manual-incomes/'.$entry->id)
            ->assertStatus(422);
    }

    #[Test]
    public function expense_rejects_qris_and_wrong_category(): void
    {
        $this->api('POST', '/api/manual-expenses', [
            'category_id' => $this->incomeCategory->id,
            'amount' => 50000,
            'occurred_at' => now()->toDateTimeString(),
            'payment_method' => 'cash',
        ])->assertStatus(422);

        $this->api('POST', '/api/manual-expenses', [
            'category_id' => $this->expenseCategory->id,
            'amount' => 50000,
            'occurred_at' => now()->toDateTimeString(),
            'payment_method' => 'qris',
        ])->assertStatus(422);

        $this->api('POST', '/api/manual-expenses', [
            'category_id' => $this->expenseCategory->id,
            'amount' => 50000,
            'occurred_at' => now()->toDateTimeString(),
            'payment_method' => 'cash',
            'description' => 'Listrik',
        ])->assertCreated();
    }
}
