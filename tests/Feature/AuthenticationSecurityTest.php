<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createCompanyAndAdmin(): User
    {
        $company = Company::query()->create([
            'name' => 'Test SA',
            'tp_type' => 1,
            'tp_name' => 'Test SA',
            'tp_TIN' => 'TIN-'.uniqid(),
            'vat_taxpayer' => 'NO',
            'ct_taxpayer' => 'NO',
            'tl_taxpayer' => 'NO',
        ]);

        return User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
        ]);
    }

    public function test_register_endpoint_requires_authentication(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response->assertUnauthorized();
    }

    public function test_authenticated_admin_can_create_user_in_own_company(): void
    {
        $admin = $this->createCompanyAndAdmin();
        $admin->assignRole(\App\Models\Role::firstOrCreate(['name' => 'admin']));
        $admin->load('roles');
        Sanctum::actingAs($admin);

        $email = 'newuser-'.uniqid().'@example.com';

        $response = $this->postJson('/api/register', [
            'name' => 'Nouveau',
            'email' => $email,
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'company_id' => $admin->company_id,
        ]);
    }

    public function test_users_index_is_scoped_to_authenticated_company(): void
    {
        $adminA = $this->createCompanyAndAdmin();
        $adminA->assignRole(\App\Models\Role::firstOrCreate(['name' => 'admin']));
        $adminA->load('roles');
        $adminB = $this->createCompanyAndAdmin();

        Sanctum::actingAs($adminA);

        $response = $this->getJson('/api/users');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data.data');
        $this->assertIsArray($data);
        foreach ($data as $row) {
            $this->assertSame($adminA->company_id, $row['company_id']);
        }

        $this->assertFalse(
            collect($data)->contains('id', $adminB->id)
        );
    }
}
