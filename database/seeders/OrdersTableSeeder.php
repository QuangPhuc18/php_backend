<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrdersTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('orders')->insert([
            [
                'user_id'    => 1,
                'name'       => 'Nguyễn Văn A',
                'email'      => 'nguyenvana@example.com',
                'phone'      => '0901234567',
                'address'    => '12 Nguyễn Trãi, Q.1, TP.HCM',
                'note'       => 'Khách thích uống cà phê rang mộc.',
                'created_by' => 1,
                'updated_by' => null,
                'status'     => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],

            [
                'user_id'    => 2,
                'name'       => 'Trần Thị B',
                'email'      => 'tranthib@example.com',
                'phone'      => '0908888999',
                'address'    => '45 Lê Lợi, Q.3, TP.HCM',
                'note'       => 'Giao giờ hành chính. Thêm ống hút giấy.',
                'created_by' => 1,
                'updated_by' => null,
                'status'     => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],

            [
                'user_id'    => 3,
                'name'       => 'Phạm Quốc C',
                'email'      => 'phamquocc@example.com',
                'phone'      => '0912345678',
                'address'    => '100 Võ Văn Kiệt, Q.5, TP.HCM',
                'note'       => 'Khách mua cà phê phin giấy, không đường.',
                'created_by' => 1,
                'updated_by' => null,
                'status'     => 1,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }
}
