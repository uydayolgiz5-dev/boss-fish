<?php

namespace Tests\Feature;

use App\Models\ClientSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kompaniya to‘lovlari / subscription olib tashlanganini tekshiradi.
 */
class OwnerSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_company_does_not_create_subscription(): void
    {
        $sa = $this->makeSuperadmin();

        $this->actingAs($sa, 'sanctum')
            ->postJson('/api/superadmin/companies', $this->companyCreatePayload([
                'name' => 'No Pay Co',
                'admin_name' => 'Owner',
                'admin_login' => 'nopay',
                'admin_password' => 'pass1234',
                'admin_phone' => '+998909998877',
            ]))
            ->assertCreated();

        $this->assertSame(0, ClientSubscription::count());
    }
}
