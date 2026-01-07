<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class OrderController extends Controller
{
    // Quy ước trạng thái: 1: Pending, 2: Processing, 3: Shipping, 4: Completed, 0: Cancelled

    // =========================================================================
    // 1. TẠO ĐƠN HÀNG (Khách mua hàng)
    // =========================================================================
    public function store(Request $request)
    {
        // 1. Validate
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'name'    => 'required|string|max:255',
            'email'   => 'nullable|email|max:255', // Email có thể null nếu khách không nhập
            'phone'   => 'required|string|max:20',
            'address' => 'required|string|max:255',
            
            'details' => 'required|array|min:1',
            'details.*.product_id' => 'required|integer',
            'details.*.qty'        => 'required|integer|min:1',
            'details.*.price'      => 'required|numeric|min:0',
            
            // Validate cả key 'size' hoặc 'option' nếu frontend gửi lên
            'details.*.size'       => 'nullable|string|max:50', 
            'details.*.option'     => 'nullable|string|max:50', 
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // 2. Tạo Order Cha
            $order = Order::create([
                'user_id' => $request->user_id,
                'name'    => $request->name,
                'email'   => $request->email,
                'phone'   => $request->phone,
                'address' => $request->address,
                'note'    => $request->note,
                'status'  => 1, // Mặc định Pending
                'total_amount' => 0, // Sẽ update sau
                'created_by' => $request->user_id
            ]);

            $totalAmount = 0;

            // 3. Tạo Order Details
            foreach ($request->details as $item) {
                $qty = $item['qty'];
                $price = $item['price'];
                $discount = $item['discount'] ?? 0;
                $amount = ($qty * $price) - $discount;

                // [QUAN TRỌNG] Logic bắt Size linh hoạt
                // Frontend React có thể gửi key 'option' hoặc 'size', ta lấy cái nào có dữ liệu
                $sizeValue = $item['size'] ?? ($item['option'] ?? null);

                OrderDetail::create([
                    'order_id'   => $order->id,
                    'product_id' => $item['product_id'],
                    'size'       => $sizeValue, // Lưu vào cột size
                    'price'      => $price,
                    'qty'        => $qty,
                    'discount'   => $discount,
                    'amount'     => $amount
                ]);

                $totalAmount += $amount;
            }

            // Cập nhật tổng tiền cho đơn hàng
            $order->update(['total_amount' => $totalAmount]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Đặt hàng thành công!',
                'id' => $order->id // Trả về ID để frontend redirect
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Lỗi tạo đơn: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 2. XEM CHI TIẾT ĐƠN HÀNG
    // =========================================================================
    public function show($id)
    {
        // Eager loading: Load luôn thông tin sản phẩm để hiển thị ảnh/tên
        $order = Order::with(['orderDetails.product'])->find($id);

        if (!$order) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $order
        ]);
    }

    // =========================================================================
    // 3. DANH SÁCH ĐƠN HÀNG (Admin/User)
    // =========================================================================
    public function index(Request $request)
    {
        // Nên load kèm orderDetails để hiển thị nhanh (preview) nếu cần
        // Hoặc chỉ load bảng orders cho nhẹ
        $query = Order::query();

        // Tìm kiếm
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('id', $term)
                  ->orWhere('name', 'like', "%{$term}%")
                  ->orWhere('phone', 'like', "%{$term}%");
            });
        }

        // Lọc theo Status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Lọc theo User (nếu muốn xem lịch sử mua hàng của user cụ thể)
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $query->orderBy('created_at', 'desc');

        $orders = $query->paginate($request->input('limit', 10));

        return response()->json([
            'status' => true,
            'data' => $orders
        ]);
    }

    // =========================================================================
    // 4. CẬP NHẬT TRẠNG THÁI (Admin)
    // =========================================================================
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|integer', 
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $order = Order::find($id);
        if (!$order) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }

        $order->status = $request->status;
        
        // Nếu muốn lưu ghi chú trạng thái (nếu có cột status_note trong DB)
        if ($request->has('note') && $request->note) {
             // $order->status_note = $request->note; 
        }

        $order->save();

        return response()->json([
            'status' => true,
            'message' => 'Cập nhật trạng thái thành công',
            'current_status' => $order->status
        ]);
    }

    // =========================================================================
    // 5. XÓA ĐƠN HÀNG
    // =========================================================================
    public function destroy($id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy đơn hàng'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Xóa chi tiết trước
            $order->orderDetails()->delete();

            // Xóa đơn hàng
            $order->delete();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Xóa đơn hàng thành công'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Lỗi xóa đơn hàng: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ], 500);
        }
    }

    
    public function myOrders(Request $request)
    {
        $user = $request->user();
        
        // Giả sử bạn có relationship 'orderDetails' trong Model Order
        $orders = Order::where('user_id', $user->id)
            ->with('orderDetails') 
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $orders
        ]);
    }

    // Hủy đơn hàng (Chỉ khi status = 1: Chờ xác nhận)
    public function cancelOrder(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::where('id', $id)->where('user_id', $user->id)->first();

        if (!$order) {
            return response()->json(['status' => false, 'message' => 'Đơn hàng không tồn tại'], 404);
        }

        // Kiểm tra trạng thái (Ví dụ: 1 là chờ xác nhận, 2 là đang giao...)
        if ($order->status != 1) {
            return response()->json(['status' => false, 'message' => 'Đơn hàng đã được xử lý, không thể hủy'], 400);
        }

        $order->status = 0; // 0: Đã hủy
        $order->save();

        return response()->json(['status' => true, 'message' => 'Hủy đơn hàng thành công']);
    }
}