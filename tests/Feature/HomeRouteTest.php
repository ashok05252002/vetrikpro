<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_front_door_is_the_login_page()
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_a_signed_in_visitor_goes_to_their_dashboard()
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }
}
