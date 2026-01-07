<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB; // Nhớ import thư viện DB

class CategoriesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Xóa dữ liệu cũ nếu cần (tùy chọn)
        // DB::table('category')->truncate();

        $categories = [
            [
                'name' => 'Cà phê',
                'slug' => 'ca-phe',
                'image' => 'images/category/ca-phe.jpg',
                'parent_id' => 0,
                'sort_order' => 1,
                'description' => 'Các loại cà phê Việt Nam và Quốc tế đậm đà hương vị.',
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => 1,
                'updated_at' => null,
                'updated_by' => null,
                'status' => 1,
            ],
            [
                'name' => 'Freeze',
                'slug' => 'freeze',
                'image' => 'images/category/freeze.jpg',
                'parent_id' => 0,
                'sort_order' => 2,
                'description' => 'Thức uống đá xay mát lạnh, sảng khoái.',
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => 1,
                'updated_at' => null,
                'updated_by' => null,
                'status' => 1,
            ],
            [
                'name' => 'Trà',
                'slug' => 'tra',
                'image' => 'images/category/tra.jpg',
                'parent_id' => 0,
                'sort_order' => 3,
                'description' => 'Các loại trà trái cây và trà sữa thơm ngon.',
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => 1,
                'updated_at' => null,
                'updated_by' => null,
                'status' => 1,
            ],
            [
                'name' => 'Bánh',
                'slug' => 'banh',
                'image' => 'images/category/banh.jpg',
                'parent_id' => 0,
                'sort_order' => 4,
                'description' => 'Bánh ngọt và bánh mặn ăn kèm hoàn hảo.',
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => 1,
                'updated_at' => null,
                'updated_by' => null,
                'status' => 1,
            ],
        ];

        // Insert dữ liệu vào bảng category
        DB::table('categories')->insert($categories);
    }
}