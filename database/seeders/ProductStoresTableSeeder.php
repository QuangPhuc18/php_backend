<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductStoresTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('product_stores')->insert([
            [
                'product_id' => 1,
                'price_root' => 15000,
                'qty' => 100,
                'created_by' => 1,
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'product_id' => 2,
                'price_root' => 12000,
                'qty' => 80,
                'created_by' => 1,
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'product_id' => 3,
                'price_root' => 20000,
                'qty' => 50,
                'created_by' => 1,
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }
}
