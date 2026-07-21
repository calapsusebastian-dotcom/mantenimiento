<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guests hitting the root URL are sent straight to the login screen.
     */
    public function test_the_root_url_redirects_guests_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    /**
     * Authenticated users hitting the root URL land on their dashboard.
     */
    public function test_the_root_url_redirects_authenticated_users_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/')->assertRedirect(route('dashboard'));
    }
}
