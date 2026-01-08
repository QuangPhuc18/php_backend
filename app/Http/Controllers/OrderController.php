<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductStore; // Model kho
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\MomoService;
use Exception;

class OrderController extends Controller
{
    // Quy ước trạng thái: 1: Pending, 2: Processing, 3: Shipping, 4: Completed, 0: Cancelled

    // =========================================================================
    // 1. TẠO ĐƠN HÀNG (Có trừ kho FIFO & Fix lỗi Discount)
    // =========================================================================
    protected $momoService;
    public function __construct(MomoService $momoService)
    {
        $this->momoService = $momoService;
    }
public function store(Request $request)
    {
        // 1. Validate Input (Giữ nguyên)
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer', 
            'name'    => 'required|string|max:255',
            'email'   => 'nullable|email|max:255',
            'phone'   => 'required|string|max:20',
            'address' => 'required|string|max:255',
            'details' => 'required|array|min:1',
            'details.*.product_id' => 'required|integer|exists:products,id',
            'details.*.qty'        => 'required|integer|min:1',
            'details.*.price'      => 'required|numeric|min:0',
            'payment_method'       => 'nullable|string' // Thêm validate cho payment_method
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // 2. Pre-check Tồn kho (Giữ nguyên)
            foreach ($request->details as $item) {
                $totalStock = ProductStore::where('product_id', $item['product_id'])->sum('qty');
                if ($totalStock < $item['qty']) {
                    $productName = Product::find($item['product_id'])->name ?? 'Sản phẩm';
                    return response()->json([
                        'status' => false,
                        'message' => "Sản phẩm '{$productName}' không đủ hàng. Trong kho còn: {$totalStock}"
                    ], 400); 
                }
            }

            // 3. Tạo Order Master (Giữ nguyên)
            $order = Order::create([
                'user_id' => $request->user_id ?? 1,
                'name'    => $request->name,
                'email'   => $request->email,
                'phone'   => $request->phone,
                'address' => $request->address,
                'note'    => $request->note,
                'status'  => 1, // Pending
                'total_money' => 0, 
                'created_by' => $request->user_id ?? 1,
                'payment_method' => $request->payment_method ?? 'cod' // Lưu phương thức thanh toán
            ]);

            $totalMoney = 0;

            // 4. Tạo Detail & Trừ Kho FIFO (Giữ nguyên)
            foreach ($request->details as $item) {
                $qtyBuy = $item['qty'];
                $price = $item['price'];
                $size = $item['size'] ?? ($item['option'] ?? null);
                $discount = isset($item['discount']) ? $item['discount'] : 0;
                $amount = ($qtyBuy * $price) - $discount;

                OrderDetail::create([
                    'order_id'   => $order->id,
                    'product_id' => $item['product_id'],
                    'qty'        => $qtyBuy,
                    'price'      => $price,
                    'amount'     => $amount,
                    'size'       => $size,
                    'discount'   => $discount
                ]);

                $totalMoney += $amount;

                // Trừ kho FIFO
                $batches = ProductStore::where('product_id', $item['product_id'])
                                       ->where('qty', '>', 0)
                                       ->orderBy('created_at', 'asc')
                                       ->lockForUpdate() // Khóa dòng để tránh xung đột
                                       ->get();

                $qtyNeedToDeduct = $qtyBuy;

                foreach ($batches as $batch) {
                    if ($qtyNeedToDeduct <= 0) break;

                    if ($batch->qty >= $qtyNeedToDeduct) {
                        $batch->qty -= $qtyNeedToDeduct;
                        $batch->save();
                        $qtyNeedToDeduct = 0;
                    } else {
                        $qtyNeedToDeduct -= $batch->qty;
                        $batch->qty = 0;
                        $batch->save();
                    }
                }

                // Check ẩn sản phẩm
                $remainingStock = ProductStore::where('product_id', $item['product_id'])->sum('qty');
                if ($remainingStock <= 0) {
                    $product = Product::find($item['product_id']);
                    if ($product) {
                        $product->status = 0; 
                        $product->save();
                    }
                }
            }

            // Update tổng tiền
            $order->update(['total_money' => $totalMoney]);

            // =========================================================
            // [LOGIC MỚI] XỬ LÝ THANH TOÁN MOMO
            // =========================================================
            if ($request->payment_method === 'momo') {
                // Commit transaction trước khi gọi API bên thứ 3 để đảm bảo đơn hàng đã lưu
                DB::commit(); 

                try {
                    $momoResponse = $this->momoService->createPayment($order);
                    
                    if (isset($momoResponse['payUrl'])) {
                        return response()->json([
                            'status' => true,
                            'message' => 'Tạo link thanh toán MoMo thành công',
                            'payUrl' => $momoResponse['payUrl'], // Frontend sẽ redirect
                            'order_id' => $order->id
                        ], 201);
                    } else {
                        // Nếu MoMo lỗi, trả về lỗi nhưng đơn hàng vẫn giữ ở trạng thái Pending (hoặc bạn có thể xóa đơn nếu muốn chặt chẽ)
                        return response()->json([
                            'status' => false,
                            'message' => 'Lỗi tạo thanh toán MoMo: ' . ($momoResponse['message'] ?? 'Unknown error'),
                            'order_id' => $order->id
                        ], 500);
                    }
                } catch (Exception $e) {
                    return response()->json(['status' => false, 'message' => 'Lỗi MoMo Service: ' . $e->getMessage()], 500);
                }
            }

            // Nếu là COD hoặc phương thức khác
            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Đặt hàng thành công!',
                'order_id' => $order->id
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Order Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()], 500);
        }
    }
    // =========================================================================
    // 2. CÁC API KHÁC (GIỮ NGUYÊN)
    // =========================================================================
    
    public function show($id)
    {
        $order = Order::with(['orderDetails.product'])->find($id);
        if (!$order) return response()->json(['status' => false, 'message' => 'Not found'], 404);
        return response()->json(['status' => true, 'data' => $order]);
    }

    public function index(Request $request)
    {
        $query = Order::query();
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('id', $term)->orWhere('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%");
            });
        }
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);

        $orders = $query->orderBy('created_at', 'desc')->paginate($request->input('limit', 10));
        return response()->json(['status' => true, 'data' => $orders]);
    }

    public function updateStatus(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) return response()->json(['status' => false, 'message' => 'Not found'], 404);
        
        $order->status = $request->status;
        $order->save();
        return response()->json(['status' => true, 'message' => 'Updated status']);
    }

    public function destroy($id)
    {
        $order = Order::find($id);
        if (!$order) return response()->json(['status' => false, 'message' => 'Not found'], 404);
        
        DB::beginTransaction();
        try {
            $order->orderDetails()->delete();
            $order->delete();
            DB::commit();
            return response()->json(['status' => true, 'message' => 'Deleted successfully']);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false], 500);
        }
    }

    public function myOrders(Request $request)
    {
        $user = $request->user();
        $orders = Order::where('user_id', $user->id)->with('orderDetails')->orderBy('created_at', 'desc')->get();
        return response()->json(['status' => true, 'data' => $orders]);
    }

    public function cancelOrder(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::where('id', $id)->where('user_id', $user->id)->first();
        if (!$order) return response()->json(['status' => false, 'message' => 'Not found'], 404);
        if ($order->status != 1) return response()->json(['status' => false, 'message' => 'Cannot cancel'], 400);

        $order->status = 0;
        $order->save();
        return response()->json(['status' => true, 'message' => 'Cancelled']);
    }
}