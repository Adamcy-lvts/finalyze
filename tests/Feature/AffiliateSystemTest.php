<?php

namespace Tests\Feature;

use App\Models\AffiliateInvite;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AffiliateSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'affiliate', 'guard_name' => 'web']);
        Role::create(['name' => 'admin', 'guard_name' => 'web']);

        SystemSetting::create([
            'key' => 'affiliate.enabled',
            'value' => ['enabled' => true],
            'type' => 'boolean',
            'group' => 'affiliate',
        ]);

        SystemSetting::create([
            'key' => 'affiliate.registration_open',
            'value' => ['enabled' => false],
            'type' => 'boolean',
            'group' => 'affiliate',
        ]);
    }

    public function test_affiliate_registration_requires_invite_when_closed(): void
    {
        $response = $this->post('/affiliate/register', [
            'name' => 'Affiliate User',
            'email' => 'affiliate@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('affiliate.register', absolute: false));
        $this->assertGuest();
    }

    public function test_affiliate_registration_with_invite_succeeds(): void
    {
        $admin = User::factory()->create();

        $invite = AffiliateInvite::create([
            'code' => 'TESTINVITE',
            'created_by' => $admin->id,
            'type' => 'single_use',
            'max_uses' => 1,
            'uses' => 0,
            'is_active' => true,
        ]);

        $response = $this->withSession(['affiliate_invite_id' => $invite->id])->post('/affiliate/register', [
            'name' => 'Affiliate User',
            'email' => 'affiliate2@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('affiliate.dashboard', absolute: false));
        $this->assertAuthenticated();

        $user = User::where('email', 'affiliate2@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->isAffiliate());
        $this->assertTrue($user->affiliate_is_pure);
    }

    public function test_regular_user_can_request_affiliate_access(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/affiliate/request');

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertSame('pending', $user->fresh()->affiliate_status);
    }

    public function test_admin_can_approve_affiliate_request(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create([
            'affiliate_status' => 'pending',
            'affiliate_requested_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post('/admin/affiliates/requests/'.$user->id.'/approve');

        $response->assertStatus(200);
        $this->assertTrue($user->fresh()->isAffiliate());
        $this->assertSame('approved', $user->fresh()->affiliate_status);
    }
}
