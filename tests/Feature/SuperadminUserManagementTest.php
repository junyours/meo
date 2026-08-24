<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperadminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_create_update_and_reset_user_password(): void
    {
        $superadmin = User::factory()->create([
            'role' => 'superadmin',
        ]);

        $staff = User::factory()->create([
            'name' => 'John Staff',
            'email' => 'john.staff@meo.gov.ph',
            'role' => 'staff',
        ]);

        // 1. Superadmin resets staff password directly
        $resetResponse = $this->actingAs($superadmin)->postJson("/superadmin/users/{$staff->id}/reset-password", [
            'password' => 'NewSecretPassword123!',
            'auto_generate' => false,
        ]);

        $resetResponse->assertStatus(200);
        $resetResponse->assertJsonFragment([
            'temporary_password' => 'NewSecretPassword123!',
        ]);

        $staff->refresh();
        $this->assertTrue(Hash::check('NewSecretPassword123!', $staff->password));

        // 2. Superadmin updates staff details
        $updateResponse = $this->actingAs($superadmin)->putJson("/superadmin/users/{$staff->id}", [
            'name' => 'John Updated',
            'email' => 'john.updated@meo.gov.ph',
            'role' => 'admin',
            'email_verified' => true,
        ]);

        $updateResponse->assertStatus(200);
        $staff->refresh();
        $this->assertEquals('John Updated', $staff->name);
        $this->assertEquals('admin', $staff->role);
        $this->assertNotNull($staff->email_verified_at);

        // 3. Superadmin creates a new staff account
        $createResponse = $this->actingAs($superadmin)->postJson("/superadmin/users", [
            'name' => 'New Field Staff',
            'email' => 'new.staff@meo.gov.ph',
            'role' => 'staff',
            'email_verified' => true,
        ]);

        $createResponse->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'new.staff@meo.gov.ph',
            'role' => 'staff',
        ]);
    }
}
