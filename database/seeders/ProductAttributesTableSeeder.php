<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductAttributesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Lấy danh sách ID sản phẩm từ bảng 'products'
        // Lưu ý: Dựa vào hình ảnh, bảng của bạn là số nhiều (lqp_products)
        // Nên trong code gọi là 'products' (Laravel tự nối prefix lqp_)
        $productIds = DB::table('products')->pluck('id');

        if ($productIds->isEmpty()) {
            echo "⚠️ Cảnh báo: Bảng 'products' chưa có dữ liệu. Hãy chạy seeder Product trước!\n";
            return;
        }

        // 2. Cấu hình dữ liệu Size
        // Vì không có bảng attributes, ta tự quy định: attribute_id = 1 nghĩa là "Size"
        $SIZE_ATTRIBUTE_ID = 1; 
        $sizes = ['S', 'M', 'L'];
        
        $dataToInsert = [];
        $now = Carbon::now();

        // 3. Tạo dữ liệu cho từng sản phẩm
        foreach ($productIds as $productId) {
            foreach ($sizes as $size) {
                // Kiểm tra xem đã có size này chưa để tránh trùng lặp
                $exists = DB::table('product_attributes')
                    ->where('product_id', $productId)
                    ->where('attribute_id', $SIZE_ATTRIBUTE_ID)
                    ->where('value', $size)
                    ->exists();

                if (!$exists) {
                    $dataToInsert[] = [
                        'product_id'   => $productId,
                        'attribute_id' => $SIZE_ATTRIBUTE_ID, // ID tự quy định
                        'value'        => $size,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ];
                }
            }
        }

        // 4. Insert vào bảng 'product_attributes' (số nhiều)
        if (!empty($dataToInsert)) {
            // Dùng chunk để insert mỗi lần 100 dòng cho nhẹ database
            foreach (array_chunk($dataToInsert, 100) as $chunk) {
                DB::table('product_attributes')->insert($chunk);
            }
            echo "✅ Đã thêm xong Size S, M, L cho " . count($productIds) . " sản phẩm.\n";
        } else {
            echo "ℹ️ Dữ liệu đã đầy đủ, không cần thêm mới.\n";
        }
    }
}