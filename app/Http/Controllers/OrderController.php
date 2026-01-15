<?php

namespace App\Http\Controllers;

use App\Mail\OrderSuccess;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductStore;
use App\Services\VnpayService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Đảm bảo import Auth
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    protected VnpayService $vnpayService;

    public function __construct(VnpayService $vnpayService)
    {
        $this->vnpayService = $vnpayService;
    }

    // =========================================================================
    // 1. TẠO YÊU CẦU ĐẶT HÀNG (STORE)
    // =========================================================================
    public function store(Request $request)
    {
        // --- 1. KIỂM TRA ĐĂNG NHẬP ---
        // Nếu không tìm thấy user trong request (Token không hợp lệ hoặc chưa gửi)
        // Trả về 401 để Frontend chuyển hướng về trang Login
        if (!$request->user()) {
            return response()->json([
                'status'        => false,
                'message'       => 'Vui lòng đăng nhập để thực hiện đặt hàng.',
                'require_login' => true
            ], 401);
        }

        // Lấy ID người dùng từ Token (An toàn hơn lấy từ request body)
        $userId = $request->user()->id;

        // --- 2. VALIDATION ---
        $validator = Validator::make($request->all(), [
            // 'user_id' => 'nullable|integer', // Không cần validate user_id từ body nữa vì lấy từ Auth
            'name'                 => 'required|string|max:255',
            'email'                => 'required|email|max:255',
            'phone'                => 'required|string|max:20',
            'address'              => 'required|string|max:255',
            'details'              => 'required|array|min:1',
            'details.*.product_id' => 'required|integer|exists:products,id',
            'details.*.qty'        => 'required|integer|min:1', // Đã sửa lỗi khoảng trắng: details.*.qty
            'details.*.price'      => 'required|numeric|min:0',
            'payment_method'       => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            // Tính tổng tiền & kiểm tra tồn kho
            $totalMoney = 0;
            foreach ($request->details as $item) {
                $stock = ProductStore::where('product_id', $item['product_id'])->sum('qty');
                if ($stock < $item['qty']) {
                    return response()->json([
                        'status'  => false,
                        'message' => "Sản phẩm ID {$item['product_id']} không đủ hàng (còn {$stock})",
                    ], 400);
                }

                $discount    = $item['discount'] ?? 0;
                $totalMoney += ($item['qty'] * $item['price']) - $discount;
            }

            $paymentMethod = $request->payment_method ?? 'cod';

            // =====================================================
            // CASE 1: THANH TOÁN VNPAY
            // =====================================================
            if ($paymentMethod === 'vnpay') {
                if ($totalMoney < 10000) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Thanh toán VNPay yêu cầu tối thiểu 10.000đ',
                    ], 400);
                }

                $tempOrderId = 'VNP_' . time() . '_' . rand(1000, 9999);

                // Lưu đầy đủ data vào Cache (30 phút)
                // Sử dụng $userId đã lấy từ Auth
                $orderData = [
                    'user_id'        => $userId, 
                    'name'           => $request->name,
                    'email'          => $request->email,
                    'phone'          => $request->phone,
                    'address'        => $request->address,
                    'note'           => $request->note ?? null,
                    'details'        => $request->details,
                    'total_money'    => $totalMoney,
                    'payment_method' => 'vnpay',
                ];
                Cache::put($tempOrderId, $orderData, now()->addMinutes(30));

                // Gọi VNPay
                $dummyOrder = (object) [
                    'id'          => $tempOrderId,
                    'total_money' => $totalMoney,
                ];

                $vnpayResponse = $this->vnpayService->createPayment($dummyOrder);

                if (isset($vnpayResponse['payUrl'])) {
                    return response()->json([
                        'status'  => true,
                        'message' => 'Chuyển hướng thanh toán VNPay...',
                        'payUrl'  => $vnpayResponse['payUrl'],
                        'orderId' => $tempOrderId,
                    ], 201);
                }

                Log::error('VNPay Create Payment Failed:', $vnpayResponse);
                return response()->json([
                    'status'  => false,
                    'message' => 'Lỗi kết nối VNPay',
                ], 500);
            }

            // =====================================================
            // CASE 2: THANH TOÁN COD
            // =====================================================
            DB::beginTransaction();

            // Truyền userId vào hàm tạo đơn
            $order = $this->createOrderRecord($request->all(), $totalMoney, 'cod', $userId);
            $this->deductStock($order);

            DB::commit();

            $this->sendOrderConfirmationEmail($order);

            return response()->json([
                'status'   => true,
                'message'  => 'Đặt hàng COD thành công! Vui lòng kiểm tra email.',
                'order_id' => $order->id,
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Order Store Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status'  => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // 2. VNPAY IPN (Server-to-Server Callback)
    // =========================================================================
    public function vnpayIpn(Request $request)
    {
        Log::info('VNPay IPN Received:', $request->all());

        try {
            $vnpData = $request->all();
            $result = $this->vnpayService->verifyPayment($vnpData);

            if (! $result['isValid']) {
                Log::error('VNPay IPN: Invalid signature');
                return response()->json(['RspCode' => '97', 'Message' => 'Invalid signature'], 200);
            }

            $tempOrderId = $result['txnRef'];
            $responseCode = $result['responseCode'];

            // 00 = Thành công
            if ($responseCode == '00') {
                $orderData = Cache::get($tempOrderId);

                if (!$orderData) {
                    Log::error("VNPay IPN: Cache not found for orderId: {$tempOrderId}");

                    $existingOrder = Order::where('note', 'LIKE', "%{$tempOrderId}%")->first();
                    if ($existingOrder) {
                        Log::info("VNPay IPN: Order already processed: #{$existingOrder->id}");
                        return response()->json(['RspCode' => '00', 'Message' => 'Confirm Success'], 200);
                    }

                    return response()->json(['RspCode' => '01', 'Message' => 'Order not found'], 200);
                }

                DB::beginTransaction();
                try {
                    // Lấy userId từ data đã cache
                    $userId = $orderData['user_id'] ?? 1;
                    $order = $this->createOrderRecord($orderData, $orderData['total_money'], 'vnpay', $userId);

                    $order->status = 2; // Đã thanh toán
                    $order->note = ($order->note ?? '') . " | VNPay TxnRef: {$tempOrderId} | TransNo: {$result['transactionNo']}";
                    $order->save();

                    $this->deductStock($order);

                    DB::commit();

                    $this->sendOrderConfirmationEmail($order);
                    Cache::forget($tempOrderId);

                    Log::info("VNPay IPN: Order created successfully #{$order->id}");

                    return response()->json(['RspCode' => '00', 'Message' => 'Confirm Success'], 200);
                } catch (Exception $dbEx) {
                    DB::rollBack();
                    Log::error("VNPay IPN Save Error: " . $dbEx->getMessage());
                    return response()->json(['RspCode' => '99', 'Message' => 'Database Error'], 200);
                }
            } else {
                Log::warning("VNPay IPN: Payment failed - TxnRef: {$tempOrderId}, ResponseCode: {$responseCode}");
                Cache::forget($tempOrderId);
                return response()->json(['RspCode' => '00', 'Message' => 'Payment Failed Acknowledged'], 200);
            }
        } catch (Exception $e) {
            Log::error("VNPay IPN Exception: " . $e->getMessage());
            return response()->json(['RspCode' => '99', 'Message' => 'System Error'], 200);
        }
    }

    // =========================================================================
    // 3. VNPAY RETURN (User redirect back)
    // =========================================================================
    public function checkVnpayOrder(Request $request)
    {
        Log::info("VNPay Return Called:", $request->all());

        $vnpData = $request->all();
        $result = $this->vnpayService->verifyPayment($vnpData);

        if (!$result['isValid']) {
            Log::error('VNPay Return: Invalid signature');
            return response()->json([
                'status'  => false,
                'message' => 'Chữ ký không hợp lệ',
            ], 400);
        }

        $tempOrderId = $result['txnRef'];
        $responseCode = $result['responseCode'];

        // 00 = Thành công
        if ($responseCode != '00') {
            Cache::forget($tempOrderId);
            return response()->json([
                'status'       => false,
                'message'      => $this->getVnpayErrorMessage($responseCode),
                'responseCode' => $responseCode,
            ], 400);
        }

        // Kiểm tra đơn đã tồn tại chưa
        $existingOrder = Order::where('note', 'LIKE', "%{$tempOrderId}%")->first();
        if ($existingOrder) {
            Log::info("VNPay Return: Order already exists #{$existingOrder->id}");
            return response()->json([
                'status'   => true,
                'message'  => 'Đơn hàng đã được xử lý',
                'order_id' => $existingOrder->id,
                'order'    => $existingOrder->load('orderDetails.product'),
            ], 200);
        }

        // Lấy data từ Cache
        $orderData = Cache::get($tempOrderId);
        if (!$orderData) {
            Log::error("VNPay Return: Cache not found for {$tempOrderId}");
            return response()->json([
                'status'  => false,
                'message' => 'Không tìm thấy thông tin đơn hàng (có thể đã hết hạn)',
            ], 404);
        }

        // Tạo đơn hàng
        DB::beginTransaction();
        try {
            $userId = $orderData['user_id'] ?? 1;
            $order = $this->createOrderRecord($orderData, $orderData['total_money'], 'vnpay', $userId);

            $order->status = 2;
            $order->note = ($order->note ?? '') . " | VNPay TxnRef: {$tempOrderId} | TransNo: {$result['transactionNo']} | Processed via Return URL";
            $order->save();

            $this->deductStock($order);

            DB::commit();

            $this->sendOrderConfirmationEmail($order);
            Cache::forget($tempOrderId);

            Log::info("VNPay Return: Order created successfully #{$order->id}");

            return response()->json([
                'status'   => true,
                'message'  => 'Đơn hàng đã được tạo thành công',
                'order_id' => $order->id,
                'order'    => $order->load('orderDetails.product'),
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("VNPay Return Error: " . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'Lỗi xử lý đơn hàng: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lấy message lỗi VNPay
     */
    private function getVnpayErrorMessage($code)
    {
        $messages = [
            '07' => 'Trừ tiền thành công. Giao dịch bị nghi ngờ (liên quan tới lừa đảo, giao dịch bất thường).',
            '09' => 'Thẻ/Tài khoản chưa đăng ký dịch vụ InternetBanking tại ngân hàng.',
            '10' => 'Khách hàng xác thực thông tin thẻ/tài khoản không đúng quá 3 lần.',
            '11' => 'Đã hết hạn chờ thanh toán. Xin quý khách vui lòng thực hiện lại giao dịch.',
            '12' => 'Thẻ/Tài khoản bị khóa.',
            '13' => 'Quý khách nhập sai mật khẩu xác thực giao dịch (OTP).',
            '24' => 'Khách hàng hủy giao dịch.',
            '51' => 'Tài khoản không đủ số dư để thực hiện giao dịch.',
            '65' => 'Tài khoản đã vượt quá hạn mức giao dịch trong ngày.',
            '75' => 'Ngân hàng thanh toán đang bảo trì.',
            '79' => 'Khách hàng nhập sai mật khẩu thanh toán quá số lần quy định.',
            '99' => 'Lỗi không xác định.',
        ];

        return $messages[$code] ?? "Thanh toán thất bại (Mã lỗi: {$code})";
    }

    // =========================================================================
    // PRIVATE HELPERS (Giữ nguyên)
    // =========================================================================
    
    // Cập nhật hàm này để nhận tham số userId chính xác
    private function createOrderRecord($data, $totalMoney, $method, $userId = 1)
    {
        $order = Order::create([
            'user_id'        => $userId, // Sử dụng ID từ Auth hoặc Cache
            'name'           => $data['name'],
            'email'          => $data['email'],
            'phone'          => $data['phone'],
            'address'        => $data['address'],
            'note'           => $data['note'] ?? null,
            'status'         => 1,
            'total_money'    => $totalMoney,
            'created_by'     => $userId,
            'payment_method' => $method,
        ]);

        foreach ($data['details'] as $item) {
            $qty      = $item['qty'];
            $price    = $item['price'];
            $discount = $item['discount'] ?? 0;
            $amount   = ($qty * $price) - $discount;
            $size     = $item['size'] ?? ($item['option'] ?? null);

            OrderDetail::create([
                'order_id'   => $order->id,
                'product_id' => $item['product_id'],
                'qty'        => $qty,
                'price'      => $price,
                'amount'     => $amount,
                'size'       => $size,
                'discount'   => $discount,
            ]);
        }

        return $order;
    }

    private function deductStock($order)
    {
        foreach ($order->orderDetails as $detail) {
            $batches = ProductStore::where('product_id', $detail->product_id)
                ->where('qty', '>', 0)
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->get();

            $qtyNeed = $detail->qty;

            foreach ($batches as $batch) {
                if ($qtyNeed <= 0) break;

                if ($batch->qty >= $qtyNeed) {
                    $batch->qty -= $qtyNeed;
                    $batch->save();
                    $qtyNeed = 0;
                } else {
                    $qtyNeed -= $batch->qty;
                    $batch->qty = 0;
                    $batch->save();
                }
            }

            $remaining = ProductStore::where('product_id', $detail->product_id)->sum('qty');
            if ($remaining <= 0) {
                $product = Product::find($detail->product_id);
                if ($product) {
                    $product->status = 0;
                    $product->save();
                }
            }
        }
    }

    private function sendOrderConfirmationEmail($order)
    {
        if (!empty($order->email)) {
            try {
                $order->load('orderDetails.product');
                Mail::to($order->email)->send(new OrderSuccess($order));
                Log::info("Email sent to: {$order->email} for Order #{$order->id}");
            } catch (Exception $e) {
                Log::error("Mail Error for Order #{$order->id}: " . $e->getMessage());
            }
        }
    }

    // =========================================================================
    // CÁC API KHÁC (Đã sửa lỗi khoảng trắng)
    // =========================================================================
    public function show($id)
    {
        // Đã sửa: 'orderDetails.product' không có dấu cách
        $order = Order::with(['orderDetails.product'])->find($id);
        if (!$order) {
            return response()->json(['status' => false, 'message' => 'Not found'], 404);
        }
        return response()->json(['status' => true, 'data' => $order]);
    }

    public function index(Request $request)
    {
        $query = Order::with('orderDetails');

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('id', $term)
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('limit', 10));

        return response()->json(['status' => true, 'data' => $orders]);
    }

    public function updateStatus(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['status' => false, 'message' => 'Not found'], 404);
        }

        $order->status = $request->status;
        $order->save();

        Log::info("Order #{$id} status updated to: {$request->status}");

        return response()->json(['status' => true, 'message' => 'Updated status']);
    }

    public function destroy($id)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['status' => false, 'message' => 'Not found'], 404);
        }

        DB::beginTransaction();
        try {
            $order->orderDetails()->delete();
            $order->delete();
            DB::commit();

            Log::info("Order #{$id} deleted successfully");

            return response()->json(['status' => true, 'message' => 'Deleted successfully']);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Delete Order Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Cannot delete'], 500);
        }
    }

    public function myOrders(Request $request)
    {
        $user = $request->user();
        // Đã sửa: 'orderDetails.product' không có dấu cách
        $orders = Order::where('user_id', $user->id)
            ->with('orderDetails.product')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['status' => true, 'data' => $orders]);
    }

    public function cancelOrder(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::where('id', $id)->where('user_id', $user->id)->first();

        if (!$order) {
            return response()->json(['status' => false, 'message' => 'Not found'], 404);
        }

        if ($order->status != 1) {
            return response()->json(['status' => false, 'message' => 'Cannot cancel this order'], 400);
        }

        $order->status = 0;
        $order->save();

        Log::info("Order #{$id} cancelled by User #{$user->id}");

        return response()->json(['status' => true, 'message' => 'Order cancelled successfully']);
    }
}