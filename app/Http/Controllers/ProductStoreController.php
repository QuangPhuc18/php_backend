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
    // =========================================================================
    // 1. GET LIST
    // =========================================================================
    public function index(Request $request)
    {
        $query = ProductStore::join('products', 'product_stores.product_id', '=', 'products.id')
            ->select(
                'product_stores.id as store_id', 
                'product_stores.product_id', 
                'product_stores.qty', 
                'product_stores.price_root', 
                'product_stores.created_at', 
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

        return response()->json(['status' => true, 'data' => $inventory]);
    }

    // =========================================================================
    // 2. IMPORT (Nhập hàng mới -> Tăng kho -> Hiện sản phẩm)
    // =========================================================================
    public function importGoods(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'qty_import' => 'required|integer|min:1', // Nhập mới thì phải > 0
            'price_root' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // 1. Tạo lô hàng
            ProductStore::create([
                'product_id' => $request->product_id,
                'price_root' => $request->price_root,
                'qty'        => $request->qty_import,
                'created_by' => Auth::id() ?? 1,
                'status'     => 1
            ]);

            // 2. Cập nhật trạng thái sản phẩm
            $this->updateProductStatusBasedOnStock($request->product_id);

            DB::commit();
            return response()->json([
                'status' => true, 
                'message' => "Nhập kho thành công! Sản phẩm đã hiển thị trang chủ."
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 3. UPDATE (Cho phép chỉnh về 0 -> Nếu hết hàng thì Ẩn)
    // =========================================================================
    public function update(Request $request, $id)
    {
        $store = ProductStore::find($id);
        if (!$store) {
            return response()->json(['status' => false, 'message' => 'Lô hàng không tồn tại'], 404);
        }

        $validator = Validator::make($request->all(), [
            'qty'        => 'required|integer|min:0', // [QUAN TRỌNG] Cho phép nhập 0
            'price_root' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // 1. Cập nhật lô hàng
            $store->update([
                'qty' => $request->qty,
                'price_root' => $request->price_root
            ]);
            
            // 2. Kiểm tra tổng tồn kho để Ẩn/Hiện sản phẩm
            $this->updateProductStatusBasedOnStock($store->product_id);

            DB::commit();
            return response()->json([
                'status' => true, 
                'message' => 'Cập nhật thành công', 
                'data' => $store
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 4. DESTROY (Xóa lô hàng -> Kiểm tra lại kho để Ẩn/Hiện)
    // =========================================================================
    public function destroy($storeId) 
    { 
        DB::beginTransaction();
        try { 
            $store = ProductStore::find($storeId); 
            if (!$store) {
                return response()->json(['status' => false, 'message' => 'Not found'], 404);
            }
            
            $productId = $store->product_id; // Lưu lại ID sản phẩm trước khi xóa
            $store->delete();

            // Kiểm tra lại tồn kho sau khi xóa
            $this->updateProductStatusBasedOnStock($productId);

            DB::commit();
            return response()->json(['status' => true, 'message' => 'Xóa thành công']); 
        } catch(\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        } 
    }

    // =========================================================================
    // HELPER FUNCTIONS
    // =========================================================================
    
    public function show($storeId) { 
        $store = ProductStore::with('product')->find($storeId); 
        if(!$store) return response()->json(['status'=>false], 404); 
        return response()->json(['status'=>true,'data'=>$store]); 
    }

    private function getValidImageUrl($path) { 
        if (!$path) return 'https://placehold.co/60'; 
        return Str::startsWith($path, ['http', 'https']) ? $path : asset('storage/' . $path); 
    }

    /**
     * Hàm tính tổng tồn kho và cập nhật status cho Product
     */
    private function updateProductStatusBasedOnStock($productId)
    {
        // Tính tổng số lượng của sản phẩm này trong tất cả các lô
        $totalQty = ProductStore::where('product_id', $productId)->sum('qty');
        
        $product = Product::find($productId);
        if ($product) {
            if ($totalQty > 0) {
                // Nếu còn hàng -> Kích hoạt (Hiện)
                if ($product->status == 0) {
                    $product->status = 1;
                    $product->save();
                }
            } else {
                // Nếu hết hàng (Tổng = 0) -> Vô hiệu hóa (Ẩn)
                if ($product->status == 1) {
                    $product->status = 0;
                    $product->save();
                }
            }
        }
    }
}