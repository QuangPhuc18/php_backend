<?php

use App\Http\Controllers\AttributeController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductSaleController;
use App\Http\Controllers\ProductStoreController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES (Không cần đăng nhập)
|--------------------------------------------------------------------------
| Các route này phải để public để ngân hàng (VNPay, Momo) có thể gọi lại 
| (Callback/IPN) mà không cần Token.
*/

// ✅ QUAN TRỌNG: Các route cụ thể của orders PHẢI đặt trước apiResource
Route::get('/orders/check-momo', [OrderController::class, 'checkMomoOrder']);
Route::get('/orders/check-momo-status', [OrderController::class, 'checkMomoStatus']);
Route::post('/orders/ipn', [OrderController::class, 'ipn']);

// Route VNPay (Move từ dưới lên đây cho gọn và đúng logic public)
Route::get('/orders/check-vnpay', [OrderController::class, 'checkVnpayOrder']);
Route::post('/orders/vnpay-ipn', [OrderController::class, 'vnpayIpn']);

// Route cập nhật trạng thái (Admin dùng, hoặc public tùy logic của bạn)
Route::post('/orders/{id}/status', [OrderController::class, 'updateStatus']);


// --- 1. RESOURCES CHUẨN (PUBLIC) ---
Route::apiResource('products', ProductController::class);
Route::apiResource('categories', CategoryController::class);
Route::post('categories/{id}', [CategoryController::class, 'update']);
Route::apiResource('/user', UserController::class); // Lưu ý: cái này quản lý user (Admin), cẩn thận lộ thông tin
Route::apiResource('banners', BannerController::class);
Route::apiResource('contacts', ContactController::class);
Route::post('contacts/{id}/reply', [ContactController::class, 'reply']);
Route::apiResource('configs', ConfigController::class);
Route::apiResource('posts', PostController::class);

Route::apiResource('attribute', AttributeController::class);
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);

// --- 2. PRODUCT SALES ---
Route::prefix('sales')->group(function () {
    Route::get('/', [ProductSaleController::class, 'index']);
    Route::get('/products-selection', [ProductSaleController::class, 'getProductsForSelection']);
    Route::post('/store', [ProductSaleController::class, 'store']);
});
Route::delete('/product-sales/{id}', [ProductSaleController::class, 'destroy']);

// --- 3. PRODUCT STORE ---
Route::prefix('store')->group(function () {
    Route::get('/', [ProductStoreController::class, 'index']);
    Route::post('/import', [ProductStoreController::class, 'importGoods']);
    Route::put('/{id}', [ProductStoreController::class, 'update']);
    Route::get('/{id}', [ProductStoreController::class, 'show']);
    Route::delete('/{id}', [ProductStoreController::class, 'destroy']);
});

// --- 4. AUTHENTICATION ---
Route::post('/login', [UserController::class, 'login']);

// Tin tức chi tiết & liên quan (Public)
Route::get('post_detail/{slug}', [PostController::class, 'getPostBySlug']);
Route::get('post_related/{topicId}/{excludeId}', [PostController::class, 'getRelatedPosts']);

// Test Mail
Route::get('/test-mail', function () {
    try {
        Mail::raw('Đây là email kiểm tra từ Laravel Shop LQP', function ($message) {
            $message->to('lequangphuc18092005@gmail.com')
                    ->subject('Test Mail thành công!');
        });
        return response()->json(['status' => true, 'message' => 'Gửi mail thành công! ']);
    } catch (\Exception $e) {
        return response()->json(['status' => false, 'message' => 'Lỗi:  ' . $e->getMessage()], 500);
    }
});


/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (Cần Token đăng nhập)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [UserController::class, 'logout']);
    Route::get('/profile', [UserController::class, 'profile']);
    Route::post('/profile/update', [UserController::class, 'updateProfile']);
    Route::post('/profile/change-password', [UserController::class, 'changePassword']);

    // 👇👇👇 QUAN TRỌNG: Route Orders đã được chuyển vào đây để nhận diện User 👇👇👇
    Route::apiResource('orders', OrderController::class);
    
    Route::get('/my-orders', [OrderController::class, 'myOrders']);
    Route::post('/my-orders/{id}/cancel', [OrderController::class, 'cancelOrder']);
});
Route::apiResource('topics', TopicController::class);
// Thêm dòng này:
// Route::get('/login', function () {
//     return response()->json([
//         'status' => false,
//         'message' => 'Bạn chưa đăng nhập hoặc Token không hợp lệ (Unauthorized).'
//     ], 401);
// })->name('login'); // 👈 Quan trọng: phải đặt tên là 'login'\
Route::get('/email/verify/{id}', [UserController::class, 'verifyEmail'])->name('auth.verify');
