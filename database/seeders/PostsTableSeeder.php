<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PostsTableSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('posts')->insert([
            [
                'topic_id'    => 1,
                'title'       => 'Khám phá quán Coffea – Thiên đường cà phê rang xay thủ công',
                'slug'        => Str::slug('Khám phá quán Coffea – Thiên đường cà phê rang xay thủ công'),
                'image'       => 'uploads/posts/coffee1.jpg',
                'content'     => '<p>Coffea là địa điểm yêu thích của những tín đồ cà phê nguyên chất. Chúng tôi phục vụ các dòng cà phê rang xay thủ công như Arabica, Robusta và Blend hương vị đậm đà.</p>
                                  <p>Không gian quán được thiết kế theo phong cách mộc với gỗ tự nhiên, giúp khách hàng cảm thấy thoải mái và thư giãn trong mỗi buổi sáng.</p>',
                'description' => 'Quán cà phê rang xay thủ công, không gian mộc và thư giãn.',
                'post_type'   => 'post',
                'created_by'  => 1,
                'updated_by'  => 1,
                'status'      => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],

            [
                'topic_id'    => 2,
                'title'       => 'Menu trà trái cây tươi mát tại Coffea',
                'slug'        => Str::slug('Menu trà trái cây tươi mát tại Coffea'),
                'image'       => 'uploads/posts/tea1.jpg',
                'content'     => '<p>Trà trái cây tại Coffea được pha chế từ nguyên liệu tự nhiên: cam, chanh, đào, dâu tằm,... Tất cả mang đến hương vị tươi mát, phù hợp cho mọi thời điểm trong ngày.</p>
                                  <p>Đặc biệt, trà đào cam sả và trà dâu tằm là hai món bán chạy nhất của quán.</p>',
                'description' => 'Trà trái cây tươi mát, nguồn nguyên liệu sạch và an toàn.',
                'post_type'   => 'post',
                'created_by'  => 1,
                'updated_by'  => 1,
                'status'      => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],

            [
                'topic_id'    => 3,
                'title'       => 'Thưởng thức bánh ngọt handmade mỗi ngày tại Coffea',
                'slug'        => Str::slug('Thưởng thức bánh ngọt handmade mỗi ngày tại Coffea'),
                'image'       => 'uploads/posts/cake1.jpg',
                'content'     => '<p>Tại Coffea, tất cả bánh ngọt đều được làm thủ công mỗi sáng. Không sử dụng chất bảo quản, đảm bảo độ tươi ngon và hương vị chuẩn mực.</p>
                                  <p>Các loại bánh nổi bật: Tiramisu, Cheesecake xoài, Bánh bông lan trứng muối.</p>',
                'description' => 'Bánh handmade luôn tươi mới mỗi ngày, thơm ngon chuẩn vị.',
                'post_type'   => 'post',
                'created_by'  => 1,
                'updated_by'  => 1,
                'status'      => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],

            [
                'topic_id'    => 1,
                'title'       => 'Không gian quán Coffea – Góc chill nhẹ nhàng cho ngày mới',
                'slug'        => Str::slug('Không gian quán Coffea – Góc chill nhẹ nhàng cho ngày mới'),
                'image'       => 'uploads/posts/space1.jpg',
                'content'     => '<p>Không gian Coffea được thiết kế theo phong cách Scandinavian tinh giản, tạo nên sự thoải mái cho khách đến làm việc, học tập hoặc gặp mặt bạn bè.</p>
                                  <p>Quán có khu vực ngoài trời với cây xanh, ánh sáng tự nhiên và tiếng nhạc acoustic nhẹ nhàng.</p>',
                'description' => 'Không gian chill phù hợp học tập, làm việc và thư giãn.',
                'post_type'   => 'post',
                'created_by'  => 1,
                'updated_by'  => 1,
                'status'      => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ]);
    }
}
