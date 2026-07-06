<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_guests_to_login(): void
    {
        auth()->logout();

        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_root_redirects_authenticated_users_to_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/dashboard');
    }
}
