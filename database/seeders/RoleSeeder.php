<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            [
                'name' => 'admin',
                'label' => 'Administrateur',
                'description' => 'Accès complet à toutes les fonctionnalités',
                'permissions' => json_encode([
                    'dashboard', 'sales', 'clients', 'stock', 'pharmaceutical',
                    'bakery', 'journal', 'reports', 'expenses', 'company', 'users'
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'manager',
                'label' => 'Manager',
                'description' => 'Gestion des opérations et supervision',
                'permissions' => json_encode([
                    'dashboard', 'sales', 'clients', 'stock', 'pharmaceutical',
                    'bakery', 'journal', 'reports', 'expenses'
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'cashier',
                'label' => 'Caissier',
                'description' => 'Gestion des ventes et transactions',
                'permissions' => json_encode(['dashboard', 'sales']),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'sales',
                'label' => 'Commercial',
                'description' => 'Gestion des clients et ventes',
                'permissions' => json_encode(['dashboard', 'clients', 'sales']),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'stock_manager',
                'label' => 'Gestionnaire de Stock',
                'description' => 'Gestion des inventaires',
                'permissions' => json_encode(['dashboard', 'stock']),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'pharmacist',
                'label' => 'Pharmacien',
                'description' => 'Gestion des produits pharmaceutiques',
                'permissions' => json_encode(['dashboard', 'pharmaceutical']),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'baker',
                'label' => 'Boulanger',
                'description' => 'Gestion de la production boulangère',
                'permissions' => json_encode(['dashboard', 'bakery']),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'accountant',
                'label' => 'Comptable',
                'description' => 'Gestion financière et comptabilité',
                'permissions' => json_encode(['dashboard', 'journal', 'expenses']),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'user',
                'label' => 'Utilisateur',
                'description' => 'Accès limité aux fonctionnalités de base',
                'permissions' => json_encode(['dashboard']),
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        DB::table('roles')->insert($roles);
    }
}
