<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insert([
            [
                'name'        => 'Admin User',
                'email'       => 'admin@example.com',
                'phone'       => '0909000001',
                'username'    => 'admin',
                'password'    => Hash::make('123456'),
                'roles'       => 'admin', // ENUM OK
                'avatar'      => 'avatar_admin.png',
                'created_by'  => 1,
                'updated_by'  => 1,
                'status'      => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'name'        => 'Staff User',
                'email'       => 'staff@example.com',
                'phone'       => '0909000002',
                'username'    => 'staff',
                'password'    => Hash::make('123456'),
                'roles'       => 'admin', // Dùng admin vì ENUM KHÔNG CÓ 'manager'
                'avatar'      => 'avatar_staff.png',
                'created_by'  => 1,
                'updated_by'  => 1,
                'status'      => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'name'        => 'Customer User',
                'email'       => 'customer@example.com',
                'phone'       => '0909000003',
                'username'    => 'customer',
                'password'    => Hash::make('123456'),
                'roles'       => 'customer', // ENUM OK
                'avatar'      => 'avatar_customer.png',
                'created_by'  => 1,
                'updated_by'  => 1,
                'status'      => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }
}
