<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductSale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ProductSaleController extends Controller
{
    // API 0: Lấy danh sách đang Sale (Giữ nguyên)
    public function index()
    {
        $sales = ProductSale::with('product')
            ->where('status', 1)
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $sales->map(function($sale) {
            $product = $sale->product;
            if (!$product) return null;

            $imageUrl = $product->thumbnail 
                ? (Str::startsWith($product->thumbnail, ['http', 'https']) ? $product->thumbnail : asset('storage/' . $product->thumbnail))
                : 'https://placehold.co/60';

            return [
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'name' => $product->name,
                'price_buy' => $product->price_buy,
                'salePrice' => $sale->price_sale,
                'thumbnail' => $product->thumbnail,
                'image_url' => $imageUrl,
                'date_begin' => $sale->date_begin,
                'date_end' => $sale->date_end,
                'is_expired' => Carbon::parse($sale->date_end)->isPast(),
                'discount_percent' => $product->price_buy > 0 
                    ? round((($product->price_buy - $sale->price_sale) / $product->price_buy) * 100) 
                    : 0
            ];
        })->filter()->values();

        return response()->json(['status' => true, 'data' => $data]);
    }

    // API 1: Modal chọn sản phẩm (Giữ nguyên)
    public function getProductsForSelection(Request $request)
    {
        $query = Product::select('id', 'name', 'price_buy', 'thumbnail')->where('status', 1);

        if ($request->keyword) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->keyword}%")
                  ->orWhere('sku', 'like', "%{$request->keyword}%");
            });
        }

        $products = $query->paginate(10);
        $products->getCollection()->transform(function ($product) {
            $product->image_url = $product->thumbnail 
                ? (Str::startsWith($product->thumbnail, ['http', 'https']) ? $product->thumbnail : asset('storage/' . $product->thumbnail))
                : 'https://placehold.co/60';
            return $product;
        });

        return response()->json(['status' => true, 'data' => $products]);
    }

    /**
     * API 2: Lưu khuyến mãi (NÂNG CẤP: Hỗ trợ nhiều khung giờ - Time Slots)
     */
    public function store(Request $request)
    {
        // 1. Validate cấu trúc dữ liệu mới
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.price_sale' => 'required|numeric|min:0',
            
            // Validate mảng khung giờ
            'time_slots' => 'required|array|min:1',
            'time_slots.*.date_begin' => 'required|date',
            'time_slots.*.date_end' => 'required|date|after:time_slots.*.date_begin',
        ], [
            'time_slots.required' => 'Vui lòng chọn ít nhất một khung giờ.',
            'time_slots.*.date_end.after' => 'Giờ kết thúc phải sau giờ bắt đầu.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $userId = Auth::id() ?? 1;
        $savedCount = 0;

        DB::beginTransaction();
        try {
            $errors = [];

            // VÒNG LẶP 1: Duyệt từng sản phẩm được chọn
            foreach ($request->products as $item) {
                $productOriginal = Product::find($item['id']);
                
                // Check giá (Giá sale < Giá gốc)
                if ($item['price_sale'] >= $productOriginal->price_buy) {
                    $errors[] = "SP '{$productOriginal->name}': Giá sale phải thấp hơn giá gốc.";
                    continue;
                }

                // VÒNG LẶP 2: Duyệt từng khung giờ (Time Slots)
                foreach ($request->time_slots as $slot) {
                    $newStart = Carbon::parse($slot['date_begin']);
                    $newEnd = Carbon::parse($slot['date_end']);

                    // Check trùng lịch (Logic chính xác từng giây)
                    $checkConflict = ProductSale::where('product_id', $item['id'])
                        ->where(function ($query) use ($newStart, $newEnd) {
                            $query->where('date_begin', '<', $newEnd)
                                  ->where('date_end', '>', $newStart);
                        })
                        ->first();

                    if ($checkConflict) {
                        $conflictStart = Carbon::parse($checkConflict->date_begin)->format('H:i d/m');
                        $conflictEnd = Carbon::parse($checkConflict->date_end)->format('H:i d/m');
                        $slotStr = $newStart->format('H:i') . ' - ' . $newEnd->format('H:i d/m');
                        
                        $errors[] = "SP '{$productOriginal->name}' khung giờ [{$slotStr}] bị trùng với CT: '{$checkConflict->name}' ({$conflictStart} - {$conflictEnd}).";
                        continue; // Bỏ qua slot này, chạy slot tiếp theo
                    }

                    // Nếu OK -> Lưu vào DB
                    ProductSale::create([
                        'name' => $request->name, // Dùng chung tên chiến dịch
                        'product_id' => $item['id'],
                        'price_sale' => $item['price_sale'],
                        'date_begin' => $newStart,
                        'date_end' => $newEnd,
                        'created_by' => $userId,
                        'status' => 1
                    ]);
                    $savedCount++;
                }
            }

            // Xử lý kết quả
            if (count($errors) > 0) {
                // Nếu bạn muốn khắt khe: Có lỗi là Rollback hết
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Phát hiện xung đột lịch trình, vui lòng kiểm tra lại.',
                    'details' => $errors
                ], 422);
            }

            DB::commit();
            return response()->json([
                'status' => true, 
                'message' => "Đã tạo thành công {$savedCount} lịch khuyến mãi!"
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()], 500);
        }
    }

    // API 3: Xóa (Giữ nguyên)
    public function destroy($id)
    {
        try {
            $productSale = ProductSale::find($id);
            if (!$productSale) return response()->json(['status' => false, 'message' => 'Không tìm thấy.'], 404);
            $productSale->delete();
            return response()->json(['status' => true, 'message' => 'Đã xóa thành công.']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }
}