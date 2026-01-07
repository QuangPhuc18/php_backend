<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ProductStoreController extends Controller
{
    /**
     * API 1: Lấy danh sách tồn kho
     */
    public function index(Request $request)
    {
        $query = ProductStore::join('products', 'product_stores.product_id', '=', 'products.id')
            ->select(
                'product_stores.id as store_id', 
                'product_stores.product_id',
                'product_stores.qty',
                'product_stores.price_root',
                'product_stores.created_at',
                'product_stores.updated_at',
                'products.name as product_name',
                'products.thumbnail as product_image',
                'products.status as product_status'
            );

        if ($request->keyword) {
            $query->where('products.name', 'like', "%{$request->keyword}%");
        }

        $inventory = $query->orderBy('product_stores.created_at', 'desc')->paginate(10);

        $inventory->getCollection()->transform(function ($item) {
            $item->product_image = $this->getValidImageUrl($item->product_image);
            $item->sku = 'SP-' . $item->product_id . '-L' . $item->store_id; 
            return $item;
        });

        return response()->json([
            'status' => true,
            'data' => $inventory
        ]);
    }

    /**
     * API 2: Nhập hàng mới (Create)
     */
    public function importGoods(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'qty_import' => 'required|integer|min:1',
            'price_root' => 'required|numeric|min:0',
        ], [
            'product_id.exists' => 'Sản phẩm không tồn tại.',
            'qty_import.min' => 'Số lượng nhập phải lớn hơn 0.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $userId = Auth::id() ?? 1;

        DB::beginTransaction();
        try {
            // 1. Chỉ tạo lô hàng mới vào kho
            ProductStore::create([
                'product_id' => $request->product_id,
                'price_root' => $request->price_root,
                'qty'        => $request->qty_import,
                'created_by' => $userId,
                'status'     => 1 // Status của lô hàng nhập (không phải status sản phẩm)
            ]);

            // [ĐÃ BỎ]: Logic tự động kích hoạt sản phẩm ($this->activateProduct...)
            // Sản phẩm sẽ giữ nguyên trạng thái cũ (Active hoặc Inactive)

            DB::commit();
            return response()->json([
                'status' => true, 
                'message' => "Đã nhập thêm lô hàng mới thành công!"
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API 3: Cập nhật lô hàng (Update)
     */
    public function update(Request $request, $id)
    {
        // Tìm lô hàng theo store_id
        $store = ProductStore::find($id);

        if (!$store) {
            return response()->json(['status' => false, 'message' => 'Lô hàng không tồn tại'], 404);
        }

        $validator = Validator::make($request->all(), [
            'qty'        => 'required|integer|min:1',
            'price_root' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Cập nhật thông tin lô hàng
            $store->update([
                'qty' => $request->qty,
                'price_root' => $request->price_root,
            ]);

            // [ĐÃ BỎ]: Logic kích hoạt lại sản phẩm. 
            // Admin tự quản lý việc hiển thị bên trang Products.

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Cập nhật lô hàng thành công',
                'data' => $store
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API 4: Xem chi tiết
     */
    public function show($storeId)
    {
        $store = ProductStore::with('product')->find($storeId);
        if (!$store) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy'], 404);
        }
        return response()->json(['status' => true, 'data' => $store]);
    }

    /**
     * API 5: Xóa lô hàng
     */
    public function destroy($storeId)
    {
        try {
            $store = ProductStore::find($storeId);
            if (!$store) {
                return response()->json(['status' => false, 'message' => 'Không tìm thấy'], 404);
            }

            $store->delete();
            
            return response()->json(['status' => true, 'message' => 'Đã xóa lô hàng thành công']);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    // --- HELPER FUNCTIONS ---
    private function getValidImageUrl($path) {
        if (!$path) return 'https://placehold.co/60';
        return Str::startsWith($path, ['http', 'https']) ? $path : asset('storage/' . $path);
    }
}