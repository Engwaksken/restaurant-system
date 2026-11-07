<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_in_user_visiting_admin_login_is_sent_to_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/login')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_guest_visiting_admin_login_sees_the_sign_in_form(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Staff sign in');
    }
}
