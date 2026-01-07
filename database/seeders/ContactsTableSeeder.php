<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ContactsTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('contacts')->insert([
            [
                'user_id'    => 1,
                'name'       => 'Nguyễn Văn A',
                'email'      => 'nguyenvana@example.com',
                'phone'      => '0901234567',
                'content'    => 'Tôi muốn hỏi về tình trạng đơn hàng #12345.',
                'reply_id'   => 0,
                'created_by' => 1,
                'updated_by' => null,
                'status'     => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id'    => null,
                'name'       => 'Trần Thị B',
                'email'      => 'tranthib@example.com',
                'phone'      => '0912345678',
                'content'    => 'Shop có hỗ trợ thanh toán khi nhận hàng không?',
                'reply_id'   => 0,
                'created_by' => 1,
                'updated_by' => null,
                'status'     => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id'    => 2,
                'name'       => 'Lê Văn C',
                'email'      => 'levanc@example.com',
                'phone'      => '0987654321',
                'content'    => 'Sản phẩm tôi mua bị lỗi, có thể đổi trả không?',
                'reply_id'   => 0,
                'created_by' => 1,
                'updated_by' => null,
                'status'     => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }
}
