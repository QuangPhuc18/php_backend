<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConfigsTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('configs')->insert([
            [
                'site_name' => 'Coffea',
                'email'     => 'admin@gmail.com',
                'phone'     => '0901 234 567',
                'hotline'   => '1800 6789',
                'address'   => 'Thủ Đức, TP. Hồ Chí Minh',
                'status'    => 1,
            ]
        ]);
    }
}
