<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BannersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('banners')->insert([
            [
                'name' => 'Summer Sale',
                'image' => 'uploads/banners/summer_sale.jpg',
                'link' => 'https://example.com/summer-sale',
                'position' => 'slideshow',   // ✔ Hợp lệ
                'sort_order' => 1,
                'description' => 'Banner giảm giá mùa hè với ưu đãi đến 50%',
                'created_at' => now(),
                'created_by' => 1,
                'updated_at' => now(),
                'updated_by' => 1,
                'status' => 1,
            ],
            [
                'name' => 'New Arrivals',
                'image' => 'uploads/banners/new_arrivals.jpg',
                'link' => 'https://example.com/new',
                'position' => 'ads',          // ✔ Hợp lệ
                'sort_order' => 2,
                'description' => 'Sản phẩm mới vừa lên kệ',
                'created_at' => now(),
                'created_by' => 1,
                'updated_at' => now(),
                'updated_by' => 1,
                'status' => 1,
            ],
            [
                'name' => 'Black Friday',
                'image' => 'uploads/banners/black_friday.jpg',
                'link' => 'https://example.com/black-friday',
                'position' => 'slideshow',    // ✔ Hợp lệ
                'sort_order' => 3,
                'description' => 'Khuyến mãi Black Friday siêu lớn',
                'created_at' => now(),
                'created_by' => 1,
                'updated_at' => now(),
                'updated_by' => 1,
                'status' => 1,
            ],
        ]);
    }
}
