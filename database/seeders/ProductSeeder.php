<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('products')->insert([
            [
                'category_id' => 1,
                'name' => 'Cà phê sữa đá',
                'slug' => Str::slug('Cà phê sữa đá'),
                'thumbnail' => 'ca-phe-sua-da.jpg',
                'content' => 'Cà phê phin pha cùng sữa đặc, hương vị đậm và béo.',
                'description' => 'Cà phê truyền thống.',
                'price_buy' => 25000,
                'created_at' => now(),
                'created_by' => 1,
                'updated_at' => now(),
                'updated_by' => 1,
                'status' => 1,
            ],
            [
                'category_id' => 1,
                'name' => 'Cà phê đen đá',
                'slug' => Str::slug('Cà phê đen đá'),
                'thumbnail' => 'ca-phe-den-da.jpg',
                'content' => 'Cà phê đen truyền thống pha phin, vị đậm mạnh.',
                'description' => 'Cà phê không đường.',
                'price_buy' => 20000,
                'created_at' => now(),
                'created_by' => 1,
                'updated_at' => now(),
                'updated_by' => 1,
                'status' => 1,
            ],
            [
                'category_id' => 2,
                'name' => 'Trà đào cam sả Freeze',
                'slug' => Str::slug('Trà đào cam sả Freeze'),
                'thumbnail' => 'tra-dao-cam-sa-freeze.jpg',
                'content' => 'Trà đào xay đá lạnh kết hợp cam và sả, vị thanh mát.',
                'description' => 'Đồ uống xay lạnh.',
                'price_buy' => 45000,
                'created_at' => now(),
                'created_by' => 1,
                'updated_at' => now(),
                'updated_by' => 1,
                'status' => 1,
            ],
            [
                'category_id' => 2,
                'name' => 'Trà Matcha Freeze',
                'slug' => Str::slug('Trà Matcha Freeze'),
                'thumbnail' => 'tra-matcha-freeze.jpg',
                'content' => 'Matcha nguyên chất xay lạnh với kem sữa.',
                'description' => 'Thức uống matcha.',
                'price_buy' => 48000,
                'created_at' => now(),
                'created_by' => 1,
                'updated_at' => now(),
                'updated_by' => 1,
                'status' => 1,
            ],
            [
                'category_id' => 3,
                'name' => 'Bánh Croissant Bơ',
                'slug' => Str::slug('Bánh Croissant Bơ'),
                'thumbnail' => 'banh-croissant-bo.jpg',
                'content' => 'Bánh croissant bơ thơm, lớp vỏ giòn rụm và ruột mềm.',
                'description' => 'Bánh ngọt Pháp.',
                'price_buy' => 30000,
                'created_at' => now(),
                'created_by' => 1,
                'updated_at' => now(),
                'updated_by' => 1,
                'status' => 1,
            ],
        ]);
    }
}
