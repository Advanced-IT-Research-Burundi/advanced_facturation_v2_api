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


        // Create users
        $users = [
            [

            "name" => "Jean Lionel", 
            "email" => "nijeanlionel@gmail.com",
            "password" => Hash::make(value: 'Advanced2026'), 
            'company_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            ],
            [
                'name' => 'Super Administrator',
                'email' => 'superadmin@system.com',
                'password' => Hash::make('password123'),
                'company_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Bazagod ',
                'email' => 'bazayo@example.com',
                'password' => Hash::make('12345678'),
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

            "name" => "Advanced Dev", 
            "email" => "dev@advancedit.com",
            "password" => Hash::make(value: 'Advanced2026'), 
            'company_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            ],
        ];

        DB::table('users')->insert($users);

        // Map users to roles
        $userRoleMap = [
            'nijeanlionel@gmail.com' => 'admin',
            'superadmin@system.com' => 'admin',
            'bazayo@example.com' => 'admin',
            'dev@advancedit.com' => 'admin',
            'john.doe@company.com' => 'manager',
            'jane.smith@company.com' => 'cashier',
            'robert.j@company.com' => 'sales',
            'maria.g@company.com' => 'stock_manager',
            'david.w@company.com' => 'pharmacist',
            'sarah.m@company.com' => 'baker',
            'michael.b@company.com' => 'accountant',
            'lisa.d@company.com' => 'user',
        ];

        foreach ($userRoleMap as $email => $roleName) {
            $user = DB::table('users')->where('email', $email)->first();
            $role = DB::table('roles')->where('name', $roleName)->first();

            if ($user && $role) {
                DB::table('role_users')->insert([
                    'role_id' => $role->id,
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
