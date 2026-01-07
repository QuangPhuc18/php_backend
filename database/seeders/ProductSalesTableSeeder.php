<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSalesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('product_sales')->insert([
            [
                'name' => 'Giảm giá đầu tuần',
                'product_id' => 1,
                'price_sale' => 199000,
                'date_begin' => '2025-12-10 00:00:00',
                'date_end' => '2025-12-15 23:59:59',
                'created_by' => 1,
                'updated_by' => 1,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Sale Giáng Sinh',
                'product_id' => 2,
                'price_sale' => 149000,
                'date_begin' => '2025-12-20 00:00:00',
                'date_end' => '2025-12-26 23:59:59',
                'created_by' => 1,
                'updated_by' => 1,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Flash Sale Black Friday',
                'product_id' => 3,
                'price_sale' => 99000,
                'date_begin' => '2025-11-25 00:00:00',
                'date_end' => '2025-11-27 23:59:59',
                'created_by' => 1,
                'updated_by' => 1,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
