<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_management_returns_401_without_authentication(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }

    public function test_user_management_returns_403_for_non_admin_users(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_admin_can_create_read_update_and_delete_users_without_granting_admin_from_input(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/v1/users', [
            'name' => 'Managed User',
            'email' => 'managed@example.com',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
            'is_admin' => true,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Managed User')
            ->assertJsonPath('data.is_admin', false)
            ->assertJsonMissingPath('data.password');

        $userId = $created->json('data.id');

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/v1/users/{$userId}")
            ->assertOk()
            ->assertJsonPath('data.email', 'managed@example.com');

        $this->putJson("/api/v1/users/{$userId}", [
            'name' => 'Updated User',
            'is_admin' => true,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated User')
            ->assertJsonPath('data.is_admin', false);

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'name' => 'Updated User',
            'is_admin' => false,
        ]);

        $this->deleteJson("/api/v1/users/{$userId}")->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_admin_promotion_command_grants_admin_access_by_email(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);

        $this->artisan('users:make-admin', ['email' => $user->email])
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_admin' => true,
        ]);
    }
}
