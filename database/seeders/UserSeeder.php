<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer ou récupérer une entreprise par défaut
        $company = Company::firstOrCreate(
            ['name' => 'Advanced IT Solutions'],
            [
                'tp_type' => 1,
                'tp_name' => 'Advanced IT Solutions',
                'tp_TIN' => '4000000000',
                'tp_trade_number' => 'RC/BM/2020/0001',
                'tp_postal_number' => '0000',
                'tp_phone_number' => '+25779000000',
                'tp_address_province' => 'Bujumbura Mairie',
                'tp_address_commune' => 'Mukaza',
                'tp_address_quartier' => 'Rohero',
                'tp_address_avenue' => 'De la Victoire',
                'tp_address_rue' => 'Rue 1',
                'tp_address_number' => '1',
                'tp_fiscal_center' => 'DGC',
                'tp_activity_sector' => 'IT',
                'tp_legal_form' => 'SARL',
                'vat_taxpayer' => 1,
                'ct_taxpayer' => 0,
                'tl_taxpayer' => 0,
                'system_or_device_id' => 'ws000000000000',
                'default_currency' => 'FBU',
                'domain' => 'general',
            ]
        );

        // Créer l'utilisateur super admin
        $user = User::firstOrCreate(
            ['email' => 'nijeanlionel@gmail.com'],
            [
                'name' => 'Super Administrateur',
                'email' => 'nijeanlionel@gmail.com',
                'password' => Hash::make('Advanced2026'),
                'company_id' => $company->id,
            ]
        );

        // Récupérer tous les rôles et les assigner à l'utilisateur
        $roles = Role::all();
        foreach ($roles as $role) {
            $user->assignRole($role);
        }
    }
}
