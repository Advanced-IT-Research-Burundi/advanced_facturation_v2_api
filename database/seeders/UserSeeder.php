<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;


class UserSeeder extends Seeder
{
     public function run()
    {
        // First, create roles
        $roles = [
            ['name' => 'Super Admin', 'description' => 'System Administrator'],
            ['name' => 'Admin', 'description' => 'Company Administrator'],
            ['name' => 'Manager', 'description' => 'Department Manager'],
            ['name' => 'Cashier', 'description' => 'Sales Cashier'],
            ['name' => 'Warehouse', 'description' => 'Warehouse Manager'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->insert([
                'name' => $role['name'],
                'description' => $role['description'],
                // 'user_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create users
        $users = [
            [
                'name' => 'Super Administrator',
                'email' => 'superadmin@system.com',
                'password' => Hash::make('password123'),
                'company_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'John Doe',
                'email' => 'john.doe@company.com',
                'password' => Hash::make('password123'),
                'company_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane.smith@company.com',
                'password' => Hash::make('password123'),
                'company_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Robert Johnson',
                'email' => 'robert.j@company.com',
                'password' => Hash::make('password123'),
                'company_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Maria Garcia',
                'email' => 'maria.g@company.com',
                'password' => Hash::make('password123'),
                'company_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'David Wilson',
                'email' => 'david.w@company.com',
                'password' => Hash::make('password123'),
                'company_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Sarah Miller',
                'email' => 'sarah.m@company.com',
                'password' => Hash::make('password123'),
                'company_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Michael Brown',
                'email' => 'michael.b@company.com',
                'password' => Hash::make('password123'),
                'company_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Lisa Davis',
                'email' => 'lisa.d@company.com',
                'password' => Hash::make('password123'),
                'company_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'James Wilson',
                'email' => 'james.w@company.com',
                'password' => Hash::make('password123'),
                'company_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('users')->insert($users);

        // Assign roles through role_users table
        for ($i = 1; $i <= 10; $i++) {
            DB::table('role_users')->insert([
                'role_id' => $i <= 5 ? $i : ($i - 5),
                'user_id' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
