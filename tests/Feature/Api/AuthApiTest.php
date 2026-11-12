<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_regular_user_and_returns_a_bearer_token(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'New User')
            ->assertJsonPath('data.is_admin', false)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonMissingPath('data.password');

        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'is_admin' => false,
        ]);
        $this->assertTrue(Hash::check('password-123', User::query()->firstOrFail()->password));
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => User::query()->firstOrFail()->id]);
    }

    public function test_login_returns_401_for_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'person@example.com']);

        $this->postJson('/api/v1/login', [
            'email' => 'person@example.com',
            'password' => 'incorrect-password',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'The provided credentials are incorrect.');
    }

    public function test_login_returns_a_bearer_token_that_expires_after_one_week(): void
    {
        $user = User::factory()->create(['email' => 'person@example.com']);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'person@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('token_type', 'Bearer');

        $token = PersonalAccessToken::query()->where('tokenable_id', $user->id)->firstOrFail();

        $this->assertSame('api-client', $token->name);
        $this->assertTrue($token->expires_at->isFuture());
    }

    public function test_logout_revokes_the_current_bearer_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password-123',
            'password_confirmation' => 'different-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');

        $this->assertDatabaseCount('users', 0);
    }
}
